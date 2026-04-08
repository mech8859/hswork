<?php
/**
 * Migration 115: 完工 3 關簽核改造
 *
 * 1. approval_flows 加 payload JSON 欄位（記錄行政勾選 has_payment / 會計勾選 payment_received）
 * 2. 確保 case_completion Level 1/2/3 三條預設規則都存在
 * 3. cases.progress 確保支援 unpaid（如果用 ENUM）
 */
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
Auth::requireRole('boss');
header('Content-Type: text/plain; charset=utf-8');

$db = Database::getInstance();

echo "=== Migration 115：完工 3 關簽核 ===\n\n";

// 1. approval_flows 加 payload 欄位
echo "1. 加 approval_flows.payload 欄位\n";
try {
    $db->exec("ALTER TABLE approval_flows ADD COLUMN payload TEXT NULL COMMENT '簽核時填寫的額外資料 (JSON)' AFTER comment");
    echo "   OK\n";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate') !== false) {
        echo "   SKIP (欄位已存在)\n";
    } else {
        echo "   ERROR: " . $e->getMessage() . "\n";
    }
}

// 2. 確保 case_completion 三條規則都存在
echo "\n2. 確保 case_completion Level 1/2/3 預設規則\n";

$rules = array(
    1 => array('name' => '完工簽核 - Level 1 工程主管', 'role' => 'eng_manager'),
    2 => array('name' => '完工簽核 - Level 2 行政人員（勾選有無收款）', 'role' => 'admin_staff'),
    3 => array('name' => '完工簽核 - Level 3 會計人員（確認入帳）', 'role' => 'accountant'),
);

foreach ($rules as $level => $info) {
    $stmt = $db->prepare("SELECT id FROM approval_rules WHERE module = 'case_completion' AND level_order = ? LIMIT 1");
    $stmt->execute(array($level));
    $existing = $stmt->fetchColumn();
    if ($existing) {
        echo "   SKIP Level $level (規則 #$existing 已存在)\n";
        continue;
    }
    try {
        $db->prepare("
            INSERT INTO approval_rules (module, rule_name, min_amount, max_amount, min_profit_rate, approver_role, approver_id, level_order, is_active)
            VALUES ('case_completion', ?, 0, NULL, NULL, ?, NULL, ?, 1)
        ")->execute(array($info['name'], $info['role'], $level));
        echo "   OK Level $level: " . $info['name'] . "\n";
    } catch (PDOException $e) {
        echo "   ERROR Level $level: " . $e->getMessage() . "\n";
    }
}

// 3. cases.status 列舉檢查（確認 unpaid 已存在）
echo "\n3. cases.status 欄位檢查\n";
try {
    $stmt = $db->query("SHOW COLUMNS FROM cases LIKE 'status'");
    $col = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($col) {
        echo "   類型: " . $col['Type'] . "\n";
        if (stripos($col['Type'], 'unpaid') === false && stripos($col['Type'], 'enum') !== false) {
            echo "   ⚠ 注意：status enum 未包含 unpaid，建議改為 VARCHAR(30)\n";
            try {
                $db->exec("ALTER TABLE cases MODIFY COLUMN status VARCHAR(30) NOT NULL DEFAULT 'tracking'");
                echo "   OK 已將 cases.status 改為 VARCHAR(30)\n";
            } catch (Exception $e) {
                echo "   ERROR: " . $e->getMessage() . "\n";
            }
        } else {
            echo "   OK 已支援 unpaid\n";
        }
    }
} catch (PDOException $e) {
    echo "   ERROR: " . $e->getMessage() . "\n";
}

// 4. 顯示目前 case_completion 規則供確認
echo "\n4. 目前 case_completion 規則：\n";
$stmt = $db->query("
    SELECT r.*, GROUP_CONCAT(u.real_name SEPARATOR ',') AS approver_names
    FROM approval_rules r
    LEFT JOIN users u ON FIND_IN_SET(u.id, r.extra_approver_ids)
    WHERE r.module = 'case_completion'
    GROUP BY r.id
    ORDER BY r.level_order
");
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    echo "   Level " . $row['level_order'] . ": " . $row['rule_name'] . "\n";
    echo "     角色: " . ($row['approver_role'] ?: '-');
    if (!empty($row['extra_approver_ids'])) {
        echo " | 指定簽核人: " . ($row['approver_names'] ?: $row['extra_approver_ids']);
    }
    echo "\n";
}

echo "\n=== 完成 ===\n";
echo "請到 /approvals.php?action=settings 設定每一關的具體簽核人（用「其他可簽核人」多選）\n";
