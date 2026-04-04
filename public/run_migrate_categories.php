<?php
/**
 * 產品分類重整遷移腳本
 * 1. 建立新分類架構
 * 2. 將產品移至新分類
 * 3. 將工程項次移入產品
 * 4. 清除舊分類
 */
require __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (!Auth::hasPermission('admin')) { die('需要管理員權限'); }

header('Content-Type: text/html; charset=utf-8');
echo '<pre style="font-family:monospace;font-size:13px;line-height:1.6">';

$db = Database::getInstance();
$dryRun = isset($_GET['execute']) ? false : true;

if ($dryRun) {
    echo "=== 預覽模式 === (加 ?execute=1 實際執行)\n\n";
} else {
    echo "=== 執行模式 ===\n\n";
}

// =============================================
// Step 1: 建立新頂層分類
// =============================================
echo "--- Step 1: 建立新頂層分類 ---\n";

$newTopCategories = array(
    array('name' => '01.監控系統', 'sort' => 1),
    array('name' => '02.門禁系統', 'sort' => 2),
    array('name' => '03.對講機系統', 'sort' => 3),
    array('name' => '04.總機/電話系統', 'sort' => 4),
    array('name' => '05.網通設備', 'sort' => 5),
    array('name' => '06.音響/廣播系統', 'sort' => 6),
    array('name' => '07.投影機/視聽設備', 'sort' => 7),
    array('name' => '08.線材', 'sort' => 8),
    array('name' => '09.光纖設備', 'sort' => 9),
    array('name' => '10.配線器材', 'sort' => 10),
    array('name' => '11.機櫃/電源', 'sort' => 11),
    array('name' => '12.防盜/保全系統', 'sort' => 12),
    array('name' => '13.智能家居/IoT', 'sort' => 13),
    array('name' => '14.車道系統', 'sort' => 14),
    array('name' => '15.工程耗材/五金', 'sort' => 15),
    array('name' => '16.工程施工項次', 'sort' => 16),
);

$newSubCategories = array(
    '01.監控系統' => array('網路攝影機(IP)', '類比攝影機', '錄影主機(NVR/DVR)', '監控硬碟', '快速球', '攝影機支架/配件', '影像傳輸器', '中央監控'),
    '02.門禁系統' => array('門禁控制器', '讀卡機', '電鎖(磁力鎖/陽極鎖)', '電鎖配件', '開門按鈕/感應器', '電子鎖', '人臉辨識設備', '繼電器'),
    '03.對講機系統' => array('門口機', '室內機', '對講機配件', '防水罩'),
    '04.總機/電話系統' => array('聯盟電話總機', '萬國總機', '話機', '電話錄音', '電話配件', '電話單機'),
    '05.網通設備' => array('交換器(Switch)', '路由器(Router)', '無線基地台(AP)', '光電轉換器', '長距離無線傳輸', '網通配件', '4G路由器'),
    '06.音響/廣播系統' => array('擴大機', '喇叭', '麥克風', '混音器', '網路廣播(IP PA)', '穩壓器'),
    '07.投影機/視聽設備' => array('投影機', '布幕', '投影機吊架', '電視/螢幕', '電視壁掛架', '液晶螢幕'),
    '08.線材' => array('網路線(Cat.6/6A)', '光纖線材', '同軸電纜線', '電話線', '電源線/控制線', 'HDMI/VGA訊號線', '喇叭線/對講線', '防盜線'),
    '09.光纖設備' => array('光纖模組', '光纖配件', '光電轉換器', '熔接材料', '光纖線材'),
    '10.配線器材' => array('資訊插座/面板', '線槽/壓條', '束帶/固定夾', '動力箱/防水盒', '跳線/跳線面板', '蓋板(Glatima/RISNA)', '插座插頭', '電視配件'),
    '11.機櫃/電源' => array('機櫃', '機櫃配件', 'UPS不斷電系統', 'PDU電源分配器', '電源供應器', '穩壓器', '鋰電池'),
    '12.防盜/保全系統' => array('感知器', '磁簧開關', '警報主機', '緊急按鈕', '雲保全設備', '緊急系統', '紅外線'),
    '13.智能家居/IoT' => array('智慧開關', '開關蓋板', 'IoT捲門控制', '保險櫃'),
    '14.車道系統' => array('車道管制設備', '車牌辨識'),
    '15.工程耗材/五金' => array('五金零件', '工具/設備', '高空作業車', '燈具', '太陽能', '電風扇'),
    '16.工程施工項次' => array('監視器工程', '網路線工程', '光纖工程', '電話工程', '廣播喇叭工程', '對講機工程', '電梯工程', '基本工程費'),
);

