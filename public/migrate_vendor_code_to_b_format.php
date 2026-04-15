<?php
/**
 * 廠商編號批次轉換：純數字 → B-XXXX (4位padding)
 *
 * 同步更新：
 *   - vendors.vendor_code
 *   - payables.vendor_code（歷史快照）
 *   - payments_out.vendor_code（歷史快照）
 *   - purchase_orders.vendor_code（歷史快照，若有）
 *
 * 保留不變：
 *   - 已是 B-XXXX 格式者
 *   - 其他格式（非純數字、非 B-格式）
 *   - 空 / NULL
 *
 * 使用：
 *   ?mode=preview (預設) — 只顯示會改什麼
 *   ?mode=execute          — 實際執行（事務包裹）
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (!Auth::hasPermission('all')) die('admin only');
$db = Database::getInstance();

$mode = isset($_GET['mode']) ? $_GET['mode'] : 'preview';

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>廠商編號轉換 B-XXXX</title>";
echo "<style>body{font-family:sans-serif;padding:20px;line-height:1.6}table{border-collapse:collapse;margin:8px 0;font-size:13px}th,td{border:1px solid #ccc;padding:6px}th{background:#f0f0f0}.ok{color:#2e7d32}.err{color:#c62828}.warn{color:#e65100}</style>";
echo "</head><body>";
echo "<h1>廠商編號批次轉換：純數字 → B-XXXX</h1>";
echo "<p>模式：<b>" . h($mode) . "</b></p>";

// ---- 建立轉換計畫 ----
$vendors = $db->query("SELECT id, vendor_code, name FROM vendors ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
$conversionMap = array(); // oldCode => newCode
$conversionList = array(); // 顯示用

foreach ($vendors as $v) {
    $oldCode = trim((string)$v['vendor_code']);
    if ($oldCode === '') continue; // 空值略過
    if (!preg_match('/^\d+$/', $oldCode)) continue; // 非純數字略過

    $newCode = 'B-' . str_pad($oldCode, 4, '0', STR_PAD_LEFT);
    $conversionMap[$oldCode] = $newCode;
    $conversionList[] = array('id' => $v['id'], 'name' => $v['name'], 'old' => $oldCode, 'new' => $newCode);
}

echo "<h2>1. vendors 表轉換計畫</h2>";
echo "<p>共 <b>" . count($conversionList) . "</b> 筆將轉換</p>";

// 轉換衝突檢查
$newCodeCounts = array();
foreach ($conversionList as $c) {
    if (!isset($newCodeCounts[$c['new']])) $newCodeCounts[$c['new']] = 0;
    $newCodeCounts[$c['new']]++;
}
$conflicts = array_filter($newCodeCounts, function($n) { return $n > 1; });
if (!empty($conflicts)) {
    echo "<p class='err'>✗ 新編號有衝突，中止：</p>";
    foreach ($conflicts as $code => $n) echo "<p>{$code}: {$n} 筆</p>";
    exit;
} else {
    echo "<p class='ok'>✓ 無衝突</p>";
}

// 預覽前 10 筆 + 後 5 筆
echo "<table><tr><th>id</th><th>廠商名稱</th><th>原</th><th>→ 新</th></tr>";
$preview = array_merge(array_slice($conversionList, 0, 10), array('...'), array_slice($conversionList, -5));
foreach ($preview as $p) {
    if ($p === '...') { echo "<tr><td colspan='4' style='text-align:center'>...(" . (count($conversionList) - 15) . " 筆省略)...</td></tr>"; continue; }
    echo "<tr><td>{$p['id']}</td><td>" . h($p['name']) . "</td><td>{$p['old']}</td><td><b>{$p['new']}</b></td></tr>";
}
echo "</table>";

// ---- 歷史快照表分析 ----
echo "<h2>2. 歷史快照表更新計畫</h2>";

$snapshotTables = array('payables', 'payments_out', 'purchase_orders');
$snapshotPlans = array();

foreach ($snapshotTables as $tbl) {
    $hasCode = $db->query("SHOW COLUMNS FROM {$tbl} LIKE 'vendor_code'")->fetch();
    if (!$hasCode) {
        $snapshotPlans[$tbl] = array('total' => 0, 'to_update' => 0, 'note' => '無 vendor_code 欄位');
        continue;
    }
    $total = (int)$db->query("SELECT COUNT(*) FROM {$tbl} WHERE vendor_code IS NOT NULL AND vendor_code != ''")->fetchColumn();
    $toUpdate = (int)$db->query("SELECT COUNT(*) FROM {$tbl} WHERE vendor_code REGEXP '^[0-9]+$'")->fetchColumn();
    $snapshotPlans[$tbl] = array('total' => $total, 'to_update' => $toUpdate);
}

echo "<table><tr><th>表</th><th>總 vendor_code 筆數</th><th>純數字需轉</th><th>備註</th></tr>";
foreach ($snapshotPlans as $tbl => $p) {
    $note = isset($p['note']) ? $p['note'] : '';
    echo "<tr><td>{$tbl}</td><td>{$p['total']}</td><td>{$p['to_update']}</td><td>{$note}</td></tr>";
}
echo "</table>";

// ---- 將執行的 SQL 清單 ----
echo "<h2>3. 將執行的更新</h2>";
echo "<ol>";
echo "<li>vendors 表：逐筆 UPDATE vendors.vendor_code（共 " . count($conversionList) . " 筆）</li>";
foreach ($snapshotTables as $tbl) {
    if (!empty($snapshotPlans[$tbl]['to_update'])) {
        echo "<li>{$tbl} 表：將純數字 vendor_code 補 0 並加 B- 前綴（共 {$snapshotPlans[$tbl]['to_update']} 筆）</li>";
    }
}
echo "</ol>";

// ---- 實際執行 ----
if ($mode === 'execute') {
    echo "<h2>4. 執行結果</h2>";
    try {
        $db->beginTransaction();

        // vendors 表逐筆更新
        $vendorUpdateStmt = $db->prepare("UPDATE vendors SET vendor_code = ? WHERE id = ? AND vendor_code = ?");
        $vendorUpdated = 0;
        foreach ($conversionList as $c) {
            $vendorUpdateStmt->execute(array($c['new'], $c['id'], $c['old']));
            $vendorUpdated += $vendorUpdateStmt->rowCount();
        }
        echo "<p class='ok'>✓ vendors 更新 {$vendorUpdated} 筆</p>";

        // 歷史快照表：用 CONCAT + LPAD 一次更新所有純數字快照
        foreach ($snapshotTables as $tbl) {
            if (empty($snapshotPlans[$tbl]['to_update'])) continue;
            $sql = "UPDATE {$tbl}
                    SET vendor_code = CONCAT('B-', LPAD(vendor_code, 4, '0'))
                    WHERE vendor_code REGEXP '^[0-9]+$'";
            $stmt = $db->prepare($sql);
            $stmt->execute();
            $affected = $stmt->rowCount();
            echo "<p class='ok'>✓ {$tbl} 更新 {$affected} 筆</p>";
        }

        $db->commit();
        echo "<p class='ok' style='font-size:18px;margin-top:20px'><b>✓ 全部完成，已提交</b></p>";
    } catch (Exception $e) {
        $db->rollBack();
        echo "<p class='err'>✗ 失敗：" . h($e->getMessage()) . "（已 rollback）</p>";
    }
} else {
    echo "<hr><p>以上僅為預覽，實際執行請按：</p>";
    echo "<p><a href='?mode=execute' onclick='return confirm(\"確定要執行全部轉換？此操作會修改 " . count($conversionList) . " 筆 vendors 記錄及相關快照表\")' style='font-size:18px;background:#c62828;color:#fff;padding:10px 20px;text-decoration:none;border-radius:4px'>⚠ 執行批次轉換</a></p>";
    echo "<p><small>（整個流程包在事務中，失敗會自動 rollback）</small></p>";
}

echo "</body></html>";
