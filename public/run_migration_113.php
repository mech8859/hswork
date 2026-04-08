<?php
/**
 * Migration 113: 銷項發票號碼唯一性
 *
 * 1. 偵測現有重複的 invoice_number
 * 2. 若無重複，加上 UNIQUE index（NULL 值不受限制）
 */
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
Auth::requireRole('boss');
header('Content-Type: text/html; charset=utf-8');

echo "<meta charset='utf-8'><h2>Migration 113：銷項發票號碼唯一性</h2>";

$db = Database::getInstance();

// 1. 檢查 index 是否已存在
$idxStmt = $db->query("SHOW INDEX FROM sales_invoices WHERE Key_name = 'uniq_sales_invoice_number'");
$existingIdx = $idxStmt->fetch(PDO::FETCH_ASSOC);
if ($existingIdx) {
    echo "<p style='color:green'>OK UNIQUE index 已存在 (uniq_sales_invoice_number)，無需重複建立</p>";
    echo "<p><a href='/sales_invoices.php'>返回銷項發票管理</a></p>";
    exit;
}

// 2. 偵測現有重複資料
echo "<h3>步驟 1：偵測重複資料</h3>";
$dupStmt = $db->query("
    SELECT invoice_number, COUNT(*) AS cnt, GROUP_CONCAT(id) AS ids
    FROM sales_invoices
    WHERE invoice_number IS NOT NULL AND invoice_number != ''
    GROUP BY invoice_number
    HAVING COUNT(*) > 1
    ORDER BY cnt DESC
");
$dups = $dupStmt->fetchAll(PDO::FETCH_ASSOC);

if (count($dups) > 0) {
    echo "<p style='color:red'><strong>發現 " . count($dups) . " 組重複的發票號碼，無法建立唯一索引</strong></p>";
    echo "<table border='1' cellpadding='6' style='border-collapse:collapse'>";
    echo "<tr><th>發票號碼</th><th>重複數</th><th>記錄 ID</th><th>動作</th></tr>";
    foreach ($dups as $dup) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($dup['invoice_number']) . "</td>";
        echo "<td>" . $dup['cnt'] . "</td>";
        echo "<td>" . htmlspecialchars($dup['ids']) . "</td>";
        echo "<td>";
        $ids = explode(',', $dup['ids']);
        foreach ($ids as $id) {
            echo "<a href='/sales_invoices.php?action=edit&id=" . (int)$id . "' target='_blank'>編輯 #" . (int)$id . "</a> ";
        }
        echo "</td>";
        echo "</tr>";
    }
    echo "</table>";
    echo "<p>請先處理重複資料（修改或作廢重複的發票號碼）後再執行此 migration。</p>";
    echo "<p><a href='/sales_invoices.php'>返回銷項發票管理</a></p>";
    exit;
}

echo "<p style='color:green'>OK 沒有發現重複的發票號碼</p>";

// 3. 建立 UNIQUE index
echo "<h3>步驟 2：建立 UNIQUE index</h3>";
try {
    $db->exec("ALTER TABLE sales_invoices ADD UNIQUE INDEX uniq_sales_invoice_number (invoice_number)");
    echo "<p style='color:green'>OK 成功建立 UNIQUE INDEX uniq_sales_invoice_number</p>";
    echo "<p>注意：MySQL/MariaDB 的 UNIQUE 索引允許多筆 NULL 值，因此空白的發票號碼仍可存在多筆。</p>";
} catch (Exception $e) {
    echo "<p style='color:red'>ERROR " . htmlspecialchars($e->getMessage()) . "</p>";
    exit;
}

echo "<h3>完成</h3>";
echo "<p>銷項發票號碼唯一性已啟用，新增/編輯時若重複將被擋下。</p>";
echo "<p><a href='/sales_invoices.php'>返回銷項發票管理</a></p>";
