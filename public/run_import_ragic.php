<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();

if (Session::getUser()['role'] !== 'boss') {
    die('需要管理員權限');
}

set_time_limit(300);
ini_set('memory_limit', '256M');

$db = Database::getInstance();

// 可選擇匯入哪個模組 (1=應收帳款, 2=收款單, 3=應付帳款, 4=付款單, all=全部)
$step = !empty($_GET['step']) ? $_GET['step'] : '';
$clear = !empty($_GET['clear']) ? $_GET['clear'] : '';

echo '<h2>匯入 Ragic 財務資料</h2>';

// 清除模式
if ($clear) {
    echo '<pre>';
    $clearMap = array(
        '1' => array('receivable_items', 'receivables'),
        '2' => array('receipt_items', 'receipts'),
        '3' => array('payable_invoices', 'payable_branches', 'payables'),
        '4' => array('payment_out_vouchers', 'payment_out_branches', 'payments_out'),
    );
    if (!empty($clearMap[$clear])) {
        $db->exec("SET FOREIGN_KEY_CHECKS = 0");
        foreach ($clearMap[$clear] as $t) {
            $db->exec("TRUNCATE TABLE {$t}");
            echo "已清除 {$t}\n";
        }
        $db->exec("SET FOREIGN_KEY_CHECKS = 1");
        echo "\n清除完成!\n";
        echo "<a href='?step={$clear}'>點此重新匯入 Step {$clear}</a>\n";
    }
    echo '</pre>';
    exit;
}

if (empty($step)) {
    // 顯示選單
    echo '<ul>';
    echo '<li><a href="?step=1">Step 1: 匯入應收帳款 (464筆)</a></li>';
    echo '<li><a href="?step=2">Step 2: 匯入收款單 (903筆)</a></li>';
    echo '<li><a href="?step=3">Step 3: 匯入應付帳款單 (96筆)</a></li>';
    echo '<li><a href="?step=4">Step 4: 匯入付款單 (411筆)</a></li>';
    echo '<li><a href="?step=all">全部匯入</a></li>';
    echo '</ul>';
    echo '<p><strong>目前資料量:</strong></p><ul>';

    $tables = array('receivables', 'receivable_items', 'receipts', 'receipt_items', 'payables', 'payable_branches', 'payable_invoices', 'payments_out', 'payment_out_branches', 'payment_out_vouchers');
    foreach ($tables as $t) {
        try {
            $cnt = $db->query("SELECT COUNT(*) FROM {$t}")->fetchColumn();
            echo "<li>{$t}: {$cnt} 筆</li>";
        } catch (Exception $e) {
            echo "<li>{$t}: (表不存在)</li>";
        }
    }
    echo '</ul>';
    exit;
}

echo '<pre>';

$fileMap = array(
    '1' => array('file' => 'import_01_receivables.sql', 'name' => '應收帳款'),
    '2' => array('file' => 'import_02_receipts.sql', 'name' => '收款單'),
    '3' => array('file' => 'import_03_payables.sql', 'name' => '應付帳款單'),
    '4' => array('file' => 'import_04_payments_out.sql', 'name' => '付款單'),
);

if ($step === 'all') {
    $steps = array('1', '2', '3', '4');
} else {
    $steps = array($step);
}

foreach ($steps as $s) {
    if (empty($fileMap[$s])) {
        echo "無效的步驟: {$s}\n";
        continue;
    }

    $info = $fileMap[$s];
    $sqlFile = __DIR__ . '/../database/' . $info['file'];

    if (!file_exists($sqlFile)) {
        echo "❌ SQL 檔案不存在: {$info['file']}\n";
        continue;
    }

    echo "========================================\n";
    echo "匯入 {$info['name']} ...\n";
    echo "========================================\n";

    $sql = file_get_contents($sqlFile);
    $stmts = explode(";\n", $sql);

    $success = 0;
    $errors = 0;
    $skipChildren = false;

    foreach ($stmts as $stmt) {
        $stmt = trim($stmt);
        if (empty($stmt)) continue;
        if (strpos($stmt, '--') === 0) continue;
        if (stripos($stmt, 'SET NAMES') === 0 || stripos($stmt, 'SET @') === 0) {
            $db->exec($stmt);
            continue;
        }
        if (stripos($stmt, 'INSERT INTO') !== 0) continue;

        // 判斷是否為子表 INSERT (使用 LAST_INSERT_ID)
        $isChild = (strpos($stmt, 'LAST_INSERT_ID()') !== false);

        // 如果父記錄失敗，跳過子記錄
        if ($isChild && $skipChildren) {
            continue;
        }

        try {
            $db->exec($stmt);
            $success++;
            if (!$isChild) {
                $skipChildren = false;
            }
        } catch (PDOException $e) {
            $msg = $e->getMessage();
            if (strpos($msg, 'Duplicate entry') !== false) {
                // 跳過重複
            } else {
                if (!$isChild) {
                    // 父記錄失敗，標記跳過後續子記錄
                    $skipChildren = true;
                    echo "⚠️ 父記錄失敗: " . mb_substr($msg, 0, 120) . "\n";
                    echo "   SQL: " . mb_substr($stmt, 0, 100) . "...\n";
                } else {
                    echo "❌ " . mb_substr($msg, 0, 120) . "\n";
                }
                $errors++;
                if ($errors > 50) {
                    echo "錯誤過多，停止匯入此模組\n";
                    break;
                }
            }
        }
    }

    echo "✅ {$info['name']}: 成功 {$success} 筆, 錯誤 {$errors} 筆\n\n";
}

echo "========================================\n";
echo "全部完成!\n";
echo '</pre>';
echo '<p><a href="/run_import_ragic.php">返回</a> | ';
echo '<a href="/receivables.php">應收帳款</a> | ';
echo '<a href="/receipts.php">收款單</a> | ';
echo '<a href="/payables.php">應付帳款</a> | ';
echo '<a href="/payments_out.php">付款單</a></p>';
