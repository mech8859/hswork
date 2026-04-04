<?php
require __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (!Auth::hasPermission('admin')) { die('需要管理員權限'); }
header('Content-Type: text/html; charset=utf-8');
echo '<pre style="font-family:monospace;font-size:13px;line-height:1.6">';
$db = Database::getInstance();

echo "=== 按廠商+功能重建子分類 ===\n\n";

function getTopId($db, $name) {
    $s = $db->prepare("SELECT id FROM product_categories WHERE name = ? AND (parent_id IS NULL OR parent_id = 0)");
    $s->execute(array($name));
    return (int)$s->fetchColumn();
}

function ensureSub($db, $parentId, $name, $sort) {
    $s = $db->prepare("SELECT id FROM product_categories WHERE name = ? AND parent_id = ?");
    $s->execute(array($name, $parentId));
    $id = $s->fetchColumn();
    if ($id) return (int)$id;
    $db->prepare("INSERT INTO product_categories (name, parent_id, sort) VALUES (?, ?, ?)")->execute(array($name, $parentId, $sort));
    return (int)$db->lastInsertId();
}

// 刪除主分類下的空子分類
function clearEmptySubs($db, $parentId) {
    $subs = $db->prepare("SELECT id, name FROM product_categories WHERE parent_id = ?");
    $subs->execute(array($parentId));
    foreach ($subs->fetchAll(PDO::FETCH_ASSOC) as $sub) {
        $cnt = $db->prepare("SELECT COUNT(*) FROM products WHERE category_id = ?");
        $cnt->execute(array($sub['id']));
        if ((int)$cnt->fetchColumn() === 0) {
            // 確認沒有子子分類
            $cc = $db->prepare("SELECT COUNT(*) FROM product_categories WHERE parent_id = ?");
            $cc->execute(array($sub['id']));
            if ((int)$cc->fetchColumn() === 0) {
                $db->prepare("DELETE FROM product_categories WHERE id = ?")->execute(array($sub['id']));
            }
        }
    }
}

// 按 supplier 分配產品到子分類
function distributeBySupplier($db, $parentId, $parentName, $supplierMap, $funcRules) {
    // 建立廠商子分類
    $sort = 1;
    $subIds = array();
    foreach ($supplierMap as $subName => $suppliers) {
        $subIds[$subName] = ensureSub($db, $parentId, $subName, $sort++);
    }
    // 建立功能子分類
    foreach ($funcRules as $subName => $keywords) {
        $subIds[$subName] = ensureSub($db, $parentId, $subName, $sort++);
    }

    // 取得主分類直屬產品
    $prods = $db->prepare("SELECT id, name, model, brand, supplier FROM products WHERE category_id = ?");
    $prods->execute(array($parentId));
    $all = $prods->fetchAll(PDO::FETCH_ASSOC);

    $moved = 0;
    foreach ($all as $p) {
        $sup = trim($p['supplier'] ?: '');
        $matched = false;

        // 先比對廠商
        foreach ($supplierMap as $subName => $suppliers) {
            foreach ($suppliers as $s) {
                if ($sup && mb_stripos($sup, $s) !== false) {
                    $db->prepare("UPDATE products SET category_id = ? WHERE id = ?")->execute(array($subIds[$subName], $p['id']));
                    $moved++;
                    $matched = true;
                    break 2;
                }
            }
        }

        // 再比對功能關鍵字（用產品名稱）
        if (!$matched) {
            $searchStr = $p['name'] . ' ' . ($p['model'] ?: '') . ' ' . ($p['brand'] ?: '');
            foreach ($funcRules as $subName => $keywords) {
                foreach ($keywords as $kw) {
                    if (mb_stripos($searchStr, $kw) !== false) {
                        $db->prepare("UPDATE products SET category_id = ? WHERE id = ?")->execute(array($subIds[$subName], $p['id']));
                        $moved++;
                        $matched = true;
                        break 2;
                    }
                }
            }
        }
    }

    echo "  {$parentName}: 分配 {$moved} / " . count($all) . "\n";
    return $moved;
}

