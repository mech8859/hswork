<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();

$db = Database::getInstance();
$success = array();
$errors = array();

// 1. work_logs 加 is_completed
try {
    $db->exec("ALTER TABLE work_logs ADD COLUMN is_completed TINYINT(1) NOT NULL DEFAULT 0 COMMENT '是否完工'");
    $success[] = "is_completed 欄位已新增";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate') !== false) {
        $success[] = "is_completed 已存在，跳過";
    } else {
        $errors[] = $e->getMessage();
    }
}

// 2. work_logs 加 next_visit_date
try {
    $db->exec("ALTER TABLE work_logs ADD COLUMN next_visit_date DATE DEFAULT NULL COMMENT '預計下次施工日期'");
    $success[] = "next_visit_date 欄位已新增";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate') !== false) {
        $success[] = "next_visit_date 已存在，跳過";
    } else {
        $errors[] = $e->getMessage();
    }
}

// 3. work_logs 加 next_visit_type
try {
    $db->exec("ALTER TABLE work_logs ADD COLUMN next_visit_type VARCHAR(20) DEFAULT NULL COMMENT 'scheduled或pending'");
    $success[] = "next_visit_type 欄位已新增";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate') !== false) {
        $success[] = "next_visit_type 已存在，跳過";
    } else {
        $errors[] = $e->getMessage();
    }
}

echo "<h2>Migration 041 – 施工回報增強</h2>";
if ($success) { echo "<ul>"; foreach ($success as $s) echo "<li style='color:green'>$s</li>"; echo "</ul>"; }
if ($errors) { echo "<ul>"; foreach ($errors as $e) echo "<li style='color:red'>$e</li>"; echo "</ul>"; }
echo "<p><a href='/schedule.php'>← 工程行事曆</a></p>";
