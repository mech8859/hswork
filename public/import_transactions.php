<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (!Auth::hasPermission('admin') && !in_array(Auth::user()['role'], array('boss','manager'))) {
    die('需要管理員權限');
}

$db = Database::getInstance();

// Check if already imported
$count = $db->query("SELECT COUNT(*) FROM transactions")->fetchColumn();
if ($count > 0) {
    echo '<h1>已有 ' . $count . ' 筆資料，跳過匯入</h1>';
    echo '<p><a href="/transactions.php">前往非廠商交易管理</a></p>';
    exit;
}

$data = array(
    array('rn' => 'H1-20260204-001', 'rd' => '2026-03-19', 'tt' => 'partner', 'cat' => 'purchase', 'cn' => '埔里-劉建鑫', 'it' => array(
        array('2025-09-30', '9/30 自取ER605 *1、EAP653 *1、TL-SG2428P *1', '', 9570, '', 1, ''),
        array('2025-09-08', '9/8 YS C6 黑色*2+YS C6 灰色*2', '', 12320, '', 1, ''),
        array('2026-01-05', '監視系統相關', '', 21080, '', 0, ''),
        array('2025-10-29', '10/29 ER605*1+EAP650-OUTDOOR*1', '', 5170, '', 1, ''),
        array('2025-11-18', '11/18 自取 HS-XVR85108HS-4KL-I3 *1', '', 4500, '', 1, ''),
        array('2025-12-17', '12/17 HA-8210 斌仕-維修費*1', '', 2050, '', 0, ''),
        array('2025-12-19', '12/19 自取HDD 2TB*1', '', 2300, '', 0, ''),
        array('2025-09-24', '9/24 自取 HS-HF81231CMN-A-3.6 *10、HS-HF81231CMN-A-2.8 *5', '', 13500, '', 1, ''),
        array('2025-12-10', '12/10 自取HS-HF82501TUN-Z-A-27135 *1', '', 2520, '', 0, ''),
        array('2025-12-16', '12/16 4杰送去給鑫哥 HS-XVR85108HS-4KL-I3*1', '', 4500, '', 1, ''),
        array('2025-12-30', '12/30 自取HS-HD81231TLMQN-A-2.8 *2+HS-HD81231TLMQN-A-3.6 *2', '', 600, '', 0, ''),
        array('2025-08-18', '8/18 自取HS-XVR85104HS-4KL-I3 *1、HS-NVR85208-EI *1', '', 8400, '', 1, ''),
    )),
    array('rn' => 'H1-20260204-002', 'rd' => '2026-03-16', 'tt' => 'partner', 'cat' => 'purchase', 'cn' => '張富全', 'it' => array(
        array('2026-03-16', '3/16 自取TL-SG2218P*2+HS-HF82501TUN-Z-A-27135*2', '', 30360, '', 0, ''),
    )),
    array('rn' => 'H1-20260205-001', 'rd' => '2026-02-06', 'tt' => 'employee', 'cat' => 'purchase', 'cn' => '蕭坤偉', 'it' => array(
        array('2026-02-05', '員工自購', '', 0, '', 1, ''),
    )),
    array('rn' => 'H1-20260206-001', 'rd' => '2026-02-23', 'tt' => 'employee', 'cat' => 'purchase', 'cn' => '謝浩維', 'it' => array(
        array('2026-02-22', '員工自購', '', 0, '', 1, ''),
    )),
    array('rn' => 'H1-20260206-002', 'rd' => '2026-03-11', 'tt' => 'employee', 'cat' => 'purchase', 'cn' => '莊竣珵', 'it' => array(
        array('2026-02-06', '114/02/06 員工自購4MP全彩半球+YS C6全彩半球', '', 6364, '', 0, '扣薪水(分兩期3/10、4/10)'),
    )),
    array('rn' => 'H1-20260207-001', 'rd' => '2026-02-26', 'tt' => 'employee', 'cat' => 'purchase', 'cn' => '陳信維', 'it' => array(
        array('2026-01-25', '115/01/25 員工自購 AX1800 戶外型無線基地台 $4590', '', 2000, '扣薪水(分兩期2/10、3/10)', 1, '2026/01/27現金支付590'),
        array('2026-02-13', '115/02/13員工自購 Zyxel 資安路由器+8埠交換器', '', 6070, '', 1, '2026/02/26匯款6070'),
    )),
    array('rn' => 'H1-20260211-001', 'rd' => '2026-02-11', 'tt' => 'employee', 'cat' => 'purchase', 'cn' => '江明軒', 'it' => array(
        array('2026-02-10', 'M3*1+E27*1+P1*1', '', 0, '', 1, '115/02/11 現金3819'),
    )),
    array('rn' => 'H1-20260211-002', 'rd' => '2026-02-23', 'tt' => 'employee', 'cat' => 'purchase', 'cn' => '黃星晴', 'it' => array(
        array('2026-01-05', '保險箱*2', '', 52500, '', 0, '3月底拿現金'),
        array('2026-02-14', 'VIGI 4路PoE+網路監控主機+1TB硬碟+攝影機', '', 0, '', 0, '主機跟硬碟要換,等更換後再算金額'),
    )),
    array('rn' => 'H1-20260223-001', 'rd' => '2026-02-23', 'tt' => 'partner', 'cat' => 'purchase', 'cn' => '劉享和', 'it' => array(
        array('2026-01-09', '禾順牌 2MP攝影機*4+4路主機+1TB硬碟', '', 8640, '', 0, ''),
        array('2026-02-08', 'HS-XVR85108HS-4KL-I3 *1', '', 4500, '', 0, ''),
        array('2025-12-14', '禾順 4K 4路主機+1TB 紫標硬碟+2MP攝影機*4', '', 4200, '', 0, '原價10620;已收6420(1214收3000、0109收3420)'),
    )),
    array('rn' => 'H1-20260225-001', 'rd' => '2026-02-25', 'tt' => 'partner', 'cat' => 'purchase', 'cn' => '劉建鑫', 'it' => array(
        array('2026-02-25', '2/25 HS-HD82501TLMQN-A-2.8 *2', '', 0, '', 1, ''),
    )),
    array('rn' => 'H1-20260226-001', 'rd' => '2026-02-26', 'tt' => 'partner', 'cat' => 'purchase', 'cn' => '黑齒', 'it' => array(
        array('2026-02-25', '115/02/25 自取 C6穿透式水晶頭*3包+護套*2包', '', 2915, '', 0, ''),
        array('2025-11-11', '114/11/11 自取 1.25*2C*100+c6水晶頭*2+護套*2', '', 3602, '', 0, ''),
        array('2025-08-12', '114/8/12 自取 兩包水晶頭兩包護套', '', 2100, '', 0, ''),
    )),
    array('rn' => 'H1-20260309-001', 'rd' => '2026-03-09', 'tt' => 'partner', 'cat' => 'purchase', 'cn' => '劉建鑫', 'it' => array(
        array('2026-03-09', '3/9 自取S-2TB *1', 'S-2TB', 2541, '', 1, ''),
    )),
    array('rn' => 'H1-20260311-001', 'rd' => '2026-03-11', 'tt' => 'employee', 'cat' => 'purchase', 'cn' => '游俊豪', 'it' => array(
        array('2026-03-10', 'AX1800基地台*2', '', 3990, '115/04/10', 0, '4/10扣薪水'),
    )),
    array('rn' => 'H1-20260319-001', 'rd' => '2026-03-19', 'tt' => 'partner', 'cat' => 'purchase', 'cn' => '劉建鑫', 'it' => array(
        array('2026-01-10', 'HDD 4TB*2', 'HDD 4TB', 6838, '', 0, ''),
    )),
    array('rn' => 'H1-20260319-002', 'rd' => '2026-03-19', 'tt' => 'partner', 'cat' => 'purchase', 'cn' => '潭子－劉建鑫', 'it' => array(
        array('2026-03-19', '16TB硬碟', '', 14300, '3/20、3/21匯款$14300', 1, '3/19匯款14300'),
    )),
);

