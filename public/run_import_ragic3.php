<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();

if (Session::getUser()['role'] !== 'boss') {
    die('需要管理員權限');
}

set_time_limit(600);
ini_set('memory_limit', '512M');

$db = Database::getInstance();

$steps = array(
    9  => array('file' => 'import_09_vendors.sql',         'table' => 'vendors',             'label' => '廠商資料'),
    10 => array('file' => 'import_10_inventory.sql',       'table' => 'inventory',           'label' => '庫存明細'),
    11 => array('file' => 'import_11_requisitions.sql',    'table' => 'requisitions',        'label' => '請購單'),
    12 => array('file' => 'import_12_purchase_orders.sql', 'table' => 'purchase_orders',     'label' => '採購單'),
    13 => array('file' => 'import_13_transfers.sql',       'table' => 'warehouse_transfers', 'label' => '倉庫調撥'),
);

$clear = !empty($_GET['clear']) ? intval($_GET['clear']) : 0;
$step  = !empty($_GET['step']) ? intval($_GET['step']) : 0;

// Clear mode
if ($clear > 0 && isset($steps[$clear])) {
    $info = $steps[$clear];
    echo '<h2>清除: ' . $info['label'] . '</h2><pre>';
    try {
        $db->exec("DELETE FROM " . $info['table']);
        echo "✅ 已清除 " . $info['table'] . "\n";
    } catch (PDOException $e) {
        echo "❌ 清除失敗: " . $e->getMessage() . "\n";
    }
    echo '</pre>';
    echo '<p><a href="/run_import_ragic3.php">返回</a></p>';
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
    $skipped = 0;

    foreach ($statements as $stmt) {
        $stmt = trim($stmt);
        if (empty($stmt) || strpos($stmt, '--') === 0) continue;
        $stmt = rtrim($stmt, ';');
        if (empty($stmt)) continue;

        try {
            $affected = $db->exec($stmt);
            if ($affected > 0) {
                $success++;
            } else {
                $skipped++;
            }
        } catch (PDOException $e) {
            $msg = $e->getMessage();
            if (strpos($msg, 'Duplicate') !== false) {
                $skipped++;
            } else {
                echo "❌ " . mb_substr($stmt, 0, 100) . "...\n   " . mb_substr($msg, 0, 150) . "\n";
                $errors++;
            }
        }
    }

    echo "\n完成: {$success} 成功, {$skipped} 跳過, {$errors} 失敗\n";
    echo '</pre>';
    echo '<p><a href="/run_import_ragic3.php">返回</a></p>';
    exit;
}

// Index page
echo '<h2>Ragic 資料匯入 (採購庫存)</h2>';
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
    echo '</td></tr>';
}
echo '</table>';

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
