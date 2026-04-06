<?php
/**
 * 排工資料模型
 */
class ScheduleModel
{
    /** 每日工作容量（小時） */
    const DAILY_HOURS_CAPACITY = 10;
    /** 預設預估工時（estimated_hours 為空時） */
    const DEFAULT_ESTIMATED_HOURS = 4;

    /** @var PDO */
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * 取得日期範圍內的排工 (行事曆用)
     */
    public function getByDateRange(array $branchIds, string $startDate, string $endDate): array
    {
        $ph = implode(',', array_fill(0, count($branchIds), '?'));
        $params = array_merge($branchIds, [$startDate, $endDate]);

        $stmt = $this->db->prepare("
            SELECT s.*, c.title AS case_title, c.case_number, c.address, c.difficulty,
                   c.case_type, c.total_visits, c.max_engineers,
                   c.planned_start_time AS case_designated_time,
                   v.plate_number, v.vehicle_type, v.seats,
                   b.name AS branch_name, b.code AS branch_code
            FROM schedules s
            JOIN cases c ON s.case_id = c.id
            JOIN branches b ON c.branch_id = b.id
            LEFT JOIN vehicles v ON s.vehicle_id = v.id
            WHERE c.branch_id IN ($ph)
              AND s.schedule_date BETWEEN ? AND ?
            ORDER BY s.schedule_date ASC, COALESCE(s.designated_time, s.start_time, '23:59') ASC
        ");
        $stmt->execute($params);
        $schedules = $stmt->fetchAll();

        // 載入每個排工的人員
        foreach ($schedules as &$s) {
            $engStmt = $this->db->prepare('
                SELECT se.*, u.real_name, u.phone, u.is_engineer
                FROM schedule_engineers se
                JOIN users u ON se.user_id = u.id
                WHERE se.schedule_id = ?
                ORDER BY se.is_lead DESC, u.real_name
            ');
            $engStmt->execute([$s['id']]);
            $s['engineers'] = $engStmt->fetchAll();

            // 點工人員
            $dwStmt = $this->db->prepare('
                SELECT sdw.*, dw.name, dw.phone, dw.vendor
                FROM schedule_dispatch_workers sdw
                JOIN dispatch_workers dw ON sdw.dispatch_worker_id = dw.id
                WHERE sdw.schedule_id = ?
                ORDER BY dw.name
            ');
            $dwStmt->execute([$s['id']]);
            $s['dispatch_workers'] = $dwStmt->fetchAll();
        }

        return $schedules;
    }

    /**
     * 取得單一排工詳情
     */
    public function getById(int $id): ?array
    {
        $stmt = $this->db->prepare('
            SELECT s.*, c.title AS case_title, c.case_number, c.address, c.difficulty,
                   c.total_visits, c.max_engineers, c.branch_id,
                   v.plate_number, v.vehicle_type, v.seats,
                   b.name AS branch_name
            FROM schedules s
            JOIN cases c ON s.case_id = c.id
            JOIN branches b ON c.branch_id = b.id
            LEFT JOIN vehicles v ON s.vehicle_id = v.id
            WHERE s.id = ?
        ');
        $stmt->execute([$id]);
        $schedule = $stmt->fetch();
        if (!$schedule) return null;

        // 人員
        $engStmt = $this->db->prepare('
            SELECT se.*, u.real_name, u.phone
            FROM schedule_engineers se
            JOIN users u ON se.user_id = u.id
            WHERE se.schedule_id = ?
        ');
        $engStmt->execute([$id]);
        $schedule['engineers'] = $engStmt->fetchAll();

        // 點工人員
        $dwStmt = $this->db->prepare('
            SELECT sdw.*, dw.name, dw.phone, dw.vendor
            FROM schedule_dispatch_workers sdw
            JOIN dispatch_workers dw ON sdw.dispatch_worker_id = dw.id
            WHERE sdw.schedule_id = ?
            ORDER BY dw.name
        ');
        $dwStmt->execute([$id]);
        $schedule['dispatch_workers'] = $dwStmt->fetchAll();

        return $schedule;
    }

    /**
     * 自動計算第幾次施工（同案件已排工次數 + 1，排除已取消）
     */
    public function calcNextVisitNumber($caseId)
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM schedules
            WHERE case_id = ? AND status != 'cancelled'
        ");
        $stmt->execute(array($caseId));
        return (int)$stmt->fetchColumn() + 1;
    }

    /**
     * 新增排工
     */
    public function create(array $data): int
    {
        // 自動計算第幾次施工
        $visitNumber = $this->calcNextVisitNumber($data['case_id']);

        $stmt = $this->db->prepare('
            INSERT INTO schedules (case_id, schedule_date, start_time, end_time, designated_time, vehicle_id, visit_number, status, note, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute(array(
            $data['case_id'],
            $data['schedule_date'],
            !empty($data['start_time']) ? $data['start_time'] : null,
            !empty($data['end_time']) ? $data['end_time'] : null,
            !empty($data['designated_time']) ? $data['designated_time'] : null,
            $data['vehicle_id'] ?: null,
            $visitNumber,
            $data['status'] ?: 'planned',
            isset($data['note']) ? $data['note'] : null,
            Auth::id(),
        ));
        $scheduleId = (int)$this->db->lastInsertId();

        // 指派工程師
        if (!empty($data['engineer_ids'])) {
            $this->assignEngineers($scheduleId, $data['engineer_ids'], $data);
        }

        // 指派點工人員
        if (!empty($data['dispatch_worker_ids'])) {
            $this->assignDispatchWorkers($scheduleId, $data['dispatch_worker_ids']);
        }

        // 更新案件狀態
        $this->db->prepare("UPDATE cases SET status = 'scheduled' WHERE id = ? AND status IN ('pending','ready')")
                 ->execute([$data['case_id']]);

        return $scheduleId;
    }

    /**
     * 更新排工
     */
    public function update(int $id, array $data): void
    {
        $stmt = $this->db->prepare('
            UPDATE schedules SET schedule_date = ?, start_time = ?, end_time = ?, designated_time = ?, vehicle_id = ?, status = ?, note = ?
            WHERE id = ?
        ');
        $stmt->execute(array(
            $data['schedule_date'],
            !empty($data['start_time']) ? $data['start_time'] : null,
            !empty($data['end_time']) ? $data['end_time'] : null,
            !empty($data['designated_time']) ? $data['designated_time'] : null,
            $data['vehicle_id'] ?: null,
            $data['status'] ?: 'planned',
            isset($data['note']) ? $data['note'] : null,
            $id,
        ));

        // 重新指派工程師
        if (isset($data['engineer_ids'])) {
            $this->db->prepare('DELETE FROM schedule_engineers WHERE schedule_id = ?')->execute([$id]);
            $this->assignEngineers($id, $data['engineer_ids'], $data);
        }

        // 重新指派點工人員
        if (isset($data['dispatch_worker_ids'])) {
            $this->assignDispatchWorkers($id, $data['dispatch_worker_ids']);
        }
    }

    /**
     * 指派工程師
     */
    private function assignEngineers(int $scheduleId, array $engineerIds, array $data): void
    {
        $stmt = $this->db->prepare('
            INSERT INTO schedule_engineers (schedule_id, user_id, is_lead, is_override, override_reason)
            VALUES (?, ?, ?, ?, ?)
        ');
        $leadId = $data['lead_engineer_id'] ?? null;
        $overrideIds = $data['override_ids'] ?? [];

        foreach ($engineerIds as $userId) {
            $userId = (int)$userId;
            if (!$userId) continue;
            $isOverride = in_array($userId, $overrideIds) ? 1 : 0;
            $stmt->execute([
                $scheduleId,
                $userId,
                $userId == $leadId ? 1 : 0,
                $isOverride,
                $isOverride ? ($data['override_reason'] ?? null) : null,
            ]);
        }

        // 多次施工人員連續性檢查
        $schedule = $this->getById($scheduleId);
        if ($schedule && $schedule['visit_number'] > 1) {
            $this->checkVisitContinuity($schedule);
        }
    }

    /**
     * 指派點工人員
     */
    private function assignDispatchWorkers($scheduleId, $workerIds)
    {
        // 清除舊的指派
        $this->db->prepare('DELETE FROM schedule_dispatch_workers WHERE schedule_id = ?')->execute(array($scheduleId));

        // 取得排工日期和案件分公司
        $schStmt = $this->db->prepare('SELECT s.schedule_date, c.branch_id FROM schedules s JOIN cases c ON s.case_id = c.id WHERE s.id = ?');
        $schStmt->execute(array($scheduleId));
        $schInfo = $schStmt->fetch(PDO::FETCH_ASSOC);

        $stmt = $this->db->prepare('INSERT INTO schedule_dispatch_workers (schedule_id, dispatch_worker_id) VALUES (?, ?)');
        foreach ($workerIds as $wid) {
            $wid = (int)$wid;
            if (!$wid) continue;
            $stmt->execute(array($scheduleId, $wid));

            // 自動建立預排出勤記錄（如果尚未存在）
            if ($schInfo) {
                $this->createPreScheduledAttendance($wid, $scheduleId, $schInfo['schedule_date'], $schInfo['branch_id']);
            }
        }

        // 清除被移除的點工的預排出勤記錄（只清 scheduled 狀態的）
        if ($schInfo) {
            $widsStr = implode(',', array_map('intval', array_filter($workerIds)));
            if ($widsStr) {
                $this->db->prepare("DELETE FROM dispatch_attendance WHERE schedule_id = ? AND status = 'scheduled' AND dispatch_worker_id NOT IN ($widsStr)")->execute(array($scheduleId));
            } else {
                $this->db->prepare("DELETE FROM dispatch_attendance WHERE schedule_id = ? AND status = 'scheduled'")->execute(array($scheduleId));
            }
        }
    }

    private function createPreScheduledAttendance($workerId, $scheduleId, $date, $branchId)
    {
        // 檢查是否已有該人/日/排工的出勤記錄
        $check = $this->db->prepare("SELECT id FROM dispatch_attendance WHERE dispatch_worker_id = ? AND attendance_date = ? AND schedule_id = ?");
        $check->execute(array($workerId, $date, $scheduleId));
        if ($check->fetchColumn()) return;

        // 取得日薪
        $rateStmt = $this->db->prepare("SELECT daily_rate FROM dispatch_workers WHERE id = ?");
        $rateStmt->execute(array($workerId));
        $dailyRate = (int)$rateStmt->fetchColumn();

        $this->db->prepare("
            INSERT INTO dispatch_attendance (dispatch_worker_id, schedule_id, attendance_date, branch_id, charge_type, daily_rate, amount, status, recorded_by)
            VALUES (?, ?, ?, ?, 'full_day', ?, ?, 'scheduled', ?)
        ")->execute(array(
            $workerId, $scheduleId, $date, $branchId, $dailyRate, $dailyRate, Auth::id()
        ));
    }

    /**
     * 取得所有點工人員（啟用中）
     */
    public function getDispatchWorkers($date = null)
    {
        if ($date) {
            $stmt = $this->db->prepare("
                SELECT DISTINCT dw.* FROM dispatch_workers dw
                WHERE dw.is_active = 1
                  AND (
                    dw.id IN (SELECT dispatch_worker_id FROM dispatch_worker_availability WHERE available_date = ?)
                    OR dw.id IN (SELECT dispatch_worker_id FROM dispatch_attendance WHERE attendance_date = ? AND status = 'present')
                  )
                ORDER BY dw.name
            ");
            $stmt->execute(array($date, $date));
            return $stmt->fetchAll();
        }
        return $this->db->query("SELECT * FROM dispatch_workers WHERE is_active = 1 ORDER BY name")->fetchAll();
    }

    /**
     * 多次施工人員連續性檢查
     */
    private function checkVisitContinuity(array $schedule): void
    {
        $prevVisit = $schedule['visit_number'] - 1;
        $stmt = $this->db->prepare('
            SELECT se.user_id FROM schedule_engineers se
            JOIN schedules s ON se.schedule_id = s.id
            WHERE s.case_id = ? AND s.visit_number = ?
        ');
        $stmt->execute([$schedule['case_id'], $prevVisit]);
        $prevEngineers = array_column($stmt->fetchAll(), 'user_id');

        if (empty($prevEngineers)) return;

        $currentEngineers = array_column($schedule['engineers'], 'user_id');
        $isSame = empty(array_diff($prevEngineers, $currentEngineers)) && empty(array_diff($currentEngineers, $prevEngineers));

        $diff = [];
        if (!$isSame) {
            $added = array_diff($currentEngineers, $prevEngineers);
            $removed = array_diff($prevEngineers, $currentEngineers);
            $diff = ['added' => array_values($added), 'removed' => array_values($removed)];
        }

        // 寫入檢查記錄
        $stmt = $this->db->prepare('
            INSERT INTO schedule_visit_check (case_id, visit_number, previous_visit_number, is_same_team, different_members)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE is_same_team = VALUES(is_same_team), different_members = VALUES(different_members), notified = 0
        ');
        $stmt->execute([
            $schedule['case_id'],
            $schedule['visit_number'],
            $prevVisit,
            $isSame ? 1 : 0,
            $isSame ? null : json_encode($diff),
        ]);
    }

    /**
     * 取得多次施工不同組通知
     */
    public function getVisitWarnings(array $branchIds): array
    {
        $ph = implode(',', array_fill(0, count($branchIds), '?'));
        $stmt = $this->db->prepare("
            SELECT svc.*, c.case_number, c.title AS case_title
            FROM schedule_visit_check svc
            JOIN cases c ON svc.case_id = c.id
            WHERE c.branch_id IN ($ph) AND svc.is_same_team = 0 AND svc.notified = 0
            ORDER BY svc.created_at DESC
        ");
        $stmt->execute($branchIds);
        return $stmt->fetchAll();
    }

    /**
     * 智慧篩選可用工程師
     */
    public function getAvailableEngineers(string $date, int $caseId, array $branchIds): array
    {
        $ph = implode(',', array_fill(0, count($branchIds), '?'));

        // 取得案件所需技能
        $reqStmt = $this->db->prepare('SELECT skill_id, min_proficiency FROM case_required_skills WHERE case_id = ?');
        $reqStmt->execute([$caseId]);
        $requiredSkills = $reqStmt->fetchAll();

        // 取得所有工程師
        $engStmt = $this->db->prepare("
            SELECT u.id, u.real_name, u.branch_id, b.name AS branch_name
            FROM users u
            JOIN branches b ON u.branch_id = b.id
            WHERE u.branch_id IN ($ph) AND u.is_engineer = 1 AND u.is_active = 1
            ORDER BY u.branch_id, u.real_name
        ");
        $engStmt->execute($branchIds);
        $engineers = $engStmt->fetchAll();

        // 取得當日已排工人員的工時
        $defaultH = self::DEFAULT_ESTIMATED_HOURS;
        $busyStmt = $this->db->prepare("
            SELECT se.user_id, SUM(COALESCE(NULLIF(c.estimated_hours, 0), {$defaultH})) AS hours_used
            FROM schedule_engineers se
            JOIN schedules s ON se.schedule_id = s.id
            JOIN cases c ON s.case_id = c.id
            WHERE s.schedule_date = ? AND s.status != 'cancelled'
            GROUP BY se.user_id
        ");
        $busyStmt->execute(array($date));
        $hoursMap = array();
        foreach ($busyStmt->fetchAll() as $row) {
            $hoursMap[$row['user_id']] = (float)$row['hours_used'];
        }

        // 取得目標案件工時
        $caseStmt2 = $this->db->prepare('SELECT estimated_hours FROM cases WHERE id = ?');
        $caseStmt2->execute(array($caseId));
        $targetHours = (float)$caseStmt2->fetchColumn();
        if ($targetHours <= 0) $targetHours = self::DEFAULT_ESTIMATED_HOURS;

        // 取得當日請假人員
        $leaveStmt = $this->db->prepare("SELECT user_id FROM leaves WHERE status = 'approved' AND start_date <= ? AND end_date >= ?");
        $leaveStmt->execute(array($date, $date));
        $onLeaveIds = array_column($leaveStmt->fetchAll(PDO::FETCH_ASSOC), 'user_id');

        // 為每位工程師計算資訊
        foreach ($engineers as &$eng) {
            $usedH = isset($hoursMap[$eng['id']]) ? $hoursMap[$eng['id']] : 0;
            $remainH = self::DAILY_HOURS_CAPACITY - $usedH;
            $eng['hours_used'] = $usedH;
            $eng['remaining_hours'] = $remainH;
            $eng['is_on_leave'] = in_array($eng['id'], $onLeaveIds);
            $eng['is_busy'] = $eng['is_on_leave'] || ($remainH < $targetHours);

            // 技能符合度
            $eng['skill_match'] = true;
            $eng['skill_details'] = [];
            foreach ($requiredSkills as $rs) {
                $skillStmt = $this->db->prepare('SELECT proficiency FROM user_skills WHERE user_id = ? AND skill_id = ?');
                $skillStmt->execute([$eng['id'], $rs['skill_id']]);
                $prof = $skillStmt->fetchColumn();
                $eng['skill_details'][] = ['skill_id' => $rs['skill_id'], 'required' => $rs['min_proficiency'], 'has' => $prof ?: 0];
                if (!$prof || $prof < $rs['min_proficiency']) {
                    $eng['skill_match'] = false;
                }
            }
        }

        // 排序: 技能符合 > 未忙碌 > 姓名
        usort($engineers, function($a, $b) {
            if ($a['skill_match'] !== $b['skill_match']) return $b['skill_match'] - $a['skill_match'];
            if ($a['is_busy'] !== $b['is_busy']) return $a['is_busy'] - $b['is_busy'];
            return strcmp($a['real_name'], $b['real_name']);
        });

        return $engineers;
    }

    /**
     * 取得可用車輛
     */
    public function getAvailableVehicles(string $date, array $branchIds): array
    {
        $ph = implode(',', array_fill(0, count($branchIds), '?'));
        $params = array_merge($branchIds, [$date]);

        $stmt = $this->db->prepare("
            SELECT v.*, b.name AS branch_name, d.real_name AS driver_name,
                   CASE WHEN s.id IS NOT NULL THEN 1 ELSE 0 END AS is_busy
            FROM vehicles v
            JOIN branches b ON v.branch_id = b.id
            LEFT JOIN users d ON v.default_driver_id = d.id
            LEFT JOIN schedules s ON s.vehicle_id = v.id AND s.schedule_date = ? AND s.status != 'cancelled'
            WHERE v.branch_id IN ($ph) AND v.is_active = 1
            ORDER BY v.branch_id, v.plate_number
        ");
        // 注意參數順序: date 在 LEFT JOIN 中用到，所以要放前面
        $paramsOrdered = array_merge([$date], $branchIds);
        $stmt->execute($paramsOrdered);
        return $stmt->fetchAll();
    }

    /**
     * 取得可排工案件
     */
    public function getSchedulableCases(array $branchIds): array
    {
        $ph = implode(',', array_fill(0, count($branchIds), '?'));
        $stmt = $this->db->prepare("
            SELECT c.id, c.case_number, c.title, c.address, c.difficulty,
                   c.total_visits, c.current_visit, c.max_engineers,
                   c.planned_start_time, c.work_time_start, c.work_time_end,
                   b.name AS branch_name
            FROM cases c
            JOIN branches b ON c.branch_id = b.id
            WHERE c.branch_id IN ($ph)
              AND c.status NOT IN ('無效','客戶取消')
            ORDER BY c.status ASC, c.updated_at DESC
        ");
        $stmt->execute($branchIds);
        return $stmt->fetchAll();
    }

    /**
     * 取得日期範圍內每日的排工人數統計
     */
    public function getDailyCapacity(array $branchIds, $startDate, $endDate)
    {
        $ph = implode(',', array_fill(0, count($branchIds), '?'));
        $params = array_merge($branchIds, array($startDate, $endDate));
        $stmt = $this->db->prepare("
            SELECT s.schedule_date, COUNT(DISTINCT se.user_id) AS eng_count
            FROM schedules s
            JOIN schedule_engineers se ON s.id = se.schedule_id
            JOIN cases c ON s.case_id = c.id
            WHERE c.branch_id IN ($ph)
              AND s.schedule_date BETWEEN ? AND ?
              AND s.status != 'cancelled'
            GROUP BY s.schedule_date
        ");
        $stmt->execute($params);
        $result = array();
        foreach ($stmt->fetchAll() as $row) {
            $result[$row['schedule_date']] = (int)$row['eng_count'];
        }
        return $result;
    }

    /**
     * 取得總工程人員數（用於容量判斷）
     */
    public function getTotalEngineers(array $branchIds)
    {
        $ph = implode(',', array_fill(0, count($branchIds), '?'));
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM users WHERE branch_id IN ($ph) AND is_engineer = 1 AND is_active = 1");
        $stmt->execute($branchIds);
        return (int)$stmt->fetchColumn();
    }

    /**
     * 取得指定人員的排工（依人員查詢用）
     */
    public function getByPerson($userId, $startDate, $endDate)
    {
        $stmt = $this->db->prepare("
            SELECT s.*, c.title AS case_title, c.case_number, c.address, c.difficulty,
                   c.case_type, c.total_visits, c.max_engineers,
                   v.plate_number, v.vehicle_type, v.seats,
                   b.name AS branch_name, b.code AS branch_code
            FROM schedules s
            JOIN cases c ON s.case_id = c.id
            JOIN branches b ON c.branch_id = b.id
            LEFT JOIN vehicles v ON s.vehicle_id = v.id
            JOIN schedule_engineers se ON s.id = se.schedule_id
            WHERE se.user_id = ?
              AND s.schedule_date BETWEEN ? AND ?
            ORDER BY s.schedule_date ASC, s.created_at ASC
        ");
        $stmt->execute(array($userId, $startDate, $endDate));
        $schedules = $stmt->fetchAll();

        foreach ($schedules as &$s) {
            $engStmt = $this->db->prepare('
                SELECT se.*, u.real_name, u.phone, u.is_engineer
                FROM schedule_engineers se
                JOIN users u ON se.user_id = u.id
                WHERE se.schedule_id = ?
                ORDER BY se.is_lead DESC, u.real_name
            ');
            $engStmt->execute(array($s['id']));
            $s['engineers'] = $engStmt->fetchAll();
        }
        return $schedules;
    }

    /**
     * 刪除排工（含關聯資料）
     */
    public function delete(int $id): void
    {
        // 先刪除關聯的材料使用紀錄 (work_logs -> material_usage)
        $this->db->prepare('
            DELETE mu FROM material_usage mu
            INNER JOIN work_logs wl ON mu.work_log_id = wl.id
            WHERE wl.schedule_id = ?
        ')->execute(array($id));

        // 刪除施工回報照片
        $this->db->prepare('
            DELETE wp FROM worklog_photos wp
            INNER JOIN work_logs wl ON wp.work_log_id = wl.id
            WHERE wl.schedule_id = ?
        ')->execute(array($id));

        // 刪除施工回報
        $this->db->prepare('DELETE FROM work_logs WHERE schedule_id = ?')->execute(array($id));

        // 刪除排工人員
        $this->db->prepare('DELETE FROM schedule_engineers WHERE schedule_id = ?')->execute(array($id));

        // 刪除排工點工人員
        $this->db->prepare('DELETE FROM schedule_dispatch_workers WHERE schedule_id = ?')->execute(array($id));

        // 刪除點工出勤預排紀錄
        try {
            $this->db->prepare("DELETE FROM dispatch_attendance WHERE schedule_id = ? AND status = 'scheduled'")->execute(array($id));
        } catch (Exception $e) {
            // schedule_id 欄位可能不存在，忽略
        }

        // 清除跨點點工的 schedule_id 參考
        $this->db->prepare('UPDATE inter_branch_support SET schedule_id = NULL WHERE schedule_id = ?')->execute(array($id));

        // 刪除施工檢查紀錄
        try {
            $this->db->prepare('DELETE FROM schedule_visit_check WHERE schedule_id = ?')->execute(array($id));
        } catch (Exception $e) {
            // schedule_id 欄位可能不存在，改用 case_id 清理
        }

        // 最後刪除排工本身
        $this->db->prepare('DELETE FROM schedules WHERE id = ?')->execute(array($id));
    }

    // =========================================================
    //  智慧排工 (Smart Scheduling)
    // =========================================================

    /**
     * 智慧排工推薦 - 主入口
     * @return array  Top 3 recommendations
     */
    public function getSmartRecommendations($caseId, array $branchIds, $days = 14)
    {
        // 1. 載入案件資料
        $caseStmt = $this->db->prepare('
            SELECT c.*, b.name AS branch_name
            FROM cases c JOIN branches b ON c.branch_id = b.id
            WHERE c.id = ?
        ');
        $caseStmt->execute([$caseId]);
        $case = $caseStmt->fetch();
        if (!$case) return [];

        $maxEng = (int)($case['max_engineers'] ?: 4);
        $visitNumber = (int)($case['current_visit'] ?: 1);

        // 案件預估工時
        $caseHours = (float)($case['estimated_hours'] ?: 0);
        if ($caseHours <= 0) {
            $caseHours = self::DEFAULT_ESTIMATED_HOURS;
        }

        // 2. 載入所需技能
        $reqStmt = $this->db->prepare('
            SELECT crs.skill_id, crs.min_proficiency, s.name AS skill_name
            FROM case_required_skills crs
            JOIN skills s ON crs.skill_id = s.id
            WHERE crs.case_id = ?
        ');
        $reqStmt->execute([$caseId]);
        $requiredSkills = $reqStmt->fetchAll();

        // 3. 載入所有工程師 + 技能（批次）
        $ph = implode(',', array_fill(0, count($branchIds), '?'));
        $engStmt = $this->db->prepare("
            SELECT u.id, u.real_name, u.branch_id, u.holiday_availability, u.night_availability,
                   u.engineer_level, u.can_lead, u.repair_priority, u.mentor_id, u.mentor_start_date
            FROM users u
            WHERE u.branch_id IN ($ph) AND u.is_engineer = 1 AND u.is_active = 1
            ORDER BY u.real_name
        ");
        $engStmt->execute($branchIds);
        $allEngineers = $engStmt->fetchAll();

        if (empty($allEngineers)) return [];

        // 批次載入技能
        $engIds = array_column($allEngineers, 'id');
        $engIdPh = implode(',', array_fill(0, count($engIds), '?'));
        $skillStmt = $this->db->prepare("
            SELECT user_id, skill_id, proficiency FROM user_skills WHERE user_id IN ($engIdPh)
        ");
        $skillStmt->execute($engIds);
        $skillRows = $skillStmt->fetchAll();

        // 建立技能查詢表 [user_id][skill_id] = proficiency
        $skillMap = [];
        foreach ($skillRows as $sr) {
            $skillMap[$sr['user_id']][$sr['skill_id']] = (int)$sr['proficiency'];
        }

        // 為每位工程師計算技能匹配分
        foreach ($allEngineers as &$eng) {
            $eng['skill_score'] = 0;
            $eng['skills_met'] = 0;
            $eng['skills'] = isset($skillMap[$eng['id']]) ? $skillMap[$eng['id']] : [];
            foreach ($requiredSkills as $rs) {
                $prof = isset($eng['skills'][$rs['skill_id']]) ? $eng['skills'][$rs['skill_id']] : 0;
                if ($prof >= $rs['min_proficiency']) {
                    $eng['skills_met']++;
                    $eng['skill_score'] += ($prof - $rs['min_proficiency'] + 1);
                }
            }
        }
        unset($eng);

        // 3b. 查修案件：repair_priority 工程師加分（影響排序，不影響評分）
        $isRepairCase = in_array($case['case_type'], array('repair', 'old_repair', 'new_repair'));
        if ($isRepairCase) {
            foreach ($allEngineers as &$eng) {
                if (!empty($eng['repair_priority'])) {
                    $eng['skill_score'] += 10;
                }
            }
            unset($eng);
        }

        // 4. 載入人員默契
        $pairs = $this->getPairCompatibility($branchIds);

        // 5. 載入上次施工人員
        $prevTeam = [];
        if ($visitNumber > 1) {
            $prevTeam = $this->getPreviousVisitTeam($caseId, $visitNumber);
        }

        // 6. 批次載入日期範圍內的排工和請假
        $startDate = date('Y-m-d', strtotime('+1 day'));
        $endDate = date('Y-m-d', strtotime('+' . $days . ' days'));

        // 已排工人員工時 (by date) — 用 estimated_hours 計算每人每日已用時數
        $defaultHours = self::DEFAULT_ESTIMATED_HOURS;
        $busyStmt = $this->db->prepare("
            SELECT s.schedule_date, se.user_id,
                   SUM(COALESCE(NULLIF(c.estimated_hours, 0), {$defaultHours})) AS hours_used
            FROM schedule_engineers se
            JOIN schedules s ON se.schedule_id = s.id
            JOIN cases c ON s.case_id = c.id
            WHERE s.schedule_date BETWEEN ? AND ? AND s.status != 'cancelled'
            GROUP BY s.schedule_date, se.user_id
        ");
        $busyStmt->execute([$startDate, $endDate]);
        $hoursByDate = array();
        foreach ($busyStmt->fetchAll() as $row) {
            $hoursByDate[$row['schedule_date']][$row['user_id']] = (float)$row['hours_used'];
        }

        // 已排車輛 (by date)
        $vBusyStmt = $this->db->prepare("
            SELECT schedule_date, vehicle_id
            FROM schedules
            WHERE schedule_date BETWEEN ? AND ? AND status != 'cancelled' AND vehicle_id IS NOT NULL
        ");
        $vBusyStmt->execute([$startDate, $endDate]);
        $vehicleBusyByDate = [];
        foreach ($vBusyStmt->fetchAll() as $row) {
            $vehicleBusyByDate[$row['schedule_date']][] = $row['vehicle_id'];
        }

        // 請假人員 (by date range)
        $leaveStmt = $this->db->prepare("
            SELECT user_id, start_date, end_date FROM leaves
            WHERE status = 'approved' AND start_date <= ? AND end_date >= ?
        ");
        $leaveStmt->execute([$endDate, $startDate]);
        $leaveRows = $leaveStmt->fetchAll();

        // 車輛清單
        $vehicles = $this->getAvailableVehicles($startDate, $branchIds);

        // 週工作量（整段期間）
        $weeklyStmt = $this->db->prepare("
            SELECT se.user_id, COUNT(DISTINCT s.schedule_date) AS cnt
            FROM schedule_engineers se
            JOIN schedules s ON se.schedule_id = s.id
            WHERE s.schedule_date BETWEEN ? AND ? AND s.status != 'cancelled'
            GROUP BY se.user_id
        ");
        // 取前兩週的範圍
        $weekStart = date('Y-m-d', strtotime('monday this week', strtotime($startDate)));
        $weekEnd = date('Y-m-d', strtotime('+14 days', strtotime($weekStart)));
        $weeklyStmt->execute([$weekStart, $weekEnd]);
        $weeklyLoad = [];
        foreach ($weeklyStmt->fetchAll() as $wl) {
            $weeklyLoad[$wl['user_id']] = (int)$wl['cnt'];
        }

        // 7. 逐日評估
        $allCandidates = [];

        for ($d = 0; $d < $days; $d++) {
            $date = date('Y-m-d', strtotime('+' . ($d + 1) . ' days'));
            $dow = (int)date('w', strtotime($date)); // 0=Sun, 6=Sat

            // 跳過週日
            if ($dow === 0) continue;

            // 該日請假人員
            $onLeave = [];
            foreach ($leaveRows as $lv) {
                if ($date >= $lv['start_date'] && $date <= $lv['end_date']) {
                    $onLeave[] = $lv['user_id'];
                }
            }

            // 該日工時使用情況
            $hoursToday = isset($hoursByDate[$date]) ? $hoursByDate[$date] : array();

            // 篩選可用工程師（依剩餘工時）
            $available = [];
            foreach ($allEngineers as $eng) {
                if (in_array($eng['id'], $onLeave)) continue;
                $usedHours = isset($hoursToday[$eng['id']]) ? $hoursToday[$eng['id']] : 0;
                $remainingHours = self::DAILY_HOURS_CAPACITY - $usedHours;
                if ($remainingHours < $caseHours) continue;
                $eng['hours_used_today'] = $usedHours;
                $eng['remaining_hours'] = $remainingHours;
                $available[] = $eng;
            }

            if (empty($available)) continue;

            // 該日可用車輛
            $busyVehicles = isset($vehicleBusyByDate[$date]) ? $vehicleBusyByDate[$date] : [];
            $availVehicles = [];
            foreach ($vehicles as $v) {
                if (!in_array($v['id'], $busyVehicles)) {
                    $availVehicles[] = $v;
                }
            }

            // 建立候選團隊
            $difficulty = (int)($case['difficulty'] ?: 3);
            $teams = $this->buildTeamCandidates($available, $requiredSkills, $maxEng, $prevTeam, $difficulty);

            // 為每個團隊 + 車輛組合評分
            foreach ($teams as $team) {
                $teamSize = count($team);

                // 找最佳車輛
                $bestVehicle = null;
                $bestVehicleScore = -1;
                foreach ($availVehicles as $v) {
                    $seats = (int)$v['seats'];
                    if ($seats < $teamSize) continue; // 坐不下
                    $vScore = 1 - abs($seats - $teamSize) / max($seats, 1);
                    if ($vScore > $bestVehicleScore) {
                        $bestVehicleScore = $vScore;
                        $bestVehicle = $v;
                    }
                }

                $score = $this->scoreRecommendation(
                    $team, $requiredSkills, $pairs, $prevTeam, $weeklyLoad,
                    $bestVehicle, $teamSize
                );

                $teamIds = [];
                $teamNames = [];
                foreach ($team as $eng) {
                    $teamIds[] = $eng['id'];
                    $teamNames[] = $eng['real_name'];
                }

                // 選主工程師：優先 can_lead=1 且非 probation，否則 fallback 最高技能分
                $leadId = null;
                foreach ($team as $eng) {
                    if (!empty($eng['can_lead']) && $eng['engineer_level'] !== 'probation') {
                        $leadId = $eng['id'];
                        break;
                    }
                }
                if ($leadId === null) {
                    // fallback: 非 probation 中技能分最高者
                    foreach ($team as $eng) {
                        if ($eng['engineer_level'] !== 'probation') {
                            $leadId = $eng['id'];
                            break;
                        }
                    }
                }
                if ($leadId === null) {
                    $leadId = $team[0]['id'];
                }

                // 團隊工時資訊
                $teamHours = array();
                foreach ($team as $eng) {
                    $teamHours[$eng['id']] = array(
                        'hours_used' => isset($eng['hours_used_today']) ? $eng['hours_used_today'] : 0,
                        'remaining'  => isset($eng['remaining_hours']) ? $eng['remaining_hours'] : self::DAILY_HOURS_CAPACITY,
                    );
                }

                $allCandidates[] = [
                    'date'           => $date,
                    'date_label'     => date('m/d', strtotime($date)) . ' (' . $this->weekdayLabel($dow) . ')',
                    'is_weekend'     => ($dow === 6),
                    'engineers'      => $team,
                    'engineer_ids'   => $teamIds,
                    'engineer_names' => $teamNames,
                    'lead_id'        => $leadId,
                    'vehicle'        => $bestVehicle,
                    'visit_number'   => $visitNumber,
                    'score'          => $score['total'],
                    'breakdown'      => $score['breakdown'],
                    'case_hours'     => $caseHours,
                    'team_hours'     => $teamHours,
                ];
            }
        }

        // 8. 為每個方案推薦點工人員
        $allCandidates = $this->addDispatchRecommendations($allCandidates, $requiredSkills, $branchIds);

        // 9. 排序取前 3
        usort($allCandidates, function ($a, $b) {
            return $b['score'] - $a['score'];
        });

        return array_slice($allCandidates, 0, 3);
    }

    /**
     * 為推薦方案加入點工人員推薦（不影響原有評分）
     */
    private function addDispatchRecommendations(array $candidates, array $requiredSkills, array $branchIds)
    {
        if (empty($candidates)) return $candidates;

        // 收集所有推薦日期
        $allDates = array();
        foreach ($candidates as $c) { $allDates[$c['date']] = true; }
        $allDateKeys = array_keys($allDates);

        // 載入在推薦日期有登錄可上工的點工人員
        if (empty($allDateKeys)) {
            foreach ($candidates as &$c) { $c['dispatch_workers'] = array(); }
            return $candidates;
        }
        $datePh = implode(',', array_fill(0, count($allDateKeys), '?'));
        $dwStmt = $this->db->prepare("
            SELECT DISTINCT dw.id, dw.name, dw.specialty, dw.daily_rate
            FROM dispatch_workers dw
            WHERE dw.is_active = 1
              AND (
                dw.id IN (SELECT dispatch_worker_id FROM dispatch_worker_availability WHERE available_date IN ($datePh))
                OR dw.id IN (SELECT dispatch_worker_id FROM dispatch_attendance WHERE attendance_date IN ($datePh) AND status = 'present')
              )
            ORDER BY dw.name
        ");
        $dwStmt->execute(array_merge($allDateKeys, $allDateKeys));
        $allWorkers = $dwStmt->fetchAll(PDO::FETCH_ASSOC);

        // 建立每日可用點工索引（可上工登錄 + 出勤登錄都算）
        $availByDate = array();
        $avStmt = $this->db->prepare("SELECT dispatch_worker_id, available_date FROM dispatch_worker_availability WHERE available_date IN ($datePh)");
        $avStmt->execute($allDateKeys);
        foreach ($avStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $availByDate[$row['available_date']][(int)$row['dispatch_worker_id']] = true;
        }
        $atStmt = $this->db->prepare("SELECT dispatch_worker_id, attendance_date FROM dispatch_attendance WHERE attendance_date IN ($datePh) AND status = 'present'");
        $atStmt->execute($allDateKeys);
        foreach ($atStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $availByDate[$row['attendance_date']][(int)$row['dispatch_worker_id']] = true;
        }
        if (empty($allWorkers)) {
            foreach ($candidates as &$c) { $c['dispatch_workers'] = array(); }
            return $candidates;
        }

        // 載入點工人員技能
        $dwSkills = array();
        $dwsStmt = $this->db->query("SELECT dispatch_worker_id, skill_id, proficiency FROM dispatch_worker_skills WHERE proficiency > 0");
        foreach ($dwsStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $dwSkills[(int)$row['dispatch_worker_id']][(int)$row['skill_id']] = (int)$row['proficiency'];
        }

        // 批次查詢已排工/出勤的點工
        $dates = $allDateKeys;

        $busyWorkers = array(); // date => [worker_id => true]
        if ($dates) {
            $ph = implode(',', array_fill(0, count($dates), '?'));
            // 已排工
            $bStmt = $this->db->prepare("
                SELECT s.schedule_date, sdw.dispatch_worker_id
                FROM schedule_dispatch_workers sdw
                JOIN schedules s ON sdw.schedule_id = s.id
                WHERE s.schedule_date IN ($ph) AND s.status != 'cancelled'
            ");
            $bStmt->execute($dates);
            foreach ($bStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $busyWorkers[$row['schedule_date']][(int)$row['dispatch_worker_id']] = true;
            }
            // 已有出勤記錄（present/scheduled）
            $aStmt = $this->db->prepare("
                SELECT attendance_date, dispatch_worker_id
                FROM dispatch_attendance
                WHERE attendance_date IN ($ph) AND status = 'present'
            ");
            $aStmt->execute($dates);
            foreach ($aStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $busyWorkers[$row['attendance_date']][(int)$row['dispatch_worker_id']] = true;
            }
        }

        // 載入點工-工程師配對分數
        $dwPairs = array();
        $dpStmt = $this->db->query("SELECT dispatch_worker_id, user_id, compatibility FROM dispatch_engineer_pairs");
        foreach ($dpStmt->fetchAll(PDO::FETCH_ASSOC) as $dp) {
            $dwPairs[(int)$dp['dispatch_worker_id']][(int)$dp['user_id']] = (int)$dp['compatibility'];
        }

        // 為每個方案推薦點工
        foreach ($candidates as &$c) {
            $date = $c['date'];
            $busy = isset($busyWorkers[$date]) ? $busyWorkers[$date] : array();
            $teamEngIds = isset($c['engineer_ids']) ? $c['engineer_ids'] : array();

            $recommended = array();
            $dateAvail = isset($availByDate[$date]) ? $availByDate[$date] : array();
            foreach ($allWorkers as $w) {
                $wid = (int)$w['id'];
                if (!isset($dateAvail[$wid])) continue; // 當天未登錄可上工
                if (isset($busy[$wid])) continue; // 當天已被排工

                // 技能匹配度計算
                $skillMatch = 0;
                $skillsMatched = 0;
                $totalRequired = count($requiredSkills);
                $workerSkills = isset($dwSkills[$wid]) ? $dwSkills[$wid] : array();

                if ($totalRequired > 0) {
                    foreach ($requiredSkills as $rs) {
                        $sid = (int)$rs['skill_id'];
                        $minProf = (int)$rs['min_proficiency'];
                        if (isset($workerSkills[$sid]) && $workerSkills[$sid] >= $minProf) {
                            $skillsMatched++;
                            $skillMatch += $workerSkills[$sid];
                        }
                    }
                }

                // 與該方案工程師的平均配對分數
                $pairScore = 0;
                $pairCount = 0;
                $workerPairs = isset($dwPairs[$wid]) ? $dwPairs[$wid] : array();
                foreach ($teamEngIds as $engId) {
                    if (isset($workerPairs[$engId])) {
                        $pairScore += $workerPairs[$engId];
                        $pairCount++;
                    } else {
                        $pairScore += 3; // 預設 3 分
                        $pairCount++;
                    }
                }
                $avgPair = $pairCount > 0 ? round($pairScore / $pairCount, 1) : 3;

                $recommended[] = array(
                    'id' => $wid,
                    'name' => $w['name'],
                    'specialty' => $w['specialty'],
                    'daily_rate' => (int)$w['daily_rate'],
                    'skills_matched' => $skillsMatched,
                    'total_required' => $totalRequired,
                    'skill_match' => $skillMatch,
                    'match_pct' => $totalRequired > 0 ? round($skillsMatched / $totalRequired * 100) : 0,
                    'pair_score' => $avgPair,
                );
            }

            // 排序：技能匹配度 + 配對分數
            usort($recommended, function ($a, $b) {
                if ($b['skills_matched'] !== $a['skills_matched']) return $b['skills_matched'] - $a['skills_matched'];
                if ($b['pair_score'] != $a['pair_score']) return $b['pair_score'] > $a['pair_score'] ? 1 : -1;
                return $b['skill_match'] - $a['skill_match'];
            });

            $c['dispatch_workers'] = array_slice($recommended, 0, 5); // 最多推薦5人
        }

        return $candidates;
    }

    /**
     * 貪心法建立候選團隊
     */
    private function buildTeamCandidates(array $available, array $requiredSkills, $maxEng, array $prevTeam, $difficulty = 3)
    {
        $candidates = array();
        $teamSize = min($maxEng, count($available));
        if ($teamSize < 1) return array();
        // 若無所需技能，team size 至少 2
        if (empty($requiredSkills) && $teamSize < 2 && count($available) >= 2) {
            $teamSize = 2;
        }

        // 分離正式工程師與試用人員
        $regularEng = array();
        $probationEng = array();
        foreach ($available as $eng) {
            if (isset($eng['engineer_level']) && $eng['engineer_level'] === 'probation') {
                $probationEng[] = $eng;
            } else {
                $regularEng[] = $eng;
            }
        }

        // 按技能分排序（正式工程師）
        usort($regularEng, function ($a, $b) {
            return $b['skill_score'] - $a['skill_score'];
        });
        // 試用也排序
        usort($probationEng, function ($a, $b) {
            return $b['skill_score'] - $a['skill_score'];
        });
        // 合併（正式在前，試用在後）用於候選組隊
        $sorted = array_merge($regularEng, $probationEng);

        // 候選 1: 上次團隊（若全員可用）
        if (!empty($prevTeam)) {
            $availIds = array_column($sorted, 'id');
            $prevAvail = array();
            foreach ($prevTeam as $pid) {
                if (in_array($pid, $availIds)) {
                    foreach ($sorted as $eng) {
                        if ($eng['id'] == $pid) {
                            $prevAvail[] = $eng;
                            break;
                        }
                    }
                }
            }
            if (count($prevAvail) >= 2) {
                $candidates[] = array_slice($prevAvail, 0, $teamSize);
            }
        }

        // 候選 2: 技能分最高 N 人（正式工程師優先）
        $topTeam = array_slice($sorted, 0, $teamSize);
        if (!empty($topTeam) && !$this->isSameTeam($topTeam, $candidates)) {
            $candidates[] = $topTeam;
        }

        // 候選 3-5: 替換最低分成員
        if (count($sorted) > $teamSize) {
            for ($swap = 0; $swap < min(3, count($sorted) - $teamSize); $swap++) {
                $variant = $topTeam;
                $variant[$teamSize - 1] = $sorted[$teamSize + $swap];
                if (!$this->isSameTeam($variant, $candidates)) {
                    $candidates[] = $variant;
                }
                if (count($candidates) >= 5) break;
            }
        }

        // 師徒組合：師傅+學徒一起（90天內）
        foreach ($probationEng as $apprentice) {
            if (empty($apprentice['mentor_id']) || empty($apprentice['mentor_start_date'])) continue;
            $daysSince = (int)((strtotime(date('Y-m-d')) - strtotime($apprentice['mentor_start_date'])) / 86400);
            if ($daysSince > 90) continue;
            // 找師傅
            $mentor = null;
            foreach ($regularEng as $eng) {
                if ($eng['id'] == $apprentice['mentor_id']) { $mentor = $eng; break; }
            }
            if (!$mentor) continue;
            // 組隊：師傅 + 其他正式 + 學徒（最後）
            $mentorTeam = array($mentor);
            $remaining = $teamSize - 2;
            $filled = 0;
            foreach ($regularEng as $eng) {
                if ($eng['id'] == $mentor['id']) continue;
                $mentorTeam[] = $eng;
                $filled++;
                if ($filled >= $remaining) break;
            }
            $mentorTeam[] = $apprentice;
            if (count($mentorTeam) >= 2 && !$this->isSameTeam($mentorTeam, $candidates)) {
                $candidates[] = $mentorTeam;
            }
            if (count($candidates) >= 5) break;
        }

        // 簡單案件（difficulty ≤ 2）：可多排 1 位試用人員練習
        if ($difficulty <= 2 && !empty($probationEng) && $teamSize < count($sorted)) {
            foreach ($candidates as $baseTeam) {
                // 檢查這個團隊是否已有 probation
                $hasProbation = false;
                foreach ($baseTeam as $eng) {
                    if (isset($eng['engineer_level']) && $eng['engineer_level'] === 'probation') { $hasProbation = true; break; }
                }
                if ($hasProbation) continue;
                // 加一位 probation
                $trainTeam = $baseTeam;
                $trainTeam[] = $probationEng[0];
                if (!$this->isSameTeam($trainTeam, $candidates)) {
                    $candidates[] = $trainTeam;
                }
                if (count($candidates) >= 7) break;
            }
        }

        return $candidates;
    }

    /**
     * 評分
     */
    private function scoreRecommendation(array $team, array $requiredSkills, array $pairs, array $prevTeam, array $weeklyLoad, $vehicle, $teamSize)
    {
        $breakdown = [
            'skill'      => 0, // /40
            'pair'       => 0, // /20
            'load'       => 0, // /15
            'vehicle'    => 0, // /10
            'continuity' => 0, // /15
        ];

        // ---- 技能匹配 (40) ----
        if (empty($requiredSkills)) {
            $breakdown['skill'] = 40;
        } else {
            $totalSkillScore = 0;
            $maxPossible = 0;
            foreach ($requiredSkills as $rs) {
                $maxPossible += 5; // max proficiency = 5
                $bestProf = 0;
                foreach ($team as $eng) {
                    $prof = isset($eng['skills'][$rs['skill_id']]) ? $eng['skills'][$rs['skill_id']] : 0;
                    if ($prof > $bestProf) $bestProf = $prof;
                }
                if ($bestProf >= $rs['min_proficiency']) {
                    $totalSkillScore += $bestProf;
                }
                // 未達門檻扣分
            }
            $breakdown['skill'] = $maxPossible > 0 ? round($totalSkillScore / $maxPossible * 40) : 40;
        }

        // ---- 人員默契 (20) ----
        if (count($team) <= 1) {
            $breakdown['pair'] = 20;
        } else {
            $pairSum = 0;
            $pairCount = 0;
            for ($i = 0; $i < count($team); $i++) {
                for ($j = $i + 1; $j < count($team); $j++) {
                    $a = min($team[$i]['id'], $team[$j]['id']);
                    $b = max($team[$i]['id'], $team[$j]['id']);
                    $key = $a . '-' . $b;

                    // 師徒配對（90天內）自動給最高分 5
                    $isMentorPair = false;
                    if (!empty($team[$i]['mentor_id']) && $team[$i]['mentor_id'] == $team[$j]['id'] && !empty($team[$i]['mentor_start_date'])) {
                        $daysSince = (int)((strtotime($date) - strtotime($team[$i]['mentor_start_date'])) / 86400);
                        if ($daysSince <= 90) $isMentorPair = true;
                    }
                    if (!empty($team[$j]['mentor_id']) && $team[$j]['mentor_id'] == $team[$i]['id'] && !empty($team[$j]['mentor_start_date'])) {
                        $daysSince = (int)((strtotime($date) - strtotime($team[$j]['mentor_start_date'])) / 86400);
                        if ($daysSince <= 90) $isMentorPair = true;
                    }

                    $pairSum += $isMentorPair ? 5 : (isset($pairs[$key]) ? $pairs[$key] : 3);
                    $pairCount++;
                }
            }
            $avg = $pairCount > 0 ? $pairSum / $pairCount : 3;
            $breakdown['pair'] = round($avg / 5 * 20);
        }

        // ---- 週工作量 + 日利用率 (15) ----
        $loadSum = 0;
        $dailyUsedSum = 0;
        foreach ($team as $eng) {
            $loadSum += isset($weeklyLoad[$eng['id']]) ? $weeklyLoad[$eng['id']] : 0;
            $dailyUsedSum += isset($eng['hours_used_today']) ? $eng['hours_used_today'] : 0;
        }
        $avgLoad = count($team) > 0 ? $loadSum / count($team) : 0;
        $avgDailyUsed = count($team) > 0 ? $dailyUsedSum / count($team) : 0;
        $weekScore = round(max(0, (1 - $avgLoad / 5)) * 10);
        $dailyScore = round(max(0, (1 - $avgDailyUsed / self::DAILY_HOURS_CAPACITY)) * 5);
        $breakdown['load'] = $weekScore + $dailyScore;

        // ---- 車輛適配 (10) ----
        if ($vehicle) {
            $seats = (int)$vehicle['seats'];
            $fit = 1 - abs($seats - $teamSize) / max($seats, 1);
            $breakdown['vehicle'] = round($fit * 10);
        } else {
            $breakdown['vehicle'] = 0;
        }

        // ---- 人員連續 (15) ----
        if (empty($prevTeam)) {
            $breakdown['continuity'] = 15; // 首次施工，滿分
        } else {
            $teamIds = array_column($team, 'id');
            $overlap = count(array_intersect($teamIds, $prevTeam));
            $ratio = count($prevTeam) > 0 ? $overlap / count($prevTeam) : 0;
            $breakdown['continuity'] = round($ratio * 15);
        }

        $total = 0;
        foreach ($breakdown as $v) {
            $total += $v;
        }

        return ['total' => $total, 'breakdown' => $breakdown];
    }

    /**
     * 取得上次施工人員
     */
    private function getPreviousVisitTeam($caseId, $visitNumber)
    {
        $stmt = $this->db->prepare('
            SELECT se.user_id FROM schedule_engineers se
            JOIN schedules s ON se.schedule_id = s.id
            WHERE s.case_id = ? AND s.visit_number = ? AND s.status != ?
        ');
        $stmt->execute([$caseId, $visitNumber - 1, 'cancelled']);
        return array_column($stmt->fetchAll(), 'user_id');
    }

    /**
     * 取得人員默契查詢表
     */
    private function getPairCompatibility(array $branchIds)
    {
        $ph = implode(',', array_fill(0, count($branchIds), '?'));
        $stmt = $this->db->prepare("
            SELECT ep.user_a_id, ep.user_b_id, ep.compatibility
            FROM engineer_pairs ep
            JOIN users ua ON ep.user_a_id = ua.id
            JOIN users ub ON ep.user_b_id = ub.id
            WHERE ua.branch_id IN ($ph)
        ");
        $stmt->execute($branchIds);
        $map = [];
        foreach ($stmt->fetchAll() as $row) {
            $key = $row['user_a_id'] . '-' . $row['user_b_id'];
            $map[$key] = (int)$row['compatibility'];
        }
        return $map;
    }

    /**
     * 檢查團隊是否已在候選列表中
     */
    private function isSameTeam(array $team, array $candidates)
    {
        $ids = array_column($team, 'id');
        sort($ids);
        foreach ($candidates as $c) {
            $cIds = array_column($c, 'id');
            sort($cIds);
            if ($ids === $cIds) return true;
        }
        return false;
    }

    /**
     * 星期幾中文標籤
     */
    private function weekdayLabel($dow)
    {
        $labels = ['日', '一', '二', '三', '四', '五', '六'];
        return isset($labels[$dow]) ? $labels[$dow] : '';
    }

    // ===== 排工日設定 =====

    /**
     * 取得日期範圍內的排工日設定
     * @return array 以日期為 key 的設定陣列
     */
    public function getDaySettings($startDate, $endDate)
    {
        $stmt = $this->db->prepare('
            SELECT * FROM schedule_day_settings
            WHERE setting_date BETWEEN ? AND ?
        ');
        $stmt->execute(array($startDate, $endDate));
        $rows = $stmt->fetchAll();
        $result = array();
        foreach ($rows as $r) {
            $result[$r['setting_date']] = $r;
        }
        return $result;
    }

    /**
     * 判斷某日是否可排工
     * 規則：星期日預設不可排，其他預設可排；有設定記錄則依 is_open 判斷
     */
    public function isDayOpen($dateStr, $daySettings = array())
    {
        if (isset($daySettings[$dateStr])) {
            return (bool)$daySettings[$dateStr]['is_open'];
        }
        // 預設規則：星期日不可排
        $dow = (int)date('w', strtotime($dateStr));
        return $dow !== 0; // 0=Sunday
    }

    /**
     * 取得某日的容量限制
     * @return array ['max_teams' => int|null, 'max_engineers' => int|null]
     */
    public function getDayCapacityLimit($dateStr, $daySettings = array())
    {
        if (isset($daySettings[$dateStr])) {
            return array(
                'max_teams' => $daySettings[$dateStr]['max_teams'],
                'max_engineers' => $daySettings[$dateStr]['max_engineers'],
            );
        }
        return array('max_teams' => null, 'max_engineers' => null);
    }

    /**
     * 更新或新增某日設定
     */
    public function saveDaySetting($dateStr, $data)
    {
        // 檢查是否已有設定
        $stmt = $this->db->prepare('SELECT id FROM schedule_day_settings WHERE setting_date = ?');
        $stmt->execute(array($dateStr));
        $existing = $stmt->fetch();

        $isOpen = isset($data['is_open']) ? (int)$data['is_open'] : 1;
        $maxTeams = (isset($data['max_teams']) && $data['max_teams'] !== '') ? (int)$data['max_teams'] : null;
        $maxEngineers = (isset($data['max_engineers']) && $data['max_engineers'] !== '') ? (int)$data['max_engineers'] : null;
        $note = isset($data['note']) ? $data['note'] : null;
        $updatedBy = isset($data['updated_by']) ? (int)$data['updated_by'] : null;

        if ($existing) {
            $stmt = $this->db->prepare('
                UPDATE schedule_day_settings
                SET is_open = ?, max_teams = ?, max_engineers = ?, note = ?, updated_by = ?
                WHERE setting_date = ?
            ');
            $stmt->execute(array($isOpen, $maxTeams, $maxEngineers, $note, $updatedBy, $dateStr));
        } else {
            $stmt = $this->db->prepare('
                INSERT INTO schedule_day_settings (setting_date, is_open, max_teams, max_engineers, note, updated_by)
                VALUES (?, ?, ?, ?, ?, ?)
            ');
            $stmt->execute(array($dateStr, $isOpen, $maxTeams, $maxEngineers, $note, $updatedBy));
        }
    }

    /**
     * 取得某日已排的組數（排工筆數，排除取消的）
     */
    public function getDayTeamCount($dateStr, array $branchIds)
    {
        $ph = implode(',', array_fill(0, count($branchIds), '?'));
        $params = array_merge(array($dateStr), $branchIds);
        $stmt = $this->db->prepare("
            SELECT COUNT(*) AS cnt
            FROM schedules s
            JOIN cases c ON s.case_id = c.id
            WHERE s.schedule_date = ?
              AND c.branch_id IN ($ph)
              AND s.status != 'cancelled'
        ");
        $stmt->execute($params);
        return (int)$stmt->fetch()['cnt'];
    }

    /**
     * 取得日期範圍內每日的組數
     */
    public function getDailyTeamCount(array $branchIds, $startDate, $endDate)
    {
        $ph = implode(',', array_fill(0, count($branchIds), '?'));
        $params = array_merge($branchIds, array($startDate, $endDate));
        $stmt = $this->db->prepare("
            SELECT s.schedule_date, COUNT(*) AS team_count
            FROM schedules s
            JOIN cases c ON s.case_id = c.id
            WHERE c.branch_id IN ($ph)
              AND s.schedule_date BETWEEN ? AND ?
              AND s.status != 'cancelled'
            GROUP BY s.schedule_date
        ");
        $stmt->execute($params);
        $result = array();
        foreach ($stmt->fetchAll() as $r) {
            $result[$r['schedule_date']] = (int)$r['team_count'];
        }
        return $result;
    }
}
