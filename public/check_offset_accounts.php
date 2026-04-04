<?php
header('Content-Type: text/html; charset=utf-8');
try {
    $db = new PDO('mysql:host=localhost;dbname=vhost158992;charset=utf8mb4', 'vhost158992', 'Kss9227456');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    die('DB連線失敗');
}

echo '<h2>立沖科目 × 餘額方向</h2>';

$stmt = $db->query("SELECT account_code, account_name, normal_balance, offset_type, relate_type FROM chart_of_accounts WHERE offset_type = '立沖科目' AND is_active = 1 ORDER BY account_code");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo '<p>共 ' . count($rows) . ' 個立沖科目</p>';
echo '<table border="1" cellpadding="6"><tr><th>科目編號</th><th>科目名稱</th><th>餘額方向</th><th>立沖屬性</th><th>往來類型</th></tr>';
foreach ($rows as $r) {
    echo '<tr>';
    echo '<td>' . htmlspecialchars($r['account_code']) . '</td>';
    echo '<td>' . htmlspecialchars($r['account_name']) . '</td>';
    echo '<td>' . htmlspecialchars($r['normal_balance']) . '</td>';
    echo '<td>' . htmlspecialchars($r['offset_type']) . '</td>';
    echo '<td>' . htmlspecialchars($r['relate_type']) . '</td>';
    echo '</tr>';
}
echo '</table>';

echo '<h3>規則確認</h3>';
echo '<table border="1" cellpadding="6"><tr><th>科目</th><th>餘額方向</th><th>借方輸入=</th><th>貸方輸入=</th></tr>';
foreach ($rows as $r) {
    $nb = $r['normal_balance'];
    echo '<tr>';
    echo '<td>' . htmlspecialchars($r['account_code'] . ' ' . $r['account_name']) . '</td>';
    echo '<td>' . htmlspecialchars($nb) . '</td>';
    echo '<td>' . ($nb === '借方' ? '<b style="color:green">立帳</b>' : '<b style="color:blue">沖帳</b>') . '</td>';
    echo '<td>' . ($nb === '貸方' ? '<b style="color:green">立帳</b>' : '<b style="color:blue">沖帳</b>') . '</td>';
    echo '</tr>';
}
echo '</table>';

echo '<p><a href="accounting.php?action=journals">返回傳票</a></p>';
