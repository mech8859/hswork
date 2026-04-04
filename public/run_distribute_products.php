<?php
require __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (!Auth::hasPermission('admin')) { die('需要管理員權限'); }
header('Content-Type: text/html; charset=utf-8');
echo '<pre style="font-family:monospace;font-size:13px;line-height:1.6">';
$db = Database::getInstance();

echo "=== 產品分配到子分類 ===\n\n";

// 工具函數
function getSubCatId($db, $subName, $parentName) {
    $p = $db->prepare("SELECT id FROM product_categories WHERE name = ? AND (parent_id IS NULL OR parent_id = 0)");
    $p->execute(array($parentName));
    $pid = $p->fetchColumn();
    if (!$pid) return null;
    $s = $db->prepare("SELECT id FROM product_categories WHERE name = ? AND parent_id = ?");
    $s->execute(array($subName, $pid));
    return $s->fetchColumn() ?: null;
}

function getTopCatId($db, $name) {
    $s = $db->prepare("SELECT id FROM product_categories WHERE name = ? AND (parent_id IS NULL OR parent_id = 0)");
    $s->execute(array($name));
    return $s->fetchColumn() ?: null;
}

// 每個主分類的子分類分配規則：[子分類名, [關鍵字...]]
// 順序重要：先比對更精確的，最後比對寬泛的
$distributions = array(

    '01.監控系統' => array(
        array('監控硬碟', array('硬碟','HDD','Seagate','WD','紫標')),
        array('快速球', array('快速球','PTZ','Speed Dome')),
        array('攝影機支架/配件', array('支架','壁掛','立柱','防護罩','接線盒','防水盒','記憶卡','配件','控制鍵盤','推播','分配器','轉換器','傳輸器','電源')),
        array('影像傳輸器', array('影像傳輸','雙絞線','同軸影像')),
        array('錄影主機(NVR/DVR)', array('NVR','DVR','錄影主機','主機')),
        array('網路攝影機(IP)', array('網路攝影','IP攝影','IPC','全景','全彩','紅外線網路','WizSense','Lite系列','VIGI','Insight')),
        array('類比攝影機', array('類比','HVR','高清類比','2MP','3K','AHD')),
        array('中央監控', array('中央監控')),
    ),

    '02.門禁系統' => array(
        array('電子鎖', array('電子鎖','密碼鎖','指紋鎖')),
        array('電鎖(磁力鎖/陽極鎖)', array('磁力鎖','陽極鎖','電鎖')),
        array('電鎖配件', array('鎖配件','感應卡','卡片','鑰匙扣')),
        array('讀卡機', array('讀卡','QR','維根')),
        array('門禁控制器', array('控制器','門禁控','SOYAL','聯工','茂旭')),
        array('人臉辨識設備', array('人臉','Face','臉部','車牌辨識','軟體')),
        array('開門按鈕/感應器', array('開門按鈕','感應','按鈕')),
        array('繼電器', array('繼電器')),
    ),

    '03.對講機系統' => array(
        array('防水罩', array('防水罩','防水箱')),
        array('門口機', array('門口機','門口')),
        array('室內機', array('室內機','室內')),
        array('對講機配件', array('配件','介面卡','伺服器','電源','中繼')),
    ),

    '04.總機/電話系統' => array(
        array('聯盟電話總機', array('聯盟','ISDK','UD-2100','UD-60','DSS','DISA','MVC')),
        array('萬國總機', array('萬國','FX-')),
        array('話機', array('話機','標準型','有線電話','免持')),
        array('電話錄音', array('錄音')),
        array('電話單機', array('單機','放大魔音')),
        array('電話配件', array('配件','門口機','中繼','套裝','轉接')),
    ),

    '05.網通設備' => array(
        array('交換器(Switch)', array('交換器','Switch','PoE','CCTV','Core','10G','2.5G','L3')),
        array('路由器(Router)', array('路由','Router','分享器')),
        array('無線基地台(AP)', array('無線','WiFi','wifi','AP','Mesh','deco','基地台')),
        array('4G路由器', array('4G','LTE')),
        array('光電轉換器', array('光電轉換','光纖模組','SFP')),
        array('長距離無線傳輸', array('長距離','振翔','聯力','無線傳輸','點對點')),
        array('網通配件', array('配件','水晶頭','水晶接頭','保護套','穿透式')),
    ),

    '06.音響/廣播系統' => array(
        array('擴大機', array('擴大機','歐姆','高壓')),
        array('喇叭', array('喇叭','音柱','號角')),
        array('麥克風', array('麥克風','MIC','mic')),
        array('混音器', array('混音','音像調節')),
        array('網路廣播(IP PA)', array('IP PA','網路廣播','TONNET')),
        array('穩壓器', array('穩壓')),
    ),

    '07.投影機/視聽設備' => array(
        array('投影機', array('投影機','EPSON','雷射投影','燈泡投影','投影燈')),
        array('布幕', array('布幕','桌幕','地拉幕','手拉幕','電動布幕','ZERO','X-Press','Tripod','Super Mol','U-WORK')),
        array('投影機吊架', array('吊架','升降架','GPCM','GrandView','力神','黑力士')),
        array('電視壁掛架', array('壁掛架','懸吊架','電視架')),
        array('電視/螢幕', array('電視','螢幕','LCD','LED')),
    ),

    '08.線材' => array(
        array('HDMI/VGA訊號線', array('HDMI','VGA','訊號線','訊號延長','光纖線 HDMI','切換器','分配器','艾吉斯')),
        array('網路線(Cat.6/6A)', array('Cat.6','Cat6','Cat.5','Cat5','網路線','UTP','LSZH','R&M','跳線','Patch','資訊座','資訊插座','面板','Panel','FM45','理線','網路點','網路配件','低煙無鹵','智能模組')),
        array('光纖線材', array('光纖','光纜','單模')),
        array('同軸電纜線', array('同軸','電纜線','RG')),
        array('電話線', array('電話線','電話電纜','C型端子','RJ11','電話插座')),
        array('電源線/控制線', array('電源線','控制電纜','電源延長','延長線')),
        array('喇叭線/對講線', array('喇叭線','對講線','廣播線')),
        array('防盜線', array('防盜線')),
    ),

    '09.光纖設備' => array(
        array('光纖配件', array('配件','接頭','耦合','尾纖','配線架','終端盒','光纖盒')),
        array('光電轉換器', array('光電轉換','轉換器','DJS','工業級')),
        array('光纖模組', array('模組','SFP')),
        array('熔接材料', array('熔接','熱縮')),
        array('光纖線材', array('光纖線','光纜','單模','跳線')),
    ),

    '10.配線器材' => array(
        array('動力箱/防水盒', array('動力箱','防水盒','防水箱')),
        array('蓋板(Glatima/RISNA)', array('蓋板','Glatima','RISNA','開關','調光')),
        array('資訊插座/面板', array('資訊插座','資訊面板','面板','星光')),
        array('線槽/壓條', array('線槽','壓條','壓地')),
        array('束帶/固定夾', array('束帶','固定夾','矽利康')),
        array('插座插頭', array('插座','插頭')),
        array('跳線/跳線面板', array('跳線')),
        array('電視配件', array('電視','天線','分歧器','分配器','訊號放大','電視接頭','電視插座')),
    ),

    '11.機櫃/電源' => array(
        array('機櫃', array('機櫃','壁掛式','活動式','開放式')),
        array('機櫃配件', array('層板','背板','輪子','儀器支撐','支架','端子版架','線槽')),
        array('UPS不斷電系統', array('UPS','不斷電','CyberPower')),
        array('PDU電源分配器', array('PDU')),
        array('電源供應器', array('電源','明緯','充電器','變壓器','Hometek')),
        array('穩壓器', array('穩壓')),
        array('鋰電池', array('鋰電池','電池','YUASA','湯淺','深循環')),
    ),

    '12.防盜/保全系統' => array(
        array('磁簧開關', array('磁簧')),
        array('感知器', array('感知器','PIR','偵測器','煙霧','一氧化碳','瓦斯','音頻','捲門感知')),
        array('緊急按鈕', array('緊急按鈕','按鈕')),
        array('警報主機', array('報警','警報','喇叭')),
        array('雲保全設備', array('雲保全','鎧鋒','KCA','保全')),
        array('紅外線', array('紅外線')),
    ),

    '13.智能家居/IoT' => array(
        array('智慧開關', array('開關','智慧','IoT智慧')),
        array('開關蓋板', array('蓋板')),
        array('IoT捲門控制', array('捲門')),
        array('保險櫃', array('保險櫃')),
    ),

    '14.車道系統' => array(
        array('車道管制設備', array('柵欄','車道','車擋','管制')),
        array('車牌辨識', array('車牌')),
    ),

    '15.工程耗材/五金' => array(
        array('工具/設備', array('工具','設備','電鑽','梯','膠帶')),
        array('高空作業車', array('高空','作業車')),
        array('燈具', array('燈','LED燈')),
        array('太陽能', array('太陽能','太陽能板','控制器')),
    ),
);

