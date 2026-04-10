<?php
/**
 * 通知系統 Model
 */
class NotificationModel
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * 發送通知給指定使用者
     */
    public function send($userId, $type, $title, $message = '', $link = '', $relatedType = null, $relatedId = null, $createdBy = null)
    {
        $stmt = $this->db->prepare("
            INSERT INTO notifications (user_id, type, title, message, link, related_type, related_id, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute(array(
            $userId, $type, $title, $message, $link, $relatedType, $relatedId, $createdBy
        ));
        return $this->db->lastInsertId();
    }

    /**
     * 發送通知給指定角色（同分公司）
     */
    public function sendToRole($role, $branchId, $type, $title, $message = '', $link = '', $relatedType = null, $relatedId = null, $createdBy = null)
    {
        $sql = "SELECT id FROM users WHERE role = ? AND is_active = 1";
        $params = array($role);
        // boss / accountant 不受分公司限制（管理者看全部、會計屬管理處但管所有分公司帳）
        if ($branchId && !in_array($role, array('boss', 'accountant'))) {
            $sql .= " AND branch_id = ?";
            $params[] = $branchId;
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $users = $stmt->fetchAll();

        $ids = array();
        foreach ($users as $u) {
            $ids[] = $this->send($u['id'], $type, $title, $message, $link, $relatedType, $relatedId, $createdBy);
        }
        return $ids;
    }

    /**
     * 發送通知給多個角色
     */
    public function sendToRoles($roles, $branchId, $type, $title, $message = '', $link = '', $relatedType = null, $relatedId = null, $createdBy = null)
    {
        $ids = array();
        foreach ($roles as $role) {
            $result = $this->sendToRole($role, $branchId, $type, $title, $message, $link, $relatedType, $relatedId, $createdBy);
            $ids = array_merge($ids, $result);
        }
        return $ids;
    }

    /**
     * 取得使用者未讀通知
     */
    public function getUnread($userId, $limit = 20)
    {
        $stmt = $this->db->prepare("
            SELECT n.*, u.real_name AS sender_name
            FROM notifications n
            LEFT JOIN users u ON n.created_by = u.id
            WHERE n.user_id = ? AND n.is_read = 0
            ORDER BY n.created_at DESC
            LIMIT ?
        ");
        $stmt->bindValue(1, $userId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * 取得使用者所有通知（含已讀）
     */
    public function getAll($userId, $limit = 50)
    {
        $stmt = $this->db->prepare("
            SELECT n.*, u.real_name AS sender_name
            FROM notifications n
            LEFT JOIN users u ON n.created_by = u.id
            WHERE n.user_id = ?
            ORDER BY n.created_at DESC
            LIMIT ?
        ");
        $stmt->bindValue(1, $userId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * 取得未讀數量
     */
    public function getUnreadCount($userId)
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt->execute(array($userId));
        return (int)$stmt->fetchColumn();
    }

    /**
     * 標記已讀
     */
    public function markRead($id, $userId)
    {
        $stmt = $this->db->prepare("UPDATE notifications SET is_read = 1, read_at = NOW() WHERE id = ? AND user_id = ?");
        $stmt->execute(array($id, $userId));
        return $stmt->rowCount();
    }

    /**
     * 全部已讀
     */
    public function markAllRead($userId)
    {
        $stmt = $this->db->prepare("UPDATE notifications SET is_read = 1, read_at = NOW() WHERE user_id = ? AND is_read = 0");
        $stmt->execute(array($userId));
        return $stmt->rowCount();
    }

    /**
     * 刪除舊通知（超過 30 天已讀的）
     */
    public function cleanup($days = 30)
    {
        $stmt = $this->db->prepare("DELETE FROM notifications WHERE is_read = 1 AND created_at < DATE_SUB(NOW(), INTERVAL ? DAY)");
        $stmt->execute(array($days));
        return $stmt->rowCount();
    }
}
