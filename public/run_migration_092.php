<?php
/**
 * Migration 092: dropdown_options 加 parent_id + 付款單主/子分類種子資料
 */
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (Auth::user()['role'] !== 'boss') { die('Permission denied'); }
header('Content-Type: text/plain; charset=utf-8');

$db = Database::getInstance();
$results = array();

// 1. 加 parent_id 欄位
try {
    $db->exec("ALTER TABLE dropdown_options ADD COLUMN parent_id INT UNSIGNED DEFAULT NULL COMMENT '父選項ID' AFTER category");
    $results[] = "OK: parent_id 欄位已新增";
} catch (Exception $e) {
    if (strpos($e->getMessage(), 'Duplicate column') !== false) {
        $results[] = "SKIP: parent_id 已存在";
    } else {
        $results[] = "ERR: " . $e->getMessage();
    }
}

// 2. 確保 option_key 欄位存在
try {
    $db->exec("ALTER TABLE dropdown_options ADD COLUMN option_key VARCHAR(100) DEFAULT NULL AFTER category");
    $results[] = "OK: option_key 欄位已新增";
} catch (Exception $e) {
    if (strpos($e->getMessage(), 'Duplicate column') !== false) {
        $results[] = "SKIP: option_key 已存在";
    } else {
        $results[] = "ERR: " . $e->getMessage();
    }
}

// 3. 檢查是否已有付款分類資料
$existing = $db->query("SELECT COUNT(*) FROM dropdown_options WHERE category = 'payment_main_category'")->fetchColumn();
if ($existing > 0) {
    $results[] = "SKIP: payment_main_category 已有 {$existing} 筆資料，不重複寫入";
    echo implode("\n", $results);
    exit;
}

// 4. 寫入主分類 + 子分類
$categories = array(
    '銀行借款' => array('本金加利息(還款)', '利息'),
    '辦公設備' => array('電腦軟體', '電腦設備'),
    '資產設備/辦公設備' => array('電腦設備', '辦公設備', '廠房及設備', '冷氣、空調設備'),
    '電子鎖相關支出' => array('電子鎖材料費', '電子鎖安裝費', '電子鎖維修費', '電子鎖更換費', '電子鎖耗材', '電子鎖硬件成本', '電子鎖設備備品', '電子鎖專案成本', '電子鎖其他支出'),
    '其他費用' => array('贊助禮品', '轉零用金', '退還客戶', '毀約退費', '電子鎖材料費', '電子鎖安裝費', '電子鎖維修費', '電子鎖更換費', '電子鎖耗材', '電子鎖硬件成本', '電子鎖設備備品', '電子鎖專案成本', '電子鎖其他支出', '其他費用'),
    '非公司帳款' => array('非公司帳務支出'),
    '員工福利與娛樂費用' => array('員工各項補助', '慰問金', '育訓練生日禮金', '周年禮金', '祝贈品', '春節活動', '團康活動', '公司尾牙', '教育訓練'),
    '外包與工班費用' => array('臨時工費', '工程外包費', '施工費用', '點工費用', '代工費用'),
    '權利金與特殊費用' => array('高雄獎品或權利金', '高雄權利金', '彰化權利金', '台中權利金'),
    '廣告與行銷費用' => array('高雄縣/廣告費', '在地網站廣告', 'GOOGLE廣告', 'YouTube行銷', 'FB廣告', '廣告費'),
    '員工借支' => array('員工借支'),
    '稅捐與專業費用' => array('NCC送審費', '扣繳申報費', '會務費', '營業稅', '會計師代辦費', '會計師記帳費'),
    '交際費' => array('廠商交際費', '客戶交際費'),
    '房租／水電／管理費' => array('水電費', '房租保證金', '房租', '電話費', '保全費'),
    '設備／工具／維修費用' => array('檢修設備', '五金材料配件', '水電材料', '設備改裝', '耗材設備', '電鑽設備', '維修費', '工具維修', '工具購買', '耗材費用', '雜項購置'),
    '勞健保與保險' => array('工程保險', '旅遊平安險', '雇主責任保險', '團保', '勞退', '健保', '勞保'),
    '薪資與獎金' => array('分紅支出', '獎金支出', '薪水支出'),
    '文具／辦公／日常用品' => array('辦公室用品雜物', '保全費用', '夾鏈袋', '生活用品', '日常支出', '文具用品'),
    '餐飲與聚餐費' => array('公司聚餐', '部門聯餐', '餐費', '午餐'),
    '郵務／運輸／手續費' => array('運費', '轉帳手續費', '印花稅', '郵資'),
    '設備器材' => array('設備器材', '線材、耗材'),
    '設備器材廠商' => array('設備器材', '設備器材廠商'),
    '車輛相關支出' => array('租賃保證金', '汽車租賃', '罰單', '修理', '保養', 'ETC', '停車費', '加油卡儲值', '現金加油'),
    '系統/軟體費用' => array('系統費用', '資訊服務費'),
);

$mainInsert = $db->prepare("INSERT INTO dropdown_options (category, parent_id, label, sort_order, is_active) VALUES ('payment_main_category', NULL, ?, ?, 1)");
$subInsert = $db->prepare("INSERT INTO dropdown_options (category, parent_id, label, sort_order, is_active) VALUES ('payment_main_category', ?, ?, ?, 1)");

$mainOrder = 0;
$totalMain = 0;
$totalSub = 0;

foreach ($categories as $mainLabel => $subs) {
    $mainOrder++;
    $mainInsert->execute(array($mainLabel, $mainOrder));
    $mainId = $db->lastInsertId();
    $totalMain++;

    $subOrder = 0;
    foreach ($subs as $subLabel) {
        $subOrder++;
        $subInsert->execute(array($mainId, $subLabel, $subOrder));
        $totalSub++;
    }
}

$results[] = "OK: 寫入 {$totalMain} 個主分類 + {$totalSub} 個子分類";

echo implode("\n", $results) . "\n";
echo "\n完成。";