$total = 0;

// ============================================================
// 01.監控系統
// ============================================================
$pid = getTopId($db, '01.監控系統');
clearEmptySubs($db, $pid);
echo "--- 01.監控系統 ---\n";
$total += distributeBySupplier($db, $pid, '01.監控系統', array(
    '大華 Dahua' => array('大華'),
    '皇昇 HST' => array('皇昇'),
    'TP-Link VIGI' => array('Tp-Link','捷元'),
    '昇銳' => array('昇銳'),
    '聲寶' => array('聲寶'),
    '群洲' => array('群洲'),
    '可取' => array('可取'),
    '聯順' => array('聯順'),
    '禾順' => array('禾順'),
    'DJS' => array('DJS'),
    'UNIFI' => array('UNIFI'),
    '哈柏' => array('哈柏'),
    '長景錄' => array('長景錄'),
    '協拓' => array('協拓'),
), array(
    '監控硬碟' => array('硬碟','HDD','Seagate'),
    '快速球' => array('快速球','PTZ'),
    '攝影機支架/配件' => array('支架','壁掛','立柱','夾具','防護罩','接線盒','集線盒'),
    '影像傳輸器' => array('影像傳輸','雙絞線','分配器','轉換器'),
    '中央監控' => array('中央監控'),
));

// ============================================================
// 02.門禁系統
// ============================================================
$pid = getTopId($db, '02.門禁系統');
clearEmptySubs($db, $pid);
echo "--- 02.門禁系統 ---\n";
$total += distributeBySupplier($db, $pid, '02.門禁系統', array(
    'SOYAL 茂旭' => array('SOYAL','茂旭'),
    '聯工' => array('聯工'),
    'Face+ 群英' => array('群英','Face'),
    'BB Home' => array('BB Home','BB home'),
    '斌仕科技 BS' => array('斌仕'),
), array(
    '電子鎖' => array('電子鎖','密碼鎖','指紋鎖'),
    '電鎖/配件' => array('磁力鎖','陽極鎖','電鎖','鎖配件','感應卡'),
    '開門按鈕/感應器' => array('開門','按鈕','感應'),
    '繼電器' => array('繼電器'),
));

// ============================================================
// 03.對講機系統
// ============================================================
$pid = getTopId($db, '03.對講機系統');
clearEmptySubs($db, $pid);
echo "--- 03.對講機系統 ---\n";
$total += distributeBySupplier($db, $pid, '03.對講機系統', array(
    'Hometek' => array('Hometek','鼎瀚'),
    'BB Home' => array('BB Home','BB home'),
    '大華 對講' => array('大華'),
    '明谷' => array('明谷'),
    '斌仕科技 BS' => array('斌仕'),
), array(
    '門口機' => array('門口機'),
    '室內機' => array('室內機'),
    '防水罩' => array('防水罩','防水箱'),
    '對講機配件' => array('配件','電源','介面卡','伺服器'),
));

// ============================================================
// 04.總機/電話系統
// ============================================================
$pid = getTopId($db, '04.總機/電話系統');
clearEmptySubs($db, $pid);
echo "--- 04.總機/電話系統 ---\n";
$total += distributeBySupplier($db, $pid, '04.總機/電話系統', array(
    '聯盟 UNION' => array('聯盟'),
    '萬國 FX' => array('萬國'),
), array(
    '話機' => array('話機','標準型'),
    '電話錄音' => array('錄音'),
    '電話配件' => array('配件','門口機','中繼','轉接','套裝','魔音'),
));

