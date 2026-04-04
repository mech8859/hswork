<?php
require __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (!Auth::hasPermission('admin')) { die('需要管理員權限'); }
header('Content-Type: text/html; charset=utf-8');
echo '<pre style="font-family:monospace;font-size:13px;line-height:1.6">';
$db = Database::getInstance();
$dryRun = false;
echo "=== 執行模式 ===\n\n";

// 取得新分類 ID
function getCatId($db, $sub, $parent) {
    $p = $db->prepare("SELECT id FROM product_categories WHERE name = ? AND (parent_id IS NULL OR parent_id = 0)");
    $p->execute(array($parent));
    $pid = $p->fetchColumn();
    if (!$pid) return null;
    if (!$sub) return $pid;
    $s = $db->prepare("SELECT id FROM product_categories WHERE name = ? AND parent_id = ?");
    $s->execute(array($sub, $pid));
    return $s->fetchColumn() ?: $pid;
}

// 未分類產品
$stmt = $db->query("SELECT id, name, model, brand, supplier FROM products WHERE category_id IS NULL OR category_id = 0 ORDER BY name");
$uncats = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "未分類產品: " . count($uncats) . " 個\n\n";

// 關鍵字比對規則 [關鍵字 => [子分類, 頂層分類]]
$rules = array(
    // 監控
    array('keywords' => array('攝影機','NVR','DVR','錄影主機','監視','監控','CCTV','IPC'), 'sub' => null, 'parent' => '01.監控系統'),
    array('keywords' => array('硬碟','HDD'), 'sub' => '監控硬碟', 'parent' => '01.監控系統'),
    array('keywords' => array('快速球','PTZ'), 'sub' => '快速球', 'parent' => '01.監控系統'),
    array('keywords' => array('支架','壁掛','立柱'), 'sub' => '攝影機支架/配件', 'parent' => '01.監控系統'),
    // 門禁
    array('keywords' => array('門禁','讀卡','控制器','感應卡','門鎖','磁力鎖','陽極鎖','SOYAL','聯工'), 'sub' => null, 'parent' => '02.門禁系統'),
    array('keywords' => array('電子鎖','密碼鎖','指紋鎖'), 'sub' => '電子鎖', 'parent' => '02.門禁系統'),
    // 對講
    array('keywords' => array('對講','室內機','門口機','Hometek'), 'sub' => null, 'parent' => '03.對講機系統'),
    // 總機
    array('keywords' => array('總機','話機','電話','ISDK','UD-'), 'sub' => null, 'parent' => '04.總機/電話系統'),
    // 網通
    array('keywords' => array('交換器','Switch','路由','Router','AP','WiFi','PoE','網路','TP-LINK','Dray','zyxel','VIGI'), 'sub' => null, 'parent' => '05.網通設備'),
    // 音響
    array('keywords' => array('喇叭','擴大機','音響','廣播','麥克風','混音'), 'sub' => null, 'parent' => '06.音響/廣播系統'),
    // 投影
    array('keywords' => array('投影','布幕','EPSON','吊架'), 'sub' => null, 'parent' => '07.投影機/視聽設備'),
    array('keywords' => array('電視','螢幕','LCD','LED顯示'), 'sub' => '電視/螢幕', 'parent' => '07.投影機/視聽設備'),
    // 線材
    array('keywords' => array('HDMI','VGA','訊號線','光纖線','網路線','Cat.6','Cat6','同軸','電話線','喇叭線','電纜','跳線','Patch'), 'sub' => null, 'parent' => '08.線材'),
    // 光纖
    array('keywords' => array('光纖','光電轉換','熔接','SFP'), 'sub' => null, 'parent' => '09.光纖設備'),
    // 配線
    array('keywords' => array('資訊插座','面板','線槽','壓條','束帶','蓋板','Glatima','RISNA','動力箱','防水盒'), 'sub' => null, 'parent' => '10.配線器材'),
    // 機櫃/電源
    array('keywords' => array('機櫃','UPS','不斷電','PDU','電源供應','明緯','穩壓','鋰電池','儲存','NAS'), 'sub' => null, 'parent' => '11.機櫃/電源'),
    // 防盜
    array('keywords' => array('防盜','磁簧','感知器','警報','緊急','紅外線偵測'), 'sub' => null, 'parent' => '12.防盜/保全系統'),
    // 智能
    array('keywords' => array('智慧開關','IoT','智能','保險櫃'), 'sub' => null, 'parent' => '13.智能家居/IoT'),
    // 車道
    array('keywords' => array('車道','柵欄','車牌'), 'sub' => null, 'parent' => '14.車道系統'),
);

$moved = 0;
$unmatched = array();

foreach ($uncats as $p) {
    $searchStr = $p['name'] . ' ' . ($p['model'] ?: '') . ' ' . ($p['brand'] ?: '') . ' ' . ($p['supplier'] ?: '');
    $matched = false;

    foreach ($rules as $rule) {
        foreach ($rule['keywords'] as $kw) {
            if (mb_stripos($searchStr, $kw) !== false) {
                $targetId = getCatId($db, $rule['sub'], $rule['parent']);
                if ($targetId) {
                    $target = $rule['parent'] . ($rule['sub'] ? ' > ' . $rule['sub'] : '');
                    echo "[分類] {$p['name']} → {$target}\n";
                    if (!$dryRun) {
                        $db->prepare("UPDATE products SET category_id = ? WHERE id = ?")->execute(array($targetId, $p['id']));
                    }
                    $moved++;
                    $matched = true;
                    break 2;
                }
            }
        }
    }

    if (!$matched) {
        $unmatched[] = $p;
    }
}

echo "\n已分類: {$moved} 個\n";

// 無法比對的放到「未分類」
if (!empty($unmatched)) {
    // 建立「未分類」頂層分類
    $chk = $db->prepare("SELECT id FROM product_categories WHERE name = '未分類' AND (parent_id IS NULL OR parent_id = 0)");
    $chk->execute();
    $fallbackId = $chk->fetchColumn();
    if (!$fallbackId) {
        $db->prepare("INSERT INTO product_categories (name, parent_id, sort) VALUES ('未分類', NULL, 99)")->execute();
        $fallbackId = (int)$db->lastInsertId();
        echo "\n[新增] 「未分類」分類 (ID: {$fallbackId})\n";
    }
    echo "\n--- 無法自動判斷，歸入「未分類」 ---\n";
    foreach ($unmatched as $p) {
        echo "[歸入] {$p['name']}";
        if ($p['brand']) echo " ({$p['brand']})";
        echo "\n";
        $db->prepare("UPDATE products SET category_id = ? WHERE id = ?")->execute(array($fallbackId, $p['id']));
    }
    echo "\n共 " . count($unmatched) . " 個歸入未分類\n";
}

// 最終確認
echo "\n--- 最終 ---\n";
$remain = $db->query("SELECT COUNT(*) FROM products WHERE category_id IS NULL OR category_id = 0")->fetchColumn();
echo "未分類剩餘: {$remain}\n";

echo '</pre>';
