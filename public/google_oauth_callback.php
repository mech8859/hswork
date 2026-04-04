<?php
/**
 * Google OAuth 回調頁面
 * Google 授權完成後會跳轉到這裡
 */
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (Auth::user()['role'] !== 'boss') {
    die('Permission denied');
}

header('Content-Type: text/html; charset=utf-8');

if (isset($_GET['error'])) {
    echo '<h2>授權失敗</h2>';
    echo '<p>錯誤: ' . htmlspecialchars($_GET['error']) . '</p>';
    exit;
}

if (empty($_GET['code'])) {
    echo '<h2>錯誤</h2>';
    echo '<p>缺少授權碼</p>';
    exit;
}

require_once __DIR__ . '/../includes/GoogleDrive.php';
$drive = new GoogleDrive();

try {
    $tokenData = $drive->exchangeCodeForToken($_GET['code']);
    echo '<h2>Google Drive 授權成功！</h2>';
    echo '<p>Refresh Token 已儲存，系統現在可以存取 Google Drive。</p>';
    echo '<p><a href="google_drive_test.php">測試連線</a></p>';
} catch (Exception $e) {
    echo '<h2>授權失敗</h2>';
    echo '<p>錯誤: ' . htmlspecialchars($e->getMessage()) . '</p>';
}
