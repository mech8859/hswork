<?php
/**
 * 操作日誌記錄器
 */
class AuditLog
{
    /**
     * 記錄操作
     */
    public static function log($module, $action, $targetId = null, $targetTitle = null, $changes = null)
    {
        try {
            $db = Database::getInstance();
            $user = Session::getUser();
            $userId = $user ? (int)$user['id'] : 0;
            $userName = $user ? $user['real_name'] : 'system';
            $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';

            $stmt = $db->prepare("INSERT INTO audit_logs (user_id, user_name, module, action, target_id, target_title, changes, ip_address) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute(array(
                $userId,
                $userName,
                $module,
                $action,
                $targetId,
                $targetTitle,
                $changes ? (is_string($changes) ? $changes : json_encode($changes, JSON_UNESCAPED_UNICODE)) : null,
                $ip
            ));
        } catch (Exception $e) {
            // 日誌記錄失敗不應影響主流程
        }
    }

    /**
     * 記錄資料變更（自動比對差異）
     */
    public static function logChange($module, $targetId, $targetTitle, $oldData, $newData, $fields = null)
    {
        $changes = array();
        $compareFields = $fields ?: array_keys($newData);

        foreach ($compareFields as $field) {
            $oldVal = isset($oldData[$field]) ? $oldData[$field] : null;
            $newVal = isset($newData[$field]) ? $newData[$field] : null;
            if ((string)$oldVal !== (string)$newVal) {
                $changes[$field] = array('from' => $oldVal, 'to' => $newVal);
            }
        }

        if (!empty($changes)) {
            self::log($module, 'update', $targetId, $targetTitle, $changes);
        }
    }

    /**
     * 更新用戶最後活動
     */
    public static function updateActivity()
    {
        try {
            $user = Session::getUser();
            if (!$user) return;
            
            $db = Database::getInstance();
            $page = isset($_SERVER['REQUEST_URI']) ? strtok($_SERVER['REQUEST_URI'], '?') : '';
            $db->prepare("UPDATE users SET last_active_at = NOW(), last_active_page = ? WHERE id = ?")
               ->execute(array($page, $user['id']));
        } catch (Exception $e) {}
    }

    /**
     * 取得操作日誌
     */
    public static function getLogs($filters = array(), $page = 1, $perPage = 50)
    {
        $db = Database::getInstance();
        $where = '1=1';
        $params = array();

        if (!empty($filters['user_id'])) {
            $where .= ' AND user_id = ?';
            $params[] = (int)$filters['user_id'];
        }
        if (!empty($filters['module'])) {
            $where .= ' AND module = ?';
            $params[] = $filters['module'];
        }
        if (!empty($filters['filter_action'])) {
            $where .= ' AND action = ?';
            $params[] = $filters['filter_action'];
        }
        if (!empty($filters['date_from'])) {
            $where .= ' AND created_at >= ?';
            $params[] = $filters['date_from'] . ' 00:00:00';
        }
        if (!empty($filters['date_to'])) {
            $where .= ' AND created_at <= ?';
            $params[] = $filters['date_to'] . ' 23:59:59';
        }
        if (!empty($filters['keyword'])) {
            $where .= ' AND (target_title LIKE ? OR user_name LIKE ? OR changes LIKE ?)';
            $kw = '%' . $filters['keyword'] . '%';
            $params[] = $kw;
            $params[] = $kw;
            $params[] = $kw;
        }

        $offset = ($page - 1) * $perPage;
        $countStmt = $db->prepare("SELECT COUNT(*) FROM audit_logs WHERE $where");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $stmt = $db->prepare("SELECT * FROM audit_logs WHERE $where ORDER BY created_at DESC LIMIT $perPage OFFSET $offset");
        $stmt->execute($params);

        return array('data' => $stmt->fetchAll(), 'total' => $total, 'page' => $page, 'perPage' => $perPage);
    }

    /**
     * 取得線上用戶（最近5分鐘有活動）
     */
    public static function getOnlineUsers()
    {
        $db = Database::getInstance();
        return $db->query("SELECT id, real_name, role, branch_id, last_active_at, last_active_page FROM users WHERE last_active_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE) ORDER BY last_active_at DESC")->fetchAll();
    }
}
