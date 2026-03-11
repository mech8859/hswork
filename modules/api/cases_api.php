<?php
/**
 * 案件 API 模組
 * 供 Ragic / Google Sheet 串接使用
 */

switch ($action) {
    case 'list':
        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = min(100, max(1, (int)($_GET['limit'] ?? 50)));
        $offset = ($page - 1) * $limit;
        $branchId = $_GET['branch_id'] ?? null;

        $where = '1=1';
        $params = [];

        if ($branchId) {
            $where .= ' AND c.branch_id = ?';
            $params[] = $branchId;
        }

        if (!empty($_GET['status'])) {
            $where .= ' AND c.status = ?';
            $params[] = $_GET['status'];
        }

        $stmt = $db->prepare("
            SELECT c.*, b.name as branch_name
            FROM cases c
            JOIN branches b ON c.branch_id = b.id
            WHERE $where
            ORDER BY c.updated_at DESC
            LIMIT $limit OFFSET $offset
        ");
        $stmt->execute($params);
        $cases = $stmt->fetchAll();

        $countStmt = $db->prepare("SELECT COUNT(*) FROM cases c WHERE $where");
        $countStmt->execute($params);
        $total = $countStmt->fetchColumn();

        json_response([
            'data'  => $cases,
            'total' => (int)$total,
            'page'  => $page,
            'limit' => $limit,
        ]);
        break;

    case 'get':
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) json_response(['error' => 'Missing id'], 400);

        $stmt = $db->prepare('
            SELECT c.*, b.name as branch_name
            FROM cases c
            JOIN branches b ON c.branch_id = b.id
            WHERE c.id = ?
        ');
        $stmt->execute([$id]);
        $case = $stmt->fetch();

        if (!$case) json_response(['error' => 'Not found'], 404);

        // 附帶排工條件
        $stmt = $db->prepare('SELECT * FROM case_readiness WHERE case_id = ?');
        $stmt->execute([$id]);
        $case['readiness'] = $stmt->fetch() ?: null;

        // 附帶聯絡人
        $stmt = $db->prepare('SELECT * FROM case_contacts WHERE case_id = ?');
        $stmt->execute([$id]);
        $case['contacts'] = $stmt->fetchAll();

        json_response(['data' => $case]);
        break;

    case 'create':
        if ($method !== 'POST') json_response(['error' => 'Method not allowed'], 405);

        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) json_response(['error' => 'Invalid JSON'], 400);

        $required = ['branch_id', 'title'];
        foreach ($required as $field) {
            if (empty($input[$field])) {
                json_response(['error' => "Missing field: $field"], 400);
            }
        }

        // 產生案件編號
        $stmt = $db->prepare('SELECT code FROM branches WHERE id = ?');
        $stmt->execute([$input['branch_id']]);
        $branchCode = $stmt->fetchColumn();
        if (!$branchCode) json_response(['error' => 'Invalid branch_id'], 400);

        $caseNumber = generate_case_number($branchCode);

        $stmt = $db->prepare('
            INSERT INTO cases (branch_id, case_number, title, case_type, difficulty,
                             estimated_hours, total_visits, address, description, ragic_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([
            $input['branch_id'],
            $caseNumber,
            $input['title'],
            $input['case_type'] ?? 'new_install',
            $input['difficulty'] ?? 3,
            $input['estimated_hours'] ?? null,
            $input['total_visits'] ?? 1,
            $input['address'] ?? null,
            $input['description'] ?? null,
            $input['ragic_id'] ?? null,
        ]);

        $newId = $db->lastInsertId();

        // 建立排工條件記錄
        $db->prepare('INSERT INTO case_readiness (case_id) VALUES (?)')->execute([$newId]);

        json_response(['data' => ['id' => $newId, 'case_number' => $caseNumber]], 201);
        break;

    default:
        json_response(['error' => 'Unknown action'], 400);
}
