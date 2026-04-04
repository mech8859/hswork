<?php
/**
 * Migration 067: 角色預設權限管理
 * 1. system_roles 加 default_permissions, default_case_sections, default_reports 欄位
 * 2. 從 config/app.php 寫入現有預設值
 */
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
Auth::requireRole('boss');

$db = Database::getInstance();
$results = array();

// Step 1: ALTER system_roles — 加欄位
$columns = array(
    'default_permissions'   => "TEXT DEFAULT NULL COMMENT '模組權限JSON'",
    'default_case_sections' => "TEXT DEFAULT NULL COMMENT '案件區域JSON'",
    'default_reports'       => "TEXT DEFAULT NULL COMMENT '報表存取JSON'",
);

foreach ($columns as $col => $def) {
    try {
        $db->exec("ALTER TABLE `system_roles` ADD COLUMN `{$col}` {$def}");
        $results[] = "[OK] 已新增欄位 system_roles.{$col}";
    } catch (Exception $e) {
        $results[] = "[SKIP] system_roles.{$col}: " . $e->getMessage();
    }
}

// Step 2: 從 config 寫入預設值
$appConfig = require __DIR__ . '/../config/app.php';
$configPerms = isset($appConfig['permissions']) ? $appConfig['permissions'] : array();
$configSections = isset($appConfig['case_section_defaults']) ? $appConfig['case_section_defaults'] : array();
$configReports = isset($appConfig['report_defaults']) ? $appConfig['report_defaults'] : array();

$stmt = $db->query("SELECT id, role_key FROM system_roles");
$roles = $stmt->fetchAll(PDO::FETCH_ASSOC);

$updateCount = 0;
foreach ($roles as $role) {
    $key = $role['role_key'];

    // 解析 config 權限為結構化格式
    $permsData = array();
    if (isset($configPerms[$key])) {
        foreach ($configPerms[$key] as $p) {
            if ($p === 'all') {
                $permsData['_all'] = true;
                continue;
            }
            $parts = explode('.', $p);
            if (count($parts) === 2) {
                $mod = $parts[0];
                $level = $parts[1];
                if ($level === 'delete') {
                    $permsData['delete_' . $mod] = true;
                } else {
                    $permsData[$mod] = $p;
                }
            }
        }
    }

    $sectionsData = isset($configSections[$key]) ? $configSections[$key] : array();
    $reportsData = isset($configReports[$key]) ? $configReports[$key] : array();

    $upd = $db->prepare("UPDATE system_roles SET default_permissions = ?, default_case_sections = ?, default_reports = ? WHERE id = ?");
    $upd->execute(array(
        json_encode($permsData, JSON_UNESCAPED_UNICODE),
        json_encode($sectionsData, JSON_UNESCAPED_UNICODE),
        json_encode($reportsData, JSON_UNESCAPED_UNICODE),
        $role['id']
    ));
    $updateCount++;
}
$results[] = "[OK] 已寫入 {$updateCount} 個角色的預設權限";

// Output
header('Content-Type: text/html; charset=utf-8');
echo '<!DOCTYPE html><html><head><title>Migration 067</title></head><body>';
echo '<h2>Migration 067: 角色預設權限管理</h2><ul>';
foreach ($results as $r) {
    $color = strpos($r, '[OK]') === 0 ? 'green' : (strpos($r, '[SKIP]') === 0 ? 'orange' : 'red');
    echo '<li style="color:' . $color . '">' . htmlspecialchars($r) . '</li>';
}
echo '</ul>';
echo '<p><a href="/dropdown_options.php?tab=roles">前往角色管理</a></p>';
echo '</body></html>';
