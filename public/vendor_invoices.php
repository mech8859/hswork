<?php
/**
 * 廠商請款單收件匣
 */
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (!Auth::hasPermission('procurement.manage') && !Auth::hasPermission('all')) {
    Session::flash('error', '無權限存取廠商請款單');
    redirect('/');
}
require_once __DIR__ . '/../modules/procurement/VendorInvoiceModel.php';

$model = new VendorInvoiceModel();
$action = isset($_GET['action']) ? $_GET['action'] : 'list';

/**
 * 把 AI service 回傳的純辨識結果，做廠商/產品比對，並補上 vendor.* 結構讓 saveRecognized 直接吃。
 */
function vendor_invoice_match_data(array $aiData): array
{
    $db = Database::getInstance();
    $vendorName = !empty($aiData['vendor_name']) ? trim($aiData['vendor_name']) : '';
    $vendorTaxId = !empty($aiData['vendor_tax_id']) ? trim($aiData['vendor_tax_id']) : '';

    $vendorMatch = null;
    $candidates = array();

    // 0) 統編完全比對（最準）
    if ($vendorTaxId && preg_match('/^\d{8}$/', $vendorTaxId)) {
        $stmt = $db->prepare("SELECT id, vendor_code, name FROM vendors WHERE tax_id = ? AND is_active = 1 LIMIT 1");
        $stmt->execute(array($vendorTaxId));
        $vendorMatch = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        if ($vendorMatch) $vendorMatch['confidence'] = 1.0;
    }

    // 1) 名稱完全比對
    if (!$vendorMatch && $vendorName) {
        $stmt = $db->prepare("SELECT id, vendor_code, name FROM vendors WHERE name = ? AND is_active = 1 LIMIT 1");
        $stmt->execute(array($vendorName));
        $vendorMatch = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        if ($vendorMatch) $vendorMatch['confidence'] = 0.95;
    }

    // 2) 名稱 LIKE
    if (!$vendorMatch && $vendorName) {
        $kw = '%' . $vendorName . '%';
        $stmt = $db->prepare("SELECT id, vendor_code, name FROM vendors WHERE (name LIKE ? OR short_name LIKE ?) AND is_active = 1 ORDER BY name LIMIT 5");
        $stmt->execute(array($kw, $kw));
        $candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!empty($candidates)) {
            $vendorMatch = $candidates[0];
            $vendorMatch['confidence'] = 0.8;
        }
    }

    // 3) 前 4 字 LIKE
    if (!$vendorMatch && $vendorName && mb_strlen($vendorName) >= 2) {
        $prefix = mb_substr($vendorName, 0, 4) . '%';
        $stmt = $db->prepare("SELECT id, vendor_code, name FROM vendors WHERE name LIKE ? AND is_active = 1 ORDER BY name LIMIT 5");
        $stmt->execute(array($prefix));
        $candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!empty($candidates)) {
            $vendorMatch = $candidates[0];
            $vendorMatch['confidence'] = 0.5;
        }
    }

    $vendorId = $vendorMatch ? (int)$vendorMatch['id'] : null;

    // 產品比對：每筆 item 加 matched_product_id
    $items = !empty($aiData['items']) ? $aiData['items'] : array();
    foreach ($items as $i => $it) {
        $model = !empty($it['model']) ? trim($it['model']) : '';
        $name = !empty($it['product_name']) ? trim($it['product_name']) : '';
        $matchedId = null;

        // 優先 vendor_products
        if ($vendorId && $model) {
            $stmt = $db->prepare("SELECT product_id FROM vendor_products WHERE vendor_id = ? AND vendor_model LIKE ? AND is_active = 1 LIMIT 1");
            $stmt->execute(array($vendorId, '%' . $model . '%'));
            $matchedId = $stmt->fetchColumn() ?: null;
        }
        // products by model exact
        if (!$matchedId && $model) {
            $stmt = $db->prepare("SELECT id FROM products WHERE model = ? AND is_active = 1 LIMIT 1");
            $stmt->execute(array($model));
            $matchedId = $stmt->fetchColumn() ?: null;
        }
        if ($matchedId) {
            $items[$i]['matched_product_id'] = (int)$matchedId;
        }
    }

    $aiData['items'] = $items;
    $aiData['vendor'] = array(
        'name'         => $vendorName,
        'tax_id'       => $vendorTaxId,
        'matched_id'   => $vendorId,
        'matched_name' => $vendorMatch ? $vendorMatch['name'] : null,
        'matched_code' => $vendorMatch ? ($vendorMatch['vendor_code'] ?? '') : '',
        'confidence'   => $vendorMatch ? $vendorMatch['confidence'] : 0,
        'candidates'   => $candidates,
    );
    return $aiData;
}

