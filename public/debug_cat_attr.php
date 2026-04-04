<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
$db = Database::getInstance();

echo "<h2>科目類別 vs 科目屬性 對照表</h2>";
echo "<table border=1 cellpadding=5 cellspacing=0>";
echo "<tr><th>cat_code</th><th>cat_name</th><th>attr</th><th>attr hex</th></tr>";

$rows = $db->query("SELECT DISTINCT cat_code, cat_name, attr FROM chart_of_accounts WHERE cat_code IS NOT NULL AND cat_code != '' ORDER BY cat_code")->fetchAll(PDO::FETCH_ASSOC);

$attrOptions = array('非收入費用','營業收入','營業成本','營業費用','營業外收入','營業外支出');

foreach ($rows as $r) {
    $attr = $r['attr'] ?: '';
    $match = in_array($attr, $attrOptions) ? '✓' : '<b style="color:red">✗ 不匹配</b>';
    echo "<tr>";
    echo "<td>" . htmlspecialchars($r['cat_code']) . "</td>";
    echo "<td>" . htmlspecialchars($r['cat_name']) . "</td>";
    echo "<td>" . htmlspecialchars($attr) . " " . $match . "</td>";
    echo "<td>" . bin2hex($attr) . "</td>";
    echo "</tr>";
}
echo "</table>";