$newCatIds = array(); // name => id

if (!$dryRun) {
    foreach ($newTopCategories as $tc) {
        // 檢查是否已存在
        $chk = $db->prepare("SELECT id FROM product_categories WHERE name = ? AND (parent_id IS NULL OR parent_id = 0)");
        $chk->execute(array($tc['name']));
        $existId = $chk->fetchColumn();
        if ($existId) {
            $newCatIds[$tc['name']] = (int)$existId;
            echo "  [已存在] {$tc['name']} (ID: {$existId})\n";
        } else {
            $db->prepare("INSERT INTO product_categories (name, parent_id, sort) VALUES (?, NULL, ?)")->execute(array($tc['name'], $tc['sort']));
            $id = (int)$db->lastInsertId();
            $newCatIds[$tc['name']] = $id;
            echo "  [新增] {$tc['name']} (ID: {$id})\n";
        }
    }

    // 建立子分類
    echo "\n--- Step 1b: 建立子分類 ---\n";
    foreach ($newSubCategories as $parentName => $subs) {
        $parentId = isset($newCatIds[$parentName]) ? $newCatIds[$parentName] : 0;
        if (!$parentId) { echo "  [跳過] 找不到父分類: {$parentName}\n"; continue; }
        foreach ($subs as $si => $subName) {
            $chk = $db->prepare("SELECT id FROM product_categories WHERE name = ? AND parent_id = ?");
            $chk->execute(array($subName, $parentId));
            $existId = $chk->fetchColumn();
            if ($existId) {
                $newCatIds[$parentName . '>' . $subName] = (int)$existId;
            } else {
                $db->prepare("INSERT INTO product_categories (name, parent_id, sort) VALUES (?, ?, ?)")->execute(array($subName, $parentId, $si + 1));
                $newCatIds[$parentName . '>' . $subName] = (int)$db->lastInsertId();
                echo "  [新增] {$parentName} > {$subName}\n";
            }
        }
    }
} else {
    echo "  將建立 " . count($newTopCategories) . " 個頂層分類\n";
    $subTotal = 0;
    foreach ($newSubCategories as $subs) $subTotal += count($subs);
    echo "  將建立 {$subTotal} 個子分類\n";
}

// =============================================
// Step 2: 產品移動對照表
// =============================================
echo "\n--- Step 2: 移動產品到新分類 ---\n";

// 舊分類名稱 → 新分類路徑 (parent>child)
$moveMap = array(
    // 監控
    '監控' => '01.監控系統',
    '監控系統' => '01.監控系統',
    '中央監控' => '01.監控系統>中央監控',
    // 門禁
    '門禁' => '02.門禁系統',
    '門禁系統' => '02.門禁系統',
    '電子鎖' => '02.門禁系統>電子鎖',
    '感應/開門按鈕' => '02.門禁系統>開門按鈕/感應器',
    // 對講機
    '對講機系統' => '03.對講機系統',
    '斌仕科技BS系列' => '03.對講機系統',
    // 總機
    '電話總機' => '04.總機/電話系統',
    '總機系統' => '04.總機/電話系統',
    // 網通
    '網通' => '05.網通設備',
    '網通設備' => '05.網通設備',
    // 音響
    '商用音響' => '06.音響/廣播系統',
    '音響喇叭設備' => '06.音響/廣播系統',
    '麥克風' => '06.音響/廣播系統>麥克風',
    // 投影/視聽
    '投影機、配件' => '07.投影機/視聽設備',
    '電視/螢幕' => '07.投影機/視聯設備>電視/螢幕',
    '電視吊架' => '07.投影機/視聽設備>電視壁掛架',
    '液晶螢幕' => '07.投影機/視聽設備>液晶螢幕',
    '電腦.視聽' => '07.投影機/視聽設備',
    // 線材
    '線材' => '08.線材',
    '線材&相關配件' => '08.線材',
    'HDMI' => '08.線材>HDMI/VGA訊號線',
    'HDMI VGA配件' => '08.線材>HDMI/VGA訊號線',
    'VGA' => '08.線材>HDMI/VGA訊號線',
    // 光纖
    '光纖' => '09.光纖設備',
    // 配線
    '資訊插座' => '10.配線器材>資訊插座/面板',
    '壓條/線槽' => '10.配線器材>線槽/壓條',
    '動力箱/防水盒' => '10.配線器材>動力箱/防水盒',
    'Panasonic IoT智慧開關(WiFi)' => '10.配線器材>蓋板(Glatima/RISNA)',
    // 機櫃/電源
    '機櫃' => '11.機櫃/電源>機櫃',
    'UPS' => '11.機櫃/電源>UPS不斷電系統',
    '不斷電系統' => '11.機櫃/電源>UPS不斷電系統',
    'CyberPower UPS不斷電系統' => '11.機櫃/電源>UPS不斷電系統',
    'PDU' => '11.機櫃/電源>PDU電源分配器',
    '電源' => '11.機櫃/電源>電源供應器',
    '鋰電池' => '11.機櫃/電源>鋰電池',
    '儲存' => '11.機櫃/電源',
    // 防盜
    '自保防盜設備' => '12.防盜/保全系統',
    '緊急系統' => '12.防盜/保全系統>緊急系統',
    '紅外線' => '12.防盜/保全系統>紅外線',
    // 智能家居
    '智能家居' => '13.智能家居/IoT',
    'IOT智能捲門控制' => '13.智能家居/IoT>IoT捲門控制',
    '保險櫃' => '13.智能家居/IoT>保險櫃',
    // 車道
    '車道系統' => '14.車道系統',
    // 工程耗材
    '五金另料' => '15.工程耗材/五金>五金零件',
    '工具/設備' => '15.工程耗材/五金>工具/設備',
    '高空作業車' => '15.工程耗材/五金>高空作業車',
    '燈具' => '15.工程耗材/五金>燈具',
    '太陽能' => '15.工程耗材/五金>太陽能',
    '電風扇' => '15.工程耗材/五金>電風扇',
    '支架/立柱' => '15.工程耗材/五金',
    '立柱/支架' => '15.工程耗材/五金',
    // 工程項次
    '工作項次' => '16.工程施工項次',
    '檢修收費標準' => '16.工程施工項次',
);

