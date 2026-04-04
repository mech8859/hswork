<?php
require __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (!Auth::hasPermission('admin')) { die('需要管理員權限'); }
header('Content-Type: text/plain; charset=utf-8');
set_time_limit(120);
$db = Database::getInstance();

// 楊雨倫 user_id
$stmt = $db->prepare("SELECT id FROM users WHERE employee_id = '00651'");
$stmt->execute();
$userId = $stmt->fetchColumn();
if (!$userId) { die("找不到員工 00651 楊雨倫\n"); }
echo "楊雨倫 User ID: {$userId}\n\n";

// Ragic 圖片列表
$photos = array(
    array('filename' => 'jdvmW8CwI9@楊雨倫-身分證正面.png', 'doc_type' => 'id_front', 'label' => '身分證-正面'),
    array('filename' => 'cCnUMLfhvM@楊雨倫-身分證背面.png', 'doc_type' => 'id_back', 'label' => '身分證-反面'),
    array('filename' => 'p38BRyFNr2@楊雨倫-大頭貼.jpg', 'doc_type' => 'photo', 'label' => '大頭貼'),
);

// Ragic 認證
$ragicEmail = 'hscctvttv@gmail.com';
$ragicPass = 'hstc88588859';
$authHeader = 'Authorization: Basic ' . base64_encode($ragicEmail . ':' . $ragicPass);

// 先取 cookie
$ch = curl_init('https://ap15.ragic.com/hstcc/ragicforms4/20004');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, array($authHeader, 'User-Agent: Mozilla/5.0'));
curl_setopt($ch, CURLOPT_HEADER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
$resp = curl_exec($ch);
// 提取 cookie
$cookies = array();
preg_match_all('/Set-Cookie:\s*([^;\r\n]+)/i', $resp, $cookieMatches);
foreach ($cookieMatches[1] as $c) { $cookies[] = $c; }
$cookieStr = implode('; ', $cookies);
curl_close($ch);
echo "Cookie 取得: " . (strlen($cookieStr) > 0 ? '成功' : '失敗') . "\n\n";

$uploadDir = __DIR__ . '/uploads/staff/';
if (!is_dir($uploadDir)) { mkdir($uploadDir, 0755, true); }

foreach ($photos as $photo) {
    $ragicFile = $photo['filename'];
    $docType = $photo['doc_type'];
    $label = $photo['label'];

    echo "--- {$label} ---\n";
    $url = 'https://ap15.ragic.com/sims/file.jsp?a=hstcc&f=' . rawurlencode($ragicFile);
    echo "  URL: {$url}\n";

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array($authHeader, 'User-Agent: Mozilla/5.0', 'Cookie: ' . $cookieStr));
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    $imgData = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);

    echo "  HTTP: {$httpCode}, Content-Type: {$contentType}, Size: " . strlen($imgData) . " bytes\n";

    if ($httpCode != 200 || strlen($imgData) < 100) {
        echo "  [失敗] 下載失敗\n\n";
        continue;
    }

    // 判斷副檔名
    $ext = 'jpg';
    if (strpos($contentType, 'png') !== false) $ext = 'png';
    elseif (strpos($contentType, 'gif') !== false) $ext = 'gif';
    elseif (strpos($contentType, 'webp') !== false) $ext = 'webp';

    // 從原始檔名取
    $origName = '';
    if (strpos($ragicFile, '@') !== false) {
        $origName = substr($ragicFile, strpos($ragicFile, '@') + 1);
    }
    $origExt = pathinfo($origName, PATHINFO_EXTENSION);
    if ($origExt) $ext = strtolower($origExt);

    $saveName = 'staff_' . $userId . '_' . $docType . '_' . date('Ymd_His') . '.' . $ext;
    $savePath = $uploadDir . $saveName;
    $dbPath = '/uploads/staff/' . $saveName;

    file_put_contents($savePath, $imgData);
    echo "  儲存: {$savePath}\n";

    // 寫入 DB (先刪除同類型舊記錄)
    $db->prepare("DELETE FROM staff_documents WHERE user_id = ? AND doc_type = ?")->execute(array($userId, $docType));
    $db->prepare("INSERT INTO staff_documents (user_id, doc_type, doc_label, file_path, file_name, uploaded_at) VALUES (?, ?, ?, ?, ?, NOW())")
       ->execute(array($userId, $docType, $label, $dbPath, $saveName));
    echo "  [成功] 已寫入 staff_documents\n";

    // 大頭貼額外更新 users.avatar
    if ($docType === 'photo') {
        $db->prepare("UPDATE users SET avatar = ? WHERE id = ?")->execute(array($dbPath, $userId));
        echo "  → 已更新 users.avatar\n";
    }
    echo "\n";
}

echo "完成！共處理 " . count($photos) . " 張照片\n";
echo "可到 https://hswork.com.tw/staff.php?action=view&id={$userId} 查看\n";
