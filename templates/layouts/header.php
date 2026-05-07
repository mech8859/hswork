<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?= e($pageTitle ?? '弱電工程排程系統') ?></title>
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#1a56db">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="禾順系統">
    <link rel="apple-touch-icon" href="/icons/icon-192.png">
    <link rel="icon" type="image/svg+xml" href="/icons/icon.svg">
    <link rel="stylesheet" href="/css/style.css?v=20260403b">
    <script src="/js/panzoom.min.js"></script>
    <?php if (!empty($extraCss)): ?>
        <?php foreach ($extraCss as $css): ?>
            <link rel="stylesheet" href="<?= e($css) ?>">
        <?php endforeach; ?>
    <?php endif; ?>
    <?php if (!empty($extraHeadHtml)) echo $extraHeadHtml; ?>
    <?php if (!empty($extraJs)): ?>
        <?php foreach ($extraJs as $js): ?>
            <script src="<?= e($js) ?>" defer></script>
        <?php endforeach; ?>
    <?php endif; ?>
</head>
<body>
<?php if (Session::isLoggedIn()): ?>
<nav class="navbar">
    <div class="navbar-brand">
        <button class="menu-toggle" id="menuToggle" aria-label="選單">&#9776;</button>
        <span class="navbar-title">禾順數位科技</span>
    </div>
    <div class="navbar-user">
        <!-- 通知鈴鐺 -->
        <div class="notif-bell" id="notifBell" onclick="toggleNotifDropdown()" style="position:relative;cursor:pointer;padding:4px 8px">
            <span style="font-size:1.2rem">🔔</span>
            <span class="notif-badge" id="notifBadge" style="display:none;position:absolute;top:0;right:2px;background:var(--danger);color:#fff;font-size:.65rem;padding:1px 5px;border-radius:10px;min-width:16px;text-align:center"></span>
        </div>
        <div class="notif-dropdown" id="notifDropdown" style="display:none;position:absolute;right:10px;top:48px;width:360px;max-height:400px;overflow-y:auto;background:#fff;color:#333;border:1px solid var(--gray-200);border-radius:8px;box-shadow:0 4px 16px rgba(0,0,0,.15);z-index:1100">
            <div style="display:flex;justify-content:space-between;align-items:center;padding:12px 16px;border-bottom:1px solid var(--gray-200)">
                <strong>通知</strong>
                <span>
                    <button type="button" onclick="markAllNotifRead()" style="background:none;border:none;color:var(--primary);cursor:pointer;font-size:.85rem">全部已讀</button>
                    <button type="button" onclick="deleteAllNotif()" style="background:none;border:none;color:var(--danger);cursor:pointer;font-size:.85rem">全部刪除</button>
                </span>
            </div>
            <div id="notifList" style="padding:0">
                <div style="padding:20px;text-align:center;color:var(--gray-400)">載入中...</div>
            </div>
        </div>
        <span class="user-branch"><?= e(Session::getUser()['branch_name'] ?? '') ?></span>
        <span class="user-name"><?= e(Session::getUser()['real_name'] ?? '') ?></span>
        <span class="user-role"><?= e(role_name(Session::getUser()['role'] ?? '')) ?></span>
    </div>
