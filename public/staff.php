<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
require_once __DIR__ . '/../modules/staff/StaffModel.php';

$model = new StaffModel();
$action = $_GET['action'] ?? 'list';
$branchIds = Auth::getAccessibleBranchIds();

switch ($action) {
    // ---- 人員清單 ----
    case 'list':
        $filters = [
            'branch_id'   => $_GET['branch_id'] ?? '',
            'role'        => $_GET['role'] ?? '',
            'is_engineer' => $_GET['is_engineer'] ?? '',
            'keyword'     => $_GET['keyword'] ?? '',
        ];
        $users = $model->getList($branchIds, $filters);
        $branches = $model->getAllBranches();
        $appConfig = require __DIR__ . '/../config/app.php';

        $pageTitle = '人員管理';
        $currentPage = 'staff';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/staff/list.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    // ---- 新增人員 ----
    case 'create':
        Auth::requirePermission('staff.manage');
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!verify_csrf()) { Session::flash('error', '安全驗證失敗'); redirect('/staff.php'); }

            // 帳號檢查
            $db = Database::getInstance();
            $chk = $db->prepare('SELECT id FROM users WHERE username = ?');
            $chk->execute([$_POST['username']]);
            if ($chk->fetch()) {
                Session::flash('error', '帳號已存在');
                redirect('/staff.php?action=create');
            }

            $userId = $model->create($_POST);
            Session::flash('success', '人員已新增');
            redirect('/staff.php?action=view&id=' . $userId);
        }
        $branches = $model->getAllBranches();
        $appConfig = require __DIR__ . '/../config/app.php';
        $user = null;

        $pageTitle = '新增人員';
        $currentPage = 'staff';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/staff/form.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    // ---- 編輯人員 ----
    case 'edit':
        Auth::requirePermission('staff.manage');
        $id = (int)($_GET['id'] ?? 0);
        $user = $model->getById($id);
        if (!$user) { Session::flash('error', '人員不存在'); redirect('/staff.php'); }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!verify_csrf()) { Session::flash('error', '安全驗證失敗'); redirect('/staff.php?action=edit&id='.$id); }
            $model->update($id, $_POST);
            Session::flash('success', '人員已更新');
            redirect('/staff.php?action=view&id=' . $id);
        }
        $branches = $model->getAllBranches();
        $appConfig = require __DIR__ . '/../config/app.php';

        $pageTitle = '編輯人員';
        $currentPage = 'staff';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/staff/form.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    // ---- 人員詳情 (技能/證照) ----
    case 'view':
        $id = (int)($_GET['id'] ?? 0);
        $user = $model->getById($id);
        if (!$user) { Session::flash('error', '人員不存在'); redirect('/staff.php'); }

        $userSkills = $model->getUserSkills($id);
        $userCerts = $model->getUserCertifications($id);

        $pageTitle = $user['real_name'] . ' - 人員資料';
        $currentPage = 'staff';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/staff/view.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    // ---- 技能管理 ----
    case 'skills':
        Auth::requirePermission('staff.manage');
        $id = (int)($_GET['id'] ?? 0);
        $user = $model->getById($id);
        if (!$user) { Session::flash('error', '人員不存在'); redirect('/staff.php'); }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!verify_csrf()) { Session::flash('error', '安全驗證失敗'); redirect('/staff.php?action=skills&id='.$id); }
            $model->saveUserSkills($id, $_POST['skills'] ?? []);
            Session::flash('success', '技能已更新');
            redirect('/staff.php?action=view&id=' . $id);
        }

        $allSkills = $model->getAllSkills();
        $userSkills = $model->getUserSkills($id);
        $userSkillMap = [];
        foreach ($userSkills as $us) { $userSkillMap[$us['skill_id']] = $us['proficiency']; }

        $pageTitle = $user['real_name'] . ' - 技能設定';
        $currentPage = 'staff';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/staff/skills.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    // ---- 證照管理 ----
    case 'add_cert':
        Auth::requirePermission('staff.manage');
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf()) {
            $model->addCertification((int)$_POST['user_id'], $_POST);
            Session::flash('success', '證照已新增');
            redirect('/staff.php?action=view&id=' . (int)$_POST['user_id']);
        }
        redirect('/staff.php');
        break;

    case 'remove_cert':
        Auth::requirePermission('staff.manage');
        if (verify_csrf()) {
            $model->removeCertification((int)$_GET['cert_id']);
        }
        redirect('/staff.php?action=view&id=' . (int)$_GET['user_id']);
        break;

    // ---- 配對表 ----
    case 'pairs':
        Auth::requirePermission('staff.manage');
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf()) {
            $model->savePair(
                (int)$_POST['user_a_id'],
                (int)$_POST['user_b_id'],
                (int)$_POST['compatibility'],
                $_POST['note'] ?? null
            );
            Session::flash('success', '配對已更新');
            redirect('/staff.php?action=pairs');
        }

        $pairs = $model->getPairs($branchIds);
        $engineers = $model->getEngineers($branchIds);

        $pageTitle = '人員配對表';
        $currentPage = 'staff';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/staff/pairs.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    // ---- 停用/啟用 ----
    case 'toggle':
        Auth::requirePermission('staff.manage');
        if (verify_csrf()) {
            $model->toggleActive((int)$_GET['id']);
            Session::flash('success', '已更新人員狀態');
        }
        redirect('/staff.php');
        break;

    default:
        redirect('/staff.php');
}
