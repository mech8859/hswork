<?php
require_once __DIR__ . '/../includes/bootstrap.php';
header('Content-Type: text/plain; charset=utf-8');

$db = Database::getInstance();

$cols = array(
    'est_labor_days'   => 'DECIMAL(5,1) DEFAULT NULL COMMENT "預估施工天數"',
    'est_labor_people' => 'INT DEFAULT NULL COMMENT "預估施工人數"',
    'est_labor_hours'  => 'DECIMAL(7,1) DEFAULT NULL COMMENT "預估施工時數"',
);

foreach ($cols as $col => $def) {
    try {
        $db->query("SELECT `{$col}` FROM cases LIMIT 1");
        echo "SKIP: cases.{$col} (already exists)\n";
    } catch (Exception $e) {
        try {
            $db->exec("ALTER TABLE cases ADD COLUMN `{$col}` {$def}");
            echo "OK: cases.{$col}\n";
        } catch (Exception $e2) {
            echo "ERR: cases.{$col} " . $e2->getMessage() . "\n";
        }
    }
}

echo "\nDone!\n";
