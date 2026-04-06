<?php
/**
 * 認證與權限管理
 */
class Auth
{
    /** 連續失敗幾次鎖定 */
    const MAX_LOGIN_ATTEMPTS = 5;

    /** 鎖定時間（分鐘） */
    const LOCKOUT_MINUTES = 15;

    /**
     * 登入驗證（含鎖定檢查）
     * @return string|true  true=成功, string=錯誤訊息
     */
    public static function attempt(string $username, string $password)
    {
        $db = Database::getInstance();

        // 查詢使用者（含鎖定欄位）
        $stmt = $db->prepare('
            SELECT u.*, b.name AS branch_name, b.code AS branch_code
            FROM users u
            JOIN branches b ON u.branch_id = b.id
            WHERE u.username = ?
            LIMIT 1
        ');
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        // 檢查帳號是否被鎖定
        if ($user && !empty($user['locked_until'])) {
            if (strtotime($user['locked_until']) > time()) {
                $remaining = ceil((strtotime($user['locked_until']) - time()) / 60);
                self::recordFailedAttempt($username);
                self::logLogin($username, isset($user['id']) ? $user['id'] : null, 'locked', '帳號鎖定中');
                return "帳號已被鎖定，請於 {$remaining} 分鐘後再試";
            }
            // 鎖定已過期，清除鎖定
            $db->prepare('UPDATE users SET locked_until = NULL, failed_login_count = 0 WHERE id = ?')
               ->execute([$user['id']]);
            $user['locked_until'] = null;
            $user['failed_login_count'] = 0;
        }

        // 帳號不存在或已停用
        if (!$user || !$user['is_active']) {
            self::recordFailedAttempt($username);
            self::logLogin($username, $user ? $user['id'] : null, 'failed', $user ? '帳號已停用' : '帳號不存在');
            return '帳號或密碼錯誤';
        }

        // 密碼驗證
        if (!password_verify($password, $user['password_hash'])) {
            self::recordFailedAttempt($username);
            $newCount = ($user['failed_login_count'] ?? 0) + 1;

            // 累計失敗次數
            if ($newCount >= self::MAX_LOGIN_ATTEMPTS) {
                // 鎖定帳號
                $lockUntil = date('Y-m-d H:i:s', time() + self::LOCKOUT_MINUTES * 60);
                $db->prepare('UPDATE users SET failed_login_count = ?, locked_until = ? WHERE id = ?')
                   ->execute([$newCount, $lockUntil, $user['id']]);
                self::logLogin($username, $user['id'], 'locked', '密碼錯誤達上限，帳號鎖定');
                return '登入失敗次數過多，帳號已鎖定 ' . self::LOCKOUT_MINUTES . ' 分鐘';
            } else {
                $db->prepare('UPDATE users SET failed_login_count = ? WHERE id = ?')
                   ->execute([$newCount, $user['id']]);
                self::logLogin($username, $user['id'], 'failed', '密碼錯誤');
                $remaining = self::MAX_LOGIN_ATTEMPTS - $newCount;
                return "帳號或密碼錯誤（還剩 {$remaining} 次嘗試機會）";
            }
        }

        // 登入成功 - 清除失敗記錄
        self::logLogin($username, $user['id'], 'success');
        $db->prepare('UPDATE users SET last_login_at = NOW(), failed_login_count = 0, locked_until = NULL WHERE id = ?')
           ->execute([$user['id']]);

        // 清除 login_attempts 記錄
        $db->prepare('DELETE FROM login_attempts WHERE username = ?')
           ->execute([$username]);

        // 載入權限（優先從 system_roles DB，fallback 到 config）
        $appConfig = require __DIR__ . '/../config/app.php';
        $dbRolePerms = null;
        $dbRoleSections = null;
        $dbRoleReports = null;
        try {
            $roleStmt = $db->prepare("SELECT default_permissions, default_case_sections, default_reports FROM system_roles WHERE role_key = ? AND is_active = 1 LIMIT 1");
            $roleStmt->execute(array($user['role']));
            $roleRow = $roleStmt->fetch(PDO::FETCH_ASSOC);
            if ($roleRow && !empty($roleRow['default_permissions'])) {
                $decoded = json_decode($roleRow['default_permissions'], true);
                if (is_array($decoded)) {
                    $dbRolePerms = array();
                    if (!empty($decoded['_all'])) {
                        $dbRolePerms[] = 'all';
                    }
                    foreach ($decoded as $k => $v) {
                        if ($k === '_all') continue;
                        if (strpos($k, 'delete_') === 0) {
                            // delete_cases => cases.delete
                            $mod = substr($k, 7);
                            if ($v) $dbRolePerms[] = $mod . '.delete';
                        } else {
                            // cases => "cases.manage"
                            if (is_string($v)) $dbRolePerms[] = $v;
                        }
                    }
                }
            }
            if ($roleRow && !empty($roleRow['default_case_sections'])) {
                $dec = json_decode($roleRow['default_case_sections'], true);
                if (is_array($dec)) $dbRoleSections = $dec;
            }
            if ($roleRow && !empty($roleRow['default_reports'])) {
                $dec = json_decode($roleRow['default_reports'], true);
                if (is_array($dec)) $dbRoleReports = $dec;
            }
        } catch (Exception $e) {
            // table might not exist yet, fallback to config
        }
        $permissions = $dbRolePerms !== null ? $dbRolePerms : (isset($appConfig['permissions'][$user['role']]) ? $appConfig['permissions'][$user['role']] : array());

        // 個人權限覆蓋（boss/vice_president 不受限）
        if (!in_array($user['role'], array('boss', 'vice_president')) && !empty($user['custom_permissions'])) {
            $custom = json_decode($user['custom_permissions'], true);
            if (is_array($custom)) {
                $modulePermMap = array(
                    'cases' => array('cases.manage', 'cases.view', 'cases.own', 'cases.assist', 'cases.delete'),
                    'schedule' => array('schedule.manage', 'schedule.view', 'schedule.delete'),
                    'repairs' => array('repairs.manage', 'repairs.view', 'repairs.own', 'repairs.delete'),
                    'staff' => array('staff.manage', 'staff.view'),
                    'staff_skills' => array('staff_skills.manage', 'staff_skills.view'),
                    'leaves' => array('leaves.manage', 'leaves.view', 'leaves.own', 'leaves.delete'),
                    'inter_branch' => array('inter_branch.manage', 'inter_branch.view', 'inter_branch.delete'),
                    'reports' => array('reports.view'),
                    'products' => array('products.view', 'products.manage', 'products.delete'),
                    'vehicles' => array('vehicles.view', 'vehicles.manage'),
                    'worklog' => array('worklog.manage', 'worklog.view'),
                    'attendance' => array('attendance.view'),
                    'quotations' => array('quotations.manage', 'quotations.view', 'quotations.own', 'quotations.delete'),
                    'customers' => array('customers.manage', 'customers.view', 'customers.own', 'customers.delete'),
                    'business_calendar' => array('business_calendar.manage', 'business_calendar.view'),
                    'business_tracking' => array('business_tracking.manage', 'business_tracking.view', 'business_tracking.own'),
                    'settings' => array('settings.manage'),
                    'inventory' => array('inventory.manage', 'inventory.view', 'inventory.delete'),
                    'finance' => array('finance.manage', 'finance.view', 'finance.delete'),
                    'engineering_tracking' => array('engineering_tracking.manage', 'engineering_tracking.view', 'engineering_tracking.own'),
                    'procurement' => array('procurement.manage', 'procurement.view'),
                    'accounting' => array('accounting.manage', 'accounting.view'),
                    'approvals' => array('approvals.manage', 'approvals.view'),
                    'system' => array('system.manage'),
                );
                // 覆蓋模組權限
                foreach ($custom as $module => $value) {
                    // 跳過 delete_* 鍵（後面單獨處理）
                    if (strpos($module, 'delete_') === 0) continue;
                    if (!isset($modulePermMap[$module])) continue;
                    $allPermsForMod = $modulePermMap[$module];
                    // 移除時排除 delete 權限（delete 獨立處理）
                    $nonDeletePerms = array();
                    foreach ($allPermsForMod as $p) {
                        if (substr($p, -7) !== '.delete') {
                            $nonDeletePerms[] = $p;
                        }
                    }
                    // 先移除該模組非 delete 權限
                    $permissions = array_values(array_diff($permissions, $nonDeletePerms));
                    // 如果有指定權限（非 false/off），加入該權限
                    if ($value !== false && $value !== 'off' && is_string($value)) {
                        $permissions[] = $value;
                        // manage 自動包含 view
                        if (substr($value, -7) === '.manage') {
                            $viewPerm = substr($value, 0, -7) . '.view';
                            if (!in_array($viewPerm, $permissions)) {
                                $permissions[] = $viewPerm;
                            }
                        }
                    }
                    // 相容舊格式：true 表示不覆蓋（保留角色預設）
                    if ($value === true) {
                        $baseRolePerms = $dbRolePerms !== null ? $dbRolePerms : (isset($appConfig['permissions'][$user['role']]) ? $appConfig['permissions'][$user['role']] : array());
                        $rolePermsForMod = array_intersect($nonDeletePerms, $baseRolePerms);
                        foreach ($rolePermsForMod as $rp) {
                            $permissions[] = $rp;
                        }
                    }
                }
                // 處理獨立的刪除權限（delete_cases, delete_schedule 等）
                $deleteModules = array('cases', 'schedule', 'repairs', 'quotations', 'customers', 'leaves', 'inter_branch', 'products', 'inventory', 'finance');
                foreach ($deleteModules as $dm) {
                    $deleteKey = 'delete_' . $dm;
                    if (array_key_exists($deleteKey, $custom)) {
                        // 先移除該模組的 delete 權限
                        $permissions = array_values(array_diff($permissions, array($dm . '.delete')));
                        // 如果勾選了，加入 delete 權限
                        if ($custom[$deleteKey]) {
                            $permissions[] = $dm . '.delete';
                        }
                    }
                }
                // 存到 session 供側邊選單用
                Session::set('custom_permissions', $custom);
            }
        }

        // 案件編輯區域權限
        $sectionDefaults = $dbRoleSections !== null
            ? $dbRoleSections
            : (isset($appConfig['case_section_defaults'][$user['role']]) ? $appConfig['case_section_defaults'][$user['role']] : array('basic'));
        // boss/manager 給全部
        $isAllPerm = in_array('all', $permissions);
        if ($isAllPerm) {
            $sectionDefaults = array('basic','finance','schedule','attach','site','contacts','skills','delete');
        }
        // 個別覆蓋（boss/manager 不覆蓋，永遠全開）
        if (!$isAllPerm && !empty($user['custom_permissions'])) {
            $custom = is_array($custom) ? $custom : json_decode($user['custom_permissions'], true);
            if (is_array($custom) && isset($custom['case_sections']) && is_array($custom['case_sections'])) {
                $sectionDefaults = $custom['case_sections'];
            }
        }

        // 報表權限
        $reportDefaults = $dbRoleReports !== null
            ? $dbRoleReports
            : (isset($appConfig['report_defaults'][$user['role']]) ? $appConfig['report_defaults'][$user['role']] : array());
        if ($isAllPerm) {
            $reportDefaults = array_keys($appConfig['report_labels']);
        }
        if (!$isAllPerm && !empty($user['custom_permissions'])) {
            $custom = is_array($custom) ? $custom : json_decode($user['custom_permissions'], true);
            if (is_array($custom) && isset($custom['report_access']) && is_array($custom['report_access'])) {
                $reportDefaults = $custom['report_access'];
            }
        }

        // 儲存到 Session
        unset($user['password_hash'], $user['locked_until'], $user['failed_login_count']);
        Session::set('user_id', (int)$user['id']);
        Session::set('user', $user);
        Session::set('permissions', $permissions);
        Session::set('case_sections', $sectionDefaults);
        Session::set('report_access', $reportDefaults);

        // 重新產生 session ID 防止 session fixation
        session_regenerate_id(true);

        return true;
    }

    /**
     * 記錄登入日誌
     */
    public static function logLogin($username, $userId, $status, $failReason = null)
    {
        $db = Database::getInstance();
        $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'unknown';
        $ua = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';

        // 解析裝置資訊
        $deviceType = 'desktop';
        $browser = '';
        $os = '';
        if ($ua) {
            // 裝置類型
            if (preg_match('/Mobile|Android|iPhone|iPad/i', $ua)) {
                $deviceType = preg_match('/iPad|Tablet/i', $ua) ? 'tablet' : 'mobile';
            }
            // 瀏覽器
            if (preg_match('/Chrome\/[\d.]+/i', $ua)) $browser = 'Chrome';
            elseif (preg_match('/Firefox\/[\d.]+/i', $ua)) $browser = 'Firefox';
            elseif (preg_match('/Safari\/[\d.]+/i', $ua) && !preg_match('/Chrome/i', $ua)) $browser = 'Safari';
            elseif (preg_match('/Edge\/[\d.]+/i', $ua)) $browser = 'Edge';
            elseif (preg_match('/MSIE|Trident/i', $ua)) $browser = 'IE';
            else $browser = 'Other';
            // 作業系統
            if (preg_match('/Windows/i', $ua)) $os = 'Windows';
            elseif (preg_match('/Mac OS X/i', $ua)) $os = 'macOS';
            elseif (preg_match('/Linux/i', $ua) && !preg_match('/Android/i', $ua)) $os = 'Linux';
            elseif (preg_match('/Android/i', $ua)) $os = 'Android';
            elseif (preg_match('/iPhone|iPad/i', $ua)) $os = 'iOS';
            else $os = 'Other';
        }

        try {
            $db->prepare("
                INSERT INTO login_logs (user_id, username, ip_address, user_agent, device_type, browser, os, status, fail_reason)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ")->execute(array(
                $userId, $username, $ip, mb_substr($ua, 0, 500),
                $deviceType, $browser, $os, $status, $failReason
            ));
        } catch (PDOException $e) {
            // 表不存在時忽略
        }
    }

