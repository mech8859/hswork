<?php
/**
 * 一次性：為「案件－報價單版本管理」加欄位與回填資料
 *   1) cases.accepted_quotation_id       INT NULL   當前生效報價單 ID
 *   2) case_attachments.is_current       TINYINT(1) DEFAULT 1  附件是否為當前版本
 *   3) 回填：依既有 quotations 狀態找出每案最新 accepted 的報價
 *   4) 回填：同案件 file_type='quotation' 附件保留最新一筆 is_current=1，其他=0
 *
 * 用法：/run_add_accepted_quotation.php           （預覽）
 *       /run_add_accepted_quotation.php?execute=1 （實際執行）
 */
require __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (!Auth::hasPermission('admin') && Auth::user()['role'] !== 'boss') {
    die('需要管理員權限');
}
header('Content-Type: text/plain; charset=utf-8');
$db = Database::getInstance();
$execute = isset($_GET['execute']) && $_GET['execute'] == '1';

echo $execute ? "=== 執行模式 ===\n\n" : "=== 預覽模式 === (加 ?execute=1 執行)\n\n";

// ---- 1) cases.accepted_quotation_id ----
echo "--- 1) cases.accepted_quotation_id ---\n";
$col1 = $db->query("SHOW COLUMNS FROM cases LIKE 'accepted_quotation_id'")->fetch();
if ($col1) {
    echo "  [已存在] cases.accepted_quotation_id\n";
} else {
    echo "  [新增] cases.accepted_quotation_id INT DEFAULT NULL\n";
    if ($execute) {
        $db->exec("ALTER TABLE cases ADD COLUMN accepted_quotation_id INT DEFAULT NULL COMMENT '當前生效報價單 ID' AFTER deal_amount");
        echo "    → 完成\n";
    }
}

// ---- 2) case_attachments.is_current ----
echo "\n--- 2) case_attachments.is_current ---\n";
$col2 = $db->query("SHOW COLUMNS FROM case_attachments LIKE 'is_current'")->fetch();
if ($col2) {
    echo "  [已存在] case_attachments.is_current\n";
} else {
    echo "  [新增] case_attachments.is_current TINYINT(1) DEFAULT 1\n";
    if ($execute) {
        $db->exec("ALTER TABLE case_attachments ADD COLUMN is_current TINYINT(1) DEFAULT 1 COMMENT '是否為當前版本（0=過期）'");
        echo "    → 完成\n";
    }
}

// ---- 3) 回填 cases.accepted_quotation_id ----
echo "\n--- 3) 回填 cases.accepted_quotation_id ---\n";
// 每個案件找最新（id desc）accepted 的 quotation
$rows = $db->query("
    SELECT case_id, MAX(id) AS qid
    FROM quotations
    WHERE status = 'customer_accepted' AND case_id IS NOT NULL
    GROUP BY case_id
")->fetchAll(PDO::FETCH_ASSOC);
echo "  找到 " . count($rows) . " 個案件有 accepted 報價\n";

$updatedCases = 0;
if ($execute && !empty($rows)) {
    $up = $db->prepare("UPDATE cases SET accepted_quotation_id = ? WHERE id = ? AND (accepted_quotation_id IS NULL OR accepted_quotation_id = 0)");
    foreach ($rows as $r) {
        $up->execute(array((int)$r['qid'], (int)$r['case_id']));
        if ($up->rowCount() > 0) $updatedCases++;
    }
    echo "  ✓ 更新 {$updatedCases} 筆\n";
} else {
    echo "  (預覽，實際執行會更新最多 " . count($rows) . " 筆)\n";
}

// ---- 4) 回填 case_attachments.is_current ----
echo "\n--- 4) 回填 case_attachments.is_current ---\n";
// 策略：同案件 file_type='quotation' 附件，保留 created_at 最新者 is_current=1，其餘 is_current=0
$caseIds = $db->query("SELECT DISTINCT case_id FROM case_attachments WHERE file_type = 'quotation'")->fetchAll(PDO::FETCH_COLUMN);
echo "  " . count($caseIds) . " 個案件有 quotation 附件\n";

$attUpdated = 0;
if ($execute && !empty($caseIds)) {
    foreach ($caseIds as $cid) {
        // 取同案件所有 quotation 附件，id desc
        $stmt = $db->prepare("SELECT id FROM case_attachments WHERE case_id = ? AND file_type = 'quotation' ORDER BY created_at DESC, id DESC");
        $stmt->execute(array((int)$cid));
        $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
        if (empty($ids)) continue;
        $latest = (int)$ids[0];
        // 最新 = is_current 1；其他 = 0
        $db->prepare("UPDATE case_attachments SET is_current = 1 WHERE id = ?")->execute(array($latest));
        if (count($ids) > 1) {
            $others = array_slice($ids, 1);
            $ph = implode(',', array_fill(0, count($others), '?'));
            $upStmt = $db->prepare("UPDATE case_attachments SET is_current = 0 WHERE id IN ($ph)");
            $upStmt->execute($others);
            $attUpdated += count($others);
        }
    }
    echo "  ✓ 標記 {$attUpdated} 筆附件為過期（is_current=0）\n";
} else {
    echo "  (預覽，實際執行會掃每案 quotation 附件標記過期)\n";
}

echo "\n完成。";
echo $execute ? "\n→ 下一步：Claude 會改 QuotationModel::fillCaseFinancials 和 UI\n" : "\n(預覽模式，無變更)\n";
