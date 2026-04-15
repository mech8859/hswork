<?php
/**
 * 廠商編號轉換分析腳本（只讀，不改資料）
 * 用途：評估將純數字廠商編號轉為 B-XXXX 格式的影響
 *
 * 檢查項目：
 *   1. vendors 表 vendor_code 格式分布
 *   2. vendors 表 vendor_code 重複檢查
 *   3. 歷史快照表 vendor_code 現況
 *   4. 歷史快照表 vendor_id ↔ vendor_code 一致性（關聯完整性）
 *   5. 模擬轉換後結果
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (!Auth::hasPermission('all')) {
    die('需要 admin 權限');
}
$db = Database::getInstance();

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function section($title) { echo "<h2 style='margin-top:24px;border-bottom:2px solid #333;padding-bottom:4px'>" . h($title) . "</h2>"; }
function subsection($title) { echo "<h3 style='margin-top:16px;color:#1976d2'>" . h($title) . "</h3>"; }
function tbl_start($headers) {
    echo "<table border='1' cellpadding='6' cellspacing='0' style='border-collapse:collapse;margin:8px 0'><tr style='background:#f0f0f0'>";
    foreach ($headers as $h) echo "<th>" . h($h) . "</th>";
    echo "</tr>";
}
function tbl_row($cells, $highlight = false) {
    $bg = $highlight ? 'background:#ffe0b2' : '';
    echo "<tr style='$bg'>";
    foreach ($cells as $c) echo "<td>" . h($c) . "</td>";
    echo "</tr>";
}
function tbl_end() { echo "</table>"; }

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>廠商編號分析</title>";
echo "<style>body{font-family:sans-serif;padding:20px;line-height:1.6}table{font-size:14px}th{text-align:left}.ok{color:#2e7d32}.warn{color:#e65100}.err{color:#c62828;font-weight:bold}</style>";
echo "</head><body>";
echo "<h1>廠商編號轉換分析報告</h1>";
echo "<p>時間：" . date('Y-m-d H:i:s') . "</p>";

/* ============================================================
 * 1. vendors 表現況
 * ============================================================ */
section('1. vendors 表 vendor_code 格式分布');

$totalVendors = (int)$db->query("SELECT COUNT(*) FROM vendors")->fetchColumn();
$activeVendors = (int)$db->query("SELECT COUNT(*) FROM vendors WHERE is_active = 1")->fetchColumn();
echo "<p>總廠商數：<b>{$totalVendors}</b> 筆（啟用：{$activeVendors} 筆）</p>";

// 分類：純數字 / B-XXXX / 其他 / 空
$categories = array(
    'empty'     => array('label' => '空 / NULL', 'count' => 0, 'samples' => array()),
    'pure_num'  => array('label' => '純數字（待轉換）', 'count' => 0, 'samples' => array()),
    'b_format'  => array('label' => 'B-XXXX 格式（已符合）', 'count' => 0, 'samples' => array()),
    'other'     => array('label' => '其他格式', 'count' => 0, 'samples' => array()),
);

$stmt = $db->query("SELECT id, vendor_code, name FROM vendors ORDER BY id");
$allVendors = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($allVendors as $v) {
    $code = trim((string)$v['vendor_code']);
    if ($code === '') {
        $cat = 'empty';
    } elseif (preg_match('/^\d+$/', $code)) {
        $cat = 'pure_num';
    } elseif (preg_match('/^B-\d{4}$/i', $code)) {
        $cat = 'b_format';
    } else {
        $cat = 'other';
    }
    $categories[$cat]['count']++;
    if (count($categories[$cat]['samples']) < 5) {
        $categories[$cat]['samples'][] = "[id={$v['id']}] {$code} — {$v['name']}";
    }
}

tbl_start(array('類別', '筆數', '範例'));
foreach ($categories as $key => $c) {
    tbl_row(array($c['label'], $c['count'], implode("\n", $c['samples'])), $key === 'pure_num');
}
tbl_end();

/* ============================================================
 * 2. vendors 表 vendor_code 重複檢查
 * ============================================================ */
section('2. vendors 表 vendor_code 重複檢查');

