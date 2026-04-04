<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
$db = Database::getInstance();
echo "<h2>客戶×案件關聯檢查</h2>";
echo "<style>body{font-family:sans-serif;padding:20px;} table{border-collapse:collapse;margin:10px 0;} td,th{border:1px solid #ccc;padding:6px 12px;}</style>";

// 案件 customer_id 統計
$stmt = $db->query("SELECT COUNT(*) as total, SUM(CASE WHEN customer_id IS NOT NULL AND customer_id > 0 THEN 1 ELSE 0 END) as has_id, SUM(CASE WHEN customer_id IS NULL OR customer_id = 0 THEN 1 ELSE 0 END) as no_id FROM cases");
$r = $stmt->fetch(PDO::FETCH_ASSOC);
echo "<h3>案件 customer_id 統計</h3>";
echo "<p>總案件: {$r['total']}，有 customer_id: {$r['has_id']}，無 customer_id: {$r['no_id']}</p>";

// 有案件客戶數
$stmt = $db->query("SELECT COUNT(DISTINCT customer_id) as cnt FROM cases WHERE customer_id IS NOT NULL AND customer_id > 0");
echo "<p>有案件的客戶數（DISTINCT customer_id）: " . $stmt->fetchColumn() . "</p>";

// 客戶表有案件的
$stmt = $db->query("SELECT COUNT(*) FROM customers c WHERE EXISTS (SELECT 1 FROM cases WHERE customer_id = c.id)");
echo "<p>客戶表中有對應案件的: " . $stmt->fetchColumn() . "</p>";

// 前10筆有 customer_id 的案件
echo "<h3>有 customer_id 的案件樣本（前10筆）</h3>";
$stmt = $db->query("SELECT c.id, c.case_number, c.customer_name, c.customer_id, cu.name as customer_table_name FROM cases c LEFT JOIN customers cu ON c.customer_id = cu.id WHERE c.customer_id IS NOT NULL AND c.customer_id > 0 LIMIT 10");
echo "<table><tr><th>案件ID</th><th>編號</th><th>customer_name</th><th>customer_id</th><th>客戶表name</th></tr>";
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "<tr><td>{$row['id']}</td><td>{$row['case_number']}</td><td>{$row['customer_name']}</td><td>{$row['customer_id']}</td><td>" . ($row['customer_table_name'] ?: '<span style="color:red">NOT FOUND</span>') . "</td></tr>";
}
echo "</table>";

// 客戶管理的「有案件客戶」怎麼算的
echo "<h3>客戶管理統計卡片的 SQL 邏輯</h3>";
$stmt = $db->query("SELECT COUNT(*) FROM customers WHERE id IN (SELECT DISTINCT customer_id FROM cases WHERE customer_id IS NOT NULL)");
echo "<p>customers WHERE id IN (SELECT customer_id FROM cases): " . $stmt->fetchColumn() . "</p>";

echo "<p><a href='/customers.php'>返回客戶管理</a></p>";
