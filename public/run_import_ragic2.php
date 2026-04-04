<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();

if (Session::getUser()['role'] !== 'boss') {
    die('需要管理員權限');
}

set_time_limit(300);
ini_set('memory_limit', '256M');

$db = Database::getInstance();

$steps = array(
    5 => array('file' => 'import_05_bank_transactions.sql', 'table' => 'bank_transactions', 'label' => '銀行帳戶交易明細'),
    6 => array('file' => 'import_06_petty_cash.sql',        'table' => 'petty_cash',        'label' => '零用金管理'),
    7 => array('file' => 'import_07_reserve_fund.sql',      'table' => 'reserve_fund',      'label' => '備用金管理'),
    8 => array('file' => 'import_08_cash_details.sql',      'table' => 'cash_details',      'label' => '現金明細'),
);

$clear = !empty($_GET['clear']) ? intval($_GET['clear']) : 0;
$step  = !empty($_GET['step']) ? intval($_GET['step']) : 0;

// Clear mode
if ($clear > 0 && isset($steps[$clear])) {
    $info = $steps[$clear];
    echo '<h2>清除: ' . $info['label'] . '</h2><pre>';
    try {
        $db->exec("TRUNCATE TABLE " . $info['table']);
        echo "✅ 已清除 " . $info['table'] . "\n";
    } catch (PDOException $e) {
        echo "❌ 清除失敗: " . $e->getMessage() . "\n";
    }
    echo '</pre>';
    echo '<p><a href="/run_import_ragic2.php">返回</a></p>';
    exit;
}

// Import step
if ($step > 0 && isset($steps[$step])) {
    $info = $steps[$step];
    $sqlFile = __DIR__ . '/../database/' . $info['file'];

    if (!file_exists($sqlFile)) {
        die('SQL 檔案不存在: ' . $info['file']);
    }

    echo '<h2>匯入: ' . $info['label'] . '</h2><pre>';

    $sql = file_get_contents($sqlFile);
    $statements = array_filter(array_map('trim', explode(";\n", $sql)));

    $success = 0;
    $errors = 0;

    foreach ($statements as $stmt) {
        $stmt = trim($stmt);
        if (empty($stmt) || strpos($stmt, '--') === 0) continue;
        // Remove trailing semicolons
        $stmt = rtrim($stmt, ';');
        if (empty($stmt)) continue;

        try {
            $db->exec($stmt);
            $success++;
        } catch (PDOException $e) {
            $msg = $e->getMessage();
            echo "❌ 失敗: " . mb_substr($stmt, 0, 80) . "...\n   " . mb_substr($msg, 0, 120) . "\n";
            $errors++;
        }
    }

    echo "\n完成: {$success} 成功, {$errors} 失敗\n";
    echo '</pre>';
    echo '<p><a href="/run_import_ragic2.php">返回</a></p>';
    exit;
}

// Index page
echo '<h2>Ragic 資料匯入 (第二批)</h2>';
echo '<table border="1" cellpadding="8" cellspacing="0"><tr><th>步驟</th><th>模組</th><th>SQL 檔案</th><th>操作</th></tr>';
foreach ($steps as $num => $info) {
    $fileExists = file_exists(__DIR__ . '/../database/' . $info['file']);
    echo '<tr>';
    echo '<td>' . $num . '</td>';
    echo '<td>' . $info['label'] . '</td>';
    echo '<td>' . $info['file'] . ($fileExists ? ' ✅' : ' ❌') . '</td>';
    echo '<td>';
    if ($fileExists) {
        echo '<a href="?step=' . $num . '">匯入</a> | ';
        echo '<a href="?clear=' . $num . '" onclick="return confirm(\'確定清除?\')">清除</a>';
    } else {
        echo '檔案不存在';
    }
    echo '</td>';
    echo '</tr>';
}
echo '</table>';

// Show current counts
echo '<h3>目前資料量</h3><pre>';
foreach ($steps as $num => $info) {
    try {
        $count = $db->query("SELECT COUNT(*) FROM " . $info['table'])->fetchColumn();
        echo $info['label'] . ": " . $count . " 筆\n";
    } catch (PDOException $e) {
        echo $info['label'] . ": 表不存在\n";
    }
}
echo '</pre>';
echo '<p><a href="/bank_transactions.php">銀行帳戶明細</a> | <a href="/petty_cash.php">零用金管理</a> | <a href="/reserve_fund.php">備用金管理</a> | <a href="/cash_details.php">現金明細</a></p>';
