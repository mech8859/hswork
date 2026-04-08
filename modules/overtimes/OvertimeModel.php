<?php
/**
 * 加班單管理模型
 */
class OvertimeModel
{
    /** @var PDO */
    private $db;

    private static $typeLabels = array(
        'weekday'  => '平日延長',
        'rest_day' => '例假日',
        'holiday'  => '國定假日',
        'other'    => '其他',
    );

    private static $statusLabels = array(
        'pending'  => '待核准',
        'approved' => '已核准',
        'rejected' => '已駁回',
    );

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public static function typeLabel($type)
    {
        return isset(self::$typeLabels[$type]) ? self::$typeLabels[$type] : $type;
    }

    public static function statusLabel($status)
    {
        return isset(self::$statusLabels[$status]) ? self::$statusLabels[$status] : $status;
    }

    public static function typeOptions()
    {
        return self::$typeLabels;
    }

    public static function statusOptions()
    {
        return self::$statusLabels;
    }

    /**
     * 取得加班單清單
     */
    public function getList(array $branchIds, array $filters = array(), $userId = null)
    {
        if (empty($branchIds)) return array();
        $where = 'u.branch_id IN (' . implode(',', array_fill(0, count($branchIds), '?')) . ')';
        $params = $branchIds;

        // 只看自己的（own 權限）
        if ($userId) {
            $where .= ' AND o.user_id = ?';
            $params[] = $userId;
        }
        if (!empty($filters['month'])) {
            $where .= ' AND o.overtime_date LIKE ?';
            $params[] = $filters['month'] . '%';
        }
        if (!empty($filters['user_id'])) {
            $where .= ' AND o.user_id = ?';
            $params[] = $filters['user_id'];
        }
        if (!empty($filters['status'])) {
            $where .= ' AND o.status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['overtime_type'])) {
            $where .= ' AND o.overtime_type = ?';
            $params[] = $filters['overtime_type'];
        }
        if (!empty($filters['branch_id'])) {
            $where .= ' AND u.branch_id = ?';
            $params[] = (int)$filters['branch_id'];
        }
        if (!empty($filters['date_from'])) {
            $where .= ' AND o.overtime_date >= ?';
            $params[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where .= ' AND o.overtime_date <= ?';
            $params[] = $filters['date_to'];
        }

        $stmt = $this->db->prepare("
            SELECT o.*, u.real_name, u.branch_id, b.name AS branch_name,
                   ap.real_name AS approved_by_name,
                   cb.real_name AS created_by_name
            FROM overtimes o
            JOIN users u ON o.user_id = u.id
            JOIN branches b ON u.branch_id = b.id
            LEFT JOIN users ap ON o.approved_by = ap.id
            LEFT JOIN users cb ON o.created_by = cb.id
            WHERE $where
            ORDER BY o.overtime_date DESC, o.created_at DESC
        ");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * 取得單一加班單
     */
    public function getById($id)
    {
        $stmt = $this->db->prepare("
            SELECT o.*, u.real_name, u.branch_id, b.name AS branch_name,
                   ap.real_name AS approved_by_name,
                   cb.real_name AS created_by_name
            FROM overtimes o
            JOIN users u ON o.user_id = u.id
            JOIN branches b ON u.branch_id = b.id
            LEFT JOIN users ap ON o.approved_by = ap.id
            LEFT JOIN users cb ON o.created_by = cb.id
            WHERE o.id = ?
        ");
        $stmt->execute(array($id));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $row : null;
    }

    /**
     * 新增加班單
     */
    public function create(array $data)
    {
        $hours = $this->calcHours($data);
        $stmt = $this->db->prepare("
            INSERT INTO overtimes
                (user_id, overtime_date, start_time, end_time, hours, overtime_type, reason, note, status, created_by, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, NOW())
        ");
        $stmt->execute(array(
            (int)$data['user_id'],
            $data['overtime_date'],
            $data['start_time'],
            $data['end_time'],
            $hours,
            !empty($data['overtime_type']) ? $data['overtime_type'] : 'weekday',
            $data['reason'],
            !empty($data['note']) ? $data['note'] : null,
            !empty($data['created_by']) ? (int)$data['created_by'] : null,
        ));
        return (int)$this->db->lastInsertId();
    }

    /**
     * 更新加班單（只能改 pending 狀態的）
     */
    public function update($id, array $data)
    {
        $row = $this->getById($id);
        if (!$row || $row['status'] !== 'pending') {
            throw new Exception('只能編輯待核准狀態的加班單');
        }
        $hours = $this->calcHours($data);
        $stmt = $this->db->prepare("
            UPDATE overtimes SET
                user_id = ?, overtime_date = ?, start_time = ?, end_time = ?,
                hours = ?, overtime_type = ?, reason = ?, note = ?
            WHERE id = ?
        ");
        $stmt->execute(array(
            (int)$data['user_id'],
            $data['overtime_date'],
            $data['start_time'],
            $data['end_time'],
            $hours,
            !empty($data['overtime_type']) ? $data['overtime_type'] : 'weekday',
            $data['reason'],
            !empty($data['note']) ? $data['note'] : null,
            (int)$id,
        ));
    }

    /**
     * 計算加班時數
     * 如果 data['hours'] 有值就用，否則用 end-start 計算
     */
    private function calcHours(array $data)
    {
        if (isset($data['hours']) && (float)$data['hours'] > 0) {
            return round((float)$data['hours'], 2);
        }
        if (empty($data['start_time']) || empty($data['end_time'])) return 0;
        $start = strtotime($data['overtime_date'] . ' ' . $data['start_time']);
        $end = strtotime($data['overtime_date'] . ' ' . $data['end_time']);
        if ($end <= $start) {
            // 跨日
            $end = strtotime('+1 day', $end);
        }
        return round(($end - $start) / 3600, 2);
    }

    /**
     * 核准
     */
    public function approve($id, $approvedBy)
    {
        $row = $this->getById($id);
        if (!$row) throw new Exception('加班單不存在');
        if ($row['status'] !== 'pending') throw new Exception('只能核准待審核狀態');
        $this->db->prepare("UPDATE overtimes SET status = 'approved', approved_by = ?, approved_at = NOW(), reject_reason = NULL WHERE id = ?")
                 ->execute(array($approvedBy, $id));
    }

    /**
     * 駁回
     */
    public function reject($id, $approvedBy, $reason = '')
    {
        $row = $this->getById($id);
        if (!$row) throw new Exception('加班單不存在');
        if ($row['status'] !== 'pending') throw new Exception('只能駁回待審核狀態');
        $this->db->prepare("UPDATE overtimes SET status = 'rejected', approved_by = ?, approved_at = NOW(), reject_reason = ? WHERE id = ?")
                 ->execute(array($approvedBy, $reason, $id));
    }

    /**
     * 撤回到 pending（管理者重置用）
     */
    public function resetToPending($id)
    {
        $this->db->prepare("UPDATE overtimes SET status = 'pending', approved_by = NULL, approved_at = NULL, reject_reason = NULL WHERE id = ?")
                 ->execute(array($id));
    }

    /**
     * 刪除（只能刪 pending 或 rejected）
     */
    public function delete($id)
    {
        $row = $this->getById($id);
        if (!$row) return false;
        if ($row['status'] === 'approved') {
            throw new Exception('已核准的加班單不可刪除，請先撤回為待審核');
        }
        $this->db->prepare("DELETE FROM overtimes WHERE id = ?")->execute(array($id));
        return true;
    }

    /**
     * 取得使用者清單（篩選用）
     */
    public function getUsers(array $branchIds)
    {
        if (empty($branchIds)) return array();
        $ph = implode(',', array_fill(0, count($branchIds), '?'));
        $stmt = $this->db->prepare("SELECT id, real_name, branch_id FROM users WHERE branch_id IN ($ph) AND is_active = 1 ORDER BY real_name");
        $stmt->execute($branchIds);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * 月結報表 - 個人總時數
     * @param string $yearMonth 'YYYY-MM'
     */
    public function getMonthlySummary($yearMonth, array $branchIds, $statusFilter = 'approved')
    {
        if (empty($branchIds)) return array();
        $ph = implode(',', array_fill(0, count($branchIds), '?'));
        $params = array($yearMonth . '%');
        $statusWhere = '';
        if ($statusFilter) {
            $statusWhere = ' AND o.status = ?';
            $params[] = $statusFilter;
        }
        $params = array_merge($params, $branchIds);

        $stmt = $this->db->prepare("
            SELECT
                u.id AS user_id,
                u.real_name,
                b.name AS branch_name,
                COUNT(o.id) AS record_count,
                COALESCE(SUM(o.hours), 0) AS total_hours,
                COALESCE(SUM(CASE WHEN o.overtime_type='weekday' THEN o.hours ELSE 0 END), 0) AS weekday_hours,
                COALESCE(SUM(CASE WHEN o.overtime_type='rest_day' THEN o.hours ELSE 0 END), 0) AS rest_day_hours,
                COALESCE(SUM(CASE WHEN o.overtime_type='holiday' THEN o.hours ELSE 0 END), 0) AS holiday_hours,
                COALESCE(SUM(CASE WHEN o.overtime_type='other' THEN o.hours ELSE 0 END), 0) AS other_hours
            FROM overtimes o
            JOIN users u ON o.user_id = u.id
            JOIN branches b ON u.branch_id = b.id
            WHERE o.overtime_date LIKE ? $statusWhere AND u.branch_id IN ($ph)
            GROUP BY u.id, u.real_name, b.name
            ORDER BY total_hours DESC, u.real_name
        ");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