$dup = $db->query("
    SELECT vendor_code, COUNT(*) as cnt, GROUP_CONCAT(id) as ids, GROUP_CONCAT(name SEPARATOR ' | ') as names
    FROM vendors
    WHERE vendor_code IS NOT NULL AND vendor_code != ''
    GROUP BY vendor_code HAVING cnt > 1
    ORDER BY cnt DESC
")->fetchAll(PDO::FETCH_ASSOC);

if (empty($dup)) {
    echo "<p class='ok'>✓ 無重複的 vendor_code</p>";
} else {
    echo "<p class='err'>✗ 發現 " . count($dup) . " 組重複</p>";
    tbl_start(array('vendor_code', '筆數', 'ids', '廠商名稱'));
    foreach ($dup as $d) tbl_row(array($d['vendor_code'], $d['cnt'], $d['ids'], $d['names']), true);
    tbl_end();
}

// 模擬轉換後是否還會重複（例：原本 "250" 和 "0250" 都會變成 "B-0250"）
subsection('模擬轉換後是否產生新重複');
$afterMap = array();
foreach ($allVendors as $v) {
    $code = trim((string)$v['vendor_code']);
    if ($code === '') continue;
    if (preg_match('/^\d+$/', $code)) {
        $newCode = 'B-' . str_pad($code, 4, '0', STR_PAD_LEFT);
    } else {
        $newCode = $code; // 保留
    }
    if (!isset($afterMap[$newCode])) $afterMap[$newCode] = array();
    $afterMap[$newCode][] = "[id={$v['id']}] 原={$code} → {$newCode} ({$v['name']})";
}
$collisions = array_filter($afterMap, function($arr) { return count($arr) > 1; });
if (empty($collisions)) {
    echo "<p class='ok'>✓ 轉換後無新的重複</p>";
} else {
    echo "<p class='err'>✗ 轉換後會產生 " . count($collisions) . " 組衝突，需人工處理</p>";
    tbl_start(array('目標 vendor_code', '衝突來源'));
    foreach ($collisions as $newCode => $items) {
        tbl_row(array($newCode, implode("\n", $items)), true);
    }
    tbl_end();
}

/* ============================================================
 * 3. 歷史快照表 vendor_code 現況
 * ============================================================ */
section('3. 歷史快照表 vendor_code 分布（存舊格式值的表）');

$snapshotTables = array(
    'payables'        => '應付帳款',
    'payments_out'    => '付款單',
    'purchase_orders' => '採購單',
    'goods_receipts'  => '進貨單',
);

foreach ($snapshotTables as $tbl => $label) {
    subsection("{$label} ({$tbl})");
    try {
        // 檢查欄位是否存在
        $colStmt = $db->query("SHOW COLUMNS FROM {$tbl} LIKE 'vendor_code'");
        if (!$colStmt->fetch()) {
            echo "<p>（此表無 vendor_code 欄位，略過）</p>";
            continue;
        }

        $total = (int)$db->query("SELECT COUNT(*) FROM {$tbl}")->fetchColumn();
        $withCode = (int)$db->query("SELECT COUNT(*) FROM {$tbl} WHERE vendor_code IS NOT NULL AND vendor_code != ''")->fetchColumn();
        $pureNum = (int)$db->query("SELECT COUNT(*) FROM {$tbl} WHERE vendor_code REGEXP '^[0-9]+$'")->fetchColumn();
        $bFormat = (int)$db->query("SELECT COUNT(*) FROM {$tbl} WHERE vendor_code REGEXP '^B-[0-9]{4}$'")->fetchColumn();
        $other = $withCode - $pureNum - $bFormat;

        echo "<p>總筆數 <b>{$total}</b>，有 vendor_code <b>{$withCode}</b>（純數字 {$pureNum}、B-格式 {$bFormat}、其他 {$other}）</p>";
    } catch (Exception $e) {
        echo "<p class='warn'>查詢失敗：" . h($e->getMessage()) . "</p>";
    }
}

/* ============================================================
 * 4. 關聯完整性：vendor_id ↔ vendor_code 一致性
 * ============================================================ */
section('4. 關聯完整性檢查（vendor_id ↔ vendor_code 是否匹配）');
echo "<p>比對歷史快照表的 vendor_code 是否和 vendor_id 對應的 vendors 記錄一致。不一致不會影響連結（因為 JOIN 用的是 vendor_id），但會造成顯示混亂。</p>";

foreach ($snapshotTables as $tbl => $label) {
    subsection("{$label} ({$tbl})");
    try {
        // 檢查 vendor_id 欄位是否存在
        $hasVendorId = (bool)$db->query("SHOW COLUMNS FROM {$tbl} LIKE 'vendor_id'")->fetch();
        $hasVendorCode = (bool)$db->query("SHOW COLUMNS FROM {$tbl} LIKE 'vendor_code'")->fetch();

        if (!$hasVendorId || !$hasVendorCode) {
            echo "<p>（缺 vendor_id 或 vendor_code 欄位，略過）</p>";
            continue;
        }

        // 找出 vendor_id 和 vendor_code 不一致的筆數
        $mismatch = $db->query("
            SELECT t.id, t.vendor_id, t.vendor_code as snapshot_code, v.vendor_code as current_code, v.name
            FROM {$tbl} t
            LEFT JOIN vendors v ON v.id = t.vendor_id
            WHERE t.vendor_id IS NOT NULL
              AND t.vendor_code IS NOT NULL AND t.vendor_code != ''
              AND (v.vendor_code IS NULL OR v.vendor_code != t.vendor_code)
            LIMIT 20
        ")->fetchAll(PDO::FETCH_ASSOC);

        $mismatchCount = (int)$db->query("
            SELECT COUNT(*)
            FROM {$tbl} t
            LEFT JOIN vendors v ON v.id = t.vendor_id
            WHERE t.vendor_id IS NOT NULL
              AND t.vendor_code IS NOT NULL AND t.vendor_code != ''
              AND (v.vendor_code IS NULL OR v.vendor_code != t.vendor_code)
        ")->fetchColumn();

        // 有 vendor_id 但找不到對應 vendors 的孤兒
        $orphan = (int)$db->query("
            SELECT COUNT(*)
            FROM {$tbl} t
            LEFT JOIN vendors v ON v.id = t.vendor_id
            WHERE t.vendor_id IS NOT NULL AND v.id IS NULL
        ")->fetchColumn();

        if ($mismatchCount == 0 && $orphan == 0) {
            echo "<p class='ok'>✓ 全部一致，無孤兒記錄</p>";
        } else {
            if ($mismatchCount > 0) echo "<p class='warn'>⚠ 有 {$mismatchCount} 筆 vendor_code 與 vendors 表不一致</p>";
            if ($orphan > 0) echo "<p class='err'>✗ 有 {$orphan} 筆 vendor_id 對應不到 vendors 記錄（孤兒）</p>";
            if (!empty($mismatch)) {
                tbl_start(array('id', 'vendor_id', '快照 code', '目前 code', '廠商名稱'));
                foreach ($mismatch as $m) {
                    tbl_row(array($m['id'], $m['vendor_id'], $m['snapshot_code'], $m['current_code'], $m['name']));
                }
                tbl_end();
                echo "<p><small>（最多列出 20 筆）</small></p>";
            }
        }
    } catch (Exception $e) {
        echo "<p class='warn'>查詢失敗：" . h($e->getMessage()) . "</p>";
    }
}

/* ============================================================
 * 5. 轉換預覽
 * ============================================================ */
section('5. 轉換預覽（前 30 筆）');
echo "<p>預覽純數字 vendor_code 轉為 B-XXXX 的結果：</p>";

tbl_start(array('vendors.id', '廠商名稱', '原 vendor_code', '→ 新 vendor_code'));
$cnt = 0;
foreach ($allVendors as $v) {
    $code = trim((string)$v['vendor_code']);
    if ($code === '' || !preg_match('/^\d+$/', $code)) continue;
    $newCode = 'B-' . str_pad($code, 4, '0', STR_PAD_LEFT);
    tbl_row(array($v['id'], $v['name'], $code, $newCode));
    $cnt++;
    if ($cnt >= 30) break;
}
tbl_end();
echo "<p>共 <b>{$categories['pure_num']['count']}</b> 筆需要轉換。</p>";

/* ============================================================
 * 6. 下一步建議
 * ============================================================ */
section('6. 評估結論');
echo "<ul>";
echo "<li>純數字廠商共 <b>{$categories['pure_num']['count']}</b> 筆，需轉換為 B-XXXX</li>";
echo "<li>已是 B-XXXX 格式 <b>{$categories['b_format']['count']}</b> 筆，保留不變</li>";
echo "<li>其他格式 <b>{$categories['other']['count']}</b> 筆，保留不變</li>";
echo "<li>空值 <b>{$categories['empty']['count']}</b> 筆，下次編輯時自動補號</li>";
echo "<li>" . (empty($collisions) ? '<span class="ok">✓ 轉換後無新重複</span>' : '<span class="err">✗ 轉換後會產生衝突，需人工處理</span>') . "</li>";
echo "<li><b>所有表間連結靠 vendor_id（整數）</b>，vendor_code 只是顯示用快照，轉換不會破壞連結</li>";
echo "<li>建議同步更新歷史快照表（payables / payments_out / purchase_orders / goods_receipts）的 vendor_code，避免新舊並存</li>";
echo "</ul>";

echo "<hr><p><small>此腳本僅讀取資料，未修改任何內容。</small></p>";
echo "</body></html>";
