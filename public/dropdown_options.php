<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
Auth::requirePermission('settings.manage');
require_once __DIR__ . '/../modules/settings/DropdownModel.php';

$model = new DropdownModel();
$action = isset($_GET['action']) ? $_GET['action'] : 'list';

switch ($action) {
    case 'list':
        $tab = isset($_GET['tab']) ? $_GET['tab'] : 'dropdown';

        if ($tab === 'numbering') {
            $sequences = $model->getNumberSequences();
            $pageTitle = '選單管理 - 單號設定';
            $currentPage = 'dropdown_options';
            require __DIR__ . '/../templates/layouts/header.php';
            require __DIR__ . '/../templates/settings/numbering.php';
            require __DIR__ . '/../templates/layouts/footer.php';
        } elseif ($tab === 'quotation') {
            $settings = $model->getSettings('quotation');
            $flash_success = Session::getFlash('success');
            $pageTitle = '系統設定 - 報價單設定';
            $currentPage = 'dropdown_options';
            require __DIR__ . '/../templates/layouts/header.php';
            require __DIR__ . '/../templates/settings/quotation_settings.php';
            require __DIR__ . '/../templates/layouts/footer.php';
        } elseif ($tab === 'roles') {
            $roles = $model->getAllRoles();
            // 取得每個角色的使用人數
            $roleUserCounts = array();
            foreach ($roles as $r) {
                $roleUserCounts[$r['role_key']] = $model->getRoleUserCount($r['role_key']);
            }
            $pageTitle = '選單管理 - 人員角色';
            $currentPage = 'dropdown_options';
            require __DIR__ . '/../templates/layouts/header.php';
            require __DIR__ . '/../templates/settings/roles_list.php';
            require __DIR__ . '/../templates/layouts/footer.php';
        } else {
            $category = isset($_GET['category']) ? $_GET['category'] : 'customer_demand';
            $categories = $model->getCategories();
            if (!isset($categories[$category])) {
                $category = 'customer_demand';
            }
            $options = $model->getAllOptions($category);
            $pageTitle = '選單管理 - 下拉選項';
            $currentPage = 'dropdown_options';
            require __DIR__ . '/../templates/layouts/header.php';
            require __DIR__ . '/../templates/settings/dropdown_list.php';
            require __DIR__ . '/../templates/layouts/footer.php';
        }
        break;

    case 'add':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('/dropdown_options.php');
        }
        if (!verify_csrf()) {
            Session::flash('error', '安全驗證失敗');
            redirect('/dropdown_options.php');
        }
        $category = isset($_POST['category']) ? $_POST['category'] : '';
        $label = isset($_POST['label']) ? trim($_POST['label']) : '';
        $optionKey = isset($_POST['option_key']) ? trim($_POST['option_key']) : '';
        if ($category && $label) {
            if ($model->isKeyedCategory($category) && $optionKey) {
                $model->addKeyedOption($category, $optionKey, $label);
            } else {
                $model->addOption($category, $label);
            }
            Session::flash('success', '已新增選項');
        }
        redirect('/dropdown_options.php?category=' . urlencode($category));
        break;

    case 'update':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            json_response(array('error' => '方法不允許'), 405);
        }
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $label = isset($_POST['label']) ? trim($_POST['label']) : '';
        if ($id && $label) {
            $model->updateOption($id, $label);
            json_response(array('success' => true));
        }
        json_response(array('error' => '缺少參數'));
        break;

    case 'toggle':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            json_response(array('error' => '方法不允許'), 405);
        }
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $active = isset($_POST['active']) ? (int)$_POST['active'] : 0;
        if ($id) {
            if ($active) {
                $model->activateOption($id);
            } else {
                $model->deactivateOption($id);
            }
            json_response(array('success' => true));
        }
        json_response(array('error' => '缺少參數'));
        break;

    // ============ 子分類管理（付款單分類）============
    case 'get_sub_options':
        header('Content-Type: application/json');
        $parentId = isset($_GET['parent_id']) ? (int)$_GET['parent_id'] : 0;
        if ($parentId) {
            echo json_encode(array('success' => true, 'data' => $model->getSubOptions($parentId)));
        } else {
            echo json_encode(array('success' => false, 'error' => '缺少 parent_id'));
        }
        exit;

    case 'add_sub_option':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            json_response(array('error' => '方法不允許'), 405);
        }
        $category = isset($_POST['category']) ? $_POST['category'] : '';
        $parentId = isset($_POST['parent_id']) ? (int)$_POST['parent_id'] : 0;
        $label = isset($_POST['label']) ? trim($_POST['label']) : '';
        if ($category && $parentId && $label) {
            $newId = $model->addSubOption($category, $parentId, $label);
            json_response(array('success' => true, 'id' => $newId));
        }
        json_response(array('error' => '缺少參數'));
        break;

    // ============ 角色管理 ============
    case 'add_role':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('/dropdown_options.php?tab=roles');
        }
        if (!verify_csrf()) {
            Session::flash('error', '安全驗證失敗');
            redirect('/dropdown_options.php?tab=roles');
        }
        // 只有 boss 可管理角色
        if (Auth::user()['role'] !== 'boss') {
            Session::flash('error', '權限不足');
            redirect('/dropdown_options.php?tab=roles');
        }
        $roleKey = isset($_POST['role_key']) ? trim($_POST['role_key']) : '';
        $roleLabel = isset($_POST['role_label']) ? trim($_POST['role_label']) : '';
        // 驗證 role_key 格式（小寫英文+底線）
        if ($roleKey && $roleLabel && preg_match('/^[a-z][a-z0-9_]{1,48}$/', $roleKey)) {
            if ($model->roleKeyExists($roleKey)) {
                Session::flash('error', '角色代碼已存在');
            } else {
                $model->addRole($roleKey, $roleLabel);
                Session::flash('success', '已新增角色「' . $roleLabel . '」');
            }
        } else {
            Session::flash('error', '角色代碼須為小寫英文開頭，僅可含小寫英文、數字、底線，2~49字元');
        }
        redirect('/dropdown_options.php?tab=roles');
        break;

    case 'update_role':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            json_response(array('error' => '方法不允許'), 405);
        }
        if (Auth::user()['role'] !== 'boss') {
            json_response(array('error' => '權限不足'), 403);
        }
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $roleLabel = isset($_POST['role_label']) ? trim($_POST['role_label']) : '';
        $roleKey = isset($_POST['role_key']) ? trim($_POST['role_key']) : '';
        if ($id && $roleLabel) {
            $existing = $model->getRoleById($id);
            if (!$existing) {
                json_response(array('error' => '角色不存在'));
            }
            // 如果有傳 role_key 且不同，檢查是否重複，並更新使用者
            if ($roleKey && $roleKey !== $existing['role_key']) {
                if (!preg_match('/^[a-z][a-z0-9_]{1,48}$/', $roleKey)) {
                    json_response(array('error' => '角色代碼格式錯誤'));
                }
                if ($model->roleKeyExists($roleKey, $id)) {
                    json_response(array('error' => '角色代碼已存在'));
                }
                // 更新 users 表中的 role 值
                $db = Database::getInstance();
                $db->prepare('UPDATE users SET role = ? WHERE role = ?')
                   ->execute(array($roleKey, $existing['role_key']));
                $model->updateRoleFull($id, $roleKey, $roleLabel);
            } else {
                $model->updateRole($id, $roleLabel);
            }
            json_response(array('success' => true));
        }
        json_response(array('error' => '缺少參數'));
        break;

    case 'toggle_role':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            json_response(array('error' => '方法不允許'), 405);
        }
        if (Auth::user()['role'] !== 'boss') {
            json_response(array('error' => '權限不足'), 403);
        }
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $active = isset($_POST['active']) ? (int)$_POST['active'] : 0;
        if ($id) {
            $existing = $model->getRoleById($id);
            if (!$existing) {
                json_response(array('error' => '角色不存在'));
            }
            // 不能停用有使用者的角色
            if (!$active) {
                $userCount = $model->getRoleUserCount($existing['role_key']);
                if ($userCount > 0) {
                    json_response(array('error' => '此角色尚有 ' . $userCount . ' 位使用者，無法停用'));
                }
                // 不能停用系統內建角色 boss
                if ($existing['role_key'] === 'boss') {
                    json_response(array('error' => '系統管理者角色無法停用'));
                }
                $model->deactivateRole($id);
            } else {
                $model->activateRole($id);
            }
            json_response(array('success' => true));
        }
        json_response(array('error' => '缺少參數'));
        break;

    // ============ 角色預設權限 ============
    case 'role_permissions':
        if (Auth::user()['role'] !== 'boss') {
            Session::flash('error', '權限不足');
            redirect('/dropdown_options.php?tab=roles');
        }
        $roleId = isset($_GET['role_id']) ? (int)$_GET['role_id'] : 0;
        $role = $model->getRoleById($roleId);
        if (!$role) {
            Session::flash('error', '角色不存在');
            redirect('/dropdown_options.php?tab=roles');
        }
        $pageTitle = '角色預設權限 - ' . $role['role_label'];
        $currentPage = 'dropdown_options';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/settings/role_permissions.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    case 'sync_role_permissions':
        if (Auth::user()['role'] !== 'boss') { header('Content-Type: application/json'); echo json_encode(array('error' => '無權限')); break; }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf()) { header('Content-Type: application/json'); echo json_encode(array('error' => '安全驗證失敗')); break; }
        $syncRoleKey = isset($_POST['role_key']) ? trim($_POST['role_key']) : '';
        if (!$syncRoleKey || $syncRoleKey === 'boss') { header('Content-Type: application/json'); echo json_encode(array('error' => '無效角色')); break; }
        $syncDb = Database::getInstance();

        // 1. 確保 system_roles.default_permissions 是最新的
        // 讀取目前資料庫的角色預設權限
        $syncRoleStmt = $syncDb->prepare("SELECT default_permissions FROM system_roles WHERE role_key = ?");
        $syncRoleStmt->execute(array($syncRoleKey));
        $syncRolePerms = $syncRoleStmt->fetchColumn();

        // 如果資料庫的角色預設權限為空，從 config/app.php 回填
        if (empty($syncRolePerms)) {
            $syncConfig = require __DIR__ . '/../config/app.php';
            if (isset($syncConfig['permissions'][$syncRoleKey])) {
                // 轉換 config 格式 → DB JSON 格式
                $configPerms = $syncConfig['permissions'][$syncRoleKey];
                $dbPerms = array();
                foreach ($configPerms as $perm) {
                    if ($perm === 'all') {
                        $dbPerms['_all'] = true;
                    } elseif (strpos($perm, '.delete') !== false) {
                        $mod = str_replace('.delete', '', $perm);
                        $dbPerms['delete_' . $mod] = true;
                    } else {
                        $parts = explode('.', $perm);
                        if (count($parts) === 2) {
                            $dbPerms[$parts[0]] = $perm;
                        }
                    }
                }
                // 回寫到資料庫
                $syncDb->prepare("UPDATE system_roles SET default_permissions = ? WHERE role_key = ?")
                    ->execute(array(json_encode($dbPerms), $syncRoleKey));
            }
        }

        // 2. 計算該角色人數
        $cntStmt = $syncDb->prepare("SELECT COUNT(*) FROM users WHERE role = ? AND is_active = 1");
        $cntStmt->execute(array($syncRoleKey));
        $syncCount = (int)$cntStmt->fetchColumn();

        // 3. 清除所有該角色使用者的自訂權限，回歸角色預設
        $syncDb->prepare("UPDATE users SET custom_permissions = NULL WHERE role = ? AND is_active = 1")
            ->execute(array($syncRoleKey));
        AuditLog::log('settings', 'sync_permissions', 0, "同步角色 {$syncRoleKey} 權限，影響 {$syncCount} 人");
        header('Content-Type: application/json');
        echo json_encode(array('success' => true, 'count' => $syncCount));
        break;

    case 'save_role_permissions':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf()) {
            Session::flash('error', '安全驗證失敗');
            redirect('/dropdown_options.php?tab=roles');
        }
        if (Auth::user()['role'] !== 'boss') {
            Session::flash('error', '權限不足');
            redirect('/dropdown_options.php?tab=roles');
        }
        $roleId = isset($_GET['role_id']) ? (int)$_GET['role_id'] : 0;
        $role = $model->getRoleById($roleId);
        if (!$role) {
            Session::flash('error', '角色不存在');
            redirect('/dropdown_options.php?tab=roles');
        }

        // 解析表單 — 模組權限
        $permsData = array();
        if (!empty($_POST['all_permissions'])) {
            $permsData['_all'] = true;
        } else {
            foreach ($_POST as $key => $val) {
                if (strpos($key, 'perm_') === 0 && $val !== 'off') {
                    $modKey = substr($key, 5);
                    $permsData[$modKey] = $val; // e.g. "cases.manage"
                }
                if (strpos($key, 'delete_') === 0) {
                    $permsData[$key] = true; // e.g. "delete_cases" => true
                }
            }
        }

        // 案件區域
        $sections = array();
        $sectionLabels = isset($appConfig['case_section_labels']) ? $appConfig['case_section_labels'] : array();
        foreach ($sectionLabels as $sk => $slabel) {
            if (!empty($_POST['section_' . $sk])) {
                $sections[] = $sk;
            }
        }

        // 報表存取
        $reports = array();
        $reportLabels = isset($appConfig['report_labels']) ? $appConfig['report_labels'] : array();
        foreach ($reportLabels as $rk => $rlabel) {
            if (!empty($_POST['report_' . $rk])) {
                $reports[] = $rk;
            }
        }

        // 儲存到 system_roles
        $db = Database::getInstance();
        $stmt = $db->prepare("UPDATE system_roles SET default_permissions = ?, default_case_sections = ?, default_reports = ? WHERE id = ?");
        $stmt->execute(array(
            json_encode($permsData, JSON_UNESCAPED_UNICODE),
            json_encode($sections, JSON_UNESCAPED_UNICODE),
            json_encode($reports, JSON_UNESCAPED_UNICODE),
            $roleId
        ));

        Session::flash('success', '角色「' . $role['role_label'] . '」的預設權限已儲存');
        redirect('/dropdown_options.php?action=role_permissions&role_id=' . $roleId);
        break;

    // ============ 報價單設定 ============
    case 'save_quotation_settings':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf()) {
            Session::flash('error', '安全驗證失敗');
            redirect('/dropdown_options.php?tab=quotation');
        }
        // 公司前綴：理創用 lc_，禾順用空字串
        $prefix = isset($_POST['quote_company_prefix']) ? $_POST['quote_company_prefix'] : '';
        $company = ($prefix === 'lc_') ? 'lichuang' : 'hershun';
        $branchId = isset($_POST['quote_branch_id']) ? (int)$_POST['quote_branch_id'] : 0;

        $saveData = array();

        if ($branchId > 0) {
            // 分公司模式：4 個分公司專屬文字欄位 + 報價章/QR 圖片，key 加 _b{id} 後綴
            $branchKeys = array(
                'quote_company_title',
                'quote_contact_address',
                'quote_contact_phone',
                'quote_contact_fax',
            );
            foreach ($branchKeys as $k) {
                if (isset($_POST[$k])) {
                    $saveData[$prefix . $k . '_b' . $branchId] = $_POST[$k];
                }
            }

            // 分公司專屬報價章 / QR
            $uploadDir = __DIR__ . '/uploads/settings/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

            if (!empty($_POST['remove_stamp'])) {
                $saveData[$prefix . 'quote_stamp_image_b' . $branchId] = '';
            }
            if (!empty($_FILES['stamp_image']['tmp_name'])) {
                $ext = pathinfo($_FILES['stamp_image']['name'], PATHINFO_EXTENSION);
                $fname = $prefix . 'stamp_b' . $branchId . '_' . time() . '.' . $ext;
                move_uploaded_file($_FILES['stamp_image']['tmp_name'], $uploadDir . $fname);
                $saveData[$prefix . 'quote_stamp_image_b' . $branchId] = 'uploads/settings/' . $fname;
            }
            if (!empty($_POST['remove_qrcode'])) {
                $saveData[$prefix . 'quote_qrcode_image_b' . $branchId] = '';
            }
            if (!empty($_FILES['qrcode_image']['tmp_name'])) {
                $ext = pathinfo($_FILES['qrcode_image']['name'], PATHINFO_EXTENSION);
                $fname = $prefix . 'qrcode_b' . $branchId . '_' . time() . '.' . $ext;
                move_uploaded_file($_FILES['qrcode_image']['tmp_name'], $uploadDir . $fname);
                $saveData[$prefix . 'quote_qrcode_image_b' . $branchId] = 'uploads/settings/' . $fname;
            }
        } else {
            // 共用模式：存所有非分公司專屬欄位（排除 4 個分公司專屬欄位）
            $sharedKeys = array(
                'quote_company_subtitle',
                'quote_bank_name', 'quote_bank_branch', 'quote_bank_account',
                'quote_service_phone',
                'quote_bank_reminder', 'quote_deposit_notice',
                'quote_warranty_months',
                'quote_warranty_text_1', 'quote_warranty_text_2', 'quote_warranty_text_3',
            );
            foreach ($sharedKeys as $k) {
                if (isset($_POST[$k])) {
                    $saveData[$prefix . $k] = $_POST[$k];
                }
            }

            // 圖章/QR 只在共用模式處理
            $uploadDir = __DIR__ . '/uploads/settings/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            if (!empty($_POST['remove_stamp'])) {
                $saveData[$prefix . 'quote_stamp_image'] = '';
            }
            if (!empty($_FILES['stamp_image']['tmp_name'])) {
                $ext = pathinfo($_FILES['stamp_image']['name'], PATHINFO_EXTENSION);
                $fname = $prefix . 'stamp_' . time() . '.' . $ext;
                move_uploaded_file($_FILES['stamp_image']['tmp_name'], $uploadDir . $fname);
                $saveData[$prefix . 'quote_stamp_image'] = 'uploads/settings/' . $fname;
            }
            if (!empty($_POST['remove_qrcode'])) {
                $saveData[$prefix . 'quote_qrcode_image'] = '';
            }
            if (!empty($_FILES['qrcode_image']['tmp_name'])) {
                $ext = pathinfo($_FILES['qrcode_image']['name'], PATHINFO_EXTENSION);
                $fname = $prefix . 'qrcode_' . time() . '.' . $ext;
                move_uploaded_file($_FILES['qrcode_image']['tmp_name'], $uploadDir . $fname);
                $saveData[$prefix . 'quote_qrcode_image'] = 'uploads/settings/' . $fname;
            }
        }

        $model->saveSettings($saveData, 'quotation');
        Session::flash('success', '報價單設定已儲存');
        $redirUrl = '/dropdown_options.php?tab=quotation&company=' . $company;
        if ($branchId > 0) $redirUrl .= '&branch=' . $branchId;
        redirect($redirUrl);
        break;

    case 'save_numbering':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf()) {
            Session::flash('error', '安全驗證失敗');
            redirect('/dropdown_options.php?tab=numbering');
        }
        $ids = isset($_POST['seq_id']) ? $_POST['seq_id'] : array();
        $prefixes = isset($_POST['seq_prefix']) ? $_POST['seq_prefix'] : array();
        $dateFormats = isset($_POST['seq_date_format']) ? $_POST['seq_date_format'] : array();
        $separators = isset($_POST['seq_separator']) ? $_POST['seq_separator'] : array();
        $digits = isset($_POST['seq_digits']) ? $_POST['seq_digits'] : array();

        foreach ($ids as $i => $id) {
            $model->updateNumberSequence((int)$id, array(
                'prefix'      => isset($prefixes[$i]) ? $prefixes[$i] : '',
                'date_format' => isset($dateFormats[$i]) ? $dateFormats[$i] : '',
                'separator'   => isset($separators[$i]) ? $separators[$i] : '-',
                'seq_digits'  => isset($digits[$i]) ? (int)$digits[$i] : 3,
            ));
        }
        Session::flash('success', '單號設定已儲存');
        redirect('/dropdown_options.php?tab=numbering');
        break;

    default:
        redirect('/dropdown_options.php');
}
