<?php
/**
 * 修正案件 2026-1609 帳款交易金額
 * 施工回報收款 #361：$7,000 → $24,000
 * 執行後請刪除此檔案
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);
date_default_timezone_set('Asia/Taipei');
header('Content-Type: text/plain; charset=utf-8');
require_once __DIR__ . '/../includes/bootstrap.php';
$db = Database::getInstance();

// 查案件
$case = $db->query("SELECT id, case_number, total_amount, total_collected, balance_amount FROM cases WHERE case_number = '2026-1609'")->fetch(PDO::FETCH_ASSOC);
if (!$case) {
    echo "案件 2026-1609 不存在\n";
    exit;
}
$caseId = $case['id'];
echo "=== 修正前 ===\n";
echo "案件: {$case['case_number']} (id={$caseId})\n";
echo "總額: \${$case['total_amount']} | 已收: \${$case['total_collected']} | 餘額: \${$case['balance_amount']}\n\n";

// 查交易紀錄
$pay = $db->prepare("SELECT id, amount, note FROM case_payments WHERE case_id = ? AND note = '施工回報收款 #361'");
$pay->execute(array($caseId));
$row = $pay->fetch(PDO::FETCH_ASSOC);
if (!$row) {
    echo "找不到 施工回報收款 #361 的交易紀錄\n";
    exit;
}
echo "交易 #{$row['id']}: \${$row['amount']} → 需修正為 \$24000\n\n";

$mode = isset($_GET['run']) ? 'execute' : 'preview';
$alreadyFixed = ($row['amount'] == 24000);

if ($alreadyFixed) {
    echo "交易金額已正確 (\$24,000)\n";
}

// 檢查是否缺少異動紀錄
$chkLog = $db->prepare("SELECT COUNT(*) FROM case_amount_changes WHERE case_id = ? AND change_source = 'manual_fix'");
$chkLog->execute(array($caseId));
$hasLog = (int)$chkLog->fetchColumn() > 0;
echo "異動紀錄: " . ($hasLog ? "已有" : "缺少") . "\n";
echo "模式: {$mode}\n\n";

if ($alreadyFixed && $hasLog) {
    echo "金額與異動紀錄皆正確，無需修正\n";
} elseif ($mode === 'execute') {
    if (!$alreadyFixed) {
        // 修正金額
        $db->prepare("UPDATE case_payments SET amount = 24000, updated_at = NOW() WHERE id = ?")->execute(array($row['id']));
        echo "已修正交易 #{$row['id']} 金額: \${$row['amount']} → \$24000\n";

        // 回寫案件帳務
        $syncStmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) FROM case_payments WHERE case_id = ?");
        $syncStmt->execute(array($caseId));
        $syncTotal = (int)$syncStmt->fetchColumn();
        $syncCaseTotal = (int)$case['total_amount'];
        $syncBalance = $syncCaseTotal > 0 ? max(0, $syncCaseTotal - $syncTotal) : 0;
        $db->prepare("UPDATE cases SET total_collected = ?, balance_amount = ? WHERE id = ?")->execute(array($syncTotal, $syncBalance, $caseId));
        echo "案件帳務更新: 已收 \${$syncTotal} | 餘額 \${$syncBalance}\n";
    }

    // 補寫金額異動紀錄（不論金額是否剛修正，只要缺紀錄就補）
    if (!$hasLog) {
        try {
            $chkTbl = $db->query("SHOW TABLES LIKE 'case_amount_changes'");
            if ($chkTbl && $chkTbl->rowCount() > 0) {
                // 原始值：$9,000 已收 → $26,000 已收 (差額 $17,000 = 24000-7000)
                $oldCollected = 9000;
                $newCollected = 26000;
                $totalAmt = (int)$case['total_amount'];
                $oldBalance = $totalAmt > 0 ? max(0, $totalAmt - $oldCollected) : 0;
                $newBalance = $totalAmt > 0 ? max(0, $totalAmt - $newCollected) : 0;

                $db->prepare("INSERT INTO case_amount_changes (case_id, field_name, old_value, new_value, change_source, changed_by, changed_by_name) VALUES (?, ?, ?, ?, ?, ?, ?)")
                    ->execute(array($caseId, 'total_collected', $oldCollected, $newCollected, 'manual_fix', 0, 'system'));
                $db->prepare("INSERT INTO case_amount_changes (case_id, field_name, old_value, new_value, change_source, changed_by, changed_by_name) VALUES (?, ?, ?, ?, ?, ?, ?)")
                    ->execute(array($caseId, 'balance_amount', $oldBalance, $newBalance, 'manual_fix', 0, 'system'));
                echo "金額異動紀錄已補寫: 已收 \${$oldCollected}→\${$newCollected} | 餘額 \${$oldBalance}→\${$newBalance}\n";
            }
        } catch (Exception $e) {
            echo "異動紀錄寫入失敗: " . $e->getMessage() . "\n";
        }
    }
    echo "\n";

    // 驗證
    $verify = $db->query("SELECT total_amount, total_collected, balance_amount FROM cases WHERE id = {$caseId}")->fetch(PDO::FETCH_ASSOC);
    echo "=== 修正後 ===\n";
    echo "總額: \${$verify['total_amount']} | 已收: \${$verify['total_collected']} | 餘額: \${$verify['balance_amount']}\n";
    $logs = $db->prepare("SELECT field_name, old_value, new_value, change_source, created_at FROM case_amount_changes WHERE case_id = ? ORDER BY id DESC LIMIT 5");
    $logs->execute(array($caseId));
    echo "\n=== 最近異動紀錄 ===\n";
    foreach ($logs->fetchAll(PDO::FETCH_ASSOC) as $log) {
        echo "{$log['field_name']}: \${$log['old_value']}→\${$log['new_value']} ({$log['change_source']}) {$log['created_at']}\n";
    }
} else {
    echo "預覽模式，加 ?run=1 執行修正\n";
}

echo "\n完成！請刪除此檔案。\n";
