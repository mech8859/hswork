<?php
/**
 * 通用編輯鎖定（防止多人同時編輯）
 */
class EditingLock
{
    public static function set($module, $recordId, $userId, $userName)
    {
        $db = Database::getInstance();
        $db->prepare("INSERT INTO editing_locks (module, record_id, user_id, user_name, locked_at, heartbeat_at) VALUES (?, ?, ?, ?, NOW(), NOW()) ON DUPLICATE KEY UPDATE user_name = VALUES(user_name), heartbeat_at = NOW()")
            ->execute(array($module, $recordId, $userId, $userName));
    }

    public static function getOthers($module, $recordId, $excludeUserId, $timeoutMinutes = 2)
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT user_id, user_name, heartbeat_at FROM editing_locks WHERE module = ? AND record_id = ? AND user_id != ? AND heartbeat_at >= DATE_SUB(NOW(), INTERVAL ? MINUTE)");
        $stmt->execute(array($module, $recordId, $excludeUserId, $timeoutMinutes));
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function heartbeat($module, $recordId, $userId)
    {
        $db = Database::getInstance();
        $db->prepare("UPDATE editing_locks SET heartbeat_at = NOW() WHERE module = ? AND record_id = ? AND user_id = ?")
            ->execute(array($module, $recordId, $userId));
    }

    public static function release($module, $recordId, $userId)
    {
        $db = Database::getInstance();
        $db->prepare("DELETE FROM editing_locks WHERE module = ? AND record_id = ? AND user_id = ?")
            ->execute(array($module, $recordId, $userId));
    }
}
