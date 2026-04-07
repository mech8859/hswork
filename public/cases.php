<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
require_once __DIR__ . '/../modules/cases/CaseModel.php';

$model = new CaseModel();
$action = $_GET['action'] ?? 'list';
$branchIds = Auth::getAccessibleBranchIds();

// 帳款交易合計回寫 total_collected + 訂金金額/方式
function updateTotalCollected($caseId) {
    $db = Database::getInstance();
    // 總收款
    $stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) FROM case_payments WHERE case_id = ?");
    $stmt->execute(array($caseId));
    $total = (int)$stmt->fetchColumn();
    // 訂金（類別=訂金的合計 + 最新一筆的支付方式）
    $depStmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) FROM case_payments WHERE case_id = ? AND payment_type = '訂金'");
    $depStmt->execute(array($caseId));
    $depositAmount = (int)$depStmt->fetchColumn();
    $depMethodStmt = $db->prepare("SELECT transaction_type FROM case_payments WHERE case_id = ? AND payment_type = '訂金' ORDER BY payment_date DESC, id DESC LIMIT 1");
    $depMethodStmt->execute(array($caseId));
    $depositMethod = $depMethodStmt->fetchColumn() ?: null;
    // 訂金日期
    $depDateStmt = $db->prepare("SELECT payment_date FROM case_payments WHERE case_id = ? AND payment_type = '訂金' ORDER BY payment_date DESC, id DESC LIMIT 1");
    $depDateStmt->execute(array($caseId));
    $depositDate = $depDateStmt->fetchColumn() ?: null;
    $db->prepare("UPDATE cases SET total_collected = ?, deposit_amount = ?, deposit_method = ?, deposit_payment_date = ? WHERE id = ?")
        ->execute(array($total, $depositAmount ?: null, $depositMethod, $depositDate, $caseId));
}

