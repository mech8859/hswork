<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (!Auth::hasPermission('admin') && !in_array(Auth::user()['role'], array('boss','manager'))) {
    die('需要管理員權限');
}

$db = Database::getInstance();
$results = array();

// 1. case_payments 加 is_remitted 欄位
try {
    $cols = $db->query("SHOW COLUMNS FROM case_payments LIKE 'is_remitted'")->fetchAll();
    if (empty($cols)) {
        $db->exec("ALTER TABLE case_payments ADD COLUMN is_remitted TINYINT(1) NOT NULL DEFAULT 0 COMMENT '是否已繳回'");
        $db->exec("ALTER TABLE case_payments ADD COLUMN remit_date DATE DEFAULT NULL COMMENT '繳回日期'");
        $db->exec("ALTER TABLE case_payments ADD COLUMN remit_note VARCHAR(255) DEFAULT '' COMMENT '繳回備註'");
        $db->exec("ALTER TABLE case_payments ADD INDEX idx_is_remitted (is_remitted)");
        $results[] = '<li style="color:green">OK: case_payments 加入 is_remitted, remit_date, remit_note 欄位</li>';
    } else {
        $results[] = '<li style="color:gray">SKIP: is_remitted 欄位已存在</li>';
    }
} catch (Exception $e) {
    if (strpos($e->getMessage(), 'Duplicate') !== false) {
        $results[] = '<li style="color:gray">SKIP: 欄位已存在</li>';
    } else {
        $results[] = '<li style="color:red">ERROR: ' . htmlspecialchars($e->getMessage()) . '</li>';
    }
}

// 2. 現有 case_payments 全部設為未繳回
try {
    $affected = $db->exec("UPDATE case_payments SET is_remitted = 0 WHERE is_remitted IS NULL");
    $results[] = '<li style="color:green">OK: 現有收款紀錄已設為未繳回</li>';
} catch (Exception $e) {
    $results[] = '<li style="color:red">ERROR: ' . htmlspecialchars($e->getMessage()) . '</li>';
}

echo '<h1>Migration 056 結果</h1><ul>' . implode('', $results) . '</ul>';
echo '<p><a href="/remittance.php">前往未繳回帳務</a></p>';
