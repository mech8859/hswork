<?php
/**
 * 請假管理模型
 */
class LeaveModel
{
    /** @var PDO */
    private $db;

    private static $typeLabels = array(
        'annual'   => '特休',
        'personal' => '事假',
        'sick'     => '病假',
        'official' => '公假',
    );

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public static function leaveTypeLabel($type)
    {
        return isset(self::$typeLabels[$type]) ? self::$typeLabels[$type] : $type;
    }

    /**
     * 取得請假清單
     */
    public function getList(array $branchIds, array $filters = array(), $userId = null)
    {
        $where = 'u.branch_id IN (' . implode(',', array_fill(0, count($branchIds), '?')) . ')';
        $params = $branchIds;

        // 只看自己的
        if ($userId) {
            $where .= ' AND l.user_id = ?';
            $params[] = $userId;
        }
        if (!empty($filters['month'])) {
            $where .= ' AND l.start_date LIKE ?';
            $params[] = $filters['month'] . '%';
        }
        if (!empty($filters['user_id'])) {
            $where .= ' AND l.user_id = ?';
            $params[] = $filters['user_id'];
        }
        if (!empty($filters['status'])) {
            $where .= ' AND l.status = ?';
            $params[] = $filters['status'];
        }

        $stmt = $this->db->prepare("
            SELECT l.*, u.real_name, u.branch_id, b.name AS branch_name,
                   ap.real_name AS approved_by_name,
                   DATEDIFF(l.end_date, l.start_date) + 1 AS days
            FROM leaves l
            JOIN users u ON l.user_id = u.id
            JOIN branches b ON u.branch_id = b.id
            LEFT JOIN users ap ON l.approved_by = ap.id
            WHERE $where
            ORDER BY l.created_at DESC
        ");
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * 取得單一請假
     */
    public function getById($id)
    {
        $stmt = $this->db->prepare('
            SELECT l.*, u.real_name, b.name AS branch_name,
                   ap.real_name AS approved_by_name,
                   DATEDIFF(l.end_date, l.start_date) + 1 AS days
            FROM leaves l
            JOIN users u ON l.user_id = u.id
            JOIN branches b ON u.branch_id = b.id
            LEFT JOIN users ap ON l.approved_by = ap.id
            WHERE l.id = ?
        ');
        $stmt->execute(array($id));
        $row = $stmt->fetch();
        return $row ? $row : null;
    }

    /**
     * 新增請假
     */
    public function create(array $data)
    {
        $stmt = $this->db->prepare('
            INSERT INTO leaves (user_id, leave_type, start_date, end_date, reason)
            VALUES (?, ?, ?, ?, ?)
        ');
        $stmt->execute(array(
            $data['user_id'],
            $data['leave_type'],
            $data['start_date'],
            $data['end_date'],
            $data['reason'] ?: null,
        ));
        return (int)$this->db->lastInsertId();
    }

    /**
     * 核准
     */
    public function approve($id, $approvedBy)
    {
        $this->db->prepare("UPDATE leaves SET status = 'approved', approved_by = ?, approved_at = NOW() WHERE id = ?")
                 ->execute(array($approvedBy, $id));
    }

    /**
     * 駁回
     */
    public function reject($id, $approvedBy, $reason = '')
    {
        $this->db->prepare("UPDATE leaves SET status = 'rejected', approved_by = ?, approved_at = NOW(), reject_reason = ? WHERE id = ?")
                 ->execute(array($approvedBy, $reason, $id));
    }

    /**
     * 刪除（只能刪待審核的）
     */
    public function delete($id)
    {
        $this->db->prepare("DELETE FROM leaves WHERE id = ? AND status = 'pending'")->execute(array($id));
    }

    /**
     * 取得某日期請假的工程師ID（排工用）
     */
    public function getEngineersOnLeave($date, array $branchIds)
    {
        $ph = implode(',', array_fill(0, count($branchIds), '?'));
        $params = array_merge(array($date, $date), $branchIds);
        $stmt = $this->db->prepare("
            SELECT l.user_id
            FROM leaves l
            JOIN users u ON l.user_id = u.id
            WHERE l.status = 'approved'
              AND ? BETWEEN l.start_date AND l.end_date
              AND ? BETWEEN l.start_date AND l.end_date
              AND u.branch_id IN ($ph)
        ");
        $stmt->execute($params);
        return array_column($stmt->fetchAll(), 'user_id');
    }

    /**
     * 取得使用者清單（篩選用）
     */
    public function getUsers(array $branchIds)
    {
        $ph = implode(',', array_fill(0, count($branchIds), '?'));
        $stmt = $this->db->prepare("SELECT id, real_name FROM users WHERE branch_id IN ($ph) AND is_active = 1 ORDER BY real_name");
        $stmt->execute($branchIds);
        return $stmt->fetchAll();
    }

    /**
     * 取得整月行事曆資料 (某月每日的請假人員)
     */
    public function getCalendarData($yearMonth, array $branchIds, $roleFilter = null)
    {
        $ph = implode(',', array_fill(0, count($branchIds), '?'));
        $startDate = $yearMonth . '-01';
        $endDate = date('Y-m-t', strtotime($startDate));

        $roleWhere = '';
        $params = array_merge(array($endDate, $startDate), $branchIds);
        if ($roleFilter && is_array($roleFilter)) {
            $rph = implode(',', array_fill(0, count($roleFilter), '?'));
            $roleWhere = " AND u.role IN ($rph)";
            $params = array_merge($params, $roleFilter);
        }
        $stmt = $this->db->prepare("
            SELECT l.*, u.real_name, u.branch_id
            FROM leaves l
            JOIN users u ON l.user_id = u.id
            WHERE l.start_date <= ? AND l.end_date >= ?
              AND u.branch_id IN ($ph)
              AND l.status IN ('approved','pending')
              {$roleWhere}
            ORDER BY u.real_name
        ");
        $stmt->execute($params);
        $leaves = $stmt->fetchAll();

        // 將請假資料按日期分組
        $calendar = array();
        $daysInMonth = (int)date('t', strtotime($startDate));
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $date = $yearMonth . '-' . str_pad($d, 2, '0', STR_PAD_LEFT);
            $calendar[$date] = array();
        }

        foreach ($leaves as $leave) {
            $from = max($startDate, $leave['start_date']);
            $to = min($endDate, $leave['end_date']);
            $current = $from;
            while ($current <= $to) {
                if (isset($calendar[$current])) {
                    $calendar[$current][] = $leave;
                }
                $current = date('Y-m-d', strtotime($current . ' +1 day'));
            }
        }

        return $calendar;
    }

    /**
     * 批次新增請假（行事曆用，一次勾選多人）
     */
    public function createBatch($date, array $userIds, $leaveType)
    {
        $stmt = $this->db->prepare('
            INSERT INTO leaves (user_id, leave_type, start_date, end_date, reason, status)
            VALUES (?, ?, ?, ?, ?, ?)
        ');
        $created = 0;
        foreach ($userIds as $uid) {
            // 檢查是否已有該日請假
            $check = $this->db->prepare("SELECT id FROM leaves WHERE user_id = ? AND start_date <= ? AND end_date >= ? AND status != 'rejected'");
            $check->execute(array($uid, $date, $date));
            if ($check->fetch()) continue;

            $stmt->execute(array($uid, $leaveType, $date, $date, null, 'approved'));
            $created++;
        }
        return $created;
    }

    /**
     * 取消某日某人請假（行事曆用）
     */
    public function cancelLeaveOnDate($userId, $date)
    {
        // 找到該日單天假
        $stmt = $this->db->prepare("
            SELECT id FROM leaves
            WHERE user_id = ? AND start_date = ? AND end_date = ?
        ");
        $stmt->execute(array($userId, $date, $date));
        $row = $stmt->fetch();
        if ($row) {
            $this->db->prepare('DELETE FROM leaves WHERE id = ?')->execute(array($row['id']));
            return true;
        }
        return false;
    }
}
