<?php
/**
 * Migration 145：holidays 國定假日表（給 MOA 出勤明細顯示用）
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
Auth::requireRole('boss');
header('Content-Type: text/plain; charset=utf-8');

$db = Database::getInstance();
try {
    $exists = $db->query("SHOW TABLES LIKE 'holidays'")->fetch();
    if ($exists) {
        echo "[skip] holidays 已存在\n";
    } else {
        $db->exec("CREATE TABLE holidays (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            holiday_date DATE NOT NULL,
            name VARCHAR(50) NOT NULL COMMENT '節日名稱',
            is_workday TINYINT(1) DEFAULT 0 COMMENT '0=放假/1=補班日',
            note VARCHAR(255) DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uk_date (holiday_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='國定假日'");
        echo "OK: holidays 已建立\n";

        // 2026 台灣國定假日（依行政院公告；補班日另記）
        $seed = array(
            array('2026-01-01', '元旦', 0),
            array('2026-02-16', '春節除夕', 0),
            array('2026-02-17', '春節', 0),
            array('2026-02-18', '春節', 0),
            array('2026-02-19', '春節', 0),
            array('2026-02-20', '春節', 0),
            array('2026-02-28', '和平紀念日', 0),
            array('2026-04-03', '兒童節', 0),
            array('2026-04-06', '清明節（補假）', 0),
            array('2026-05-01', '勞動節', 0),
            array('2026-06-19', '端午節', 0),
            array('2026-09-25', '中秋節', 0),
            array('2026-10-09', '國慶日（補假）', 0),
            array('2026-10-10', '國慶日', 0),
        );
        $ins = $db->prepare("INSERT IGNORE INTO holidays (holiday_date, name, is_workday) VALUES (?, ?, ?)");
        foreach ($seed as $h) { $ins->execute($h); }
        echo "OK: 2026 國定假日 seed 完成（" . count($seed) . " 筆）\n";
    }
    AuditLog::log('attendance', 'migration', 0, 'Migration 145: holidays');
    echo "Migration 145 done.\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