$db->beginTransaction();
$imported = 0;
$itemCount = 0;

try {
    $stmtTx = $db->prepare("INSERT INTO transactions (register_no, register_date, target_type, category, contact_name, total_unpaid, created_by) VALUES (?, ?, ?, ?, ?, 0, NULL)");
    $stmtItem = $db->prepare("INSERT INTO transaction_items (transaction_id, trade_date, description, product, amount, due_date, is_settled, note) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmtCalc = $db->prepare("UPDATE transactions SET total_unpaid = (SELECT COALESCE(SUM(amount),0) FROM transaction_items WHERE transaction_id = ? AND is_settled = 0) WHERE id = ?");

    foreach ($data as $r) {
        $stmtTx->execute(array($r['rn'], $r['rd'], $r['tt'], $r['cat'], $r['cn']));
        $txId = $db->lastInsertId();
        $imported++;

        foreach ($r['it'] as $item) {
            $stmtItem->execute(array(
                $txId,
                !empty($item[0]) ? $item[0] : null,
                $item[1],
                $item[2],
                $item[3],
                $item[4],
                $item[5],
                $item[6]
            ));
            $itemCount++;
        }

        $stmtCalc->execute(array($txId, $txId));
    }

    $db->commit();
    echo '<h1 style="color:green">匯入完成</h1>';
    echo '<p>匯入 <b>' . $imported . '</b> 筆交易、<b>' . $itemCount . '</b> 筆明細</p>';
} catch (Exception $e) {
    $db->rollBack();
    echo '<h1 style="color:red">匯入失敗</h1>';
    echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
}

echo '<p><a href="/transactions.php">前往非廠商交易管理</a></p>';