// ============================================================
// 05.網通設備
// ============================================================
$pid = getTopId($db, '05.網通設備');
clearEmptySubs($db, $pid);
echo "--- 05.網通設備 ---\n";
$total += distributeBySupplier($db, $pid, '05.網通設備', array(
    'TP-LINK' => array('Tp-Link','TP-LINK','捷元'),
    'Zyxel 合勤' => array('zyxel','Zyxel','合勤'),
    'DrayTek 居易' => array('Dray','居易'),
    '振翔科技' => array('振翔'),
    '聯力科技' => array('聯力'),
), array(
    '交換器(Switch)' => array('交換器','Switch','PoE'),
    '路由器(Router)' => array('路由','Router','分享器'),
    '無線基地台(AP)' => array('無線','WiFi','wifi','AP','Mesh','deco','基地台'),
    '光電轉換器' => array('光電轉換','光纖模組','SFP'),
    '長距離無線傳輸' => array('長距離','點對點'),
    '網通配件' => array('配件','水晶頭','水晶接頭','保護套','穿透式'),
));

// ============================================================
// 06~16 用功能分類即可（品牌較少不需按廠商分）
// ============================================================
$funcDistributions = array(
    '06.音響/廣播系統' => array(
        '擴大機' => array('擴大機','歐姆','高壓'),
        '喇叭' => array('喇叭','音柱','號角'),
        '麥克風' => array('麥克風','MIC'),
        '混音器' => array('混音','音像調節'),
        '網路廣播(IP PA)' => array('IP PA','網路廣播','TONNET'),
        '穩壓器' => array('穩壓'),
    ),
    '07.投影機/視聽設備' => array(
        '投影機' => array('投影機','EPSON','雷射','燈泡'),
        '布幕' => array('布幕','桌幕','地拉幕','手拉幕','電動布幕','ZERO','X-Press','Tripod'),
        '投影機吊架' => array('吊架','升降架','GPCM','GrandView','力神','黑力士'),
        '電視壁掛架' => array('壁掛架','懸吊架','電視架'),
        '電視/螢幕' => array('電視','螢幕'),
    ),
    '08.線材' => array(
        'HDMI/VGA訊號線' => array('HDMI','VGA','訊號','艾吉斯','切換器'),
        '網路線(Cat.6/6A)' => array('Cat.6','Cat6','Cat.5','Cat5','網路線','UTP','LSZH','R&M','跳線','Patch','Panel','FM45','理線','低煙無鹵','智能模組','網路點'),
        '光纖線材' => array('光纖','光纜','單模'),
        '同軸電纜線' => array('同軸','RG'),
        '電話線' => array('電話線','電話電纜','C型端子','RJ11','電話插座'),
        '電源線/控制線' => array('電源線','控制電纜','延長線'),
        '喇叭線/對講線' => array('喇叭線','對講線','廣播線'),
        '防盜線' => array('防盜線'),
        '資訊插座/面板' => array('資訊插座','資訊面板','面板','星光'),
        '電視配件' => array('天線','分歧器','分配器','訊號放大','電視接頭','電視插座'),
    ),
    '09.光纖設備' => array(
        '光纖配件' => array('配件','接頭','耦合','尾纖','配線架','終端盒'),
        '光電轉換器' => array('光電轉換','轉換器','DJS','工業級'),
        '光纖模組' => array('模組','SFP'),
        '熔接材料' => array('熔接','熱縮'),
        '光纖線材' => array('光纖線','光纜','單模','跳線'),
    ),
    '10.配線器材' => array(
        '動力箱/防水盒' => array('動力箱','防水盒','防水箱'),
        '蓋板(Glatima/RISNA)' => array('蓋板','Glatima','RISNA','開關','調光'),
        '資訊插座/面板' => array('資訊插座','面板','星光'),
        '線槽/壓條' => array('線槽','壓條'),
        '束帶/固定夾' => array('束帶','固定夾','矽利康'),
        '插座插頭' => array('插座','插頭'),
        '電視配件' => array('天線','分歧器','分配器','訊號放大','電視接頭','電視插座'),
    ),
    '11.機櫃/電源' => array(
        '機櫃' => array('機櫃','壁掛式','活動式','開放式'),
        '機櫃配件' => array('層板','背板','輪子','儀器支撐','端子版架'),
        'UPS不斷電系統' => array('UPS','不斷電','CyberPower'),
        'PDU電源分配器' => array('PDU'),
        '電源供應器' => array('電源','明緯','充電器','變壓器','Hometek'),
        '鋰電池' => array('鋰電池','電池','YUASA','湯淺','深循環'),
    ),
    '12.防盜/保全系統' => array(
        '磁簧開關' => array('磁簧'),
        '感知器' => array('感知器','PIR','偵測器','煙霧','一氧化碳','瓦斯','音頻','捲門感知'),
        '緊急按鈕' => array('緊急按鈕'),
        '警報主機' => array('報警','警報','警報喇叭'),
        '雲保全設備' => array('雲保全','鎧鋒','KCA','保全'),
        '紅外線' => array('紅外線'),
    ),
    '13.智能家居/IoT' => array(
        '智慧開關' => array('開關','智慧','IoT智慧'),
        '開關蓋板' => array('蓋板'),
        'IoT捲門控制' => array('捲門'),
        '保險櫃' => array('保險櫃'),
    ),
);

