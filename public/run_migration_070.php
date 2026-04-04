<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (Auth::user()['role'] !== 'boss') { die('Permission denied'); }
header('Content-Type: text/html; charset=utf-8');
echo '<pre>';

$db = Database::getInstance();

// 檢查欄位是否已存在
$cols = $db->query("SHOW COLUMNS FROM case_payments LIKE 'approval_status'")->fetchAll();
if (empty($cols)) {
    try {
        $db->exec("ALTER TABLE case_payments ADD COLUMN approval_status VARCHAR(20) DEFAULT NULL COMMENT 'pending/approved/rejected, NULL=不需簽核' AFTER image_path");
        echo "OK: approval_status column added\n";
    } catch (Exception $e) {
        echo "ERROR: " . $e->getMessage() . "\n";
    }
} else {
    echo "Column approval_status already exists, skipping.\n";
}

echo '</pre>';
echo '<p><strong>Done.</strong> <a href="/cases.php">返回案件管理</a></p>';
