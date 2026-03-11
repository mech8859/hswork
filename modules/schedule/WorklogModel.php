<?php
/**
 * 施工回報資料模型
 */
class WorklogModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * 取得工程師今日排工
     */
    public function getTodaySchedules(int $userId): array
    {
        $stmt = $this->db->prepare('
            SELECT s.*, c.title AS case_title, c.case_number, c.address,
                   c.total_visits, v.plate_number,
                   wl.id AS worklog_id, wl.arrival_time, wl.departure_time, wl.work_description
            FROM schedule_engineers se
            JOIN schedules s ON se.schedule_id = s.id
            JOIN cases c ON s.case_id = c.id
            LEFT JOIN vehicles v ON s.vehicle_id = v.id
            LEFT JOIN work_logs wl ON wl.schedule_id = s.id AND wl.user_id = ?
            WHERE se.user_id = ? AND s.schedule_date = CURDATE()
              AND s.status != ?
            ORDER BY s.created_at ASC
        ');
        $stmt->execute([$userId, $userId, 'cancelled']);
        return $stmt->fetchAll();
    }

    /**
     * 取得工程師歷史回報
     */
    public function getHistory(int $userId, int $limit = 20): array
    {
        $stmt = $this->db->prepare("
            SELECT wl.*, s.schedule_date, c.title AS case_title, c.case_number
            FROM work_logs wl
            JOIN schedules s ON wl.schedule_id = s.id
            JOIN cases c ON s.case_id = c.id
            WHERE wl.user_id = ?
            ORDER BY s.schedule_date DESC, wl.created_at DESC
            LIMIT $limit
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    /**
     * 取得回報詳情
     */
    public function getWorklog(int $id): ?array
    {
        $stmt = $this->db->prepare('
            SELECT wl.*, s.schedule_date, s.case_id, s.visit_number,
                   c.title AS case_title, c.case_number, c.address
            FROM work_logs wl
            JOIN schedules s ON wl.schedule_id = s.id
            JOIN cases c ON s.case_id = c.id
            WHERE wl.id = ?
        ');
        $stmt->execute([$id]);
        $log = $stmt->fetch();
        if (!$log) return null;

        // 材料使用
        $stmt = $this->db->prepare('SELECT * FROM material_usage WHERE work_log_id = ? ORDER BY id');
        $stmt->execute([$id]);
        $log['materials'] = $stmt->fetchAll();

        return $log;
    }

    /**
     * 打卡 - 到場
     */
    public function checkIn(int $scheduleId, int $userId): int
    {
        // 檢查是否已有記錄
        $stmt = $this->db->prepare('SELECT id FROM work_logs WHERE schedule_id = ? AND user_id = ?');
        $stmt->execute([$scheduleId, $userId]);
        $existing = $stmt->fetchColumn();

        if ($existing) {
            $this->db->prepare('UPDATE work_logs SET arrival_time = NOW() WHERE id = ?')->execute([$existing]);
            return (int)$existing;
        }

        $stmt = $this->db->prepare('
            INSERT INTO work_logs (schedule_id, user_id, arrival_time) VALUES (?, ?, NOW())
        ');
        $stmt->execute([$scheduleId, $userId]);

        // 更新排工狀態
        $this->db->prepare("UPDATE schedules SET status = 'in_progress' WHERE id = ? AND status IN ('planned','confirmed')")
                 ->execute([$scheduleId]);

        // 更新案件狀態
        $caseStmt = $this->db->prepare('SELECT case_id FROM schedules WHERE id = ?');
        $caseStmt->execute([$scheduleId]);
        $caseId = $caseStmt->fetchColumn();
        if ($caseId) {
            $this->db->prepare("UPDATE cases SET status = 'in_progress' WHERE id = ? AND status IN ('pending','ready','scheduled')")
                     ->execute([$caseId]);
        }

        return (int)$this->db->lastInsertId();
    }

    /**
     * 打卡 - 離場
     */
    public function checkOut(int $worklogId): void
    {
        $this->db->prepare('UPDATE work_logs SET departure_time = NOW() WHERE id = ?')->execute([$worklogId]);
    }

    /**
     * 填寫施作回報
     */
    public function saveReport(int $worklogId, array $data): void
    {
        $stmt = $this->db->prepare('
            UPDATE work_logs SET
                work_description = ?,
                issues = ?,
                next_visit_needed = ?,
                next_visit_note = ?
            WHERE id = ?
        ');
        $stmt->execute([
            $data['work_description'] ?? null,
            $data['issues'] ?? null,
            $data['next_visit_needed'] ?? 0,
            $data['next_visit_note'] ?? null,
            $worklogId,
        ]);
    }

    /**
     * 儲存材料使用 (先清後存)
     */
    public function saveMaterials(int $worklogId, array $materials): void
    {
        $this->db->prepare('DELETE FROM material_usage WHERE work_log_id = ?')->execute([$worklogId]);
        $stmt = $this->db->prepare('
            INSERT INTO material_usage (work_log_id, material_type, material_name, unit, shipped_qty, used_qty, returned_qty, note)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ');
        foreach ($materials as $m) {
            if (empty($m['material_name'])) continue;
            $stmt->execute([
                $worklogId,
                $m['material_type'] ?? 'consumable',
                $m['material_name'],
                $m['unit'] ?? null,
                $m['shipped_qty'] ?? 0,
                $m['used_qty'] ?? 0,
                $m['returned_qty'] ?? 0,
                $m['material_note'] ?? null,
            ]);
        }
    }
}
