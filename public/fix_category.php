<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (Session::getUser()['role'] !== 'boss') { die('需要管理員權限'); }
header('Content-Type: text/plain; charset=utf-8');
$db = Database::getInstance();

$rules = array(
    // 政府機關
    'government' => array(
        '改良場','稅捐','調查站','監獄','看守所','戒治所','矯正','少輔院',
        '氣象站','衛生所','清潔隊','水資源','自來水','抽水站','焚化',
        '掩埋場','資源回收','回收站','營區','陸軍','海軍','空軍',
        '步兵','砲兵','裝甲','後勤','聯勤','國軍','憲兵','旅部','營部',
        '立法院','鎮公所','鄉公所','區公所','里辦公','村辦公',
        '法院','地檢','調查局','關務','海關','入出境','移民',
        '工務段','養護','公路局','高速公路','交通部','運輸','航港',
        '農會','漁會','水利會','原子能','核能',
    ),
    // 餐飲
    'food' => array(
        '八方雲集','胖老爹','麥味登','自助餐','鐵板燒','燒臘','米粉湯',
        '便當','滷味','雞排','鹹酥雞','臭豆腐','碗粿','米糕','肉圓',
        '牛肉麵','拉麵','麵店','麵館','水餃','鍋貼','包子','饅頭',
        '豆花','冰店','飲料','手搖','茶飲','奶茶','紅茶','泡沫',
        '咖啡','烘焙','蛋糕','麵包','糕餅',
        '火鍋','涮','鍋物','羊肉爐','薑母鴨',
        '燒烤','燒肉','串燒','居酒屋',
        '壽司','日本料理','韓式','越南','泰式',
        '素食','蔬食','養生','食堂','簡餐',
        '熱炒','快炒','合菜','辦桌','外燴',
        '雞肉飯','排骨飯','控肉','魯肉','焢肉',
        '鵝肉','羊肉','牛排','披薩','漢堡',
        '早餐','早午餐','brunch','三明治',
        '茶坊','茶枋','茶棧','茶藝',
    ),
    // 零售店面
    'shop' => array(
        '巧玲瓏','娃娃機','夾娃娃','選物販賣',
        '玻璃行','工程行','水電行','材料行','五金行',
        '洗衣','乾洗','自助洗',
        '花店','花藝','花卉',
        '寵物','動物','獸醫',
        '大尺碼','服飾店','童裝','內衣',
        '髮型','髮廊','理髮',
        '機車行','汽車百貨','輪胎行',
        '店面','門市',
    ),
    // 休閒
    'leisure' => array(
        '露營','露營區','遊樂場','遊戲場',
        '停車場','洗車場',
    ),
    // 醫療
    'hospital' => array(
        '照護中心','護理之家','安養','長照','日照',
        '牙科','齒科','中醫',
    ),
    // 教育
    'school' => array(
        '國民中學','國民小學','高級中學',
        '音樂教室','才藝','安親','課輔',
    ),
);

$total = 0;
foreach ($rules as $category => $keywords) {
    foreach ($keywords as $kw) {
        $stmt = $db->prepare("UPDATE customers SET category = ? WHERE category = 'residential' AND name LIKE ? AND name NOT LIKE '%住家%' AND name NOT LIKE '%住宅%' AND name NOT LIKE '%自宅%'");
        $stmt->execute(array($category, '%' . $kw . '%'));
        $affected = $stmt->rowCount();
        if ($affected > 0) {
            $total += $affected;
            echo "{$category} ← '{$kw}': {$affected} 筆\n";
        }
    }
}

// 也用地址判斷
$addrRules = array(
    'government' => array('營區','軍營','兵營'),
);
foreach ($addrRules as $category => $keywords) {
    foreach ($keywords as $kw) {
        $stmt = $db->prepare("UPDATE customers SET category = ? WHERE category = 'residential' AND site_address LIKE ? AND name NOT LIKE '%住家%'");
        $stmt->execute(array($category, '%' . $kw . '%'));
        $affected = $stmt->rowCount();
        if ($affected > 0) {
            $total += $affected;
            echo "{$category} ← 地址含'{$kw}': {$affected} 筆\n";
        }
    }
}

echo "\n總計修正: {$total} 筆\n";

// 修正後統計
echo "\n=== 修正後分類統計 ===\n";
$stmt = $db->query("SELECT category, COUNT(*) as cnt FROM customers GROUP BY category ORDER BY cnt DESC");
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "  {$r['category']}: {$r['cnt']}\n";
}
