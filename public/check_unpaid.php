<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
$db = Database::getInstance();

echo "<h2>完工未收款分析</h2>";
echo "<style>body{font-family:sans-serif;padding:20px;} table{border-collapse:collapse;margin:10px 0;} td,th{border:1px solid #ccc;padding:6px 12px;text-align:left;}</style>";

// 1. 看 closed 案件裡有多少有尾款未收
echo "<h3>目前 status=closed 的案件金額狀態</h3>";
$stmt = $db->query("
    SELECT
        CASE
            WHEN deal_amount IS NULL OR deal_amount = 0 THEN '無成交金額'
            WHEN balance_amount > 0 THEN '有尾款未收'
            WHEN total_collected >= deal_amount AND deal_amount > 0 THEN '已全數收款'
            WHEN total_collected > 0 AND total_collected < deal_amount THEN '部分收款'
            ELSE '未收款(balance=0但未全收)'
        END as payment_status,
        COUNT(*) as cnt,
        SUM(balance_amount) as total_balance
    FROM cases
    WHERE status = 'closed'
    GROUP BY payment_status
    ORDER BY cnt DESC
");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "<table><tr><th>收款狀態</th><th>筆數</th><th>尾款合計</th></tr>";
foreach ($rows as $row) {
    $bal = number_format($row['total_balance']);
    echo "<tr><td>{$row['payment_status']}</td><td>{$row['cnt']}</td><td>\${$bal}</td></tr>";
}
echo "</table>";

// 2. 有尾款的案件樣本
echo "<h3>closed 但有尾款 (balance_amount > 0) 前10筆</h3>";
$stmt = $db->query("SELECT case_number, title, sub_status, deal_amount, balance_amount, total_collected FROM cases WHERE status = 'closed' AND balance_amount > 0 ORDER BY balance_amount DESC LIMIT 10");
$samples = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "<table><tr><th>編號</th><th>標題</th><th>sub_status</th><th>成交金額</th><th>尾款</th><th>已收</th></tr>";
foreach ($samples as $s) {
    echo "<tr><td>{$s['case_number']}</td><td>{$s['title']}</td><td>{$s['sub_status']}</td><td>" . number_format($s['deal_amount']) . "</td><td>" . number_format($s['balance_amount']) . "</td><td>" . number_format($s['total_collected']) . "</td></tr>";
}
echo "</table>";

// 3. 目前 incomplete 的金額狀態
echo "<h3>目前 status=incomplete 的案件金額狀態</h3>";
$stmt = $db->query("
    SELECT
        CASE
            WHEN deal_amount IS NULL OR deal_amount = 0 THEN '無成交金額'
            WHEN balance_amount > 0 THEN '有尾款未收'
            ELSE '其他'
        END as payment_status,
        COUNT(*) as cnt
    FROM cases
    WHERE status = 'incomplete'
    GROUP BY payment_status
    ORDER BY cnt DESC
");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "<table><tr><th>收款狀態</th><th>筆數</th></tr>";
foreach ($rows as $row) {
    echo "<tr><td>{$row['payment_status']}</td><td>{$row['cnt']}</td></tr>";
}
echo "</table>";

// 4. 全部 status 分布（確認）
echo "<h3>目前全部 status 分布</h3>";
$stmt = $db->query("SELECT status, COUNT(*) as cnt FROM cases GROUP BY status ORDER BY cnt DESC");
echo "<table><tr><th>status</th><th>筆數</th></tr>";
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "<tr><td>{$row['status']}</td><td>{$row['cnt']}</td></tr>";
}
echo "</table>";

echo "<p><a href='/cases.php'>返回案件管理</a></p>";
