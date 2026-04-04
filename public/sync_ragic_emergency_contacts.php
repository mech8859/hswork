<?php
/**
 * 從 Ragic 同步緊急聯絡人到 staff_emergency_contacts
 * 只同步緊急聯絡人，不影響其他人員資料
 */
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (Auth::user()['role'] !== 'boss') { die('Permission denied'); }
header('Content-Type: text/html; charset=utf-8');
set_time_limit(300);
echo '<pre style="font-family:monospace;font-size:13px;line-height:1.6">';

$API_KEY = 'dGhmNTRyZk9uMlRUS2c3MjhhQytMZjlZdCtQc1lUMVJHYzVCNlA0dFFVZm1tREk0MFVxU0JibnRmNGV3TElEMA==';
$RAGIC_URL = 'https://ap15.ragic.com/hstcc/ragicforms4/20004';
$SUBTABLE_ID = '_subtable_3001028'; // 緊急聯絡人 subtable

$execute = isset($_GET['execute']) && $_GET['execute'] == '1';
echo $execute ? "=== 執行模式 ===" : "=== 預覽模式 === (加 ?execute=1 執行)";
echo "\n\n";

// 1. 從 Ragic 抓人員資料（含 subtable）
echo "[1] 從 Ragic API 抓取人員資料...\n";
$allRecords = array();
$offset = 0;
$limit = 100;

while (true) {
    $url = $RAGIC_URL . '?api&limit=' . $limit . '&offset=' . $offset . '&subtables=true';
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Authorization: Basic ' . $API_KEY));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        echo "API 錯誤: HTTP {$httpCode}\n";
        break;
    }
    $batch = json_decode($response, true);
    if (!$batch || empty($batch)) break;

    $allRecords = $allRecords + $batch;
    echo "  已抓取 " . count($allRecords) . " 筆 (offset={$offset})\n";
    ob_flush(); flush();

    if (count($batch) < $limit) break;
    $offset += $limit;
}

echo "共 " . count($allRecords) . " 筆人員\n\n";

// 2. 比對 hswork 人員
$db = Database::getInstance();
$stats = array('synced' => 0, 'skipped' => 0, 'no_match' => 0, 'no_contact' => 0);

// 建立 employee_id → user_id 的對照
$userMap = array();
$stmt = $db->query("SELECT id, employee_id, real_name FROM users WHERE is_active = 1");
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $u) {
    if (!empty($u['employee_id'])) {
        $userMap[trim($u['employee_id'])] = $u;
    }
    // 也用姓名做 fallback
    $userMap['name:' . $u['real_name']] = $u;
}

echo "[2] 同步緊急聯絡人...\n\n";

foreach ($allRecords as $ragicId => $rec) {
    $name = '';
    foreach (array('姓名', 'name') as $f) {
        if (!empty($rec[$f])) { $name = trim($rec[$f]); break; }
    }
    $empId = isset($rec['employee_id']) ? trim($rec['employee_id']) : '';

    if (!$name) continue;

    // 找到對應的 hswork 使用者
    $matchedUser = null;
    if ($empId && isset($userMap[$empId])) {
        $matchedUser = $userMap[$empId];
    } elseif (isset($userMap['name:' . $name])) {
        $matchedUser = $userMap['name:' . $name];
    }

    if (!$matchedUser) {
        echo "  [NO MATCH] {$name} (empId={$empId})\n";
        $stats['no_match']++;
        continue;
    }

    $userId = (int)$matchedUser['id'];

    // 解析緊急聯絡人 subtable
    $contacts = array();
    if (isset($rec[$SUBTABLE_ID]) && is_array($rec[$SUBTABLE_ID])) {
        foreach ($rec[$SUBTABLE_ID] as $subId => $sub) {
            $cName = isset($sub['姓名']) ? trim($sub['姓名']) : '';
            if (!$cName) continue;
            $contacts[] = array(
                'contact_name' => $cName,
                'relationship' => isset($sub['關係']) ? trim($sub['關係']) : '',
                'mobile' => isset($sub['手機']) ? trim($sub['手機']) : '',
                'home_phone' => isset($sub['住家電話']) ? trim($sub['住家電話']) : '',
                'work_phone' => isset($sub['公司電話']) ? trim($sub['公司電話']) : '',
            );
        }
    }

    if (empty($contacts)) {
        $stats['no_contact']++;
        continue;
    }

    echo "  [{$name}] user#{$userId} → ";
    foreach ($contacts as $c) {
        echo "{$c['contact_name']}({$c['relationship']}) {$c['mobile']} ";
    }
    echo "\n";

    if ($execute) {
        // 先清除舊的緊急聯絡人
        $db->prepare("DELETE FROM staff_emergency_contacts WHERE user_id = ?")->execute(array($userId));

        // 寫入新的
        $insStmt = $db->prepare("INSERT INTO staff_emergency_contacts (user_id, contact_name, relationship, mobile, home_phone, work_phone, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
        foreach ($contacts as $c) {
            $insStmt->execute(array(
                $userId,
                $c['contact_name'],
                $c['relationship'] ?: null,
                $c['mobile'] ?: null,
                $c['home_phone'] ?: null,
                $c['work_phone'] ?: null,
            ));
        }
    }
    $stats['synced']++;
}

echo "\n=== 完成 ===\n";
echo "已同步: {$stats['synced']} 人\n";
echo "無緊急聯絡人: {$stats['no_contact']} 人\n";
echo "未匹配: {$stats['no_match']} 人\n";
echo "跳過: {$stats['skipped']} 人\n";

if (!$execute) {
    echo "\n→ 確認無誤後加 ?execute=1 執行寫入\n";
}
echo '</pre>';