$UPLOAD_DIR = __DIR__ . '/uploads/vendor_invoices';
$REL_DIR    = 'uploads/vendor_invoices';
if (!is_dir($UPLOAD_DIR)) @mkdir($UPLOAD_DIR, 0755, true);
$ALLOWED_EXT = array('pdf', 'jpg', 'jpeg', 'png');
$MAX_SIZE = 25 * 1024 * 1024; // 25MB

switch ($action) {

    // ---- 列表 ----
    case 'list':
        $statusFilter = isset($_GET['status']) ? $_GET['status'] : 'pending'; // 預設「待辨識」
        if (!in_array($statusFilter, array('pending', 'recognized', 'confirmed', ''))) $statusFilter = 'pending';
        $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;

        $result = $model->getList($statusFilter, $page);
        $statusCounts = $model->getStatusCounts();

        $pageTitle = '廠商請款單';
        $currentPage = 'vendor_invoices';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/vendor_invoices/list.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    // ---- 上傳（多檔）----
    case 'upload':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf()) {
            Session::flash('error', '安全驗證失敗');
            redirect('/vendor_invoices.php');
        }
        if (empty($_FILES['files'])) {
            Session::flash('error', '請選擇檔案');
            redirect('/vendor_invoices.php');
        }

        $files = $_FILES['files'];
        $count = is_array($files['name']) ? count($files['name']) : 0;
        $userId = Auth::id();
        $okCount = 0;
        $errors = array();

        for ($i = 0; $i < $count; $i++) {
            $name = $files['name'][$i];
            $tmp = $files['tmp_name'][$i];
            $size = (int)$files['size'][$i];
            $err = (int)$files['error'][$i];

            if ($err !== UPLOAD_ERR_OK) {
                $errors[] = "{$name}: 上傳錯誤碼 {$err}";
                continue;
            }
            if ($size > $MAX_SIZE) {
                $errors[] = "{$name}: 超過 25MB";
                continue;
            }
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if (!in_array($ext, $ALLOWED_EXT, true)) {
                $errors[] = "{$name}: 不支援的格式（限 PDF/JPG/PNG）";
                continue;
            }
            $newName = date('Ymd_His') . '_' . substr(md5(uniqid('', true)), 0, 8) . '.' . $ext;
            $absPath = $UPLOAD_DIR . '/' . $newName;
            if (!move_uploaded_file($tmp, $absPath)) {
                $errors[] = "{$name}: 移動檔案失敗";
                continue;
            }

            try {
                $model->create(array(
                    'file_path'   => $REL_DIR . '/' . $newName,
                    'file_name'   => $name,
                    'file_size'   => $size,
                    'file_pages'  => null,
                    'uploaded_by' => $userId,
                ));
                $okCount++;
            } catch (Exception $e) {
                $errors[] = "{$name}: " . $e->getMessage();
                @unlink($absPath);
            }
        }

        if ($okCount > 0) {
            AuditLog::log('vendor_invoices', 'upload', 0, "上傳 {$okCount} 張請款單");
            Session::flash('success', "已上傳 {$okCount} 張請款單到「待辨識」分頁");
        }
        if (!empty($errors)) {
            Session::flash('error', '部分檔案失敗：' . implode('; ', $errors));
        }
        redirect('/vendor_invoices.php?status=pending');
        break;

    // ---- 檢視（顯示原圖 + 辨識結果預覽）----
    case 'view':
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        $record = $model->getById($id);
        if (!$record) {
            Session::flash('error', '紀錄不存在');
            redirect('/vendor_invoices.php');
        }
        $items = $model->getItems($id);

        $pageTitle = '請款單檢視 - ' . ($record['file_name'] ?: '#' . $id);
        $currentPage = 'vendor_invoices';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/vendor_invoices/view.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    // ---- 下載原檔 ----
    case 'download':
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        $record = $model->getById($id);
        if (!$record) { http_response_code(404); echo '不存在'; exit; }
        $abs = __DIR__ . '/' . $record['file_path'];
        if (!is_file($abs)) { http_response_code(404); echo '檔案遺失'; exit; }
        $ext = strtolower(pathinfo($record['file_name'], PATHINFO_EXTENSION));
        $mime = $ext === 'pdf' ? 'application/pdf'
              : (in_array($ext, array('jpg','jpeg'), true) ? 'image/jpeg'
              : ($ext === 'png' ? 'image/png' : 'application/octet-stream'));
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($abs));
        header('Content-Disposition: inline; filename="' . rawurlencode($record['file_name']) . '"');
        header('Cache-Control: private, max-age=0');
        readfile($abs);
        exit;

    // ---- AI 辨識 ----
    case 'recognize':
        @set_time_limit(180);
        @ignore_user_abort(true);
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        try {
            $record = $model->getById($id);
            if (!$record) {
                Session::flash('error', '紀錄不存在');
                redirect('/vendor_invoices.php');
            }
            $abs = __DIR__ . '/' . $record['file_path'];
            if (!is_file($abs)) {
                Session::flash('error', '檔案遺失，無法辨識');
                redirect('/vendor_invoices.php?action=view&id=' . $id);
            }

            $aiServiceUrl = 'http://114.35.174.204:1500/api/recognize/vendor-invoice';
            $aiToken = 'hswork-ai-2026';

            $ext = strtolower(pathinfo($record['file_name'], PATHINFO_EXTENSION));
            $mime = $ext === 'pdf' ? 'application/pdf'
                  : (in_array($ext, array('jpg','jpeg'), true) ? 'image/jpeg'
                  : ($ext === 'png' ? 'image/png' : 'application/octet-stream'));

            $ch = curl_init();
            $cFile = curl_file_create($abs, $mime, $record['file_name']);
            curl_setopt_array($ch, array(
                CURLOPT_URL => $aiServiceUrl,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => array('image' => $cFile),
                CURLOPT_HTTPHEADER => array('X-AI-Token: ' . $aiToken),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 150,
                CURLOPT_CONNECTTIMEOUT => 10,
            ));
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($curlError) {
                Session::flash('error', 'AI 服務連線失敗：' . $curlError);
                redirect('/vendor_invoices.php?action=view&id=' . $id);
            }
            $aiData = json_decode((string)$response, true);
            if ($httpCode !== 200) {
                $detail = '';
                if (is_array($aiData) && !empty($aiData['error'])) $detail = $aiData['error'];
                if (!$detail) $detail = substr((string)$response, 0, 300);
                Session::flash('error', 'AI 辨識失敗（HTTP ' . $httpCode . '）：' . $detail);
                redirect('/vendor_invoices.php?action=view&id=' . $id);
            }
            if (empty($aiData['success'])) {
                $detail = !empty($aiData['error']) ? $aiData['error'] : '回傳格式錯誤';
                Session::flash('error', 'AI 辨識失敗：' . $detail);
                redirect('/vendor_invoices.php?action=view&id=' . $id);
            }

            // PHP 端做廠商 + 產品比對（DB 在 hswork，AI service 沒 DB 連線）
            $aiData = vendor_invoice_match_data($aiData);

            $model->saveRecognized($id, $aiData);
            AuditLog::log('vendor_invoices', 'recognize', $id, '辨識完成 ' . $record['file_name']);
            Session::flash('success', '辨識完成，請核對 AI 結果後按「確認」');
            redirect('/vendor_invoices.php?action=view&id=' . $id);
        } catch (Throwable $e) {
            error_log('[vendor_invoices recognize] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
            Session::flash('error', '辨識流程錯誤：' . $e->getMessage());
            redirect('/vendor_invoices.php?action=view&id=' . $id);
        }
        break;

    // ---- 確認（recognized → confirmed）----
    case 'confirm':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf()) {
            Session::flash('error', '安全驗證失敗');
            redirect('/vendor_invoices.php');
        }
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $record = $model->getById($id);
        if (!$record) {
            Session::flash('error', '紀錄不存在');
            redirect('/vendor_invoices.php');
        }
        if ($record['status'] !== 'recognized' && !Auth::hasPermission('all')) {
            Session::flash('error', '只有「待確認」狀態可以確認');
            redirect('/vendor_invoices.php?action=view&id=' . $id);
        }

        $headerEdit = array(
            'vendor_id'      => isset($_POST['vendor_id']) ? (int)$_POST['vendor_id'] : null,
            'invoice_date'   => isset($_POST['invoice_date']) ? trim($_POST['invoice_date']) : null,
            'invoice_number' => isset($_POST['invoice_number']) ? trim($_POST['invoice_number']) : null,
            'total_amount'   => isset($_POST['total_amount']) ? trim($_POST['total_amount']) : null,
            'note'           => isset($_POST['note']) ? trim($_POST['note']) : null,
        );

        $itemEdits = array();
        if (!empty($_POST['items']) && is_array($_POST['items'])) {
            foreach ($_POST['items'] as $row) {
                if (empty($row['id'])) continue;
                $itemEdits[] = array(
                    'id'                 => (int)$row['id'],
                    'matched_product_id' => !empty($row['matched_product_id']) ? (int)$row['matched_product_id'] : null,
                    'final_model'        => isset($row['final_model']) ? trim($row['final_model']) : null,
                    'final_name'         => isset($row['final_name']) ? trim($row['final_name']) : null,
                    'final_qty'          => isset($row['final_qty']) ? trim($row['final_qty']) : null,
                    'final_unit'         => isset($row['final_unit']) ? trim($row['final_unit']) : null,
                    'final_unit_price'   => isset($row['final_unit_price']) ? trim($row['final_unit_price']) : null,
                    'final_amount'       => isset($row['final_amount']) ? trim($row['final_amount']) : null,
                );
            }
        }

        try {
            $model->confirm($id, $headerEdit, $itemEdits, (int)Auth::id());
            AuditLog::log('vendor_invoices', 'confirm', $id, '確認請款單 ' . $record['file_name']);
            Session::flash('success', '已確認，價格紀錄已寫入');
            redirect('/vendor_invoices.php?status=confirmed');
        } catch (Exception $e) {
            Session::flash('error', '確認失敗：' . $e->getMessage());
            redirect('/vendor_invoices.php?action=view&id=' . $id);
        }
        break;

    // ---- 狀態查詢（AJAX 輪詢用）----
    case 'status':
        header('Content-Type: application/json');
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        $rec = $model->getById($id);
        if (!$rec) {
            echo json_encode(array('ok' => false, 'error' => 'not_found'));
            exit;
        }
        echo json_encode(array(
            'ok'             => true,
            'id'             => (int)$rec['id'],
            'status'         => $rec['status'],
            'recognized_at'  => $rec['recognized_at'],
            'updated_at'     => $rec['updated_at'] ?? null,
        ));
        exit;

    // ---- 退回為待辨識 ----
    case 'reset':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf()) {
            Session::flash('error', '安全驗證失敗');
            redirect('/vendor_invoices.php');
        }
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        try {
            $model->resetToPending($id);
            AuditLog::log('vendor_invoices', 'reset', $id, '退回待辨識');
            Session::flash('success', '已退回「待辨識」，可重新辨識');
        } catch (Exception $e) {
            Session::flash('error', '失敗：' . $e->getMessage());
        }
        redirect('/vendor_invoices.php?action=view&id=' . $id);
        break;

    // ---- 刪除 ----
    case 'delete':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf()) {
            Session::flash('error', '安全驗證失敗');
            redirect('/vendor_invoices.php');
        }
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $record = $model->getById($id);
        if (!$record) {
            Session::flash('error', '紀錄不存在');
            redirect('/vendor_invoices.php');
        }
        if ($record['status'] === 'confirmed' && !Auth::hasPermission('all')) {
            Session::flash('error', '已確認的請款單不可刪除');
            redirect('/vendor_invoices.php?status=confirmed');
        }
        try {
            $model->delete($id);
            AuditLog::log('vendor_invoices', 'delete', $id, '刪除請款單 ' . $record['file_name']);
            Session::flash('success', '已刪除');
        } catch (Exception $e) {
            Session::flash('error', '刪除失敗：' . $e->getMessage());
        }
        redirect('/vendor_invoices.php?status=' . $record['status']);
        break;

    default:
        redirect('/vendor_invoices.php');
        break;
}
