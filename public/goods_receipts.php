<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
require_once __DIR__ . '/../modules/procurement/GoodsReceiptModel.php';
require_once __DIR__ . '/../modules/inventory/InventoryModel.php';

$model = new GoodsReceiptModel();
$invModel = new InventoryModel();
$action = !empty($_GET['action']) ? $_GET['action'] : 'list';

switch ($action) {
    case 'list':
        $filters = array(
            'status'      => !empty($_GET['status']) ? $_GET['status'] : '',
            'vendor_name' => !empty($_GET['vendor_name']) ? $_GET['vendor_name'] : '',
            'keyword'     => !empty($_GET['keyword']) ? $_GET['keyword'] : '',
            'date_from'   => !empty($_GET['date_from']) ? $_GET['date_from'] : '',
            'date_to'     => !empty($_GET['date_to']) ? $_GET['date_to'] : '',
            'warehouse_id' => !empty($_GET['warehouse_id']) ? $_GET['warehouse_id'] : '',
        );
        $page = max(1, (int)(!empty($_GET['page']) ? $_GET['page'] : 1));
        $result = $model->getList($filters, $page, 100);
        $records = $result['data'];
        $pagination = $result;
        $warehouses = $invModel->getWarehouses();

        $pageTitle = '進貨單';
        $currentPage = 'goods_receipts';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/goods_receipts/list.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    case 'create':
        $fromPoId = !empty($_GET['from_po']) ? (int)$_GET['from_po'] : 0;
        $prefill = null;
        if ($fromPoId > 0) {
            $prefill = $model->createFromPO($fromPoId);
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!verify_csrf()) {
                Session::flash('error', '安全驗證失敗');
                redirect('/goods_receipts.php');
            }

            // 強制廠商必選 (vendor_id 必填)
            if (empty($_POST['vendor_id']) || empty($_POST['vendor_name'])) {
                Session::flash('error', '廠商必須從廠商管理選擇，不可手動輸入或留空');
                redirect('/goods_receipts.php?action=create' . ($fromPoId > 0 ? '&from_po=' . $fromPoId : ''));
            }

            $userId = Session::getUser()['id'];
            $data = array(
                'gr_date'       => !empty($_POST['gr_date']) ? $_POST['gr_date'] : date('Y-m-d'),
                'status'        => !empty($_POST['status']) ? $_POST['status'] : '草稿',
                'po_id'         => !empty($_POST['po_id']) ? $_POST['po_id'] : null,
                'po_number'     => !empty($_POST['po_number']) ? $_POST['po_number'] : null,
                'vendor_id'     => !empty($_POST['vendor_id']) ? $_POST['vendor_id'] : null,
                'vendor_name'   => !empty($_POST['vendor_name']) ? $_POST['vendor_name'] : null,
                'warehouse_id'  => !empty($_POST['warehouse_id']) ? $_POST['warehouse_id'] : null,
                'receiver_name' => !empty($_POST['receiver_name']) ? $_POST['receiver_name'] : null,
                'note'          => !empty($_POST['note']) ? $_POST['note'] : null,
                'total_qty'     => !empty($_POST['total_qty']) ? $_POST['total_qty'] : 0,
                'total_amount'  => !empty($_POST['total_amount']) ? $_POST['total_amount'] : 0,
                'created_by'    => $userId,
                'items'         => !empty($_POST['items']) ? $_POST['items'] : array(),
            );

            $grId = $model->create($data);

            // 從採購單轉入時，更新採購單狀態為「已轉進貨單」
            if (!empty($data['po_id'])) {
                $db = Database::getInstance();
                $db->prepare("UPDATE purchase_orders SET status = '已轉進貨單' WHERE id = ?")->execute(array($data['po_id']));
            }

            Session::flash('success', '進貨單已新增');
            redirect('/goods_receipts.php?action=edit&id=' . $grId);
        }

        $record = $prefill ? $prefill['header'] : null;
        $items = $prefill ? $prefill['items'] : array();
        $warehouses = $invModel->getWarehouses();
        $branches = Database::getInstance()->query('SELECT id, name FROM branches WHERE is_active = 1 ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
        $pendingPOs = $model->getPendingPOs();

        $pageTitle = '新增進貨單';
        $currentPage = 'goods_receipts';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/goods_receipts/form.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    case 'edit':
        $id = (int)(!empty($_GET['id']) ? $_GET['id'] : 0);
        $record = $model->getById($id);
        if (!$record) {
            Session::flash('error', '進貨單不存在');
            redirect('/goods_receipts.php');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!verify_csrf()) {
                Session::flash('error', '安全驗證失敗');
                redirect('/goods_receipts.php?action=edit&id=' . $id);
            }

            // 強制廠商必選（編輯時）
            // 註：歷史單舊資料 vendor_id 為空也允許保留，只擋「使用者改了廠商欄位卻沒帶 ID」的情況
            //     判斷邏輯：如果 vendor_name 改變了 → 必須有 vendor_id
            $oldVendorName = isset($record['vendor_name']) ? trim($record['vendor_name']) : '';
            $newVendorName = isset($_POST['vendor_name']) ? trim($_POST['vendor_name']) : '';
            if ($newVendorName !== '' && $newVendorName !== $oldVendorName && empty($_POST['vendor_id'])) {
                Session::flash('error', '廠商必須從廠商管理選擇，不可手動輸入');
                redirect('/goods_receipts.php?action=edit&id=' . $id);
            }
            if ($newVendorName === '') {
                Session::flash('error', '廠商不可留空');
                redirect('/goods_receipts.php?action=edit&id=' . $id);
            }

            $userId = Session::getUser()['id'];
            $data = array(
                'gr_date'       => !empty($_POST['gr_date']) ? $_POST['gr_date'] : date('Y-m-d'),
                'status'        => !empty($_POST['status']) ? $_POST['status'] : $record['status'],
                'po_id'         => !empty($_POST['po_id']) ? $_POST['po_id'] : null,
                'po_number'     => !empty($_POST['po_number']) ? $_POST['po_number'] : null,
                'vendor_id'     => !empty($_POST['vendor_id']) ? $_POST['vendor_id'] : null,
                'vendor_name'   => !empty($_POST['vendor_name']) ? $_POST['vendor_name'] : null,
                'warehouse_id'  => !empty($_POST['warehouse_id']) ? $_POST['warehouse_id'] : null,
                'receiver_name' => !empty($_POST['receiver_name']) ? $_POST['receiver_name'] : null,
                'note'          => !empty($_POST['note']) ? $_POST['note'] : null,
                'total_qty'     => !empty($_POST['total_qty']) ? $_POST['total_qty'] : 0,
                'total_amount'  => !empty($_POST['total_amount']) ? $_POST['total_amount'] : 0,
                'updated_by'    => $userId,
                'items'         => isset($_POST['items']) ? $_POST['items'] : array(),
            );

            $model->update($id, $data);
            Session::flash('success', '進貨單已更新');
            redirect('/goods_receipts.php?action=edit&id=' . $id);
        }

        $items = $model->getItems($id);
        $warehouses = $invModel->getWarehouses();
        $branches = Database::getInstance()->query('SELECT id, name FROM branches WHERE is_active = 1 ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
        $pendingPOs = $model->getPendingPOs();

        $pageTitle = '編輯進貨單 - ' . $record['gr_number'];
        $currentPage = 'goods_receipts';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/goods_receipts/form.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    case 'view':
        $id = (int)(!empty($_GET['id']) ? $_GET['id'] : 0);
        $record = $model->getById($id);
        if (!$record) {
            Session::flash('error', '進貨單不存在');
            redirect('/goods_receipts.php');
        }
        $items = $model->getItems($id);

        $pageTitle = '進貨單 - ' . $record['gr_number'];
        $currentPage = 'goods_receipts';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/goods_receipts/view.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    case 'confirm':
        $id = (int)(!empty($_GET['id']) ? $_GET['id'] : 0);
        if ($id > 0) {
            try {
                $userId = Session::getUser()['id'];
                $stockInId = $model->confirm($id, $userId);
                if ($stockInId) {
                    Session::flash('success', '進貨單已確認，入庫單已自動建立');
                } else {
                    Session::flash('error', '確認失敗：進貨單狀態不正確或無明細');
                }
            } catch (Exception $e) {
                Session::flash('error', '確認失敗: ' . $e->getMessage());
            }
        }
        redirect('/goods_receipts.php?action=view&id=' . $id);
        break;

    case 'delete':
        if (!Auth::hasPermission('inventory.delete')) {
            Session::flash('error', '無刪除權限');
            redirect('/goods_receipts.php');
        }
        $id = (int)(!empty($_GET['id']) ? $_GET['id'] : 0);
        if ($id > 0) {
            if ($model->delete($id)) {
                Session::flash('success', '進貨單已刪除');
            } else {
                Session::flash('error', '僅草稿狀態的進貨單可刪除');
            }
        }
        redirect('/goods_receipts.php');
        break;

    case 'create_from_po':
        $poId = (int)(!empty($_GET['po_id']) ? $_GET['po_id'] : 0);
        if ($poId > 0) {
            redirect('/goods_receipts.php?action=create&from_po=' . $poId);
        }
        redirect('/goods_receipts.php');
        break;

    // ============================================================
    // ADMIN_TOOL_BLOCK_START - 測試期專用，完成後可整段移除
    // ============================================================
    case 'admin_delete':
        $u = Auth::user();
        if (!$u || $u['role'] !== 'boss') {
            Session::flash('error', '無權限執行此操作（僅系統管理者）');
            redirect('/goods_receipts.php');
        }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf()) {
            Session::flash('error', '請從畫面操作');
            redirect('/goods_receipts.php');
        }
        $id = (int)(!empty($_POST['id']) ? $_POST['id'] : 0);
        if (!$id) { Session::flash('error', '參數錯誤'); redirect('/goods_receipts.php'); }
        $reasons = $model->checkDeletable($id);
        if (!empty($reasons)) {
            Session::flash('error', '無法刪除：' . implode('；', $reasons));
            redirect('/goods_receipts.php?action=view&id=' . $id);
        }
        try {
            $model->deleteHard($id);
            AuditLog::log('goods_receipts', 'admin_delete', $id, '管理者刪除整張進貨單');
            Session::flash('success', '進貨單已刪除');
        } catch (Exception $e) {
            Session::flash('error', '刪除失敗：' . $e->getMessage());
        }
        redirect('/goods_receipts.php');
        break;

    case 'admin_edit_basic':
        $u = Auth::user();
        if (!$u || $u['role'] !== 'boss') {
            Session::flash('error', '無權限執行此操作（僅系統管理者）');
            redirect('/goods_receipts.php');
        }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf()) {
            Session::flash('error', '請從畫面操作');
            redirect('/goods_receipts.php');
        }
        $id = (int)(!empty($_POST['id']) ? $_POST['id'] : 0);
        if (!$id) { Session::flash('error', '參數錯誤'); redirect('/goods_receipts.php'); }
        try {
            $model->updateBasic($id, array(
                'vendor_name' => trim(!empty($_POST['vendor_name']) ? $_POST['vendor_name'] : ''),
                'vendor_id'   => (int)(!empty($_POST['vendor_id']) ? $_POST['vendor_id'] : 0),
            ));
            AuditLog::log('goods_receipts', 'admin_edit_basic', $id, '管理者修改廠商: ' . $_POST['vendor_name']);
            Session::flash('success', '廠商已更新');
        } catch (Exception $e) {
            Session::flash('error', '更新失敗：' . $e->getMessage());
        }
        redirect('/goods_receipts.php?action=view&id=' . $id);
        break;
    // ADMIN_TOOL_BLOCK_END

    // ---- AI 辨識代理 ----
    case 'ajax_ai_recognize':
        header('Content-Type: application/json');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(array('success' => false, 'error' => 'POST only'));
            exit;
        }
        if (empty($_FILES['image'])) {
            echo json_encode(array('success' => false, 'error' => '請上傳圖片'));
            exit;
        }

        $aiServiceUrl = 'http://114.35.174.204:1500/api/recognize/test';
        $aiToken = 'hswork-ai-2026';

        $file = $_FILES['image'];
        $tmpPath = $file['tmp_name'];
        $mimeType = !empty($file['type']) ? $file['type'] : 'image/jpeg';
        $fileName = !empty($file['name']) ? $file['name'] : 'image.jpg';

        // 用 cURL 轉發到 ai-service（純辨識模式）
        $ch = curl_init();
        if (function_exists('curl_file_create')) {
            $cFile = curl_file_create($tmpPath, $mimeType, $fileName);
        } else {
            $cFile = '@' . realpath($tmpPath) . ';type=' . $mimeType . ';filename=' . $fileName;
        }
        curl_setopt_array($ch, array(
            CURLOPT_URL => $aiServiceUrl,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => array('image' => $cFile),
            CURLOPT_HTTPHEADER => array('X-AI-Token: ' . $aiToken),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_CONNECTTIMEOUT => 10,
        ));
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            echo json_encode(array('success' => false, 'error' => 'AI 服務連線失敗：' . $curlError));
            exit;
        }
        if ($httpCode !== 200) {
            $err = json_decode($response, true);
            echo json_encode(array('success' => false, 'error' => 'AI 服務錯誤 (HTTP ' . $httpCode . ')', 'detail' => !empty($err['error']) ? $err['error'] : $response));
            exit;
        }

        // 解析 AI 辨識結果，在 PHP 端做廠商/產品比對
        $aiData = json_decode($response, true);
        if (empty($aiData['success']) || empty($aiData['result'])) {
            echo $response;
            exit;
        }

        $raw = $aiData['result'];
        $db = Database::getInstance();

        // 廠商比對
        $vendorName = !empty($raw['vendor_name']) ? $raw['vendor_name'] : '';
        $vendorMatch = null;
        if ($vendorName) {
            // 精確比對
            $stmt = $db->prepare("SELECT id, vendor_code, name FROM vendors WHERE name = ? AND is_active = 1 LIMIT 1");
            $stmt->execute(array($vendorName));
            $vendorMatch = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$vendorMatch) {
                // 模糊比對
                $stmt = $db->prepare("SELECT id, vendor_code, name FROM vendors WHERE (name LIKE ? OR short_name LIKE ?) AND is_active = 1 LIMIT 1");
                $kw = '%' . $vendorName . '%';
                $stmt->execute(array($kw, $kw));
                $vendorMatch = $stmt->fetch(PDO::FETCH_ASSOC);
            }
            if (!$vendorMatch) {
                // 前4字比對
                $prefix = mb_substr($vendorName, 0, 4);
                $stmt = $db->prepare("SELECT id, vendor_code, name FROM vendors WHERE name LIKE ? AND is_active = 1 LIMIT 1");
                $stmt->execute(array($prefix . '%'));
                $vendorMatch = $stmt->fetch(PDO::FETCH_ASSOC);
            }
        }

        // 品項比對（用 vendor_products 或 products 表）
        $items = !empty($raw['items']) ? $raw['items'] : array();
        $matchedItems = array();
        $vendorId = $vendorMatch ? $vendorMatch['id'] : null;

        foreach ($items as $item) {
            $matched = $item;
            $matched['product_id'] = null;
            $matched['match_source'] = null;
            $aiModel = !empty($item['model']) ? $item['model'] : '';
            $aiName = !empty($item['product_name']) ? $item['product_name'] : '';

            if ($vendorId && $aiModel) {
                // 先查 vendor_products
                $stmt = $db->prepare("SELECT vp.product_id, vp.vendor_model, vp.vendor_name, p.name as product_name, p.model as product_model
                    FROM vendor_products vp LEFT JOIN products p ON vp.product_id = p.id
                    WHERE vp.vendor_id = ? AND vp.vendor_model LIKE ? LIMIT 1");
                $stmt->execute(array($vendorId, '%' . $aiModel . '%'));
                $vpMatch = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($vpMatch) {
                    $matched['product_id'] = $vpMatch['product_id'];
                    $matched['product_name'] = $vpMatch['product_name'] ? $vpMatch['product_name'] : $aiName;
                    $matched['model'] = $vpMatch['product_model'] ? $vpMatch['product_model'] : $aiModel;
                    $matched['match_source'] = 'vendor_products';
                }
            }

            if (!$matched['product_id'] && $aiModel) {
                // 查 products 表 by model
                $stmt = $db->prepare("SELECT id, name, model, unit FROM products WHERE model LIKE ? AND is_active = 1 LIMIT 1");
                $stmt->execute(array('%' . $aiModel . '%'));
                $pMatch = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($pMatch) {
                    $matched['product_id'] = $pMatch['id'];
                    $matched['product_name'] = $pMatch['name'];
                    $matched['model'] = $pMatch['model'];
                    if (!empty($pMatch['unit'])) $matched['unit'] = $pMatch['unit'];
                    $matched['match_source'] = 'products_model';
                }
            }

            $matchedItems[] = $matched;
        }

        // 組合回傳
        $result = array(
            'success' => true,
            'vendor' => array(
                'name' => $vendorName,
                'matched_id' => $vendorMatch ? $vendorMatch['id'] : null,
                'matched_name' => $vendorMatch ? $vendorMatch['name'] : null,
                'matched_code' => $vendorMatch ? $vendorMatch['vendor_code'] : null,
                'confidence' => $vendorMatch ? 0.9 : 0,
            ),
            'date' => !empty($raw['date']) ? $raw['date'] : '',
            'invoice_number' => !empty($raw['invoice_number']) ? $raw['invoice_number'] : '',
            'items' => $matchedItems,
            'subtotal' => !empty($raw['subtotal']) ? $raw['subtotal'] : 0,
            'tax' => !empty($raw['tax']) ? $raw['tax'] : 0,
            'total' => !empty($raw['total']) ? $raw['total'] : 0,
        );

        echo json_encode($result);
        exit;

    // ---- 廠商搜尋 AJAX（獨立 endpoint，不依賴 finance 權限）----
    case 'ajax_vendor_search':
        header('Content-Type: application/json');
        $q = trim($_GET['q'] ?? '');
        if (strlen($q) < 1) { echo '[]'; exit; }
        $kw = '%' . $q . '%';
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT id, vendor_code, name, contact_person, phone, tax_id, fax, email, address FROM vendors WHERE (name LIKE ? OR vendor_code LIKE ? OR contact_person LIKE ?) AND is_active = 1 ORDER BY name LIMIT 10");
        $stmt->execute(array($kw, $kw, $kw));
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (empty($results)) {
            $stmt2 = $db->prepare("SELECT id, '' as vendor_code, name, contact_person, phone FROM outsource_vendors WHERE name LIKE ? AND is_active = 1 ORDER BY name LIMIT 10");
            $stmt2->execute(array($kw));
            $results = $stmt2->fetchAll(PDO::FETCH_ASSOC);
        }
        echo json_encode($results);
        exit;

    default:
        redirect('/goods_receipts.php');
        break;
}
