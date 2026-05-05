<?php
/**
 * 一次性：清掉 products 表 model 欄位裡的佔位字串（如「未提供」「廠商無提供」）
 *
 * 預覽模式（預設）：
 *   /run_clean_placeholder_models.php
 * 執行模式：
 *   /run_clean_placeholder_models.php?confirm=1
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (!Auth::hasPermission('all')) die('admin only');
$db = Database::getInstance();

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$confirm = !empty($_GET['confirm']);

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>清理佔位 model</title>";
echo "<style>
body{font-family:-apple-system,'PingFang TC',sans-serif;padding:20px;line-height:1.5;font-size:14px;max-width:1200px}
h1{color:#1f4e79}
table{border-collapse:collapse;margin:8px 0;width:100%}
th,td{border:1px solid #ddd;padding:6px 10px;text-align:left}
th{background:#f0f8ff}
tr:nth-child(even){background:#fafafa}
.warn{background:#fff3cd;padding:10px 14px;border-left:4px solid #f9a825;margin:12px 0;border-radius:4px}
.ok{color:#16a34a;font-weight:600}
.btn{display:inline-block;padding:10px 20px;background:#dc3545;color:#fff;border-radius:4px;text-decoration:none;font-weight:600}
.btn-cancel{background:#6c757d;margin-left:8px}
</style></head><body>";

echo "<h1>🧹 清理 products 表佔位 model</h1>";
echo "<p>目標：把 <code>model = '未提供'</code> 或 <code>model = '廠商無提供'</code> 的記錄，model 欄改為 NULL（產品本身保留）</p>";

// 影響範圍預覽
$placeholders = array('未提供', '廠商無提供');
$ph = implode(',', array_fill(0, count($placeholders), '?'));

$rows = $db->prepare("SELECT id, model, name, brand, category_id, is_active FROM products WHERE model IN ({$ph}) ORDER BY id");
$rows->execute($placeholders);
$affected = $rows->fetchAll(PDO::FETCH_ASSOC);

if (empty($affected)) {
    echo "<p class='ok'>✓ 已經沒有佔位 model 了，不用做任何事。</p>";
    echo "</body></html>";
    exit;
}

echo "<div class='warn'>共 <strong>" . count($affected) . "</strong> 筆會被改</div>";

echo "<table><thead><tr><th>ID</th><th>原 model</th><th>品名</th><th>品牌</th><th>啟用</th></tr></thead><tbody>";
foreach ($affected as $r) {
    echo "<tr>";
    echo "<td>" . (int)$r['id'] . "</td>";
    echo "<td><code>" . h($r['model']) . "</code> → <strong>NULL</strong></td>";
    echo "<td>" . h($r['name']) . "</td>";
    echo "<td>" . h($r['brand']) . "</td>";
    echo "<td>" . ((int)$r['is_active'] ? '是' : '否') . "</td>";
    echo "</tr>";
}
echo "</tbody></table>";

if (!$confirm) {
    echo "<div class='warn'>⚠ 目前是<strong>預覽模式</strong>，沒有真的改 DB。<br>確認以上內容無誤再點下方按鈕執行：</div>";
    echo "<a href='/run_clean_placeholder_models.php?confirm=1' class='btn' onclick=\"return confirm('確定要把以上 " . count($affected) . " 筆 product 的 model 改成 NULL 嗎？此動作不可逆，但產品本身不會刪除。')\">執行清理</a>";
    echo "<a href='/products.php' class='btn btn-cancel'>取消</a>";
} else {
    // 真的執行
    $stmt = $db->prepare("UPDATE products SET model = NULL, updated_at = NOW() WHERE model IN ({$ph})");
    $stmt->execute($placeholders);
    $changed = $stmt->rowCount();

    echo "<div class='warn' style='border-color:#16a34a;background:#d4edda'>";
    echo "<p class='ok'>✓ 完成！已將 <strong>{$changed}</strong> 筆 products.model 改為 NULL</p>";
    echo "</div>";

    // 寫入 audit log（如果有的話）
    if (class_exists('AuditLog')) {
        try {
            AuditLog::log('products', 'cleanup', 0, "批次清理佔位 model（未提供/廠商無提供） {$changed} 筆 → NULL");
        } catch (Exception $e) { /* ignore */ }
    }

    echo "<p><a href='/products.php' class='btn'>返回產品目錄</a> ";
    echo "<a href='/analyze_product_quality.php' class='btn btn-cancel'>重新分析</a></p>";
}

echo "</body></html>";
