<?php
header('Content-Type: text/plain; charset=utf-8');
require_once __DIR__ . '/../includes/bootstrap.php';
$db = Database::getInstance();

$mode = isset($_GET['run']) ? 'execute' : 'preview';
echo "=== TP-LINK / VIGI 產品分類修正 v2 ===\n";
echo "模式: $mode（加 ?run=1 執行）\n\n";

// === 排除清單（不是TP-LINK的產品）===
$excludeModels = array(
    'DS1525+','DS1621+','DS1825+','DS225+','DS2422+','DS425+','DS725+','DS925+', // Synology NAS
    'DS-DVR','DS-1294', // 海康
    'DSA-', 'DSS-', // 變壓器/中繼台
);

function isExcluded($model) {
    global $excludeModels;
    foreach ($excludeModels as $ex) {
        if (stripos($model, $ex) === 0) return true;
    }
    // C 開頭但不是 VIGI 的排除規則
    if (preg_match('/^C[0-9]/', $model) && !preg_match('/^C[2-9][2-9]0/i', $model)) return true;
    if (preg_match('/^C[A-Z]/', $model) && !preg_match('/^C[2-9][2-9]0/i', $model)) return true;
    // TL- 但不是網通的排除
    if (preg_match('/^TL-(C5|HD|GBIC|VGA)/i', $model)) return true;
    // ES- 但不是交換器
    if (preg_match('/^ES-/i', $model)) return true;
    // S 開頭但不是 Insight/交換器
    if (preg_match('/^S[A-Z]{2,}/', $model) && !preg_match('/^(SG|SX|SM|S[2-8][2-8]5)/i', $model)) return true;
    // MC 但不是光纖轉換器
    if (preg_match('/^MC[A-Z]/i', $model) && !preg_match('/^MC[12]\d{2}/i', $model)) return true;
    // H3 但不是攝影機
    if (preg_match('/^H3$|^H3[^A]/i', $model)) return true;
    // POE 但不是 TP-LINK PoE
    if (preg_match('/^POE-|^POE12/i', $model)) return true;
    // NVR 但不是 VIGI（有 HS- 或 JH- 前綴的是其他品牌）
    // OC220 不是 OC200/OC300
    if ($model === 'OC220' || stripos($model, 'OC220') === 0) return true;
    return false;
}

