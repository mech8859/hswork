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
        // 沒有人員管理/檢視/技能管理權限的人 → 只能改密碼
        if (!Auth::hasPermission('staff.manage') && !Auth::hasPermission('staff.view') && !Auth::hasPermission('staff_skills.manage')) {
            redirect('/staff.php?action=change_password');
        }
        $filters = [
            'branch_id'   => $_GET['branch_id'] ?? '',
            'role'        => $_GET['role'] ?? '',
            'is_engineer' => $_GET['is_engineer'] ?? '',
            'is_active'   => $_GET['is_active'] ?? '',
            'emp_status'  => isset($_GET['emp_status']) ? $_GET['emp_status'] : 'working',
            'keyword'     => $_GET['keyword'] ?? '',
        ];
        $users = $model->getList($branchIds, $filters);
        $branches = $model->getAllBranches();
        $appConfig = require __DIR__ . '/../config/app.php';

        // 在職人數統計
        $db = Database::getInstance();
        $staffCounts = array('active' => 0, 'probation' => 0, 'suspended' => 0, 'resigned' => 0, 'total' => 0);
        try {
            $cntStmt = $db->query("SELECT employment_status, COUNT(*) as cnt FROM users WHERE employment_status IN ('active','probation','suspended') AND employee_id IS NOT NULL AND employee_id != '' GROUP BY employment_status");
            foreach ($cntStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $key = $row['employment_status'];
                if (isset($staffCounts[$key])) {
                    $staffCounts[$key] = (int)$row['cnt'];
                }
            }
            $staffCounts['total'] = $staffCounts['active'] + $staffCounts['probation'] + $staffCounts['suspended'];
        } catch (Exception $e) {}

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
        $mentorCandidates = $model->getMentorCandidates(0);

        $pageTitle = '新增人員';
        $currentPage = 'staff';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/staff/form.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    // ---- 編輯人員 ----
    case 'edit':
        $id = (int)($_GET['id'] ?? 0);
        $limitedEdit = false; // 限制模式：只能看到姓名與據點
        if (!Auth::hasPermission('staff.manage')) {
            // 有技能管理權限 → 限制模式編輯
            if (Auth::hasPermission('staff_skills.manage')) {
                $limitedEdit = true;
            } else {
                if ($id !== Auth::id()) {
                    Session::flash('error', '權限不足');
                    redirect('/staff.php');
                }
                // 沒權限 → 導向修改密碼
                redirect('/staff.php?action=change_password');
            }
        }
        $user = $model->getById($id);
        if (!$user) { Session::flash('error', '人員不存在'); redirect('/staff.php'); }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!verify_csrf()) { Session::flash('error', '安全驗證失敗'); redirect('/staff.php?action=edit&id='.$id); }

            // 帳號修改檢查（僅 boss 可修改）
            if (Auth::user()['role'] === 'boss' && !empty($_POST['username']) && $_POST['username'] !== $user['username']) {
                $db = Database::getInstance();
                $chk = $db->prepare('SELECT id FROM users WHERE username = ? AND id != ?');
                $chk->execute([$_POST['username'], $id]);
                if ($chk->fetch()) {
                    Session::flash('error', '帳號已存在');
                    redirect('/staff.php?action=edit&id=' . $id);
                }
            } elseif (Auth::user()['role'] !== 'boss') {
                // 非 boss 不允許修改帳號
                unset($_POST['username']);
            }

            // 個人權限設定在獨立的 permissions 頁處理，這裡不動 custom_permissions
            // 確保編輯基本資料時不會覆蓋已設定的權限
            unset($_POST['custom_permissions']);

            $oldUser = $model->getById($id);
            $model->update($id, $_POST);
            AuditLog::logChange('staff', $id, $oldUser['real_name'], $oldUser, $_POST, array('real_name','role','branch_id','phone','email','is_engineer','is_sales','can_view_all_branches','holiday_availability','night_availability','caution_notes','employee_id','id_number','birth_date','gender','marital_status','blood_type','education_level','job_title','address','bank_name','bank_account','hire_date','resignation_date','employment_status','labor_insurance_company','labor_insurance_date','dependent_insurance','annual_leave_days','engineer_level','can_lead','repair_priority','mentor_id','mentor_start_date'));

            // 更新現有緊急聯絡人
            if (!empty($_POST['ec']) && is_array($_POST['ec'])) {
                foreach ($_POST['ec'] as $ecId => $ecData) {
                    if (!empty($ecData['contact_name'])) {
                        $model->updateEmergencyContact((int)$ecId, $ecData);
                    }
                }
            }
            // 新增緊急聯絡人
            if (!empty($_POST['ec_new']) && is_array($_POST['ec_new'])) {
                foreach ($_POST['ec_new'] as $ecData) {
                    if (!empty($ecData['contact_name'])) {
                        $model->addEmergencyContact($id, $ecData);
                    }
                }
            }

            Session::flash('success', '人員已更新');
            redirect('/staff.php?action=view&id=' . $id);
        }
        $branches = $model->getAllBranches();
        $appConfig = require __DIR__ . '/../config/app.php';
        $mentorCandidates = $model->getMentorCandidates($user ? $user['id'] : 0);

        $pageTitle = '編輯人員';
        $currentPage = 'staff';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/staff/form.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    // ---- 人員詳情 (技能/證照) ----
    case 'view':
        $id = (int)($_GET['id'] ?? 0);
        // 非管理者/檢視/技能管理權限者只能看自己
        if (!Auth::hasPermission('staff.manage') && !Auth::hasPermission('staff.view') && !Auth::hasPermission('staff_skills.manage') && $id !== Auth::id()) {
            redirect('/staff.php?action=view&id=' . Auth::id());
        }
        $user = $model->getById($id);
        if (!$user) { Session::flash('error', '人員不存在'); redirect('/staff.php'); }

        $userSkills = $model->getUserSkills($id);
        $userCerts = $model->getUserCertifications($id);
        $vendorTrainings = $model->getVendorTrainings($id);

        // 證照文件
        $docTypes = $model->getDocTypes();
        $userDocs = $model->getDocuments($id);
        // 建立 docType => doc 的映射
        $docMap = array();
        foreach ($userDocs as $doc) {
            $docMap[$doc['doc_type']] = $doc;
        }

        $pageTitle = $user['real_name'] . ' - 人員資料';
        $currentPage = 'staff';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/staff/view.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    // ---- 技能管理 ----
    case 'skills':
        if (!Auth::hasPermission('staff_skills.manage') && !Auth::hasPermission('staff.manage')) {
            Session::flash('error', '無權限'); redirect('/staff.php');
        }
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
        $userSkillMap = array();
        foreach ($userSkills as $us) { $userSkillMap[$us['skill_id']] = $us['proficiency']; }

        // 配對資料（放在技能設定旁）
        $userPairs = $model->getUserPairs($id);
        $engineers = $model->getEngineers($branchIds);

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

    // ---- 廠商上課證管理 ----
    case 'add_vendor_training':
        Auth::requirePermission('staff.manage');
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf()) {
            $model->addVendorTraining((int)$_POST['user_id'], $_POST);
            Session::flash('success', '廠商上課證已新增');
            redirect('/staff.php?action=view&id=' . (int)$_POST['user_id']);
        }
        redirect('/staff.php');
        break;

    case 'remove_vendor_training':
        Auth::requirePermission('staff.manage');
        if (verify_csrf()) {
            $model->removeVendorTraining((int)$_GET['vt_id']);
        }
        redirect('/staff.php?action=view&id=' . (int)$_GET['user_id']);
        break;

    // ---- 配對表（個人） ----
    case 'pairs':
        if (!Auth::hasPermission('staff_skills.manage') && !Auth::hasPermission('staff.manage')) {
            Session::flash('error', '無權限'); redirect('/staff.php');
        }
        $userId = (int)($_GET['id'] ?? 0);
        if (!$userId) { Session::flash('error', '請指定人員'); redirect('/staff.php'); }

        $targetUser = $model->getById($userId);
        if (!$targetUser) { Session::flash('error', '人員不存在'); redirect('/staff.php'); }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf()) {
            // 批次儲存配對
            $pairData = $_POST['pairs'] ?? array();
            foreach ($pairData as $partnerId => $info) {
                $partnerId = (int)$partnerId;
                $compat = (int)($info['compatibility'] ?? 3);
                $note = trim($info['note'] ?? '');
                if ($compat > 0) {
                    $model->savePair($userId, $partnerId, $compat, $note ?: null);
                }
            }
            Session::flash('success', '配對已更新');
            redirect('/staff.php?action=pairs&id=' . $userId);
        }

        $engineers = $model->getEngineers($branchIds);
        // 取得此人員的所有配對
        $existingPairs = array();
        $allPairs = $model->getPairs($branchIds);
        foreach ($allPairs as $p) {
            if ((int)$p['user_a_id'] === $userId) {
                $existingPairs[(int)$p['user_b_id']] = $p;
            } elseif ((int)$p['user_b_id'] === $userId) {
                $existingPairs[(int)$p['user_a_id']] = $p;
            }
        }

        $pageTitle = $targetUser['real_name'] . ' - 配對表';
        $currentPage = 'staff';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/staff/pairs_individual.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    // ---- 刪除配對 ----
    case 'delete_pair':
        if (!Auth::hasPermission('staff_skills.manage') && !Auth::hasPermission('staff.manage')) {
            Session::flash('error', '無權限'); redirect('/staff.php');
        }
        if (verify_csrf()) {
            $model->deletePair((int)$_GET['pair_id']);
            Session::flash('success', '配對已刪除');
        }
        $returnTo = $_GET['return'] ?? 'pairs';
        if ($returnTo === 'skills' && !empty($_GET['user_id'])) {
            redirect('/staff.php?action=skills&id=' . (int)$_GET['user_id']);
        }
        redirect('/staff.php?action=pairs');
        break;

    // ---- 技能項目管理（CRUD） ----
    case 'manage_skills':
        if (!Auth::hasPermission('staff_skills.manage') && !Auth::hasPermission('staff.manage')) {
            Session::flash('error', '無權限'); redirect('/staff.php');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf()) {
            $subAction = $_POST['sub_action'] ?? '';
            if ($subAction === 'create') {
                $model->createSkill($_POST);
                Session::flash('success', '技能已新增');
            } elseif ($subAction === 'update' && !empty($_POST['skill_id'])) {
                $model->updateSkill((int)$_POST['skill_id'], $_POST);
                Session::flash('success', '技能已更新');
            } elseif ($subAction === 'delete' && !empty($_POST['skill_id'])) {
                $model->deleteSkill((int)$_POST['skill_id']);
                Session::flash('success', '技能已刪除');
            }
            redirect('/staff.php?action=manage_skills');
        }

        $skillGroups = $model->getSkillGroups();
        $distinctGroups = $model->getDistinctSkillGroups();
        $distinctCategories = $model->getDistinctCategories();

        $pageTitle = '技能項目管理';
        $currentPage = 'staff';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/staff/manage_skills.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    // ---- 停用/啟用 ---- (boss only)
    case 'toggle':
        if (!Auth::hasPermission('all')) { Session::flash('error', '無權限'); redirect('/staff.php'); }
        if (verify_csrf()) {
            $id = (int)$_GET['id'];
            // 不能停用自己
            if ($id === Auth::id()) {
                Session::flash('error', '不能停用自己的帳號');
            } else {
                $model->toggleActive($id);
                Session::flash('success', '已更新人員狀態');
            }
        }
        redirect('/staff.php');
        break;

    // ---- 重設密碼 ---- (boss only)
    case 'reset_password':
        if (!Auth::hasPermission('all')) { Session::flash('error', '無權限'); redirect('/staff.php'); }
        $id = (int)($_GET['id'] ?? 0);
        $user = $model->getById($id);
        if (!$user) { Session::flash('error', '人員不存在'); redirect('/staff.php'); }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!verify_csrf()) { Session::flash('error', '安全驗證失敗'); redirect('/staff.php?action=view&id='.$id); }

            $newPassword = trim($_POST['new_password'] ?? '');
            $confirmPassword = trim($_POST['confirm_password'] ?? '');

            if (strlen($newPassword) < 6) {
                Session::flash('error', '密碼長度至少 6 個字元');
                redirect('/staff.php?action=reset_password&id=' . $id);
            } elseif ($newPassword !== $confirmPassword) {
                Session::flash('error', '兩次密碼輸入不一致');
                redirect('/staff.php?action=reset_password&id=' . $id);
            } else {
                $model->resetPassword($id, $newPassword);
                Session::flash('success', '密碼已重設');
                redirect('/staff.php?action=view&id=' . $id);
            }
        }

        $pageTitle = '重設密碼 - ' . $user['real_name'];
        $currentPage = 'staff';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/staff/reset_password.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    // ---- 解除鎖定 ----
    case 'unlock':
        Auth::requirePermission('staff.manage');
        if (verify_csrf()) {
            $id = (int)$_GET['id'];
            $model->unlockAccount($id);
            Session::flash('success', '帳號已解除鎖定');
        }
        redirect('/staff.php?action=view&id=' . (int)$_GET['id']);
        break;

    // ---- 分公司管理 ----
    case 'branches':
        if (Auth::user()['role'] !== 'boss') { Session::flash('error', '權限不足'); redirect('/staff.php'); }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf()) {
            $subAction = isset($_POST['sub_action']) ? $_POST['sub_action'] : '';
            if ($subAction === 'create') {
                $model->createBranch($_POST);
                Session::flash('success', '分公司已新增');
            } elseif ($subAction === 'update' && !empty($_POST['branch_id'])) {
                $model->updateBranch((int)$_POST['branch_id'], $_POST);
                Session::flash('success', '分公司已更新');
            }
            redirect('/staff.php?action=branches');
        }

        $branches = $model->getAllBranchesIncludeInactive();
        $pageTitle = '分公司管理';
        $currentPage = 'staff';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/staff/branches.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    // ---- 權限設定 ---- (boss only)
    case 'permissions':
        if (!Auth::hasPermission('all')) { Session::flash('error', '無權限'); redirect('/staff.php'); }
        $id = (int)($_GET['id'] ?? 0);
        $user = $model->getById($id);
        if (!$user) { Session::flash('error', '人員不存在'); redirect('/staff.php'); }

        $appConfig = require __DIR__ . '/../config/app.php';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!verify_csrf()) { Session::flash('error', '安全驗證失敗'); redirect('/staff.php?action=permissions&id=' . $id); }

            // 組合自訂權限 JSON
            $allModules = array('cases','schedule','repairs','staff','staff_skills','leaves','overtime','inter_branch','reports','products','vehicles','worklog','attendance','quotations','customers','business_calendar','business_tracking','engineering_tracking','finance','transactions','petty_cash','procurement','inventory','accounting','approvals','reviews','settings','system');
            $customPerms = array();
            $hasCustom = false;

            foreach ($allModules as $mod) {
                $val = isset($_POST['perm_' . $mod]) ? $_POST['perm_' . $mod] : 'default';
                if ($val === 'default') {
                    // 不覆蓋，使用角色預設
                    continue;
                }
                $hasCustom = true;
                if ($val === 'off') {
                    $customPerms[$mod] = false;
                } else {
                    // val 格式為 "module.level"
                    $customPerms[$mod] = $val;
                }
            }

            // 刪除權限（獨立 checkbox）
            $deleteModules = array('cases', 'schedule', 'repairs', 'quotations', 'customers', 'leaves', 'inter_branch', 'products', 'inventory', 'finance', 'transactions');
            foreach ($deleteModules as $dm) {
                $deleteKey = 'delete_' . $dm;
                if (!empty($_POST[$deleteKey])) {
                    $hasCustom = true;
                    $customPerms[$deleteKey] = true;
                } else {
                    // 只在表單有提交時才設為 false（表示明確取消勾選）
                    $hasCustom = true;
                    $customPerms[$deleteKey] = false;
                }
            }

            // 新增權限（獨立 checkbox，目前案件/客戶用）
            $createModules = array('cases', 'customers');
            foreach ($createModules as $cm) {
                $createKey = 'create_' . $cm;
                if (!empty($_POST[$createKey])) {
                    $hasCustom = true;
                    $customPerms[$createKey] = true;
                } else {
                    $hasCustom = true;
                    $customPerms[$createKey] = false;
                }
            }

            // 案件編輯區域
            if (empty($_POST['case_section_use_default'])) {
                $hasCustom = true;
                $sectionKeys = array('basic','finance','schedule','attach','site','contacts','skills','delete','worklog');
                $selectedSections = array();
                foreach ($sectionKeys as $sk) {
                    if (!empty($_POST['case_section_' . $sk])) {
                        $selectedSections[] = $sk;
                    }
                }
                $customPerms['case_sections'] = $selectedSections;
            }

            // 報表存取
            if (empty($_POST['report_use_default'])) {
                $hasCustom = true;
                $reportKeys = array_keys($appConfig['report_labels']);
                $selectedReports = array();
                foreach ($reportKeys as $rk) {
                    if (!empty($_POST['report_access_' . $rk])) {
                        $selectedReports[] = $rk;
                    }
                }
                $customPerms['report_access'] = $selectedReports;
            }

            // 儲存
            $permJson = $hasCustom ? json_encode($customPerms, JSON_UNESCAPED_UNICODE) : null;
            $db = Database::getInstance();
            $db->prepare('UPDATE users SET custom_permissions = ? WHERE id = ?')
               ->execute(array($permJson, $id));

            Session::flash('success', '權限設定已儲存（' . count($customPerms) . ' 項自訂）');
            redirect('/staff.php?action=permissions&id=' . $id);
        }

        $pageTitle = '權限設定 - ' . $user['real_name'];
        $currentPage = 'staff';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/staff/permissions.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    // ---- 修改自己密碼 ----
    case 'change_password':
        $myId = Auth::id();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!verify_csrf()) { Session::flash('error', '安全驗證失敗'); redirect('/staff.php?action=change_password'); }
            $currentPw = $_POST['current_password'] ?? '';
            $newPw = $_POST['new_password'] ?? '';
            $confirmPw = $_POST['confirm_password'] ?? '';

            $me = $model->getById($myId);
            if (!password_verify($currentPw, $me['password_hash'])) {
                Session::flash('error', '目前密碼錯誤');
                redirect('/staff.php?action=change_password');
            }
            $pwErr = validate_password($newPw);
            if ($pwErr) {
                Session::flash('error', $pwErr);
                redirect('/staff.php?action=change_password');
            }
            if ($newPw !== $confirmPw) {
                Session::flash('error', '新密碼與確認密碼不一致');
                redirect('/staff.php?action=change_password');
            }
            $db = Database::getInstance();
            $db->prepare('UPDATE users SET password_hash = ?, plain_password = ?, must_change_password = 0 WHERE id = ?')
               ->execute(array(password_hash($newPw, PASSWORD_DEFAULT), $newPw, $myId));
            AuditLog::log('staff', 'change_password', $myId, '自行修改密碼');
            Session::flash('success', '密碼已更新');
            redirect('/staff.php?action=view&id=' . $myId);
        }

        $pageTitle = '修改密碼';
        $currentPage = 'staff';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/staff/change_password.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    // ---- 首次登入強制改密碼 ----
    case 'force_change_password':
        $myId = Auth::id();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!verify_csrf()) { Session::flash('error', '安全驗證失敗'); redirect('/staff.php?action=force_change_password'); }
            $newPw = $_POST['new_password'] ?? '';
            $confirmPw = $_POST['confirm_password'] ?? '';

            $pwErr = validate_password($newPw);
            if ($pwErr) {
                Session::flash('error', $pwErr);
                redirect('/staff.php?action=force_change_password');
            }
            if ($newPw !== $confirmPw) {
                Session::flash('error', '新密碼與確認密碼不一致');
                redirect('/staff.php?action=force_change_password');
            }
            $db = Database::getInstance();
            $db->prepare('UPDATE users SET password_hash = ?, plain_password = ?, must_change_password = 0 WHERE id = ?')
               ->execute(array(password_hash($newPw, PASSWORD_DEFAULT), $newPw, $myId));
            AuditLog::log('staff', 'force_change_password', $myId, '首次登入修改密碼');
            Session::flash('success', '密碼已更新，歡迎使用系統');
            redirect('/index.php');
        }

        $pageTitle = '設定新密碼';
        $currentPage = 'staff';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/staff/force_change_password.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    // ---- 刪除緊急聯絡人 (AJAX) ----
    case 'remove_emergency_contact':
        Auth::requirePermission('staff.manage');
        header('Content-Type: application/json');
        if (verify_csrf()) {
            $model->removeEmergencyContact((int)$_GET['ec_id']);
            echo json_encode(array('success' => true));
        } else {
            echo json_encode(array('success' => false, 'message' => '安全驗證失敗'));
        }
        exit;

    // ---- 文件上傳 (AJAX) ----
    case 'upload_doc':
        Auth::requirePermission('staff.manage');
        header('Content-Type: application/json');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(array('success' => false, 'message' => '方法不允許'));
            exit;
        }
        if (!verify_csrf()) {
            echo json_encode(array('success' => false, 'message' => '安全驗證失敗'));
            exit;
        }
        $userId = (int)($_POST['user_id'] ?? 0);
        $docType = trim($_POST['doc_type'] ?? '');
        $docLabel = trim($_POST['doc_label'] ?? '');
        if (!$userId || !$docType || !$docLabel) {
            echo json_encode(array('success' => false, 'message' => '缺少必要參數'));
            exit;
        }
        if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(array('success' => false, 'message' => '檔案上傳失敗'));
            exit;
        }
        $file = $_FILES['file'];
        $allowedExts = array('jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf');
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExts)) {
            echo json_encode(array('success' => false, 'message' => '不支援的檔案格式，僅接受: ' . implode(', ', $allowedExts)));
            exit;
        }
        if ($file['size'] > 10 * 1024 * 1024) {
            echo json_encode(array('success' => false, 'message' => '檔案大小不可超過 10MB'));
            exit;
        }
        // 建立目錄
        $uploadDir = __DIR__ . '/uploads/staff_docs/' . $userId;
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        $newFileName = $docType . '_' . time() . '.' . $ext;
        $destPath = $uploadDir . '/' . $newFileName;
        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
            echo json_encode(array('success' => false, 'message' => '檔案儲存失敗'));
            exit;
        }
        $relPath = '/uploads/staff_docs/' . $userId . '/' . $newFileName;
        backup_to_drive($destPath, 'staff', $userId);
        $docId = $model->uploadDocument($userId, $docType, $docLabel, $relPath, $file['name']);
        $isPdf = ($ext === 'pdf');
        echo json_encode(array(
            'success' => true,
            'doc_id' => $docId,
            'file_path' => $relPath,
            'file_name' => $file['name'],
            'is_pdf' => $isPdf
        ));
        exit;

    // ---- 文件刪除 (AJAX) ----
    case 'delete_doc':
        Auth::requirePermission('staff.manage');
        header('Content-Type: application/json');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(array('success' => false, 'message' => '方法不允許'));
            exit;
        }
        if (!verify_csrf()) {
            echo json_encode(array('success' => false, 'message' => '安全驗證失敗'));
            exit;
        }
        $docId = (int)($_POST['doc_id'] ?? 0);
        if (!$docId) {
            echo json_encode(array('success' => false, 'message' => '缺少文件ID'));
            exit;
        }
        $doc = $model->deleteDocument($docId);
        if ($doc && !empty($doc['file_path'])) {
            $fullPath = __DIR__ . $doc['file_path'];
            if (file_exists($fullPath)) {
                @unlink($fullPath);
            }
        }
        echo json_encode(array('success' => true));
        exit;

    // ---- 新增自訂文件類型 (AJAX, boss only) ----
    case 'add_doc_type':
        header('Content-Type: application/json');
        if (!Auth::hasPermission('all')) {
            echo json_encode(array('success' => false, 'message' => '權限不足'));
            exit;
        }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(array('success' => false, 'message' => '方法不允許'));
            exit;
        }
        if (!verify_csrf()) {
            echo json_encode(array('success' => false, 'message' => '安全驗證失敗'));
            exit;
        }
        $typeLabel = trim($_POST['type_label'] ?? '');
        if (!$typeLabel) {
            echo json_encode(array('success' => false, 'message' => '請輸入文件類型名稱'));
            exit;
        }
        // 產生 key：用拼音或隨機
        $typeKey = 'custom_' . time();
        try {
            $newId = $model->addDocType($typeKey, $typeLabel);
            echo json_encode(array('success' => true, 'type_key' => $typeKey, 'type_label' => $typeLabel, 'id' => $newId));
        } catch (PDOException $e) {
            echo json_encode(array('success' => false, 'message' => '新增失敗: ' . $e->getMessage()));
        }
        exit;

    default:
        redirect('/staff.php');
}