switch ($action) {
    // ---- 案件清單 ----
    case 'list':
        $filters = [
            'status'     => $_GET['status'] ?? '',
            'case_type'  => $_GET['case_type'] ?? '',
            'keyword'    => $_GET['keyword'] ?? '',
            'branch_id'  => $_GET['branch_id'] ?? '',
            'sub_status' => $_GET['sub_status'] ?? '',
            'sales_id'   => $_GET['sales_id'] ?? '',
            'date_from'  => $_GET['date_from'] ?? '',
            'date_to'    => $_GET['date_to'] ?? '',
        ];
        $page = max(1, (int)($_GET['page'] ?? 1));
        $result = $model->getList($branchIds, $filters, $page);
        $branches = $model->getAllBranches();
        $salesUsers = $model->getSalesUsers($branchIds);

        $pageTitle = '案件管理';
        $currentPage = 'cases';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/cases/list.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    // ---- 新增案件 ----
    case 'create':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!verify_csrf()) {
                Session::flash('error', '安全驗證失敗');
                redirect('/cases.php');
            }
            $caseId = $model->create($_POST);
            $model->updateReadiness($caseId, $_POST);
            $model->updateSiteConditions($caseId, $_POST);
            if (!empty($_POST['contacts'])) {
                $model->saveContacts($caseId, $_POST['contacts']);
            }
            if (!empty($_POST['required_skills'])) {
                $model->saveRequiredSkills($caseId, $_POST['required_skills']);
            }
            if (isset($_POST['est_materials'])) {
                $model->saveMaterialEstimates($caseId, $_POST['est_materials']);
            }
            Session::flash('success', '案件已新增');
            redirect('/cases.php?action=view&id=' . $caseId);
        }

        $case = null;
        $worklogTimeline = array();
        $branches = $model->getAllBranches();
        $skills = $model->getAllSkills();
        $salesUsers = $model->getSalesUsers($branchIds);
        require_once __DIR__ . '/../modules/settings/DropdownModel.php';
        $ddModel = new DropdownModel();
        $caseCompanyOptions = $ddModel->getOptions('case_company');
        $caseSourceOptions = $ddModel->getOptions('case_source');
        $customerDemandOptions = $ddModel->getOptions('customer_demand');
        $systemTypeOptions = $ddModel->getOptions('system_type');
        $extraCss = array('/css/cases-form.css?v=20260403');
        $extraJs = array('/js/cases-form.js?v=20260403', '/js/tw_districts.js');
        $extraHeadHtml = '<script>var CASE_DATA={contactCount:0,caseId:0};</script>';

        $pageTitle = '新增案件';
        $currentPage = 'cases';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/cases/form.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    // ---- 編輯案件 ----
    case 'edit':
        $id = (int)($_GET['id'] ?? 0);
        $case = $model->getById($id);
        if (!$case) {
            Session::flash('error', '案件不存在');
            redirect('/cases.php');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!verify_csrf()) {
                Session::flash('error', '安全驗證失敗');
                redirect('/cases.php?action=edit&id=' . $id);
            }
            try {
                $model->update($id, $_POST);
            } catch (\RuntimeException $e) {
                Session::flash('error', $e->getMessage());
                redirect('/cases.php?action=edit&id=' . $id);
            }
            $model->updateReadiness($id, $_POST);
            $model->updateSiteConditions($id, $_POST);
            if (isset($_POST['contacts'])) {
                $model->saveContacts($id, $_POST['contacts']);
            }
            if (isset($_POST['required_skills'])) {
                $model->saveRequiredSkills($id, $_POST['required_skills']);
            }
            if (isset($_POST['est_materials'])) {
                $model->saveMaterialEstimates($id, $_POST['est_materials']);
            }
            Session::flash('success', '案件已更新');
            redirect('/cases.php?action=view&id=' . $id);
        }

        $worklogTimeline = array();
        $contacts = $case['contacts'] ?? array();
        $branches = $model->getAllBranches();
        $skills = $model->getAllSkills();
        $salesUsers = $model->getSalesUsers($branchIds);
        require_once __DIR__ . '/../modules/settings/DropdownModel.php';
        $ddModel = new DropdownModel();
        $caseCompanyOptions = $ddModel->getOptions('case_company');
        $caseSourceOptions = $ddModel->getOptions('case_source');
        $customerDemandOptions = $ddModel->getOptions('customer_demand');
        $systemTypeOptions = $ddModel->getOptions('system_type');
        $extraCss = array('/css/cases-form.css?v=20260403');
        $extraJs = array('/js/cases-form.js?v=20260403', '/js/tw_districts.js');
        $extraHeadHtml = '<script>var CASE_DATA={contactCount:' . count($contacts) . ',caseId:' . $case['id'] . '};</script>';

        $pageTitle = '編輯案件';
        $currentPage = 'cases';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/cases/form.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    // ---- 案件詳情 ----
    case 'view':
        $id = (int)($_GET['id'] ?? 0);
        $case = $model->getById($id);
        if (!$case) {
            Session::flash('error', '案件不存在');
            redirect('/cases.php');
        }

        $pageTitle = $case['case_number'] . ' - ' . $case['title'];
        $currentPage = 'cases';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/cases/view.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    // ---- 刪除案件 ----
    case 'delete':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!verify_csrf()) {
                Session::flash('error', '安全驗證失敗');
                redirect('/cases.php');
            }
            if (!Auth::canEditSection('delete')) {
                Session::flash('error', '無刪除權限');
                redirect('/cases.php');
            }
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0) {
                $model->deleteCase($id);
                Session::flash('success', '案件已刪除');
            }
        }
        redirect('/cases.php');
        break;

    // ---- AJAX: 取得預估材料 ----
    case 'get_material_estimates':
        header('Content-Type: application/json');
        $caseId = isset($_GET['case_id']) ? (int)$_GET['case_id'] : 0;
        echo json_encode(array('success' => true, 'data' => $model->getMaterialEstimates($caseId)));
        exit;

    // ---- AJAX: 搜尋產品（線材&配件）----
    case 'search_products':
        header('Content-Type: application/json');
        $keyword = isset($_GET['q']) ? trim($_GET['q']) : '';
        if (mb_strlen($keyword) < 1) { echo json_encode(array()); exit; }
        $db = Database::getInstance();
        $like = '%' . $keyword . '%';
        // 限定分類：線材&相關配件、五金配件及其所有子分類
        $catIds = array();
        $parentNames = array('線材&相關配件', '五金配件');
        foreach ($parentNames as $pn) {
            $pStmt = $db->prepare("SELECT id FROM product_categories WHERE name = ?");
            $pStmt->execute(array($pn));
            $parentId = $pStmt->fetchColumn();
            if ($parentId) {
                $catIds[] = (int)$parentId;
                $childStmt = $db->prepare("SELECT id FROM product_categories WHERE parent_id = ?");
                $childStmt->execute(array($parentId));
                while ($cid = $childStmt->fetchColumn()) {
                    $catIds[] = (int)$cid;
                    // 第三層
                    $grandStmt = $db->prepare("SELECT id FROM product_categories WHERE parent_id = ?");
                    $grandStmt->execute(array($cid));
                    while ($gid = $grandStmt->fetchColumn()) {
                        $catIds[] = (int)$gid;
                    }
                }
            }
        }
        if (empty($catIds)) {
            // fallback: 搜全部
            $stmt = $db->prepare("SELECT id, name, model AS model_number, unit, price FROM products WHERE is_active = 1 AND (name LIKE ? OR model LIKE ? OR brand LIKE ?) ORDER BY name LIMIT 15");
            $stmt->execute(array($like, $like, $like));
        } else {
            $placeholders = implode(',', array_fill(0, count($catIds), '?'));
            $params = array_merge(array($like, $like, $like), $catIds);
            $stmt = $db->prepare("SELECT id, name, model AS model_number, unit, price FROM products WHERE is_active = 1 AND (name LIKE ? OR model LIKE ? OR brand LIKE ?) AND category_id IN ({$placeholders}) ORDER BY name LIMIT 15");
            $stmt->execute($params);
        }
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        exit;

    // ---- AJAX: 取得帳款交易 ----
    case 'get_payment':
        header('Content-Type: application/json');
        $pid = (int)($_GET['id'] ?? 0);
        $stmt = Database::getInstance()->prepare('SELECT * FROM case_payments WHERE id = ?');
        $stmt->execute(array($pid));
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        echo json_encode($data ? array('success' => true, 'data' => $data) : array('success' => false, 'error' => '找不到紀錄'));
        break;

    // ---- 送出無訂金排工簽核 ----
    case 'submit_no_deposit_approval':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('/cases.php'); }
        if (!verify_csrf()) { Session::flash('error', '安全驗證失敗'); redirect('/cases.php'); }
        $caseId = (int)($_POST['case_id'] ?? 0);
        if ($caseId > 0) {
            try {
                require_once __DIR__ . '/../modules/approvals/ApprovalModel.php';
                $appModel = new ApprovalModel();
                $result = $appModel->submitNoDepositSchedule($caseId, Auth::id());
                if (!empty($result['auto_approved'])) {
                    Session::flash('success', '此案件不需簽核，可直接排工');
                } elseif (!empty($result['error'])) {
                    Session::flash('error', '送簽失敗：' . $result['error']);
                } else {
                    Session::flash('success', '已送出無訂金排工簽核');
                    AuditLog::log('cases', 'submit_no_deposit_approval', $caseId, '送出無訂金排工簽核');
                }
            } catch (Exception $e) {
                Session::flash('error', '送簽失敗：' . $e->getMessage());
            }
        }
        redirect('/cases.php?action=edit&id=' . $caseId);
        break;

    // ---- AJAX: 新增帳款交易 ----
    case 'add_payment':
        header('Content-Type: application/json');
        if (!verify_csrf()) { echo json_encode(array('success' => false, 'error' => 'CSRF')); break; }
        try {
        $caseId = (int)($_POST['case_id'] ?? 0);
        $payDate = $_POST['payment_date'] ?? '';
        $payType = $_POST['payment_type'] ?? '';
        $payMethod = $_POST['transaction_type'] ?? '';
        $payAmount = (int)($_POST['amount'] ?? 0);
        $payUntaxed = (int)($_POST['untaxed_amount'] ?? 0);
        $payTax = (int)($_POST['tax_amount'] ?? 0);
        $payNote = $_POST['note'] ?? '';
        $payReceiptNo = $_POST['receipt_number'] ?? null;

        $db = Database::getInstance();
        $stmt = $db->prepare('INSERT INTO case_payments (case_id, payment_date, payment_type, transaction_type, amount, untaxed_amount, tax_amount, receipt_number, note, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute(array($caseId, $payDate, $payType, $payMethod, $payAmount, $payUntaxed, $payTax, $payReceiptNo, $payNote, Auth::id()));
        $newId = (int)$db->lastInsertId();

        // Handle images
        if (!empty($_FILES['images']['name'][0])) {
            $imgPaths = array();
            $dir = __DIR__ . '/uploads/cases/' . $caseId;
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            foreach ($_FILES['images']['tmp_name'] as $i => $tmp) {
                if (!$tmp) continue;
                $fname = date('Ymd_His') . '_pay_' . $newId . '_' . $i . '.' . pathinfo($_FILES['images']['name'][$i], PATHINFO_EXTENSION);
                move_uploaded_file($tmp, $dir . '/' . $fname);
                $imgPaths[] = 'uploads/cases/' . $caseId . '/' . $fname;
            }
            if ($imgPaths) {
                $db->prepare('UPDATE case_payments SET image_path = ? WHERE id = ?')->execute(array(json_encode($imgPaths), $newId));
            }
        }

        // 自動建立收款單（拋轉待確認）— 若使用者沒有手動填收款單號才建立
        $generatedReceiptNo = null;
        if (empty($payReceiptNo)) {
            try {
                $caseStmt = $db->prepare('SELECT case_number, customer_id, customer_no, customer_name, sales_id, branch_id FROM cases WHERE id = ?');
                $caseStmt->execute(array($caseId));
                $caseRow = $caseStmt->fetch(PDO::FETCH_ASSOC);
                if ($caseRow) {
                    require_once __DIR__ . '/../modules/finance/FinanceModel.php';
                    $finModel = new FinanceModel();
                    $receiptData = array(
                        'register_date'    => $payDate,
                        'deposit_date'     => $payDate,
                        'customer_name'    => $caseRow['customer_name'],
                        'case_id'          => $caseId,
                        'case_number'      => $caseRow['case_number'],
                        'customer_no'      => $caseRow['customer_no'],
                        'sales_id'         => $caseRow['sales_id'],
                        'branch_id'        => $caseRow['branch_id'],
                        'receipt_method'   => $payMethod,
                        'invoice_category' => $payType,
                        'status'           => '拋轉待確認',
                        'bank_ref'         => null,
                        'subtotal'         => $payUntaxed,
                        'tax'              => $payTax,
                        'discount'         => 0,
                        'total_amount'     => $payAmount,
                        'note'             => '案件帳款自動產生 - ' . $payType . ($payNote ? ' / ' . $payNote : ''),
                        'created_by'       => Auth::id(),
                    );
                    $receiptId = $finModel->createReceipt($receiptData);
                    // 取得新建收款單的單號
                    $rn = $db->prepare('SELECT receipt_number FROM receipts WHERE id = ?');
                    $rn->execute(array($receiptId));
                    $generatedReceiptNo = $rn->fetchColumn();
                    if ($generatedReceiptNo) {
                        // 回寫至案件帳款交易
                        $db->prepare('UPDATE case_payments SET receipt_number = ? WHERE id = ?')
                           ->execute(array($generatedReceiptNo, $newId));
                    }
                }
            } catch (Exception $e) {
                // 收款單建立失敗不影響帳款交易，記錯誤
                error_log('auto create receipt failed: ' . $e->getMessage());
            }
        }

        updateTotalCollected($caseId);
        echo json_encode(array('success' => true, 'receipt_number' => $generatedReceiptNo));
        } catch (Exception $e) {
            echo json_encode(array('success' => false, 'error' => $e->getMessage()));
        }
        break;

    // ---- AJAX: 編輯帳款交易 ----
    case 'edit_payment':
        header('Content-Type: application/json');
        if (!verify_csrf()) { echo json_encode(array('success' => false, 'error' => 'CSRF')); break; }
        try {
        $pid = (int)($_POST['payment_id'] ?? 0);
        $db = Database::getInstance();
        $stmt = $db->prepare('SELECT * FROM case_payments WHERE id = ?');
        $stmt->execute(array($pid));
        $pay = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$pay) { echo json_encode(array('success' => false, 'error' => '找不到紀錄')); break; }

        $newDate = $_POST['payment_date'] ?? '';
        $newType = $_POST['payment_type'] ?? '';
        $newMethod = $_POST['transaction_type'] ?? '';
        $newAmount = (int)($_POST['amount'] ?? 0);
        $newUntaxed = (int)($_POST['untaxed_amount'] ?? 0);
        $newTax = (int)($_POST['tax_amount'] ?? 0);
        $newReceiptNo = $_POST['receipt_number'] ?? null;
        $newNote = $_POST['note'] ?? '';

        $db->prepare('UPDATE case_payments SET payment_date=?, payment_type=?, transaction_type=?, amount=?, untaxed_amount=?, tax_amount=?, receipt_number=?, note=? WHERE id=?')
            ->execute(array($newDate, $newType, $newMethod, $newAmount, $newUntaxed, $newTax, $newReceiptNo, $newNote, $pid));

        // 同步更新對應的收款單（若有 receipt_number）
        if (!empty($newReceiptNo)) {
            try {
                $db->prepare("UPDATE receipts SET register_date=?, deposit_date=?, receipt_method=?, invoice_category=?, subtotal=?, tax=?, total_amount=?, note=CONCAT('案件帳款自動產生 - ', ?, ?) WHERE receipt_number=?")
                   ->execute(array($newDate, $newDate, $newMethod, $newType, $newUntaxed, $newTax, $newAmount, $newType, $newNote ? ' / ' . $newNote : '', $newReceiptNo));
            } catch (Exception $e) {
                error_log('sync receipt failed: ' . $e->getMessage());
            }
        }

        // Handle new images
        if (!empty($_FILES['images']['name'][0])) {
            $existing = $pay['image_path'] ? json_decode($pay['image_path'], true) : array();
            if (!is_array($existing)) $existing = $pay['image_path'] ? array($pay['image_path']) : array();
            $dir = __DIR__ . '/uploads/cases/' . $pay['case_id'];
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            foreach ($_FILES['images']['tmp_name'] as $i => $tmp) {
                if (!$tmp) continue;
                $fname = date('Ymd_His') . '_pay_' . $pid . '_' . $i . '.' . pathinfo($_FILES['images']['name'][$i], PATHINFO_EXTENSION);
                move_uploaded_file($tmp, $dir . '/' . $fname);
                $existing[] = 'uploads/cases/' . $pay['case_id'] . '/' . $fname;
            }
            $db->prepare('UPDATE case_payments SET image_path = ? WHERE id = ?')->execute(array(json_encode($existing), $pid));
        }
        updateTotalCollected((int)$pay['case_id']);
        echo json_encode(array('success' => true));
        } catch (Exception $e) {
            echo json_encode(array('success' => false, 'error' => $e->getMessage()));
        }
        break;

    // ---- AJAX: 刪除帳款交易 ----
    case 'delete_payment':
        header('Content-Type: application/json');
        if (!verify_csrf()) { echo json_encode(array('success' => false, 'error' => 'CSRF')); break; }
        if (!Auth::canEditSection('delete') && !Auth::hasPermission('finance.delete') && !Auth::hasPermission('all')) {
            echo json_encode(array('success' => false, 'error' => '無刪除權限'));
            break;
        }
        $pid = (int)($_POST['payment_id'] ?? 0);
        $delStmt = Database::getInstance()->prepare('SELECT case_id FROM case_payments WHERE id = ?');
        $delStmt->execute(array($pid));
        $delCaseId = (int)$delStmt->fetchColumn();
        Database::getInstance()->prepare('DELETE FROM case_payments WHERE id = ?')->execute(array($pid));
        if ($delCaseId) updateTotalCollected($delCaseId);
        echo json_encode(array('success' => true));
        break;

    // ---- AJAX: 取得施工回報 ----
    case 'get_worklog':
        header('Content-Type: application/json');
        $wid = (int)($_GET['id'] ?? 0);
        $stmt = Database::getInstance()->prepare('SELECT * FROM case_work_logs WHERE id = ?');
        $stmt->execute(array($wid));
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        echo json_encode($data ? array('success' => true, 'data' => $data) : array('success' => false, 'error' => '找不到紀錄'));
        break;

    // ---- AJAX: 新增施工回報 ----
    case 'add_worklog':
        header('Content-Type: application/json');
        if (!verify_csrf()) { echo json_encode(array('success' => false, 'error' => 'CSRF')); break; }
        $caseId = (int)($_POST['case_id'] ?? 0);
        $stmt = Database::getInstance()->prepare('INSERT INTO case_work_logs (case_id, work_date, work_content, equipment_used, cable_used, created_by) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute(array($caseId, $_POST['work_date'] ?? '', $_POST['work_content'] ?? '', $_POST['equipment_used'] ?? '', $_POST['cable_used'] ?? '', Auth::id()));
        $newId = (int)Database::getInstance()->lastInsertId();
        // Handle photos
        if (!empty($_FILES['photos']['name'][0])) {
            $photoPaths = array();
            $dir = __DIR__ . '/uploads/cases/' . $caseId . '/worklogs';
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            foreach ($_FILES['photos']['tmp_name'] as $i => $tmp) {
                if (!$tmp) continue;
                $fname = date('Ymd_His') . '_wl_' . $newId . '_' . $i . '.' . pathinfo($_FILES['photos']['name'][$i], PATHINFO_EXTENSION);
                move_uploaded_file($tmp, $dir . '/' . $fname);
                $photoPaths[] = 'uploads/cases/' . $caseId . '/worklogs/' . $fname;
            }
            if ($photoPaths) {
                Database::getInstance()->prepare('UPDATE case_work_logs SET photo_paths = ? WHERE id = ?')->execute(array(json_encode($photoPaths), $newId));
            }
        }
        echo json_encode(array('success' => true));
        break;

    // ---- AJAX: 編輯施工回報 ----
    case 'edit_worklog':
        header('Content-Type: application/json');
        if (!verify_csrf()) { echo json_encode(array('success' => false, 'error' => 'CSRF')); break; }
        $wid = (int)($_POST['worklog_id'] ?? 0);
        $stmt = Database::getInstance()->prepare('SELECT * FROM case_work_logs WHERE id = ?');
        $stmt->execute(array($wid));
        $wl = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$wl) { echo json_encode(array('success' => false, 'error' => '找不到紀錄')); break; }
        Database::getInstance()->prepare('UPDATE case_work_logs SET work_date=?, work_content=?, equipment_used=?, cable_used=? WHERE id=?')
            ->execute(array($_POST['work_date'] ?? '', $_POST['work_content'] ?? '', $_POST['equipment_used'] ?? '', $_POST['cable_used'] ?? '', $wid));
        if (!empty($_FILES['photos']['name'][0])) {
            $existing = $wl['photo_paths'] ? json_decode($wl['photo_paths'], true) : array();
            if (!is_array($existing)) $existing = array();
            $dir = __DIR__ . '/uploads/cases/' . $wl['case_id'] . '/worklogs';
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            foreach ($_FILES['photos']['tmp_name'] as $i => $tmp) {
                if (!$tmp) continue;
                $fname = date('Ymd_His') . '_wl_' . $wid . '_' . $i . '.' . pathinfo($_FILES['photos']['name'][$i], PATHINFO_EXTENSION);
                move_uploaded_file($tmp, $dir . '/' . $fname);
                $existing[] = 'uploads/cases/' . $wl['case_id'] . '/worklogs/' . $fname;
            }
            Database::getInstance()->prepare('UPDATE case_work_logs SET photo_paths = ? WHERE id = ?')->execute(array(json_encode($existing), $wid));
        }
        echo json_encode(array('success' => true));
        break;

    // ---- AJAX: 刪除施工回報 ----
    case 'delete_worklog':
        header('Content-Type: application/json');
        if (!verify_csrf()) { echo json_encode(array('success' => false, 'error' => 'CSRF')); break; }
        if (!Auth::canEditSection('delete') && !Auth::hasPermission('schedule.delete') && !Auth::hasPermission('all')) {
            echo json_encode(array('success' => false, 'error' => '無刪除權限'));
            break;
        }
        $wid = (int)($_POST['worklog_id'] ?? 0);
        Database::getInstance()->prepare('DELETE FROM case_work_logs WHERE id = ?')->execute(array($wid));
        echo json_encode(array('success' => true));
        break;

    // ---- AJAX: 上傳附件 ----
    case 'upload_attachment':
        header('Content-Type: application/json');
        if (!verify_csrf()) { echo json_encode(array('success' => false, 'error' => 'CSRF')); break; }
        $caseId = (int)($_GET['id'] ?? 0);
        $fileType = $_POST['file_type'] ?? 'other';
        if (empty($_FILES['file']['tmp_name'])) { echo json_encode(array('success' => false, 'error' => '無檔案')); break; }
        $dir = __DIR__ . '/uploads/cases/' . $caseId;
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $origName = $_FILES['file']['name'];
        $fname = date('Ymd_His') . '_' . mt_rand(1000,9999) . '_' . preg_replace('/[^a-zA-Z0-9._\x{4e00}-\x{9fff}-]/u', '', $origName);
        $filePath = '/uploads/cases/' . $caseId . '/' . $fname;
        move_uploaded_file($_FILES['file']['tmp_name'], $dir . '/' . $fname);
        $attId = $model->saveAttachment($caseId, $fileType, $origName, $filePath);
        if (function_exists('backup_to_drive')) { backup_to_drive($dir . '/' . $fname, 'cases/' . $caseId . '/' . $fname); }
        echo json_encode(array('success' => true, 'id' => $attId, 'file_name' => $origName, 'file_path' => $filePath));
        break;

    // ---- AJAX: 刪除附件 ----
    case 'delete_attachment':
        header('Content-Type: application/json');
        if (!verify_csrf()) { echo json_encode(array('success' => false, 'error' => 'CSRF')); break; }
        if (!Auth::canEditSection('attach') && !Auth::hasPermission('all')) {
            echo json_encode(array('success' => false, 'error' => '無權限'));
            break;
        }
        $attId = (int)($_POST['attachment_id'] ?? 0);
        $model->deleteAttachment($attId);
        echo json_encode(array('success' => true));
        break;

    // ---- AJAX: 新增附件分類 ----
    case 'add_attach_type':
        header('Content-Type: application/json');
        if (!verify_csrf()) { echo json_encode(array('success' => false, 'error' => 'CSRF')); break; }
        $label = trim($_POST['label'] ?? '');
        if (!$label) { echo json_encode(array('success' => false, 'error' => '名稱不可為空')); break; }
        $key = 'custom_' . time();
        CaseModel::addAttachType($key, $label);
        echo json_encode(array('success' => true, 'key' => $key));
        break;

    // ---- AJAX: 切換客戶不允許拍照 ----
    case 'toggle_no_photo':
        header('Content-Type: application/json');
        if (!verify_csrf()) { echo json_encode(array('success' => false, 'error' => 'CSRF')); break; }
        $caseId = (int)($_POST['case_id'] ?? 0);
        $noPhoto = (int)($_POST['no_photo'] ?? 0);
        Database::getInstance()->prepare('UPDATE case_readiness SET no_photo_allowed = ? WHERE case_id = ?')->execute(array($noPhoto, $caseId));
        echo json_encode(array('success' => true));
        break;

    // ---- AJAX: 搜尋客戶 ----
    case 'ajax_search_customer':
        header('Content-Type: application/json');
        $keyword = $_GET['keyword'] ?? '';
        if (mb_strlen($keyword) < 2) { echo '[]'; break; }
        $stmt = Database::getInstance()->prepare("SELECT c.id, c.customer_no, c.name, c.phone, c.mobile, c.tax_id, c.site_address, c.contact_person, c.line_official, c.source_company, c.is_blacklisted, c.blacklist_reason FROM customers c WHERE c.name LIKE ? OR c.phone LIKE ? OR c.mobile LIKE ? OR c.tax_id LIKE ? OR c.customer_no LIKE ? ORDER BY c.name LIMIT 20");
        $like = '%' . $keyword . '%';
        $stmt->execute(array($like, $like, $like, $like, $like));
        $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        // Load contacts for each customer
        foreach ($customers as &$c) {
            $cs = Database::getInstance()->prepare('SELECT contact_name, phone, role FROM customer_contacts WHERE customer_id = ? LIMIT 5');
            $cs->execute(array($c['id']));
            $c['contacts'] = $cs->fetchAll(PDO::FETCH_ASSOC);
        }
        echo json_encode($customers);
        break;

    // ---- AJAX: 快速新增客戶 ----
    case 'ajax_create_customer':
        header('Content-Type: application/json');
        if (!verify_csrf()) { echo json_encode(array('success' => false, 'error' => 'CSRF')); break; }
        $name = trim($_POST['name'] ?? '');
        if (!$name) { echo json_encode(array('success' => false, 'error' => '名稱不可為空')); break; }
        $db = Database::getInstance();
        // Generate customer_no
        $maxNo = $db->query("SELECT MAX(CAST(SUBSTRING(customer_no, 3) AS UNSIGNED)) FROM customers WHERE customer_no LIKE 'A-%'")->fetchColumn();
        $customerNo = 'A-' . str_pad(($maxNo ?: 0) + 1, 6, '0', STR_PAD_LEFT);
        $caseNumber = trim($_POST['case_number'] ?? '');
        $caseDate = trim($_POST['case_date'] ?? '');
        $sourceCompany = trim($_POST['source_company'] ?? '');
        $db->prepare('INSERT INTO customers (customer_no, name, contact_person, phone, mobile, site_address, case_number, case_date, source_company, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
            ->execute(array($customerNo, $name, $_POST['contact_person'] ?? '', $_POST['phone'] ?? '', $_POST['mobile'] ?? '', $_POST['address'] ?? '', $caseNumber ?: null, $caseDate ?: null, $sourceCompany ?: null, Auth::id()));
        $newId = (int)$db->lastInsertId();
        echo json_encode(array('success' => true, 'customer' => array('id' => $newId, 'customer_no' => $customerNo, 'name' => $name, 'phone' => $_POST['phone'] ?? '', 'mobile' => $_POST['mobile'] ?? '', 'site_address' => $_POST['address'] ?? '', 'contact_person' => $_POST['contact_person'] ?? '', 'contacts' => array())));
        break;

    // ---- AJAX: 請款流程 新增/編輯/刪除 ----
    case 'ajax_billing_item_save':
        header('Content-Type: application/json');
        if (!verify_csrf()) { echo json_encode(array('success' => false, 'error' => 'CSRF')); break; }
        $db = Database::getInstance();
        $biId = (int)($_POST['id'] ?? 0);
        $caseId = (int)($_POST['case_id'] ?? 0);
        if (!$caseId) { echo json_encode(array('success' => false, 'error' => '缺少案件ID')); break; }
        $biData = array(
            !empty($_POST['payment_category']) ? $_POST['payment_category'] : '',
            !empty($_POST['amount_untaxed']) ? (int)str_replace(',', '', $_POST['amount_untaxed']) : null,
            !empty($_POST['tax_amount']) ? (int)str_replace(',', '', $_POST['tax_amount']) : null,
            !empty($_POST['total_amount']) ? (int)str_replace(',', '', $_POST['total_amount']) : 0,
            !empty($_POST['tax_included']) ? 1 : 0,
            !empty($_POST['customer_billable']) ? 1 : 0,
            !empty($_POST['customer_paid']) ? 1 : 0,
            !empty($_POST['customer_paid_info']) ? trim($_POST['customer_paid_info']) : null,
            !empty($_POST['is_billed']) ? 1 : 0,
            !empty($_POST['billed_info']) ? trim($_POST['billed_info']) : null,
            !empty($_POST['invoice_number']) ? trim($_POST['invoice_number']) : null,
            !empty($_POST['note']) ? trim($_POST['note']) : null,
        );
        if ($biId) {
            $db->prepare("UPDATE case_billing_items SET payment_category=?, amount_untaxed=?, tax_amount=?, total_amount=?, tax_included=?, customer_billable=?, customer_paid=?, customer_paid_info=?, is_billed=?, billed_info=?, invoice_number=?, note=? WHERE id=? AND case_id=?")
                ->execute(array_merge($biData, array($biId, $caseId)));
        } else {
            $maxSeq = $db->prepare("SELECT COALESCE(MAX(seq_no),0) FROM case_billing_items WHERE case_id=?");
            $maxSeq->execute(array($caseId));
            $seqNo = (int)$maxSeq->fetchColumn() + 1;
            $db->prepare("INSERT INTO case_billing_items (case_id, seq_no, payment_category, amount_untaxed, tax_amount, total_amount, tax_included, customer_billable, customer_paid, customer_paid_info, is_billed, billed_info, invoice_number, note, created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
                ->execute(array_merge(array($caseId, $seqNo), $biData, array(Auth::id())));
        }
        echo json_encode(array('success' => true));
        break;

    case 'ajax_billing_item_delete':
        header('Content-Type: application/json');
        if (!verify_csrf()) { echo json_encode(array('success' => false, 'error' => 'CSRF')); break; }
        $biId = (int)($_POST['id'] ?? 0);
        $caseId = (int)($_POST['case_id'] ?? 0);
        if ($biId && $caseId) {
            Database::getInstance()->prepare("DELETE FROM case_billing_items WHERE id=? AND case_id=?")->execute(array($biId, $caseId));
        }
        echo json_encode(array('success' => true));
        break;

    // ---- AJAX: 取得支援分公司 ----
    case 'get_support_branches':
        header('Content-Type: application/json');
        $caseId = (int)($_GET['id'] ?? 0);
        try {
            $supportBranches = $model->getSupportBranches($caseId);
            echo json_encode(array('success' => true, 'data' => $supportBranches));
        } catch (Exception $e) {
            echo json_encode(array('success' => true, 'data' => array()));
        }
        break;

    // ---- AJAX: 儲存支援分公司 ----
    case 'save_support_branches':
        header('Content-Type: application/json');
        if (!verify_csrf()) {
            echo json_encode(array('success' => false, 'error' => 'CSRF'));
            break;
        }
        if (!Auth::hasPermission('cases.manage') && !Auth::hasPermission('all')) {
            echo json_encode(array('success' => false, 'error' => '無權限'));
            break;
        }
        $caseId = (int)($_POST['case_id'] ?? 0);
        $selectedBranches = $_POST['branch_ids'] ?? array();
        if (!is_array($selectedBranches)) {
            $selectedBranches = array();
        }
        $selectedBranches = array_map('intval', $selectedBranches);
        $model->saveSupportBranches($caseId, $selectedBranches, Auth::id());
        echo json_encode(array('success' => true));
        break;

    default:
        redirect('/cases.php');
}
