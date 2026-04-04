<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
$db = Database::getInstance();

echo "<h3>Migration 024: 案件金額欄位</h3>";

$step = isset($_GET['step']) ? $_GET['step'] : 'preview';

if ($step === 'preview') {
    echo "<p>將新增以下欄位到 cases 表：</p>";
    echo "<ul>";
    echo "<li>deal_amount - 成交金額(未稅)</li>";
    echo "<li>is_tax_included - 是否含稅</li>";
    echo "<li>tax_amount - 稅金</li>";
    echo "<li>total_amount - 含稅金額</li>";
    echo "<li>deposit_amount - 訂金金額</li>";
    echo "<li>deposit_payment_date - 訂金付款日</li>";
    echo "<li>deposit_method - 訂金支付方式</li>";
    echo "<li>balance_amount - 尾款</li>";
    echo "<li>completion_amount - 完工金額(含稅)</li>";
    echo "<li>total_collected - 總收款金額</li>";
    echo "</ul>";
    echo "<a href='?step=migrate' style='background:#4CAF50;color:#fff;padding:10px 20px;border-radius:6px;text-decoration:none'>執行 Migration</a>";
    echo " <a href='?step=import' style='background:#2196F3;color:#fff;padding:10px 20px;border-radius:6px;text-decoration:none;margin-left:10px'>匯入金額資料</a>";
    exit;
}

if ($step === 'migrate') {
    // Check if columns exist
    $cols = array();
    $stmt = $db->query("SHOW COLUMNS FROM cases");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $cols[] = $row['Field'];
    }

    $newCols = array(
        'deal_amount' => "DECIMAL(12,0) DEFAULT NULL COMMENT '成交金額(未稅)'",
        'is_tax_included' => "VARCHAR(30) DEFAULT NULL COMMENT '是否含稅'",
        'tax_amount' => "DECIMAL(12,0) DEFAULT NULL COMMENT '稅金'",
        'total_amount' => "DECIMAL(12,0) DEFAULT NULL COMMENT '含稅金額'",
        'deposit_amount' => "DECIMAL(12,0) DEFAULT NULL COMMENT '訂金金額'",
        'deposit_payment_date' => "DATE DEFAULT NULL COMMENT '訂金付款日'",
        'deposit_method' => "VARCHAR(30) DEFAULT NULL COMMENT '訂金支付方式'",
        'balance_amount' => "DECIMAL(12,0) DEFAULT NULL COMMENT '尾款'",
        'completion_amount' => "DECIMAL(12,0) DEFAULT NULL COMMENT '完工金額(含稅)'",
        'total_collected' => "DECIMAL(12,0) DEFAULT NULL COMMENT '總收款金額'",
    );

    $added = 0;
    $after = 'quote_amount';
    foreach ($newCols as $name => $def) {
        if (in_array($name, $cols)) {
            echo "欄位 {$name} 已存在，跳過<br>";
        } else {
            $sql = "ALTER TABLE cases ADD COLUMN `{$name}` {$def} AFTER `{$after}`";
            $db->exec($sql);
            echo "新增欄位 {$name} ✓<br>";
            $added++;
        }
        $after = $name;
    }

    echo "<br><b>完成！新增 {$added} 個欄位</b><br><br>";
    echo "<a href='?step=import'>下一步：匯入金額資料</a>";
    exit;
}

if ($step === 'import') {
    $jsonFile = __DIR__ . '/../cases_financial_import.json';
    if (!file_exists($jsonFile)) {
        echo "<p style='color:red'>找不到 cases_financial_import.json</p>";
        exit;
    }

    $data = json_decode(file_get_contents($jsonFile), true);
    echo "JSON 載入 " . count($data) . " 筆記錄<br><br>";

    $sql = "UPDATE cases SET
        quote_amount = COALESCE(?, quote_amount),
        deal_amount = ?,
        is_tax_included = ?,
        tax_amount = ?,
        total_amount = ?,
        deposit_amount = ?,
        deposit_payment_date = ?,
        deposit_method = ?,
        balance_amount = ?,
        completion_amount = ?,
        total_collected = ?
        WHERE case_number = ?";
    $stmt = $db->prepare($sql);

    $updated = 0;
    $skipped = 0;
    foreach ($data as $r) {
        $stmt->execute(array(
            $r['quote_amount'],
            $r['deal_amount'],
            $r['is_tax_included'],
            $r['tax_amount'],
            $r['total_amount'],
            $r['deposit_amount'],
            $r['deposit_payment_date'],
            $r['deposit_method'],
            $r['balance_amount'],
            $r['completion_amount'],
            $r['total_collected'],
            $r['case_number']
        ));
        if ($stmt->rowCount() > 0) {
            $updated++;
        } else {
            $skipped++;
        }
    }

    echo "<b>更新: {$updated} | 跳過: {$skipped}</b><br><br>";

    // Show stats
    $check = $db->query("SELECT
        COUNT(*) as total,
        SUM(CASE WHEN deal_amount IS NOT NULL AND deal_amount > 0 THEN 1 ELSE 0 END) as has_deal,
        SUM(CASE WHEN total_amount IS NOT NULL AND total_amount > 0 THEN 1 ELSE 0 END) as has_total,
        SUM(CASE WHEN deposit_amount IS NOT NULL AND deposit_amount > 0 THEN 1 ELSE 0 END) as has_deposit,
        SUM(CASE WHEN balance_amount IS NOT NULL AND balance_amount > 0 THEN 1 ELSE 0 END) as has_balance,
        SUM(CASE WHEN total_collected IS NOT NULL AND total_collected > 0 THEN 1 ELSE 0 END) as has_collected
        FROM cases")->fetch(PDO::FETCH_ASSOC);

    echo "<b>資料統計：</b><br>";
    echo "案件總數: {$check['total']}<br>";
    echo "有成交金額: {$check['has_deal']}<br>";
    echo "有含稅金額: {$check['has_total']}<br>";
    echo "有訂金: {$check['has_deposit']}<br>";
    echo "有尾款: {$check['has_balance']}<br>";
    echo "有收款: {$check['has_collected']}<br>";

    echo "<br><a href='/cases.php'>回案件管理</a>";
}
