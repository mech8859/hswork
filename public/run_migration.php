<?php
/**
 * 一次性遷移腳本 - 執行完請刪除
 */
require_once __DIR__ . '/../includes/bootstrap.php';

// 安全檢查：只有管理員可執行
if (!Session::isLoggedIn() || Session::getUser()['role'] !== 'boss') {
    die('需要管理員權限');
}

$db = Database::getInstance();
$results = array();

try {
    // Migration 007: 技能分類重新規劃
    $db->exec("ALTER TABLE `skills` ADD COLUMN `skill_group` VARCHAR(50) DEFAULT '系統安裝技能' AFTER `category`");
    $results[] = '007: 新增 skill_group 欄位 OK';
} catch (Exception $e) {
    $results[] = '007: skill_group - ' . $e->getMessage();
}

try {
    $db->exec("ALTER TABLE `skills` ADD COLUMN `sort_order` INT UNSIGNED DEFAULT 0 AFTER `skill_group`");
    $results[] = '007: 新增 sort_order 欄位 OK';
} catch (Exception $e) {
    $results[] = '007: sort_order - ' . $e->getMessage();
}

// 設定現有技能的 skill_group
$db->exec("UPDATE `skills` SET `skill_group` = '系統安裝技能' WHERE `category` IN ('監控','門禁','電子鎖','對講','網路','電話','廣播','車辨')");
$db->exec("UPDATE `skills` SET `skill_group` = '設備安裝技能', `category` = '管路施工' WHERE `category` = '管線'");
$db->exec("UPDATE `skills` SET `skill_group` = '設備安裝技能' WHERE `category` = '特殊'");
$results[] = '007: 更新現有技能 skill_group OK';

// 新增對講細分技能
$newSkills = array(
    array('Hometake對講系統安裝查修', '對講', '系統安裝技能'),
    array('BBhome對講系統安裝查修', '對講', '系統安裝技能'),
    array('大華對講系統安裝查修', '對講', '系統安裝技能'),
    array('類比系統安裝維修', '監控', '系統安裝技能'),
    array('大華數位系統安裝維修', '監控', '系統安裝技能'),
    array('海康數位系統安裝維修', '監控', '系統安裝技能'),
    array('宇視數位系統安裝維修', '監控', '系統安裝技能'),
    array('TPLink系統維護安裝維修', '監控', '系統安裝技能'),
    array('壓條施工', '管路施工', '設備安裝技能'),
    array('RC穿牆', '管路施工', '設備安裝技能'),
    array('鋁製線槽配置', '管路施工', '設備安裝技能'),
    array('吊管工程', '管路施工', '設備安裝技能'),
    array('切地埋管', '管路施工', '設備安裝技能'),
    array('架空作業', '管路施工', '設備安裝技能'),
    array('應對溝通協調能力', '通用能力', '通用能力'),
    array('施工速度', '通用能力', '通用能力'),
    array('查修能力', '通用能力', '通用能力'),
    array('領導統籌能力', '通用能力', '通用能力'),
);

$stmt = $db->prepare('INSERT IGNORE INTO skills (name, category, skill_group) VALUES (?, ?, ?)');
foreach ($newSkills as $sk) {
    $stmt->execute($sk);
}
$results[] = '007: 新增技能項目 OK';

// 更新管線名稱
$db->exec("UPDATE `skills` SET `name` = 'PVC管路施工' WHERE `name` = 'PVC管線施工'");
$db->exec("UPDATE `skills` SET `name` = 'EMT管路施工' WHERE `name` = 'EMT管線施工'");
$db->exec("UPDATE `skills` SET `name` = 'RSG管路施工' WHERE `name` = 'RSG管線施工'");
$results[] = '007: 更新管線名稱 OK';

// Migration 008: 出勤表
try {
    $db->exec("
        CREATE TABLE IF NOT EXISTS `engineer_attendance` (
          `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          `user_id` INT UNSIGNED NOT NULL,
          `attendance_date` DATE NOT NULL,
          `created_by` INT UNSIGNED DEFAULT NULL,
          `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          UNIQUE KEY `uk_user_date` (`user_id`, `attendance_date`),
          FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
          FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='工程人員出勤紀錄'
    ");
    $results[] = '008: 建立 engineer_attendance 表 OK';
} catch (Exception $e) {
    $results[] = '008: ' . $e->getMessage();
}

echo '<h2>遷移結果</h2><ul>';
foreach ($results as $r) {
    echo '<li>' . htmlspecialchars($r) . '</li>';
}
echo '</ul>';
echo '<p style="color:red;font-weight:bold">遷移完成後請刪除此檔案！</p>';
