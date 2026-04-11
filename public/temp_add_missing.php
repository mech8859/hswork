<?php
header('Content-Type: text/plain; charset=utf-8');
require_once __DIR__ . '/../includes/bootstrap.php';
$db = Database::getInstance();

$mode = isset($_GET['run']) ? 'execute' : 'preview';
echo "=== Excel 缺漏產品比對 ===\n";
echo "模式: $mode（加 ?run=1 執行新增）\n\n";

// Excel 產品清單（型號 → 資料）
// 型號需要清理：去掉括號部分如 (2.8mm)(4mm)
$excelProducts = array(
    // === VIGI Insight 金屬殼 (110) ===
    array('model'=>'InSight S385','name'=>'VIGI 8MP 全彩槍型金屬殼網路攝影機','cost'=>2000,'cat'=>110),
    array('model'=>'InSight S485','name'=>'VIGI 8MP 全彩半球金屬殼網路攝影機','cost'=>2000,'cat'=>110),
    array('model'=>'InSight S285','name'=>'VIGI 8MP 全彩防爆球型金屬殼網路攝影機','cost'=>2100,'cat'=>110),
    array('model'=>'Insight S385DPS','name'=>'VIGI 8MP 戶外全景槍型金屬殼網路攝影機','cost'=>4500,'cat'=>110),
    array('model'=>'Insight S385PI','name'=>'VIGI 8MP 紅外線廣角槍型金屬殼網路攝影機','cost'=>3500,'cat'=>110),
    array('model'=>'Insight S485PI','name'=>'VIGI 8MP 紅外線廣角半球金屬殼網路攝影機','cost'=>3500,'cat'=>110),
    array('model'=>'InSight S355','name'=>'VIGI 5MP 全彩槍型金屬殼網路攝影機','cost'=>1250,'cat'=>110),
    array('model'=>'InSight S455','name'=>'VIGI 5MP 全彩半球金屬殼網路攝影機','cost'=>1250,'cat'=>110),
    array('model'=>'InSight S655I','name'=>'VIGI 5MP 紅外線魚眼金屬殼網路攝影機','cost'=>2400,'cat'=>110),
    array('model'=>'InSight S245ZI','name'=>'VIGI 4MP 紅外線變焦球型金屬殼網路攝影機','cost'=>2300,'cat'=>110),
    array('model'=>'InSight S345ZI','name'=>'VIGI 4MP 紅外線變焦槍型金屬殼網路攝影機','cost'=>2300,'cat'=>110),
    array('model'=>'InSight S445ZI','name'=>'VIGI 4MP 紅外線變焦半球金屬殼網路攝影機','cost'=>2300,'cat'=>110),
    array('model'=>'InSight S345-4G','name'=>'VIGI 4MP 全彩4G槍型金屬殼網路攝影機','cost'=>3000,'cat'=>110),
    array('model'=>'InSight S345','name'=>'VIGI 4MP 全彩槍型金屬殼網路攝影機','cost'=>1100,'cat'=>110),
    array('model'=>'InSight S445','name'=>'VIGI 4MP 全彩半球金屬殼網路攝影機','cost'=>1100,'cat'=>110),
    array('model'=>'InSight S245','name'=>'VIGI 4MP 全彩防爆球型金屬殼網路攝影機','cost'=>1150,'cat'=>110),
    array('model'=>'InSight S325','name'=>'VIGI 2MP 全彩槍型金屬殼網路攝影機','cost'=>680,'cat'=>110),
    array('model'=>'InSight S425','name'=>'VIGI 2MP 全彩半球金屬殼網路攝影機','cost'=>680,'cat'=>110),
    array('model'=>'InSight S225','name'=>'VIGI 2MP 全彩球型金屬殼網路攝影機','cost'=>720,'cat'=>110),
    // === VIGI Easycam 塑膠殼 5MP (373) ===
    array('model'=>'VIGI C350','name'=>'VIGI 5MP 全彩槍型網路攝影機','cost'=>1050,'cat'=>373),
    array('model'=>'VIGI C450','name'=>'VIGI 5MP 全彩半球型網路攝影機','cost'=>1000,'cat'=>373),
    array('model'=>'VIGI C250','name'=>'VIGI 5MP 全彩球型網路攝影機','cost'=>1000,'cat'=>373),
    array('model'=>'VIGI C540S','name'=>'VIGI 4MP 黑光旋轉網路攝影機','cost'=>2700,'cat'=>373),
    array('model'=>'VIGI C540V','name'=>'VIGI 4MP 雙鏡頭旋轉網路攝影機','cost'=>1550,'cat'=>373),
    array('model'=>'VIGI C540-W','name'=>'VIGI 4MP WiFi旋轉網路攝影機','cost'=>1550,'cat'=>373),
    array('model'=>'VIGI C540-4G','name'=>'VIGI 4MP 4G旋轉網路攝影機','cost'=>2400,'cat'=>373),
    array('model'=>'VIGI C540','name'=>'VIGI 4MP 旋轉網路攝影機','cost'=>1400,'cat'=>373),
    array('model'=>'VIGI C340S','name'=>'VIGI 4MP 黑光槍型網路攝影機','cost'=>1800,'cat'=>373),
    array('model'=>'VIGI C440','name'=>'VIGI 4MP 全彩槍型網路攝影機','cost'=>950,'cat'=>373),
    array('model'=>'VIGI C240','name'=>'VIGI 4MP 全彩網路攝影機','cost'=>950,'cat'=>373),
    array('model'=>'VIGI C340','name'=>'VIGI 4MP 全彩槍型網路攝影機','cost'=>950,'cat'=>373),
    array('model'=>'VIGI C340-W','name'=>'VIGI 4MP WiFi槍型網路攝影機','cost'=>1200,'cat'=>373),
    array('model'=>'VIGI C440-W','name'=>'VIGI 4MP WiFi半球網路攝影機','cost'=>1150,'cat'=>373),
    // === VIGI Easycam 塑膠殼 2MP (372) ===
    array('model'=>'VIGI C320I','name'=>'VIGI 2MP 紅外線槍型網路攝影機','cost'=>460,'cat'=>372),
    array('model'=>'VIGI C420I','name'=>'VIGI 2MP 紅外線半球網路攝影機','cost'=>460,'cat'=>372),
    array('model'=>'VIGI C220I','name'=>'VIGI 2MP 紅外線球型網路攝影機','cost'=>550,'cat'=>372),
    // === VIGI NVR (111) ===
    array('model'=>'VIGI NVR4064H','name'=>'VIGI 64路 4硬碟 NVR','cost'=>8500,'cat'=>111),
    array('model'=>'VIGI NVR4032H','name'=>'VIGI 32路 4硬碟 NVR','cost'=>6000,'cat'=>111),
    array('model'=>'VIGI NVR4016H','name'=>'VIGI 16路 4硬碟 NVR','cost'=>4200,'cat'=>111),
    array('model'=>'VIGI NVR2016H','name'=>'VIGI 16路 2硬碟 NVR','cost'=>3000,'cat'=>111),
    array('model'=>'VIGI NVR2016H-16MP','name'=>'VIGI 16路 2硬碟 PoE NVR','cost'=>5500,'cat'=>111),
    array('model'=>'VIGI NVR1016H','name'=>'VIGI 16路 1硬碟 NVR','cost'=>1400,'cat'=>111),
    array('model'=>'VIGI NVR2008H-8MP','name'=>'VIGI 8路 2硬碟 PoE NVR','cost'=>3500,'cat'=>111),
    array('model'=>'VIGI NVR1008H-8MP','name'=>'VIGI 8路 1硬碟 PoE NVR','cost'=>2700,'cat'=>111),
    array('model'=>'VIGI NVR1008H','name'=>'VIGI 8路 1硬碟 NVR','cost'=>1200,'cat'=>111),
    array('model'=>'VIGI NVR1004H-4P','name'=>'VIGI 4路 1硬碟 PoE NVR 鋼殼','cost'=>1500,'cat'=>111),
    array('model'=>'VIGI NVR1104H-4P','name'=>'VIGI 4路 1硬碟 PoE NVR 塑殼','cost'=>1300,'cat'=>111),
    // === Omada WiFi 7 (158) ===
    array('model'=>'EAP787','name'=>'Omada BE15000 吸頂式三頻 Wi-Fi 7 無線基地台','cost'=>8000,'cat'=>158),
    array('model'=>'EAP773','name'=>'Omada BE11000 吸頂式三頻 Wi-Fi 7 無線基地台','cost'=>4800,'cat'=>158),
    array('model'=>'EAP772','name'=>'Omada BE11000 吸頂式三頻 Wi-Fi 7 無線基地台','cost'=>4000,'cat'=>158),
    array('model'=>'EAP772-Outdoor','name'=>'Omada BE11000 戶外三頻 Wi-Fi 7 無線基地台','cost'=>4800,'cat'=>158),
    array('model'=>'EAP723','name'=>'Omada BE5000 吸頂式雙頻 Wi-Fi 7 無線基地台','cost'=>2100,'cat'=>158),
    array('model'=>'EAP775-Wall','name'=>'Omada BE11000 嵌牆式 Wi-Fi 7 無線基地台','cost'=>5000,'cat'=>158),
    array('model'=>'EAP725-WALL','name'=>'Omada BE5000 嵌牆式 Wi-Fi 7 無線基地台','cost'=>2200,'cat'=>158),
    // === Omada WiFi 6 / AP (159) ===
    array('model'=>'EAP650-Outdoor','name'=>'Omada AX3000 室內/戶外型無線基地台','cost'=>3500,'cat'=>159),
    array('model'=>'EAP650','name'=>'Omada AX3000 吸頂式無線基地台','cost'=>2500,'cat'=>159),
    array('model'=>'EAP653 UR','name'=>'Omada AX3000 吸頂式無線基地台(無變壓器)','cost'=>2000,'cat'=>159),
    array('model'=>'EAP653','name'=>'Omada AX3000 吸頂式無線基地台','cost'=>2000,'cat'=>159),
    array('model'=>'EAP610','name'=>'Omada AX1800 吸頂式無線基地台','cost'=>1900,'cat'=>159),
    array('model'=>'EAP625-Outdoor HD','name'=>'Omada AX1800 室內/戶外型無線基地台','cost'=>3500,'cat'=>159),
    array('model'=>'EAP610-Outdoor','name'=>'Omada AX1800 室內/戶外型無線基地台','cost'=>3000,'cat'=>159),
    array('model'=>'EAP655-Wall','name'=>'Omada AX3000 嵌牆式無線基地台','cost'=>1800,'cat'=>159),
    array('model'=>'EAP615-Wall','name'=>'Omada AX3000 嵌牆式無線基地台','cost'=>1600,'cat'=>159),
    array('model'=>'EAP215-Bridge KIT','name'=>'Omada 5GHz 戶外型無線網橋 5公里','cost'=>2400,'cat'=>159),
    array('model'=>'EAP211-Bridge KIT','name'=>'Omada 5GHz 戶外型無線網橋 1公里','cost'=>2200,'cat'=>159),
    array('model'=>'EAP115-Bridge KIT','name'=>'Omada 5GHz 戶外型無線網橋 1公里 100M','cost'=>1800,'cat'=>159),
    // === Omada 控制器 (160) ===
    array('model'=>'OC200','name'=>'Omada 硬體控制器 (100台AP)','cost'=>1500,'cat'=>160),
    array('model'=>'OC300','name'=>'Omada 硬體控制器 (500台AP)','cost'=>5000,'cat'=>160),
    // === Omada 路由器 (371) ===
    array('model'=>'ER8411','name'=>'Omada 10G SFP+ WAN/LAN VPN路由器','cost'=>9000,'cat'=>371),
    array('model'=>'ER7212PC','name'=>'Omada 三合一路由器+控制器+交換器','cost'=>3600,'cat'=>371),
    array('model'=>'ER703WP-4G-Outdoor','name'=>'Omada 戶外型4G路由器','cost'=>4000,'cat'=>371),
    array('model'=>'ER706W-4G','name'=>'Omada 4G VPN路由器','cost'=>4000,'cat'=>371),
    array('model'=>'ER706W','name'=>'Omada Gigabit VPN路由器','cost'=>2900,'cat'=>371),
    array('model'=>'ER7412-M2','name'=>'Omada 2.5G VPN路由器','cost'=>4000,'cat'=>371),
    array('model'=>'ER707-M2','name'=>'Omada 2.5G VPN路由器','cost'=>3500,'cat'=>371),
    array('model'=>'ER7406','name'=>'Omada Gigabit VPN路由器','cost'=>2800,'cat'=>371),
    array('model'=>'ER7206','name'=>'Omada Gigabit VPN路由器','cost'=>2500,'cat'=>371),
    array('model'=>'ER605','name'=>'Omada Gigabit VPN路由器','cost'=>1200,'cat'=>371),
);

