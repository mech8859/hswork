<?php
/**
 * 技術手冊
 * 師傅施工時可查閱的產品手冊 / 規格書 / 操作說明
 */
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();

if (!Auth::hasPermission('tech_manuals.manage')
    && !Auth::hasPermission('tech_manuals.view')
    && !Auth::hasPermission('all')) {
    Session::flash('error', '無權限');
    redirect('/');
}

require_once __DIR__ . '/../modules/tech_manuals/TechManualModel.php';

$model = new TechManualModel();
$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$canManage = Auth::hasPermission('tech_manuals.manage') || Auth::hasPermission('all');

$ALLOWED_EXT = array('pdf', 'jpg', 'jpeg', 'png');
$MAX_SIZE = 20 * 1024 * 1024; // 20MB
$UPLOAD_DIR = __DIR__ . '/uploads/tech_manuals';
$REL_DIR = 'uploads/tech_manuals';

switch ($action) {

    // ---- 列表 ----
    case 'list':
        $filters = array(
            'category' => isset($_GET['category']) ? $_GET['category'] : '',
            'brand'    => isset($_GET['brand']) ? $_GET['brand'] : '',
            'keyword'  => isset($_GET['keyword']) ? trim($_GET['keyword']) : '',
        );
        $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $result = $model->getList($filters, $page);
        $records = $result['data'];
        $categories = $model->getDistinctCategories();
        $brands = $model->getDistinctBrands();

        $pageTitle = '技術手冊';
        $currentPage = 'tech_manuals';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/tech_manuals/list.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    // ---- 新增表單 ----
    case 'create':
        if (!$canManage) {
            Session::flash('error', '無權限執行此操作');
            redirect('/tech_manuals.php');
        }
        $record = null;
        $categories = $model->getDistinctCategories();
        $brands = $model->getDistinctBrands();

        $pageTitle = '新增技術手冊';
        $currentPage = 'tech_manuals';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/tech_manuals/form.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    // ---- 編輯表單 ----
    case 'edit':
        if (!$canManage) {
            Session::flash('error', '無權限執行此操作');
            redirect('/tech_manuals.php');
        }
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        $record = $model->getById($id);
        if (!$record) {
            Session::flash('error', '找不到記錄');
            redirect('/tech_manuals.php');
        }
        $categories = $model->getDistinctCategories();
        $brands = $model->getDistinctBrands();

        $pageTitle = '編輯技術手冊';
        $currentPage = 'tech_manuals';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/tech_manuals/form.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    // ---- 儲存（新增/更新） ----
    case 'store':
        if (!$canManage) {
            Session::flash('error', '無權限執行此操作');
            redirect('/tech_manuals.php');
        }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('/tech_manuals.php');
        }
        if (!verify_csrf()) {
            Session::flash('error', '安全驗證失敗');
            redirect('/tech_manuals.php');
        }

        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $data = array(
            'title'       => isset($_POST['title']) ? trim($_POST['title']) : '',
            'category'    => isset($_POST['category']) ? trim($_POST['category']) : '',
            'brand'       => isset($_POST['brand']) ? trim($_POST['brand']) : '',
            'model'       => isset($_POST['model']) ? trim($_POST['model']) : '',
            'description' => isset($_POST['description']) ? trim($_POST['description']) : '',
            'tags'        => isset($_POST['tags']) ? trim($_POST['tags']) : '',
        );

        if ($data['title'] === '') {
            Session::flash('error', '請輸入標題');
            redirect('/tech_manuals.php' . ($id > 0 ? '?action=edit&id=' . $id : '?action=create'));
        }

        // 處理檔案上傳
        $fileData = null;
        if (!empty($_FILES['file']['tmp_name']) && is_uploaded_file($_FILES['file']['tmp_name'])) {
            if ($_FILES['file']['size'] > $MAX_SIZE) {
                Session::flash('error', '檔案超過 20MB 上限');
                redirect('/tech_manuals.php' . ($id > 0 ? '?action=edit&id=' . $id : '?action=create'));
            }
            $origName = $_FILES['file']['name'];
            $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
            if (!in_array($ext, $ALLOWED_EXT, true)) {
                Session::flash('error', '僅支援 PDF / JPG / PNG');
                redirect('/tech_manuals.php' . ($id > 0 ? '?action=edit&id=' . $id : '?action=create'));
            }
            if (!is_dir($UPLOAD_DIR)) mkdir($UPLOAD_DIR, 0755, true);
            $fname = date('Ymd_His') . '_' . substr(md5(uniqid('', true)), 0, 8) . '.' . $ext;
            $target = $UPLOAD_DIR . '/' . $fname;
            if (!move_uploaded_file($_FILES['file']['tmp_name'], $target)) {
                Session::flash('error', '檔案上傳失敗');
                redirect('/tech_manuals.php');
            }
            $fileData = array(
                'file_path' => $REL_DIR . '/' . $fname,
                'file_name' => $origName,
                'file_size' => (int)$_FILES['file']['size'],
                'file_ext'  => $ext,
            );
        }

        if ($id > 0) {
            // 編輯
            $existing = $model->getById($id);
            if (!$existing) {
                Session::flash('error', '找不到記錄');
                redirect('/tech_manuals.php');
            }
            $model->update($id, $data);
            if ($fileData) {
                // 換檔：刪除舊檔
                $oldFull = __DIR__ . '/' . $existing['file_path'];
                if (!empty($existing['file_path']) && is_file($oldFull)) @unlink($oldFull);
                $model->updateFile($id, $fileData);
            }
            AuditLog::log('tech_manuals', 'update', $id, '更新技術手冊');
            Session::flash('success', '已更新');
        } else {
            // 新增：必須上傳檔案
            if (!$fileData) {
                Session::flash('error', '請選擇檔案');
                redirect('/tech_manuals.php?action=create');
            }
            $newData = array_merge($data, $fileData);
            $newId = $model->create($newData);
            AuditLog::log('tech_manuals', 'create', $newId, '新增技術手冊');
            Session::flash('success', '已新增');
        }
        redirect('/tech_manuals.php');
        break;

    // ---- 下載/預覽（統一入口，方便權限檢查）----
    case 'download':
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        $record = $model->getById($id);
        if (!$record) {
            http_response_code(404);
            echo '找不到檔案';
            exit;
        }
        $full = __DIR__ . '/' . $record['file_path'];
        if (!is_file($full)) {
            http_response_code(404);
            echo '檔案不存在';
            exit;
        }
        $ext = strtolower($record['file_ext']);
        $mime = $ext === 'pdf' ? 'application/pdf'
              : (in_array($ext, array('jpg', 'jpeg'), true) ? 'image/jpeg'
              : ($ext === 'png' ? 'image/png' : 'application/octet-stream'));
        $disposition = isset($_GET['dl']) && $_GET['dl'] === '1' ? 'attachment' : 'inline';
        $dlName = !empty($record['file_name']) ? $record['file_name'] : basename($full);
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($full));
        header('Content-Disposition: ' . $disposition . '; filename="' . rawurlencode($dlName) . '"');
        header('Cache-Control: private, max-age=0');
        readfile($full);
        exit;

    // ---- 刪除 ----
    case 'delete':
        if (!$canManage) {
            Session::flash('error', '無權限執行此操作');
            redirect('/tech_manuals.php');
        }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf()) {
            Session::flash('error', '安全驗證失敗');
            redirect('/tech_manuals.php');
        }
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        if ($id > 0) {
            $existing = $model->getById($id);
            if ($existing) {
                $full = __DIR__ . '/' . $existing['file_path'];
                if (is_file($full)) @unlink($full);
            }
            AuditLog::log('tech_manuals', 'delete', $id, '刪除技術手冊');
            $model->delete($id);
            Session::flash('success', '已刪除');
        }
        redirect('/tech_manuals.php');
        break;

    default:
        redirect('/tech_manuals.php');
}