    /**
     * 記錄登入失敗
     */
    private static function recordFailedAttempt(string $username): void
    {
        $db = Database::getInstance();
        $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'unknown';
        $db->prepare('INSERT INTO login_attempts (username, ip_address) VALUES (?, ?)')
           ->execute([$username, $ip]);
    }

    /**
     * 登出
     */
    public static function logout(): void
    {
        Session::destroy();
    }

    /**
     * 確認已登入，否則導向登入頁
     */
    public static function requireLogin(): void
    {
        if (!Session::isLoggedIn()) {
            header('Location: /login.php');
            exit;
        }
    }

    /**
     * 確認角色權限
     */
    public static function requireRole(string ...$roles): void
    {
        self::requireLogin();
        $user = Session::getUser();
        if (!in_array($user['role'], $roles)) {
            http_response_code(403);
            require __DIR__ . '/../templates/layouts/403.php';
            exit;
        }
    }

    /**
     * 檢查是否有特定權限
     */
    /**
     * 重新載入當前用戶的權限（不需重新登入）
     */
    public static function reloadPermissions()
    {
        $user = Session::getUser();
        if (!$user || empty($user['role'])) return;

        $db = Database::getInstance();
        $appConfig = require __DIR__ . '/../config/app.php';

        // 重新讀取用戶的 custom_permissions（可能已被管理員修改）
        $stmt = $db->prepare("SELECT custom_permissions FROM users WHERE id = ?");
        $stmt->execute(array($user['id']));
        $freshUser = $stmt->fetch(PDO::FETCH_ASSOC);
        $customPermJson = $freshUser ? $freshUser['custom_permissions'] : null;

        // 載入角色預設權限
        $dbRolePerms = null;
        $dbRoleSections = null;
        $dbRoleReports = null;
        try {
            $roleStmt = $db->prepare("SELECT default_permissions, default_case_sections, default_reports FROM system_roles WHERE role_key = ? AND is_active = 1 LIMIT 1");
            $roleStmt->execute(array($user['role']));
            $roleRow = $roleStmt->fetch(PDO::FETCH_ASSOC);
            if ($roleRow && !empty($roleRow['default_permissions'])) {
                $decoded = json_decode($roleRow['default_permissions'], true);
                if (is_array($decoded)) {
                    $dbRolePerms = array();
                    if (!empty($decoded['_all'])) {
                        $dbRolePerms[] = 'all';
                    }
                    foreach ($decoded as $k => $v) {
                        if ($k === '_all') continue;
                        if (strpos($k, 'delete_') === 0) {
                            $mod = substr($k, 7);
                            if ($v) $dbRolePerms[] = $mod . '.delete';
                        } else {
                            if (is_string($v)) $dbRolePerms[] = $v;
                        }
                    }
                }
            }
            if ($roleRow && !empty($roleRow['default_case_sections'])) {
                $dec = json_decode($roleRow['default_case_sections'], true);
                if (is_array($dec)) $dbRoleSections = $dec;
            }
            if ($roleRow && !empty($roleRow['default_reports'])) {
                $dec = json_decode($roleRow['default_reports'], true);
                if (is_array($dec)) $dbRoleReports = $dec;
            }
        } catch (Exception $e) {}

        $permissions = $dbRolePerms !== null ? $dbRolePerms : (isset($appConfig['permissions'][$user['role']]) ? $appConfig['permissions'][$user['role']] : array());

        // 個人權限覆蓋
        $custom = null;
        if (!in_array($user['role'], array('boss', 'vice_president')) && !empty($customPermJson)) {
            $custom = json_decode($customPermJson, true);
            if (is_array($custom)) {
                $modulePermMap = array(
                    'cases' => array('cases.manage', 'cases.view', 'cases.own', 'cases.assist', 'cases.delete'),
                    'schedule' => array('schedule.manage', 'schedule.view', 'schedule.delete'),
                    'repairs' => array('repairs.manage', 'repairs.view', 'repairs.own', 'repairs.delete'),
                    'staff' => array('staff.manage', 'staff.view'),
                    'staff_skills' => array('staff_skills.manage', 'staff_skills.view'),
                    'leaves' => array('leaves.manage', 'leaves.view', 'leaves.own', 'leaves.delete'),
                    'inter_branch' => array('inter_branch.manage', 'inter_branch.view', 'inter_branch.delete'),
                    'reports' => array('reports.view'),
                    'products' => array('products.view', 'products.manage', 'products.delete'),
                    'vehicles' => array('vehicles.view', 'vehicles.manage'),
                    'worklog' => array('worklog.manage', 'worklog.view'),
                    'attendance' => array('attendance.view'),
                    'quotations' => array('quotations.manage', 'quotations.view', 'quotations.own', 'quotations.delete'),
                    'customers' => array('customers.manage', 'customers.view', 'customers.own', 'customers.delete'),
                    'business_calendar' => array('business_calendar.manage', 'business_calendar.view'),
                    'business_tracking' => array('business_tracking.manage', 'business_tracking.view', 'business_tracking.own'),
                    'settings' => array('settings.manage'),
                    'inventory' => array('inventory.manage', 'inventory.view', 'inventory.delete'),
                    'finance' => array('finance.manage', 'finance.view', 'finance.delete'),
                    'engineering_tracking' => array('engineering_tracking.manage', 'engineering_tracking.view', 'engineering_tracking.own'),
                    'procurement' => array('procurement.manage', 'procurement.view'),
                    'accounting' => array('accounting.manage', 'accounting.view'),
                    'approvals' => array('approvals.manage', 'approvals.view'),
                    'system' => array('system.manage'),
                );
                foreach ($custom as $module => $value) {
                    if (strpos($module, 'delete_') === 0) continue;
                    if (!isset($modulePermMap[$module])) continue;
                    $allPermsForMod = $modulePermMap[$module];
                    $nonDeletePerms = array();
                    foreach ($allPermsForMod as $p) {
                        if (substr($p, -7) !== '.delete') $nonDeletePerms[] = $p;
                    }
                    $permissions = array_values(array_diff($permissions, $nonDeletePerms));
                    if ($value !== false && $value !== 'off' && is_string($value)) {
                        $permissions[] = $value;
                        if (substr($value, -7) === '.manage') {
                            $viewPerm = substr($value, 0, -7) . '.view';
                            if (!in_array($viewPerm, $permissions)) $permissions[] = $viewPerm;
                        }
                    }
                    if ($value === true) {
                        $baseRolePerms = $dbRolePerms !== null ? $dbRolePerms : (isset($appConfig['permissions'][$user['role']]) ? $appConfig['permissions'][$user['role']] : array());
                        $rolePermsForMod = array_intersect($nonDeletePerms, $baseRolePerms);
                        foreach ($rolePermsForMod as $rp) $permissions[] = $rp;
                    }
                }
                $deleteModules = array('cases', 'schedule', 'repairs', 'quotations', 'customers', 'leaves', 'inter_branch', 'products', 'inventory', 'finance');
                foreach ($deleteModules as $dm) {
                    $deleteKey = 'delete_' . $dm;
                    if (array_key_exists($deleteKey, $custom)) {
                        $permissions = array_values(array_diff($permissions, array($dm . '.delete')));
                        if ($custom[$deleteKey]) $permissions[] = $dm . '.delete';
                    }
                }
                Session::set('custom_permissions', $custom);
            }
        }

        // 案件編輯區域
        $sectionDefaults = $dbRoleSections !== null
            ? $dbRoleSections
            : (isset($appConfig['case_section_defaults'][$user['role']]) ? $appConfig['case_section_defaults'][$user['role']] : array('basic'));
        $isAllPerm = in_array('all', $permissions);
        if ($isAllPerm) {
            $sectionDefaults = array('basic','finance','schedule','attach','site','contacts','skills','delete');
        }
        if (!$isAllPerm && is_array($custom) && isset($custom['case_sections']) && is_array($custom['case_sections'])) {
            $sectionDefaults = $custom['case_sections'];
        }

        // 報表權限
        $reportDefaults = $dbRoleReports !== null
            ? $dbRoleReports
            : (isset($appConfig['report_defaults'][$user['role']]) ? $appConfig['report_defaults'][$user['role']] : array());
        if ($isAllPerm) {
            $reportDefaults = array_keys($appConfig['report_labels']);
        }
        if (!$isAllPerm && is_array($custom) && isset($custom['report_access']) && is_array($custom['report_access'])) {
            $reportDefaults = $custom['report_access'];
        }

        Session::set('permissions', $permissions);
        Session::set('case_sections', $sectionDefaults);
        Session::set('report_access', $reportDefaults);
    }