// 查系統現有型號
$existingModels = array();
$stmt = $db->query("SELECT model FROM products WHERE is_active = 1");
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    // 正規化：去掉空白、大小寫
    $existingModels[strtolower(trim($row['model']))] = true;
    // 也存含括號變體
    if (strpos($row['model'], '-') !== false) {
        $parts = explode('-', $row['model']);
        $existingModels[strtolower($parts[0])] = true;
    }
}

$missing = array();
$found = 0;

foreach ($excelProducts as $ep) {
    $modelLower = strtolower(trim($ep['model']));
    // 檢查是否存在（精確或前綴匹配）
    $exists = false;
    if (isset($existingModels[$modelLower])) {
        $exists = true;
    } else {
        // 模糊比對：系統裡有 S385-2.8 但 Excel 是 InSight S385
        $shortModel = preg_replace('/^(InSight|Insight|VIGI)\s*/i', '', $ep['model']);
        $shortModelLower = strtolower(trim($shortModel));
        if (isset($existingModels[$shortModelLower])) {
            $exists = true;
        }
        // 再查 DB
        if (!$exists) {
            $chk = $db->prepare("SELECT id FROM products WHERE model = ? OR model LIKE ? LIMIT 1");
            $chk->execute(array($ep['model'], $ep['model'] . '%'));
            if ($chk->fetch()) $exists = true;
        }
        if (!$exists) {
            $chk = $db->prepare("SELECT id FROM products WHERE model = ? OR model LIKE ? LIMIT 1");
            $chk->execute(array($shortModel, $shortModel . '%'));
            if ($chk->fetch()) $exists = true;
        }
    }

    if ($exists) {
        $found++;
    } else {
        $missing[] = $ep;
        echo sprintf("[缺] %s | %s | 成本:%s → 分類:%s\n", $ep['model'], $ep['name'], $ep['cost'], $ep['cat']);
    }
}

echo "\n=== 統計 ===\n";
echo "Excel 產品: " . count($excelProducts) . " 筆\n";
echo "系統已有: $found 筆\n";
echo "缺少需新增: " . count($missing) . " 筆\n";

if ($mode === 'execute' && !empty($missing)) {
    echo "\n=== 新增產品 ===\n";
    $stmt = $db->prepare("INSERT INTO products (model, name, cost, category_id, is_active, created_at) VALUES (?, ?, ?, ?, 1, NOW())");
    foreach ($missing as $m) {
        $stmt->execute(array($m['model'], $m['name'], $m['cost'], $m['cat']));
        echo "新增: " . $m['model'] . "\n";
    }
    echo "已新增 " . count($missing) . " 筆\n";
}
