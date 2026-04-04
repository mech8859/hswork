<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (Auth::user()['role'] !== 'boss') { die('Permission denied'); }
header('Content-Type: text/html; charset=utf-8');
echo '<pre>';

$db = Database::getInstance();

// Step 1: Create table
try {
    $db->exec("
        CREATE TABLE IF NOT EXISTS notification_settings (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            module VARCHAR(50) NOT NULL,
            event VARCHAR(50) NOT NULL,
            condition_field VARCHAR(50) DEFAULT NULL,
            condition_value VARCHAR(100) DEFAULT NULL,
            notify_type ENUM('role','field') NOT NULL,
            notify_target VARCHAR(50) NOT NULL,
            branch_scope ENUM('same','all') NOT NULL DEFAULT 'same',
            title_template VARCHAR(200) NOT NULL,
            message_template TEXT,
            link_template VARCHAR(500) DEFAULT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            sort_order INT NOT NULL DEFAULT 0,
            created_by INT UNSIGNED DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_module_event (module, event, is_active),
            INDEX idx_active (is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "OK: Table created/exists\n";
} catch (Exception $e) {
    echo "ERROR creating table: " . $e->getMessage() . "\n";
}

// Step 2: Check row count
$count = $db->query("SELECT COUNT(*) FROM notification_settings")->fetchColumn();
echo "Current rows: $count\n";

// Step 3: Seed if empty
if ($count == 0) {
    $seeds = array(
        array('receipts','created',NULL,NULL,'role','boss','same','新收款單通知','客戶：{customer_name}，收款金額：NT${total_amount}，狀態：{status}','/receipts.php?action=edit&id={id}',1,1),
        array('receipts','created',NULL,NULL,'role','sales_manager','same','新收款單通知','客戶：{customer_name}，收款金額：NT${total_amount}，狀態：{status}','/receipts.php?action=edit&id={id}',1,2),
        array('receipts','created',NULL,NULL,'role','eng_manager','same','新收款單通知','客戶：{customer_name}，收款金額：NT${total_amount}，狀態：{status}','/receipts.php?action=edit&id={id}',1,3),
        array('receipts','created',NULL,NULL,'field','sales_id','same','新收款單通知','客戶：{customer_name}，收款金額：NT${total_amount}，狀態：{status}','/receipts.php?action=edit&id={id}',1,4),
        array('receipts','status_changed','status','已收款','field','sales_id','same','收款單已收款通知','客戶：{customer_name}，收款金額：NT${total_amount}，狀態已更新為：已收款','/receipts.php?action=edit&id={id}',1,5),
        array('receipts','status_changed','status','已收款','role','sales_assistant','same','收款單已收款通知','客戶：{customer_name}，收款金額：NT${total_amount}，狀態已更新為：已收款','/receipts.php?action=edit&id={id}',1,6),
        array('business_tracking','created',NULL,NULL,'role','sales_manager','same','新進件：{title}','{actor_name} 新增了一筆進件','/business_tracking.php?action=edit&id={id}',1,1),
        array('business_tracking','created',NULL,NULL,'role','sales_assistant','same','新進件：{title}','{actor_name} 新增了一筆進件','/business_tracking.php?action=edit&id={id}',1,2),
        array('business_tracking','status_changed','sub_status','已成交','role','eng_manager','same','案件成交：{title}','業務 {actor_name} 回報案件已成交，需安排排工','/cases.php?action=edit&id={id}',1,3),
        array('business_tracking','status_changed','sub_status','已成交','role','eng_deputy','same','案件成交：{title}','業務 {actor_name} 回報案件已成交，需安排排工','/cases.php?action=edit&id={id}',1,4),
        array('business_tracking','status_changed','sub_status','跨月成交','role','eng_manager','same','案件成交：{title}','業務 {actor_name} 回報案件跨月成交，需安排排工','/cases.php?action=edit&id={id}',1,5),
        array('business_tracking','status_changed','sub_status','跨月成交','role','eng_deputy','same','案件成交：{title}','業務 {actor_name} 回報案件跨月成交，需安排排工','/cases.php?action=edit&id={id}',1,6),
        array('business_tracking','status_changed','sub_status','現簽','role','eng_manager','same','案件成交：{title}','業務 {actor_name} 回報案件現簽成交，需安排排工','/cases.php?action=edit&id={id}',1,7),
        array('business_tracking','status_changed','sub_status','現簽','role','eng_deputy','same','案件成交：{title}','業務 {actor_name} 回報案件現簽成交，需安排排工','/cases.php?action=edit&id={id}',1,8),
        array('business_tracking','status_changed','sub_status','電話報價成交','role','eng_manager','same','案件成交：{title}','業務 {actor_name} 回報電話報價成交，需安排排工','/cases.php?action=edit&id={id}',1,9),
        array('business_tracking','status_changed','sub_status','電話報價成交','role','eng_deputy','same','案件成交：{title}','業務 {actor_name} 回報電話報價成交，需安排排工','/cases.php?action=edit&id={id}',1,10),
        array('business_tracking','assigned',NULL,NULL,'field','sales_id','same','新案件指派：{title}','您已被指派為此案件的承辦業務','/business_tracking.php?action=edit&id={id}',1,11),
        array('worklog','status_changed','status','完工','role','eng_manager','same','完工待簽核：{case_title}','工程人員回報已完工，請確認簽核','/approvals.php',1,1),
    );

    $stmt = $db->prepare("INSERT INTO notification_settings (module, event, condition_field, condition_value, notify_type, notify_target, branch_scope, title_template, message_template, link_template, is_active, sort_order) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
    $inserted = 0;
    foreach ($seeds as $s) {
        try {
            $stmt->execute($s);
            $inserted++;
        } catch (Exception $e) {
            echo "Seed error: " . $e->getMessage() . "\n";
        }
    }
    echo "Seeded: $inserted rows\n";
} else {
    echo "Table already has data, skipping seed.\n";
}

echo '</pre>';
echo '<p><strong>Done.</strong> <a href="/notification_settings.php">前往通知設定</a></p>';
