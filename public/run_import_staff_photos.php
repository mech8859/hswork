<?php
require __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (!Auth::hasPermission('admin')) { die('需要管理員權限'); }
header('Content-Type: text/html; charset=utf-8');
set_time_limit(300);
ini_set('memory_limit', '512M');
echo '<pre>';

$db = Database::getInstance();
$startFrom = isset($_GET['from']) ? (int)$_GET['from'] : 0;
$batchSize = isset($_GET['batch']) ? (int)$_GET['batch'] : 10;

echo "=== 照片+證書匯入 === (從第 {$startFrom} 筆, 每批 {$batchSize} 筆)\n\n";

$jsonPath = __DIR__ . '/../ragic_staff_all.json';
$records = json_decode(file_get_contents($jsonPath), true);
echo "共 " . count($records) . " 筆\n\n";

// 確保 panduit_cert doc_type 存在
try { $db->exec("INSERT IGNORE INTO staff_doc_types (type_key, type_label, sort_order) VALUES ('panduit_cert', 'PANDUIT證書', 32)"); } catch (PDOException $e) {}

// Ragic auth + cookie
$ragicAuth = 'Authorization: Basic ' . base64_encode('hscctvttv@gmail.com:hstc88588859');
$ch = curl_init('https://ap15.ragic.com/hstcc/ragicforms4/20004');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, array($ragicAuth, 'User-Agent: Mozilla/5.0'));
curl_setopt($ch, CURLOPT_HEADER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
$resp = curl_exec($ch);
preg_match_all('/Set-Cookie:\s*([^;\r\n]+)/i', $resp, $cm);
$cookieStr = implode('; ', $cm[1]);
curl_close($ch);
echo "Cookie: " . (strlen($cookieStr) > 0 ? 'OK' : 'FAIL') . "\n\n";

$uploadDir = __DIR__ . '/uploads/staff/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

// JSON key => [DB doc_type, 顯示名稱]
$photoMap = array(
    'id_front'       => array('id_front',          '身分證-正面'),
    'id_back'        => array('id_back',            '身分證-反面'),
    'license_front'  => array('license_front',      '汽車駕照-正面'),
    'license_back'   => array('license_back',       '汽車駕照-反面'),
    'avatar'         => array('photo',              '大頭貼'),
    'safety_officer' => array('safety_officer',     '甲種營造業職業安全衛生業務主管'),
    'safety_cert'    => array('safety_education',   '一般安全衛生教育結業證書-營造業'),
    'aerial_worker'  => array('aerial_worker',      '高空作業車操作人員'),
    'first_aid'      => array('first_aid',          '急救人員'),
    'telecom_c'      => array('telecom_c',          '通訊技術士-丙'),
    'telecom_b'      => array('telecom_b',          '通訊技術士-乙'),
    'network_c'      => array('network_c',          '網路架設-丙'),
    'network_b'      => array('network_b',          '網路架設-乙'),
    'wiring_c'       => array('indoor_c',           '室內配線-丙'),
    'wiring_b'       => array('indoor_b',           '室內配線-乙'),
    'cad_c'          => array('cad_c',              '繪圖設計-丙'),
    'cad_b'          => array('cad_b',              '繪圖設計-乙'),
    'web_c'          => array('web_c',              '網頁設計-丙'),
    'web_b'          => array('web_b',              '網頁設計-乙'),
    'unifi'          => array('unifi_cert',         'UNIFI證書'),
    'fluke'          => array('fluke_cert',         'FLUKE證書'),
    'panduit'        => array('panduit_cert',       'PANDUIT證書'),
);

$photoCount = 0;
$processed = 0;

foreach ($records as $idx => $r) {
    $num = $idx + 1;
    if ($num <= $startFrom) continue;
    if ($processed >= $batchSize) break;
    $processed++;

    $empId = trim($r['employee_id']);
    $name = trim($r['name']);
    if (empty($name)) continue;

    $chk = $db->prepare("SELECT id FROM users WHERE employee_id = ? AND employee_id != ''");
    $chk->execute(array($empId));
    $userId = $chk->fetchColumn();
    if (!$userId) { echo "[{$num}] {$empId} {$name} — 找不到帳號\n\n"; continue; }

    echo "[{$num}] {$empId} {$name} (ID:{$userId})\n";

    $hasNew = false;
    foreach ($photoMap as $jsonKey => $info) {
        $dbDocType = $info[0];
        $label = $info[1];
        $fileVal = isset($r[$jsonKey]) ? $r[$jsonKey] : '';
        if (empty($fileVal) || strpos($fileVal, '@') === false) continue;

        // 檢查是否已有相同檔案
        $existChk = $db->prepare("SELECT COUNT(*) FROM staff_documents WHERE user_id = ? AND doc_type = ?");
        $existChk->execute(array($userId, $dbDocType));
        if ($existChk->fetchColumn() > 0) continue;

        $imgUrl = 'https://ap15.ragic.com/sims/file.jsp?a=hstcc&f=' . rawurlencode($fileVal);
        $ich = curl_init($imgUrl);
        curl_setopt($ich, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ich, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ich, CURLOPT_HTTPHEADER, array($ragicAuth, 'User-Agent: Mozilla/5.0', 'Cookie: ' . $cookieStr));
        curl_setopt($ich, CURLOPT_TIMEOUT, 15);
        $imgData = curl_exec($ich);
        $imgHttp = curl_getinfo($ich, CURLINFO_HTTP_CODE);
        $imgCt = curl_getinfo($ich, CURLINFO_CONTENT_TYPE);
        curl_close($ich);

        if ($imgHttp == 200 && strlen($imgData) > 100) {
            $ext = 'jpg';
            if (strpos($imgCt, 'png') !== false) $ext = 'png';
            $origExt = pathinfo($fileVal, PATHINFO_EXTENSION);
            if ($origExt) $ext = strtolower($origExt);

            $saveName = 'staff_' . $userId . '_' . $dbDocType . '_' . date('Ymd_His') . '.' . $ext;
            file_put_contents($uploadDir . $saveName, $imgData);
            $dbPath = '/uploads/staff/' . $saveName;

            $db->prepare("DELETE FROM staff_documents WHERE user_id = ? AND doc_type = ?")->execute(array($userId, $dbDocType));
            $db->prepare("INSERT INTO staff_documents (user_id, doc_type, doc_label, file_path, file_name, uploaded_at) VALUES (?, ?, ?, ?, ?, NOW())")
               ->execute(array($userId, $dbDocType, $label, $dbPath, $saveName));

            if ($dbDocType === 'photo') {
                $db->prepare("UPDATE users SET avatar = ? WHERE id = ?")->execute(array($dbPath, $userId));
            }
            echo "  📷 {$label} (" . round(strlen($imgData)/1024) . "KB)\n";
            $photoCount++;
            $hasNew = true;
        }
    }
    if (!$hasNew) echo "  (無新照片)\n";
    echo "\n";
    ob_flush(); flush();
}

$nextFrom = $startFrom + $batchSize;
echo "==============================\n";
echo "本批完成！照片: {$photoCount} 張\n\n";
if ($nextFrom < count($records)) {
    echo "<a href='/run_import_staff_photos.php?from={$nextFrom}&batch={$batchSize}'>→ 繼續下一批 (from={$nextFrom})</a>\n";
} else {
    echo "全部完成！\n";
}
echo '</pre>';
