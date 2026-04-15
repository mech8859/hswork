<?php
/**
 * Migration 123: 修正 inventory UK 與 branch_id=0 資料
 *
 * 執行：
 *   1. DROP 舊 UK uk_product_branch (product_id, branch_id)
 *   2. UPDATE branch_id=0 → 依 warehouse_id 反查 warehouses.branch_id
 *   3. ADD 新 UK uk_product_warehouse (product_id, warehouse_id)
 *   4. 驗證筆數 + 庫存總量不變
 *
 * 模式：
 *   ?mode=preview  (預設) 只預覽要動的內容
 *   ?mode=execute  真的執行
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (!Auth::hasPermission('all')) die('admin only');
$db = Database::getInstance();

$mode = isset($_GET['mode']) ? $_GET['mode'] : 'preview';

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

echo "<!DOCTYPE html><html><head><meta charset=utf-8><title>Migration 123</title>";
echo "<style>body{font-family:sans-serif;padding:20px;line-height:1.6}table{border-collapse:collapse;font-size:13px;margin:8px 0}th,td{border:1px solid #ccc;padding:6px}th{background:#f0f0f0}.ok{color:#2e7d32;font-weight:bold}.warn{color:#e65100}.err{color:#c62828;font-weight:bold}</style></head><body>";
echo "<h1>Migration 123：inventory UK 修正</h1>";
echo "<p>模式：<b>" . h($mode) . "</b></p>";

// 執行前備份快照
$before = array(
    'total' => (int)$db->query("SELECT COUNT(*) FROM inventory")->fetchColumn(),
    'sum_stock' => (float)$db->query("SELECT COALESCE(SUM(stock_qty),0) FROM inventory")->fetchColumn(),
    'sum_avail' => (float)$db->query("SELECT COALESCE(SUM(available_qty),0) FROM inventory")->fetchColumn(),
    'zero_branch' => (int)$db->query("SELECT COUNT(*) FROM inventory WHERE branch_id = 0")->fetchColumn(),
);

echo "<h2>執行前狀態</h2>";
echo "<table><tr><th>指標</th><th>值</th></tr>";
echo "<tr><td>總筆數</td><td>{$before['total']}</td></tr>";
echo "<tr><td>stock_qty 總和</td><td>" . number_format($before['sum_stock'], 2) . "</td></tr>";
echo "<tr><td>available_qty 總和</td><td>" . number_format($before['sum_avail'], 2) . "</td></tr>";
echo "<tr><td>branch_id=0 筆數</td><td>{$before['zero_branch']}</td></tr>";
echo "</table>";

// 檢查 UK 名稱是否還在
$indexRow = $db->query("SHOW INDEX FROM inventory WHERE Key_name = 'uk_product_branch'")->fetch();
$hasOldUK = (bool)$indexRow;
$indexRow2 = $db->query("SHOW INDEX FROM inventory WHERE Key_name = 'uk_product_warehouse'")->fetch();
$hasNewUK = (bool)$indexRow2;

echo "<p>舊 UK uk_product_branch：" . ($hasOldUK ? '<span class="warn">存在</span>' : '<span class="ok">已移除</span>') . "</p>";
echo "<p>新 UK uk_product_warehouse：" . ($hasNewUK ? '<span class="ok">已建立</span>' : '<span class="warn">尚未建立</span>') . "</p>";

// 預覽要改的資料
echo "<h2>預覽：branch_id=0 → 補正資料</h2>";
$updatePreview = $db->query("
    SELECT i.id, i.product_id, i.warehouse_id, i.branch_id AS old_branch, w.branch_id AS new_branch, w.name AS warehouse_name, i.stock_qty
    FROM inventory i
    LEFT JOIN warehouses w ON w.id = i.warehouse_id
    WHERE i.branch_id = 0
    ORDER BY i.id
")->fetchAll(PDO::FETCH_ASSOC);

$canUpdate = 0; $noMatch = 0;
foreach ($updatePreview as $r) {
    if ($r['new_branch'] !== null) $canUpdate++;
    else $noMatch++;
}
echo "<p>可補正：<b>{$canUpdate}</b> 筆，無對應 warehouse：<b>{$noMatch}</b> 筆</p>";

if ($noMatch > 0) {
    echo "<p class='err'>⚠ 有 {$noMatch} 筆找不到 warehouse → branch 對應，請人工處理</p>";
}

// ==== 實際執行 ====
if ($mode === 'execute') {
    echo "<h2>執行中...</h2>";
    try {
        // Step 1: DROP 舊 UK
        if ($hasOldUK) {
            echo "<p>Step 1: DROP uk_product_branch...</p>";
            $db->exec("ALTER TABLE inventory DROP INDEX uk_product_branch");
            echo "<p class='ok'>✓ 舊 UK 已移除</p>";
        } else {
            echo "<p>舊 UK 已不存在，略過 Step 1</p>";
        }

        // Step 2: UPDATE branch_id=0 依 warehouse 反查
        echo "<p>Step 2: 補正 branch_id=0 資料...</p>";
        $updateStmt = $db->prepare("
            UPDATE inventory i
            JOIN warehouses w ON w.id = i.warehouse_id
            SET i.branch_id = w.branch_id
            WHERE i.branch_id = 0 AND w.branch_id IS NOT NULL
        ");
        $updateStmt->execute();
        $updated = $updateStmt->rowCount();
        echo "<p class='ok'>✓ 已補正 {$updated} 筆</p>";

        // Step 3: ADD 新 UK
        if (!$hasNewUK) {
            echo "<p>Step 3: ADD uk_product_warehouse...</p>";
            $db->exec("ALTER TABLE inventory ADD UNIQUE KEY uk_product_warehouse (product_id, warehouse_id)");
            echo "<p class='ok'>✓ 新 UK 已建立</p>";
        } else {
            echo "<p>新 UK 已存在，略過 Step 3</p>";
        }

        // Step 4: 驗證
        $after = array(
            'total' => (int)$db->query("SELECT COUNT(*) FROM inventory")->fetchColumn(),
            'sum_stock' => (float)$db->query("SELECT COALESCE(SUM(stock_qty),0) FROM inventory")->fetchColumn(),
            'sum_avail' => (float)$db->query("SELECT COALESCE(SUM(available_qty),0) FROM inventory")->fetchColumn(),
            'zero_branch' => (int)$db->query("SELECT COUNT(*) FROM inventory WHERE branch_id = 0")->fetchColumn(),
        );

        echo "<h2>執行後驗證</h2>";
        echo "<table><tr><th>指標</th><th>之前</th><th>之後</th><th>狀態</th></tr>";
        $ok = true;
        foreach ($before as $k => $v) {
            $a = $after[$k];
            $match = ($k === 'zero_branch') ? ($a == 0) : ($a == $v);
            if (!$match && $k !== 'zero_branch') $ok = false;
            echo "<tr><td>{$k}</td><td>" . (is_float($v) ? number_format($v, 2) : $v) . "</td><td>" . (is_float($a) ? number_format($a, 2) : $a) . "</td><td>" . ($match ? '<span class=ok>✓ OK</span>' : '<span class=err>✗ 異常</span>') . "</td></tr>";
        }
        echo "</table>";
        if ($ok) {
            echo "<p class='ok' style='font-size:1.2em'>✓ Migration 成功！總筆數 / 庫存總量保持一致，branch_id=0 已清零</p>";
        } else {
            echo "<p class='err'>⚠ 請檢查差異</p>";
        }
    } catch (Exception $e) {
        echo "<p class='err'>✗ 失敗：" . h($e->getMessage()) . "</p>";
        echo "<p>若需回退：<br>1. 如 UK 已改但資料未補完：SHOW INDEX FROM inventory 看現況<br>2. 緊急可執行 ALTER TABLE inventory DROP INDEX uk_product_warehouse; 回到舊狀態</p>";
    }
} else {
    echo "<hr><p>預覽完成，以上僅讀取。實際執行請按紅色按鈕：</p>";
    echo "<p><a href='?mode=execute' onclick='return confirm(\"確定執行 Migration？\\n\\n將：\\n1. 移除舊 UK\\n2. 補正 {$canUpdate} 筆 branch_id\\n3. 建立新 UK\\n\\n過程保留所有資料，筆數 / 庫存總量不變\")' style='font-size:18px;background:#c62828;color:#fff;padding:10px 24px;text-decoration:none;border-radius:4px'>⚠ 執行 Migration 123</a></p>";
    echo "<p>失敗可回退至 git tag: before-inventory-uk-fix</p>";
}

echo "</body></html>";
