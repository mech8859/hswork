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

echo "<br><b>case_site_conditions 新增 {$added} 個欄位</b><br>";

// 檢查 product_categories 表
echo "<h3>product_categories 表</h3>";
$pcCols = array();
$pcStmt = $db->query("SHOW COLUMNS FROM product_categories");
while ($row = $pcStmt->fetch(PDO::FETCH_ASSOC)) {
    $pcCols[] = $row['Field'];
}
if (!in_array('exclude_from_stockout', $pcCols)) {
    try {
        $db->exec("ALTER TABLE product_categories ADD COLUMN `exclude_from_stockout` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '不進出庫單' AFTER `sort`");
        echo "新增 exclude_from_stockout ✓<br>";
    } catch (Exception $ex) {
        echo "失敗: " . $ex->getMessage() . "<br>";
    }
} else {
    echo "exclude_from_stockout 已存在 ✓<br>";
}
if (!in_array('is_non_inventory', $pcCols)) {
    try {
        $db->exec("ALTER TABLE product_categories ADD COLUMN `is_non_inventory` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '非庫存(不出現在入庫/出庫/採購搜尋)' AFTER `exclude_from_stockout`");
        echo "新增 is_non_inventory ✓<br>";
    } catch (Exception $ex) {
        echo "失敗: " . $ex->getMessage() . "<br>";
    }
} else {
    echo "is_non_inventory 已存在 ✓<br>";
}

// 檢查 case_billing_items 表
echo "<h3>case_billing_items 表</h3>";
$biCols = array();
$biStmt = $db->query("SHOW COLUMNS FROM case_billing_items");
while ($row = $biStmt->fetch(PDO::FETCH_ASSOC)) {
    $biCols[] = $row['Field'];
}
if (!in_array('attachment_path', $biCols)) {
    try {
        $db->exec("ALTER TABLE case_billing_items ADD COLUMN `attachment_path` VARCHAR(500) DEFAULT NULL COMMENT '附件路徑(JSON)' AFTER `note`");
        echo "新增 attachment_path ✓<br>";
    } catch (Exception $ex) {
        echo "失敗: " . $ex->getMessage() . "<br>";
    }
} else {
    echo "attachment_path 已存在 ✓<br>";
}

// 檢查 cases 表：no_equipment
echo "<h3>cases 表</h3>";
$cCols = array();
$cStmt = $db->query("SHOW COLUMNS FROM cases");
while ($row = $cStmt->fetch(PDO::FETCH_ASSOC)) {
    $cCols[] = $row['Field'];
}
if (!in_array('no_equipment', $cCols)) {
    try {
        $db->exec("ALTER TABLE cases ADD COLUMN `no_equipment` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '此案件無使用設備(不需出庫)'");
        echo "新增 no_equipment ✓<br>";
    } catch (Exception $ex) {
        echo "失敗: " . $ex->getMessage() . "<br>";
    }
} else {
    echo "no_equipment 已存在 ✓<br>";
}

echo "<br><a href='/cases.php'>回案件管理</a> | <a href='/products.php?action=categories'>產品分類</a>";