function getTargetCategory($model, $name) {
    // === VIGI Insight 金屬殼 (110) ===
    // 帶 InSight/Insight 前綴
    if (preg_match('/^(InSight|Insight)\s*/i', $model)) return array(110, 'Insight金屬殼');
    // S + 數字 開頭的 Insight（S285, S385, S455, S655I 等）
    if (preg_match('/^S[2-8][2-8]5/i', $model)) return array(110, 'Insight金屬殼');

    // === VIGI Easycam 塑膠殼 ===
    // VIGI C 或直接 C + 3位數字
    if (preg_match('/^(VIGI\s*)?C([2-9])([2-9])0/i', $model, $m)) {
        // 第2碼: 形狀 (3=槍 4=半球 2=球 5=旋轉)
        // 第3碼: MP (5=5MP 4=4MP 3=3MP 2=2MP)
        $mpDigit = (int)$m[3];
        if ($mpDigit >= 5) return array(373, '塑膠殼5MP+');
        if ($mpDigit >= 3) return array(373, '塑膠殼4MP→5MP');
        return array(372, '塑膠殼2MP');
    }
    // BC500 等 VIGI 產品
    if (preg_match('/^BC\d/i', $model) && preg_match('/網路攝影機/u', $name)) {
        if (preg_match('/5MP|500萬/u', $name)) return array(373, 'VIGI 5MP');
        return array(372, 'VIGI 2MP');
    }
    // H3AE WiFi 室內攝影機
    if (preg_match('/^H3AE/i', $model)) return array(372, 'VIGI WiFi');

    // === VIGI NVR (111) ===
    if (preg_match('/^(VIGI\s*)?NVR\d/i', $model)) return array(111, 'VIGI NVR');

    // === Omada WiFi 7 (158) ===
    if (preg_match('/^EAP7/i', $model)) return array(158, 'WiFi7');

    // === Omada WiFi 6 / AP (159) ===
    if (preg_match('/^EAP[1-6]/i', $model)) return array(159, 'WiFi6/AP');

    // === Omada 控制器 (160) ===
    if (preg_match('/^OC[23]00$/i', $model)) return array(160, '控制器');

    // === Omada 路由器 (371) ===
    if (preg_match('/^ER\d/i', $model)) return array(371, '路由器');

    // === Omada L3 交換器 (157) ===
    if (preg_match('/^S[GX]6\d/i', $model)) return array(157, 'L3交換器');

    // === Omada 10G/L3 Lite (156) ===
    if (preg_match('/^(SG5|SX3)/i', $model)) return array(156, '10G/L3Lite');

    // === Omada L2 管理型 (160) ===
    if (preg_match('/^SG[23]\d{3}/i', $model)) return array(160, 'L2管理型');

    // === 工業級交換器 (153) ===
    if (preg_match('/^IES/i', $model)) return array(153, '工業級');

    // === 簡易雲管理 (153) ===
    if (preg_match('/^ES\d/i', $model)) return array(153, '簡易管理型');

    // === 非管理型 POE (161) ===
    if (preg_match('/^DS1\d{2}[GP]/i', $model)) return array(161, '非管理型POE');

    // === TP-LINK 交換器 (152) ===
    // TL-SG, TL-SL, TL-SF, TL-SX 交換器
    if (preg_match('/^TL-S[GLFX]/i', $model)) return array(152, 'TP-LINK交換器');
    // TL-MR, TL-WR 路由器 → 也歸 TP-LINK
    if (preg_match('/^TL-(MR|WR)/i', $model)) return array(152, 'TP-LINK路由器');
    // TL-SM SFP 模組 → 光纖 (154)
    if (preg_match('/^TL-SM/i', $model)) return array(154, 'TP-LINK SFP');

    // === SG1xxx 無型號前綴的 TP-LINK 交換器 ===
    if (preg_match('/^SG1\d{3}/i', $model)) return array(152, 'TP-LINK交換器');
    if (preg_match('/^SG10[58]/i', $model)) return array(152, 'TP-LINK交換器');
    if (preg_match('/^SG108$/i', $model)) return array(152, 'TP-LINK交換器');
    if (preg_match('/^SX1\d{2}/i', $model)) return array(152, 'TP-LINK交換器');
    if (preg_match('/^SX105$/i', $model)) return array(152, 'TP-LINK交換器');

    // === 光纖模組/轉換器 (154) ===
    if (preg_match('/^SM\d{3}/i', $model)) return array(154, '光纖模組');
    if (preg_match('/^MC[12]\d{2}/i', $model)) return array(154, '光纖轉換器');
    if (preg_match('/^POE[12]\d{2}S$/i', $model)) return array(154, 'PoE供電器');
    if (preg_match('/^PoE\d{3}S$/i', $model)) return array(154, 'PoE供電器');

    return null;
}

