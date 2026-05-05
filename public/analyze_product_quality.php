<?php
/**
 * 產品比對品質分析
 * 1. products 表重複型號（不同 product_id 但同 model，AI 比對容易選錯）
 * 2. 已確認進貨單但 vendor_products 沒同步成功的明細
 * 3. products 缺品名/缺單位的記錄
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (!Auth::hasPermission('all')) die('admin only');
$db = Database::getInstance();

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>產品比對品質分析</title>";
echo "<style>
body{font-family:-apple-system,'PingFang TC',sans-serif;padding:20px;line-height:1.5;font-size:14px;max-width:1400px}
h1{color:#1f4e79}
h2{color:#2e75b6;border-bottom:2px solid #2e75b6;padding-bottom:4px;margin-top:32px}
table{border-collapse:collapse;margin:8px 0;width:100%}
th,td{border:1px solid #ddd;padding:6px 10px;text-align:left;vertical-align:top}
th{background:#f0f8ff;font-weight:600}
tr:nth-child(even){background:#fafafa}
.warn{color:#c5221f;font-weight:600}
.ok{color:#16a34a}
.muted{color:#999;font-size:.9em}
.summary{background:#fff3cd;padding:10px 14px;border-left:4px solid #f9a825;margin:12px 0;border-radius:4px}
code{background:#f5f5f5;padding:2px 6px;border-radius:3px;font-size:.9em}
</style></head><body>";

echo "<h1>📊 產品比對品質分析</h1>";
echo "<p class='muted'>分析時間：" . date('Y-m-d H:i:s') . "</p>";

// ============================================================
// 1. 重複型號（同 model 多筆 product_id）
// ============================================================
echo "<h2>1️⃣ products 重複型號（AI 比對最可能誤匹配的熱點）</h2>";
echo "<p class='muted'>同一個 model 對應多筆 products，AI 辨識用 LIKE %model% 容易選錯。</p>";

$dupModels = $db->query("
    SELECT model, COUNT(*) cnt, GROUP_CONCAT(id ORDER BY id) ids, GROUP_CONCAT(name SEPARATOR ' / ') names
    FROM products
    WHERE is_active = 1 AND model IS NOT NULL AND model <> ''
    GROUP BY model
    HAVING cnt > 1
    ORDER BY cnt DESC, model
    LIMIT 100
")->fetchAll(PDO::FETCH_ASSOC);

if (empty($dupModels)) {
    echo "<p class='ok'>✓ 沒有重複型號，太棒了！</p>";
} else {
    echo "<div class='summary'>找到 <strong>" . count($dupModels) . "</strong> 組重複型號（前 100 筆）</div>";
    echo "<table><thead><tr><th>型號</th><th>數量</th><th>product_id 清單</th><th>各品名</th></tr></thead><tbody>";
    foreach ($dupModels as $r) {
        echo "<tr>";
        echo "<td><code>" . h($r['model']) . "</code></td>";
        echo "<td class='warn'>" . (int)$r['cnt'] . "</td>";
        echo "<td>" . h($r['ids']) . "</td>";
        echo "<td>" . h($r['names']) . "</td>";
        echo "</tr>";
    }
    echo "</tbody></table>";
}

// ============================================================
// 2. products 缺品名 / 缺單位 / 缺廠商關聯
// ============================================================
echo "<h2>2️⃣ products 資料不完整（影響辨識準確度）</h2>";

$missingName = $db->query("SELECT COUNT(*) FROM products WHERE is_active = 1 AND (name IS NULL OR name = '')")->fetchColumn();
$missingModel = $db->query("SELECT COUNT(*) FROM products WHERE is_active = 1 AND (model IS NULL OR model = '')")->fetchColumn();
$missingUnit = $db->query("SELECT COUNT(*) FROM products WHERE is_active = 1 AND (unit IS NULL OR unit = '')")->fetchColumn();
$totalActive = $db->query("SELECT COUNT(*) FROM products WHERE is_active = 1")->fetchColumn();

echo "<table style='max-width:600px'>";
echo "<thead><tr><th>項目</th><th>數量</th><th>佔比</th></tr></thead><tbody>";
echo "<tr><td>啟用中產品總數</td><td>" . number_format($totalActive) . "</td><td>100%</td></tr>";
echo "<tr><td>缺<strong>品名</strong></td><td class='" . ($missingName > 0 ? 'warn' : 'ok') . "'>" . number_format($missingName) . "</td><td>" . ($totalActive > 0 ? number_format($missingName / $totalActive * 100, 1) : 0) . "%</td></tr>";
echo "<tr><td>缺<strong>型號</strong></td><td class='" . ($missingModel > 0 ? 'warn' : 'ok') . "'>" . number_format($missingModel) . "</td><td>" . ($totalActive > 0 ? number_format($missingModel / $totalActive * 100, 1) : 0) . "%</td></tr>";
echo "<tr><td>缺<strong>單位</strong></td><td class='" . ($missingUnit > 0 ? 'warn' : 'ok') . "'>" . number_format($missingUnit) . "</td><td>" . ($totalActive > 0 ? number_format($missingUnit / $totalActive * 100, 1) : 0) . "%</td></tr>";
echo "</tbody></table>";

if ($missingName > 0) {
    echo "<h3>缺品名的前 30 筆 product_id</h3>";
    $rows = $db->query("SELECT id, model, brand, unit FROM products WHERE is_active = 1 AND (name IS NULL OR name = '') ORDER BY id LIMIT 30")->fetchAll(PDO::FETCH_ASSOC);
    echo "<table><thead><tr><th>ID</th><th>型號</th><th>品牌</th><th>單位</th></tr></thead><tbody>";
    foreach ($rows as $r) {
        echo "<tr><td>" . (int)$r['id'] . "</td><td><code>" . h($r['model']) . "</code></td><td>" . h($r['brand']) . "</td><td>" . h($r['unit']) . "</td></tr>";
    }
    echo "</tbody></table>";
}

// ============================================================
// 3. 已確認進貨單明細未同步到 vendor_products
// ============================================================
echo "<h2>3️⃣ 進貨單明細未同步到 vendor_products（白白浪費的訓練資料）</h2>";
echo "<p class='muted'>已確認進貨單明細若 product_id 不為空，理應在 vendor_products 有對應紀錄；缺對應則 AI 下次比對時會錯過這個歷史資料。</p>";

$missingSync = $db->query("
    SELECT gri.id AS gri_id, gri.goods_receipt_id, gr.gr_number, gr.confirmed_at, gr.vendor_id, v.name AS vendor_name,
           gri.product_id, gri.model, gri.product_name
    FROM goods_receipt_items gri
    JOIN goods_receipts gr ON gri.goods_receipt_id = gr.id
    LEFT JOIN vendors v ON gr.vendor_id = v.id
    LEFT JOIN vendor_products vp ON vp.vendor_id = gr.vendor_id AND vp.product_id = gri.product_id
    WHERE gr.status = '已確認'
      AND gri.product_id IS NOT NULL
      AND gr.vendor_id IS NOT NULL
      AND vp.id IS NULL
    ORDER BY gr.confirmed_at DESC
    LIMIT 50
")->fetchAll(PDO::FETCH_ASSOC);

$missingSyncCount = (int)$db->query("
    SELECT COUNT(*)
    FROM goods_receipt_items gri
    JOIN goods_receipts gr ON gri.goods_receipt_id = gr.id
    LEFT JOIN vendor_products vp ON vp.vendor_id = gr.vendor_id AND vp.product_id = gri.product_id
    WHERE gr.status = '已確認'
      AND gri.product_id IS NOT NULL
      AND gr.vendor_id IS NOT NULL
      AND vp.id IS NULL
")->fetchColumn();

if ($missingSyncCount === 0) {
    echo "<p class='ok'>✓ 全部已確認進貨單都有對應的 vendor_products 紀錄</p>";
} else {
    echo "<div class='summary'>共 <strong>" . number_format($missingSyncCount) . "</strong> 筆進貨明細未同步（顯示最近 50 筆）<br>";
    echo "建議：執行 <code>php public/run_resync_vendor_products.php</code>（補同步腳本）</div>";
    echo "<table><thead><tr><th>進貨單號</th><th>確認日</th><th>廠商</th><th>型號</th><th>品名</th></tr></thead><tbody>";
    foreach ($missingSync as $r) {
        echo "<tr>";
        echo "<td>" . h($r['gr_number']) . "</td>";
        echo "<td>" . h(substr($r['confirmed_at'] ?? '', 0, 10)) . "</td>";
        echo "<td>" . h($r['vendor_name']) . "</td>";
        echo "<td><code>" . h($r['model']) . "</code></td>";
        echo "<td>" . h($r['product_name']) . "</td>";
        echo "</tr>";
    }
    echo "</tbody></table>";
}

// ============================================================
// 4. 進貨單明細 product_id 為空（未對應任何產品）
// ============================================================
echo "<h2>4️⃣ 進貨單明細未對應 product_id（使用者沒選 / AI 沒比對到）</h2>";

$noProductIdCount = (int)$db->query("
    SELECT COUNT(*)
    FROM goods_receipt_items gri
    JOIN goods_receipts gr ON gri.goods_receipt_id = gr.id
    WHERE gr.status = '已確認'
      AND (gri.product_id IS NULL OR gri.product_id = 0)
")->fetchColumn();

if ($noProductIdCount === 0) {
    echo "<p class='ok'>✓ 已確認進貨單明細都有對應 product_id</p>";
} else {
    echo "<div class='summary'>有 <strong>" . number_format($noProductIdCount) . "</strong> 筆已確認進貨明細沒對應 product_id<br>";
    echo "（這些品項沒進入 vendor_products 訓練資料；AI 下次看到時要重新比對）</div>";

    // 顯示 model 出現次數最多的（這些是該優先建檔到 products 的）
    $topModels = $db->query("
        SELECT gri.model, gri.product_name, COUNT(*) cnt
        FROM goods_receipt_items gri
        JOIN goods_receipts gr ON gri.goods_receipt_id = gr.id
        WHERE gr.status = '已確認'
          AND (gri.product_id IS NULL OR gri.product_id = 0)
          AND gri.model IS NOT NULL AND gri.model <> ''
        GROUP BY gri.model, gri.product_name
        ORDER BY cnt DESC
        LIMIT 50
    ")->fetchAll(PDO::FETCH_ASSOC);

    echo "<h3>優先建檔候選（按出現次數排序，前 50）</h3>";
    echo "<p class='muted'>這些 model 出現多次但沒對應 product_id，建議優先建到 products 表，下次 AI 看到就能正確比對。</p>";
    echo "<table><thead><tr><th>型號</th><th>品名</th><th>出現次數</th></tr></thead><tbody>";
    foreach ($topModels as $r) {
        echo "<tr><td><code>" . h($r['model']) . "</code></td><td>" . h($r['product_name']) . "</td><td class='warn'>" . (int)$r['cnt'] . "</td></tr>";
    }
    echo "</tbody></table>";
}

// ============================================================
// 5. vendor_products 統計
// ============================================================
echo "<h2>5️⃣ vendor_products 統計（廠商產品對照表）</h2>";
$vpTotal = (int)$db->query("SELECT COUNT(*) FROM vendor_products WHERE is_active = 1")->fetchColumn();
$vpDistinctVendors = (int)$db->query("SELECT COUNT(DISTINCT vendor_id) FROM vendor_products WHERE is_active = 1")->fetchColumn();
$vpDistinctProducts = (int)$db->query("SELECT COUNT(DISTINCT product_id) FROM vendor_products WHERE is_active = 1 AND product_id IS NOT NULL")->fetchColumn();

echo "<table style='max-width:600px'><thead><tr><th>項目</th><th>數量</th></tr></thead><tbody>";
echo "<tr><td>啟用中對照筆數</td><td>" . number_format($vpTotal) . "</td></tr>";
echo "<tr><td>涉及廠商數</td><td>" . number_format($vpDistinctVendors) . "</td></tr>";
echo "<tr><td>涉及產品數</td><td>" . number_format($vpDistinctProducts) . "</td></tr>";
echo "</tbody></table>";

echo "<hr style='margin-top:40px'>";
echo "<p class='muted'>分析完成。要清掉重複型號或補同步可以告訴我下一步。</p>";
echo "</body></html>";