// 非產品分類（要刪除的）
$nonProductCategories = array('廠商聯絡資料', '財務行銷回報資料', '特殊案例', '授權', '文具用品');

if (!$dryRun) {
    // 取得所有舊分類的 ID → name 對照
    $allOldCats = $db->query("SELECT id, name, parent_id FROM product_categories ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    $oldCatById = array();
    foreach ($allOldCats as $c) $oldCatById[$c['id']] = $c;

    $movedCount = 0;
    foreach ($moveMap as $oldName => $newPath) {
        // 找到舊分類
        $oldCats = $db->prepare("SELECT id FROM product_categories WHERE name = ?");
        $oldCats->execute(array($oldName));
        $oldIds = $oldCats->fetchAll(PDO::FETCH_COLUMN);

        if (empty($oldIds)) continue;

        // 解析新路徑
        $targetId = null;
        if (isset($newCatIds[$newPath])) {
            $targetId = $newCatIds[$newPath];
        } else {
            // 嘗試找
            $parts = explode('>', $newPath);
            if (count($parts) == 1) {
                $chk = $db->prepare("SELECT id FROM product_categories WHERE name = ? AND (parent_id IS NULL OR parent_id = 0)");
                $chk->execute(array($parts[0]));
                $targetId = $chk->fetchColumn();
            }
        }
        if (!$targetId) {
            echo "  [警告] 找不到目標分類: {$newPath}\n";
            continue;
        }

        // 收集此分類及所有子孫分類 ID
        $allSourceIds = $oldIds;
        $queue = $oldIds;
        while (!empty($queue)) {
            $current = array_shift($queue);
            $children = $db->prepare("SELECT id FROM product_categories WHERE parent_id = ?");
            $children->execute(array($current));
            foreach ($children->fetchAll(PDO::FETCH_COLUMN) as $childId) {
                $allSourceIds[] = $childId;
                $queue[] = $childId;
            }
        }
        $allSourceIds = array_unique($allSourceIds);

        // 移動產品
        if (!empty($allSourceIds)) {
            $ph = implode(',', array_fill(0, count($allSourceIds), '?'));
            $countStmt = $db->prepare("SELECT COUNT(*) FROM products WHERE category_id IN ({$ph})");
            $countStmt->execute($allSourceIds);
            $cnt = (int)$countStmt->fetchColumn();
            if ($cnt > 0) {
                $db->prepare("UPDATE products SET category_id = ? WHERE category_id IN ({$ph})")->execute(array_merge(array($targetId), $allSourceIds));
                echo "  [移動] {$oldName} → {$newPath} ({$cnt} 個產品)\n";
                $movedCount += $cnt;
            }
        }
    }
    echo "\n  共移動 {$movedCount} 個產品\n";
} else {
    echo "  將根據 " . count($moveMap) . " 條對照規則移動產品\n";
}

// =============================================
// Step 3: 工程項次移入產品
// =============================================
echo "\n--- Step 3: 工程項次移入產品分類 ---\n";

$engCatMap = array(
    '光纖工程' => '16.工程施工項次>光纖工程',
    '監視器工程' => '16.工程施工項次>監視器工程',
    '網路線工程' => '16.工程施工項次>網路線工程',
    '廣播喇叭工程' => '16.工程施工項次>廣播喇叭工程',
    '電話工程' => '16.工程施工項次>電話工程',
    '影視對講工程' => '16.工程施工項次>對講機工程',
    '電梯工程' => '16.工程施工項次>電梯工程',
    '基本工程' => '16.工程施工項次>基本工程費',
);

if (!$dryRun) {
    try {
        $engItems = $db->query("SELECT * FROM engineering_items WHERE is_active = 1 ORDER BY category, sort_order")->fetchAll(PDO::FETCH_ASSOC);
        $insertedEng = 0;
        foreach ($engItems as $ei) {
            $catPath = isset($engCatMap[$ei['category']]) ? $engCatMap[$ei['category']] : '16.工程施工項次';
            $targetId = isset($newCatIds[$catPath]) ? $newCatIds[$catPath] : null;
            if (!$targetId) {
                // fallback: 頂層
                $targetId = isset($newCatIds['16.工程施工項次']) ? $newCatIds['16.工程施工項次'] : null;
            }
            if (!$targetId) { echo "  [跳過] 找不到分類: {$catPath}\n"; continue; }

            // 檢查是否已存在同名產品
            $chk = $db->prepare("SELECT id FROM products WHERE name = ? AND category_id = ?");
            $chk->execute(array($ei['name'], $targetId));
            if ($chk->fetchColumn()) {
                echo "  [已存在] {$ei['name']}\n";
                continue;
            }

            $db->prepare("INSERT INTO products (name, unit, price, cost, category_id, is_active) VALUES (?, ?, ?, ?, ?, 1)")
               ->execute(array($ei['name'], $ei['unit'], $ei['default_price'], $ei['default_cost'], $targetId));
            $insertedEng++;
            echo "  [新增產品] {$ei['category']} > {$ei['name']} (價:{$ei['default_price']})\n";
        }
        echo "\n  共新增 {$insertedEng} 個工程項次產品\n";
    } catch (Exception $e) {
        echo "  [錯誤] engineering_items: " . $e->getMessage() . "\n";
    }
} else {
    echo "  將從 engineering_items 移入產品分類\n";
}

// =============================================
// Step 4: 清除非產品分類和空舊分類
// =============================================
echo "\n--- Step 4: 清除非產品分類 ---\n";

if (!$dryRun) {
    foreach ($nonProductCategories as $nc) {
        // 先移除此分類下的產品到 15.工程耗材 (如果有)
        $stmt = $db->prepare("SELECT id FROM product_categories WHERE name = ?");
        $stmt->execute(array($nc));
        $ncIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
        foreach ($ncIds as $ncId) {
            $cnt = $db->prepare("SELECT COUNT(*) FROM products WHERE category_id = ?");
            $cnt->execute(array($ncId));
            $pCnt = (int)$cnt->fetchColumn();
            if ($pCnt > 0) {
                echo "  [警告] {$nc} 有 {$pCnt} 個產品，先不刪除\n";
            }
        }
    }
    echo "  非產品分類需手動處理（有產品的不自動刪除）\n";
} else {
    echo "  將處理非產品分類: " . implode(', ', $nonProductCategories) . "\n";
}

// =============================================
// 完成統計
// =============================================
echo "\n--- 統計 ---\n";
$totalProducts = $db->query("SELECT COUNT(*) FROM products")->fetchColumn();
$totalCats = $db->query("SELECT COUNT(*) FROM product_categories")->fetchColumn();
$uncategorized = $db->query("SELECT COUNT(*) FROM products WHERE category_id IS NULL OR category_id = 0")->fetchColumn();
echo "總產品: {$totalProducts}\n";
echo "總分類: {$totalCats}\n";
echo "未分類: {$uncategorized}\n";

if ($dryRun) {
    echo "\n\n=== 這是預覽模式，沒有實際更改 ===\n";
    echo "確認無誤後，訪問 ?execute=1 執行\n";
}

echo '</pre>';
