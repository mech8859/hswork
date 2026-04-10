<?php
/**
 * 施工回報資料模型
 */
class WorklogModel
{
    /** @var PDO */
    private $db;

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
     * 取得工程師歷史回報（時間軸式）
     */
    public function getHistory(int $userId, int $limit = 50): array
    {
        $stmt = $this->db->prepare("
            SELECT wl.*, s.schedule_date, s.visit_number,
                   c.title AS case_title, c.case_number, c.address, c.total_visits
            FROM work_logs wl
            JOIN schedules s ON wl.schedule_id = s.id
            JOIN cases c ON s.case_id = c.id
            WHERE wl.user_id = ?
            ORDER BY s.schedule_date DESC, wl.created_at DESC
            LIMIT $limit
        ");
        $stmt->execute([$userId]);
        $logs = $stmt->fetchAll();

        foreach ($logs as &$log) {
            $pStmt = $this->db->prepare('SELECT COUNT(*) FROM worklog_photos WHERE work_log_id = ?');
            $pStmt->execute(array($log['id']));
            $log['photo_count'] = (int)$pStmt->fetchColumn();
        }
        unset($log);

        return $logs;
    }

    /**
     * 取得案件的時間軸回報（所有工程師）
     */
    public function getCaseTimeline($caseId, $limit = 100)
    {
        $stmt = $this->db->prepare("
            SELECT wl.*, s.schedule_date, s.visit_number, u.real_name,
                   c.title AS case_title, c.case_number
            FROM work_logs wl
            JOIN schedules s ON wl.schedule_id = s.id
            JOIN cases c ON s.case_id = c.id
            JOIN users u ON wl.user_id = u.id
            WHERE s.case_id = ?
              AND wl.work_description IS NOT NULL AND wl.work_description != ''
            ORDER BY s.schedule_date DESC, wl.created_at DESC
            LIMIT $limit
        ");
        $stmt->execute(array($caseId));
        $logs = $stmt->fetchAll();

        foreach ($logs as &$log) {
            $pStmt = $this->db->prepare('SELECT * FROM worklog_photos WHERE work_log_id = ? ORDER BY id');
            $pStmt->execute(array($log['id']));
            $log['photos'] = $pStmt->fetchAll();

            $mStmt = $this->db->prepare('SELECT * FROM material_usage WHERE work_log_id = ? ORDER BY id');
            $mStmt->execute(array($log['id']));
            $log['materials'] = $mStmt->fetchAll();
        }
        unset($log);

        return $logs;
    }

    /**
     * 取得某排工的所有回報（含照片+材料）
     */
    public function getBySchedule($scheduleId)
    {
        $stmt = $this->db->prepare("
            SELECT wl.*, u.real_name
            FROM work_logs wl
            JOIN users u ON wl.user_id = u.id
            WHERE wl.schedule_id = ?
            ORDER BY wl.created_at ASC
        ");
        $stmt->execute(array($scheduleId));
        $logs = $stmt->fetchAll();

        foreach ($logs as &$log) {
            $pStmt = $this->db->prepare('SELECT * FROM worklog_photos WHERE work_log_id = ? ORDER BY uploaded_at');
            $pStmt->execute(array($log['id']));
            $log['photos'] = $pStmt->fetchAll();

            $mStmt = $this->db->prepare('SELECT mu.*, p.name AS product_name FROM material_usage mu LEFT JOIN products p ON mu.product_id = p.id WHERE mu.work_log_id = ?');
            $mStmt->execute(array($log['id']));
            $log['materials'] = $mStmt->fetchAll();
        }
        unset($log);
        return $logs;
    }

    /**
     * 取得回報詳情
     */
    public function getWorklog(int $id): ?array
    {
        $stmt = $this->db->prepare('
            SELECT wl.*, s.schedule_date, s.case_id, s.visit_number,
                   c.title AS case_title, c.case_number, c.address, c.total_visits
            FROM work_logs wl
            JOIN schedules s ON wl.schedule_id = s.id
            JOIN cases c ON s.case_id = c.id
            WHERE wl.id = ?
        ');
        $stmt->execute([$id]);
        $log = $stmt->fetch();
        if (!$log) return null;

        // 材料使用
        $stmt = $this->db->prepare('
            SELECT mu.*, p.name AS product_name
            FROM material_usage mu
            LEFT JOIN products p ON mu.product_id = p.id
            WHERE mu.work_log_id = ?
            ORDER BY mu.id
        ');
        $stmt->execute([$id]);
        $log['materials'] = $stmt->fetchAll();

        // 照片
        $stmt = $this->db->prepare('SELECT * FROM worklog_photos WHERE work_log_id = ? ORDER BY id');
        $stmt->execute([$id]);
        $log['photos'] = $stmt->fetchAll();

        return $log;
    }

    /**
     * 打卡 - 到場
     */
    public function checkIn(int $scheduleId, int $userId): int
    {
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
            $this->db->prepare("UPDATE cases SET status = 'incomplete' WHERE id = ? AND status IN ('tracking','scheduled','awaiting_dispatch','pending','ready')")
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
     * 填寫施作回報（含收款、完工、再次施工）
     */
    public function saveReport(int $worklogId, array $data): void
    {
        $stmt = $this->db->prepare('
            UPDATE work_logs SET
                work_description = ?,
                issues = ?,
                next_visit_needed = ?,
                next_visit_note = ?,
                payment_collected = ?,
                payment_amount = ?,
                payment_method = ?,
                payment_note = ?,
                is_completed = ?,
                next_visit_date = ?,
                next_visit_type = ?
            WHERE id = ?
        ');
        $isCompleted = !empty($data['is_completed']) ? 1 : 0;
        $nextVisitDate = !empty($data['next_visit_date']) ? $data['next_visit_date'] : null;
        $nextVisitType = !empty($data['next_visit_type']) ? $data['next_visit_type'] : null;

        $stmt->execute([
            $data['work_description'] ?? null,
            $data['issues'] ?? null,
            $data['next_visit_needed'] ?? 0,
            $data['next_visit_note'] ?? null,
            $data['payment_collected'] ?? 0,
            !empty($data['payment_amount']) ? $data['payment_amount'] : null,
            !empty($data['payment_method']) ? $data['payment_method'] : null,
            $data['payment_note'] ?? null,
            $isCompleted,
            $nextVisitDate,
            $nextVisitType,
            $worklogId,
        ]);

        // 完工處理
        if ($isCompleted) {
            $this->markCaseCompletedPending($worklogId);
        }

        // 同步到案件的 case_work_logs（案件管理主表）
        $this->syncToCaseWorkLog($worklogId);
    }

    /**
     * 同步 work_logs 到 case_work_logs
     */
    private function syncToCaseWorkLog($worklogId)
    {
        try {
            // 取得 work_log + schedule + case 資訊
            $stmt = $this->db->prepare("
                SELECT wl.*, s.case_id, s.schedule_date, u.real_name
                FROM work_logs wl
                JOIN schedules s ON wl.schedule_id = s.id
                LEFT JOIN users u ON wl.user_id = u.id
                WHERE wl.id = ?
            ");
            $stmt->execute(array($worklogId));
            $wl = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$wl || empty($wl['case_id'])) return;

            // 取得照片
            $photoStmt = $this->db->prepare("SELECT file_path FROM worklog_photos WHERE work_log_id = ? ORDER BY id");
            $photoStmt->execute(array($worklogId));
            $photos = $photoStmt->fetchAll(PDO::FETCH_COLUMN);
            $photoJson = !empty($photos) ? json_encode($photos) : null;

            // 組合施工內容
            $content = '';
            if (!empty($wl['work_description'])) $content .= $wl['work_description'];
            if (!empty($wl['issues'])) $content .= ($content ? "\n問題：" : '問題：') . $wl['issues'];

            // 檢查是否已有同步紀錄（用 source_worklog_id 對應）
            $existStmt = $this->db->prepare("SELECT id FROM case_work_logs WHERE case_id = ? AND source_worklog_id = ?");
            $existStmt->execute(array($wl['case_id'], $worklogId));
            $existId = $existStmt->fetchColumn();

            if ($existId) {
                // 更新
                $this->db->prepare("UPDATE case_work_logs SET work_date = ?, work_content = ?, equipment_used = ?, arrival_time = ?, departure_time = ?, photo_paths = ? WHERE id = ?")
                    ->execute(array($wl['schedule_date'], $content, $wl['issues'], $wl['arrival_time'], $wl['departure_time'], $photoJson, $existId));
            } else {
                // 新增
                $this->db->prepare("INSERT INTO case_work_logs (case_id, work_date, work_content, equipment_used, arrival_time, departure_time, photo_paths, source_worklog_id, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())")
                    ->execute(array($wl['case_id'], $wl['schedule_date'], $content, $wl['issues'], $wl['arrival_time'], $wl['departure_time'], $photoJson, $worklogId, $wl['user_id']));
            }
        } catch (Exception $e) {
            error_log('syncToCaseWorkLog error: ' . $e->getMessage());
        }
    }

    /**
     * 手動設定上工/下工時間
     */
    public function setManualTime($worklogId, $arrivalTime, $departureTime)
    {
        $stmt = $this->db->prepare('
            UPDATE work_logs SET arrival_time = ?, departure_time = ? WHERE id = ?
        ');
        $stmt->execute([$arrivalTime ?: null, $departureTime ?: null, $worklogId]);
    }

    /**
     * 建立空白 worklog（不打卡）
     */
    public function createBlank($scheduleId, $userId)
    {
        // 只找空白的（還沒填施工內容的）
        $stmt = $this->db->prepare("SELECT id FROM work_logs WHERE schedule_id = ? AND user_id = ? AND (work_description IS NULL OR work_description = '') ORDER BY id DESC LIMIT 1");
        $stmt->execute(array($scheduleId, $userId));
        $existing = $stmt->fetchColumn();
        if ($existing) return (int)$existing;

        // 沒有空白的 → 建立新的（允許多次回報）
        $this->db->prepare('INSERT INTO work_logs (schedule_id, user_id) VALUES (?, ?)')->execute(array($scheduleId, $userId));
        return (int)$this->db->lastInsertId();
    }

    /**
     * 完工 → 案件進度變「已完工待簽核」
     */
    private function markCaseCompletedPending($worklogId)
    {
        $stmt = $this->db->prepare('
            SELECT s.case_id, wl.schedule_id FROM work_logs wl
            JOIN schedules s ON wl.schedule_id = s.id
            WHERE wl.id = ?
        ');
        $stmt->execute([$worklogId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) return;

        $caseId = $row['case_id'];
        $scheduleId = $row['schedule_id'];

        // 更新案件狀態
        $this->db->prepare("UPDATE cases SET status = 'completed_pending' WHERE id = ? AND status NOT IN ('completed_pending','closed')")
                 ->execute([$caseId]);

        // 更新排工狀態為已完成
        if ($scheduleId) {
            $this->db->prepare("UPDATE schedules SET status = 'completed' WHERE id = ?")->execute([$scheduleId]);
        }

        // 送簽核：如果已有 pending flow 就不重複送
        try {
            require_once __DIR__ . '/../approvals/ApprovalModel.php';
            $approvalModel = new ApprovalModel();
            $existPending = $this->db->prepare("SELECT COUNT(*) FROM approval_flows WHERE module = 'case_completion' AND target_id = ? AND status = 'pending'");
            $existPending->execute([$caseId]);
            if ((int)$existPending->fetchColumn() === 0) {
                $approvalModel->submitCaseCompletion($caseId, Auth::id());
            }
        } catch (Exception $e) {
            // 簽核模組尚未設定規則時不中斷
        }
    }

    /**
     * 儲存材料使用 (先清後存)
     */
    public function saveMaterials(int $worklogId, array $materials): void
    {
        $this->db->prepare('DELETE FROM material_usage WHERE work_log_id = ?')->execute([$worklogId]);
        $stmt = $this->db->prepare('
            INSERT INTO material_usage (work_log_id, product_id, material_type, material_name, unit, shipped_qty, used_qty, returned_qty, unit_cost, note)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ');
        foreach ($materials as $m) {
            if (empty($m['material_name'])) continue;
            $stmt->execute([
                $worklogId,
                !empty($m['product_id']) ? (int)$m['product_id'] : null,
                $m['material_type'] ?? 'consumable',
                $m['material_name'],
                $m['unit'] ?? null,
                $m['shipped_qty'] ?? 0,
                $m['used_qty'] ?? 0,
                $m['returned_qty'] ?? 0,
                !empty($m['unit_cost']) ? $m['unit_cost'] : null,
                $m['material_note'] ?? null,
            ]);
        }
    }

    /**
     * 儲存照片
     */
    public function savePhoto($worklogId, $filePath, $caption = null)
    {
        $stmt = $this->db->prepare('INSERT INTO worklog_photos (work_log_id, file_path, caption) VALUES (?, ?, ?)');
        $stmt->execute(array($worklogId, $filePath, $caption));
        return (int)$this->db->lastInsertId();
    }

    /**
     * 刪除照片
     */
    public function deletePhoto($photoId)
    {
        $stmt = $this->db->prepare('SELECT file_path FROM worklog_photos WHERE id = ?');
        $stmt->execute(array($photoId));
        $path = $stmt->fetchColumn();
        if ($path) {
            $fullPath = __DIR__ . '/../../public' . $path;
            if (file_exists($fullPath)) {
                unlink($fullPath);
            }
        }
        $this->db->prepare('DELETE FROM worklog_photos WHERE id = ?')->execute(array($photoId));
    }

    /**
     * 取得未填回報提醒（主管用）
     */
    public function getIncompleteReports(array $branchIds, $date = null)
    {
        if (!$date) $date = date('Y-m-d');
        $ph = implode(',', array_fill(0, count($branchIds), '?'));
        $stmt = $this->db->prepare("
            SELECT s.id AS schedule_id, s.schedule_date, c.title AS case_title,
                   u.real_name, u.id AS user_id,
                   wl.id AS worklog_id, wl.arrival_time, wl.departure_time, wl.work_description
            FROM schedule_engineers se
            JOIN schedules s ON se.schedule_id = s.id
            JOIN cases c ON s.case_id = c.id
            JOIN users u ON se.user_id = u.id
            LEFT JOIN work_logs wl ON wl.schedule_id = s.id AND wl.user_id = se.user_id
            WHERE c.branch_id IN ($ph)
              AND s.schedule_date = ?
              AND s.status != 'cancelled'
              AND (wl.id IS NULL OR wl.arrival_time IS NULL OR wl.departure_time IS NULL OR wl.work_description IS NULL OR wl.work_description = '')
            ORDER BY u.real_name
        ");
        $params = array_merge($branchIds, array($date));
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}
