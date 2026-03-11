<?php
/**
 * 人員 API 模組
 */

switch ($action) {
    case 'list':
        $branchId = $_GET['branch_id'] ?? null;
        $engineersOnly = isset($_GET['engineers_only']);

        $where = 'u.is_active = 1';
        $params = [];

        if ($branchId) {
            $where .= ' AND u.branch_id = ?';
            $params[] = $branchId;
        }
        if ($engineersOnly) {
            $where .= ' AND u.is_engineer = 1';
        }

        $stmt = $db->prepare("
            SELECT u.id, u.real_name, u.role, u.phone, u.is_engineer,
                   b.name as branch_name
            FROM users u
            JOIN branches b ON u.branch_id = b.id
            WHERE $where
            ORDER BY u.branch_id, u.real_name
        ");
        $stmt->execute($params);
        json_response(['data' => $stmt->fetchAll()]);
        break;

    case 'skills':
        $userId = (int)($_GET['user_id'] ?? 0);
        if (!$userId) json_response(['error' => 'Missing user_id'], 400);

        $stmt = $db->prepare('
            SELECT us.*, s.name as skill_name, s.category
            FROM user_skills us
            JOIN skills s ON us.skill_id = s.id
            WHERE us.user_id = ?
            ORDER BY s.category, s.name
        ');
        $stmt->execute([$userId]);
        json_response(['data' => $stmt->fetchAll()]);
        break;

    default:
        json_response(['error' => 'Unknown action'], 400);
}
