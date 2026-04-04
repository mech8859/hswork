<?php
require __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (!Auth::hasPermission('admin')) { die('需要管理員權限'); }
header('Content-Type: text/plain; charset=utf-8');
$db = Database::getInstance();

$execute = isset($_GET['execute']) && $_GET['execute'] == '1';
echo $execute ? "=== 執行模式 ===\n\n" : "=== 預覽模式 === (加 ?execute=1 執行)\n\n";

// 讀取禾順原始分類
$json = file_get_contents(__DIR__ . '/../database/hershun_categories.json');
$hershunCats = json_decode($json, true);

echo "禾順分類: " . count($hershunCats) . " 個主分類\n\n";

// Step 1: 備份現有產品的分類對應（產品ID → 現有分類名稱）
echo "--- Step 1: 備份產品分類對應 ---\n";
$products = $db->query("
    SELECT p.id, p.name, p.category_id,
           pc.name AS cat_name, pc.parent_id,
           pp.name AS parent_cat_name
    FROM products p
    LEFT JOIN product_categories pc ON p.category_id = pc.id
    LEFT JOIN product_categories pp ON pc.parent_id = pp.id
")->fetchAll(PDO::FETCH_ASSOC);
echo "產品總數: " . count($products) . "\n";

// 建立產品的分類路徑 map
$productCatMap = array();
foreach ($products as $p) {
    $path = '';
    if ($p['parent_cat_name']) {
        $path = $p['parent_cat_name'] . ' > ' . $p['cat_name'];
    } elseif ($p['cat_name']) {
        $path = $p['cat_name'];
    }
    $productCatMap[$p['id']] = array(
        'name' => $p['name'],
        'old_cat_id' => $p['category_id'],
        'old_cat_name' => $p['cat_name'],
        'old_parent_name' => $p['parent_cat_name'],
        'path' => $path,
    );
}

// Step 2: 建立禾順分類名稱 → 現有分類的對照表
echo "\n--- Step 2: 分類名稱對照 ---\n";

// 現有的所有分類
$existingCats = $db->query("SELECT id, name, parent_id FROM product_categories ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$existingByName = array();
foreach ($existingCats as $c) {
    $existingByName[$c['name']] = $c;
}

if ($execute) {
    // Step 3: 清除所有現有分類
    echo "\n--- Step 3: 清除現有分類 ---\n";
    $db->exec("DELETE FROM product_categories");
    echo "已清除所有分類\n";

    // Step 4: 建立禾順分類
    echo "\n--- Step 4: 建立禾順分類 ---\n";
    $newCatMap = array(); // name => id
    $sort = 1;
    foreach ($hershunCats as $mainName => $subs) {
        $stmt = $db->prepare("INSERT INTO product_categories (name, parent_id, sort) VALUES (?, NULL, ?)");
        $stmt->execute(array($mainName, $sort));
        $mainId = (int)$db->lastInsertId();
        $newCatMap[$mainName] = $mainId;
        echo "[主] {$mainName} (ID: {$mainId})\n";

        $subSort = 1;
        foreach ($subs as $subName) {
            $stmt2 = $db->prepare("INSERT INTO product_categories (name, parent_id, sort) VALUES (?, ?, ?)");
            $stmt2->execute(array($subName, $mainId, $subSort));
            $subId = (int)$db->lastInsertId();
            $newCatMap[$subName] = $subId;
            echo "  ├─ {$subName} (ID: {$subId})\n";
            $subSort++;
        }
        $sort++;
    }
    // 加一個「未分類」
    $db->prepare("INSERT INTO product_categories (name, parent_id, sort) VALUES ('未分類', NULL, ?)")->execute(array($sort));
    $uncatId = (int)$db->lastInsertId();
    $newCatMap['未分類'] = $uncatId;
    echo "[主] 未分類 (ID: {$uncatId})\n";

    echo "\n共建立 " . count($newCatMap) . " 個分類\n";

    // Step 5: 重新配對產品
    echo "\n--- Step 5: 重新配對產品 ---\n";

    // 建立名稱模糊對照規則（舊分類名 → 新主分類名）
    $catMapping = array(
        // 現有主分類 → 禾順主分類
        '01.監控系統' => '監控系統',
        '02.門禁系統' => '門禁系統',
        '03.對講機系統' => '對講機系統',
        '04.總機/電話系統' => '總機系統',
        '05.網通設備' => '網通設備',
        '06.音響/廣播系統' => '音響喇叭設備',
        '07.投影機/視聽設備' => '投影機、配件',
        '08.線材' => '線材&相關配件',
        '09.光纖設備' => '線材&相關配件',
        '10.配線器材' => '線材&相關配件',
        '11.機櫃/電源' => '機櫃',
        '12.防盜/保全系統' => '自保防盜設備',
        '13.智能家居/IoT' => 'Panasonic IoT智慧開關(WiFi)',
        '14.車道系統' => '斌仕科技BS系列',
        '15.工程耗材/五金' => '線材&相關配件',
        '16.工程施工項次' => '檢修收費標準',
        '未分類' => '未分類',
        // 子分類對照
        'TP-Link VIGI' => 'TPLINK VIGI 監視器產品',
        '中央監控' => '監控相關配件',
        '影像傳輸器' => '監控相關配件',
        '快速球' => '監控相關配件',
        '攝影機支架/配件' => '監控相關配件',
        '監控硬碟' => '監控專用硬碟',
        '皇昇 HST' => '皇昇牌 HVR 2系列 (高清類比)',
        '聯順' => '監控系統',
        'Face+ 群英' => 'Face+ 群英人臉門禁系統',
        'SOYAL 茂旭' => 'SOYAL茂旭門禁系統',
        '聯工' => '聯工門禁系統',
        '電子鎖' => '電鎖及配件',
        '電鎖/配件' => '電鎖及配件',
        '開門按鈕/感應器' => '電鎖及配件',
        '繼電器' => '繼電器',
        '斌仕科技 BS' => '斌仕科技BS系列',
        '室內機' => '對講機系統',
        '門口機' => '對講機系統',
        '大華 對講' => '網路影像對講機系統(大華)',
        '聯盟 UNION' => '聯盟電話總機系統',
        '話機' => '電話單機',
        '電話配件' => '電話配件',
        '電話錄音' => '電話錄音',
        'TP-LINK' => 'TP-LINK',
        '交換器(Switch)' => '網通設備',
        '無線基地台(AP)' => '網通設備',
        '路由器(Router)' => '網通設備',
        '光電轉換器' => '網通設備',
        'HDMI/VGA訊號線' => 'HDMI VGA配件',
        '光纖線材' => '光纖-線材&配件 專區',
        '光纖模組' => '光纖-線材&配件 專區',
        '光纖配件' => '光纖-線材&配件 專區',
        '網路線(Cat.6/6A)' => '常用線材區',
        '電源線/控制線' => '常用線材區',
        '電話線' => '電話配件',
        '喇叭線/對講線' => '常用線材區',
        '同軸電纜線' => '常用線材區',
        '資訊插座/面板' => '網路配件',
        '電視配件' => '電視配件',
        '線槽/壓條' => '五金配件',
        '蓋板(Glatima/RISNA)' => '國際牌星光面板',
        '動力箱/防水盒' => '線材&相關配件',
        '機櫃' => '機櫃',
        'UPS不斷電系統' => 'CyberPower UPS不斷電系統',
        '電源供應器' => '電源',
        'PDU電源分配器' => 'PDU電源分配器',
        '鋰電池' => '鋰電池',
        '喇叭' => '廣播喇叭、配件',
        '擴大機' => '音響設備',
        '麥克風' => '麥克風設備',
        '布幕' => '布幕',
        '投影機' => '投影機',
        '投影機吊架' => '投影機吊架',
        '液晶螢幕' => '液晶螢幕',
        '電視/螢幕' => '液晶螢幕',
        '電視壁掛架' => '電視吊架',
        '感知器' => '防盜器材',
        '磁簧開關' => '防盜器材',
        '紅外線' => '防盜器材',
        '緊急系統' => '防盜器材',
        '五金零件' => '線材&相關配件',
        '工具/設備' => '線材&相關配件',
    );

    $matched = 0;
    $unmatched = 0;
    $unmatchedList = array();

    foreach ($productCatMap as $pid => $info) {
        $oldCat = $info['old_cat_name'];
        $oldParent = $info['old_parent_name'];
        $newCatName = null;

        // 1. 先查子分類名稱對照
        if ($oldCat && isset($catMapping[$oldCat])) {
            $newCatName = $catMapping[$oldCat];
        }
        // 2. 查主分類對照
        elseif ($oldParent && isset($catMapping[$oldParent])) {
            $newCatName = $catMapping[$oldParent];
        }
        // 3. 直接名稱匹配
        elseif ($oldCat && isset($newCatMap[$oldCat])) {
            $newCatName = $oldCat;
        }

        if ($newCatName && isset($newCatMap[$newCatName])) {
            $newCatId = $newCatMap[$newCatName];
            $db->prepare("UPDATE products SET category_id = ? WHERE id = ?")->execute(array($newCatId, $pid));
            $matched++;
        } else {
            // 歸到未分類
            $db->prepare("UPDATE products SET category_id = ? WHERE id = ?")->execute(array($uncatId, $pid));
            $unmatched++;
            if (count($unmatchedList) < 20) {
                $unmatchedList[] = "ID:{$pid} {$info['name']} (舊分類: {$info['path']})";
            }
        }
    }

    echo "配對成功: {$matched} 筆\n";
    echo "歸入未分類: {$unmatched} 筆\n";
    if ($unmatchedList) {
        echo "\n未配對範例:\n";
        foreach ($unmatchedList as $u) echo "  {$u}\n";
    }
} else {
    // 預覽
    echo "\n--- 將執行 ---\n";
    echo "1. 清除現有 " . count($existingCats) . " 個分類\n";
    $totalSub = 0;
    foreach ($hershunCats as $main => $subs) {
        echo "2. 建立主分類: {$main} (" . count($subs) . " 個子分類)\n";
        $totalSub += count($subs);
    }
    echo "\n共建立 " . count($hershunCats) . " 個主分類 + {$totalSub} 個子分類 + 1 未分類\n";
    echo "3. 重新配對 " . count($products) . " 筆產品\n";
    echo "\n※ 只動分類，不動產品的名稱/型號/品牌/價格/庫存等任何資料\n";
}

echo "\n完成\n";