$grandTotal = 0;

foreach ($distributions as $parentName => $rules) {
    $parentId = getTopCatId($db, $parentName);
    if (!$parentId) { echo "[跳過] 找不到: {$parentName}\n"; continue; }

    // 取得直接在主分類下的產品（不在子分類中的）
    $products = $db->prepare("SELECT id, name, model, brand, supplier FROM products WHERE category_id = ?");
    $products->execute(array($parentId));
    $prods = $products->fetchAll(PDO::FETCH_ASSOC);

    if (empty($prods)) continue;

    echo "\n--- {$parentName} ({$parentId}) 有 " . count($prods) . " 個待分配 ---\n";
    $moved = 0;

    foreach ($prods as $p) {
        $searchStr = $p['name'] . ' ' . ($p['model'] ?: '') . ' ' . ($p['brand'] ?: '') . ' ' . ($p['supplier'] ?: '');
        $matched = false;

        foreach ($rules as $rule) {
            $subName = $rule[0];
            $keywords = $rule[1];
            foreach ($keywords as $kw) {
                if (mb_stripos($searchStr, $kw) !== false) {
                    $subId = getSubCatId($db, $subName, $parentName);
                    if ($subId) {
                        $db->prepare("UPDATE products SET category_id = ? WHERE id = ?")->execute(array($subId, $p['id']));
                        $moved++;
                        $matched = true;
                        break 2;
                    }
                }
            }
        }
    }

    echo "  已分配 {$moved} / " . count($prods) . " 個\n";
    $grandTotal += $moved;

    // 剩餘
    $remain = $db->prepare("SELECT COUNT(*) FROM products WHERE category_id = ?");
    $remain->execute(array($parentId));
    $remainCount = (int)$remain->fetchColumn();
    if ($remainCount > 0) {
        echo "  剩餘 {$remainCount} 個仍在主分類\n";
    }
}