</nav>
<aside class="sidebar" id="sidebar">
    <ul class="nav-menu">
        <?php $cp = Session::get('custom_permissions'); ?>
        <li><a href="/index.php" class="<?= ($currentPage ?? '') === 'dashboard' ? 'active' : '' ?>">🏠 儀表板</a></li>

        <?php if (Auth::hasPermission('cases.manage') || Auth::hasPermission('cases.view') || Auth::hasPermission('cases.own')): ?>
        <li><a href="/cases.php" class="<?= ($currentPage ?? '') === 'cases' ? 'active' : '' ?>">📋 案件管理</a></li>
        <?php endif; ?>

        <!-- 工程管理 -->
        <li class="nav-section">工程管理</li>
        <?php if (Auth::hasPermission('engineering_tracking.manage') || Auth::hasPermission('engineering_tracking.view') || Auth::hasPermission('engineering_tracking.own')): ?>
        <li><a href="/engineering_tracking.php" class="<?= ($currentPage ?? '') === 'engineering_tracking' ? 'active' : '' ?>">🔧 工程追蹤</a></li>
        <?php endif; ?>
        <?php if (Auth::hasPermission('schedule.manage') || Auth::hasPermission('schedule.view')): ?>
        <li><a href="/schedule.php" class="<?= ($currentPage ?? '') === 'schedule' ? 'active' : '' ?>">📅 工程行事曆</a></li>
        <?php endif; ?>
        <?php if (Auth::hasPermission('repairs.manage') || Auth::hasPermission('repairs.view') || Auth::hasPermission('repairs.own')): ?>
        <li><a href="/repairs.php" class="<?= ($currentPage ?? '') === 'repairs' ? 'active' : '' ?>">🔧 維修單</a></li>
        <?php endif; ?>
        <?php if (Auth::hasPermission('worklog.manage') || Auth::hasPermission('worklog.view')): ?>
        <li><a href="/worklog.php" class="<?= ($currentPage ?? '') === 'worklog' ? 'active' : '' ?>">📝 施工回報</a></li>
        <?php endif; ?>
        <?php if (Auth::hasPermission('attendance.view') || Auth::hasPermission('schedule.manage') || Auth::hasPermission('schedule.view')): ?>
        <li><a href="/attendance.php" class="<?= ($currentPage ?? '') === 'attendance' ? 'active' : '' ?>">📊 出勤狀況表</a></li>
        <?php endif; ?>
        <?php if (Auth::hasPermission('reviews.manage') || Auth::hasPermission('reviews.view') || Auth::hasPermission('all')): ?>
        <li><a href="/reviews.php" class="<?= ($currentPage ?? '') === 'reviews' ? 'active' : '' ?>">⭐ 五星評價統計</a></li>
        <?php endif; ?>
        <?php if (Auth::hasPermission('tech_manuals.manage') || Auth::hasPermission('tech_manuals.view') || Auth::hasPermission('all')): ?>
        <li><a href="/tech_manuals.php" class="<?= ($currentPage ?? '') === 'tech_manuals' ? 'active' : '' ?>">📖 技術手冊</a></li>
        <?php endif; ?>

        <!-- 業務管理 -->
        <li class="nav-section">業務管理</li>
        <?php if (Auth::hasPermission('business_tracking.manage') || Auth::hasPermission('business_tracking.view') || Auth::hasPermission('business_tracking.own')): ?>
        <li><a href="/business_tracking.php" class="<?= ($currentPage ?? '') === 'business_tracking' ? 'active' : '' ?>">📊 業務追蹤表</a></li>
        <?php endif; ?>
        <?php if (Auth::hasPermission('quotations.manage') || Auth::hasPermission('quotations.view') || Auth::hasPermission('quotations.own')): ?>
        <li><a href="/quotations.php" class="<?= ($currentPage ?? '') === 'quotations' ? 'active' : '' ?>">💲 報價管理</a></li>
        <?php endif; ?>
        <?php if (Auth::hasPermission('business_calendar.manage') || Auth::hasPermission('business_calendar.view')): ?>
        <li><a href="/business_calendar.php" class="<?= ($currentPage ?? '') === 'business_calendar' ? 'active' : '' ?>">📅 業務行事曆</a></li>
        <?php endif; ?>
        <?php if (Auth::hasPermission('customers.manage') || Auth::hasPermission('customers.view')): ?>
        <li><a href="/customers.php" class="<?= ($currentPage ?? '') === 'customers' ? 'active' : '' ?>">👤 客戶管理</a></li>
        <?php endif; ?>

        <!-- 簽核管理 -->
        <?php
        $pendingApprovalCount = 0;
        try {
            require_once __DIR__ . '/../../modules/approvals/ApprovalModel.php';
            $tmpApproval = new ApprovalModel();
            $pendingApprovalCount = $tmpApproval->getPendingCount(Auth::id());
        } catch (Exception $e) {}
        $showApprovalMenu = $pendingApprovalCount > 0 || Auth::hasPermission('approvals.view') || Auth::hasPermission('approvals.manage') || Auth::hasPermission('all');
        ?>
        <?php if ($showApprovalMenu): ?>
        <li class="nav-section">簽核管理</li>
        <li><a href="/approvals.php" class="<?= ($currentPage ?? '') === 'approvals' ? 'active' : '' ?>">✅ 待簽核<?php if ($pendingApprovalCount > 0): ?> <span class="badge" style="background:var(--danger);color:#fff;font-size:.7rem"><?= $pendingApprovalCount ?></span><?php endif; ?></a></li>
        <?php if (Auth::user()['role'] === 'boss'): ?>
        <li><a href="/approvals.php?action=settings" class="<?= ($currentPage ?? '') === 'approval_settings' ? 'active' : '' ?>">⚙️ 簽核設定</a></li>
        <?php endif; ?>
        <?php endif; ?>

        <!-- 產品庫存 -->
        <?php if (Auth::hasPermission('products.manage') || Auth::hasPermission('products.view')): ?>
        <li class="nav-section">產品庫存</li>
        <li><a href="/products.php" class="<?= ($currentPage ?? '') === 'products' ? 'active' : '' ?>">📦 產品目錄</a></li>
        <?php endif; ?>

        <!-- 財務會計 -->
        <?php
        $hasFinancePerm = Auth::hasPermission('finance.manage') || Auth::hasPermission('finance.view');
        $hasPettyCashPerm = Auth::hasPermission('petty_cash.manage') || Auth::hasPermission('petty_cash.view');
        ?>
        <?php if ($hasFinancePerm || $hasPettyCashPerm): ?>
        <li class="nav-section">財務會計</li>
        <?php if ($hasFinancePerm): ?>
        <li><a href="/receivables.php" class="<?= ($currentPage ?? '') === 'receivables' ? 'active' : '' ?>">📄 應收帳款</a></li>
        <li><a href="/receipts.php" class="<?= ($currentPage ?? '') === 'receipts' ? 'active' : '' ?>">💰 收款單</a></li>
        <li><a href="/payables.php" class="<?= ($currentPage ?? '') === 'payables' ? 'active' : '' ?>">📋 應付帳款單</a></li>
        <li><a href="/payments_out.php" class="<?= ($currentPage ?? '') === 'payments_out' ? 'active' : '' ?>">💸 付款單</a></li>
        <li><a href="/bank_transactions.php" class="<?= ($currentPage ?? '') === 'bank_transactions' ? 'active' : '' ?>">🏦 銀行帳戶明細</a></li>
        <?php endif; ?>
        <?php if ($hasFinancePerm || $hasPettyCashPerm): ?>
        <li><a href="/petty_cash.php" class="<?= ($currentPage ?? '') === 'petty_cash' ? 'active' : '' ?>">🪙 零用金管理</a></li>
        <?php endif; ?>
        <?php if ($hasFinancePerm): ?>
        <li><a href="/reserve_fund.php" class="<?= ($currentPage ?? '') === 'reserve_fund' ? 'active' : '' ?>">💵 備用金管理</a></li>
        <li><a href="/cash_details.php" class="<?= ($currentPage ?? '') === 'cash_details' ? 'active' : '' ?>">📝 現金明細</a></li>
        <li><a href="/purchase_invoices.php" class="<?= ($currentPage ?? '') === 'purchase_invoices' ? 'active' : '' ?>">📥 進項發票</a></li>
        <li><a href="/sales_invoices.php" class="<?= ($currentPage ?? '') === 'sales_invoices' ? 'active' : '' ?>">📤 銷項發票</a></li>
        <li><a href="/tax_report.php" class="<?= ($currentPage ?? '') === 'tax_report' ? 'active' : '' ?>">📊 401營業稅申報</a></li>
        <?php endif; ?>
        <?php endif; ?>

        <!-- 會計管理 -->
        <?php if (Auth::hasPermission('accounting.manage') || Auth::hasPermission('accounting.view') || Auth::hasPermission('finance.manage') || Auth::hasPermission('finance.view')): ?>
        <li class="nav-section">會計管理</li>
        <li><a href="/accounting.php?action=journals" class="<?= ($currentPage ?? '') === 'accounting' && !in_array(($action ?? ''), array('accounts','cost_centers','journal_reports','ledger','offset_ledger','offset_reports','trial_balance','income_statement','balance_sheet','reconciliation','voucher_reconciliation','invoice_voucher_reconciliation','financial_reports','budget')) ? 'active' : '' ?>">📋 傳票管理</a></li>
        <li><a href="/accounting.php?action=accounts" class="<?= ($currentPage ?? '') === 'accounting' && ($action ?? '') === 'accounts' ? 'active' : '' ?>">📊 會計科目</a></li>
        <li><a href="/accounting.php?action=cost_centers" class="<?= ($currentPage ?? '') === 'accounting' && ($action ?? '') === 'cost_centers' ? 'active' : '' ?>">🏢 成本中心</a></li>
        <li><a href="/accounting.php?action=journal_reports" class="<?= ($currentPage ?? '') === 'accounting' && ($action ?? '') === 'journal_reports' ? 'active' : '' ?>">📄 傳票報表</a></li>
        <li><a href="/accounting.php?action=ledger" class="<?= ($currentPage ?? '') === 'accounting' && ($action ?? '') === 'ledger' ? 'active' : '' ?>">📖 總帳查詢</a></li>
        <li><a href="/accounting.php?action=offset_ledger" class="<?= ($currentPage ?? '') === 'accounting' && ($action ?? '') === 'offset_ledger' ? 'active' : '' ?>">🔄 立沖帳查詢</a></li>
        <li><a href="/accounting.php?action=offset_reports" class="<?= ($currentPage ?? '') === 'accounting' && ($action ?? '') === 'offset_reports' ? 'active' : '' ?>">📑 立沖帳報表</a></li>
        <li><a href="/accounting.php?action=trial_balance" class="<?= ($currentPage ?? '') === 'accounting' && ($action ?? '') === 'trial_balance' ? 'active' : '' ?>">📑 試算表</a></li>
        <li><a href="/accounting.php?action=income_statement" class="<?= ($currentPage ?? '') === 'accounting' && ($action ?? '') === 'income_statement' ? 'active' : '' ?>">📈 損益表</a></li>
        <li><a href="/accounting.php?action=balance_sheet" class="<?= ($currentPage ?? '') === 'accounting' && ($action ?? '') === 'balance_sheet' ? 'active' : '' ?>">📊 資產負債表</a></li>
        <li><a href="/accounting.php?action=reconciliation" class="<?= ($currentPage ?? '') === 'accounting' && ($action ?? '') === 'reconciliation' ? 'active' : '' ?>">🏦 銀行對帳</a></li>
        <li><a href="/accounting.php?action=voucher_reconciliation" class="<?= ($currentPage ?? '') === 'accounting' && ($action ?? '') === 'voucher_reconciliation' ? 'active' : '' ?>">✅ 傳票核對</a></li>
        <li><a href="/accounting.php?action=invoice_voucher_reconciliation&type=sales" class="<?= ($currentPage ?? '') === 'accounting' && ($action ?? '') === 'invoice_voucher_reconciliation' ? 'active' : '' ?>">🧾 發票傳票對帳</a></li>
        <li><a href="/accounting.php?action=financial_reports" class="<?= ($currentPage ?? '') === 'accounting' && ($action ?? '') === 'financial_reports' ? 'active' : '' ?>">📊 財務報表</a></li>
        <li><a href="/accounting.php?action=budget" class="<?= ($currentPage ?? '') === 'accounting' && ($action ?? '') === 'budget' ? 'active' : '' ?>">📝 預算編輯</a></li>
        <?php endif; ?>

        <!-- 採購進貨 -->
        <?php if (Auth::hasPermission('procurement.manage') || Auth::hasPermission('procurement.view')): ?>
        <li class="nav-section">採購進貨</li>
        <li><a href="/requisitions.php" class="<?= ($currentPage ?? '') === 'requisitions' ? 'active' : '' ?>">📋 請購單</a></li>
        <li><a href="/purchase_orders.php" class="<?= ($currentPage ?? '') === 'purchase_orders' ? 'active' : '' ?>">🛒 採購單</a></li>
        <li><a href="/vendors.php" class="<?= ($currentPage ?? '') === 'vendors' ? 'active' : '' ?>">🏭 廠商管理</a></li>
        <li><a href="/vendor_products.php" class="<?= ($currentPage ?? '') === 'vendor_products' ? 'active' : '' ?>">🔗 廠商產品對照</a></li>
        <li><a href="/vendor_invoices.php" class="<?= ($currentPage ?? '') === 'vendor_invoices' ? 'active' : '' ?>">📥 廠商請款單</a></li>
        <?php endif; ?>

        <!-- 庫存管理 -->
        <?php if (Auth::hasPermission('inventory.manage') || Auth::hasPermission('inventory.view')): ?>
        <li class="nav-section">庫存管理</li>
        <li><a href="/inventory.php" class="<?= ($currentPage ?? '') === 'inventory' ? 'active' : '' ?>">📦 庫存管理</a></li>
        <li><a href="/stock_outs.php" class="<?= ($currentPage ?? '') === 'stock_outs' ? 'active' : '' ?>">📤 出庫單</a></li>
        <li><a href="/stock_ins.php" class="<?= ($currentPage ?? '') === 'stock_ins' ? 'active' : '' ?>">📥 入庫單</a></li>

        <li><a href="/goods_receipts.php" class="<?= ($currentPage ?? '') === 'goods_receipts' ? 'active' : '' ?>">📥 進貨單</a></li>
        <li><a href="/returns.php" class="<?= ($currentPage ?? '') === 'returns' ? 'active' : '' ?>">🔄 退貨單</a></li>
        <li><a href="/warehouse_transfers.php" class="<?= ($currentPage ?? '') === 'warehouse_transfers' ? 'active' : '' ?>">🔀 調撥單</a></li>
        <li><a href="/inventory.php?action=stocktake_list" class="<?= ($currentPage ?? '') === 'stocktake' ? 'active' : '' ?>">📋 盤點單</a></li>
        <li><a href="/inventory.php?action=movements" class="<?= ($currentPage ?? '') === 'movements' ? 'active' : '' ?>">📊 庫存異動表</a></li>
        <?php endif; ?>

        <!-- 人事行政 -->
        <li class="nav-section">人事行政</li>
        <?php if (Auth::hasPermission('staff.manage') || Auth::hasPermission('staff.view') || Auth::hasPermission('staff_skills.manage')): ?>
        <li><a href="/staff.php" class="<?= ($currentPage ?? '') === 'staff' ? 'active' : '' ?>">👥 人員管理</a></li>
        <?php endif; ?>
        <?php if (Auth::hasPermission('vehicles.manage') || Auth::hasPermission('vehicles.view')): ?>
        <li><a href="/vehicles.php" class="<?= ($currentPage ?? '') === 'vehicles' ? 'active' : '' ?>">🚗 車輛管理</a></li>
        <?php endif; ?>
        <?php if (Auth::hasPermission('leaves.manage') || Auth::hasPermission('leaves.own') || Auth::hasPermission('leaves.view')): ?>
        <li><a href="/leaves.php" class="<?= ($currentPage ?? '') === 'leaves' ? 'active' : '' ?>">🏖 請假管理</a></li>
        <?php endif; ?>
        <?php if (Auth::hasPermission('overtime.manage') || Auth::hasPermission('overtime.own') || Auth::hasPermission('overtime.view')): ?>
        <li><a href="/overtimes.php" class="<?= ($currentPage ?? '') === 'overtimes' ? 'active' : '' ?>">⏰ 加班單管理</a></li>
        <?php endif; ?>
        <?php if (Auth::hasPermission('attendance.manage') || Auth::hasPermission('attendance.view') || Auth::hasPermission('all')): ?>
        <li><a href="/moa_attendance.php" class="<?= ($currentPage ?? '') === 'moa_attendance' ? 'active' : '' ?>">🕒 MOA 考勤</a></li>
        <?php endif; ?>
        <?php if (Auth::hasPermission('inter_branch.manage') || Auth::hasPermission('inter_branch.view')): ?>
        <li><a href="/inter_branch.php" class="<?= ($currentPage ?? '') === 'inter_branch' && !isset($_GET['action']) ? 'active' : '' ?>">💰 點工費管理</a></li>
        <li><a href="/inter_branch.php?action=attendance" class="<?= ($currentPage ?? '') === 'inter_branch' && (isset($_GET['action']) && $_GET['action'] === 'attendance') ? 'active' : '' ?>">📋 點工出勤登錄</a></li>
        <li><a href="/inter_branch.php?action=attendance_settle_page" class="<?= ($currentPage ?? '') === 'inter_branch' && (isset($_GET['action']) && $_GET['action'] === 'attendance_settle_page') ? 'active' : '' ?>">📊 點工出勤結算</a></li>
        <?php endif; ?>
        <?php if (Auth::hasPermission('transactions.manage') || Auth::hasPermission('transactions.view')): ?>
        <li><a href="/transactions.php" class="<?= ($currentPage ?? '') === 'transactions' ? 'active' : '' ?>">💳 非廠商交易</a></li>
        <?php endif; ?>
        <?php if (Auth::hasPermission('finance.manage') || Auth::hasPermission('finance.view')): ?>
        <li><a href="/remittance.php" class="<?= ($currentPage ?? '') === 'remittance' ? 'active' : '' ?>">🏦 未繳回帳務</a></li>
        <?php endif; ?>
        <?php if (Auth::hasPermission('reports.view')): ?>
        <li><a href="/reports.php" class="<?= ($currentPage ?? '') === 'reports' ? 'active' : '' ?>">📈 報表</a></li>
        <?php endif; ?>

        <?php if (Auth::hasPermission('settings.manage')): ?>
        <!-- 系統設定 -->
        <li class="nav-section">系統設定</li>
        <li><a href="/dropdown_options.php" class="<?= ($currentPage ?? '') === 'dropdown_options' ? 'active' : '' ?>">⚙ 選單管理</a></li>
        <li><a href="/notification_settings.php" class="<?= ($currentPage ?? '') === 'notification_settings' ? 'active' : '' ?>">🔔 通知設定</a></li>
        <li><a href="/audit_logs.php" class="<?= ($currentPage ?? '') === 'audit_logs' ? 'active' : '' ?>">📋 操作日誌</a></li>
        <?php endif; ?>

        <li class="nav-divider"></li>
        <li><a href="/logout.php">🚪 登出</a></li>
    </ul>
</aside>
<script>
(function(){
    var el = document.querySelector('.sidebar a.active');
    if (el) el.scrollIntoView({block:'center',behavior:'auto'});
})();
</script>
<main class="content" id="mainContent">
<?php else: ?>
<main class="content content-full">
<?php endif; ?>

<?php
// Flash 訊息
$flashSuccess = Session::getFlash('success');
$flashError = Session::getFlash('error');
if ($flashSuccess): ?>
    <div class="alert alert-success"><?= e($flashSuccess) ?></div>
<?php endif; ?>
<?php if ($flashError): ?>
    <div class="alert alert-error"><?= e($flashError) ?></div>
<?php endif; ?>
