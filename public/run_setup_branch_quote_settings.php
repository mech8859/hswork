<?php
/**
 * 一次性腳本：報價單支援分公司
 *   1) 新建「竹南分公司」到 branches（若不存在）
 *   2) 把現有禾順／理創的設定複製為「預設分公司」版本
 *   3) seed 各分公司的公司抬頭（地址/電話/傳真留空，之後在設定頁填）
 *
 * 規則：
 *   禾順 (無前綴)  → branch_id 1/2/3/竹南 使用 quote_xxx_b{id}
 *   理創 (lc_ 前綴) → branch_id 4/5 使用 lc_quote_xxx_b{id}
 *
 * 用法：/run_setup_branch_quote_settings.php           （預覽）
 *       /run_setup_branch_quote_settings.php?execute=1 （實際執行）
 */
require __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (!Auth::hasPermission('admin') && Auth::user()['role'] !== 'boss') {
    die('需要管理員權限');
}
header('Content-Type: text/plain; charset=utf-8');
$db = Database::getInstance();
$execute = isset($_GET['execute']) && $_GET['execute'] == '1';

echo $execute ? "=== 執行模式 ===\n\n" : "=== 預覽模式 === (加 ?execute=1 執行)\n\n";

// ================== 1) 確保竹南分公司存在 ==================
echo "--- 1) 竹南分公司 ---\n";
$zs = $db->prepare("SELECT id, name FROM branches WHERE name = ? LIMIT 1");
$zs->execute(array('竹南分公司'));
$zsRow = $zs->fetch(PDO::FETCH_ASSOC);
if ($zsRow) {
    $zhunanId = (int)$zsRow['id'];
    echo "  [已存在] 竹南分公司 id={$zhunanId}\n";
} else {
    echo "  [新增] 竹南分公司（is_active=1, code=ZN）\n";
    if ($execute) {
        $db->prepare("INSERT INTO branches (name, code, is_active, created_at) VALUES (?, 'ZN', 1, NOW())")
           ->execute(array('竹南分公司'));
        $zhunanId = (int)$db->lastInsertId();
        echo "    → 完成，新 id={$zhunanId}\n";
    } else {
        $zhunanId = 0; // 預覽模式沒 id
        echo "    (預覽：尚未實際建立，id 待定)\n";
    }
}
echo "\n";

// ================== 2) 取現有禾順/理創設定當基底 ==================
$stmt = $db->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_group = 'quotation'");
$existing = array();
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $existing[$r['setting_key']] = $r['setting_value'];
}

// ================== 3) 定義要 seed 的分公司資料 ==================
// 禾順分公司（3 + 1 竹南）
$hershunBranches = array(
    1 => array('title' => '禾順監視數位科技-台中分公司', 'is_default' => true),  // 台中＝潭子
    2 => array('title' => '禾順監視數位科技-清水分公司', 'is_default' => false),
    3 => array('title' => '禾順監視數位科技-員林分公司', 'is_default' => false),
);
if ($zhunanId > 0) {
    $hershunBranches[$zhunanId] = array('title' => '禾順監視數位科技-竹南分公司', 'is_default' => false);
}

// 理創分公司（東區電子鎖/清水電子鎖）
$lichuangBranches = array(
    4 => array('title' => '政達企業有限公司-東區電子鎖'),
    5 => array('title' => '政達企業有限公司-清水電子鎖'),
);

// ================== 4) seed 禾順 ==================
echo "--- 2) 禾順各分公司設定 ---\n";
$toSave = array();
$seedBranchKeys = function($prefix, $branchId, $defaultTitle, $isDefault) use (&$toSave, $existing) {
    $fields = array(
        'quote_company_title'   => $defaultTitle,
        'quote_contact_address' => '',
        'quote_contact_phone'   => '',
        'quote_contact_fax'     => '',
    );
    foreach ($fields as $baseKey => $seedVal) {
        $targetKey = $prefix . $baseKey . '_b' . $branchId;
        if (isset($existing[$targetKey])) {
            echo "    [略] {$targetKey} 已存在（值：{$existing[$targetKey]}）\n";
            continue;
        }
        // 台中（預設分公司）：複製舊值當內容
        if ($isDefault) {
            $legacyKey = $prefix . $baseKey;
            $val = isset($existing[$legacyKey]) ? $existing[$legacyKey] : $seedVal;
            if ($baseKey === 'quote_company_title' && $val === '') $val = $defaultTitle;
        } else {
            // 非預設分公司：抬頭 seed，其他欄位留空
            $val = ($baseKey === 'quote_company_title') ? $defaultTitle : '';
        }
        echo "    [新增] {$targetKey} = " . ($val === '' ? '(空)' : $val) . "\n";
        $toSave[$targetKey] = $val;
    }
};

foreach ($hershunBranches as $bid => $info) {
    echo "  分公司 id={$bid}：{$info['title']}\n";
    $seedBranchKeys('', $bid, $info['title'], $info['is_default']);
}

echo "\n--- 3) 理創各分公司設定 ---\n";
foreach ($lichuangBranches as $bid => $info) {
    echo "  分公司 id={$bid}：{$info['title']}\n";
    $seedBranchKeys('lc_', $bid, $info['title'], false);
}

// ================== 5) 寫入 ==================
echo "\n--- 4) 寫入 ---\n";
if ($execute && !empty($toSave)) {
    $ins = $db->prepare('INSERT INTO system_settings (setting_key, setting_value, setting_group) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
    $c = 0;
    foreach ($toSave as $k => $v) {
        $ins->execute(array($k, $v, 'quotation'));
        $c++;
    }
    echo "  ✓ 已寫入 {$c} 筆\n";
} elseif (empty($toSave)) {
    echo "  (無資料要寫入，全部已存在)\n";
} else {
    echo "  (預覽模式，未寫入 " . count($toSave) . " 筆)\n";
}

echo "\n完成。";
echo $execute
    ? "\n→ 下一步請告訴 Claude 繼續 Step 2（改 print.php）\n"
    : "\n(預覽模式，無變更。加 ?execute=1 實際執行)\n";
