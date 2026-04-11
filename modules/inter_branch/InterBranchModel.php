<?php
/**
 * 跨點點工費管理 Model
 */
class InterBranchModel
{
    /** @var PDO */
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * 取得清單
     */
    public function getList(array $branchIds, array $filters = array())
    {
        $where = array();
        $params = array();

        // 據點篩選
        if (!empty($branchIds)) {
            $ph = implode(',', array_fill(0, count($branchIds), '?'));
            $where[] = "(ibs.from_branch_id IN ($ph) OR ibs.to_branch_id IN ($ph))";
            $params = array_merge($branchIds, $branchIds);
        }

        // 月份篩選
        if (!empty($filters['month'])) {
            $startDate = $filters['month'] . '-01';
            $endDate = date('Y-m-t', strtotime($startDate));
            $where[] = 'ibs.support_date BETWEEN ? AND ?';
            $params[] = $startDate;
            $params[] = $endDate;
        }

        // 據點篩選（特定）
        if (!empty($filters['branch_id'])) {
            $where[] = '(ibs.from_branch_id = ? OR ibs.to_branch_id = ?)';
            $params[] = (int)$filters['branch_id'];
            $params[] = (int)$filters['branch_id'];
        }

        // 結算狀態
        if ($filters['settled'] !== '' && isset($filters['settled'])) {
            $where[] = 'ibs.settled = ?';
            $params[] = (int)$filters['settled'];
        }

        $whereStr = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $stmt = $this->db->prepare("
            SELECT ibs.*,
                   u.real_name AS user_name,
                   bf.name AS from_branch_name,
                   bt.name AS to_branch_name
            FROM inter_branch_support ibs
            JOIN users u ON ibs.user_id = u.id
            JOIN branches bf ON ibs.from_branch_id = bf.id
            JOIN branches bt ON ibs.to_branch_id = bt.id
            $whereStr
            ORDER BY ibs.support_date DESC, ibs.id DESC
        ");
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * 取得單筆
     */
    public function getById($id)
    {
        $stmt = $this->db->prepare("
            SELECT ibs.*,
                   u.real_name AS user_name,
                   bf.name AS from_branch_name,
                   bt.name AS to_branch_name
            FROM inter_branch_support ibs
            JOIN users u ON ibs.user_id = u.id
            JOIN branches bf ON ibs.from_branch_id = bf.id
            JOIN branches bt ON ibs.to_branch_id = bt.id
            WHERE ibs.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    /**
     * 新增
     */
    public function create(array $data)
    {
        $stmt = $this->db->prepare("
            INSERT INTO inter_branch_support (user_id, from_branch_id, to_branch_id, support_date, charge_type, hours, schedule_id, note)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute(array(
            (int)$data['user_id'],
            (int)$data['from_branch_id'],
            (int)$data['to_branch_id'],
            $data['support_date'],
            $data['charge_type'],
            $data['charge_type'] === 'hourly' ? ($data['hours'] ?: null) : null,
            !empty($data['schedule_id']) ? (int)$data['schedule_id'] : null,
            $data['note'] ?: null,
        ));
        return $this->db->lastInsertId();
    }

    /**
     * 更新
     */
    public function update($id, array $data)
    {
        $stmt = $this->db->prepare("
            UPDATE inter_branch_support
            SET user_id = ?, from_branch_id = ?, to_branch_id = ?, support_date = ?,
                charge_type = ?, hours = ?, note = ?
            WHERE id = ? AND settled = 0
        ");
        $stmt->execute(array(
            (int)$data['user_id'],
            (int)$data['from_branch_id'],
            (int)$data['to_branch_id'],
            $data['support_date'],
            $data['charge_type'],
            $data['charge_type'] === 'hourly' ? ($data['hours'] ?: null) : null,
            $data['note'] ?: null,
            (int)$id,
        ));
    }

    /**
     * 刪除（僅未結算）
     */
    public function delete($id)
    {
        $stmt = $this->db->prepare("DELETE FROM inter_branch_support WHERE id = ? AND settled = 0");
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }

    /**
     * 月結：標記指定月份的記錄為已結算
     */
    public function settleMonth($month, array $branchIds)
    {
        $startDate = $month . '-01';
        $endDate = date('Y-m-t', strtotime($startDate));
        $ph = implode(',', array_fill(0, count($branchIds), '?'));

        $params = array_merge(array($month), $branchIds, array($startDate, $endDate));

        $stmt = $this->db->prepare("
            UPDATE inter_branch_support
            SET settled = 1, settle_month = ?
            WHERE (from_branch_id IN ($ph) OR to_branch_id IN ($ph))
              AND support_date BETWEEN ? AND ?
              AND settled = 0
        ");

        // 需要兩份 branchIds for OR 條件
        $allParams = array($month);
        $allParams = array_merge($allParams, $branchIds, $branchIds);
        $allParams[] = $startDate;
        $allParams[] = $endDate;

        $stmt->execute($allParams);
        return $stmt->rowCount();
    }

    /**
     * 月結摘要
     */
    public function getSettleSummary($month, array $branchIds)
    {
        $startDate = $month . '-01';
        $endDate = date('Y-m-t', strtotime($startDate));
        $ph = implode(',', array_fill(0, count($branchIds), '?'));

        $params = array_merge($branchIds, $branchIds, array($startDate, $endDate));

        $stmt = $this->db->prepare("
            SELECT bf.name AS from_branch, bt.name AS to_branch,
                   COUNT(*) AS support_count,
                   SUM(CASE WHEN ibs.charge_type = 'full_day' THEN 1 ELSE 0 END) AS full_days,
                   SUM(CASE WHEN ibs.charge_type = 'half_day' THEN 1 ELSE 0 END) AS half_days,
                   SUM(CASE WHEN ibs.charge_type = 'hourly' THEN COALESCE(ibs.hours, 0) ELSE 0 END) AS total_hours,
                   SUM(ibs.settled) AS settled_count
            FROM inter_branch_support ibs
            JOIN branches bf ON ibs.from_branch_id = bf.id
            JOIN branches bt ON ibs.to_branch_id = bt.id
            WHERE (ibs.from_branch_id IN ($ph) OR ibs.to_branch_id IN ($ph))
              AND ibs.support_date BETWEEN ? AND ?
            GROUP BY bf.name, bt.name
            ORDER BY bf.name, bt.name
        ");
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * 取得所有據點
     */
    public function getAllBranches()
    {
        $stmt = $this->db->query("SELECT * FROM branches WHERE is_active = 1 ORDER BY code");
        return $stmt->fetchAll();
    }

    /**
     * 取得工程師
     */
    public function getEngineers(array $branchIds = array())
    {
        if (empty($branchIds)) {
            $stmt = $this->db->query("SELECT id, real_name, branch_id FROM users WHERE is_active = 1 AND is_engineer = 1 AND employment_status IN ('active','probation') AND employee_id IS NOT NULL AND employee_id != '' ORDER BY real_name");
            return $stmt->fetchAll();
        }
        $ph = implode(',', array_fill(0, count($branchIds), '?'));
        $stmt = $this->db->prepare("SELECT id, real_name, branch_id FROM users WHERE is_active = 1 AND is_engineer = 1 AND employment_status IN ('active','probation') AND employee_id IS NOT NULL AND employee_id != '' AND branch_id IN ($ph) ORDER BY real_name");
        $stmt->execute($branchIds);
        return $stmt->fetchAll();
    }

    /**
     * 計費類型標籤
     */
    public static function chargeTypeLabel($type)
    {
        $labels = array(
            'full_day' => '整日',
            'half_day' => '半日',
            'hourly'   => '時數',
        );
        return isset($labels[$type]) ? $labels[$type] : $type;
    }

    // ============================================================
    // 點工出勤管理
    // ============================================================

    public function getAttendanceByDate($date)
    {
        // 取得指定日期的出勤記錄（含排工自動產生的 + 手動新增的）
        $stmt = $this->db->prepare("
            SELECT da.*, dw.name AS worker_name, dw.phone AS worker_phone, dw.daily_rate AS worker_daily_rate,
                   b.name AS branch_name, s.id AS sched_id,
                   c.title AS case_name, u.real_name AS recorded_by_name
            FROM dispatch_attendance da
            JOIN dispatch_workers dw ON da.dispatch_worker_id = dw.id
            JOIN branches b ON da.branch_id = b.id
            LEFT JOIN schedules s ON da.schedule_id = s.id
            LEFT JOIN cases c ON s.case_id = c.id
            LEFT JOIN users u ON da.recorded_by = u.id
            WHERE da.attendance_date = ?
            ORDER BY dw.name, b.name
        ");
        $stmt->execute(array($date));
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getScheduledDispatchWorkers($date)
    {
        // 取得指定日期排工中已指派的點工人員（但尚未建出勤記錄的）
        $stmt = $this->db->prepare("
            SELECT sdw.schedule_id, sdw.dispatch_worker_id, dw.name AS worker_name,
                   dw.daily_rate, s.schedule_date, c.branch_id, b.name AS branch_name,
                   c.title AS case_name
            FROM schedule_dispatch_workers sdw
            JOIN dispatch_workers dw ON sdw.dispatch_worker_id = dw.id
            JOIN schedules s ON sdw.schedule_id = s.id
            JOIN cases c ON s.case_id = c.id
            JOIN branches b ON c.branch_id = b.id
            WHERE s.schedule_date = ? AND s.status != 'cancelled'
              AND NOT EXISTS (
                SELECT 1 FROM dispatch_attendance da
                WHERE da.dispatch_worker_id = sdw.dispatch_worker_id
                  AND da.attendance_date = s.schedule_date
                  AND da.schedule_id = sdw.schedule_id
              )
            ORDER BY dw.name
        ");
        $stmt->execute(array($date));
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function saveAttendance($data)
    {
        // 新增或更新出勤記錄
        $rate = (int)$data['daily_rate'];
        $amount = $data['charge_type'] === 'half_day' ? (int)round($rate / 2) : $rate;

        $check = $this->db->prepare("SELECT id FROM dispatch_attendance WHERE dispatch_worker_id = ? AND attendance_date = ? AND branch_id = ?");
        $check->execute(array($data['dispatch_worker_id'], $data['attendance_date'], $data['branch_id']));
        $existing = $check->fetchColumn();

        if ($existing) {
            $stmt = $this->db->prepare("
                UPDATE dispatch_attendance SET charge_type=?, daily_rate=?, amount=?, status=?, note=?, schedule_id=?, updated_at=NOW()
                WHERE id = ? AND settled = 0
            ");
            $stmt->execute(array(
                $data['charge_type'], $rate, $amount, $data['status'],
                isset($data['note']) ? $data['note'] : null,
                isset($data['schedule_id']) ? $data['schedule_id'] : null,
                $existing
            ));
            return $existing;
        } else {
            $stmt = $this->db->prepare("
                INSERT INTO dispatch_attendance (dispatch_worker_id, schedule_id, attendance_date, branch_id, charge_type, daily_rate, amount, status, note, recorded_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute(array(
                $data['dispatch_worker_id'],
                isset($data['schedule_id']) ? $data['schedule_id'] : null,
                $data['attendance_date'],
                $data['branch_id'],
                $data['charge_type'],
                $rate, $amount, $data['status'],
                isset($data['note']) ? $data['note'] : null,
                $data['recorded_by']
            ));
            return $this->db->lastInsertId();
        }
    }

    public function deleteAttendance($id)
    {
        $stmt = $this->db->prepare("DELETE FROM dispatch_attendance WHERE id = ? AND settled = 0");
        $stmt->execute(array($id));
        return $stmt->rowCount();
    }

    public function getAttendanceSettle($filters)
    {
        $where = 'da.status = \'present\'';
        $params = array();

        if (!empty($filters['date_from'])) {
            $where .= ' AND da.attendance_date >= ?';
            $params[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where .= ' AND da.attendance_date <= ?';
            $params[] = $filters['date_to'];
        }
        if (!empty($filters['worker_id'])) {
            $where .= ' AND da.dispatch_worker_id = ?';
            $params[] = (int)$filters['worker_id'];
        }
        if (!empty($filters['branch_id'])) {
            $where .= ' AND da.branch_id = ?';
            $params[] = (int)$filters['branch_id'];
        }
        if (isset($filters['settled']) && $filters['settled'] !== '') {
            $where .= ' AND da.settled = ?';
            $params[] = (int)$filters['settled'];
        }

        // 按人員統計
        $byWorker = $this->db->prepare("
            SELECT da.dispatch_worker_id, dw.name AS worker_name, dw.daily_rate AS current_rate,
                   SUM(CASE WHEN da.charge_type='full_day' THEN 1 ELSE 0 END) AS full_days,
                   SUM(CASE WHEN da.charge_type='half_day' THEN 1 ELSE 0 END) AS half_days,
                   SUM(da.amount) AS total_amount,
                   COUNT(*) AS total_records
            FROM dispatch_attendance da
            JOIN dispatch_workers dw ON da.dispatch_worker_id = dw.id
            WHERE $where
            GROUP BY da.dispatch_worker_id
            ORDER BY dw.name
        ");
        $byWorker->execute($params);

        // 按天統計
        $byDate = $this->db->prepare("
            SELECT da.attendance_date,
                   COUNT(DISTINCT da.dispatch_worker_id) AS worker_count,
                   SUM(da.amount) AS total_amount
            FROM dispatch_attendance da
            WHERE $where
            GROUP BY da.attendance_date
            ORDER BY da.attendance_date
        ");
        $byDate->execute($params);

        // 按分公司統計
        $byBranch = $this->db->prepare("
            SELECT da.branch_id, b.name AS branch_name,
                   COUNT(DISTINCT da.dispatch_worker_id) AS worker_count,
                   SUM(CASE WHEN da.charge_type='full_day' THEN 1 ELSE 0 END) AS full_days,
                   SUM(CASE WHEN da.charge_type='half_day' THEN 1 ELSE 0 END) AS half_days,
                   SUM(da.amount) AS total_amount
            FROM dispatch_attendance da
            JOIN branches b ON da.branch_id = b.id
            WHERE $where
            GROUP BY da.branch_id
            ORDER BY b.name
        ");
        $byBranch->execute($params);

        // 明細
        $detail = $this->db->prepare("
            SELECT da.*, dw.name AS worker_name, b.name AS branch_name,
                   c.title AS case_name, u.real_name AS recorded_by_name
            FROM dispatch_attendance da
            JOIN dispatch_workers dw ON da.dispatch_worker_id = dw.id
            JOIN branches b ON da.branch_id = b.id
            LEFT JOIN schedules s ON da.schedule_id = s.id
            LEFT JOIN cases c ON s.case_id = c.id
            LEFT JOIN users u ON da.recorded_by = u.id
            WHERE $where
            ORDER BY da.attendance_date, dw.name
        ");
        $detail->execute($params);

        return array(
            'by_worker' => $byWorker->fetchAll(PDO::FETCH_ASSOC),
            'by_date' => $byDate->fetchAll(PDO::FETCH_ASSOC),
            'by_branch' => $byBranch->fetchAll(PDO::FETCH_ASSOC),
            'detail' => $detail->fetchAll(PDO::FETCH_ASSOC),
        );
    }

    public function settleAttendance($month, $filters = array())
    {
        $dateFrom = $month . '-01';
        $dateTo = date('Y-m-t', strtotime($dateFrom));

        $sql = "UPDATE dispatch_attendance SET settled = 1, settle_month = ? WHERE status = 'present' AND settled = 0 AND attendance_date >= ? AND attendance_date <= ?";
        $p = array($month, $dateFrom, $dateTo);

        if (!empty($filters['branch_id'])) {
            $sql .= ' AND branch_id = ?';
            $p[] = (int)$filters['branch_id'];
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($p);
        return $stmt->rowCount();
    }

    public function getActiveDispatchWorkers()
    {
        return $this->db->query("SELECT id, name, daily_rate, phone FROM dispatch_workers WHERE is_active = 1 ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getBranches()
    {
        return $this->db->query("SELECT id, name FROM branches WHERE is_active = 1 ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    }
}
