<?php
/**
 * 應用程式設定
 */
return [
    'name'     => '弱電工程排程系統',
    'version'  => '1.0.0',
    'timezone' => 'Asia/Taipei',
    'locale'   => 'zh_TW',

    // 網站根路徑 (智邦虛擬主機設定)
    'base_url' => getenv('APP_URL') ?: 'https://hswork.com.tw',
    'base_path' => __DIR__ . '/..',

    // Session 設定
    'session' => [
        'name'     => 'HSWORK_SID',
        'lifetime' => 28800, // 8小時
    ],

    // 上傳設定
    'upload' => [
        'max_size'       => 10 * 1024 * 1024, // 10MB
        'allowed_types'  => ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'xls', 'xlsx'],
        'cases_path'     => '/uploads/cases/',
    ],

    // 角色定義
    'roles' => [
        'boss'            => '老闆',
        'sales_manager'   => '業務主管',
        'eng_manager'     => '工程主管',
        'eng_deputy'      => '工程副主管',
        'sales'           => '業務',
        'sales_assistant' => '業務助理',
        'admin_staff'     => '行政人員',
    ],

    // 角色權限
    'permissions' => [
        'boss'            => ['all'],
        'sales_manager'   => ['cases.manage', 'staff.view', 'schedule.view', 'reports.view'],
        'eng_manager'     => ['cases.view', 'staff.manage', 'schedule.manage', 'reports.view'],
        'eng_deputy'      => ['cases.view', 'staff.view', 'schedule.manage'],
        'sales'           => ['cases.own', 'schedule.view'],
        'sales_assistant' => ['cases.assist', 'schedule.view'],
        'admin_staff'     => ['cases.view', 'staff.view', 'schedule.view', 'payments.manage'],
    ],

    // 證照到期預警天數
    'cert_warning_days' => 30,

    // API 設定
    'api' => [
        'rate_limit'     => 1000,  // 每小時
        'token_lifetime' => 86400, // 24小時
    ],
];
