<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
require_once __DIR__ . '/../modules/repairs/RepairModel.php';

$model = new RepairModel();
$action = $_GET['action'] ?? 'list';
$branchIds = Auth::getAccessibleBranchIds();

switch ($action) {
    case 'list':
        $filters = array(
            'month'   => $_GET['month'] ?? date('Y-m'),
            'status'  => $_GET['status'] ?? '',
            'keyword' => $_GET['keyword'] ?? '',
        );
        $repairs = $model->getList($branchIds, $filters);
        $pageTitle = '維修單管理';
        $currentPage = 'repairs';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/repairs/list.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    case 'create':
        Auth::requirePermission('repairs.manage');
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!verify_csrf()) { Session::flash('error', '安全驗證失敗'); redirect('/repairs.php'); }
            $repairId = $model->create($_POST);
            // 儲存項目
            if (!empty($_POST['items'])) {
                $model->saveItems($repairId, $_POST['items']);
            }
            Session::flash('success', '維修單已建立');
            redirect('/repairs.php?action=view&id=' . $repairId);
        }
        $repair = null;
        $engineers = $model->getEngineers($branchIds);
        $branches = $model->getBranches();
        $pageTitle = '新增維修單';
        $currentPage = 'repairs';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/repairs/form.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    case 'edit':
        Auth::requirePermission('repairs.manage');
        $id = (int)($_GET['id'] ?? 0);
        $repair = $model->getById($id);
        if (!$repair) { Session::flash('error', '維修單不存在'); redirect('/repairs.php'); }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!verify_csrf()) { Session::flash('error', '安全驗證失敗'); redirect('/repairs.php?action=edit&id='.$id); }
            AuditLog::logChange('repairs', $id, $repair['repair_number'] ?? "維修單#{$id}", $repair, $_POST, array('status','assigned_engineer_id','customer_name','description','resolution'));
            $model->update($id, $_POST);
            if (isset($_POST['items'])) {
                $model->saveItems($id, $_POST['items']);
            }
            Session::flash('success', '維修單已更新');
            redirect('/repairs.php?action=view&id=' . $id);
        }
        $engineers = $model->getEngineers($branchIds);
        $branches = $model->getBranches();
        $pageTitle = '編輯維修單';
        $currentPage = 'repairs';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/repairs/form.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    case 'view':
        $id = (int)($_GET['id'] ?? 0);
        $repair = $model->getById($id);
        if (!$repair) { Session::flash('error', '維修單不存在'); redirect('/repairs.php'); }
        $repair['reports'] = $model->getReports($id);
        $repair['photos'] = $model->getPhotos($id);
        $statusLabels = array('draft' => '草稿', 'completed' => '已完工', 'invoiced' => '已請款');
        $pageTitle = '維修單 ' . $repair['repair_number'];
        $currentPage = 'repairs';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/repairs/view.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    // ---- 維修回報 (AJAX) ----
    case 'add_report':
        header('Content-Type: application/json');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(array('error' => '方法不允許'));
            exit;
        }
        $repairId = (int)($_POST['repair_id'] ?? 0);
        $reportText = trim($_POST['report_text'] ?? '');
        if (!$repairId) {
            echo json_encode(array('error' => '缺少維修單 ID'));
            exit;
        }
        $reportId = $model->addReport($repairId, $reportText);

        // 處理照片
        $photos = array();
        if (!empty($_FILES['photos'])) {
            $uploadDir = __DIR__ . '/uploads/repairs/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            $fileCount = is_array($_FILES['photos']['name']) ? count($_FILES['photos']['name']) : 0;
            for ($i = 0; $i < $fileCount; $i++) {
                if ($_FILES['photos']['error'][$i] !== UPLOAD_ERR_OK) continue;
                if ($_FILES['photos']['size'][$i] > 10 * 1024 * 1024) continue;
                $ext = strtolower(pathinfo($_FILES['photos']['name'][$i], PATHINFO_EXTENSION));
                if (!in_array($ext, array('jpg','jpeg','png','gif','webp'))) continue;
                $newName = $repairId . '_' . time() . '_' . $i . '.' . $ext;
                $dest = $uploadDir . $newName;
                if (move_uploaded_file($_FILES['photos']['tmp_name'][$i], $dest)) {
                    $photoId = $model->savePhoto($repairId, $reportId, '/uploads/repairs/' . $newName, '');
                    $photos[] = array('id' => $photoId, 'file_path' => '/uploads/repairs/' . $newName);
                    backup_to_drive($dest, 'repairs', $repairId);
                }
            }
        }
        $user = Session::getUser();
        echo json_encode(array(
            'success' => true,
            'report_id' => $reportId,
            'reporter_name' => $user['real_name'],
            'created_at' => date('Y-m-d H:i:s'),
            'photos' => $photos,
        ));
        exit;

    // ---- 刪除維修照片 (AJAX) ----
    case 'delete_photo':
        header('Content-Type: application/json');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(array('error' => '方法不允許'));
            exit;
        }
        $photoId = (int)($_POST['photo_id'] ?? 0);
        if (!$photoId) {
            echo json_encode(array('error' => '缺少照片 ID'));
            exit;
        }
        $model->deletePhoto($photoId);
        echo json_encode(array('success' => true));
        exit;

    case 'print':
        $id = (int)($_GET['id'] ?? 0);
        $repair = $model->getById($id);
        if (!$repair) { Session::flash('error', '維修單不存在'); redirect('/repairs.php'); }
        require __DIR__ . '/../templates/repairs/print.php';
        break;

    case 'status':
        Auth::requirePermission('repairs.manage');
        if (verify_csrf()) {
            $model->updateStatus((int)$_GET['id'], $_GET['status']);
            Session::flash('success', '狀態已更新');
        }
        redirect('/repairs.php?action=view&id=' . (int)$_GET['id']);
        break;

    case 'delete':
        if (!Auth::hasPermission('repairs.delete') && !Auth::hasPermission('all')) {
            Session::flash('error', '權限不足，無法刪除維修單');
            redirect('/repairs.php');
        }
        if (verify_csrf()) {
            $model->delete((int)$_GET['id']);
            Session::flash('success', '維修單已刪除');
        }
        redirect('/repairs.php');
        break;

    default:
        redirect('/repairs.php');
}