// 未分類的也做分配
echo "\n--- 未分類產品 ---\n";
$uncatId = getTopCatId($db, '未分類');
if ($uncatId) {
    $uncatProducts = $db->prepare("SELECT id, name, model, brand FROM products WHERE category_id = ?");
    $uncatProducts->execute(array($uncatId));
    $uncats = $uncatProducts->fetchAll(PDO::FETCH_ASSOC);

    $uncatRules = array(
        array('11.機櫃/電源', '機櫃配件', array('層板','背板','輪子','端子版架','線槽')),
        array('11.機櫃/電源', '鋰電池', array('電池','YUASA','湯淺','深循環')),
        array('11.機櫃/電源', '電源供應器', array('充電器','逆變器','電源')),
        array('10.配線器材', '動力箱/防水盒', array('防水箱','防水盒','鐵箱','鐵明箱','明箱','散熱')),
        array('10.配線器材', '資訊插座/面板', array('資訊盒','資訊插座')),
        array('08.線材', '網路線(Cat.6/6A)', array('Cat5','Cat6','水晶頭','水晶接頭','護套','穿透式')),
        array('12.防盜/保全系統', '感知器', array('瓦斯','偵測')),
        array('15.工程耗材/五金', '太陽能', array('太陽能')),
    );

    $ucMoved = 0;
    foreach ($uncats as $p) {
        $searchStr = $p['name'] . ' ' . ($p['model'] ?: '') . ' ' . ($p['brand'] ?: '');
        foreach ($uncatRules as $ur) {
            foreach ($ur[2] as $kw) {
                if (mb_stripos($searchStr, $kw) !== false) {
                    $targetId = getSubCatId($db, $ur[1], $ur[0]);
                    if ($targetId) {
                        $db->prepare("UPDATE products SET category_id = ? WHERE id = ?")->execute(array($targetId, $p['id']));
                        echo "  [移動] {$p['name']} → {$ur[0]} > {$ur[1]}\n";
                        $ucMoved++;
                        break 2;
                    }
                }
            }
        }
    }
    echo "  未分類移出 {$ucMoved} 個\n";
    $grandTotal += $ucMoved;
}

echo "\n=== 總計分配 {$grandTotal} 個產品 ===\n\n";

// 最終統計
echo "--- 最終各分類統計 ---\n";
$allTop = $db->query("SELECT id, name FROM product_categories WHERE (parent_id IS NULL OR parent_id = 0) ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
foreach ($allTop as $top) {
    $directCnt = $db->prepare("SELECT COUNT(*) FROM products WHERE category_id = ?");
    $directCnt->execute(array($top['id']));
    $dc = (int)$directCnt->fetchColumn();

    $subs = $db->prepare("SELECT id, name FROM product_categories WHERE parent_id = ? ORDER BY name");
    $subs->execute(array($top['id']));
    $subList = $subs->fetchAll(PDO::FETCH_ASSOC);

    $subTotal = 0;
    $subInfo = array();
    foreach ($subList as $sub) {
        $sc = $db->prepare("SELECT COUNT(*) FROM products WHERE category_id = ?");
        $sc->execute(array($sub['id']));
        $cnt = (int)$sc->fetchColumn();
        $subTotal += $cnt;
        if ($cnt > 0) $subInfo[] = "  {$sub['name']}: {$cnt}";
    }

    echo "{$top['name']}: 直屬{$dc} + 子分類{$subTotal} = " . ($dc + $subTotal) . "\n";
    foreach ($subInfo as $si) echo "{$si}\n";
}

echo '</pre>';
