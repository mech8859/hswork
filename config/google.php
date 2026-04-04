<?php
/**
 * Google OAuth / Drive API 設定
 */
return array(
    'client_id'     => '984154792513-2939r6bosfv4chhtd3q70vvavtl43fmg.apps.googleusercontent.com',
    'client_secret' => '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',
    'redirect_uri'  => 'https://hswork.com.tw/google_oauth_callback.php',
    'scopes'        => array('https://www.googleapis.com/auth/drive.file'),
    // 授權後取得的 token 存放於此檔案
    'token_file'    => __DIR__ . '/../data/google_token.json',
);