// Excel 價格對照
$excelPrices = array(
    'EAP787'=>8000,'EAP773'=>4800,'EAP772'=>4000,'EAP772-Outdoor'=>4800,'EAP723'=>2100,
    'EAP775-Wall'=>5000,'EAP725-WALL'=>2200,
    'EAP650-Outdoor'=>3500,'EAP650'=>2500,'EAP653'=>2000,'EAP610'=>1900,
    'EAP625-Outdoor HD'=>3500,'EAP610-Outdoor'=>3000,'EAP655-Wall'=>1800,'EAP615-Wall'=>1600,
    'EAP215-Bridge KIT'=>2400,'EAP211-Bridge KIT'=>2200,'EAP115-Bridge KIT'=>1800,
    'OC200'=>1500,'OC300'=>5000,
    'ER8411'=>9000,'ER7212PC'=>3600,'ER703WP-4G-Outdoor'=>4000,'ER706W-4G'=>4000,
    'ER706W'=>2900,'ER7412-M2'=>4000,'ER707-M2'=>3500,'ER7406'=>2800,'ER7206'=>2500,'ER605'=>1200,
    'TL-SG1008MP'=>2000,'TL-SG1210P'=>1500,'TL-SG1005P-PD'=>800,
    'TL-SL1218MP'=>3300,'TL-SL1311MP'=>1600,'TL-SL1311P'=>1100,'TL-SF1009P'=>800,'TL-SF1006P'=>750,
    'TL-SG1024DE'=>2400,'TL-SG1016DE'=>1800,
    'TL-SX1008'=>7200,'TL-SX105'=>5700,'TL-SG105PP-M2'=>2600,'TL-SG108-M2'=>2000,'TL-SG105-M2'=>1350,
    'MC220L'=>400,'MC210CS'=>600,'MC200CM'=>600,'MC211CS-20'=>500,'MC212CS-20'=>500,
    'MC211CS-2'=>450,'MC212CS-2'=>450,'MC110CMP'=>800,
    'SM6110-LR'=>1500,'SM6110-SR'=>1000,'SM5310-T'=>850,'SM5110-SR'=>450,'SM5110-LR'=>700,
    'SM5220-3M'=>550,'SM311LM'=>350,'SM311LS'=>350,'SM321A'=>350,'SM321B'=>350,
    'POE260S'=>450,'PoE160S'=>400,'PoE150S'=>350,
    'SG6654XHP'=>40000,'SG6428XHP'=>35000,'SG6428X'=>19000,'SX6632YF'=>70000,
    'SG5452XMPP'=>30000,'SG5452X'=>16000,'SG5428XMPP'=>18000,'SG5428X'=>6500,
    'SX3032F'=>22000,'SX3016F'=>14000,'SX3008F'=>6000,'SX3832MPP'=>45000,'SX3832'=>38000,'SX3206HPP'=>11000,
    'SG3428XPP-M2'=>16000,'SG3218XP-M2'=>10000,'SG3210XHP-M2'=>8500,'SG2210XMP-M2'=>7000,
    'SG3428X-M2'=>10500,'SG3210X-M2'=>5500,
    'SG3452X'=>10500,'SG3452'=>7800,'SG3428XF'=>13000,'SG3428X'=>5500,'SG3428XMP'=>9000,
    'SG3428'=>4200,'SG3428MP'=>7300,'SG3210'=>2300,'SG2428P'=>5600,'SG2218P'=>4800,
    'SG2210MP'=>3300,'SG2210P'=>2000,'SG2218'=>2700,'SG2008'=>1600,'SG2005P-PD'=>2200,
    'IES210GPP'=>4000,'IES206GPP'=>3500,'IES208G'=>2700,'IES206G'=>2500,
    'ES228GMP'=>7200,'ES220GMP'=>4400,'ES210GMP'=>2400,'ES206GP'=>980,'ES205GP'=>900,
    'ES224G'=>2600,'ES216G'=>2000,'ES208G'=>850,'ES205G'=>600,
    'DS1018GMP'=>3800,'DS110GMP'=>2100,'DS108GP'=>900,'DS106GPP'=>1100,'DS105GP'=>780,'DS111P'=>1000,'DS106P'=>800,
);

