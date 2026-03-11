<?php
/**
 * 同步 API 模組 (Ragic / Google Sheet)
 */

switch ($action) {
    case 'ragic_import':
        if ($method !== 'POST') json_response(['error' => 'Method not allowed'], 405);

        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input || empty($input['records'])) {
            json_response(['error' => 'Invalid data'], 400);
        }

        $processed = 0;
        $errors = [];

        foreach ($input['records'] as $record) {
            try {
                // 以 ragic_id 查找或新建案件
                if (!empty($record['ragic_id'])) {
                    $stmt = $db->prepare('SELECT id FROM cases WHERE ragic_id = ?');
                    $stmt->execute([$record['ragic_id']]);
                    $existingId = $stmt->fetchColumn();

                    if ($existingId) {
                        // 更新
                        $stmt = $db->prepare('
                            UPDATE cases SET title = ?, status = ?, address = ?,
                                           description = ?, updated_at = NOW()
                            WHERE id = ?
                        ');
                        $stmt->execute([
                            $record['title'] ?? '',
                            $record['status'] ?? 'pending',
                            $record['address'] ?? null,
                            $record['description'] ?? null,
                            $existingId,
                        ]);
                    } else {
                        // 新建 (需要 branch_id)
                        if (empty($record['branch_id'])) continue;

                        $stmtBranch = $db->prepare('SELECT code FROM branches WHERE id = ?');
                        $stmtBranch->execute([$record['branch_id']]);
                        $branchCode = $stmtBranch->fetchColumn();
                        if (!$branchCode) continue;

                        $stmt = $db->prepare('
                            INSERT INTO cases (branch_id, case_number, title, ragic_id,
                                             address, description)
                            VALUES (?, ?, ?, ?, ?, ?)
                        ');
                        $stmt->execute([
                            $record['branch_id'],
                            generate_case_number($branchCode),
                            $record['title'] ?? '',
                            $record['ragic_id'],
                            $record['address'] ?? null,
                            $record['description'] ?? null,
                        ]);
                    }
                    $processed++;
                }
            } catch (Exception $e) {
                $errors[] = $e->getMessage();
            }
        }

        // 記錄同步
        $status = empty($errors) ? 'success' : (count($errors) < count($input['records']) ? 'partial' : 'failed');
        $db->prepare('
            INSERT INTO sync_logs (source, direction, entity_type, status, records_processed, error_message)
            VALUES (?, ?, ?, ?, ?, ?)
        ')->execute([
            'ragic', 'import', 'cases', $status, $processed,
            empty($errors) ? null : json_encode($errors, JSON_UNESCAPED_UNICODE),
        ]);

        json_response([
            'processed' => $processed,
            'errors'    => $errors,
            'status'    => $status,
        ]);
        break;

    case 'logs':
        $limit = min(100, max(1, (int)($_GET['limit'] ?? 20)));
        $stmt = $db->prepare("
            SELECT * FROM sync_logs ORDER BY created_at DESC LIMIT $limit
        ");
        $stmt->execute();
        json_response(['data' => $stmt->fetchAll()]);
        break;

    default:
        json_response(['error' => 'Unknown action'], 400);
}
