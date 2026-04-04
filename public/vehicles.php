<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
require_once __DIR__ . '/../modules/vehicles/VehicleModel.php';

$model = new VehicleModel();
$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$branchIds = Auth::getAccessibleBranchIds();

switch ($action) {
    case 'list':
        $filters = array(
            'vehicle_type' => isset($_GET['vehicle_type']) ? $_GET['vehicle_type'] : '',
            'keyword'      => isset($_GET['keyword']) ? $_GET['keyword'] : '',
        );
        $vehicles = $model->getList($branchIds, $filters);
        $pageTitle = '車輛管理';
        $currentPage = 'vehicles';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/vehicles/list.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    case 'create':
        Auth::requirePermission('staff.manage');
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!verify_csrf()) { Session::flash('error', '安全驗證失敗'); redirect('/vehicles.php'); }
            $vehicleId = $model->create($_POST);
            if (!empty($_POST['tools'])) {
                $model->saveTools($vehicleId, $_POST['tools']);
            }
            Session::flash('success', '車輛已新增');
            redirect('/vehicles.php?action=view&id=' . $vehicleId);
        }
        $vehicle = null;
        $users = $model->getUsers($branchIds);
        $branches = $model->getBranches();
        $pageTitle = '新增車輛';
        $currentPage = 'vehicles';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/vehicles/form.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    case 'edit':
        Auth::requirePermission('staff.manage');
        $id = (int)(isset($_GET['id']) ? $_GET['id'] : 0);
        $vehicle = $model->getById($id);
        if (!$vehicle) { Session::flash('error', '車輛不存在'); redirect('/vehicles.php'); }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!verify_csrf()) { Session::flash('error', '安全驗證失敗'); redirect('/vehicles.php?action=edit&id='.$id); }
            $model->update($id, $_POST);
            if (isset($_POST['tools'])) {
                $model->saveTools($id, $_POST['tools']);
            }
            Session::flash('success', '車輛已更新');
            redirect('/vehicles.php?action=view&id=' . $id);
        }
        $users = $model->getUsers($branchIds);
        $branches = $model->getBranches();
        $pageTitle = '編輯車輛';
        $currentPage = 'vehicles';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/vehicles/form.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    case 'view':
        $id = (int)(isset($_GET['id']) ? $_GET['id'] : 0);
        $vehicle = $model->getById($id);
        if (!$vehicle) { Session::flash('error', '車輛不存在'); redirect('/vehicles.php'); }
        $maintenanceHistory = $model->getMaintenanceHistory($id);
        $pageTitle = '車輛 ' . $vehicle['plate_number'];
        $currentPage = 'vehicles';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/vehicles/view.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    case 'add_maintenance':
        header('Content-Type: application/json');
        Auth::requirePermission('staff.manage');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(array('error' => '方法不允許'));
            exit;
        }
        $vehicleId = (int)(isset($_POST['vehicle_id']) ? $_POST['vehicle_id'] : 0);
        if (!$vehicleId) {
            echo json_encode(array('error' => '缺少車輛 ID'));
            exit;
        }
        $maintId = $model->addMaintenance($vehicleId, $_POST);
        echo json_encode(array('success' => true, 'id' => $maintId));
        exit;

    case 'upload_file':
        header('Content-Type: application/json');
        Auth::requirePermission('staff.manage');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(array('error' => '方法不允許'));
            exit;
        }
        $vehicleId = (int)(isset($_GET['id']) ? $_GET['id'] : (isset($_POST['vehicle_id']) ? $_POST['vehicle_id'] : 0));
        if (!$vehicleId || empty($_FILES['file'])) {
            echo json_encode(array('error' => '缺少必要資料'));
            exit;
        }
        $file = $_FILES['file'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(array('error' => '上傳失敗'));
            exit;
        }
        if ($file['size'] > 10 * 1024 * 1024) {
            echo json_encode(array('error' => '檔案過大（上限10MB）'));
            exit;
        }
        $uploadDir = __DIR__ . '/uploads/vehicles/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $newName = $vehicleId . '_' . time() . '_' . mt_rand(100, 999) . '.' . $ext;
        $dest = $uploadDir . $newName;
        if (move_uploaded_file($file['tmp_name'], $dest)) {
            $fileType = isset($_POST['file_type']) ? $_POST['file_type'] : 'other';
            backup_to_drive($dest, 'vehicles', $vehicleId);
            $fileId = $model->saveFile($vehicleId, $file['name'], '/uploads/vehicles/' . $newName, $fileType);
            echo json_encode(array(
                'success' => true,
                'file' => array('id' => $fileId, 'file_name' => $file['name'], 'file_path' => '/uploads/vehicles/' . $newName),
            ));
        } else {
            echo json_encode(array('error' => '檔案儲存失敗'));
        }
        exit;

    case 'delete_file':
        header('Content-Type: application/json');
        Auth::requirePermission('staff.manage');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(array('error' => '方法不允許'));
            exit;
        }
        $fileId = (int)(isset($_POST['file_id']) ? $_POST['file_id'] : 0);
        if (!$fileId) {
            echo json_encode(array('error' => '缺少檔案 ID'));
            exit;
        }
        $model->deleteFile($fileId);
        echo json_encode(array('success' => true));
        exit;

    case 'delete':
        Auth::requirePermission('staff.manage');
        if (verify_csrf()) {
            $model->deactivate((int)$_GET['id']);
            Session::flash('success', '車輛已停用');
        }
        redirect('/vehicles.php');
        break;

    default:
        redirect('/vehicles.php');
}
