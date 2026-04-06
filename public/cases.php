<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
require_once __DIR__ . '/../modules/cases/CaseModel.php';

$model = new CaseModel();
$action = $_GET['action'] ?? 'list';
$branchIds = Auth::getAccessibleBranchIds();

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
            $model->update($id, $_POST);
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

    // ---- AJAX: 新增帳款交易 ----
    case 'add_payment':
        header('Content-Type: application/json');
        if (!verify_csrf()) { echo json_encode(array('success' => false, 'error' => 'CSRF')); break; }
        $caseId = (int)($_POST['case_id'] ?? 0);
        $stmt = Database::getInstance()->prepare('INSERT INTO case_payments (case_id, payment_date, payment_type, transaction_type, amount, note, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute(array($caseId, $_POST['payment_date'] ?? '', $_POST['payment_type'] ?? '', $_POST['transaction_type'] ?? '', (int)($_POST['amount'] ?? 0), $_POST['note'] ?? '', Auth::id()));
        $newId = (int)Database::getInstance()->lastInsertId();
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
                Database::getInstance()->prepare('UPDATE case_payments SET image_path = ? WHERE id = ?')->execute(array(json_encode($imgPaths), $newId));
            }
        }
        echo json_encode(array('success' => true));
        break;

    // ---- AJAX: 編輯帳款交易 ----
    case 'edit_payment':
        header('Content-Type: application/json');
        if (!verify_csrf()) { echo json_encode(array('success' => false, 'error' => 'CSRF')); break; }
        $pid = (int)($_POST['payment_id'] ?? 0);
        $stmt = Database::getInstance()->prepare('SELECT * FROM case_payments WHERE id = ?');
        $stmt->execute(array($pid));
        $pay = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$pay) { echo json_encode(array('success' => false, 'error' => '找不到紀錄')); break; }
        Database::getInstance()->prepare('UPDATE case_payments SET payment_date=?, payment_type=?, transaction_type=?, amount=?, note=? WHERE id=?')
            ->execute(array($_POST['payment_date'] ?? '', $_POST['payment_type'] ?? '', $_POST['transaction_type'] ?? '', (int)($_POST['amount'] ?? 0), $_POST['note'] ?? '', $pid));
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
            Database::getInstance()->prepare('UPDATE case_payments SET image_path = ? WHERE id = ?')->execute(array(json_encode($existing), $pid));
        }
        echo json_encode(array('success' => true));
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
        Database::getInstance()->prepare('DELETE FROM case_payments WHERE id = ?')->execute(array($pid));
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
