<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();

$db = Database::getInstance();
$errors = array();
$success = array();

// 1. 建立 approval_rules 表
try {
    $db->exec("
        CREATE TABLE IF NOT EXISTS approval_rules (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            module VARCHAR(50) NOT NULL COMMENT '模組名',
            rule_name VARCHAR(100) NOT NULL COMMENT '規則名稱',
            min_amount DECIMAL(12,0) DEFAULT 0 COMMENT '最低金額',
            max_amount DECIMAL(12,0) DEFAULT NULL COMMENT '最高金額',
            min_profit_rate DECIMAL(5,2) DEFAULT NULL COMMENT '最低利潤率',
            approver_role VARCHAR(50) DEFAULT NULL COMMENT '簽核人角色',
            approver_id INT UNSIGNED DEFAULT NULL COMMENT '指定簽核人',
            level_order INT NOT NULL DEFAULT 1 COMMENT '簽核順序',
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='簽核規則'
    ");
    $success[] = "approval_rules 表已建立";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'already exists') !== false) {
        $success[] = "approval_rules 表已存在，跳過";
    } else {
        $errors[] = "建立 approval_rules 失敗: " . $e->getMessage();
    }
}

// 2. 建立 approval_flows 表
try {
    $db->exec("
        CREATE TABLE IF NOT EXISTS approval_flows (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            module VARCHAR(50) NOT NULL COMMENT '模組名',
            target_id INT UNSIGNED NOT NULL COMMENT '單據ID',
            rule_id INT UNSIGNED DEFAULT NULL COMMENT '對應規則',
            level_order INT NOT NULL DEFAULT 1 COMMENT '簽核層級',
            approver_id INT UNSIGNED NOT NULL COMMENT '簽核人',
            status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
            comment TEXT DEFAULT NULL COMMENT '簽核意見',
            submitted_by INT UNSIGNED NOT NULL COMMENT '送簽人',
            submitted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            decided_at DATETIME DEFAULT NULL,
            INDEX idx_module_target (module, target_id),
            INDEX idx_approver_status (approver_id, status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='簽核紀錄'
    ");
    $success[] = "approval_flows 表已建立";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'already exists') !== false) {
        $success[] = "approval_flows 表已存在，跳過";
    } else {
        $errors[] = "建立 approval_flows 失敗: " . $e->getMessage();
    }
}

// 3. 擴充 quotations.status 欄位
try {
    $db->exec("ALTER TABLE quotations MODIFY COLUMN status VARCHAR(30) NOT NULL DEFAULT 'draft' COMMENT '狀態'");
    $success[] = "quotations.status 已改為 VARCHAR(30)，支援新狀態";
} catch (PDOException $e) {
    $errors[] = "修改 quotations.status 失敗: " . $e->getMessage();
}

// 4. 將舊的 accepted → customer_accepted, rejected → customer_rejected
try {
    $count1 = $db->exec("UPDATE quotations SET status = 'customer_accepted' WHERE status = 'accepted'");
    $count2 = $db->exec("UPDATE quotations SET status = 'customer_rejected' WHERE status = 'rejected'");
    $success[] = "已轉換 {$count1} 筆 accepted → customer_accepted, {$count2} 筆 rejected → customer_rejected";
} catch (PDOException $e) {
    $errors[] = "轉換舊狀態失敗: " . $e->getMessage();
}

echo "<h2>Migration 039 - 通用簽核系統</h2>";
if ($success) {
    echo "<div style='color:green'><ul>";
    foreach ($success as $s) echo "<li>$s</li>";
    echo "</ul></div>";
}
if ($errors) {
    echo "<div style='color:red'><ul>";
    foreach ($errors as $e) echo "<li>$e</li>";
    echo "</ul></div>";
}
echo "<p><a href='/approvals.php?action=settings'>→ 簽核設定</a> | <a href='/quotations.php'>報價管理</a></p>";
