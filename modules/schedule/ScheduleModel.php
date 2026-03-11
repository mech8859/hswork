<?php
/**
 * 排工資料模型
 */
class ScheduleModel
{
    private PDO $db;

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
                   v.plate_number, v.vehicle_type, v.seats,
                   b.name AS branch_name, b.code AS branch_code
            FROM schedules s
            JOIN cases c ON s.case_id = c.id
            JOIN branches b ON c.branch_id = b.id
            LEFT JOIN vehicles v ON s.vehicle_id = v.id
            WHERE c.branch_id IN ($ph)
              AND s.schedule_date BETWEEN ? AND ?
            ORDER BY s.schedule_date ASC, s.created_at ASC
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

        return $schedule;
    }

    /**
     * 新增排工
     */
    public function create(array $data): int
    {
        $stmt = $this->db->prepare('
            INSERT INTO schedules (case_id, schedule_date, vehicle_id, visit_number, status, note, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([
            $data['case_id'],
            $data['schedule_date'],
            $data['vehicle_id'] ?: null,
            $data['visit_number'] ?? 1,
            $data['status'] ?? 'planned',
            $data['note'] ?? null,
            Auth::id(),
        ]);
        $scheduleId = (int)$this->db->lastInsertId();

        // 指派工程師
        if (!empty($data['engineer_ids'])) {
            $this->assignEngineers($scheduleId, $data['engineer_ids'], $data);
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
            UPDATE schedules SET schedule_date = ?, vehicle_id = ?, visit_number = ?, status = ?, note = ?
            WHERE id = ?
        ');
        $stmt->execute([
            $data['schedule_date'],
            $data['vehicle_id'] ?: null,
            $data['visit_number'] ?? 1,
            $data['status'] ?? 'planned',
            $data['note'] ?? null,
            $id,
        ]);

        // 重新指派工程師
        if (isset($data['engineer_ids'])) {
            $this->db->prepare('DELETE FROM schedule_engineers WHERE schedule_id = ?')->execute([$id]);
            $this->assignEngineers($id, $data['engineer_ids'], $data);
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

        // 取得當日已排工的人員
        $busyStmt = $this->db->prepare('
            SELECT se.user_id FROM schedule_engineers se
            JOIN schedules s ON se.schedule_id = s.id
            WHERE s.schedule_date = ? AND s.status != ?
        ');
        $busyStmt->execute([$date, 'cancelled']);
        $busyIds = array_column($busyStmt->fetchAll(), 'user_id');

        // 為每位工程師計算資訊
        foreach ($engineers as &$eng) {
            $eng['is_busy'] = in_array($eng['id'], $busyIds);

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
                   b.name AS branch_name
            FROM cases c
            JOIN branches b ON c.branch_id = b.id
            WHERE c.branch_id IN ($ph)
              AND c.status IN ('pending','ready','scheduled','in_progress')
            ORDER BY c.status ASC, c.updated_at DESC
        ");
        $stmt->execute($branchIds);
        return $stmt->fetchAll();
    }

    /**
     * 刪除排工
     */
    public function delete(int $id): void
    {
        $this->db->prepare('DELETE FROM schedules WHERE id = ?')->execute([$id]);
    }
}
