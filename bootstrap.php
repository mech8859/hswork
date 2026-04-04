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
require_once __DIR__ . '/helpers.php';

// 啟動 Session
Session::start();

// 載入應用設定
$appConfig = require __DIR__ . '/../config/app.php';
