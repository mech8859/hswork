<?php
/**
 * Google Drive OAuth 授權啟動頁面
 * 只有管理員可以執行，只需要執行一次
 */
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (Auth::user()['role'] !== 'boss') {
    die('Permission denied');
}

require_once __DIR__ . '/../includes/GoogleDrive.php';
$drive = new GoogleDrive();

if ($drive->isAuthorized()) {
    echo '<h2>Google Drive 已授權</h2>';
    echo '<p>已有有效的 Refresh Token，無需重新授權。</p>';
    echo '<p><a href="google_drive_test.php">測試連線</a></p>';
    exit;
}

// 跳轉到 Google 授權頁面
header('Location: ' . $drive->getAuthUrl());
exit;