    public static function hasPermission(string $permission): bool
    {
        $permissions = Session::get('permissions', []);
        if (in_array('all', $permissions) || in_array($permission, $permissions)) {
            return true;
        }
        // .view 可被 .manage 滿足
        if (substr($permission, -5) === '.view') {
            $managePerm = substr($permission, 0, -5) . '.manage';
            return in_array($managePerm, $permissions);
        }
        return false;
    }

    /**
     * 檢查是否可編輯案件區域
     */
    public static function canEditSection($section)
    {
        // boss/manager 自動有所有區域權限
        $user = Session::getUser();
        if ($user && in_array($user['role'], array('boss', 'vice_president', 'manager'))) {
            return true;
        }
        $sections = Session::get('case_sections', array());
        return in_array($section, $sections);
    }

    /**
     * 取得所有可編輯區域
     */
    public static function editableSections()
    {
        // boss/manager 回傳所有區域
        $user = Session::getUser();
        if ($user && in_array($user['role'], array('boss', 'vice_president', 'manager'))) {
            $config = require __DIR__ . '/../config/app.php';
            return array_keys($config['case_section_labels'] ?? array());
        }
        return Session::get('case_sections', array());
    }

    /**
     * 檢查是否可存取報表
     */
    public static function canAccessReport($reportKey)
    {
        $reports = Session::get('report_access', array());
        return in_array($reportKey, $reports);
    }

