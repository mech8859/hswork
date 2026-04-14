<?php
require_once __DIR__ . '/../includes/bootstrap.php';
header('Content-Type: text/plain; charset=utf-8');

$db = Database::getInstance();

$db->prepare("INSERT INTO system_settings (setting_key, setting_value, setting_group) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)")
    ->execute(array('labor_hourly_cost', '560', 'operation'));
echo "labor_hourly_cost = 560\n";

$db->prepare("INSERT INTO system_settings (setting_key, setting_value, setting_group) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)")
    ->execute(array('operation_cost_mode', 'labor_ratio', 'operation'));
echo "operation_cost_mode = labor_ratio\n";

$db->prepare("INSERT INTO system_settings (setting_key, setting_value, setting_group) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)")
    ->execute(array('operation_cost_rate', '128', 'operation'));
echo "operation_cost_rate = 128\n";

echo "\nDone!\n";
echo "hourly: $560, mode: labor_ratio, rate: 128%\n";
