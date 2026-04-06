<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (!Auth::hasPermission('customers.manage') && !Auth::hasPermission('customers.view') && !Auth::hasPermission('customers.own')) {
    Session::flash('error', '無權限查看此頁面');
    redirect('/');
}
require_once __DIR__ . '/../modules/customers/CustomerModel.php';

$model = new CustomerModel();
$action = $_GET['action'] ?? 'list';
$branchIds = Auth::getAccessibleBranchIds();

switch ($action) {
    // ---- 客戶列表 ----
    case 'list':
        $filters = array(
            'keyword'  => $_GET['keyword'] ?? '',
            'category' => $_GET['category'] ?? '',
            'sales_id' => $_GET['sales_id'] ?? '',
            'has_cases' => $_GET['has_cases'] ?? '',
            'import_source' => $_GET['import_source'] ?? '',
            'source_branch' => $_GET['source_branch'] ?? '',
            'date_from' => $_GET['date_from'] ?? '',
            'date_to' => $_GET['date_to'] ?? '',
            'has_relations' => $_GET['has_relations'] ?? '',
            'excel_2026' => $_GET['excel_2026'] ?? '',
        );
        $hasSearch = !empty($filters['keyword']) || !empty($filters['category']) || !empty($filters['sales_id']) || !empty($filters['has_cases']) || !empty($filters['import_source']) || !empty($filters['source_branch']) || !empty($filters['date_from']) || !empty($filters['date_to']) || !empty($filters['has_relations']) || !empty($filters['excel_2026']);
        $salespeople = $model->getSalespeople();
        $perPage = 100;
        $currentPageNum = max(1, (int)($_GET['page'] ?? 1));

        if ($hasSearch) {
            $totalCount = $model->getListCount($filters);
            $totalPages = max(1, ceil($totalCount / $perPage));
            $currentPageNum = min($currentPageNum, $totalPages);
            $customers = $model->getList($filters, $perPage, $currentPageNum);
        } else {
            $customers = array();
            $totalCount = 0;
            $totalPages = 0;
        }

        $dashboardStats = $model->getDashboardStats();

        $pageTitle = '客戶管理';
        $currentPage = 'customers';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/customers/list.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    // ---- 新增客戶 ----
    case 'create':
        Auth::requirePermission('customers.manage');
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!verify_csrf()) { Session::flash('error', '安全驗證失敗'); redirect('/customers.php'); }
            $id = $model->create($_POST);
            if (!empty($_POST['contacts'])) {
                $model->saveContacts($id, $_POST['contacts']);
            }
            AuditLog::log('customers', 'create', $id, $_POST['name'] ?? '');
            Session::flash('success', '客戶已新增');
            redirect('/customers.php?action=edit&id=' . $id);
        }
        $salespeople = $model->getSalespeople();
        $customer = null;
        $cases = array();
        $deals = array();
        $transactions = array();
        $files = array();

        $pageTitle = '新增客戶';
        $currentPage = 'customers';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/customers/form.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    // ---- 編輯客戶（單頁全資訊）----
    case 'edit':
        $id = (int)($_GET['id'] ?? 0);
        $customer = $model->getById($id);
        if (!$customer) { Session::flash('error', '客戶不存在'); redirect('/customers.php'); }

        $canManage = Auth::hasPermission('customers.manage');

        // 自動從關聯案件補進件資訊（case_number/case_date/source_company 為空時）
        if (empty($customer['case_number'])) {
            $db = Database::getInstance();
            $stmt = $db->prepare("SELECT c.case_number, DATE(c.created_at) as case_date, b.name as branch_name FROM cases c LEFT JOIN branches b ON c.branch_id = b.id WHERE c.customer_id = ? ORDER BY c.created_at ASC LIMIT 1");
            $stmt->execute(array($id));
            $lc = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($lc) {
                $customer['case_number'] = $lc['case_number'];
                $customer['case_date'] = $lc['case_date'];
                if (empty($customer['source_company'])) {
                    $customer['source_company'] = $lc['branch_name'];
                }
                // 回寫到客戶表
                $db->prepare("UPDATE customers SET case_number = ?, case_date = ?, source_company = COALESCE(NULLIF(source_company,''), ?) WHERE id = ?")->execute(array($lc['case_number'], $lc['case_date'], $lc['branch_name'], $id));
            }
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Auth::requirePermission('customers.manage');
            if (!verify_csrf()) { Session::flash('error', '安全驗證失敗'); redirect('/customers.php?action=edit&id='.$id); }
            AuditLog::logChange('customers', $id, $customer['name'], $customer, $_POST, array('name','category','contact_person','phone','mobile','email','address','tax_id','invoice_title','legacy_customer_no'));
            $model->update($id, $_POST);
            // 同步完工日期到關聯案件
            if (!empty($_POST['completion_date'])) {
                $db = Database::getInstance();
                $db->prepare("UPDATE cases SET completion_date = ? WHERE customer_id = ? AND (completion_date IS NULL OR completion_date = '')")->execute(array($_POST['completion_date'], $id));
            }
            if (isset($_POST['contacts'])) {
                $model->saveContacts($id, $_POST['contacts']);
            }
            Session::flash('success', '客戶已更新');
            redirect('/customers.php?action=edit&id=' . $id);
        }
        $salespeople = $model->getSalespeople();
        $cases = $model->getCases($id);
        $deals = $model->getDeals($id);
        $transactions = $model->getTransactions($id);
        $files = $model->getFiles($id);
        $repairPhotos = $model->getRepairPhotos($id);

        $pageTitle = $customer['name'] . ' - 客戶管理';
        $currentPage = 'customers';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/customers/form.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    // ---- 客戶詳情 → redirect 到 edit ----
    case 'view':
        redirect('/customers.php?action=edit&id=' . (int)($_GET['id'] ?? 0));
        break;

    // ---- 停用/啟用 ----
    case 'toggle':
        Auth::requirePermission('customers.manage');
        if (verify_csrf()) {
            $model->toggleActive((int)$_GET['id']);
            Session::flash('success', '客戶狀態已更新');
        }
        redirect('/customers.php');
        break;

    // ---- AJAX搜尋 ----
    case 'ajax_search':
        header('Content-Type: application/json');
        $keyword = $_GET['q'] ?? '';
        if (strlen($keyword) < 1) { echo '[]'; exit; }
        echo json_encode($model->search($keyword));
        exit;

    // ---- 上傳文件（AJAX）----
    case 'upload_file':
        Auth::requirePermission('customers.manage');
        $customerId = (int)($_POST['customer_id'] ?? 0);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf()) {
            json_response(array('success' => false, 'error' => '安全驗證失敗'));
        }
        $customer = $model->getById($customerId);
        if (!$customer) { json_response(array('success' => false, 'error' => '客戶不存在')); }

        if (!empty($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = '/uploads/customers/' . $customerId . '/';
            $realDir = __DIR__ . $uploadDir;
            if (!is_dir($realDir)) { mkdir($realDir, 0755, true); }

            $ext = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
            $allowed = array('jpg','jpeg','png','gif','pdf','doc','docx','xls','xlsx');
            if (!in_array($ext, $allowed)) {
                json_response(array('success' => false, 'error' => '不支援的檔案格式'));
            }

            $fileName = $_FILES['file']['name'];
            $saveName = date('Ymd_His') . '_' . mt_rand(1000,9999) . '.' . $ext;
            $savePath = $uploadDir . $saveName;

            if (move_uploaded_file($_FILES['file']['tmp_name'], $realDir . $saveName)) {
                $model->addFile($customerId, array(
                    'file_type' => $_POST['file_type'] ?: 'other',
                    'file_name' => $fileName,
                    'file_path' => $savePath,
                    'file_size' => $_FILES['file']['size'],
                    'note'      => $_POST['note'] ?: null,
                ));
                backup_to_drive($realDir . $saveName, 'customers', $customerId);
                json_response(array('success' => true));
            } else {
                json_response(array('success' => false, 'error' => '上傳失敗'));
            }
        } else {
            json_response(array('success' => false, 'error' => '請選擇檔案'));
        }
        break;

    // ---- 刪除文件（AJAX）----
    case 'delete_file':
        Auth::requirePermission('customers.manage');
        if (!verify_csrf()) { json_response(array('success' => false, 'error' => '安全驗證失敗')); }
        $fileId = (int)($_POST['file_id'] ?? $_GET['file_id'] ?? 0);
        $model->deleteFile($fileId);
        json_response(array('success' => true));
        break;

    // ---- 新增成交紀錄（AJAX）----
    case 'add_deal':
        Auth::requirePermission('customers.manage');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf()) {
            json_response(array('success' => false, 'error' => '安全驗證失敗'));
        }
        $customerId = (int)($_POST['customer_id'] ?? 0);
        if (!$customerId) { json_response(array('success' => false, 'error' => '缺少客戶 ID')); }
        $model->addDeal($customerId, $_POST);
        json_response(array('success' => true));
        break;

    // ---- 刪除成交紀錄（AJAX）----
    case 'delete_deal':
        Auth::requirePermission('customers.manage');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf()) {
            json_response(array('success' => false, 'error' => '安全驗證失敗'));
        }
        $dealId = (int)($_POST['deal_id'] ?? 0);
        $model->deleteDeal($dealId);
        json_response(array('success' => true));
        break;

    // ---- 新增帳款交易（AJAX）----
    case 'add_transaction':
        Auth::requirePermission('customers.manage');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf()) {
            json_response(array('success' => false, 'error' => '安全驗證失敗'));
        }
        $customerId = (int)($_POST['customer_id'] ?? 0);
        if (!$customerId) { json_response(array('success' => false, 'error' => '缺少客戶 ID')); }
        $model->addTransaction($customerId, $_POST);
        json_response(array('success' => true));
        break;

    // ---- 刪除帳款交易（AJAX）----
    case 'delete_transaction':
        Auth::requirePermission('customers.manage');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf()) {
            json_response(array('success' => false, 'error' => '安全驗證失敗'));
        }
        $transactionId = (int)($_POST['transaction_id'] ?? 0);
        $model->deleteTransaction($transactionId);
        json_response(array('success' => true));
        break;

    // ---- 新增文件分類（AJAX）----
    case 'add_file_type':
        Auth::requirePermission('customers.manage');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf()) {
            json_response(array('success' => false, 'error' => '安全驗證失敗'));
        }
        $label = trim($_POST['label'] ?? '');
        if (!$label) { json_response(array('success' => false, 'error' => '請輸入分類名稱')); }
        $key = 'custom_' . time();
        CustomerModel::addFileType($key, $label);
        json_response(array('success' => true, 'key' => $key, 'label' => $label));
        break;

    default:
        redirect('/customers.php');
}