    /**
     * 確認有特定權限
     */
    public static function requirePermission(string $permission): void
    {
        self::requireLogin();
        if (!self::hasPermission($permission)) {
            http_response_code(403);
            require __DIR__ . '/../templates/layouts/403.php';
            exit;
        }
    }

    /**
     * 取得目前使用者可查看的據點ID清單
     */
    public static function getAccessibleBranchIds(): array
    {
        $user = Session::getUser();
        if (!$user) return [];

        // boss/manager 或勾選全區 → 全部分公司
        if ($user['can_view_all_branches'] || in_array($user['role'], array('boss', 'vice_president', 'manager'))) {
            $db = Database::getInstance();
            $stmt = $db->query('SELECT id FROM branches WHERE is_active = 1');
            return array_column($stmt->fetchAll(), 'id');
        }

        // 有指定可查看分公司
        $viewable = array();
        if (!empty($user['viewable_branches'])) {
            $decoded = is_array($user['viewable_branches']) ? $user['viewable_branches'] : json_decode($user['viewable_branches'], true);
            if (is_array($decoded)) {
                $viewable = array_map('intval', $decoded);
            }
        }

        // 確保包含自己的分公司
        $ownBranch = (int)$user['branch_id'];
        if (!in_array($ownBranch, $viewable)) {
            $viewable[] = $ownBranch;
        }

        return $viewable;
    }

    /**
     * 目前使用者
     */
    public static function user(): ?array
    {
        return Session::getUser();
    }

    /**
     * 目前使用者ID
     */
    public static function id(): ?int
    {
        return Session::getUserId();
    }
}
