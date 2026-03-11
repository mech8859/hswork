<?php
/**
 * 認證與權限管理
 */
class Auth
{
    /**
     * 登入驗證
     */
    public static function attempt(string $username, string $password): bool
    {
        $db = Database::getInstance();
        $stmt = $db->prepare('
            SELECT u.*, b.name AS branch_name, b.code AS branch_code
            FROM users u
            JOIN branches b ON u.branch_id = b.id
            WHERE u.username = ? AND u.is_active = 1
            LIMIT 1
        ');
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            return false;
        }

        // 更新最後登入時間
        $db->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?')
           ->execute([$user['id']]);

        // 載入權限
        $appConfig = require __DIR__ . '/../config/app.php';
        $permissions = $appConfig['permissions'][$user['role']] ?? [];

        // 儲存到 Session
        unset($user['password_hash']);
        Session::set('user_id', (int)$user['id']);
        Session::set('user', $user);
        Session::set('permissions', $permissions);

        // 重新產生 session ID 防止 session fixation
        session_regenerate_id(true);

        return true;
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
    public static function hasPermission(string $permission): bool
    {
        $permissions = Session::get('permissions', []);
        return in_array('all', $permissions) || in_array($permission, $permissions);
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

        if ($user['can_view_all_branches'] || $user['role'] === 'boss') {
            $db = Database::getInstance();
            $stmt = $db->query('SELECT id FROM branches WHERE is_active = 1');
            return array_column($stmt->fetchAll(), 'id');
        }

        return [(int)$user['branch_id']];
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
