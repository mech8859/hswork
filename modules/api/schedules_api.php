<?php
/**
 * 排工 API 模組
 */

switch ($action) {
    case 'list':
        $startDate = $_GET['start_date'] ?? date('Y-m-d');
        $endDate = $_GET['end_date'] ?? date('Y-m-d', strtotime('+7 days'));
        $branchId = $_GET['branch_id'] ?? null;

        $where = 's.schedule_date BETWEEN ? AND ?';
        $params = [$startDate, $endDate];

        if ($branchId) {
            $where .= ' AND c.branch_id = ?';
            $params[] = $branchId;
        }

        $stmt = $db->prepare("
            SELECT s.*, c.title as case_title, c.case_number, c.address,
                   v.plate_number, v.vehicle_type,
                   b.name as branch_name
            FROM schedules s
            JOIN cases c ON s.case_id = c.id
            JOIN branches b ON c.branch_id = b.id
            LEFT JOIN vehicles v ON s.vehicle_id = v.id
            WHERE $where
            ORDER BY s.schedule_date ASC, s.created_at ASC
        ");
        $stmt->execute($params);
        $schedules = $stmt->fetchAll();

        // 附帶每個排工的人員
        foreach ($schedules as &$schedule) {
            $stmt = $db->prepare('
                SELECT se.*, u.real_name, u.phone
                FROM schedule_engineers se
                JOIN users u ON se.user_id = u.id
                WHERE se.schedule_id = ?
            ');
            $stmt->execute([$schedule['id']]);
            $schedule['engineers'] = $stmt->fetchAll();
        }

        json_response(['data' => $schedules]);
        break;

    default:
        json_response(['error' => 'Unknown action'], 400);
}
