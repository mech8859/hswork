<?php
/**
 * 應用程式啟動載入
 * 所有頁面的進入點都應 require 此檔案
 */

// 時區設定
date_default_timezone_set('Asia/Taipei');

// 錯誤處理 (正式環境請改為 0)
ini_set('display_errors', '0');
error_reporting(E_ALL);
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../logs/error.log');

// 載入核心類別
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Session.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/AuditLog.php';
require_once __DIR__ . '/helpers.php';

// 啟動 Session
Session::start();

// 載入應用設定
$appConfig = require __DIR__ . '/../config/app.php';

// 自動更新用戶活動狀態（每次頁面載入）
if (Session::getUser() && php_sapi_name() !== 'cli') {
    AuditLog::updateActivity();
    // 每次載入重新計算權限（改權限不需重新登入）
    Auth::reloadPermissions();
}

// 每日自動備份檢查（boss 登入時觸發，背景執行）
if (Session::getUser() && php_sapi_name() !== 'cli') {
    $user = Session::getUser();
    if (isset($user['role']) && $user['role'] === 'boss') {
        $backupFlag = __DIR__ . '/../data/backups/last_auto_backup.txt';
        $lastBackup = file_exists($backupFlag) ? trim(file_get_contents($backupFlag)) : '';
        if ($lastBackup !== date('Y-m-d')) {
            // 標記今天已觸發（避免重複）
            @file_put_contents($backupFlag, date('Y-m-d'));
            $baseUrl = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
            // 背景觸發 DB 備份（不阻塞頁面載入）
            $ch = curl_init($baseUrl . '/cron_backup.php?key=hswork_backup_2026_secret');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 1);
            curl_setopt($ch, CURLOPT_NOSIGNAL, 1);
            @curl_exec($ch);
            curl_close($ch);
            // 背景觸發客戶+案件 CSV 匯出到 Google Drive
            $ch2 = curl_init($baseUrl . '/google_drive_export_data.php?type=all&key=hswork_backup_2026_secret');
            curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch2, CURLOPT_TIMEOUT, 1);
            curl_setopt($ch2, CURLOPT_NOSIGNAL, 1);
            @curl_exec($ch2);
            curl_close($ch2);
        }
    }
}

// 自動修復卡住簽核（每 30 分鐘最多一次，背景執行不阻塞）
if (Session::getUser() && php_sapi_name() !== 'cli') {
    $fixFlag = __DIR__ . '/../data/backups/last_approval_fix.txt';
    $lastFix = file_exists($fixFlag) ? (int)trim(@file_get_contents($fixFlag)) : 0;
    if (time() - $lastFix > 1800) { // 30 分鐘
        @file_put_contents($fixFlag, time());
        $baseUrl = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
        $ch = curl_init($baseUrl . '/cron_fix_stuck_approvals.php?key=hswork_approval_fix_2026');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 1);
        curl_setopt($ch, CURLOPT_NOSIGNAL, 1);
        @curl_exec($ch);
        curl_close($ch);
    }
}

// 確保沒有殘留的未關閉事務
try {
    $__db = Database::getInstance();
    if ($__db->inTransaction()) { $__db->rollBack(); }
} catch (Exception $__e) {}
