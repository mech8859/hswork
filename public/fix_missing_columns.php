<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
$db = Database::getInstance();

echo "<h3>修補缺少的欄位</h3>";

// 檢查 case_site_conditions 表
$cols = array();
$stmt = $db->query("SHOW COLUMNS FROM case_site_conditions");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $cols[] = $row['Field'];
}

$siteNewCols = array(
    'ladder_size' => array("VARCHAR(10) DEFAULT NULL COMMENT '拉梯尺寸(米)'", 'has_ladder_needed'),
    'high_ceiling_height' => array("VARCHAR(20) DEFAULT NULL COMMENT '挑高場所高度(米)'", 'ladder_size'),
    'needs_scissor_lift' => array("TINYINT(1) DEFAULT 0 COMMENT '需要自走車'", 'high_ceiling_height'),
    'scissor_lift_height' => array("VARCHAR(20) DEFAULT NULL COMMENT '自走車米數(8/10/12或自訂)'", 'needs_scissor_lift'),
    'safety_equipment' => array("VARCHAR(255) DEFAULT NULL COMMENT '工安需求(逗號分隔)'", 'scissor_lift_height'),
    'special_requirements' => array("TEXT DEFAULT NULL COMMENT '特殊需求說明'", 'safety_equipment'),
);

$added = 0;
foreach ($siteNewCols as $name => $info) {
    if (in_array($name, $cols)) {
        echo "case_site_conditions.{$name} 已存在 ✓<br>";
    } else {
        $afterCol = in_array($info[1], $cols) ? $info[1] : '';
        $afterSql = $afterCol ? " AFTER `{$afterCol}`" : '';
        $sql = "ALTER TABLE case_site_conditions ADD COLUMN `{$name}` {$info[0]}{$afterSql}";
        try {
            $db->exec($sql);
            echo "新增 case_site_conditions.{$name} ✓<br>";
            $added++;
            $cols[] = $name;
        } catch (Exception $ex) {
            echo "新增 {$name} 失敗: " . $ex->getMessage() . "<br>";
        }
    }
}

echo "<br><b>完成！新增 {$added} 個欄位</b><br>";
echo "<br><a href='/cases.php'>回案件管理</a>";
