<?php
require_once __DIR__ . '/../includes/bootstrap.php';
header('Content-Type: text/plain; charset=utf-8');

$db = Database::getInstance();

// 人力時薪 $404（全員平均含勞健保+團保+點工）
$db->prepare("INSERT INTO system_settings (setting_key, setting_value, setting_group) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)")
    ->execute(array('labor_hourly_cost', '404', 'operation'));
echo "labor_hourly_cost = 404\n";

// 營運成本比例 15%（管銷+器具+其他，不含人事）
$db->prepare("INSERT INTO system_settings (setting_key, setting_value, setting_group) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)")
    ->execute(array('operation_cost_rate', '15', 'operation'));
echo "operation_cost_rate = 15%\n";

echo "\n完成！\n";
echo "人力時薪: $404/時（全員平均含勞健保+團保+點工）\n";
echo "營運成本: 15%（房租車油器具等間接費用）\n";
echo "案件利潤公式: 成交金額 - 材料 - (工時×404) - (成交金額×15%)\n";
