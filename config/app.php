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

    // 角色定義（靜態預設，優先使用 system_roles 資料表）
    'roles' => [
        'boss'              => '系統管理者',
        'vice_president'    => '副總',
        'manager'           => '分公司／部門管理者',
        'assistant_manager' => '協理',
        'sales_manager'     => '業務主管',
        'eng_manager'       => '工程主管',
        'eng_deputy'        => '工程副主管',
        'engineer'          => '工程人員',
        'sales'             => '業務',
        'sales_assistant'   => '業務助理',
        'admin_staff'       => '行政人員',
        'accountant'        => '會計人員',
        'warehouse'         => '倉管',
        'purchaser'         => '採購',
        'hq'                => '總公司',
    ],

    // 角色權限
    'permissions' => [
        'boss'              => ['all', 'cases.delete', 'schedule.delete', 'repairs.delete', 'quotations.delete', 'customers.delete', 'leaves.delete', 'inter_branch.delete', 'products.delete', 'inventory.delete', 'finance.delete', 'accounting.manage', 'overtime.manage'],
        'vice_president'    => ['all', 'cases.delete', 'schedule.delete', 'repairs.delete', 'quotations.delete', 'customers.delete', 'leaves.delete', 'inter_branch.delete', 'products.delete', 'inventory.delete', 'finance.delete', 'accounting.manage', 'overtime.manage'],
        'manager'           => ['all', 'overtime.manage'],
        'assistant_manager' => ['reviews.view', 'tech_manuals.view', 'overtime.own'],
        'sales_manager'   => ['cases.manage', 'staff.view', 'schedule.view', 'reports.view', 'leaves.manage', 'inter_branch.view', 'repairs.view', 'quotations.manage', 'customers.manage', 'business_calendar.manage', 'business_tracking.manage', 'engineering_tracking.view', 'finance.view', 'procurement.view', 'inventory.view', 'reviews.view', 'tech_manuals.view', 'petty_cash.view', 'overtime.view', 'overtime.own'],
        'eng_manager'     => ['cases.view', 'staff_skills.manage', 'schedule.manage', 'reports.view', 'leaves.manage', 'inter_branch.manage', 'repairs.manage', 'quotations.view', 'customers.view', 'business_calendar.view', 'business_tracking.view', 'engineering_tracking.manage', 'finance.view', 'procurement.view', 'inventory.manage', 'reviews.view', 'tech_manuals.view', 'petty_cash.view', 'overtime.manage'],
        'eng_deputy'      => ['cases.view', 'staff.view', 'schedule.manage', 'leaves.manage', 'inter_branch.view', 'repairs.view', 'engineering_tracking.view', 'reviews.view', 'tech_manuals.view', 'overtime.view', 'overtime.own'],
        'engineer'        => ['cases.view', 'schedule.view', 'leaves.own', 'repairs.view', 'engineering_tracking.own', 'reviews.view', 'tech_manuals.view', 'overtime.own'],
        'sales'           => ['cases.own', 'schedule.view', 'leaves.own', 'repairs.own', 'quotations.own', 'customers.own', 'business_calendar.manage', 'business_tracking.own', 'reviews.view', 'tech_manuals.view', 'overtime.own'],
        'sales_assistant' => ['cases.assist', 'schedule.view', 'leaves.own', 'quotations.view', 'customers.view', 'business_calendar.view', 'business_tracking.view', 'reviews.view', 'tech_manuals.view', 'overtime.own'],
        'admin_staff'       => ['cases.view', 'staff.view', 'schedule.view', 'payments.manage', 'leaves.view', 'inter_branch.manage', 'repairs.manage', 'quotations.view', 'customers.view', 'business_calendar.view', 'business_tracking.view', 'engineering_tracking.view', 'finance.manage', 'procurement.manage', 'inventory.manage', 'accounting.manage', 'reviews.manage', 'tech_manuals.view', 'petty_cash.manage', 'overtime.own'],
        'accountant'        => ['cases.view', 'finance.manage', 'procurement.view', 'inventory.view', 'accounting.manage', 'reports.view', 'reviews.view', 'tech_manuals.view', 'petty_cash.manage', 'overtime.own'],
        'warehouse'         => ['inventory.manage', 'procurement.view', 'products.view', 'reviews.view', 'tech_manuals.view', 'overtime.own'],
        'purchaser'         => ['procurement.manage', 'procurement.view', 'inventory.view', 'products.view', 'approvals.view', 'customers.view', 'reviews.view', 'tech_manuals.view', 'overtime.own'],
        'hq'                => ['cases.view', 'staff.view', 'schedule.view', 'reports.view', 'leaves.view', 'inter_branch.view', 'repairs.view', 'quotations.view', 'customers.view', 'business_calendar.view', 'business_tracking.view', 'engineering_tracking.view', 'finance.view', 'procurement.view', 'inventory.view', 'products.view', 'reviews.view', 'tech_manuals.view', 'petty_cash.view', 'overtime.own'],
    ],

    // 案件編輯區域權限（角色預設）
    'case_section_defaults' => [
        'boss'              => ['basic','finance','schedule','attach','worklog','site','contacts','skills','delete'],
        'vice_president'    => ['basic','finance','schedule','attach','worklog','site','contacts','skills','delete'],
        'manager'           => ['basic','finance','schedule','attach','worklog','site','contacts','skills','delete'],
        'assistant_manager' => ['basic','finance','schedule','attach','contacts'],
        'sales_manager'     => ['basic','finance','contacts'],
        'eng_manager'     => ['basic','schedule','worklog','site','skills'],
        'eng_deputy'      => ['basic','schedule','worklog','site'],
        'engineer'        => ['schedule','worklog','site'],
        'sales'           => ['basic','finance','contacts'],
        'sales_assistant' => ['basic','contacts'],
        'admin_staff'       => ['basic','finance','attach','worklog','contacts'],
        'accountant'        => ['finance'],
        'warehouse'         => [],
        'purchaser'         => [],
        'hq'                => ['basic','finance'],
    ],

    // 區域標籤
    'case_section_labels' => [
        'basic'    => '基本資料',
        'finance'  => '帳務資訊',
        'schedule' => '施工時程',
        'attach'   => '附件管理',
        'worklog'  => '施工回報',
        'site'     => '現場環境',
        'contacts' => '聯絡人',
        'skills'   => '所需技能',
        'delete'   => '刪除案件',
    ],

    // 報表權限（角色預設）
    'report_defaults' => [
        'boss'              => ['case_summary','case_profit','staff_value','finance_summary','inter_branch_monthly','sales_personal','unpaid_cases','case_progress'],
        'vice_president'    => ['case_summary','case_profit','staff_value','finance_summary','inter_branch_monthly','sales_personal','unpaid_cases','case_progress'],
        'manager'           => ['case_summary','case_profit','staff_value','finance_summary','inter_branch_monthly','sales_personal','unpaid_cases','case_progress'],
        'assistant_manager' => ['case_summary','case_profit','finance_summary','unpaid_cases','case_progress'],
        'sales_manager'   => ['case_summary','case_profit','sales_personal','unpaid_cases','case_progress'],
        'eng_manager'     => ['case_summary','staff_value','inter_branch_monthly','unpaid_cases','case_progress'],
        'eng_deputy'      => ['staff_value'],
        'engineer'        => [],
        'sales'           => ['case_summary','sales_personal','case_progress'],
        'sales_assistant' => [],
        'admin_staff'       => ['case_summary','finance_summary','inter_branch_monthly','unpaid_cases','case_progress'],
        'accountant'        => ['finance_summary','unpaid_cases'],
        'warehouse'         => [],
        'purchaser'         => [],
        'hq'                => ['case_summary','case_profit','finance_summary','sales_personal','unpaid_cases','case_progress'],
    ],

    // 報表標籤（新增報表只要加在這裡，權限管理會自動出現）
    'report_labels' => [
        'case_summary'          => '案件綜合分析',
        'case_profit'           => '案件利潤分析',
        'staff_value'           => '員工產值統計',
        'finance_summary'       => '帳務綜合分析',
        'inter_branch_monthly'  => '跨點點工費月結',
        'sales_personal'        => '業務個人分析',
        'branch_monthly'        => '分公司月報',
        'unpaid_cases'          => '完工未收款/未完工',
        'case_progress'         => '案件更新進度',
    ],

    // 假別
    'leave_types' => [
        'annual'      => '特休',
        'day_off'     => '排休',
        'personal'    => '事假',
        'sick'        => '病假',
        'menstrual'   => '生理假',
        'bereavement' => '喪假',
        'official'    => '公假',
    ],

    // 證照到期預警天數
    'cert_warning_days' => 30,

    // API 設定
    'api' => [
        'rate_limit'     => 1000,  // 每小時
        'token_lifetime' => 86400, // 24小時
    ],
];