foreach ($funcDistributions as $parentName => $rules) {
    $pid = getTopId($db, $parentName);
    if (!$pid) continue;
    clearEmptySubs($db, $pid);

    $prods = $db->prepare("SELECT id, name, model, brand, supplier FROM products WHERE category_id = ?");
    $prods->execute(array($pid));
    $all = $prods->fetchAll(PDO::FETCH_ASSOC);
    if (empty($all)) continue;

    echo "--- {$parentName} ---\n";
    $sort = 1;
    $subIds = array();
    foreach ($rules as $subName => $kws) {
        $subIds[$subName] = ensureSub($db, $pid, $subName, $sort++);
    }

    $moved = 0;
    foreach ($all as $p) {
        $searchStr = $p['name'] . ' ' . ($p['model'] ?: '') . ' ' . ($p['brand'] ?: '') . ' ' . ($p['supplier'] ?: '');
        foreach ($rules as $subName => $keywords) {
            $matched = false;
            foreach ($keywords as $kw) {
                if (mb_stripos($searchStr, $kw) !== false) {
                    $db->prepare("UPDATE products SET category_id = ? WHERE id = ?")->execute(array($subIds[$subName], $p['id']));
                    $moved++;
                    $matched = true;
                    break 2;
                }
            }
        }
    }
    echo "  分配 {$moved} / " . count($all) . "\n";
    $total += $moved;
}

echo "\n=== 總計分配 {$total} 個 ===\n\n";

// 最終統計
echo "--- 最終統計 ---\n";
$allTop = $db->query("SELECT id, name FROM product_categories WHERE (parent_id IS NULL OR parent_id = 0) ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
foreach ($allTop as $top) {
    $dc = $db->prepare("SELECT COUNT(*) FROM products WHERE category_id = ?");
    $dc->execute(array($top['id']));
    $directCount = (int)$dc->fetchColumn();

    $subs = $db->prepare("SELECT id, name FROM product_categories WHERE parent_id = ? ORDER BY sort, name");
    $subs->execute(array($top['id']));
    $subList = $subs->fetchAll(PDO::FETCH_ASSOC);

    $subTotal = 0;
    $subInfo = array();
    foreach ($subList as $sub) {
        $sc = $db->prepare("SELECT COUNT(*) FROM products WHERE category_id = ?");
        $sc->execute(array($sub['id']));
        $cnt = (int)$sc->fetchColumn();
        $subTotal += $cnt;
        $subInfo[] = "    └ {$sub['name']}: {$cnt}";
    }

    echo "{$top['name']}: 直屬{$directCount} + 子分類{$subTotal}\n";
    foreach ($subInfo as $si) echo "{$si}\n";
}

echo '</pre>';