// 查詢
$products = $db->query("SELECT p.id, p.name, p.model, p.cost, p.category_id, c.name as cat_name
    FROM products p LEFT JOIN product_categories c ON p.category_id = c.id
    WHERE p.model LIKE 'EAP%' OR p.model LIKE 'ER%' OR p.model LIKE 'OC200' OR p.model LIKE 'OC300'
    OR p.model LIKE 'SG%' OR p.model LIKE 'SX%' OR p.model LIKE 'IES%'
    OR p.model LIKE 'ES%' OR p.model LIKE 'DS%'
    OR p.model LIKE 'TL-S%' OR p.model LIKE 'TL-M%' OR p.model LIKE 'TL-W%'
    OR p.model LIKE 'MC1%' OR p.model LIKE 'MC2%' OR p.model LIKE 'SM%'
    OR p.model LIKE 'POE%S' OR p.model LIKE 'PoE%S'
    OR p.model LIKE 'VIGI%' OR p.model LIKE 'C_50%' OR p.model LIKE 'C_40%' OR p.model LIKE 'C_30%' OR p.model LIKE 'C_20%'
    OR p.model LIKE 'C540%'
    OR p.model LIKE 'InSight%' OR p.model LIKE 'Insight%'
    OR p.model LIKE 'S_85%' OR p.model LIKE 'S_55%' OR p.model LIKE 'S_45%' OR p.model LIKE 'S285%' OR p.model LIKE 'S655%'
    OR p.model LIKE 'BC%' OR p.model LIKE 'H3AE%'
    OR p.model LIKE 'NVR%'
    ORDER BY p.model")->fetchAll(PDO::FETCH_ASSOC);

$changes = array();
$priceUpdates = array();
$stats = array('分類修正' => 0, '已正確' => 0, '跳過' => 0);

foreach ($products as $p) {
    if (isExcluded($p['model'])) {
        $stats['跳過']++;
        continue;
    }

    $result = getTargetCategory($p['model'], $p['name']);
    if (!$result) {
        $stats['跳過']++;
        continue;
    }

    $targetCatId = $result[0];
    $reason = $result[1];

    if ($p['category_id'] == $targetCatId) {
        $stats['已正確']++;
        continue;
    }

    $stats['分類修正']++;
    $changes[] = array('id' => $p['id'], 'cat_id' => $targetCatId);
    echo sprintf("[修正] #%s %s → %s (原:%s)\n", $p['id'], $p['model'], $reason, $p['cat_name']);

    // 價格比對
    foreach ($excelPrices as $em => $ep) {
        if ($p['model'] === $em || stripos($p['model'], $em) === 0) {
            if ((int)$p['cost'] !== $ep) {
                $priceUpdates[] = array('id' => $p['id'], 'model' => $p['model'], 'old' => (int)$p['cost'], 'new' => $ep);
            }
            break;
        }
    }
}

echo "\n=== 統計 ===\n";
foreach ($stats as $k => $v) echo "$k: $v 筆\n";
echo "需變更: " . count($changes) . " 筆\n";

if (!empty($priceUpdates)) {
    echo "\n=== 價格差異（成本） ===\n";
    foreach ($priceUpdates as $pu) {
        echo sprintf("#%s %s: %s → %s\n", $pu['id'], $pu['model'], $pu['old'], $pu['new']);
    }
    echo "共 " . count($priceUpdates) . " 筆\n";
}

if ($mode === 'execute') {
    if (!empty($changes)) {
        echo "\n=== 執行分類更新 ===\n";
        $stmt = $db->prepare("UPDATE products SET category_id = ? WHERE id = ?");
        foreach ($changes as $ch) $stmt->execute(array($ch['cat_id'], $ch['id']));
        echo "已更新 " . count($changes) . " 筆分類\n";
    }
    if (!empty($priceUpdates) && isset($_GET['price'])) {
        echo "\n=== 執行價格更新 ===\n";
        $stmt = $db->prepare("UPDATE products SET cost = ? WHERE id = ?");
        foreach ($priceUpdates as $pu) $stmt->execute(array($pu['new'], $pu['id']));
        echo "已更新 " . count($priceUpdates) . " 筆價格\n";
    }
}
