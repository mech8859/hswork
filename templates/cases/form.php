<div class="d-flex justify-between align-center flex-wrap gap-1 mb-2">
    <div>
        <h2><?= $case ? '編輯案件 - ' . e($case['case_number']) : '新增案件' ?></h2>
        <?php if ($case): ?>
        <?php
        // 狀態（sub_status）- 點擊到業務追蹤表
        $subStatusVal = isset($case['sub_status']) ? $case['sub_status'] : '';
        if ($subStatusVal):
            $salesStages = array('待聯絡','未指派','已聯絡安排場勘','已聯絡電話報價','已報價待追蹤','已會勘未報價','電話不通或未接','待場勘','已聯絡待場勘','規劃或預算案','待追蹤');
            $engStages = array('未完工','已成交','跨月成交','現簽','電話報價成交','待安排派工查修','完工未收款','已完工結案');
            if (in_array($subStatusVal, $salesStages)) {
                $trackUrl = '/business_tracking.php?keyword=' . urlencode($case['case_number']);
            } else {
                $trackUrl = '/engineering_tracking.php?keyword=' . urlencode($case['case_number']);
            }
        ?>
        <a href="<?= $trackUrl ?>" class="badge badge-info" style="text-decoration:none;cursor:pointer" title="點擊前往追蹤表"><?= e($subStatusVal) ?></a>
        <?php endif; ?>
        <span class="badge <?= CaseModel::statusBadge($case['status']) ?>"><?= e(CaseModel::statusLabel($case['status'])) ?></span>
        <span class="badge badge-primary"><?= e(CaseModel::typeLabel($case['case_type'])) ?></span>
        <span class="text-muted" style="font-size:.85rem"><?= e($case['branch_name'] ?? '') ?></span>
        <?php
        if (!empty($case['id'])) {
            $_sbStmt = Database::getInstance()->prepare('SELECT b.name FROM case_branch_support cbs JOIN branches b ON cbs.branch_id = b.id WHERE cbs.case_id = ? ORDER BY b.name');
            $_sbStmt->execute(array($case['id']));
            $_sbList = $_sbStmt->fetchAll(PDO::FETCH_COLUMN);
            foreach ($_sbList as $_sbName): ?>
            <span class="badge" style="background:#ede9fe;color:#6366f1;font-size:.78rem">支援：<?= e($_sbName) ?></span>
            <?php endforeach;
        }
        ?>
        <?php if (!empty($case['updated_at'])): ?>
        <?php
        $caseUpdaterName = '';
        $updaterId = !empty($case['updated_by']) ? $case['updated_by'] : (!empty($case['created_by']) ? $case['created_by'] : null);
        if ($updaterId) {
            $uStmt = Database::getInstance()->prepare("SELECT real_name FROM users WHERE id = ?");
            $uStmt->execute(array($updaterId));
            $caseUpdaterName = $uStmt->fetchColumn() ?: '';
        }
        ?>
        <span style="font-size:.8rem;color:var(--gray-500)">最後修改：<?= date('Y/m/d H:i', strtotime($case['updated_at'])) ?><?= $caseUpdaterName ? ' by ' . e($caseUpdaterName) : '' ?></span>
        <?php endif; ?>
        <?php
        if (function_exists('get_readiness_warnings')) {
            $liveReadiness = function_exists('compute_case_readiness_live') ? compute_case_readiness_live($case) : ($case['readiness'] ?: array());
            $warnings = get_readiness_warnings($liveReadiness, $case['case_type'] ?: 'new_install');
            if (!empty($warnings)):
        ?>
        <span style="color:#e65100;font-size:.85rem;font-weight:600;margin-left:12px">排工條件尚未備齊：<?= implode('、', array_map('e', $warnings)) ?></span>
        <?php endif; } ?>
        <?php
        // 出庫單狀態（純顯示）
        if (!empty($caseStockOutStatus) && $caseStockOutStatus['quote_count'] > 0):
            if ($caseStockOutStatus['stockout_count'] > 0):
                foreach ($caseStockOutStatus['stockouts'] as $_so):
        ?>
        <a href="/stock_outs.php?action=view&id=<?= $_so['id'] ?>" style="font-size:.85rem;font-weight:600;margin-left:12px;text-decoration:none;color:var(--success)" title="點擊查看出庫單">📦 出庫單 <?= e($_so['so_number']) ?> <span class="badge" style="background:#e8f5e9;color:#2e7d32;font-size:.7rem;padding:1px 6px"><?= e($_so['status']) ?></span></a>
        <?php endforeach; else: ?>
        <span style="color:#e65100;font-size:.85rem;font-weight:600;margin-left:12px">📦 出庫單尚未建立</span>
        <?php endif; endif; ?>
        <?php endif; ?>
    </div>
    <div class="d-flex gap-1">
        <?php
        // 以下進度不顯示排工按鈕：已完工結案 / 已完工待簽核 / 待追蹤 / 毀約 / 客戶取消 / 無效
        $hideScheduleStatuses = array('closed','completed_pending','unpaid','tracking','pending','breach','customer_cancel','cancelled','lost');
        // 虧損尚未簽核擋排工：該案件有 profit_rate<0 的報價單且狀態在 draft/pending_approval 等未過關階段
        $lossBlockReason = '';
        if ($case) {
            $_lbStmt = Database::getInstance()->prepare("
                SELECT quotation_number, status, profit_rate
                FROM quotations
                WHERE case_id = ? AND profit_rate < 0
                  AND status IN ('draft','pending_approval','rejected_internal','revision_needed','pending_revision')
                LIMIT 1
            ");
            $_lbStmt->execute(array($case['id']));
            $_lbRow = $_lbStmt->fetch(PDO::FETCH_ASSOC);
            if ($_lbRow) {
                $_lbStatusLabel = $_lbRow['status'] === 'pending_approval' ? '簽核中' : ($_lbRow['status'] === 'draft' ? '未送簽核' : '待重送簽核');
                $lossBlockReason = '報價單 ' . $_lbRow['quotation_number'] . ' 為虧損，' . $_lbStatusLabel . '，需完成簽核才能排工';
            }
        }
        ?>
        <?php if ($case && Auth::hasPermission('schedule.manage') && !in_array($case['status'], $hideScheduleStatuses)): ?>
        <?php if ($lossBlockReason): ?>
        <button type="button" class="btn btn-sm" style="background:#9e9e9e;color:#fff;cursor:not-allowed" onclick="alert('<?= e($lossBlockReason) ?>')" title="<?= e($lossBlockReason) ?>">手動排工 🔒</button>
        <button type="button" class="btn btn-sm" style="background:#9e9e9e;color:#fff;cursor:not-allowed" onclick="alert('<?= e($lossBlockReason) ?>')" title="<?= e($lossBlockReason) ?>">智慧排工 🔒</button>
        <?php else: ?>
        <a href="/schedule.php?action=create&case_id=<?= $case['id'] ?>" class="btn btn-sm" style="background:#FF9800;color:#fff">手動排工</a>
        <?php if (!empty($warnings)): ?>
        <button type="button" class="btn btn-success btn-sm" onclick="alert('排工條件尚未備齊：<?= implode('、', array_map('e', $warnings)) ?>\n\n請先補齊資料再使用智慧排工。')">智慧排工</button>
        <?php else: ?>
        <a href="/schedule.php?action=smart&case_id=<?= $case['id'] ?>" class="btn btn-success btn-sm">智慧排工</a>
        <?php endif; ?>
        <?php endif; /* loss block end */ ?>
        <?php endif; /* schedule permission end */ ?>
        <?php if ($case && Auth::canEditSection('delete')): ?>
        <button type="button" class="btn btn-danger btn-sm" onclick="confirmDeleteCase(<?= $case['id'] ?>, '<?= e($case['case_number']) ?>')">刪除</button>
        <?php endif; ?>
        <?= back_button('/cases.php') ?>
    </div>
</div>

<!-- 區域權限判斷 -->
<?php
$canEdit = array(
    'basic'    => Auth::canEditSection('basic'),
    'finance'  => Auth::canEditSection('finance'),
    'schedule' => Auth::canEditSection('schedule'),
    'attach'   => Auth::canEditSection('attach'),
    'site'     => Auth::canEditSection('site'),
    'contacts' => Auth::canEditSection('contacts'),
    'skills'   => Auth::canEditSection('skills'),
);
// 會計主管自動有 finance 區的編輯權（含請款流程編輯/刪除）
if (Auth::user() && Auth::user()['role'] === 'accounting_supervisor') {
    $canEdit['finance'] = true;
}
// 新增案件時全部可編輯
if (!$case) { foreach ($canEdit as $k => $v) { $canEdit[$k] = true; } }

// 表單級唯讀（由 controller 傳入 $caseCanEdit）
if (!isset($caseCanEdit)) $caseCanEdit = true;
$readOnly = $case && !$caseCanEdit;
// 唯讀時，全部區段都鎖定
if ($readOnly) {
    foreach ($canEdit as $k => $v) { $canEdit[$k] = false; }
}
require __DIR__ . '/../_readonly_form_helper.php';
?>

<!-- 區域導航 -->
<?php if ($case): ?>
<div class="section-nav">
    <a href="#sec-basic" class="sec-link active">基本資料<?= $canEdit['basic'] ? '' : ' 🔒' ?></a>
    <a href="#sec-finance" class="sec-link">帳務資訊<?= $canEdit['finance'] ? '' : ' 🔒' ?></a>
    <a href="#sec-case-payments" class="sec-link">帳款交易 <span class="badge" style="font-size:.7rem;padding:1px 6px;background:#eee;color:#666"><?= count($case['case_payments'] ?? array()) ?></span></a>
    <a href="#sec-schedule" class="sec-link">施工時程<?= $canEdit['schedule'] ? '' : ' 🔒' ?></a>
    <a href="#sec-attach" class="sec-link">附件管理<?= $canEdit['attach'] ? '' : ' 🔒' ?></a>
    <?php
    // 施工回報 badge：排工回報 + 手動/Ragic（排除已同步的避免重複計數）
    $_wlBadgeCount = count($worklogTimeline);
    foreach (($case['case_work_logs'] ?? array()) as $_cwl) {
        if (empty($_cwl['source_worklog_id'])) $_wlBadgeCount++;
    }
    ?>
    <a href="#sec-worklog" class="sec-link">施工回報 <span class="badge" style="font-size:.7rem;padding:1px 6px;background:#eee;color:#666"><?= $_wlBadgeCount ?></span></a>
    <a href="#sec-readiness" class="sec-link">排工驗證</a>
    <a href="#sec-materials" class="sec-link">預計材料 <span class="badge" style="font-size:.7rem;padding:1px 6px;background:#eee;color:#666"><?= count($case['material_estimates'] ?? array()) ?></span></a>
    <a href="#sec-site" class="sec-link">現場環境<?= $canEdit['site'] ? '' : ' 🔒' ?></a>
    <a href="#sec-skills" class="sec-link">所需技能<?= $canEdit['skills'] ? '' : ' 🔒' ?></a>
</div>
<?php endif; ?>

<?php require __DIR__ . '/../layouts/editing_lock_warning.php'; ?>

<form method="POST" class="mt-2 <?= $readOnly ? 'form-readonly' : '' ?>" onsubmit="return validateCaseForm()">
    <?= csrf_field() ?>

    <!-- 基本資料 -->
    <div class="card <?= $canEdit['basic'] ? '' : 'section-readonly' ?>" id="sec-basic">
        <div class="card-header">基本資料</div>
        <div class="form-row" style="align-items:flex-end">
            <div class="form-group" style="flex:0 0 140px">
                <label>進件編號</label>
                <input type="text" class="form-control" value="<?= e($case ? $case['case_number'] : peek_next_doc_number('cases')) ?>" readonly style="background:#f0f7ff;font-weight:600;color:var(--primary)">
            </div>
            <div class="form-group" style="flex:0 0 130px">
                <label>進件日期</label>
                <input type="text" class="form-control" value="<?= e($case ? substr($case['created_at'], 0, 10) : date('Y-m-d')) ?>" readonly style="background:#f0f7ff;color:var(--gray-600)">
            </div>
            <?php
            // 預填客戶資料（從客戶管理帶入）
            $pCustId = $case['customer_id'] ?? (isset($prefillCustomer) && $prefillCustomer ? $prefillCustomer['id'] : '');
            $pCustNo = $case ? ($case['linked_customer_no'] ?? $case['customer_no'] ?? '') : (isset($prefillCustomer) && $prefillCustomer ? $prefillCustomer['customer_no'] : '');
            $pCustName = $case['customer_name'] ?? (isset($prefillCustomer) && $prefillCustomer ? $prefillCustomer['name'] : '');
            $pCustCat = $case['customer_category'] ?? (isset($prefillCustomer) && $prefillCustomer ? ($prefillCustomer['category'] ?? '') : '');
            ?>
            <div class="form-group" style="flex:0 0 100px">
                <label>客戶編號</label>
                <input type="text" id="customerNoDisplay" class="form-control" value="<?= e($pCustNo) ?>" readonly style="background:#f0f7ff;font-weight:600;color:var(--primary)">
            </div>
            <div class="form-group" style="flex:1;min-width:160px;position:relative">
                <label>客戶名稱</label>
                <input type="hidden" name="customer_id" id="customerId" value="<?= e($pCustId) ?>">
                <input type="text" name="customer_name" id="customerNameInput" class="form-control" value="<?= e($pCustName) ?>" placeholder="輸入客戶名稱搜尋..." autocomplete="off" onkeyup="onCustomerKeyup(event)">
                <div id="customerDropdown" class="customer-dropdown" style="display:none"></div>
                <?php if ($pCustId): ?>
                <small class="text-muted" id="customerInfo" style="position:absolute;bottom:-18px;left:0;font-size:.75rem;z-index:2"><a href="customers.php?action=view&id=<?= e($pCustId) ?>" style="color:#007bff;text-decoration:underline;cursor:pointer">已關聯客戶 <?= e($pCustName) ?><?= $pCustNo ? ' (' . e($pCustNo) . ')' : '' ?></a></small>
                <?php else: ?>
                <small class="text-muted" id="customerInfo" style="position:absolute;bottom:-18px;left:0;font-size:.75rem;z-index:2"></small>
                <?php endif; ?>
            </div>
            <div class="form-group" style="flex:0 0 160px">
                <label>客戶分類</label>
                <select name="customer_category" class="form-control">
                    <option value="">請選擇</option>
                    <?php foreach (array('個人 / 住戶','一般公司 / 企業','製造 / 工廠','餐飲業','零售 / 店面','社區 / 管委會','機關 / 政府','金融 / 保險','醫療 / 健康照護','建設 / 營造','教育','宗教','旅宿業','上市櫃企業','休閒娛樂','物流 / 倉儲','協會 / 團體') as $cc): ?>
                    <option value="<?= $cc ?>" <?= $pCustCat === $cc ? 'selected' : '' ?>><?= $cc ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="flex:0 0 auto">
                <?php
                $dealStatuses = array('已成交','跨月成交','現簽','電話報價成交');
                $curSubStatus = isset($case['sub_status']) ? $case['sub_status'] : '';
                // 與顯示邏輯一致：linked_customer_no 或 customer_no 或 customer_id 任一有值
                $hasCustomerLinked = !empty($case['linked_customer_no']) || !empty($case['customer_no']) || !empty($case['customer_id']);
                $hasCreateCustomer = Auth::hasPermission('customers.create');
                // 已有客戶編號代表已連結既有客戶，不需再新增客戶；且使用者要有 customers.create 權限
                $showNewBtn = in_array($curSubStatus, $dealStatuses, true) && !$hasCustomerLinked && $hasCreateCustomer;
                ?>
                <?php if ($hasCreateCustomer && !$hasCustomerLinked): ?>
                <button type="button" id="btnNewCustomer" class="btn btn-outline btn-sm" onclick="openNewCustomerModal()" style="white-space:nowrap;<?= $showNewBtn ? '' : 'display:none' ?>">+ 新增客戶</button>
                <?php endif; ?>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group" style="flex:2;min-width:200px">
                <label>案件名稱 *</label>
                <input type="text" name="title" class="form-control" value="<?= e($case['title'] ?? (isset($prefillCustomer) && $prefillCustomer ? $prefillCustomer['name'] : '')) ?>" required>
            </div>
            <div class="form-group">
                <label>所屬分公司 *</label>
                <select name="branch_id" class="form-control" required>
                    <option value="">請選擇</option>
                    <?php foreach ($branches as $b): ?>
                    <option value="<?= $b['id'] ?>" <?= ($case['branch_id'] ?? '') == $b['id'] ? 'selected' : '' ?>><?= e($b['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>進件公司</label>
                <?php $pCompany = $case['company'] ?? (isset($prefillCustomer) && $prefillCustomer ? ($prefillCustomer['source_company'] ?? '') : ''); ?>
                <select name="company" id="companyInput" class="form-control">
                    <option value="">請選擇</option>
                    <?php if (isset($caseCompanyOptions)): foreach ($caseCompanyOptions as $opt): ?>
                    <option value="<?= e($opt['label']) ?>" <?= $pCompany === $opt['label'] ? 'selected' : '' ?>><?= e($opt['label']) ?></option>
                    <?php endforeach; endif; ?>
                    <?php if ($pCompany && !in_array($pCompany, array_column($caseCompanyOptions ?? array(), 'label'))): ?>
                    <option value="<?= e($pCompany) ?>" selected><?= e($pCompany) ?></option>
                    <?php endif; ?>
                </select>
            </div>
            <div class="form-group">
                <label>案件來源</label>
                <select name="case_source" class="form-control">
                    <option value="">請選擇</option>
                    <?php if (isset($caseSourceOptions)): foreach ($caseSourceOptions as $opt): ?>
                    <option value="<?= e($opt['label']) ?>" <?= ($case['case_source'] ?? '') === $opt['label'] ? 'selected' : '' ?>><?= e($opt['label']) ?></option>
                    <?php endforeach; endif; ?>
                    <?php if (!empty($case['case_source']) && !in_array($case['case_source'], array_column($caseSourceOptions ?? array(), 'label'))): ?>
                    <option value="<?= e($case['case_source']) ?>" selected><?= e($case['case_source']) ?></option>
                    <?php endif; ?>
                </select>
            </div>
        </div>
        <div class="form-row">
            <?php
            $pContact = $case['contact_person'] ?? (isset($prefillCustomer) && $prefillCustomer ? ($prefillCustomer['contact_person'] ?? '') : '');
            $pPhone = $case['customer_phone'] ?? (isset($prefillCustomer) && $prefillCustomer ? ($prefillCustomer['phone'] ?? '') : '');
            $pMobile = $case['customer_mobile'] ?? (isset($prefillCustomer) && $prefillCustomer ? ($prefillCustomer['mobile'] ?? '') : '');
            $pLine = $case['contact_line_id'] ?? (isset($prefillCustomer) && $prefillCustomer ? ($prefillCustomer['line_official'] ?? '') : '');
            ?>
            <div class="form-group">
                <label>聯絡人 *</label>
                <input type="text" name="contact_person" id="contactPersonInput" class="form-control" value="<?= e($pContact) ?>" placeholder="聯絡人姓名" required>
            </div>
            <div class="form-group">
                <label>市話</label>
                <input type="text" name="customer_phone" id="customerPhoneInput" class="form-control" value="<?= e($pPhone) ?>" placeholder="如 04-2222-3333">
            </div>
            <div class="form-group">
                <label>手機</label>
                <input type="text" name="customer_mobile" id="customerMobileInput" class="form-control" value="<?= e($pMobile) ?>" placeholder="如 0912-345-678">
            </div>
            <div class="form-group">
                <label>LINE ID</label>
                <input type="text" name="contact_line_id" id="contactLineInput" class="form-control" value="<?= e($pLine) ?>" placeholder="LINE ID">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>案別</label>
                <select name="case_type" class="form-control">
                    <option value="">請選擇</option>
                    <?php foreach (CaseModel::caseTypeOptions() as $v => $l): ?>
                    <option value="<?= $v ?>" <?= ($case['case_type'] ?? '') === $v ? 'selected' : '' ?>><?= e($l) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>案件進度</label>
                <?php
                $currentStatus = isset($case['status']) ? $case['status'] : 'tracking';
                $isBoss = Session::getUser() && Session::getUser()['role'] === 'boss';
                $approvalStatuses = array('closed', 'completed_pending', 'unpaid');
                ?>
                <select name="status" class="form-control" onchange="checkSalesNoteRequired()">
                    <?php foreach (CaseModel::progressOptions() as $v => $l):
                        $isProtected = in_array($v, $approvalStatuses) && $v !== $currentStatus && !$isBoss;
                    ?>
                    <option value="<?= $v ?>" <?= $currentStatus === $v ? 'selected' : '' ?> <?= $isProtected ? 'disabled' : '' ?>><?= e($l) ?><?= $isProtected ? '（需簽核）' : '' ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (in_array($currentStatus, $approvalStatuses) && !$isBoss): ?>
                <small class="text-muted">目前狀態需透過簽核流程變更</small>
                <?php endif; ?>
            </div>
            <div class="form-group">
                <label>狀態</label>
                <?php $defaultSubStatus = isset($case['sub_status']) ? $case['sub_status'] : '未指派'; ?>
                <select name="sub_status" id="subStatusSelect" class="form-control" data-original="<?= e($defaultSubStatus) ?>" onchange="toggleNewCustomerBtn();checkSalesNoteRequired()">
                    <option value="">請選擇</option>
                    <?php foreach (CaseModel::subStatusOptions() as $v => $l): ?>
                    <option value="<?= $v ?>" <?= $defaultSubStatus === $v ? 'selected' : '' ?>><?= e($l) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>是否已完工</label>
                <select name="is_completed" class="form-control">
                    <option value="0" <?= empty($case['is_completed']) ? 'selected' : '' ?>>未完工</option>
                    <option value="1" <?= !empty($case['is_completed']) ? 'selected' : '' ?>>已完工</option>
                </select>
            </div>
            <div class="form-group">
                <label>完工日期</label>
                <input type="date" name="completion_date" class="form-control" value="<?= e($case['completion_date'] ?? '') ?>">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>客戶需求</label>
                <select name="description" class="form-control">
                    <option value="">請選擇</option>
                    <?php if (isset($customerDemandOptions)): foreach ($customerDemandOptions as $opt): ?>
                    <option value="<?= e($opt['label']) ?>" <?= ($case['description'] ?? '') === $opt['label'] ? 'selected' : '' ?>><?= e($opt['label']) ?></option>
                    <?php endforeach; endif; ?>
                    <?php if (!empty($case['description']) && !in_array($case['description'], array_column($customerDemandOptions ?? array(), 'label'))): ?>
                    <option value="<?= e($case['description']) ?>" selected><?= e($case['description']) ?></option>
                    <?php endif; ?>
                </select>
            </div>
            <div class="form-group">
                <label>系統別</label>
                <select name="system_type" class="form-control">
                    <option value="">請選擇</option>
                    <?php if (isset($systemTypeOptions)): foreach ($systemTypeOptions as $opt): ?>
                    <option value="<?= e($opt['label']) ?>" <?= ($case['system_type'] ?? '') === $opt['label'] ? 'selected' : '' ?>><?= e($opt['label']) ?></option>
                    <?php endforeach; endif; ?>
                    <?php if (!empty($case['system_type']) && !in_array($case['system_type'], array_column($systemTypeOptions ?? array(), 'label'))): ?>
                    <option value="<?= e($case['system_type']) ?>" selected><?= e($case['system_type']) ?></option>
                    <?php endif; ?>
                </select>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>施工區域</label>
                <div style="display:flex;gap:8px">
                    <select id="constructionCounty" class="form-control" style="flex:1" onchange="updateDistricts()">
                        <option value="">選擇縣市</option>
                    </select>
                    <select id="constructionDistrict" class="form-control" style="flex:1" onchange="updateConstructionArea()">
                        <option value="">選擇鄉鎮區</option>
                    </select>
                </div>
                <input type="hidden" name="construction_area" id="constructionAreaHidden" value="<?= e($case['construction_area'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>施工地址</label>
                <input type="text" name="address" class="form-control" value="<?= e($case['address'] ?? (isset($prefillCustomer) && $prefillCustomer ? trim(($prefillCustomer['site_city'] ?: '') . ($prefillCustomer['site_district'] ?: '') . ($prefillCustomer['site_address'] ?: '')) : '')) ?>">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group" style="flex:2">
                <label>Mail</label>
                <input type="email" name="customer_email" class="form-control" value="<?= e($case['customer_email'] ?? '') ?>" placeholder="客戶 Email">
            </div>
            <div class="form-group" style="flex:2"></div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>聯絡地址
                    <label class="checkbox-label" style="display:inline-flex;margin-left:12px;font-weight:normal;font-size:.85rem">
                        <input type="checkbox" id="sameAsAddress" onchange="if(this.checked){document.querySelector('input[name=contact_address]').value=document.querySelector('input[name=address]').value}">
                        <span>同施工地址</span>
                    </label>
                </label>
                <input type="text" name="contact_address" class="form-control" value="<?= e($case['contact_address'] ?? '') ?>" placeholder="客戶公司地址或約定地點">
            </div>
            <div class="form-group"></div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>場勘日期</label>
                <input type="date" name="survey_date" class="form-control" value="<?= e($case['survey_date'] ?? '') ?>">
            </div>
            <div class="form-group" style="flex:0 0 130px">
                <label>場勘時間</label>
                <input type="time" name="survey_time" class="form-control" value="<?= e($case['survey_time'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>拜訪方式</label>
                <select name="visit_method" class="form-control">
                    <option value="">請選擇</option>
                    <option value="現場" <?= ($case['visit_method'] ?? '') === '現場' ? 'selected' : '' ?>>現場</option>
                    <option value="電話" <?= ($case['visit_method'] ?? '') === '電話' ? 'selected' : '' ?>>電話</option>
                    <option value="LINE" <?= ($case['visit_method'] ?? '') === 'LINE' ? 'selected' : '' ?>>LINE</option>
                    <option value="視訊" <?= ($case['visit_method'] ?? '') === '視訊' ? 'selected' : '' ?>>視訊</option>
                </select>
            </div>
            <div class="form-group">
                <label>承辦業務</label>
                <select name="sales_id" class="form-control">
                    <option value="">請選擇</option>
                    <?php foreach ($salesUsers as $u): ?>
                    <option value="<?= $u['id'] ?>" <?= ($case['sales_id'] ?? '') == $u['id'] ? 'selected' : '' ?>><?= e($u['real_name']) ?><?= !empty($u['is_active']) ? '' : '(離職)' ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>難易度 (業務填寫)</label>
                <select name="difficulty" class="form-control">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                    <option value="<?= $i ?>" <?= ($case['difficulty'] ?? 3) == $i ? 'selected' : '' ?>><?= $i ?> 星</option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="form-group">
                <label>難易度 (系統判別)</label>
                <input type="text" class="form-control" value="<?= ($case && !empty($case['system_difficulty'])) ? $case['system_difficulty'] . ' 星' : '尚未評估' ?>" readonly style="background:#f5f5f5;color:var(--gray-500)">
            </div>
            <div class="form-group">
                <label>預估工時 (小時)</label>
                <input type="number" name="estimated_hours" class="form-control" step="0.5" min="0" value="<?= e($case['estimated_hours'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>預估施工次數</label>
                <input type="number" name="total_visits" class="form-control" min="1" value="<?= e($case['total_visits'] ?? 1) ?>">
            </div>
            <div class="form-group">
                <label>最多施工人數</label>
                <input type="number" name="max_engineers" class="form-control" min="1" max="10" value="<?= e($case['max_engineers'] ?? 2) ?>">
            </div>
        </div>
        <div class="form-group">
            <label>備註</label>
            <textarea name="notes" class="form-control" rows="2"><?= e($case['notes'] ?? '') ?></textarea>
        </div>
        <div class="form-group">
            <label>業務備註 <small class="text-muted">（與業務行事曆雙向同步）</small><span id="salesNoteRequired" style="display:none;color:#dc3545;font-weight:bold;margin-left:8px;">填寫未成交原因</span></label>
            <textarea name="sales_note" id="salesNoteInput" class="form-control" rows="2" oninput="checkSalesNoteRequired()"><?= e($case['sales_note'] ?? '') ?></textarea>
        </div>

        <!-- 登記人 -->
        <div class="form-row">
            <div class="form-group" style="flex:0 0 140px">
                <label>登記人</label>
                <?php
                $regName = '';
                if ($case && !empty($case['registrar'])) {
                    $regName = $case['registrar'];
                } else {
                    $u = Auth::user();
                    $regName = $u ? $u['real_name'] : '';
                }
                ?>
                <input type="text" class="form-control" value="<?= e($regName) ?>" readonly style="background:#f5f5f5;color:#666">
                <input type="hidden" name="registrar" value="<?= e($regName) ?>">
                <small class="text-muted"><?= $case && !empty($case['created_at']) ? date('Y/m/d H:i', strtotime($case['created_at'])) : date('Y/m/d H:i') ?></small>
            </div>
        </div>

        <!-- 聯絡人（內嵌基本資料） -->
        <div style="border-top:1px solid var(--gray-200);padding-top:12px;margin-top:8px">
            <div class="d-flex justify-between align-center mb-1">
                <label style="font-weight:600;margin:0">聯絡人</label>
                <button type="button" class="btn btn-outline btn-sm" onclick="addContact()">+ 新增聯絡人</button>
            </div>
            <div id="contactsContainer">
                <?php
                $contacts = array();
                if ($case && !empty($case['contacts'])) {
                    $contacts = $case['contacts'];
                } elseif (isset($prefillCustomer) && $prefillCustomer && !empty($prefillCustomer['contacts'])) {
                    foreach ($prefillCustomer['contacts'] as $pc) {
                        $contacts[] = array(
                            'contact_name' => $pc['contact_name'] ?: '',
                            'contact_phone' => $pc['phone'] ?: '',
                            'contact_role' => $pc['role'] ?: ''
                        );
                    }
                }
                foreach ($contacts as $idx => $c):
                ?>
                <div class="contact-row" data-index="<?= $idx ?>">
                    <div class="form-row">
                        <div class="form-group">
                            <label>姓名</label>
                            <input type="text" name="contacts[<?= $idx ?>][contact_name]" class="form-control" value="<?= e($c['contact_name']) ?>">
                        </div>
                        <div class="form-group">
                            <label>電話</label>
                            <input type="text" name="contacts[<?= $idx ?>][contact_phone]" class="form-control" value="<?= e($c['contact_phone'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label>角色</label>
                            <input type="text" name="contacts[<?= $idx ?>][contact_role]" class="form-control" value="<?= e($c['contact_role'] ?? '') ?>" placeholder="屋主/管委會/工地主任">
                        </div>
                        <div class="form-group" style="align-self:flex-end">
                            <button type="button" class="btn btn-danger btn-sm" onclick="this.closest('.contact-row').remove()">刪除</button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <?php if ($case && !empty($case['address'])): ?>
        <div class="form-group">
            <label>地圖</label>
            <iframe src="https://maps.google.com/maps?q=<?= urlencode($case['address']) ?>&output=embed&hl=zh-TW" style="width:100%;max-width:560px;height:200px;border:1px solid var(--gray-200);border-radius:6px" allowfullscreen loading="lazy"></iframe>
        </div>
        <?php endif; ?>
    </div>

    <!-- 報價單與圖面 -->
    <?php if ($case): ?>
    <?php if (in_array($case['case_type'] ?? '', array('repair','old_repair','new_repair')) || !empty($case['repair_report_date']) || !empty($case['repair_fault_reason'])): ?>
    <div class="card" id="sec-repair-info">
        <div class="card-header"><span>維修案資料</span></div>
        <div class="card-body">
            <div class="form-row">
                <div class="form-group">
                    <label>維修申告日期</label>
                    <input type="date" name="repair_report_date" class="form-control" value="<?= e($case['repair_report_date'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>維修結果</label>
                    <select name="repair_result" class="form-control">
                        <option value="">請選擇</option>
                        <?php foreach (array('其他問題','已維修完成','無法維修','待料中','需再訪') as $rr): ?>
                        <option value="<?= $rr ?>" <?= ($case['repair_result'] ?? '') === $rr ? 'selected' : '' ?>><?= $rr ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>有無收費</label>
                    <select name="repair_is_charged" class="form-control">
                        <option value="">請選擇</option>
                        <?php foreach (array('有收費','無收費','做服務不收費') as $rc): ?>
                        <option value="<?= $rc ?>" <?= ($case['repair_is_charged'] ?? '') === $rc ? 'selected' : '' ?>><?= $rc ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group" style="flex:2">
                    <label>客戶申告故障原因</label>
                    <textarea name="repair_fault_reason" class="form-control" rows="2"><?= e($case['repair_fault_reason'] ?? '') ?></textarea>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>是否由業務報價</label>
                    <select name="repair_by_sales" class="form-control">
                        <option value="">請選擇</option>
                        <option value="1" <?= ($case['repair_by_sales'] ?? '') == '1' ? 'selected' : '' ?>>業務報價</option>
                        <option value="0" <?= isset($case['repair_by_sales']) && $case['repair_by_sales'] === '0' ? 'selected' : '' ?>>非業務報價</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>維修器材</label>
                    <input type="text" name="repair_equipment" class="form-control" value="<?= e($case['repair_equipment'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>維修人員</label>
                    <input type="text" name="repair_staff" class="form-control" value="<?= e($case['repair_staff'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>點工人員</label>
                    <input type="text" name="repair_helper" class="form-control" value="<?= e($case['repair_helper'] ?? '') ?>">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group" style="flex:2">
                    <label>維修完成說明</label>
                    <textarea name="repair_description" class="form-control" rows="2"><?= e($case['repair_description'] ?? '') ?></textarea>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>原案件客戶編號</label>
                    <input type="text" name="repair_original_case" class="form-control" value="<?= e($case['repair_original_case'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>原案件完工日期</label>
                    <input type="date" name="repair_original_complete_date" class="form-control" value="<?= e($case['repair_original_complete_date'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>原案件保固日期</label>
                    <input type="date" name="repair_original_warranty_date" class="form-control" value="<?= e($case['repair_original_warranty_date'] ?? '') ?>">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>不收費原因</label>
                    <input type="text" name="repair_no_charge_reason" class="form-control" value="<?= e($case['repair_no_charge_reason'] ?? '') ?>">
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php
    // 預先查詢報價單，決定按鈕顯示
    $caseQuotes = array();
    try {
        $qStmt = Database::getInstance()->prepare("SELECT id, quotation_number AS quote_number, customer_name, total_amount, status, created_at FROM quotations WHERE case_id = ? ORDER BY created_at DESC");
        $qStmt->execute(array($case['id']));
        $caseQuotes = $qStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {}
    $latestQuote = !empty($caseQuotes) ? $caseQuotes[0] : null;
    $_qStatusMap = array('draft'=>'草稿','pending_approval'=>'待簽核','approved'=>'已核准','rejected_internal'=>'退回修改','sent'=>'已送客戶','accepted'=>'已接受','rejected'=>'已拒絕','customer_accepted'=>'客戶已接受','customer_rejected'=>'客戶已拒絕','revision_needed'=>'待修改');
    $_qBadgeMap = array('draft'=>'warning','pending_approval'=>'info','approved'=>'primary','rejected_internal'=>'danger','sent'=>'primary','accepted'=>'success','rejected'=>'danger','customer_accepted'=>'success','customer_rejected'=>'danger','revision_needed'=>'warning');
    ?>
    <div class="card" id="sec-quote-drawing">
        <div class="card-header d-flex justify-between align-center">
            <span>報價單 / 圖面</span>
            <?php if (Auth::hasPermission('quotations.manage') || Auth::hasPermission('quotations.view')): ?>
                <?php if (!$latestQuote): ?>
                <a href="/quotations.php?action=create&case_id=<?= $case['id'] ?>&customer_id=<?= urlencode($case['customer_id'] ?? '') ?>&customer_name=<?= urlencode($case['customer_name'] ?? $case['title'] ?? '') ?>&address=<?= urlencode($case['address'] ?? '') ?>&contact=<?= urlencode($case['contact_person'] ?? '') ?>&phone=<?= urlencode(!empty($case['customer_mobile']) ? $case['customer_mobile'] : ($case['customer_phone'] ?? '')) ?>"
                   class="btn btn-primary btn-sm">+ 建立報價單</a>
                <?php elseif ($latestQuote['status'] === 'customer_accepted'): ?>
                <a href="/quotations.php?action=view&id=<?= $latestQuote['id'] ?>"
                   class="btn btn-outline btn-sm">檢視報價單</a>
                <?php else: ?>
                <a href="/quotations.php?action=edit&id=<?= $latestQuote['id'] ?>"
                   class="btn btn-primary btn-sm">編輯報價單</a>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <!-- 已關聯的報價單 -->
        <?php if (!empty($caseQuotes)): ?>
        <div class="table-responsive mb-2">
            <table class="table" style="font-size:.85rem">
                <thead><tr><th>報價單號</th><th>客戶</th><th>金額</th><th>狀態</th><th>日期</th><th>操作</th></tr></thead>
                <tbody>
                <?php foreach ($caseQuotes as $q): ?>
                <tr>
                    <td><a href="/quotations.php?action=view&id=<?= $q['id'] ?>"><?= e($q['quote_number'] ?: "Q-{$q['id']}") ?></a></td>
                    <td><?= e($q['customer_name']) ?></td>
                    <td>$<?= number_format($q['total_amount'] ?? 0) ?></td>
                    <td><span class="badge badge-<?= isset($_qBadgeMap[$q['status']]) ? $_qBadgeMap[$q['status']] : '' ?>"><?= e(isset($_qStatusMap[$q['status']]) ? $_qStatusMap[$q['status']] : $q['status']) ?></span></td>
                    <td><?= e(substr($q['created_at'], 0, 10)) ?></td>
                    <td><a href="/quotations.php?action=view&id=<?= $q['id'] ?>" class="btn btn-sm btn-outline">查看</a></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <p class="text-muted" style="font-size:.85rem">尚無報價單，點右上方按鈕建立</p>
        <?php endif; ?>

        <!-- 圖面管理（預留 AI 手繪轉正式圖面功能） -->
        <div style="border-top:1px solid var(--gray-200);padding-top:12px;margin-top:8px">
            <div class="d-flex justify-between align-center mb-1">
                <strong style="font-size:.9rem">📐 圖面管理</strong>
                <label class="atc-add-btn" style="display:inline-flex;padding:6px 14px;font-size:.85rem">
                    <input type="file" name="drawing_files[]" style="display:none" multiple accept="image/*,.pdf,.dwg,.dxf" onchange="previewDrawings(this)">
                    + 上傳圖面
                </label>
            </div>
            <?php
            $drawings = array();
            if (!empty($case['attachments'])) {
                foreach ($case['attachments'] as $att) {
                    if ($att['file_type'] === 'blueprint') $drawings[] = $att;
                }
            }
            ?>
            <?php if (!empty($drawings)): ?>
            <div class="drawing-grid">
                <?php foreach ($drawings as $d):
                    $ext = strtolower(pathinfo($d['file_name'], PATHINFO_EXTENSION));
                    $isImg = in_array($ext, array('jpg','jpeg','png','gif','webp'));
                ?>
                <div class="drawing-item">
                    <?php if ($isImg): ?>
                    <img src="/<?= e($d['file_path']) ?>" class="drawing-thumb" onclick="openLightbox('/<?= e($d['file_path']) ?>')">
                    <?php else: ?>
                    <div class="drawing-thumb drawing-file">📄 <?= strtoupper($ext) ?></div>
                    <?php endif; ?>
                    <div class="drawing-name"><?= e($d['file_name']) ?></div>
                    <!-- AI 轉換按鈕（預留） -->
                    <div class="drawing-actions">
                        <a href="/<?= e($d['file_path']) ?>" target="_blank" class="btn btn-sm btn-outline" style="font-size:.75rem">下載</a>
                        <button type="button" class="btn btn-sm btn-outline" style="font-size:.75rem;opacity:.4;cursor:not-allowed" title="AI 圖面轉換（即將推出）">🤖 AI 轉換</button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <p class="text-muted" style="font-size:.8rem">尚無圖面，可上傳手繪圖或施工圖</p>
            <?php endif; ?>
        </div>
    </div>
    <!-- CSS moved to /css/cases-form.css -->
    <?php endif; ?>

    <!-- 帳務資訊 -->
    <div class="card <?= $canEdit['finance'] ? '' : 'section-readonly' ?>" id="sec-finance">
        <div class="card-header">帳務資訊</div>
        <div class="form-row">
            <div class="form-group">
                <label>報價金額</label>
                <input type="number" name="quote_amount" class="form-control" min="0" value="<?= e($case['quote_amount'] ?? '') ?>" placeholder="元">
            </div>
            <div class="form-group">
                <label>成交金額 (未稅)</label>
                <input type="number" name="deal_amount" class="form-control" min="0" value="<?= e($case['deal_amount'] ?? '') ?>" placeholder="元">
            </div>
            <div class="form-group">
                <label>成交日期</label>
                <input type="date" name="deal_date" class="form-control" value="<?= e($case['deal_date'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>是否含稅 <span class="tax-required-mark" style="<?= !empty($case['deal_amount']) ? '' : 'display:none' ?>">*</span></label>
                <select name="is_tax_included" id="isTaxIncluded" class="form-control" <?= !empty($case['deal_amount']) ? 'required' : '' ?>>
                    <option value="">請選擇</option>
                    <option value="含稅(需開發票)" <?= ($case['is_tax_included'] ?? '') === '含稅(需開發票)' ? 'selected' : '' ?>>含稅(需開發票)</option>
                    <option value="未稅(不開發票)" <?= ($case['is_tax_included'] ?? '') === '未稅(不開發票)' ? 'selected' : '' ?>>未稅(不開發票)</option>
                    <option value="含稅(免開發票)" <?= ($case['is_tax_included'] ?? '') === '含稅(免開發票)' ? 'selected' : '' ?>>含稅(免開發票)</option>
                </select>
            </div>
        </div>
        <div class="form-row" id="taxRow" style="<?= ($case['is_tax_included'] ?? '') === '未稅(不開發票)' ? 'display:none' : '' ?>">
            <div class="form-group">
                <label>稅金</label>
                <input type="text" name="tax_amount" class="form-control" value="<?= !empty($case['tax_amount']) ? number_format($case['tax_amount']) : '' ?>" placeholder="元">
            </div>
            <div class="form-group">
                <label>含稅金額</label>
                <input type="text" name="total_amount" class="form-control" value="<?= !empty($case['total_amount']) ? number_format($case['total_amount']) : '' ?>" placeholder="元">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>訂金金額 <small style="color:#999">(由交易紀錄帶入)</small></label>
                <input type="number" name="deposit_amount" class="form-control" value="<?= e($case['deposit_amount'] ?? '') ?>" readonly style="background:#f5f5f5">
            </div>
            <div class="form-group">
                <label>訂金支付方式</label>
                <input type="text" name="deposit_method" class="form-control" value="<?= e($case['deposit_method'] ?? '') ?>" readonly style="background:#f5f5f5">
            </div>
            <div class="form-group">
                <label>訂金付款日</label>
                <input type="text" name="deposit_payment_date" class="form-control" value="<?= e($case['deposit_payment_date'] ?? '') ?>" readonly style="background:#f5f5f5">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>尾款 <small style="color:#999">(自動計算)</small></label>
                <input type="text" name="balance_amount" id="balanceInput" class="form-control" value="<?= !empty($case['balance_amount']) ? number_format($case['balance_amount']) : '' ?>" readonly style="background:#f5f5f5">
            </div>
            <?php
            // 折讓/匯費：合計案件帳款交易中的 wire_fee
            $_caseWireTotal = 0;
            if (!empty($case['case_payments'])) {
                foreach ($case['case_payments'] as $_cp) {
                    $_caseWireTotal += isset($_cp['wire_fee']) ? (float)$_cp['wire_fee'] : 0;
                }
            }
            ?>
            <div class="form-group">
                <label>折讓/匯費 <small style="color:#999">(交易合計)</small></label>
                <input type="text" id="wireFeeTotalDisplay" class="form-control" value="<?= $_caseWireTotal > 0 ? number_format($_caseWireTotal) : '' ?>" readonly style="background:#f5f5f5">
            </div>
            <div class="form-group">
                <label>完工金額 (含稅)</label>
                <input type="number" name="completion_amount" class="form-control" value="<?= e($case['completion_amount'] ?? '') ?>" readonly style="background:#f5f5f5">
            </div>
            <div class="form-group">
                <label>總收款金額 <small style="color:#999">(由交易紀錄帶入)</small></label>
                <input type="number" name="total_collected" id="totalCollectedDisplay" class="form-control" value="<?= e($case['total_collected'] ?? '') ?>" readonly style="background:#f5f5f5">
            </div>
        </div>
        <div class="form-row">
            <?php
            // 開放手動修改結清狀態/日期的條件：尾款=0 且 有實際金額流動（成交金額或總收款其中一個 > 0）
            $_balance   = isset($case['balance_amount']) ? (int)$case['balance_amount'] : null;
            $_deal      = isset($case['deal_amount']) ? (int)$case['deal_amount'] : 0;
            $_totalAmt  = isset($case['total_amount']) ? (int)$case['total_amount'] : 0;
            $_collected = isset($case['total_collected']) ? (int)$case['total_collected'] : 0;
            $_noAmount  = ($_deal <= 0 && $_totalAmt <= 0 && $_collected <= 0);
            $_lockSettle = ($_balance === null || $_balance > 0 || $_noAmount);
            $_lockTip = $_noAmount ? '尚無成交金額與收款，無需結清' : ($_lockSettle ? '尾款未歸零，由系統自動管理' : '');
            ?>
            <div class="form-group">
                <label>帳款是否結清<?= $_lockSettle ? ' <span style="color:var(--gray-400);font-weight:400;font-size:.8rem">（唯讀）</span>' : '' ?></label>
                <select name="settlement_confirmed" class="form-control" <?= $_lockSettle ? 'disabled' : '' ?> title="<?= e($_lockTip) ?>">
                    <?php
                    $settleVal = isset($case['settlement_confirmed']) ? $case['settlement_confirmed'] : '';
                    $isZero = ($settleVal !== '' && $settleVal !== null && (int)$settleVal === 0);
                    $isOne  = ($settleVal !== '' && $settleVal !== null && (int)$settleVal === 1);
                    ?>
                    <option value="">請選擇</option>
                    <option value="0" <?= $isZero ? 'selected' : '' ?>>未結清</option>
                    <option value="1" <?= $isOne ? 'selected' : '' ?>>已結清</option>
                </select>
                <?php if ($_lockSettle): ?>
                <input type="hidden" name="settlement_confirmed" value="<?= e($settleVal) ?>">
                <?php endif; ?>
            </div>
            <div class="form-group">
                <label>帳款結清日期<?= $_lockSettle ? ' <span style="color:var(--gray-400);font-weight:400;font-size:.8rem">（唯讀）</span>' : '' ?></label>
                <input type="date" name="settlement_date" class="form-control" value="<?= e($case['settlement_date'] ?? '') ?>" <?= $_lockSettle ? 'readonly style="background:#f5f5f5"' : '' ?> title="<?= e($_lockTip) ?>">
            </div>
            <div class="form-group"></div>
        </div>
        <?php if ($case && !empty($case['payments'])): ?>
        <div class="mt-1" style="border-top:1px solid var(--gray-200);padding-top:12px">
            <label style="font-weight:600;font-size:.9rem">收款記錄</label>
            <div class="table-responsive">
                <table class="table" style="font-size:.85rem">
                    <thead><tr><th>類型</th><th>方式</th><th>金額</th><th>日期</th></tr></thead>
                    <tbody>
                    <?php
                    $paymentTypes = array('deposit'=>'訂金','final_payment'=>'尾款');
                    $paymentMethods = array('cash'=>'現金','transfer'=>'匯款','check'=>'支票');
                    foreach ($case['payments'] as $p): ?>
                    <tr>
                        <td><?= isset($paymentTypes[$p['payment_type']]) ? $paymentTypes[$p['payment_type']] : $p['payment_type'] ?></td>
                        <td><?= isset($paymentMethods[$p['payment_method']]) ? $paymentMethods[$p['payment_method']] : $p['payment_method'] ?></td>
                        <td>$<?= number_format($p['amount']) ?></td>
                        <td><?= format_date($p['payment_date']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- 帳款交易紀錄 -->
    <?php if ($case):
        $casePayments = $case['case_payments'] ?? array();
        // 編輯/刪除權限：只有 boss（系統管理者）可以修改或刪除已存的交易
        // 新增權限：維持 finance section 編輯權限（業務/行政/助理都能新增）
        $_pmtUser = Auth::user();
        // 編輯/刪除已存帳款：boss / 會計主管
        $_canEditPayment = $_pmtUser && in_array($_pmtUser['role'], array('boss','accounting_supervisor'), true);
        // 匯費填寫權限：boss / 會計主管 / 會計人員
        $_canEditWireFee = $_pmtUser && in_array($_pmtUser['role'], array('boss','accounting_supervisor','accountant'), true);
    ?>
    <div class="card" id="sec-case-payments">
        <div class="card-header d-flex justify-between align-center">
            <span>帳款交易紀錄
                <?php if (!$_canEditPayment): ?>
                <small style="color:#888;font-weight:normal">（存檔後僅系統管理者可修改/刪除）</small>
                <?php endif; ?>
            </span>
            <?php if ($canEdit['finance']): ?>
            <button type="button" class="btn btn-primary btn-sm" onclick="togglePaymentForm()">+ 新增交易</button>
            <?php endif; ?>
        </div>

        <?php if ($canEdit['finance']): ?>
        <div id="payment-add-form" style="display:none;padding:12px;border-bottom:1px solid var(--gray-200);background:#fafafa">
            <div class="form-row">
                <div class="form-group">
                    <label>交易日期 *</label>
                    <input type="date" max="2099-12-31" id="pay_date" class="form-control" value="<?= date('Y-m-d') ?>">
                </div>
                <div class="form-group">
                    <label>帳款類別</label>
                    <select id="pay_type" class="form-control">
                        <option value="訂金">訂金</option>
                        <option value="第一期款">第一期款</option>
                        <option value="第二期款">第二期款</option>
                        <option value="第三期款">第三期款</option>
                        <option value="尾款">尾款</option>
                        <option value="保留款">保留款</option>
                        <option value="全款">全款</option>
                        <option value="退款">退款</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>交易方式</label>
                    <select id="pay_method" class="form-control">
                        <option value="匯款">匯款</option>
                        <option value="現金">現金</option>
                        <option value="支票">支票</option>
                        <option value="轉帳">轉帳</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>未稅金額</label>
                    <input type="number" id="pay_untaxed_amount" class="form-control" placeholder="0" oninput="onPayUntaxedChange()">
                </div>
                <div class="form-group">
                    <label>稅額（5% 自動）</label>
                    <input type="number" id="pay_tax_amount" class="form-control" placeholder="0" oninput="onPayTaxChange()">
                </div>
                <div class="form-group">
                    <label>總金額 *</label>
                    <input type="number" id="pay_amount" class="form-control" placeholder="0">
                </div>
                <div class="form-group">
                    <label>收款單號</label>
                    <input type="text" id="pay_receipt_number" class="form-control" placeholder="S2-...">
                </div>
            </div>
            <div class="form-row">
                <?php if ($_canEditWireFee): ?>
                <div class="form-group">
                    <label>折讓/匯費 <small style="color:#888">(扣除後計尾款)</small></label>
                    <input type="number" id="pay_wire_fee" class="form-control" placeholder="0" min="0" step="1">
                </div>
                <?php endif; ?>
                <div class="form-group" style="flex:2">
                    <label>備註</label>
                    <input type="text" id="pay_note" class="form-control" placeholder="選填">
                </div>
                <div class="form-group">
                    <label>憑證圖片</label>
                    <input type="file" id="pay_image" accept="image/*" class="form-control" multiple>
                </div>
                <div class="form-group" style="align-self:flex-end">
                    <button type="button" class="btn btn-primary btn-sm" onclick="saveCasePayment()">儲存</button>
                    <button type="button" class="btn btn-outline btn-sm" onclick="togglePaymentForm()">取消</button>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if (empty($casePayments)): ?>
        <p class="text-muted text-center" style="padding:20px">目前無帳款交易紀錄</p>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table" style="font-size:.9rem">
                <thead><tr><th style="width:100px">日期</th><th style="width:60px">類別</th><th style="width:80px">方式</th><th class="text-right" style="width:90px">未稅</th><th class="text-right" style="width:70px">稅額</th><th class="text-right" style="width:90px">總金額</th><th class="text-right" style="width:80px">折讓/匯費</th><th style="width:120px">收款單號</th><th>備註</th><th style="width:50px">憑證</th><?php if ($_canEditPayment): ?><th style="width:60px">操作</th><?php endif; ?></tr></thead>
                <tbody>
                    <?php $payTotal = 0; $payUntaxedTotal = 0; $payTaxTotal = 0; $payWireTotal = 0; foreach ($casePayments as $cp): $payTotal += $cp['amount']; $payUntaxedTotal += isset($cp['untaxed_amount']) ? $cp['untaxed_amount'] : 0; $payTaxTotal += isset($cp['tax_amount']) ? $cp['tax_amount'] : 0; $payWireTotal += isset($cp['wire_fee']) ? $cp['wire_fee'] : 0; ?>
                    <tr style="cursor:pointer" onclick="openPaymentDetail(<?= $cp['id'] ?>)">
                        <td><?= e($cp['payment_date']) ?></td>
                        <td><span class="badge"><?= e($cp['payment_type'] ?: '-') ?></span></td>
                        <td><?= e($cp['transaction_type'] ?: '-') ?></td>
                        <td class="text-right"><?= !empty($cp['untaxed_amount']) ? '$' . number_format($cp['untaxed_amount']) : '-' ?></td>
                        <td class="text-right"><?= !empty($cp['tax_amount']) ? '$' . number_format($cp['tax_amount']) : '-' ?></td>
                        <td class="text-right" style="font-weight:600">$<?= number_format($cp['amount']) ?></td>
                        <td class="text-right" style="color:<?= !empty($cp['wire_fee']) ? '#e65100' : '#999' ?>"><?= !empty($cp['wire_fee']) ? '-$' . number_format($cp['wire_fee']) : '-' ?></td>
                        <td style="font-size:.8rem;color:var(--primary)"><?= e($cp['receipt_number'] ?: '-') ?></td>
                        <td style="white-space:pre-line"><?= e($cp['note'] ?: '-') ?></td>
                        <td><?php
                            $cpImages = array();
                            if ($cp['image_path']) {
                                $decoded = json_decode($cp['image_path'], true);
                                $cpImages = is_array($decoded) ? $decoded : array($cp['image_path']);
                            }
                            if (!empty($cpImages)):
                                foreach ($cpImages as $cpImg): if (!$cpImg) continue;
                        ?><img src="/<?= e($cpImg) ?>" style="width:40px;height:40px;object-fit:cover;border-radius:4px;cursor:pointer;margin:1px" onclick="event.stopPropagation();openLightbox('/<?= e($cpImg) ?>')"><?php
                                endforeach;
                            else: ?>-<?php endif;
                        ?></td>
                        <?php if ($_canEditPayment): ?>
                        <td><button type="button" class="btn btn-outline btn-sm" style="color:var(--danger);font-size:.75rem" onclick="event.stopPropagation();deleteCasePayment(<?= $cp['id'] ?>)">刪除</button></td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot><tr><td colspan="3" class="text-right"><strong>合計</strong></td><td class="text-right">$<?= number_format($payUntaxedTotal) ?></td><td class="text-right">$<?= number_format($payTaxTotal) ?></td><td class="text-right" style="font-weight:700;color:var(--primary)">$<?= number_format($payTotal) ?></td><td class="text-right" style="color:<?= $payWireTotal > 0 ? '#e65100' : '#999' ?>;font-weight:600"><?= $payWireTotal > 0 ? '-$' . number_format($payWireTotal) : '-' ?></td><td colspan="<?= $_canEditPayment ? '4' : '3' ?>"></td></tr></tfoot>
            </table>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- 請款流程 -->
    <?php if ($case): ?>
    <div class="card" id="sec-billing-flow">
        <div class="card-header d-flex justify-between align-center">
            <span>請款流程</span>
            <?php if ($canEdit['finance'] ?? false): ?>
            <button type="button" class="btn btn-primary btn-sm" onclick="addBillingItem()">+ 新增</button>
            <?php endif; ?>
        </div>
        <?php $billingItems = isset($case['billing_items']) ? $case['billing_items'] : array(); ?>
        <?php if (empty($billingItems)): ?>
        <p class="text-muted text-center" style="padding:20px">尚未建立請款流程</p>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead><tr>
                    <th style="width:40px">#</th>
                    <th>帳款類別</th>
                    <th class="text-right">未稅</th>
                    <th class="text-right">稅金</th>
                    <th class="text-right">總金額</th>
                    <th>含稅</th>
                    <th>客戶通知可請款</th>
                    <th>客戶通知已付款</th>
                    <th>已請款</th>
                    <th>發票號碼</th>
                    <th>備註</th>
                    <th style="width:60px">憑證</th>
                    <?php if ($canEdit['finance'] ?? false): ?><th style="width:60px">操作</th><?php endif; ?>
                </tr></thead>
                <tbody>
                <?php foreach ($billingItems as $bi): ?>
                <tr>
                    <td><?= (int)$bi['seq_no'] ?></td>
                    <td><?= e($bi['payment_category']) ?></td>
                    <td class="text-right"><?= $bi['amount_untaxed'] !== null ? '$' . number_format($bi['amount_untaxed']) : '-' ?></td>
                    <td class="text-right"><?= $bi['tax_amount'] !== null ? '$' . number_format($bi['tax_amount']) : '-' ?></td>
                    <td class="text-right" style="font-weight:600">$<?= number_format($bi['total_amount']) ?></td>
                    <td><?= $bi['tax_included'] ? '含稅' : '未稅' ?></td>
                    <td><?= $bi['customer_billable'] ? '<span style="color:#2e7d32">✓</span>' : '-' ?></td>
                    <td><?= $bi['customer_paid'] ? '<span style="color:#2e7d32">✓</span>' : '-' ?><?= !empty($bi['customer_paid_info']) ? '<br><small class="text-muted">' . e($bi['customer_paid_info']) . '</small>' : '' ?></td>
                    <td><?= $bi['is_billed'] ? '<span style="color:#1565c0">✓</span>' : '-' ?><?= !empty($bi['billed_info']) ? '<br><small class="text-muted">' . e($bi['billed_info']) . '</small>' : '' ?></td>
                    <td><?= e($bi['invoice_number'] ?: '-') ?></td>
                    <td><?= e($bi['note'] ?: '-') ?></td>
                    <td><?php if (!empty($bi['attachment_path'])): ?><a href="/<?= e($bi['attachment_path']) ?>" target="_blank" onclick="event.stopPropagation();hsOpenFile('/<?= e($bi['attachment_path']) ?>','憑證')" style="font-size:.8rem">📎 檢視</a><?php else: ?>-<?php endif; ?></td>
                    <?php if ($canEdit['finance'] ?? false): ?>
                    <td>
                        <button type="button" class="btn btn-outline btn-sm" onclick="editBillingItem(<?= htmlspecialchars(json_encode($bi), ENT_QUOTES) ?>)" style="font-size:.75rem">編輯</button>
                        <button type="button" class="btn btn-danger btn-sm" onclick="deleteBillingItem(<?= (int)$bi['id'] ?>)" style="font-size:.75rem">刪除</button>
                    </td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- 金額異動紀錄 -->
    <?php if ($case):
        $amtChanges = isset($case['amount_changes']) ? $case['amount_changes'] : array();
        $amtFieldLabels = array(
            'deal_amount'     => '成交金額',
            'total_amount'    => '含稅金額',
            'tax_amount'      => '稅金',
            'total_collected' => '總收款',
            'balance_amount'  => '尾款',
        );
        $amtSourceLabels = array(
            'manual_edit'            => '手動編輯',
            'payment_add'            => '新增收款',
            'payment_edit'           => '編輯收款',
            'payment_delete'         => '刪除收款',
            'worklog_payment'        => '施工回報收款',
            'worklog_payment_cancel' => '施工回報取消收款',
            'manual_fix'             => '系統修正',
        );
    ?>
    <div class="card" id="sec-amount-changes">
        <div class="card-header">金額異動紀錄</div>
        <?php if (empty($amtChanges)): ?>
        <p class="text-muted text-center" style="padding:20px">尚無金額異動紀錄</p>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table" style="font-size:.85rem">
                <thead><tr>
                    <th style="width:140px">異動日期</th>
                    <th style="width:80px">欄位</th>
                    <th class="text-right" style="width:110px">原金額</th>
                    <th class="text-right" style="width:110px">新金額</th>
                    <th style="width:90px">異動來源</th>
                    <th style="width:80px">異動人</th>
                </tr></thead>
                <tbody>
                <?php foreach ($amtChanges as $ac): ?>
                <tr>
                    <td><?= e(substr($ac['created_at'], 0, 16)) ?></td>
                    <td><?= isset($amtFieldLabels[$ac['field_name']]) ? $amtFieldLabels[$ac['field_name']] : e($ac['field_name']) ?></td>
                    <td class="text-right">$<?= number_format($ac['old_value']) ?></td>
                    <td class="text-right" style="font-weight:600;color:<?= $ac['new_value'] > $ac['old_value'] ? '#2e7d32' : '#c62828' ?>">$<?= number_format($ac['new_value']) ?></td>
                    <td><span class="badge"><?= isset($amtSourceLabels[$ac['change_source']]) ? $amtSourceLabels[$ac['change_source']] : e($ac['change_source']) ?></span></td>
                    <td><?= $ac['changed_by_name'] === 'system' ? '系統' : e($ac['changed_by_name']) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- 請款資訊 -->
    <div class="card <?= ($canEdit['finance'] ?? false) ? '' : 'section-readonly' ?>" id="sec-billing-info">
        <div class="card-header">請款資訊</div>
        <div class="form-row">
            <div class="form-group" style="flex:2">
                <label>發票抬頭</label>
                <input type="text" name="billing_title" class="form-control" value="<?= e($case['billing_title'] ?? '') ?>" placeholder="公司全名">
            </div>
            <div class="form-group">
                <label>統一編號</label>
                <input type="text" name="billing_tax_id" id="billingTaxIdInput" class="form-control" value="<?= e($case['billing_tax_id'] ?? '') ?>" placeholder="8碼統編">
            </div>
        </div>

        <!-- 完工 3 關簽核 timeline + 我的簽核 -->
        <?php if ($case):
            require_once __DIR__ . '/../../modules/approvals/ApprovalModel.php';
            $_compApp = new ApprovalModel();
            $_compTimeline = $_compApp->getCaseCompletionTimeline($case['id']);
            $_myFlow = $_compApp->getMyPendingCompletionFlow($case['id'], Auth::id());
            $_levelLabels = array(1 => '工程主管', 2 => '行政人員', 3 => '會計人員');
            // 自動帶值: 有無收款預設
            $_autoHasPayment = ((float)($case['total_collected'] ?? 0) > 0) ? 1 : 0;
            $_balance = (int)($case['balance_amount'] ?? 0);
        ?>
        <?php if (!empty($_compTimeline) || $_myFlow): ?>
        <div style="margin:8px 0;padding:12px;background:#e3f2fd;border:1px solid #1565c0;border-radius:6px">
            <div style="font-weight:600;margin-bottom:8px">📋 完工簽核流程</div>
            <?php if (!empty($_compTimeline)): ?>
            <table style="width:100%;font-size:.82rem;margin-bottom:8px">
                <thead>
                    <tr style="background:rgba(255,255,255,.5)">
                        <th style="padding:4px 8px;text-align:left;width:90px">關卡</th>
                        <th style="padding:4px 8px;text-align:left">簽核人</th>
                        <th style="padding:4px 8px;text-align:left;width:80px">狀態</th>
                        <th style="padding:4px 8px;text-align:left;width:140px">時間</th>
                        <th style="padding:4px 8px;text-align:left">備註 / 額外資料</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($_compTimeline as $f):
                        $payload = !empty($f['payload']) ? json_decode($f['payload'], true) : array();
                    ?>
                    <tr style="border-top:1px solid rgba(0,0,0,.08)">
                        <td style="padding:4px 8px">第<?= (int)$f['level_order'] ?>關 <?= e(isset($_levelLabels[$f['level_order']]) ? $_levelLabels[$f['level_order']] : '') ?></td>
                        <td style="padding:4px 8px"><?= e($f['approver_name'] ?? '-') ?></td>
                        <td style="padding:4px 8px">
                            <?php if ($f['status'] === 'pending'): ?><span style="color:#e65100">待簽核</span>
                            <?php elseif ($f['status'] === 'approved'): ?><span style="color:#2e7d32">✓ 已核准</span>
                            <?php elseif ($f['status'] === 'rejected'): ?><span style="color:#c62828">✗ 已駁回</span>
                            <?php elseif ($f['status'] === 'cancelled'): ?><span style="color:#999">已取消</span>
                            <?php else: ?><?= e($f['status']) ?><?php endif; ?>
                        </td>
                        <td style="padding:4px 8px"><?= e($f['decided_at'] ?? '-') ?></td>
                        <td style="padding:4px 8px;color:#666">
                            <?php if (!empty($payload['has_payment'])): ?>✓ 有收款<?php endif; ?>
                            <?php if (isset($payload['has_payment']) && empty($payload['has_payment'])): ?>✗ 無收款<?php endif; ?>
                            <?php if (!empty($payload['payment_received'])): ?> ✓ 款項已入帳<?php endif; ?>
                            <?php if (!empty($f['comment'])): ?> <?= e($f['comment']) ?><?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>

            <?php if ($_myFlow): ?>
            <div class="approval-zone" style="background:#fff;padding:10px;border-radius:4px;border:1px solid #1565c0">
                <strong>✋ 您要簽核：第 <?= (int)$_myFlow['level_order'] ?> 關 (<?= e($_levelLabels[$_myFlow['level_order']] ?? '') ?>)</strong>
                <div style="margin-top:8px">
                    <?php if ($_myFlow['level_order'] == 2): ?>
                    <!-- Level 2 行政人員：勾選有無收款 -->
                    <div style="margin:6px 0">
                        <label style="display:flex;align-items:center;gap:6px;cursor:pointer;pointer-events:auto !important">
                            <input type="checkbox" id="compHasPayment" value="1" <?= $_autoHasPayment ? 'checked' : '' ?> style="pointer-events:auto !important">
                            <span>有收款（依目前總收款金額自動帶入：$<?= number_format((float)($case['total_collected'] ?? 0)) ?>，可手動勾消）</span>
                        </label>
                        <small style="color:#666">勾起 → 進入第 3 關（會計確認入帳）<br>不勾 → 案件直接進入「完工未收款」狀態</small>
                    </div>
                    <?php elseif ($_myFlow['level_order'] == 3): ?>
                    <!-- Level 3 會計人員：勾款項已入帳 -->
                    <div style="margin:6px 0">
                        <label style="display:flex;align-items:center;gap:6px;cursor:pointer;pointer-events:auto !important">
                            <input type="checkbox" id="compPaymentReceived" value="1" style="pointer-events:auto !important">
                            <span>款項已入帳（必勾才能結案）</span>
                        </label>
                        <?php if ($_balance !== 0): ?>
                        <div style="margin-top:6px;padding:8px;background:#ffebee;border:1px solid #c62828;border-radius:4px;font-size:.85rem;color:#c62828">
                            ⚠ 警告：尾款還有 <strong>$<?= number_format($_balance) ?></strong>，<strong>無法結案</strong>。請先到帳款交易紀錄補登收款，或請業務做折讓。
                        </div>
                        <?php else: ?>
                        <small style="color:#2e7d32">✓ 尾款 $0，可以結案</small>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <div style="margin-top:6px">
                        <input type="text" id="compApproveComment" class="form-control" placeholder="備註（選填）" style="font-size:.85rem;pointer-events:auto !important;background:#fff !important;color:#333 !important">
                    </div>
                    <div style="display:flex;gap:6px;margin-top:8px">
                        <button type="button" id="btnCompletionApprove" class="btn btn-success btn-sm" style="pointer-events:auto !important;opacity:1 !important" onclick="completionApproveSubmit()" <?= ($_myFlow['level_order'] == 3 && $_balance !== 0) ? 'disabled' : '' ?>>✓ 核准</button>
                        <button type="button" id="btnCompletionReject" class="btn btn-danger btn-sm" style="pointer-events:auto !important;opacity:1 !important" onclick="completionRejectShow(<?= (int)$_myFlow['id'] ?>, <?= (int)$case['id'] ?>)">✗ 駁回</button>
                    </div>
                </div>
                <script>
                function completionApproveSubmit() {
                    if (!confirm('確定核准？')) return;
                    <?php if ($_myFlow['level_order'] == 3): ?>
                    var prEl = document.getElementById('compPaymentReceived');
                    if (!prEl || !prEl.checked) { alert('請勾選「款項已入帳」才能核准'); return; }
                    <?php endif; ?>
                    // 鎖定按鈕 + 顯示處理中，避免重複點擊與讓使用者知道系統在運作
                    var btnA = document.getElementById('btnCompletionApprove');
                    var btnR = document.getElementById('btnCompletionReject');
                    if (btnA) { btnA.disabled = true; btnA.textContent = '處理中…'; btnA.style.opacity = '0.6'; }
                    if (btnR) { btnR.disabled = true; btnR.style.opacity = '0.6'; }
                    var f = document.createElement('form');
                    f.method = 'POST';
                    f.action = '/approvals.php?action=approve';
                    f.style.display = 'none';
                    var fields = {
                        'csrf_token': '<?= e(Session::getCsrfToken()) ?>',
                        'flow_id': '<?= (int)$_myFlow['id'] ?>',
                        'module': 'case_completion',
                        'target_id': '<?= (int)$case['id'] ?>',
                        'redirect': '/cases.php?action=edit&id=<?= (int)$case['id'] ?>#sec-billing',
                        'comment': document.getElementById('compApproveComment').value
                    };
                    <?php if ($_myFlow['level_order'] == 2): ?>
                    var hp = document.getElementById('compHasPayment');
                    if (hp && hp.checked) fields['has_payment'] = '1';
                    <?php endif; ?>
                    <?php if ($_myFlow['level_order'] == 3): ?>
                    fields['payment_received'] = '1';
                    <?php endif; ?>
                    for (var k in fields) {
                        var inp = document.createElement('input');
                        inp.type = 'hidden'; inp.name = k; inp.value = fields[k];
                        f.appendChild(inp);
                    }
                    document.body.appendChild(f);
                    f.submit();
                }
                </script>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; endif; ?>

        <!-- 完工駁回對話框 -->
        <?php if (!empty($_myFlow)): ?>
        <div id="completionRejectModal" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,.5);z-index:10000;align-items:center;justify-content:center;pointer-events:auto">
            <div style="background:#fff;border-radius:8px;padding:20px;max-width:480px;width:90%;pointer-events:auto">
                <h3 style="margin-top:0;color:#333">駁回完工簽核</h3>
                <input type="hidden" id="completionRejectFlowId">
                <input type="hidden" id="completionRejectCaseId">
                <input type="hidden" id="completionRejectRedirect">
                <div style="margin-bottom:12px">
                    <label style="font-size:.85rem;font-weight:600;color:#333">駁回原因</label>
                    <textarea id="completionRejectComment" class="form-control" rows="3" placeholder="請輸入駁回原因" style="pointer-events:auto !important;background:#fff !important;color:#333 !important"></textarea>
                </div>
                <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:16px">
                    <button type="button" class="btn btn-outline" onclick="document.getElementById('completionRejectModal').style.display='none'" style="min-width:80px;pointer-events:auto !important;opacity:1 !important">取消</button>
                    <button type="button" class="btn btn-danger" onclick="submitCompletionReject()" style="min-width:100px;pointer-events:auto !important;opacity:1 !important">確定駁回</button>
                </div>
            </div>
        </div>
        <script>
        // 把駁回 modal 搬到 body 下，脫離 section-readonly
        (function() {
            var m = document.getElementById('completionRejectModal');
            if (m) document.body.appendChild(m);
        })();
        function completionRejectShow(flowId, caseId) {
            document.getElementById('completionRejectFlowId').value = flowId;
            document.getElementById('completionRejectCaseId').value = caseId;
            document.getElementById('completionRejectRedirect').value = '/cases.php?action=edit&id=' + caseId + '#sec-billing';
            document.getElementById('completionRejectComment').value = '';
            document.getElementById('completionRejectModal').style.display = 'flex';
        }
        function submitCompletionReject() {
            var comment = document.getElementById('completionRejectComment').value.trim();
            if (!comment) { alert('請輸入駁回原因'); return; }
            var btn = document.querySelector('#completionRejectModal .btn-danger');
            btn.disabled = true; btn.textContent = '處理中...';
            var params = 'csrf_token=<?= urlencode(Session::getCsrfToken()) ?>'
                + '&flow_id=' + encodeURIComponent(document.getElementById('completionRejectFlowId').value)
                + '&module=case_completion'
                + '&target_id=' + encodeURIComponent(document.getElementById('completionRejectCaseId').value)
                + '&redirect=' + encodeURIComponent(document.getElementById('completionRejectRedirect').value)
                + '&comment=' + encodeURIComponent(comment);
            var xhr = new XMLHttpRequest();
            xhr.open('POST', '/approvals.php?action=reject');
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onload = function(){
                // 不管 server 回什麼，直接跳轉
                var redir = document.getElementById('completionRejectRedirect').value || '/approvals.php';
                window.location.href = redir;
            };
            xhr.onerror = function(){
                window.location.href = document.getElementById('completionRejectRedirect').value || '/approvals.php';
            };
            xhr.send(params);
        }
        </script>
        <?php endif; ?>

        <!-- 無訂金排工簽核 -->
        <?php if ($case):
            $noDepStatus = $case['no_deposit_approval_status'] ?? null;
            $depAmt = (float)($case['deposit_amount'] ?? 0);
            if ($depAmt <= 0):
                // 檢查是否需要簽核
                require_once __DIR__ . '/../../modules/approvals/ApprovalModel.php';
                $_noDepApp = new ApprovalModel();
                $_noDepNeeds = $_noDepApp->checkNoDepositNeedsApproval($case['id']);
        ?>
        <div style="margin:8px 0;padding:10px;background:#fff8e1;border:1px solid #ffc107;border-radius:6px">
            <div class="d-flex justify-between align-center">
                <div style="font-size:.88rem">
                    <strong>⚠️ 無訂金排工簽核</strong>
                    <?php if (!$_noDepNeeds): ?>
                    <span style="color:#2e7d32;margin-left:8px">✓ 此案件不需簽核，可直接排工</span>
                    <?php elseif ($noDepStatus === 'approved'): ?>
                    <span style="color:#2e7d32;margin-left:8px">✓ 已核准，可以排工</span>
                    <?php elseif ($noDepStatus === 'pending'): ?>
                    <span style="color:#e65100;margin-left:8px">⏳ 簽核中</span>
                    <?php elseif ($noDepStatus === 'rejected'): ?>
                    <span style="color:#c62828;margin-left:8px">✗ 已退回，請重新申請</span>
                    <?php else: ?>
                    <span style="color:#666;margin-left:8px">無訂金且符合需簽核條件，請先申請</span>
                    <?php endif; ?>
                </div>
                <?php if ($_noDepNeeds && $noDepStatus !== 'approved' && $noDepStatus !== 'pending'): ?>
                <form method="POST" action="/cases.php?action=submit_no_deposit_approval" style="display:inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="case_id" value="<?= (int)$case['id'] ?>">
                    <button type="submit" class="btn btn-primary btn-sm" onclick="return confirm('確認送出無訂金排工簽核？')">+ 申請無訂金排工簽核</button>
                </form>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; endif; ?>

        <!-- 銷項發票 -->
        <?php if ($case): $caseSalesInvoices = $case['sales_invoices'] ?? array(); $siVouchers = $case['sales_invoice_vouchers'] ?? array(); ?>
        <div style="margin:8px 0;padding:10px;background:#fafafa;border:1px solid var(--gray-200);border-radius:6px">
            <div class="d-flex justify-between align-center" style="margin-bottom:6px">
                <strong style="font-size:.9rem">銷項發票</strong>
                <div style="display:flex;gap:6px">
                    <?php if (Auth::hasPermission('finance.manage') || Auth::hasPermission('all')): ?>
                    <button type="button" class="btn btn-outline btn-sm" onclick="openSiSearchModal()" title="搜尋既有銷項發票並連結到本案件（舊資料補掛用）">🔍 搜尋連結</button>
                    <?php endif; ?>
                    <a href="/sales_invoices.php?action=create&case_id=<?= $case['id'] ?>&return=case" class="btn btn-primary btn-sm">+ 新增銷項發票</a>
                </div>
            </div>

            <?php if (Auth::hasPermission('finance.manage') || Auth::hasPermission('all')): ?>
            <!-- 搜尋銷項發票 Modal（僅會計/系統管理者可見，舊資料補掛用） -->
            <div id="siSearchModal" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,.5);z-index:9999;align-items:flex-start;justify-content:center;padding-top:60px">
                <div style="background:#fff;border-radius:8px;width:90%;max-width:900px;max-height:80vh;display:flex;flex-direction:column;overflow:hidden">
                    <div style="padding:14px 20px;border-bottom:1px solid #eee;display:flex;justify-content:space-between;align-items:center">
                        <strong>搜尋銷項發票並連結到本案件</strong>
                        <button type="button" onclick="closeSiSearchModal()" style="border:none;background:transparent;font-size:20px;cursor:pointer">×</button>
                    </div>
                    <div style="padding:14px 20px;border-bottom:1px solid #eee">
                        <input type="text" id="siSearchInput" class="form-control" placeholder="輸入發票號碼（如 ZN00168850）" oninput="onSiSearchInput()" style="width:100%">
                        <small class="text-muted" style="font-size:.75rem">僅搜尋發票號碼；最多顯示 30 筆。已連結其他案件者不可改連結（請先從原案件解除）</small>
                    </div>
                    <div id="siSearchResults" style="padding:10px;overflow-y:auto;flex:1">
                        <div style="padding:20px;color:#999;text-align:center">輸入發票號碼搜尋</div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            <?php if (empty($caseSalesInvoices)): ?>
            <div class="text-muted text-center" style="padding:12px;font-size:.85rem">尚無銷項發票</div>
            <?php else: ?>
            <table class="table" style="font-size:.85rem;margin:0">
                <thead><tr><th style="width:120px">日期</th><th style="width:140px">發票號碼</th><th class="text-right" style="width:120px">含稅金額</th><th style="width:80px">狀態</th><th>憑證（僅此案件）</th><th style="width:140px">操作</th></tr></thead>
                <tbody>
                <?php foreach ($caseSalesInvoices as $si):
                    $statusLabels = array('draft'=>'草稿','pending'=>'待確認','confirmed'=>'已確認','voided'=>'作廢');
                    $statusLabel = isset($statusLabels[$si['status']]) ? $statusLabels[$si['status']] : $si['status'];
                    $vList = isset($siVouchers[$si['id']]) ? $siVouchers[$si['id']] : array();
                ?>
                <tr>
                    <td><?= e($si['invoice_date']) ?></td>
                    <td style="color:var(--primary);font-weight:600"><?= e($si['invoice_number']) ?></td>
                    <td class="text-right">$<?= number_format($si['total_amount']) ?></td>
                    <td><span class="badge"><?= e($statusLabel) ?></span></td>
                    <td>
                        <div id="siv-list-<?= $si['id'] ?>" style="display:flex;flex-wrap:wrap;gap:4px;align-items:center">
                            <?php foreach ($vList as $v):
                                $ext = strtolower(pathinfo($v['file_path'], PATHINFO_EXTENSION));
                                $isImg = in_array($ext, array('jpg','jpeg','png','gif','webp'));
                            ?>
                            <span class="siv-item" data-id="<?= $v['id'] ?>" style="position:relative;display:inline-block">
                                <?php if ($isImg): ?>
                                    <a href="/<?= e($v['file_path']) ?>" target="_blank" title="<?= e($v['file_name']) ?>"><img src="/<?= e($v['file_path']) ?>" style="width:40px;height:40px;object-fit:cover;border:1px solid #ccc;border-radius:3px"></a>
                                <?php else: ?>
                                    <a href="/<?= e($v['file_path']) ?>" target="_blank" title="<?= e($v['file_name']) ?>" style="display:inline-block;padding:3px 8px;background:#eee;border-radius:3px;color:#333;text-decoration:none;font-size:.75rem">📎 <?= e(mb_substr($v['file_name'] ?: 'file', 0, 8)) ?></a>
                                <?php endif; ?>
                                <button type="button" onclick="deleteSiVoucher(<?= $v['id'] ?>, <?= $si['id'] ?>)" style="position:absolute;top:-6px;right:-6px;width:16px;height:16px;border-radius:50%;border:none;background:#f44;color:#fff;font-size:10px;cursor:pointer;line-height:14px;padding:0" title="刪除">×</button>
                            </span>
                            <?php endforeach; ?>
                        </div>
                    </td>
                    <td>
                        <a href="/sales_invoices.php?action=edit&id=<?= $si['id'] ?>&return=case&case_id=<?= $case['id'] ?>" class="btn btn-outline btn-sm" style="font-size:.7rem;padding:2px 8px">編輯</a>
                        <button type="button" class="btn btn-outline btn-sm" style="font-size:.7rem;padding:2px 8px" onclick="document.getElementById('siv-input-<?= $si['id'] ?>').click()">上傳憑證</button>
                        <input type="file" id="siv-input-<?= $si['id'] ?>" accept="image/*,application/pdf" style="display:none" onchange="uploadSiVoucher(this, <?= $si['id'] ?>, <?= $case['id'] ?>)">
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <script>
        function uploadSiVoucher(input, siId, caseId) {
            if (!input.files || !input.files[0]) return;
            var fd = new FormData();
            fd.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);
            fd.append('case_id', caseId);
            fd.append('sales_invoice_id', siId);
            fd.append('file', input.files[0]);
            var xhr = new XMLHttpRequest();
            xhr.open('POST', '/cases.php?action=upload_si_voucher');
            xhr.onload = function() {
                try {
                    var res = JSON.parse(xhr.responseText);
                    if (res.success) {
                        // 附加到該發票的憑證清單
                        var list = document.getElementById('siv-list-' + siId);
                        if (list) {
                            var ext = (res.file_name || '').split('.').pop().toLowerCase();
                            var isImg = ['jpg','jpeg','png','gif','webp'].indexOf(ext) !== -1;
                            var html = '<span class="siv-item" data-id="' + res.id + '" style="position:relative;display:inline-block">';
                            if (isImg) {
                                html += '<a href="/' + res.file_path + '" target="_blank" title="' + res.file_name + '"><img src="/' + res.file_path + '" style="width:40px;height:40px;object-fit:cover;border:1px solid #ccc;border-radius:3px"></a>';
                            } else {
                                html += '<a href="/' + res.file_path + '" target="_blank" title="' + res.file_name + '" style="display:inline-block;padding:3px 8px;background:#eee;border-radius:3px;color:#333;text-decoration:none;font-size:.75rem">📎 ' + (res.file_name || 'file').substring(0, 8) + '</a>';
                            }
                            html += '<button type="button" onclick="deleteSiVoucher(' + res.id + ',' + siId + ')" style="position:absolute;top:-6px;right:-6px;width:16px;height:16px;border-radius:50%;border:none;background:#f44;color:#fff;font-size:10px;cursor:pointer;line-height:14px;padding:0" title="刪除">×</button>';
                            html += '</span>';
                            list.insertAdjacentHTML('beforeend', html);
                        }
                        input.value = '';
                    } else {
                        alert('上傳失敗：' + (res.error || ''));
                    }
                } catch (e) { alert('回應解析失敗'); }
            };
            xhr.onerror = function() { alert('上傳失敗'); };
            xhr.send(fd);
        }

        // ========== 搜尋銷項發票並連結 ==========
        function openSiSearchModal() {
            var modal = document.getElementById('siSearchModal');
            if (!modal) {
                alert('Modal 元素不存在（siSearchModal 未找到）— 請確認頁面已重新載入');
                return;
            }
            modal.style.display = 'flex';
            var input = document.getElementById('siSearchInput');
            if (input) input.value = '';
            var results = document.getElementById('siSearchResults');
            if (results) results.innerHTML = '<div style="padding:20px;color:#999;text-align:center">輸入發票號碼搜尋</div>';
            setTimeout(function() { if (input) input.focus(); }, 100);
        }
        function closeSiSearchModal() {
            var modal = document.getElementById('siSearchModal');
            if (modal) modal.style.display = 'none';
        }
        var _siSearchTimer = null;
        function onSiSearchInput() {
            clearTimeout(_siSearchTimer);
            var q = document.getElementById('siSearchInput').value.trim();
            if (q === '') {
                document.getElementById('siSearchResults').innerHTML = '<div style="padding:20px;color:#999;text-align:center">輸入發票號碼搜尋</div>';
                return;
            }
            _siSearchTimer = setTimeout(function() { doSiSearch(q); }, 300);
        }
        function doSiSearch(q) {
            var caseId = <?= (int)($case['id'] ?? 0) ?>;
            var xhr = new XMLHttpRequest();
            xhr.open('GET', '/cases.php?action=ajax_search_si&q=' + encodeURIComponent(q));
            xhr.onload = function() {
                try {
                    var res = JSON.parse(xhr.responseText);
                    if (!res.success) {
                        document.getElementById('siSearchResults').innerHTML = '<div style="padding:20px;color:#c62828">' + (res.error || '搜尋失敗') + '</div>';
                        return;
                    }
                    renderSiSearchResults(res.data, caseId);
                } catch (e) {
                    document.getElementById('siSearchResults').innerHTML = '<div style="padding:20px;color:#c62828">回應解析失敗</div>';
                }
            };
            xhr.send();
        }
        function renderSiSearchResults(list, caseId) {
            var box = document.getElementById('siSearchResults');
            if (!list || list.length === 0) {
                box.innerHTML = '<div style="padding:20px;color:#999;text-align:center">無符合發票</div>';
                return;
            }
            var html = '<table class="table" style="font-size:.85rem;margin:0"><thead><tr><th>發票號碼</th><th>日期</th><th>客戶</th><th class="text-right">金額</th><th>連結狀態</th><th>操作</th></tr></thead><tbody>';
            for (var i = 0; i < list.length; i++) {
                var r = list[i];
                var statusText = '<span style="color:#2e7d32">未連結</span>';
                var canLink = true;
                if (r.reference_type === 'case' && r.reference_id) {
                    if (String(r.reference_id) === String(caseId)) {
                        statusText = '<span style="color:#1976d2">已連結本案件</span>';
                        canLink = false;
                    } else {
                        var tag = r.linked_case_number ? r.linked_case_number : ('ref=' + r.reference_id);
                        statusText = '<span style="color:#c62828">已連結至 ' + tag + '（需先解除）</span>';
                        canLink = false;
                    }
                } else if (r.reference_type && r.reference_id) {
                    statusText = '<span style="color:#e65100">已連結其他類型（' + r.reference_type + '）</span>';
                    canLink = false;
                }
                html += '<tr>'
                     + '<td style="font-weight:600;color:#1976d2">' + (r.invoice_number || '') + '</td>'
                     + '<td>' + (r.invoice_date || '') + '</td>'
                     + '<td>' + (r.customer_name || '') + '</td>'
                     + '<td class="text-right">$' + Number(r.total_amount || 0).toLocaleString() + '</td>'
                     + '<td>' + statusText + '</td>'
                     + '<td>' + (canLink
                         ? '<button type="button" class="btn btn-primary btn-sm" style="font-size:.7rem;padding:2px 8px" onclick="linkSiToCase(' + r.id + ',\'' + (r.invoice_number || '').replace(/\'/g,"\\'") + '\')">連結</button>'
                         : '<button type="button" class="btn btn-outline btn-sm" disabled style="font-size:.7rem;padding:2px 8px;opacity:.4">—</button>') + '</td>'
                     + '</tr>';
            }
            html += '</tbody></table>';
            box.innerHTML = html;
        }
        function linkSiToCase(siId, invNum) {
            if (!confirm('確定將發票「' + invNum + '」連結到本案件？')) return;
            var fd = new FormData();
            fd.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);
            fd.append('case_id', <?= (int)($case['id'] ?? 0) ?>);
            fd.append('sales_invoice_id', siId);
            var xhr = new XMLHttpRequest();
            xhr.open('POST', '/cases.php?action=link_si_to_case');
            xhr.onload = function() {
                try {
                    var res = JSON.parse(xhr.responseText);
                    if (res.success) {
                        alert('已連結發票 ' + (res.invoice_number || ''));
                        closeSiSearchModal();
                        location.reload();
                    } else {
                        alert('連結失敗：' + (res.error || ''));
                    }
                } catch (e) { alert('回應解析失敗'); }
            };
            xhr.send(fd);
        }

        function deleteSiVoucher(voucherId, siId) {
            if (!confirm('確定刪除此憑證？')) return;
            var fd = new FormData();
            fd.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);
            fd.append('voucher_id', voucherId);
            var xhr = new XMLHttpRequest();
            xhr.open('POST', '/cases.php?action=delete_si_voucher');
            xhr.onload = function() {
                try {
                    var res = JSON.parse(xhr.responseText);
                    if (res.success) {
                        var el = document.querySelector('#siv-list-' + siId + ' .siv-item[data-id="' + voucherId + '"]');
                        if (el) el.remove();
                    } else {
                        alert('刪除失敗：' + (res.error || ''));
                    }
                } catch (e) { alert('回應解析失敗'); }
            };
            xhr.send(fd);
        }
        </script>

        <div class="form-row">
            <div class="form-group">
                <label>帳務請款聯絡人</label>
                <input type="text" name="billing_contact" class="form-control" value="<?= e($case['billing_contact'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>請款聯絡人電話</label>
                <input type="text" name="billing_phone" class="form-control" value="<?= e($case['billing_phone'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>手機</label>
                <input type="text" name="billing_mobile" class="form-control" value="<?= e($case['billing_mobile'] ?? '') ?>">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group" style="flex:2">
                <label>發票寄送地址</label>
                <input type="text" name="billing_address" class="form-control" value="<?= e($case['billing_address'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>電子發票寄送 Email</label>
                <input type="email" name="billing_email" class="form-control" value="<?= e($case['billing_email'] ?? '') ?>">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group" style="flex:1">
                <label>備註</label>
                <textarea name="billing_note" class="form-control" rows="2" placeholder="請款相關備註"><?= e($case['billing_note'] ?? '') ?></textarea>
            </div>
        </div>
    </div>

    <!-- 施工時程與條件 -->
    <div class="card <?= $canEdit['schedule'] ? '' : 'section-readonly' ?>" id="sec-schedule">
        <div class="card-header">施工時程與條件</div>
        <div class="form-row">
            <div class="form-group">
                <label>預計施工日</label>
                <input type="date" max="2099-12-31" name="planned_start_date" id="plannedStartDate" class="form-control" value="<?= e($case['planned_start_date'] ?? '') ?>" onchange="autoCalcEndDate()">
            </div>
            <div class="form-group">
                <label>預計完工日 <small style="color:#888;font-weight:normal">(自動=施工日+天數)</small></label>
                <input type="date" max="2099-12-31" name="planned_end_date" id="plannedEndDate" class="form-control" value="<?= e($case['planned_end_date'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>急迫性</label>
                <select name="urgency" class="form-control">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                    <option value="<?= $i ?>" <?= ($case['urgency'] ?? 3) == $i ? 'selected' : '' ?>><?= $i ?> <?= $i <= 2 ? '(低)' : ($i >= 4 ? '(高)' : '(中)') ?></option>
                    <?php endfor; ?>
                </select>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>預估施工天數</label>
                <input type="number" name="est_labor_days" id="estLaborDays" class="form-control" value="<?= e($case['est_labor_days'] ?? '') ?>" step="0.5" min="0" placeholder="天" oninput="autoCalcCaseHours()">
            </div>
            <div class="form-group">
                <label>預估施工人數</label>
                <input type="number" name="est_labor_people" id="estLaborPeople" class="form-control" value="<?= e($case['est_labor_people'] ?? '') ?>" min="0" placeholder="人" oninput="autoCalcCaseHours()">
            </div>
            <div class="form-group">
                <label>預估施工時數 <small style="color:#888;font-weight:normal">(自動=天×人×8)</small></label>
                <input type="number" name="est_labor_hours" id="estLaborHours" class="form-control" value="<?= e($case['est_labor_hours'] ?? '') ?>" step="0.5" min="0" placeholder="時" oninput="caseHoursManual=true">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>指定施工時間</label>
                <?php
                $pstHour = ''; $pstMin = '';
                if (!empty($case['planned_start_time'])) {
                    $pts = explode(':', $case['planned_start_time']);
                    $pstHour = $pts[0] ?? '';
                    $pstMin = $pts[1] ?? '';
                }
                ?>
                <div style="display:flex;gap:4px;align-items:center">
                    <select name="planned_start_hour" class="form-control" style="flex:1" onchange="updatePlannedTime()">
                        <option value="">時</option>
                        <?php for ($h = 6; $h <= 22; $h++): ?>
                        <option value="<?= sprintf('%02d', $h) ?>" <?= $pstHour === sprintf('%02d', $h) ? 'selected' : '' ?>><?= sprintf('%02d', $h) ?></option>
                        <?php endfor; ?>
                    </select>
                    <span>:</span>
                    <select name="planned_start_min" class="form-control" style="flex:1" onchange="updatePlannedTime()">
                        <option value="">分</option>
                        <?php foreach (array('00','15','30','45') as $mm): ?>
                        <option value="<?= $mm ?>" <?= $pstMin === $mm ? 'selected' : '' ?>><?= $mm ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="hidden" name="planned_start_time" id="plannedStartTime" value="<?= e($case['planned_start_time'] ?? '') ?>">
                </div>
            </div>
            <div class="form-group">
                <label>施工時間起</label>
                <input type="time" name="work_time_start" class="form-control" value="<?= e($case['work_time_start'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>施工時間迄</label>
                <input type="time" name="work_time_end" class="form-control" value="<?= e($case['work_time_end'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>客戶休息時間</label>
                <input type="text" name="customer_break_time" class="form-control" value="<?= e($case['customer_break_time'] ?? '') ?>" placeholder="如: 12:00-13:00">
            </div>
        </div>
        <div class="checkbox-row mt-1">
            <label class="checkbox-label">
                <input type="hidden" name="has_time_restriction" value="0">
                <input type="checkbox" name="has_time_restriction" value="1" <?= !empty($case['has_time_restriction']) ? 'checked' : '' ?>>
                <span>有施工時間限制</span>
            </label>
            <label class="checkbox-label">
                <input type="hidden" name="allow_night_work" value="0">
                <input type="checkbox" name="allow_night_work" value="1" <?= !empty($case['allow_night_work']) ? 'checked' : '' ?>>
                <span>可夜間加班</span>
            </label>
            <label class="checkbox-label">
                <input type="hidden" name="is_flexible" value="0">
                <input type="checkbox" name="is_flexible" value="1" <?= !empty($case['is_flexible']) ? 'checked' : '' ?>>
                <span>可隨時安排</span>
            </label>
            <label class="checkbox-label">
                <input type="hidden" name="is_large_project" value="0">
                <input type="checkbox" name="is_large_project" value="1" <?= !empty($case['is_large_project']) ? 'checked' : '' ?>>
                <span>大型案件</span>
            </label>
            <label class="checkbox-label">
                <input type="hidden" name="needs_multiple_visits" value="0">
                <input type="checkbox" name="needs_multiple_visits" value="1" <?= !empty($case['needs_multiple_visits']) ? 'checked' : '' ?>>
                <span>需多次施工</span>
            </label>
        </div>
        <div class="form-group" style="grid-column: span 2; margin-top:8px">
            <label>施工注意事項</label>
            <textarea name="construction_note" class="form-control" rows="3" placeholder="施工時需特別注意的事項（如：需帶拉梯、客戶限定時間、特殊環境等）"><?= e($case['construction_note'] ?? '') ?></textarea>
        </div>
        <?php if ($case && Auth::hasPermission('schedule.manage')): ?>
        <div style="margin-top:12px">
            <a href="/schedule.php?action=create&case_id=<?= $case['id'] ?>" class="btn btn-outline btn-sm">📅 新增施工行程</a>
        </div>
        <?php endif; ?>
    </div>

    <!-- 預計使用線材與配件 -->
    <?php if ($case): ?>
    <div class="card" id="sec-materials">
        <div class="card-header" style="display:flex;justify-content:space-between;align-items:center">
            <span>預計使用線材與配件</span>
            <button type="button" class="btn btn-outline btn-sm" onclick="addEstMaterial()">+ 新增材料</button>
        </div>
        <div class="table-responsive">
            <table class="table est-table" style="font-size:.9rem">
                <thead><tr>
                    <th style="min-width:220px">品名</th>
                    <th style="width:150px">型號</th>
                    <th style="width:70px">單位</th>
                    <th style="width:90px">預估數量</th>
                    <th style="width:90px" class="text-right">單位成本</th>
                    <th style="width:100px" class="text-right">成本小計</th>
                    <th style="width:40px"></th>
                </tr></thead>
                <tbody id="estMaterialsContainer">
                <?php
                $estMaterials = $case['material_estimates'] ?: array();
                $estIdx = 0;
                $estCostTotal = 0;
                foreach ($estMaterials as $em):
                    $emUnitCost = 0;
                    if (!empty($em['product_id'])) {
                        $emCostStmt = Database::getInstance()->prepare("SELECT cost, pack_qty, cost_per_unit FROM products WHERE id = ?");
                        $emCostStmt->execute(array($em['product_id']));
                        $emCostRow = $emCostStmt->fetch(PDO::FETCH_ASSOC);
                        if ($emCostRow) {
                            if (!empty($emCostRow['cost_per_unit'])) {
                                $emUnitCost = (float)$emCostRow['cost_per_unit'];
                            } elseif (!empty($emCostRow['pack_qty']) && $emCostRow['pack_qty'] > 0) {
                                $emUnitCost = (float)$emCostRow['cost'] / (float)$emCostRow['pack_qty'];
                            } else {
                                $emUnitCost = (float)$emCostRow['cost'];
                            }
                        }
                    }
                    $emLineCost = $emUnitCost * (float)($em['estimated_qty'] ?: 0);
                    $estCostTotal += $emLineCost;
                ?>
                <tr class="est-material-row" data-idx="<?= $estIdx ?>">
                    <td style="position:relative">
                        <input type="text" name="est_materials[<?= $estIdx ?>][material_name]" class="form-control est-name-input"
                               value="<?= e($em['material_name']) ?>" placeholder="搜尋產品..."
                               autocomplete="off" oninput="searchEstProduct(this, <?= $estIdx ?>)">
                        <input type="hidden" name="est_materials[<?= $estIdx ?>][product_id]" value="<?= e($em['product_id'] ?: '') ?>">
                        <div class="est-suggestions" id="est-sug-<?= $estIdx ?>"></div>
                    </td>
                    <td><input type="text" name="est_materials[<?= $estIdx ?>][model_number]" class="form-control" value="<?= e($em['model_number'] ?: '') ?>" placeholder="型號"></td>
                    <td><input type="text" name="est_materials[<?= $estIdx ?>][unit]" class="form-control" value="<?= e($em['unit'] ?: '') ?>" placeholder="單位"></td>
                    <td><input type="number" name="est_materials[<?= $estIdx ?>][estimated_qty]" class="form-control" value="<?= e($em['estimated_qty'] ?: '') ?>" min="0" step="0.1"></td>
                    <td class="text-right" style="color:var(--gray-500);font-size:.85rem"><?= $emUnitCost > 0 ? '$' . number_format($emUnitCost, 1) : '-' ?></td>
                    <td class="text-right" style="font-weight:600;font-size:.85rem"><?= $emLineCost > 0 ? '$' . number_format(round($emLineCost)) : '-' ?></td>
                    <td><button type="button" class="btn btn-sm" style="background:#e53935;color:#fff;padding:4px 8px" onclick="this.closest('tr').remove()">✕</button></td>
                </tr>
                <?php $estIdx++; endforeach; ?>
                <?php if ($estCostTotal > 0): ?>
                <tr style="font-weight:600;border-top:2px solid var(--gray-300)">
                    <td colspan="5" class="text-right">成本合計</td>
                    <td class="text-right">$<?= number_format(round($estCostTotal)) ?></td>
                    <td></td>
                </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php
        // 比對：預估 vs 實際用量
        $actualUsage = array();
        if (!empty($worklogTimeline)) {
            foreach ($worklogTimeline as $wl) {
                if (!empty($wl['materials'])) {
                    foreach ($wl['materials'] as $m) {
                        $key = !empty($m['product_id']) ? 'p_' . $m['product_id'] : 'n_' . $m['material_name'];
                        if (!isset($actualUsage[$key])) {
                            $actualUsage[$key] = array('name' => $m['material_name'], 'product_id' => isset($m['product_id']) ? $m['product_id'] : null, 'unit' => isset($m['unit']) ? $m['unit'] : '', 'total_used' => 0);
                        }
                        $actualUsage[$key]['total_used'] += (float)(isset($m['used_qty']) ? $m['used_qty'] : 0);
                    }
                }
            }
        }
        if (!empty($estMaterials) && !empty($actualUsage)):
        ?>
        <div style="margin-top:12px;border-top:1px solid #e0e0e0;padding-top:12px">
            <strong style="font-size:.9rem">預估 vs 實際用量</strong>
            <table class="est-compare-table">
                <thead><tr><th>品名</th><th>型號</th><th>單位</th><th class="text-right">預估</th><th class="text-right">實際</th><th class="text-right">差異</th></tr></thead>
                <tbody>
                <?php foreach ($estMaterials as $em):
                    $key = !empty($em['product_id']) ? 'p_' . $em['product_id'] : 'n_' . $em['material_name'];
                    $actual = isset($actualUsage[$key]) ? $actualUsage[$key]['total_used'] : 0;
                    $diff = $actual - (float)$em['estimated_qty'];
                    $diffClass = $diff > 0 ? 'est-over' : ($diff < 0 ? 'est-under' : '');
                ?>
                <tr>
                    <td><?= e($em['material_name']) ?></td>
                    <td><?= e($em['model_number'] ?: '-') ?></td>
                    <td><?= e($em['unit'] ?: '-') ?></td>
                    <td class="text-right"><?= $em['estimated_qty'] ?></td>
                    <td class="text-right"><?= $actual ?: '-' ?></td>
                    <td class="text-right <?= $diffClass ?>"><?= $actual ? ($diff > 0 ? '+' : '') . $diff : '-' ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- 附件管理 -->
    <?php if ($case):
        $attachTypes = CaseModel::attachTypeOptions();
        $groupedAtt = array();
        foreach ($attachTypes as $ak => $av) { $groupedAtt[$ak] = array(); }
        if (!empty($case['attachments'])) {
            foreach ($case['attachments'] as $att) {
                $t = isset($groupedAtt[$att['file_type']]) ? $att['file_type'] : 'other';
                $groupedAtt[$t][] = $att;
            }
        }
    ?>
    <div class="card <?= $canEdit['attach'] ? '' : 'section-readonly' ?>" id="sec-attach">
        <div class="card-header d-flex justify-between align-center">
            <span>附件管理</span>
            <?php if ($canEdit['attach']): ?>
            <button type="button" class="btn btn-outline btn-sm" onclick="addNewAttachType()">+ 新增分類</button>
            <?php endif; ?>
        </div>
        <div class="attach-grid">
            <?php foreach ($attachTypes as $typeKey => $typeLabel): ?>
            <div class="attach-type-card" id="atc-<?= $typeKey ?>" data-file-type="<?= $typeKey ?>">
                <div class="atc-header">
                    <span class="atc-title"><?= e($typeLabel) ?></span>
                    <span class="atc-count" id="atc-count-<?= $typeKey ?>"><?= count($groupedAtt[$typeKey]) ?></span>
                </div>
                <div class="atc-files" id="atc-files-<?= $typeKey ?>">
                    <?php foreach ($groupedAtt[$typeKey] as $att):
                        $ext = strtolower(pathinfo($att['file_name'], PATHINFO_EXTENSION));
                        $isImg = in_array($ext, array('jpg','jpeg','png','gif','webp','bmp'));
                    ?>
                    <div class="atc-file <?= $isImg ? 'atc-file-img' : '' ?>" id="att-<?= $att['id'] ?>">
                        <?php if ($isImg): ?>
                        <img src="<?= e($att['file_path']) ?>" class="atc-thumb hs-photo" onclick="hsOpenImage('<?= e($att['file_path']) ?>')" alt="<?= e($att['file_name']) ?>">
                        <?php else: ?>
                        <a href="javascript:void(0)" onclick="hsOpenFile('<?= e($att['file_path']) ?>','<?= e($att['file_name']) ?>')" class="atc-filename">📄 <?= e($att['file_name']) ?></a>
                        <?php endif; ?>
                        <button type="button" class="atc-del" onclick="deleteAttachment(<?= $att['id'] ?>, '<?= $typeKey ?>')">✕</button>
                    </div>
                    <?php endforeach; ?>
                </div>
                <label class="atc-add-btn">
                    <input type="file" multiple accept="image/*,.pdf,.doc,.docx,.xls,.xlsx" style="display:none" onchange="uploadFiles(this, '<?= $typeKey ?>')">
                    <span>＋ 上傳<?= e($typeLabel) ?>（或拖曳檔案進來）</span>
                </label>
                <?php if ($typeKey === 'site_photo'): ?>
                <label style="display:flex;align-items:center;gap:6px;padding:6px 0;font-size:.85rem;color:#e65100;cursor:pointer">
                    <input type="checkbox" name="no_photo_allowed" value="1"
                        <?= !empty($case['readiness']['no_photo_allowed']) ? 'checked' : '' ?>
                        onchange="toggleNoPhoto(this.checked)">
                    客戶不允許拍照
                </label>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <!-- Lightbox -->
    <div class="lightbox-overlay" id="lightboxOverlay" onclick="closeLightbox()">
        <span class="lightbox-close">&times;</span>
        <div class="lightbox-prev" onclick="event.stopPropagation();lightboxNav(-1)">&#10094;</div>
        <img id="lightboxImg" src="" alt="預覽" onclick="event.stopPropagation()">
        <div class="lightbox-next" onclick="event.stopPropagation();lightboxNav(1)">&#10095;</div>
        <div class="lightbox-counter" id="lightboxCounter"></div>
    </div>
    <?php endif; ?>

    <!-- 排工條件驗證（自動偵測） -->
    <?php if ($case):
        $atts = $case['attachments'] ?? array();
        $attTypes = array();
        foreach ($atts as $a) { $attTypes[$a['file_type']] = true; }
        $autoQuotation = !empty($attTypes['quotation']);
        $autoSitePhotos = !empty($attTypes['site_photo']);
        $autoAmount = !empty($case['deal_amount']) && $case['deal_amount'] > 0;
        $sc = $case['site_conditions'] ?? array();
        $autoSiteInfo = !empty($sc['structure_type']) || !empty($sc['conduit_type']) || !empty($sc['floor_count']);
        $isNewInstall = ($case['case_type'] ?? '') === 'new_install';
        $checks = array();
        // 報價單和現場照片只有新案才強制
        if ($isNewInstall) {
            $checks[] = array('label' => '報價單', 'ok' => $autoQuotation, 'hint' => $autoQuotation ? '已上傳' : '未上傳報價單附件');
            $checks[] = array('label' => '現場照片', 'ok' => $autoSitePhotos, 'hint' => $autoSitePhotos ? '已上傳' : '未上傳現場照片附件');
        }
        $checks[] = array('label' => '金額確認', 'ok' => $autoAmount, 'hint' => $autoAmount ? '$' . number_format($case['deal_amount']) : '尚未填寫預估金額');
        $checks[] = array('label' => '現場資料', 'ok' => $autoSiteInfo, 'hint' => $autoSiteInfo ? '已填寫' : '尚未填寫現場環境');
    ?>
    <div class="card" id="sec-readiness">
        <div class="card-header">排工條件驗證</div>
        <p class="text-muted mb-1" style="font-size:.85rem">系統自動偵測，缺少項目將在案件列表顯示警示</p>
        <div class="readiness-grid">
            <?php foreach ($checks as $ck): ?>
            <div class="readiness-item <?= $ck['ok'] ? 'ready-ok' : 'ready-no' ?>">
                <div class="ready-icon"><?= $ck['ok'] ? '✓' : '✕' ?></div>
                <div class="ready-info">
                    <div class="ready-label"><?= e($ck['label']) ?></div>
                    <div class="ready-hint"><?= e($ck['hint']) ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <input type="hidden" name="has_quotation" value="<?= $autoQuotation ? '1' : '0' ?>">
        <input type="hidden" name="has_site_photos" value="<?= $autoSitePhotos ? '1' : '0' ?>">
        <input type="hidden" name="has_amount_confirmed" value="<?= $autoAmount ? '1' : '0' ?>">
        <input type="hidden" name="has_site_info" value="<?= $autoSiteInfo ? '1' : '0' ?>">
    </div>
    <?php endif; ?>

    <!-- 案件利潤分析表 -->
    <?php if ($case && !empty($caseProfitAnalysis) && Auth::hasPermission('cases.manage')):
        $_pa = $caseProfitAnalysis;
        $_deal = $_pa['deal_amount'];
        $_opRate = $_pa['op_rate'];
        $_opMode = !empty($_pa['op_mode']) ? $_pa['op_mode'] : 'labor_ratio';
        $_hourlyCost = !empty($_pa['labor_hourly_cost']) ? $_pa['labor_hourly_cost'] : 560;
        $_laborSource = !empty($_pa['labor_source']) ? $_pa['labor_source'] : '';
        $_laborSourceLabel = $_laborSource === 'case' ? '案件預估' : ($_laborSource === 'quotation' ? '報價預估' : '');
        // 報價預估
        $_qMatCost = $_pa['q_material_cost'];
        $_qCableCostFromQuote = !empty($_pa['q_cable_cost']) ? $_pa['q_cable_cost'] : 0;
        $_qCableCostFromEst = $_pa['est_cable_cost'];
        $_qCableCost = $_qCableCostFromQuote > 0 ? $_qCableCostFromQuote : $_qCableCostFromEst;
        $_qLaborCost = $_pa['q_labor_cost'];
        // 營運成本計算：按人力成本比率 or 按成交金額比率
        if ($_opMode === 'labor_ratio') {
            $_qOpCost = round($_qLaborCost * $_opRate / 100);
        } else {
            $_qOpCost = round($_deal * $_opRate / 100);
        }
        $_qTotalCost = $_qMatCost + $_qCableCost + $_qLaborCost + $_qOpCost;
        $_qProfit = $_deal - $_qTotalCost;
        $_qProfitRate = $_deal > 0 ? round($_qProfit / $_deal * 100, 1) : 0;
        // 實際數據
        $_aEquip = $_pa['actual_equipment'];
        $_aCable = $_pa['actual_cable'];
        $_aConsum = $_pa['actual_consumable'];
        $_aStockout = !empty($_pa['actual_stockout_cost']) ? $_pa['actual_stockout_cost'] : 0;
        $_aMatTotal = $_aEquip + $_aCable + $_aConsum;
        // 如果有出庫成本且無施工回報材料，用出庫成本
        if ($_aMatTotal == 0 && $_aStockout > 0) $_aMatTotal = $_aStockout;
        // 實際人力成本 = 實際工時 × 時薪
        $_aMinutes = $_pa['actual_total_minutes'];
        $_aHours = round($_aMinutes / 60, 1);
        $_aLaborCost = round($_aHours * $_hourlyCost);
        // 實際營運成本
        if ($_opMode === 'labor_ratio') {
            $_aOpCost = round($_aLaborCost * $_opRate / 100);
        } else {
            $_aOpCost = round($_deal * $_opRate / 100);
        }
        // 實際總成本
        $_aTotalCost = $_aMatTotal + $_aLaborCost + $_aOpCost;
        $_aProfit = $_deal - $_aTotalCost;
        $_aProfitRate = $_deal > 0 ? round($_aProfit / $_deal * 100, 1) : 0;
        // 工時
        $_qHours = $_pa['q_labor_hours'];
        // 收款
        $_totalCollected = !empty($_pa['total_collected']) ? $_pa['total_collected'] : 0;
        $_balance = $_deal - $_totalCollected;
    ?>
    <div class="card" id="sec-profit-analysis">
        <div class="card-header">案件利潤分析表</div>
        <?php if (!$_deal && !$_pa['has_quotation']): ?>
        <div style="padding:16px;color:#888;text-align:center">尚無成交金額與報價單數據</div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="profit-table">
                <thead>
                    <tr>
                        <th style="min-width:140px">項目</th>
                        <th style="width:160px;text-align:right">報價金額</th>
                        <th style="width:160px;text-align:right">實際數據</th>
                        <th style="width:120px;text-align:right">差異</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="profit-row-highlight">
                        <td><strong>成交金額</strong></td>
                        <td class="text-right"><strong>$<?= number_format($_deal) ?></strong></td>
                        <td class="text-right"><span class="text-muted">-</span></td>
                        <td class="text-right"><span class="text-muted">-</span></td>
                    </tr>
                    <tr>
                        <td>器材成本</td>
                        <td class="text-right"><?= $_pa['has_quotation'] ? '$' . number_format($_qMatCost) : '<span class="text-muted">-</span>' ?></td>
                        <td class="text-right"><?= $_aEquip ? '$' . number_format($_aEquip) : '<span class="text-muted">-</span>' ?></td>
                        <td class="text-right"><?php if ($_pa['has_quotation'] && $_aEquip): $d = $_aEquip - $_qMatCost; ?>
                            <span style="color:<?= $d > 0 ? '#c5221f' : '#137333' ?>"><?= ($d > 0 ? '+' : '') . '$' . number_format($d) ?></span>
                        <?php else: ?><span class="text-muted">-</span><?php endif; ?></td>
                    </tr>
                    <tr>
                        <td>線材預估成本</td>
                        <td class="text-right"><?= $_qCableCost ? '$' . number_format($_qCableCost) : '<span class="text-muted">-</span>' ?></td>
                        <td class="text-right"><?= $_aCable ? '$' . number_format($_aCable) : '<span class="text-muted">-</span>' ?></td>
                        <td class="text-right"><?php if ($_qCableCost && $_aCable): $d = $_aCable - $_qCableCost; ?>
                            <span style="color:<?= $d > 0 ? '#c5221f' : '#137333' ?>"><?= ($d > 0 ? '+' : '') . '$' . number_format($d) ?></span>
                        <?php else: ?><span class="text-muted">-</span><?php endif; ?></td>
                    </tr>
                    <tr>
                        <td>耗材</td>
                        <td class="text-right"><span class="text-muted">-</span></td>
                        <td class="text-right"><?= $_aConsum ? '$' . number_format($_aConsum) : '<span class="text-muted">-</span>' ?></td>
                        <td class="text-right"><span class="text-muted">-</span></td>
                    </tr>
                    <tr>
                        <td>人力總工時</td>
                        <td class="text-right"><?= $_qHours ? $_qHours . ' 小時' : '<span class="text-muted">-</span>' ?></td>
                        <td class="text-right"><?= $_aHours ? $_aHours . ' 小時' : '<span class="text-muted">-</span>' ?></td>
                        <td class="text-right"><?php if ($_qHours && $_aHours): $d = round($_aHours - $_qHours, 1); ?>
                            <span style="color:<?= $d > 0 ? '#c5221f' : '#137333' ?>"><?= ($d > 0 ? '+' : '') . $d ?> 小時</span>
                        <?php else: ?><span class="text-muted">-</span><?php endif; ?></td>
                    </tr>
                    <tr>
                        <td>人力成本 <small style="color:#888">($<?= number_format($_hourlyCost) ?>/時<?= $_laborSourceLabel ? '·' . $_laborSourceLabel : '' ?>)</small></td>
                        <td class="text-right"><?= $_qLaborCost ? '$' . number_format($_qLaborCost) : '<span class="text-muted">-</span>' ?></td>
                        <td class="text-right"><?= $_aLaborCost ? '$' . number_format($_aLaborCost) : '<span class="text-muted">-</span>' ?></td>
                        <td class="text-right"><?php if ($_qLaborCost && $_aLaborCost): $d = $_aLaborCost - $_qLaborCost; ?>
                            <span style="color:<?= $d > 0 ? '#c5221f' : '#137333' ?>"><?= ($d > 0 ? '+' : '') . '$' . number_format($d) ?></span>
                        <?php else: ?><span class="text-muted">-</span><?php endif; ?></td>
                    </tr>
                    <tr>
                        <td>營運成本 <small style="color:#888">(人力×<?= $_opRate ?>%)</small></td>
                        <td class="text-right"><?= $_qLaborCost ? '$' . number_format($_qOpCost) : '<span class="text-muted">-</span>' ?></td>
                        <td class="text-right"><?= $_aLaborCost ? '$' . number_format($_aOpCost) : '<span class="text-muted">-</span>' ?></td>
                        <td class="text-right"><?php if ($_qLaborCost && $_aLaborCost): $d = $_aOpCost - $_qOpCost; ?>
                            <span style="color:<?= $d > 0 ? '#c5221f' : '#137333' ?>"><?= ($d > 0 ? '+' : '') . '$' . number_format($d) ?></span>
                        <?php else: ?><span class="text-muted">-</span><?php endif; ?></td>
                    </tr>
                    <tr class="profit-row-highlight">
                        <td><strong>總成本</strong></td>
                        <td class="text-right"><strong>$<?= number_format($_qTotalCost) ?></strong></td>
                        <td class="text-right"><strong><?= ($_aMatTotal || $_aLaborCost) ? '$' . number_format($_aTotalCost) : '-' ?></strong></td>
                        <td class="text-right"><?php if ($_aMatTotal || $_aLaborCost): $d = $_aTotalCost - $_qTotalCost; ?>
                            <strong style="color:<?= $d > 0 ? '#c5221f' : '#137333' ?>"><?= ($d > 0 ? '+' : '') . '$' . number_format($d) ?></strong>
                        <?php else: ?><span class="text-muted">-</span><?php endif; ?></td>
                    </tr>
                    <tr class="profit-row-result">
                        <td><strong>利潤金額</strong></td>
                        <td class="text-right"><strong style="color:<?= $_qProfit >= 0 ? '#137333' : '#c5221f' ?>">$<?= number_format($_qProfit) ?></strong></td>
                        <td class="text-right"><strong style="color:<?= $_aProfit >= 0 ? '#137333' : '#c5221f' ?>"><?= $_aMatTotal ? '$' . number_format($_aProfit) : '-' ?></strong></td>
                        <td class="text-right"><?php if ($_aMatTotal): $d = $_aProfit - $_qProfit; ?>
                            <strong style="color:<?= $d >= 0 ? '#137333' : '#c5221f' ?>"><?= ($d > 0 ? '+' : '') . '$' . number_format($d) ?></strong>
                        <?php else: ?><span class="text-muted">-</span><?php endif; ?></td>
                    </tr>
                    <tr class="profit-row-result">
                        <td><strong>利潤率</strong></td>
                        <td class="text-right"><strong style="color:<?= $_qProfitRate >= 0 ? '#137333' : '#c5221f' ?>"><?= $_qProfitRate ?>%</strong></td>
                        <td class="text-right"><strong style="color:<?= $_aProfitRate >= 0 ? '#137333' : '#c5221f' ?>"><?= ($_aMatTotal || $_aLaborCost) ? $_aProfitRate . '%' : '-' ?></strong></td>
                        <td class="text-right"><?php if ($_aMatTotal || $_aLaborCost): $d = round($_aProfitRate - $_qProfitRate, 1); ?>
                            <strong style="color:<?= $d >= 0 ? '#137333' : '#c5221f' ?>"><?= ($d > 0 ? '+' : '') . $d ?>%</strong>
                        <?php else: ?><span class="text-muted">-</span><?php endif; ?></td>
                    </tr>
                    <?php if ($_deal > 0): ?>
                    <tr style="border-top:2px solid #e0e0e0">
                        <td><strong>已收款</strong></td>
                        <td class="text-right" colspan="2">
                            <strong style="color:<?= $_totalCollected >= $_deal ? '#137333' : '#e65100' ?>">$<?= number_format($_totalCollected) ?></strong>
                            <?php if ($_deal > 0): ?>
                            <small style="color:#888">(<?= round($_totalCollected / $_deal * 100) ?>%)</small>
                            <?php endif; ?>
                        </td>
                        <td class="text-right">
                            <?php if ($_balance > 0): ?>
                            <span style="color:#c5221f">尾款 $<?= number_format($_balance) ?></span>
                            <?php elseif ($_balance == 0): ?>
                            <span style="color:#137333">已收清</span>
                            <?php else: ?>
                            <span style="color:#1565c0">溢收 $<?= number_format(abs($_balance)) ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- 施工回報 -->
    <?php if ($case):
        $canWorklog = Auth::canEditSection('worklog');
        // 合併排工回報 + case_work_logs
        $caseWorkLogs = $case['case_work_logs'] ?? array();
        $allWorklogs = array();
        // 排工回報
        foreach ($worklogTimeline as $wl) {
            $allWorklogs[] = array(
                'type' => 'schedule',
                'worklog_id' => $wl['id'],
                'date' => $wl['schedule_date'],
                'engineer' => $wl['real_name'],
                'visit' => $wl['visit_number'],
                'arrival' => $wl['arrival_time'],
                'departure' => $wl['departure_time'],
                'content' => $wl['work_description'],
                'issues' => $wl['issues'],
                'photos' => $wl['photos'],
                'materials' => $wl['materials'],
                'payment_collected' => $wl['payment_collected'],
                'payment_amount' => $wl['payment_amount'],
                'payment_method' => $wl['payment_method'],
                'next_visit' => $wl['next_visit_needed'],
                'next_note' => $wl['next_visit_note'],
                'is_completed' => !empty($wl['is_completed']),
                'next_visit_date' => $wl['next_visit_date'] ?? null,
                'next_visit_type' => $wl['next_visit_type'] ?? null,
            );
        }
        // case_work_logs (Ragic 匯入或手動新增)
        // 排除已從 work_logs 同步過來的（source_worklog_id 有值），避免跟 $worklogTimeline 重複
        foreach ($caseWorkLogs as $cwl) {
            if (!empty($cwl['source_worklog_id'])) continue;
            // 解析 photo_paths JSON 為照片陣列
            $manualPhotos = array();
            if (!empty($cwl['photo_paths'])) {
                $decoded = json_decode($cwl['photo_paths'], true);
                if (is_array($decoded)) {
                    foreach ($decoded as $pp) {
                        $manualPhotos[] = array('file_path' => $pp, 'caption' => '');
                    }
                }
            }
            $allWorklogs[] = array(
                'type' => 'manual',
                'id' => $cwl['id'],
                'date' => $cwl['work_date'],
                'engineer' => $cwl['creator_name'] ?? '',
                'visit' => null,
                'arrival' => null,
                'departure' => null,
                'content' => $cwl['work_content'],
                'issues' => null,
                'photos' => $manualPhotos,
                'materials' => array(),
                'equipment' => $cwl['equipment_used'],
                'cable' => $cwl['cable_used'],
                'payment_collected' => false,
                'payment_amount' => 0,
                'payment_method' => null,
                'next_visit' => false,
                'next_note' => null,
            );
        }
        // 按日期排序（新的在前）
        usort($allWorklogs, function($a, $b) {
            return strcmp($b['date'] ?: '0', $a['date'] ?: '0');
        });
        $totalWl = count($allWorklogs);
    ?>
    <div class="card" id="sec-worklog">
        <div class="card-header d-flex justify-between align-center">
            <span>施工回報紀錄</span>
            <div class="d-flex gap-1 align-center">
                <span class="badge"><?= $totalWl ?> 筆</span>
                <?php if ($canWorklog): ?>
                <a href="/worklog.php?action=new_from_case&case_id=<?= $case['id'] ?>" class="btn btn-primary btn-sm">+ 新增回報</a>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($canWorklog): ?>
        <div id="worklog-add-form" style="display:none;padding:14px;border-bottom:1px solid var(--gray-200);background:#fafafa">
            <div class="form-row">
                <div class="form-group">
                    <label>施工日期 *</label>
                    <input type="date" max="2099-12-31" id="wl_date" class="form-control" value="<?= date('Y-m-d') ?>">
                </div>
                <div class="form-group">
                    <label>上工時間</label>
                    <input type="time" id="wl_arrival" class="form-control">
                </div>
                <div class="form-group">
                    <label>下工時間</label>
                    <input type="time" id="wl_departure" class="form-control">
                </div>
            </div>
            <div class="form-group">
                <label>施工內容 *</label>
                <textarea id="wl_content" class="form-control" rows="3" placeholder="施工項目、使用設備等"></textarea>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>使用器材</label>
                    <input type="text" id="wl_equipment" class="form-control" placeholder="選填">
                </div>
                <div class="form-group">
                    <label>使用線材</label>
                    <input type="text" id="wl_cable" class="form-control" placeholder="選填">
                </div>
            </div>
            <div class="form-group">
                <label>施工照片</label>
                <div class="wl-photo-upload">
                    <div class="wl-photo-previews" id="wl_photo_previews"></div>
                    <label class="wl-upload-btn">
                        <input type="file" id="wl_photos" multiple accept="image/*" style="display:none" onchange="previewWlPhotos(this)">
                        <span>📷 上傳照片</span>
                    </label>
                </div>
            </div>
            <div class="d-flex gap-1">
                <button type="button" class="btn btn-primary btn-sm" onclick="saveWorklog()">儲存</button>
                <button type="button" class="btn btn-outline btn-sm" onclick="toggleWorklogForm()">取消</button>
            </div>
        </div>
        <?php endif; ?>

        <?php if (empty($allWorklogs)): ?>
        <p class="text-muted text-center" style="padding:24px">目前無施工回報紀錄</p>
        <?php else: ?>
        <div class="wl-timeline">
            <?php foreach ($allWorklogs as $wl): ?>
            <div class="wl-entry" <?php if ($wl['type'] === 'schedule' && !empty($wl['worklog_id'])): ?>style="cursor:pointer" onclick="window.location='/worklog.php?action=report&id=<?= $wl['worklog_id'] ?>&from_case=<?= $case['id'] ?>'"<?php elseif ($wl['type'] === 'manual'): ?>style="cursor:pointer" onclick="openWorklogDetail(<?= $wl['id'] ?>)"<?php endif; ?>>
                <div class="wl-date-bar">
                    <span class="wl-date"><?= e($wl['date'] ?: '未填日期') ?></span>
                    <?php if ($wl['engineer']): ?><span class="wl-engineer"><?= e($wl['engineer']) ?></span><?php endif; ?>
                    <?php if ($wl['visit']): ?>
                    <span class="badge" style="font-size:.7rem">第<?= $wl['visit'] ?>次</span>
                    <?php endif; ?>
                    <?php if ($wl['arrival'] && $wl['departure']):
                        $arr = strtotime($wl['arrival']);
                        $dep = strtotime($wl['departure']);
                        $mins = round(($dep - $arr) / 60);
                        $hrs = floor($mins / 60);
                        $rm = $mins % 60;
                    ?>
                    <span class="text-muted" style="font-size:.8rem"><?= date('H:i', $arr) ?> ~ <?= date('H:i', $dep) ?> (<?= $hrs ?>時<?= $rm ?>分)</span>
                    <?php elseif ($wl['arrival']): ?>
                    <span class="text-muted" style="font-size:.8rem">到場 <?= date('H:i', strtotime($wl['arrival'])) ?></span>
                    <?php endif; ?>
                    <?php if ($wl['type'] === 'schedule' && !empty($wl['worklog_id'])): ?>
                    <span class="text-muted" style="font-size:.75rem">點擊查看 →</span>
                    <?php endif; ?>
                    <?php if ($wl['type'] === 'manual' && in_array(Auth::user()['role'], array('boss', 'vice_president', 'manager'))): ?>
                    <button type="button" class="btn btn-outline btn-sm" style="font-size:.7rem;padding:2px 6px;color:var(--danger)" onclick="event.stopPropagation();deleteWorklog(<?= $wl['id'] ?>)">刪除</button>
                    <?php endif; ?>
                </div>
                <?php if ($wl['content']): ?>
                <div class="wl-desc"><?= nl2br(e($wl['content'])) ?></div>
                <?php endif; ?>
                <?php if (!empty($wl['issues'])): ?>
                <div class="wl-issues"><strong>問題：</strong><?= nl2br(e($wl['issues'])) ?></div>
                <?php endif; ?>
                <?php if (!empty($wl['photos'])): ?>
                <div class="wl-photos">
                    <?php foreach ($wl['photos'] as $ph):
                        $pPath = $ph['file_path'];
                        if (strpos($pPath, '/') !== 0) $pPath = '/' . $pPath;
                    ?>
                    <img src="<?= e($pPath) ?>" class="wl-photo-thumb" onclick="event.stopPropagation();openLightbox('<?= e($pPath) ?>')" alt="<?= e($ph['caption'] ?: '施工照片') ?>">
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                <?php if (!empty($wl['equipment'])): ?>
                <div class="wl-materials"><strong>使用器材：</strong><?= e($wl['equipment']) ?></div>
                <?php endif; ?>
                <?php if (!empty($wl['cable'])): ?>
                <div class="wl-materials"><strong>使用線材：</strong><?= e($wl['cable']) ?></div>
                <?php endif; ?>
                <?php if (!empty($wl['materials'])): ?>
                <div class="wl-materials">
                    <strong>使用材料：</strong>
                    <?php foreach ($wl['materials'] as $mt): ?>
                    <span class="badge" style="background:#f5f5f5;color:#333;margin:2px"><?= e($mt['material_name']) ?> ×<?= $mt['used_qty'] ?></span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                <?php if ($wl['payment_collected'] && $wl['payment_amount'] > 0): ?>
                <div class="wl-payment">💰 收款 $<?= number_format($wl['payment_amount']) ?> (<?= e($wl['payment_method'] ?: '') ?>)</div>
                <?php endif; ?>
                <?php if (!empty($wl['is_completed'])): ?>
                <div style="margin-top:4px"><span class="badge badge-success">已完工</span></div>
                <?php endif; ?>
                <?php if ($wl['next_visit']): ?>
                <div class="wl-next" style="color:#e65100;font-size:.85rem">
                    ⚠ 需再次施工
                    <?php if (!empty($wl['next_visit_date'])): ?>
                    — 預計 <?= e($wl['next_visit_date']) ?>
                    <?php elseif (($wl['next_visit_type'] ?? '') === 'pending'): ?>
                    — 待安排
                    <?php endif; ?>
                    <?= $wl['next_note'] ? '：' . e($wl['next_note']) : '' ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- 現場環境 -->
    <div class="card <?= $canEdit['site'] ? '' : 'section-readonly' ?>" id="sec-site">
        <div class="card-header">現場環境</div>
        <?php $sc = $case['site_conditions'] ?? array(); ?>
        <div class="form-group">
            <label>建築結構 (可複選)</label>
            <div class="checkbox-row">
                <?php
                $structures = array('RC'=>'RC結構', 'steel_sheet'=>'鐵皮', 'open_area'=>'空曠地', 'construction_site'=>'建築工地');
                $currentStructures = isset($sc['structure_type']) ? explode(',', $sc['structure_type']) : array();
                foreach ($structures as $v => $l):
                ?>
                <label class="checkbox-label">
                    <input type="checkbox" name="structure_type[]" value="<?= $v ?>" <?= in_array($v, $currentStructures) ? 'checked' : '' ?>>
                    <span><?= $l ?></span>
                </label>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="form-group">
            <label>管線需求 (可複選)</label>
            <div class="checkbox-row">
                <?php
                $conduits = array('PVC'=>'PVC', 'EMT'=>'EMT', 'RSG'=>'RSG', 'molding'=>'壓條', 'wall_penetration'=>'穿牆', 'aerial'=>'架空', 'underground'=>'切地埋管');
                $currentConduits = isset($sc['conduit_type']) ? explode(',', $sc['conduit_type']) : array();
                foreach ($conduits as $v => $l):
                ?>
                <label class="checkbox-label">
                    <input type="checkbox" name="conduit_type[]" value="<?= $v ?>" <?= in_array($v, $currentConduits) ? 'checked' : '' ?>>
                    <span><?= $l ?></span>
                </label>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>樓層數</label>
                <input type="number" name="floor_count" class="form-control" min="0" value="<?= e($sc['floor_count'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>&nbsp;</label>
                <div class="checkbox-row">
                    <label class="checkbox-label">
                        <input type="checkbox" name="has_elevator" value="1" <?= !empty($sc['has_elevator']) ? 'checked' : '' ?>>
                        <span>有電梯</span>
                    </label>
                </div>
            </div>
        </div>
        <div class="form-group">
            <label>特殊設備需求</label>
            <div class="equip-group">
                <div class="equip-item">
                    <label class="checkbox-label">
                        <input type="hidden" name="has_ladder_needed" value="0">
                        <input type="checkbox" name="has_ladder_needed" value="1" id="chkLadder" <?= !empty($sc['has_ladder_needed']) ? 'checked' : '' ?> onchange="toggleLadderSize()">
                        <span>拉梯</span>
                    </label>
                    <div id="ladderSizeWrap" style="display:<?= !empty($sc['has_ladder_needed']) ? 'flex' : 'none' ?>;align-items:center;gap:6px;margin-left:8px">
                        <?php
                        $ladderSizes = array('4'=>'4米','5'=>'5米','6'=>'6米','7'=>'7米','9'=>'9米');
                        $currentLadder = $sc['ladder_size'] ?? '';
                        foreach ($ladderSizes as $lv => $ll):
                        ?>
                        <label class="checkbox-label">
                            <input type="radio" name="ladder_size" value="<?= $lv ?>" <?= $currentLadder == $lv ? 'checked' : '' ?>>
                            <span><?= $ll ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="equip-item">
                    <label class="checkbox-label">
                        <input type="checkbox" id="chkHighCeiling" <?= !empty($sc['high_ceiling_height']) ? 'checked' : '' ?> onchange="toggleHighCeiling()">
                        <span>挑高場所</span>
                    </label>
                    <div id="highCeilingWrap" style="display:<?= !empty($sc['high_ceiling_height']) ? 'inline-flex' : 'none' ?>;align-items:center;gap:4px;margin-left:8px">
                        <input type="text" name="high_ceiling_height" class="form-control" style="width:80px" value="<?= e($sc['high_ceiling_height'] ?? '') ?>" placeholder="高度">
                        <span>米</span>
                    </div>
                </div>
                <div class="equip-item">
                    <label class="checkbox-label">
                        <input type="hidden" name="needs_scissor_lift" value="0">
                        <input type="checkbox" name="needs_scissor_lift" value="1" id="chkScissorLift" <?= !empty($sc['needs_scissor_lift']) ? 'checked' : '' ?> onchange="toggleScissorLift()">
                        <span>自走車</span>
                    </label>
                    <div id="scissorLiftWrap" style="display:<?= !empty($sc['needs_scissor_lift']) ? 'flex' : 'none' ?>;align-items:center;gap:6px;margin-left:8px;flex-wrap:wrap">
                        <?php
                        $scissorSizes = array('8'=>'8米','10'=>'10米','12'=>'12米');
                        $currentScissor = isset($sc['scissor_lift_height']) ? $sc['scissor_lift_height'] : '';
                        $isPreset = in_array($currentScissor, array('8','10','12'), true);
                        foreach ($scissorSizes as $sv => $sl):
                        ?>
                        <label class="checkbox-label">
                            <input type="radio" name="scissor_lift_height_preset" value="<?= $sv ?>" <?= $currentScissor === $sv ? 'checked' : '' ?> onchange="onScissorPresetChange(this)">
                            <span><?= $sl ?></span>
                        </label>
                        <?php endforeach; ?>
                        <label class="checkbox-label">
                            <input type="radio" name="scissor_lift_height_preset" value="custom" <?= ($currentScissor !== '' && !$isPreset) ? 'checked' : '' ?> onchange="onScissorPresetChange(this)">
                            <span>自訂</span>
                        </label>
                        <input type="text" name="scissor_lift_height" id="scissorLiftHeightInput" class="form-control" style="width:80px" value="<?= e($currentScissor) ?>" placeholder="米數">
                    </div>
                </div>
                <?php
                $safetyItems = array('helmet'=>'安全帽', 'reflective_vest'=>'反光背心', 'safety_shoes'=>'安全鞋', 'harness'=>'背負式安全帶', 'tool_lanyard'=>'工具防墜');
                $currentSafety = isset($sc['safety_equipment']) ? explode(',', $sc['safety_equipment']) : array();
                $hasSafety = !empty($currentSafety) && $currentSafety[0] !== '';
                ?>
                <div class="equip-item">
                    <label class="checkbox-label">
                        <input type="checkbox" id="chkSafetyToggle" <?= $hasSafety ? 'checked' : '' ?> onchange="toggleSafetyEquipment()">
                        <span>工安需求</span>
                    </label>
                    <div id="safetyEquipmentWrap" style="display:<?= $hasSafety ? 'flex' : 'none' ?>;align-items:center;gap:6px;margin-left:8px;flex-wrap:wrap">
                        <?php foreach ($safetyItems as $sv => $sl): ?>
                        <label class="checkbox-label">
                            <input type="checkbox" name="safety_equipment[]" value="<?= $sv ?>" <?= in_array($sv, $currentSafety) ? 'checked' : '' ?>>
                            <span><?= $sl ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="form-group">
            <label>特殊需求</label>
            <textarea name="special_requirements" class="form-control" rows="2"><?= e($sc['special_requirements'] ?? '') ?></textarea>
        </div>
    </div>

    <!-- 聯絡人已移至基本資料區塊 -->

    <!-- 所需技能 -->
    <div class="card <?= $canEdit['skills'] ? '' : 'section-readonly' ?>" id="sec-skills">
        <div class="card-header">所需技能</div>
        <p class="text-muted mb-1" style="font-size:.85rem">勾選此案件需要的技能，並設定最低熟練度</p>
        <div class="skills-grid">
            <?php
            $reqSkills = array();
            if (!empty($case['required_skills'])) {
                foreach ($case['required_skills'] as $rs) {
                    $reqSkills[$rs['skill_id']] = $rs['min_proficiency'];
                }
            }
            $lastCat = '';
            foreach ($skills as $sk):
                if ($sk['category'] !== $lastCat):
                    $lastCat = $sk['category'];
            ?>
            <div class="skill-category"><?= e($sk['category']) ?></div>
            <?php endif; ?>
            <div class="skill-item">
                <label class="checkbox-label">
                    <input type="checkbox" class="skill-check" data-skill="<?= $sk['id'] ?>"
                           <?= isset($reqSkills[$sk['id']]) ? 'checked' : '' ?>
                           onchange="toggleSkillLevel(this)">
                    <span><?= e($sk['name']) ?></span>
                </label>
                <select name="required_skills[<?= $sk['id'] ?>]" class="form-control skill-level"
                        style="width:100px;display:<?= isset($reqSkills[$sk['id']]) ? 'inline-block' : 'none' ?>">
                    <option value="0">不需要</option>
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                    <option value="<?= $i ?>" <?= ($reqSkills[$sk['id']] ?? 0) == $i ? 'selected' : '' ?>><?= $i ?> 星以上</option>
                    <?php endfor; ?>
                </select>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- 儲存按鈕（固定底部） -->
    <div class="form-actions">
        <button type="submit" class="btn btn-primary"><?= $case ? '儲存變更' : '建立案件' ?></button>
        <a href="/cases.php" class="btn btn-outline">取消</a>
    </div>
</form>

<!-- CSS moved to /css/cases-form.css -->

<script>
var CASE_DATA = {
    contactCount: <?= isset($contacts) ? count($contacts) : 0 ?>,
    caseId: <?= $case ? $case['id'] : 0 ?>
};
</script>
<script src="/js/tw_districts.js"></script>
<script src="/js/cases-form.js?v=20260413a"></script>

<!-- 新增客戶 Modal -->
<div id="newCustomerModal" class="modal-overlay" style="display:none">
    <div class="modal-content" style="max-width:640px">
        <div class="modal-header">
            <h3 style="margin:0">快速新增客戶</h3>
            <button type="button" onclick="closeNewCustomerModal()" style="background:none;border:none;font-size:1.5rem;cursor:pointer">&times;</button>
        </div>
        <div class="modal-body">
            <div class="form-row">
                <div class="form-group" style="flex:2">
                    <label>客戶名稱 *</label>
                    <input type="text" id="modalCustomerName" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>聯絡人</label>
                    <input type="text" id="modalContactPerson" class="form-control">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>市話</label>
                    <input type="text" id="modalPhone" class="form-control">
                </div>
                <div class="form-group">
                    <label>手機</label>
                    <input type="text" id="modalMobile" class="form-control">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>LINE ID</label>
                    <input type="text" id="modalLineId" class="form-control">
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" id="modalEmail" class="form-control">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group" style="flex:2">
                    <label>發票抬頭</label>
                    <input type="text" id="modalInvoiceTitle" class="form-control">
                </div>
                <div class="form-group">
                    <label>統一編號</label>
                    <input type="text" id="modalTaxId" class="form-control" maxlength="8">
                </div>
            </div>
            <div class="form-group">
                <label>承辦業務</label>
                <select id="modalSalesId" class="form-control">
                    <option value="">請選擇</option>
                    <?php foreach ($salesUsers as $u): ?>
                    <option value="<?= $u['id'] ?>"><?= e($u['real_name']) ?><?= !empty($u['is_active']) ? '' : '(離職)' ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>地址</label>
                <input type="text" id="modalAddress" class="form-control">
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline" onclick="closeNewCustomerModal()">取消</button>
            <button type="button" class="btn btn-primary" onclick="saveNewCustomer()">建立客戶</button>
        </div>
    </div>
</div>

<!-- 統編關聯客戶 Modal -->
<div id="taxIdLinkModal" class="modal-overlay" style="display:none" onclick="if(event.target===this)closeTaxIdLinkModal()">
    <div class="modal-content" style="max-width:760px">
        <div class="modal-header">
            <h3 style="margin:0">設定關聯客戶（依統一編號）</h3>
            <button type="button" onclick="closeTaxIdLinkModal()" style="background:none;border:none;font-size:1.5rem;cursor:pointer">&times;</button>
        </div>
        <div class="modal-body">
            <div id="taxIdLinkBody" style="font-size:.88rem">載入中...</div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline" onclick="closeTaxIdLinkModal()">關閉</button>
        </div>
    </div>
</div>

<!-- 帳務交易詳細 Modal -->
<div class="modal-overlay detail-modal" id="paymentDetailModal" style="display:none" onclick="if(event.target===this)closePaymentDetail()">
    <div class="modal-content">
        <div class="modal-header">
            <h3 style="margin:0;font-size:1.1rem">帳款交易詳細</h3>
            <button type="button" onclick="closePaymentDetail()" style="background:none;border:none;font-size:1.3rem;cursor:pointer">&times;</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="pd_id">
            <div class="form-row">
                <div class="form-group">
                    <label>交易日期 *</label>
                    <input type="date" id="pd_date" class="form-control">
                </div>
                <div class="form-group">
                    <label>帳款類別</label>
                    <select id="pd_type" class="form-control">
                        <option value="">--</option>
                        <option value="訂金">訂金</option>
                        <option value="第一期款">第一期款</option>
                        <option value="第二期款">第二期款</option>
                        <option value="第三期款">第三期款</option>
                        <option value="尾款">尾款</option>
                        <option value="保留款">保留款</option>
                        <option value="全款">全款</option>
                        <option value="退款">退款</option>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>交易方式</label>
                    <select id="pd_method" class="form-control">
                        <option value="">--</option>
                        <option value="匯款">匯款</option>
                        <option value="現金">現金</option>
                        <option value="支票">支票</option>
                        <option value="check">支票</option>
                        <option value="轉帳">轉帳</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>未稅金額</label>
                    <input type="number" id="pd_untaxed_amount" class="form-control" oninput="onPdUntaxedChange()">
                </div>
                <div class="form-group">
                    <label>稅額（5% 自動）</label>
                    <input type="number" id="pd_tax_amount" class="form-control" oninput="onPdTaxChange()">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>總金額 *</label>
                    <input type="number" id="pd_amount" class="form-control">
                </div>
                <?php if ($_canEditWireFee): ?>
                <div class="form-group">
                    <label>折讓/匯費 <small style="color:#888">(扣除後計尾款)</small></label>
                    <input type="number" id="pd_wire_fee" class="form-control" min="0" step="1" <?= $_canEditPayment ? '' : 'readonly style="background:#f5f5f5"' ?>>
                </div>
                <?php endif; ?>
                <div class="form-group" style="flex:2">
                    <label>收款單號 <small style="color:#888">(連動鎖定，不可修改)</small></label>
                    <input type="text" id="pd_receipt_number" class="form-control" placeholder="S2-..." readonly style="background:#f5f5f5">
                </div>
            </div>
            <div class="form-group">
                <label>備註</label>
                <textarea id="pd_note" class="form-control" rows="4"></textarea>
            </div>
            <div class="form-group">
                <label>憑證圖片</label>
                <div id="pd_current_image"></div>
                <input type="file" id="pd_image" accept="image/*" multiple>
            </div>
            <?php if (!$_canEditPayment): ?>
            <div style="margin-top:8px;padding:8px 12px;background:#fff8e1;border:1px solid #ffc107;border-radius:4px;font-size:.85rem;color:#856404">
                ℹ️ 此交易已存檔，僅可檢視。如需修改或刪除，請聯絡系統管理者。
            </div>
            <?php endif; ?>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline" onclick="closePaymentDetail()">關閉</button>
            <?php if ($_canEditPayment): ?>
            <button type="button" class="btn btn-primary" onclick="savePaymentEdit()">儲存變更</button>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- 施工回報詳細 Modal -->
<div class="modal-overlay detail-modal" id="worklogDetailModal" style="display:none" onclick="if(event.target===this)closeWorklogDetail()">
    <div class="modal-content">
        <div class="modal-header">
            <h3 style="margin:0;font-size:1.1rem">施工回報詳細</h3>
            <button type="button" onclick="closeWorklogDetail()" style="background:none;border:none;font-size:1.3rem;cursor:pointer">&times;</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="wd_id">
            <div class="form-row">
                <div class="form-group">
                    <label>施工日期 *</label>
                    <input type="date" id="wd_date" class="form-control">
                </div>
            </div>
            <div class="form-group">
                <label>施工內容 *</label>
                <textarea id="wd_content" class="form-control" rows="4"></textarea>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>使用器材</label>
                    <input type="text" id="wd_equipment" class="form-control">
                </div>
                <div class="form-group">
                    <label>使用線材</label>
                    <input type="text" id="wd_cable" class="form-control">
                </div>
            </div>
            <div class="form-group">
                <label>施工照片</label>
                <div class="wl-photo-grid" id="wd_current_photos"></div>
                <input type="file" id="wd_photos" multiple accept="image/*">
            </div>
        </div>
        <div class="modal-footer" style="justify-content:space-between">
            <button type="button" class="btn btn-outline" onclick="goWorklogDetail()" style="font-size:.85rem">詳細資訊 →</button>
            <div style="display:flex;gap:8px">
                <button type="button" class="btn btn-outline" onclick="closeWorklogDetail()">取消</button>
                <button type="button" class="btn btn-primary" onclick="saveWorklogEdit()">儲存變更</button>
            </div>
        </div>
    </div>
</div>

<!-- 請款流程 Modal -->
<div id="biModal" class="modal-overlay" style="display:none">
    <div class="modal-content" id="biModalBox" style="max-width:700px;max-height:90vh;display:flex;flex-direction:column;position:relative;cursor:default">
        <div class="d-flex justify-between align-center" id="biModalHeader" style="cursor:move;user-select:none;padding:12px 16px;border-bottom:1px solid var(--gray-200,#eee);flex-shrink:0;position:sticky;top:0;background:#fff;z-index:1;border-radius:12px 12px 0 0">
            <h3 id="biModalTitle" style="margin:0">新增請款項目</h3>
            <a href="javascript:void(0)" onclick="closeBiModal()" style="font-size:1.5rem;color:var(--gray-400);padding:4px 8px">&times;</a>
        </div>
        <form id="biForm" onsubmit="event.preventDefault();saveBillingItem()" style="overflow-y:auto;padding:12px 16px;flex:1;-webkit-overflow-scrolling:touch">
            <input type="hidden" name="id" id="biId">
            <div class="form-row">
                <div class="form-group">
                    <label>帳款類別 *</label>
                    <select name="payment_category" id="biCategory" class="form-control" required>
                        <option value="">請選擇</option>
                        <option value="訂金">訂金</option>
                        <option value="第一期款">第一期款</option>
                        <option value="第二期款">第二期款</option>
                        <option value="第三期款">第三期款</option>
                        <option value="尾款">尾款</option>
                        <option value="保留款">保留款</option>
                        <option value="全款">全款</option>
                        <option value="退款">退款</option>
                    </select>
                </div>
                <div class="form-group" style="flex:0 0 100px">
                    <label>含稅</label>
                    <div style="padding-top:6px"><label><input type="checkbox" name="tax_included" id="biTaxIncluded" value="1"> 含稅</label></div>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>未稅金額</label><input type="text" name="amount_untaxed" id="biUntaxed" class="form-control" placeholder="0"></div>
                <div class="form-group"><label>稅金</label><input type="text" name="tax_amount" id="biTax" class="form-control" placeholder="0"></div>
                <div class="form-group"><label>總金額 *</label><input type="text" name="total_amount" id="biTotal" class="form-control" placeholder="0" required></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label><input type="checkbox" name="customer_billable" id="biBillable" value="1" onchange="biExclusive('biBillable')"> 客戶通知可請款</label></div>
                <div class="form-group"><label><input type="checkbox" name="customer_paid" id="biPaid" value="1" onchange="biExclusive('biPaid')"> 客戶通知已付款</label></div>
                <div class="form-group"><label><input type="checkbox" name="is_billed" id="biBilled" value="1" onchange="biExclusive('biBilled')"> 已請款</label></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>付款資訊</label><input type="text" name="customer_paid_info" id="biPaidInfo" class="form-control" placeholder="付款資訊說明"></div>
                <div class="form-group"><label>請款資訊</label><input type="text" name="billed_info" id="biBilledInfo" class="form-control" placeholder="已請款資訊"></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>發票號碼</label><input type="text" name="invoice_number" id="biInvoice" class="form-control" placeholder="發票號碼"></div>
                <div class="form-group"><label>備註</label><input type="text" name="note" id="biNote" class="form-control" placeholder="備註"></div>
            </div>
            <div class="form-group">
                <label>附件（憑證/照片）</label>
                <input type="file" name="bi_attachment" id="biAttachment" accept="image/*,.pdf,.jpg,.jpeg,.png" style="font-size:.85rem">
                <div id="biAttachmentPreview" style="margin-top:4px"></div>
            </div>
            <div class="d-flex gap-1 mt-1">
                <button type="submit" class="btn btn-primary" id="biSubmitBtn">儲存</button>
                <button type="button" class="btn btn-outline" onclick="closeBiModal()">取消</button>
            </div>
        </form>
    </div>
</div>
<script>
// 請款流程三個勾選互斥（單選）
function biExclusive(checkedId) {
    var ids = ['biBillable', 'biPaid', 'biBilled'];
    var el = document.getElementById(checkedId);
    if (el && el.checked) {
        for (var i = 0; i < ids.length; i++) {
            if (ids[i] !== checkedId) document.getElementById(ids[i]).checked = false;
        }
    }
}
function addBillingItem() {
    document.getElementById('biModalTitle').textContent = '新增請款項目';
    document.getElementById('biForm').reset();
    document.getElementById('biId').value = '';
    document.getElementById('biAttachmentPreview').innerHTML = '';
    document.getElementById('biSubmitBtn').disabled = false;
    document.getElementById('biSubmitBtn').textContent = '儲存';
    document.getElementById('biTotal').readOnly = false;
    document.getElementById('biTax').readOnly = false;
    document.getElementById('biModal').style.display = 'flex';
}
function editBillingItem(bi) {
    document.getElementById('biModalTitle').textContent = '編輯請款項目';
    document.getElementById('biId').value = bi.id;
    document.getElementById('biCategory').value = bi.payment_category || '';
    document.getElementById('biUntaxed').value = bi.amount_untaxed || '';
    document.getElementById('biTax').value = bi.tax_amount || '';
    document.getElementById('biTotal').value = bi.total_amount || '';
    document.getElementById('biTaxIncluded').checked = bi.tax_included == 1;
    document.getElementById('biBillable').checked = bi.customer_billable == 1;
    document.getElementById('biPaid').checked = bi.customer_paid == 1;
    document.getElementById('biPaidInfo').value = bi.customer_paid_info || '';
    document.getElementById('biBilled').checked = bi.is_billed == 1;
    document.getElementById('biBilledInfo').value = bi.billed_info || '';
    document.getElementById('biInvoice').value = bi.invoice_number || '';
    document.getElementById('biNote').value = bi.note || '';
    document.getElementById('biSubmitBtn').disabled = false;
    document.getElementById('biSubmitBtn').textContent = '儲存';
    var preview = document.getElementById('biAttachmentPreview');
    if (bi.attachment_path) {
        preview.innerHTML = '<a href="/' + bi.attachment_path + '" target="_blank" style="font-size:.85rem">📎 已有附件（點擊檢視）</a>';
    } else {
        preview.innerHTML = '';
    }
    document.getElementById('biModal').style.display = 'flex';
}
function closeBiModal() {
    document.getElementById('biModal').style.display = 'none';
    var box = document.getElementById('biModalBox');
    box.style.transform = '';
}
// 拖曳功能
(function() {
    var header = document.getElementById('biModalHeader');
    var box = document.getElementById('biModalBox');
    var dx = 0, dy = 0, startX = 0, startY = 0, dragging = false;
    header.addEventListener('mousedown', function(e) {
        if (e.target.tagName === 'A') return;
        dragging = true;
        startX = e.clientX - dx;
        startY = e.clientY - dy;
        document.addEventListener('mousemove', onDrag);
        document.addEventListener('mouseup', stopDrag);
    });
    function onDrag(e) {
        if (!dragging) return;
        dx = e.clientX - startX;
        dy = e.clientY - startY;
        box.style.transform = 'translate(' + dx + 'px,' + dy + 'px)';
    }
    function stopDrag() {
        dragging = false;
        document.removeEventListener('mousemove', onDrag);
        document.removeEventListener('mouseup', stopDrag);
    }
})();
// 未稅金額自動計算稅金與總金額
(function(){
    var untaxed = document.getElementById('biUntaxed');
    var tax = document.getElementById('biTax');
    var total = document.getElementById('biTotal');
    var taxIncl = document.getElementById('biTaxIncluded');
    if (!untaxed || !tax || !total) return;

    function calcFromUntaxed() {
        var amt = parseInt(untaxed.value.replace(/,/g, ''), 10) || 0;
        if (amt > 0) {
            var t = Math.round(amt * 0.05);
            tax.value = t;
            total.value = amt + t;
            total.readOnly = true;
            tax.readOnly = true;
        } else {
            tax.value = '';
            total.value = '';
            total.readOnly = false;
            tax.readOnly = false;
        }
    }

    function calcFromTotal() {
        // 只在未稅為空時，手動輸入總金額
        if (untaxed.value && parseInt(untaxed.value.replace(/,/g, ''), 10) > 0) return;
        var t = parseInt(total.value.replace(/,/g, ''), 10) || 0;
        if (taxIncl.checked && t > 0) {
            var amt = Math.round(t / 1.05);
            var tx = t - amt;
            untaxed.value = amt;
            tax.value = tx;
        }
    }

    untaxed.addEventListener('input', calcFromUntaxed);
    total.addEventListener('input', calcFromTotal);
    taxIncl.addEventListener('change', function() {
        var amt = parseInt(untaxed.value.replace(/,/g, ''), 10) || 0;
        if (amt > 0) calcFromUntaxed();
        else calcFromTotal();
    });
})();
function saveBillingItem() {
    var form = document.getElementById('biForm');
    var fileInput = document.getElementById('biAttachment');
    var btn = document.getElementById('biSubmitBtn');
    btn.disabled = true;
    btn.textContent = '儲存中...';

    function doSave(fd) {
        fd.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);
        fd.append('case_id', '<?= (int)($case['id'] ?? 0) ?>');
        var xhr = new XMLHttpRequest();
        xhr.open('POST', '/cases.php?action=ajax_billing_item_save');
        xhr.onload = function() {
            try {
                var res = JSON.parse(xhr.responseText);
                if (res.success) { location.reload(); }
                else { btn.disabled = false; btn.textContent = '儲存'; alert(res.error || '儲存失敗'); }
            } catch(e) { btn.disabled = false; btn.textContent = '儲存'; alert('儲存失敗'); }
        };
        xhr.onerror = function() { btn.disabled = false; btn.textContent = '儲存'; alert('網路錯誤'); };
        xhr.send(fd);
    }

    // 如果有檔案，先壓縮圖片再上傳
    if (fileInput && fileInput.files.length > 0) {
        var file = fileInput.files[0];
        compressImage(file, 1600, 0.7).then(function(compressed) {
            var fd = new FormData(form);
            fd.delete('bi_attachment');
            fd.append('bi_attachment', compressed);
            doSave(fd);
        });
    } else {
        doSave(new FormData(form));
    }
}
function deleteBillingItem(biId) {
    if (!confirm('確定刪除此請款項目？')) return;
    var fd = new FormData();
    fd.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);
    fd.append('id', biId);
    fd.append('case_id', '<?= (int)($case['id'] ?? 0) ?>');
    var xhr = new XMLHttpRequest();
    xhr.open('POST', '/cases.php?action=ajax_billing_item_delete');
    xhr.onload = function() { location.reload(); };
    xhr.send(fd);
}
</script>

<?php return; /* 以下為舊程式碼，已搬移到外部檔案 */ ?>

<script>
// [OLD CODE - MOVED TO /js/cases-form.js]
(function() {
    var links = document.querySelectorAll('.sec-link');
    var sections = [];
    links.forEach(function(l) {
        var id = l.getAttribute('href').substring(1);
        var el = document.getElementById(id);
        if (el) sections.push({ el: el, link: l });
    });
    if (!sections.length) return;
    var onScroll = function() {
        var scrollY = window.scrollY + 80;
        var current = sections[0];
        for (var i = 0; i < sections.length; i++) {
            if (sections[i].el.offsetTop <= scrollY) current = sections[i];
        }
        links.forEach(function(l) { l.classList.remove('active'); });
        current.link.classList.add('active');
    };
    window.addEventListener('scroll', onScroll);
    // 平滑滾動
    links.forEach(function(l) {
        l.addEventListener('click', function(e) {
            e.preventDefault();
            var id = this.getAttribute('href').substring(1);
            var el = document.getElementById(id);
            if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    });
})();

var lightboxImages = [];
var lightboxIndex = 0;
function openLightbox(src) {
    // 收集頁面上所有圖片（用 onclick 含 openLightbox 的）
    lightboxImages = [];
    var allImgs = document.querySelectorAll('.atc-thumb, .wl-photo-thumb, .drawing-thumb, .current-image');
    allImgs.forEach(function(img) {
        var oc = img.getAttribute('onclick') || '';
        var match = oc.match(/openLightbox\(['"]([^'"]+)['"]/);
        if (match && lightboxImages.indexOf(match[1]) === -1) {
            lightboxImages.push(match[1]);
        }
    });
    if (lightboxImages.length === 0) lightboxImages = [src];
    lightboxIndex = lightboxImages.indexOf(src);
    if (lightboxIndex < 0) lightboxIndex = 0;
    showLightboxImage();
    document.getElementById('lightboxOverlay').classList.add('active');
}
function showLightboxImage() {
    document.getElementById('lightboxImg').src = lightboxImages[lightboxIndex];
    var counter = document.getElementById('lightboxCounter');
    if (lightboxImages.length > 1) {
        counter.textContent = (lightboxIndex + 1) + ' / ' + lightboxImages.length;
        counter.style.display = 'block';
    } else {
        counter.style.display = 'none';
    }
    // 隱藏/顯示箭頭
    document.querySelector('.lightbox-prev').style.display = lightboxImages.length > 1 ? 'block' : 'none';
    document.querySelector('.lightbox-next').style.display = lightboxImages.length > 1 ? 'block' : 'none';
}
function lightboxNav(dir) {
    lightboxIndex += dir;
    if (lightboxIndex < 0) lightboxIndex = lightboxImages.length - 1;
    if (lightboxIndex >= lightboxImages.length) lightboxIndex = 0;
    showLightboxImage();
}
function closeLightbox() { document.getElementById('lightboxOverlay').classList.remove('active'); document.getElementById('lightboxImg').src = ''; }
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeLightbox();
    if (e.key === 'ArrowLeft') lightboxNav(-1);
    if (e.key === 'ArrowRight') lightboxNav(1);
});

// 預估施工時數自動計算
var caseHoursManual = false;
function autoCalcCaseHours() {
    if (!caseHoursManual) {
        var days = parseFloat(document.getElementById('estLaborDays').value) || 0;
        var people = parseFloat(document.getElementById('estLaborPeople').value) || 0;
        var hoursInput = document.getElementById('estLaborHours');
        if (days > 0 && people > 0) {
            hoursInput.value = (days * people * 8);
        } else if (days === 0 && people === 0) {
            hoursInput.value = '';
        }
    }
    autoCalcEndDate();
}
// 預計完工日自動計算（施工日 + 天數，跳過週日）
function autoCalcEndDate() {
    var startInput = document.getElementById('plannedStartDate');
    var endInput = document.getElementById('plannedEndDate');
    var daysInput = document.getElementById('estLaborDays');
    if (!startInput || !endInput || !daysInput) return;
    var startVal = startInput.value;
    var days = parseFloat(daysInput.value) || 0;
    if (!startVal || days <= 0) return;
    // 已手動填過完工日就不覆蓋
    if (endInput.dataset.manual === '1') return;
    var parts = startVal.split('-');
    var current = new Date(parseInt(parts[0]), parseInt(parts[1]) - 1, parseInt(parts[2]));
    var count = 0;
    var daysInt = Math.ceil(days);
    while (count < daysInt - 1) {
        current.setDate(current.getDate() + 1);
        if (current.getDay() !== 0) count++; // 跳過週日
    }
    var y = current.getFullYear();
    var m = String(current.getMonth() + 1).padStart(2, '0');
    var d = String(current.getDate()).padStart(2, '0');
    endInput.value = y + '-' + m + '-' + d;
}
// 手動改完工日時標記
(function(){
    var endInput = document.getElementById('plannedEndDate');
    if (endInput) endInput.addEventListener('input', function(){ endInput.dataset.manual = '1'; });
})();

var contactIndex = <?= count($contacts) ?>;
function addContact() {
    var html = '<div class="contact-row" data-index="' + contactIndex + '">' +
        '<div class="form-row">' +
        '<div class="form-group"><label>姓名</label><input type="text" name="contacts[' + contactIndex + '][contact_name]" class="form-control"></div>' +
        '<div class="form-group"><label>電話</label><input type="text" name="contacts[' + contactIndex + '][contact_phone]" class="form-control"></div>' +
        '<div class="form-group"><label>角色</label><input type="text" name="contacts[' + contactIndex + '][contact_role]" class="form-control" placeholder="屋主/管委會/工地主任"></div>' +
        '<div class="form-group" style="align-self:flex-end"><button type="button" class="btn btn-danger btn-sm" onclick="this.closest(\'.contact-row\').remove()">刪除</button></div>' +
        '</div></div>';
    document.getElementById('contactsContainer').insertAdjacentHTML('beforeend', html);
    contactIndex++;
}
function toggleLadderSize() {
    document.getElementById('ladderSizeWrap').style.display = document.getElementById('chkLadder').checked ? 'flex' : 'none';
}
function toggleHighCeiling() {
    var wrap = document.getElementById('highCeilingWrap');
    wrap.style.display = document.getElementById('chkHighCeiling').checked ? 'inline-flex' : 'none';
    if (!document.getElementById('chkHighCeiling').checked) wrap.querySelector('input[name="high_ceiling_height"]').value = '';
}
function toggleScissorLift() {
    document.getElementById('scissorLiftWrap').style.display = document.getElementById('chkScissorLift').checked ? 'flex' : 'none';
}
function toggleSafetyEquipment() {
    document.getElementById('safetyEquipmentWrap').style.display = document.getElementById('chkSafetyToggle').checked ? 'flex' : 'none';
}
function onScissorPresetChange(radio) {
    var input = document.getElementById('scissorLiftHeightInput');
    if (radio.value === 'custom') {
        input.value = '';
        input.focus();
    } else {
        input.value = radio.value;
    }
}
function toggleSkillLevel(cb) {
    var sel = cb.closest('.skill-item').querySelector('.skill-level');
    if (cb.checked) { sel.style.display = 'inline-block'; if (sel.value === '0') sel.value = '1'; }
    else { sel.style.display = 'none'; sel.value = '0'; }
}
function uploadFiles(input, fileType) {
    var files = input.files;
    if (!files.length) return;
    var csrfToken = document.querySelector('input[name="csrf_token"]').value;
    var addBtn = input.parentElement;
    var origText = addBtn.querySelector('span').textContent;
    addBtn.querySelector('span').textContent = '壓縮中...';
    compressImages(Array.prototype.slice.call(files)).then(function(compressed) {
    var uploaded = 0, total = compressed.length;
    addBtn.querySelector('span').textContent = '上傳中 0/' + total + '...';
    for (var i = 0; i < compressed.length; i++) {
        (function(file) {
            if (file.size > 20 * 1024 * 1024) { alert(file.name + ' 超過 20MB'); uploaded++; if (uploaded >= total) { addBtn.querySelector('span').textContent = origText; input.value = ''; } return; }
            var fd = new FormData(); fd.append('file', file); fd.append('file_type', fileType); fd.append('csrf_token', csrfToken);
            var xhr = new XMLHttpRequest();
            xhr.open('POST', '/cases.php?action=upload_attachment&id=<?= $case ? $case['id'] : 0 ?>');
            xhr.onload = function() {
                uploaded++;
                addBtn.querySelector('span').textContent = '上傳中 ' + uploaded + '/' + total + '...';
                if (xhr.status === 200) {
                    try {
                        var res = JSON.parse(xhr.responseText);
                        if (res.success) {
                            var imgExts = ['jpg','jpeg','png','gif','webp','bmp'];
                            var ext = res.file_name.split('.').pop().toLowerCase();
                            var html;
                            if (imgExts.indexOf(ext) !== -1) {
                                html = '<div class="atc-file atc-file-img" id="att-' + res.id + '"><img src="' + res.file_path + '" class="atc-thumb hs-photo" onclick="hsOpenImage(\'' + res.file_path + '\')" alt="' + res.file_name + '"><button type="button" class="atc-del" onclick="deleteAttachment(' + res.id + ',\'' + fileType + '\')">✕</button></div>';
                            } else {
                                html = '<div class="atc-file" id="att-' + res.id + '"><a href="javascript:void(0)" onclick="hsOpenFile(\'' + res.file_path + '\',\'' + res.file_name + '\')" class="atc-filename">📄 ' + res.file_name + '</a><button type="button" class="atc-del" onclick="deleteAttachment(' + res.id + ',\'' + fileType + '\')">✕</button></div>';
                            }
                            document.getElementById('atc-files-' + fileType).insertAdjacentHTML('beforeend', html);
                            updateCount(fileType, 1);
                        } else { alert(res.error || '上傳失敗'); }
                    } catch(e) { alert('上傳失敗'); }
                }
                if (uploaded >= total) { addBtn.querySelector('span').textContent = origText; input.value = ''; }
            };
            xhr.onerror = function() { uploaded++; alert('網路錯誤'); if (uploaded >= total) { addBtn.querySelector('span').textContent = origText; input.value = ''; } };
            xhr.send(fd);
        })(compressed[i]);
    }
    });
}
function updateCount(fileType, delta) {
    var el = document.getElementById('atc-count-' + fileType);
    if (el) el.textContent = parseInt(el.textContent || '0') + delta;
}
function addNewAttachType() {
    var label = prompt('請輸入新的附件分類名稱：');
    if (!label || !label.trim()) return;
    var fd = new FormData();
    fd.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);
    fd.append('label', label.trim());
    var xhr = new XMLHttpRequest();
    xhr.open('POST', '/cases.php?action=add_attach_type', true);
    xhr.onload = function() {
        try {
            var res = JSON.parse(xhr.responseText);
            if (res.success) { location.reload(); }
            else { alert(res.error || '新增失敗'); }
        } catch(e) { alert('新增失敗'); }
    };
    xhr.send(fd);
}
function confirmDeleteCase(id, caseNumber) {
    if (!confirm('確定要刪除案件 ' + caseNumber + '？\n\n此操作無法復原，將同時刪除所有附件、聯絡人、排工紀錄等關聯資料。')) return;
    var form = document.createElement('form');
    form.method = 'POST';
    form.action = '/cases.php?action=delete';
    var idInput = document.createElement('input');
    idInput.type = 'hidden'; idInput.name = 'id'; idInput.value = id;
    var csrfInput = document.createElement('input');
    csrfInput.type = 'hidden'; csrfInput.name = 'csrf_token'; csrfInput.value = document.querySelector('input[name="csrf_token"]').value;
    form.appendChild(idInput);
    form.appendChild(csrfInput);
    document.body.appendChild(form);
    form.submit();
}
function deleteAttachment(id, fileType) {
    if (!confirm('確定刪除此附件?')) return;
    var xhr = new XMLHttpRequest();
    xhr.open('POST', '/cases.php?action=delete_attachment');
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onload = function() {
        if (xhr.status === 200) {
            try {
                var res = JSON.parse(xhr.responseText);
                if (res.success) { var el = document.getElementById('att-' + id); if (el) el.remove(); if (fileType) updateCount(fileType, -1); }
                else { alert(res.error || '刪除失敗'); }
            } catch(e) { alert('刪除失敗'); }
        }
    };
    xhr.send('attachment_id=' + id + '&csrf_token=' + document.querySelector('input[name="csrf_token"]').value);
}

function toggleNoPhoto(checked) {
    var xhr = new XMLHttpRequest();
    xhr.open('POST', '/cases.php?action=toggle_no_photo');
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onload = function() {
        if (xhr.status === 200) { location.reload(); }
    };
    xhr.send('case_id=<?= $case ? $case['id'] : 0 ?>&no_photo=' + (checked ? '1' : '0') + '&csrf_token=' + document.querySelector('input[name="csrf_token"]').value);
}
function togglePaymentForm() {
    var f = document.getElementById('payment-add-form');
    f.style.display = f.style.display === 'none' ? 'block' : 'none';
}

function saveCasePayment() {
    var date = document.getElementById('pay_date').value;
    var amount = document.getElementById('pay_amount').value;
    if (!date || !amount) { alert('請填寫日期和金額'); return; }

    var fd = new FormData();
    fd.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);
    fd.append('case_id', '<?= $case ? $case['id'] : 0 ?>');
    fd.append('payment_date', date);
    fd.append('payment_type', document.getElementById('pay_type').value);
    fd.append('transaction_type', document.getElementById('pay_method').value);
    fd.append('amount', amount);
    var wireEl = document.getElementById('pay_wire_fee');
    if (wireEl) fd.append('wire_fee', wireEl.value || 0);
    fd.append('note', document.getElementById('pay_note').value);
    var img = document.getElementById('pay_image');
    for (var fi = 0; fi < img.files.length; fi++) {
        fd.append('images[]', img.files[fi]);
    }

    var xhr = new XMLHttpRequest();
    xhr.open('POST', '/cases.php?action=add_payment');
    xhr.onload = function() { location.reload(); };
    xhr.send(fd);
}

function deleteCasePayment(id) {
    if (!confirm('確定刪除此帳款紀錄？')) return;
    var xhr = new XMLHttpRequest();
    xhr.open('POST', '/cases.php?action=delete_payment');
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onload = function() { location.reload(); };
    xhr.send('payment_id=' + id + '&csrf_token=' + document.querySelector('input[name="csrf_token"]').value);
}

var wlSelectedFiles = [];
function previewWlPhotos(input) {
    var container = document.getElementById('wl_photo_previews');
    for (var i = 0; i < input.files.length; i++) {
        var file = input.files[i];
        wlSelectedFiles.push(file);
        var idx = wlSelectedFiles.length - 1;
        var reader = new FileReader();
        reader.onload = (function(index) {
            return function(e) {
                var div = document.createElement('div');
                div.className = 'wl-preview-item';
                div.id = 'wl-prev-' + index;
                div.innerHTML = '<img src="' + e.target.result + '">' +
                    '<button type="button" class="wl-preview-del" onclick="removeWlPhoto(' + index + ')">✕</button>';
                container.appendChild(div);
            };
        })(idx);
        reader.readAsDataURL(file);
    }
    input.value = '';
}
function removeWlPhoto(idx) {
    wlSelectedFiles[idx] = null;
    var el = document.getElementById('wl-prev-' + idx);
    if (el) el.remove();
}

function toggleWorklogForm() {
    var f = document.getElementById('worklog-add-form');
    f.style.display = f.style.display === 'none' ? 'block' : 'none';
}

function saveWorklog() {
    var date = document.getElementById('wl_date').value;
    var content = document.getElementById('wl_content').value;
    if (!date || !content) { alert('請填寫施工日期和施工內容'); return; }

    var fd = new FormData();
    fd.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);
    fd.append('case_id', '<?= $case ? $case['id'] : 0 ?>');
    fd.append('work_date', date);
    fd.append('work_content', content);
    fd.append('equipment_used', document.getElementById('wl_equipment').value);
    fd.append('cable_used', document.getElementById('wl_cable').value);

    var arrival = document.getElementById('wl_arrival').value;
    var departure = document.getElementById('wl_departure').value;
    if (arrival) fd.append('arrival_time', arrival);
    if (departure) fd.append('departure_time', departure);

    var photoFiles = [];
    for (var i = 0; i < wlSelectedFiles.length; i++) {
        if (wlSelectedFiles[i]) photoFiles.push(wlSelectedFiles[i]);
    }

    compressImages(photoFiles).then(function(compressed) {
        for (var j = 0; j < compressed.length; j++) {
            fd.append('photos[]', compressed[j]);
        }
        var xhr = new XMLHttpRequest();
        xhr.open('POST', '/cases.php?action=add_worklog');
        xhr.onload = function() { location.reload(); };
        xhr.send(fd);
    });
}

function deleteWorklog(id) {
    if (!confirm('確定刪除此施工回報？')) return;
    var xhr = new XMLHttpRequest();
    xhr.open('POST', '/cases.php?action=delete_worklog');
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onload = function() { location.reload(); };
    xhr.send('worklog_id=' + id + '&csrf_token=' + document.querySelector('input[name="csrf_token"]').value);
}

// ===== 表單驗證 =====
function validateCaseForm() {
    var phone = document.getElementById('customerPhoneInput').value.trim();
    var mobile = document.getElementById('customerMobileInput').value.trim();
    if (!phone && !mobile) {
        alert('請填寫市話或手機，至少填一個');
        document.getElementById('customerPhoneInput').focus();
        return false;
    }
    // 未成交狀態 → 業務備註必填
    if (typeof isSalesNoteLostCase === 'function' && isSalesNoteLostCase()) {
        var note = document.getElementById('salesNoteInput');
        if (note && !note.value.trim()) {
            alert('此案件狀態需填寫業務備註（未成交原因）');
            note.focus();
            note.style.borderColor = '#dc3545';
            return false;
        }
    }
    return true;
}

// ===== 客戶搜尋 =====
var customerSearchTimer = null;
var lastCustomerKeyword = '';
function onCustomerKeyup(e) {
    // 跳過 IME 組字中的按鍵 (keyCode 229 = IME processing)
    if (e.isComposing || e.keyCode === 229) return;
    var val = document.getElementById('customerNameInput').value;
    if (val === lastCustomerKeyword) return;
    lastCustomerKeyword = val;
    // 清除客戶關聯
    document.getElementById('customerId').value = '';
    var info = document.getElementById('customerInfo');
    if (info) info.textContent = '';
    searchCustomer(val);
}
function searchCustomer(keyword) {
    clearTimeout(customerSearchTimer);
    var dd = document.getElementById('customerDropdown');
    if (keyword.length < 2) { dd.style.display = 'none'; return; }
    customerSearchTimer = setTimeout(function() {
        var xhr = new XMLHttpRequest();
        xhr.open('GET', '/cases.php?action=ajax_search_customer&keyword=' + encodeURIComponent(keyword));
        xhr.onload = function() {
            var data = JSON.parse(xhr.responseText);
            if (!data.length) {
                dd.innerHTML = '<div class="customer-dropdown-item" style="color:#999">無符合客戶，請按「+ 新增客戶」建立</div>';
                dd.style.display = 'block';
                return;
            }
            var html = '';
            for (var i = 0; i < data.length; i++) {
                var c = data[i];
                var contacts = c.contacts || [];
                var contactText = '';
                for (var j = 0; j < contacts.length; j++) {
                    if (j > 0) contactText += '、';
                    contactText += contacts[j].contact_name + (contacts[j].phone ? ' ' + contacts[j].phone : '');
                }
                var blacklistBadge = c.is_blacklisted == 1 ? '<span style="background:#e53e3e;color:#fff;padding:1px 6px;border-radius:3px;font-size:.7em;margin-left:4px">⚠ 黑名單</span>' : '';
                var itemStyle = c.is_blacklisted == 1 ? 'border-left:3px solid #e53e3e;background:#fff5f5;' : '';
                html += '<div class="customer-dropdown-item" style="' + itemStyle + '" onclick="selectCustomer(' + JSON.stringify(c).replace(/"/g, '&quot;') + ')">' +
                    '<div style="font-weight:600">' + escHtml(c.name) + blacklistBadge + '</div>' +
                    '<div style="font-size:.75rem;color:#888">' +
                        (c.phone ? c.phone + ' ' : '') +
                        (c.tax_id ? '統編:' + c.tax_id + ' ' : '') +
                        (contactText ? '聯絡人:' + contactText : '') +
                    '</div></div>';
            }
            dd.innerHTML = html;
            dd.style.display = 'block';
        };
        xhr.send();
    }, 300);
}

function escHtml(s) {
    if (!s) return '';
    var d = document.createElement('div');
    d.appendChild(document.createTextNode(s));
    return d.innerHTML;
}

function selectCustomer(c) {
    // 黑名單警告
    if (c.is_blacklisted == 1) {
        var reason = c.blacklist_reason ? '\n原因：' + c.blacklist_reason : '';
        if (!confirm('⚠️ 警告：此客戶已列入黑名單！' + reason + '\n\n確定要選擇此客戶嗎？')) {
            return;
        }
    }
    document.getElementById('customerId').value = c.id;
    document.getElementById('customerNameInput').value = c.name;
    document.getElementById('customerDropdown').style.display = 'none';

    // 更新客戶編號顯示
    var noDisp = document.getElementById('customerNoDisplay');
    if (noDisp && c.customer_no) noDisp.value = c.customer_no;

    // 已連結既有客戶 → 隱藏「+ 新增客戶」按鈕
    var newBtn = document.getElementById('btnNewCustomer');
    if (newBtn && c.customer_no) newBtn.style.display = 'none';

    // 帶入施工地址（如果為空）
    var addrInput = document.querySelector('input[name="address"]');
    if (addrInput && !addrInput.value && c.site_address) {
        addrInput.value = c.site_address;
    }

    // 帶入案件名稱（如果為空）
    var titleInput = document.querySelector('input[name="title"]');
    if (titleInput && !titleInput.value) titleInput.value = c.name;

    // 帶入聯絡人/電話/手機/LINE（如果為空）
    var cpInput = document.getElementById('contactPersonInput');
    if (cpInput && !cpInput.value && c.contact_person) cpInput.value = c.contact_person;
    var phInput = document.getElementById('customerPhoneInput');
    if (phInput && !phInput.value && c.phone) phInput.value = c.phone;
    var mbInput = document.getElementById('customerMobileInput');
    if (mbInput && !mbInput.value && c.mobile) mbInput.value = c.mobile;
    var liInput = document.getElementById('contactLineInput');
    if (liInput && !liInput.value && c.line_official) liInput.value = c.line_official;
    var coInput = document.getElementById('companyInput');
    if (coInput && !coInput.value && c.source_company) coInput.value = c.source_company;

    // 帶入聯絡人
    var contacts = c.contacts || [];
    if (contacts.length > 0) {
        var container = document.getElementById('contactsContainer');
        // 只在沒有已填聯絡人時帶入
        var existingNames = container.querySelectorAll('input[name*="contact_name"]');
        var hasExisting = false;
        for (var i = 0; i < existingNames.length; i++) {
            if (existingNames[i].value.trim()) { hasExisting = true; break; }
        }
        if (!hasExisting) {
            container.innerHTML = '';
            contactIndex = 0;
            for (var j = 0; j < contacts.length; j++) {
                var ct = contacts[j];
                var html = '<div class="contact-row" data-index="' + contactIndex + '">' +
                    '<div class="form-row">' +
                    '<div class="form-group"><label>姓名</label><input type="text" name="contacts[' + contactIndex + '][contact_name]" class="form-control" value="' + escHtml(ct.contact_name) + '"></div>' +
                    '<div class="form-group"><label>電話</label><input type="text" name="contacts[' + contactIndex + '][contact_phone]" class="form-control" value="' + escHtml(ct.phone || '') + '"></div>' +
                    '<div class="form-group"><label>角色</label><input type="text" name="contacts[' + contactIndex + '][contact_role]" class="form-control" value="' + escHtml(ct.role || '') + '" placeholder="屋主/管委會/工地主任"></div>' +
                    '<div class="form-group" style="align-self:flex-end"><button type="button" class="btn btn-danger btn-sm" onclick="this.closest(\'.contact-row\').remove()">刪除</button></div>' +
                    '</div></div>';
                container.insertAdjacentHTML('beforeend', html);
                contactIndex++;
            }
        }
    }

    // 顯示已關聯提示
    var info = document.getElementById('customerInfo');
    if (!info) {
        var inp = document.getElementById('customerNameInput');
        var small = document.createElement('small');
        small.className = 'text-muted';
        small.id = 'customerInfo';
        inp.parentNode.appendChild(small);
        info = small;
    }
    var label = '已關聯客戶 ' + c.name + (c.customer_no ? ' (' + c.customer_no + ')' : '');
    info.innerHTML = '<a href="customers.php?action=view&id=' + c.id + '" style="color:#007bff;text-decoration:underline">' + label + '</a>';
}

// 點擊外面關閉下拉
document.addEventListener('click', function(e) {
    if (!e.target.closest('#customerNameInput') && !e.target.closest('#customerDropdown')) {
        document.getElementById('customerDropdown').style.display = 'none';
    }
});

// 清除客戶關聯已整合到 onCustomerKeyup

// ===== 統編關聯客戶建議 =====
// 條件：案件尚未關聯客戶 (customer_id 為空)，且填有統一編號，
// 而客戶資料中或其他案件已使用同一統編 → 在客戶名稱下方顯示「設定關聯客戶」提示。
var taxIdLookupCache = { taxId: null, data: null };
function checkTaxIdLink() {
    var cidEl = document.getElementById('customerId');
    var taxEl = document.getElementById('billingTaxIdInput');
    var info = document.getElementById('customerInfo');
    console.log('[taxlink] check', {cid: cidEl && cidEl.value, tax: taxEl && taxEl.value, hasInfo: !!info});
    if (!cidEl || !taxEl) return;
    // 確保有 customerInfo 容器；沒有就建立
    if (!info) {
        var nameInp = document.getElementById('customerNameInput');
        if (nameInp && nameInp.parentNode) {
            info = document.createElement('small');
            info.id = 'customerInfo';
            info.className = 'text-muted';
            info.style.cssText = 'position:absolute;bottom:-18px;left:0;font-size:.75rem;z-index:2';
            nameInp.parentNode.appendChild(info);
        } else {
            return;
        }
    }
    // 已有關聯客戶 → 不需要顯示建議（"0" 視為空）
    var cidVal = (cidEl.value || '').trim();
    if (cidVal && cidVal !== '0') return;
    var taxId = (taxEl.value || '').trim();
    if (!taxId) {
        if (info.getAttribute('data-suggest') === '1') {
            info.innerHTML = '';
            info.removeAttribute('data-suggest');
        }
        return;
    }
    if (taxIdLookupCache.taxId === taxId && taxIdLookupCache.data) {
        renderTaxIdSuggest(taxIdLookupCache.data);
        return;
    }
    var caseId = '<?= $case ? (int)$case['id'] : 0 ?>';
    var xhr = new XMLHttpRequest();
    xhr.open('GET', '/cases.php?action=ajax_lookup_by_tax_id&tax_id=' + encodeURIComponent(taxId) + '&exclude_case_id=' + caseId);
    xhr.onload = function() {
        console.log('[taxlink] ajax', xhr.status, xhr.responseText.substring(0, 200));
        try {
            var data = JSON.parse(xhr.responseText);
            taxIdLookupCache = { taxId: taxId, data: data };
            renderTaxIdSuggest(data);
        } catch (e) { console.error('[taxlink] parse', e); }
    };
    xhr.onerror = function() { console.error('[taxlink] xhr error'); };
    xhr.send();
}
function renderTaxIdSuggest(data) {
    var info = document.getElementById('customerInfo');
    if (!info) return;
    var nCust = (data.customers || []).length;
    var nCases = (data.cases || []).length;
    if (nCust === 0 && nCases === 0) {
        if (info.getAttribute('data-suggest') === '1') {
            info.innerHTML = '';
            info.removeAttribute('data-suggest');
        }
        return;
    }
    info.setAttribute('data-suggest', '1');
    var label = '🔗 設定關聯客戶（找到 ' + nCust + ' 位相同統編客戶' + (nCases > 0 ? '，已用於 ' + nCases + ' 筆案件' : '') + '）';
    info.innerHTML = '<a href="javascript:void(0)" onclick="openTaxIdLinkModal()" style="color:#e65100;text-decoration:underline;font-weight:600;cursor:pointer">' + label + '</a>';
}
function openTaxIdLinkModal() {
    var data = taxIdLookupCache.data;
    var body = document.getElementById('taxIdLinkBody');
    if (!data) { body.textContent = '請先輸入統一編號'; }
    else { body.innerHTML = buildTaxIdLinkHtml(data); }
    document.getElementById('taxIdLinkModal').style.display = 'flex';
}
function closeTaxIdLinkModal() {
    document.getElementById('taxIdLinkModal').style.display = 'none';
}
function buildTaxIdLinkHtml(data) {
    var customers = data.customers || [];
    var cases = data.cases || [];
    // 統計每位客戶在相同統編案件中已被使用的次數
    var caseCount = {};
    var caseSamples = {};
    var unlinkedCases = [];
    for (var j = 0; j < cases.length; j++) {
        var k = cases[j];
        var cid = +k.customer_id;
        if (cid > 0) {
            caseCount[cid] = (caseCount[cid] || 0) + 1;
            if (!caseSamples[cid]) caseSamples[cid] = [];
            if (caseSamples[cid].length < 3) caseSamples[cid].push(k.case_number);
        } else {
            unlinkedCases.push(k);
        }
    }
    var html = '';
    if (customers.length === 0) {
        html += '<div style="padding:10px;background:#fff8e1;border:1px solid #ffe082;border-radius:6px;font-size:.85rem">客戶資料中沒有此統編。請按「+ 新增客戶」建立。</div>';
    } else {
        html += '<div style="font-weight:600;margin-bottom:6px">找到 ' + customers.length + ' 位相同統編客戶，請選擇要關聯的客戶</div>';
        html += '<div style="border:1px solid #e0e0e0;border-radius:6px;overflow:hidden">';
        for (var i = 0; i < customers.length; i++) {
            var c = customers[i];
            var blacklist = c.is_blacklisted == 1 ? '<span style="background:#e53e3e;color:#fff;padding:1px 6px;border-radius:3px;font-size:.7em;margin-left:4px">⚠ 黑名單</span>' : '';
            var cnt = caseCount[+c.id] || 0;
            var caseBadge = cnt > 0
                ? '<span style="background:#e3f2fd;color:#1565c0;padding:1px 8px;border-radius:10px;font-size:.72rem;margin-left:6px">已用於 ' + cnt + ' 筆案件' + (caseSamples[+c.id] ? '（' + caseSamples[+c.id].join('、') + (cnt > 3 ? '…' : '') + '）' : '') + '</span>'
                : '<span style="background:#f5f5f5;color:#999;padding:1px 8px;border-radius:10px;font-size:.72rem;margin-left:6px">尚未用於其他案件</span>';
            html += '<div style="padding:10px;border-bottom:1px solid #f0f0f0;display:flex;justify-content:space-between;align-items:center">' +
                '<div style="flex:1;min-width:0">' +
                    '<div style="font-weight:600">' + escHtml(c.name) + blacklist + caseBadge + '</div>' +
                    '<div style="font-size:.78rem;color:#888;margin-top:2px">' +
                        (c.customer_no ? '客戶編號:' + escHtml(c.customer_no) + '　' : '') +
                        (c.tax_id ? '統編:' + escHtml(c.tax_id) + '　' : '') +
                        (c.phone ? '電話:' + escHtml(c.phone) + '　' : '') +
                        (c.contact_person ? '聯絡人:' + escHtml(c.contact_person) : '') +
                    '</div>' +
                '</div>' +
                '<button type="button" class="btn btn-primary btn-sm" style="white-space:nowrap;margin-left:10px" onclick=\'linkCustomerFromTaxId(' + JSON.stringify(c).replace(/'/g, "&#39;") + ')\'>關聯此客戶</button>' +
            '</div>';
        }
        html += '</div>';
    }
    if (unlinkedCases.length > 0) {
        html += '<div style="margin-top:12px;padding:8px 10px;background:#fff8e1;border:1px solid #ffe082;border-radius:6px;font-size:.8rem;color:#666">另有 ' + unlinkedCases.length + ' 筆相同統編案件尚未關聯客戶：' +
            unlinkedCases.slice(0, 5).map(function(x){ return escHtml(x.case_number); }).join('、') +
            (unlinkedCases.length > 5 ? '…' : '') + '</div>';
    }
    return html;
}
// 只關聯客戶（設定 customer_id + 帶入客戶編號），不覆蓋案件其他欄位
function linkCustomerOnly(c) {
    if (!c || !c.id) return;
    if (c.is_blacklisted == 1) {
        var reason = c.blacklist_reason ? '\n原因：' + c.blacklist_reason : '';
        if (!confirm('⚠️ 警告：此客戶已列入黑名單！' + reason + '\n\n確定要關聯此客戶嗎？')) return;
    }
    document.getElementById('customerId').value = c.id;
    var noDisp = document.getElementById('customerNoDisplay');
    if (noDisp) noDisp.value = c.customer_no || '';
    var info = document.getElementById('customerInfo');
    if (info) {
        info.removeAttribute('data-suggest');
        var label = '已關聯客戶 ' + (c.name || '') + (c.customer_no ? ' (' + c.customer_no + ')' : '');
        info.innerHTML = '<a href="customers.php?action=view&id=' + c.id + '" style="color:#007bff;text-decoration:underline;cursor:pointer">' + label + '</a>';
    }
    closeTaxIdLinkModal();
}
function linkCustomerFromTaxId(c) { linkCustomerOnly(c); }
function linkCustomerFromCase(customerId) {
    if (!customerId) return;
    var custs = (taxIdLookupCache.data && taxIdLookupCache.data.customers) || [];
    for (var i = 0; i < custs.length; i++) {
        if (+custs[i].id === +customerId) { linkCustomerOnly(custs[i]); return; }
    }
    alert('找不到對應客戶資料，請從上方客戶清單選擇');
}
// 載入時與統編變動時觸發
function _initTaxIdLink() {
    console.log('[taxlink] init');
    checkTaxIdLink();
    var taxEl = document.getElementById('billingTaxIdInput');
    if (taxEl) {
        taxEl.addEventListener('blur', checkTaxIdLink);
        taxEl.addEventListener('change', checkTaxIdLink);
    }
}
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', _initTaxIdLink);
} else {
    _initTaxIdLink();
}

// ===== 新增客戶按鈕動態顯示/紅框提醒 =====
(function(){
    if (document.getElementById('pulseRedStyle')) return;
    var s = document.createElement('style');
    s.id = 'pulseRedStyle';
    s.textContent = '@keyframes pulseRed{0%,100%{box-shadow:0 0 0 0 rgba(229,57,53,.5)}50%{box-shadow:0 0 0 6px rgba(229,57,53,0)}}';
    document.head.appendChild(s);
})();
var _dealSubStatuses = ['電話報價成交','已成交','跨月成交','現簽'];
function toggleNewCustomerBtn() {
    var sel = document.getElementById('subStatusSelect');
    var btn = document.getElementById('btnNewCustomer');
    var cidEl = document.getElementById('customerId');
    var cnoEl = document.getElementById('customerNoDisplay');
    if (!sel || !btn) return;
    var sub = sel.value;
    // 客戶 id 或 客戶編號 任一有值就視為已關聯
    var hasCust = (cidEl && cidEl.value) || (cnoEl && cnoEl.value);
    var needCust = _dealSubStatuses.indexOf(sub) !== -1 && !hasCust;
    btn.style.display = needCust ? '' : 'none';
    if (needCust) {
        btn.style.border = '2px solid #e53935';
        btn.style.color = '#e53935';
        btn.style.fontWeight = '700';
        btn.style.animation = 'pulseRed 1.2s infinite';
        btn.setAttribute('title', '此狀態必須新增客戶才能儲存');
    } else {
        btn.style.border = '';
        btn.style.color = '';
        btn.style.fontWeight = '';
        btn.style.animation = '';
        btn.removeAttribute('title');
    }
}

// 送出前檢查：改成成交類 + 沒客戶 → 擋
(function() {
    var form = document.querySelector('form');
    if (!form) return;
    form.addEventListener('submit', function(e) {
        var sel = document.getElementById('subStatusSelect');
        var cidEl = document.getElementById('customerId');
        if (!sel || !cidEl) return;
        var cur = sel.value;
        var orig = sel.getAttribute('data-original') || '';
        var cnoEl2 = document.getElementById('customerNoDisplay');
        var hasCust = cidEl.value || (cnoEl2 && cnoEl2.value);
        if (_dealSubStatuses.indexOf(cur) !== -1 && !hasCust && cur !== orig) {
            e.preventDefault();
            alert('狀態改為「' + cur + '」前，請先按「+ 新增客戶」建立客戶');
            var btn = document.getElementById('btnNewCustomer');
            if (btn) btn.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    });
})();

// 強制：客戶編號顯示有值就隱藏新增客戶按鈕（不管其他邏輯）
(function(){
    var cno = document.getElementById('customerNoDisplay');
    var btn = document.getElementById('btnNewCustomer');
    if (cno && cno.value && btn) {
        btn.style.setProperty('display', 'none', 'important');
    }
})();

// ===== 新增客戶 Modal =====
function openNewCustomerModal() {
    var name = document.getElementById('customerNameInput').value;
    document.getElementById('modalCustomerName').value = name;
    document.getElementById('newCustomerModal').style.display = 'flex';
    if (!name) document.getElementById('modalCustomerName').focus();
}
function closeNewCustomerModal() {
    document.getElementById('newCustomerModal').style.display = 'none';
}
function saveNewCustomer() {
    var name = document.getElementById('modalCustomerName').value.trim();
    if (!name) { alert('請輸入客戶名稱'); return; }

    var fd = new FormData();
    fd.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);
    fd.append('name', name);
    fd.append('contact_person', document.getElementById('modalContactPerson').value);
    fd.append('phone', document.getElementById('modalPhone').value);
    fd.append('mobile', document.getElementById('modalMobile').value);
    fd.append('address', document.getElementById('modalAddress').value);

    // 帶入案件資訊
    var caseNoEl = document.querySelector('input[value^="<?= e($case ? substr($case['case_number'], 0, 4) : '2026') ?>"]');
    fd.append('case_number', '<?= e($case['case_number'] ?? '') ?>');
    fd.append('case_date', '<?= e($case ? substr($case['created_at'], 0, 10) : date('Y-m-d')) ?>');
    var branchSelect = document.querySelector('select[name="branch_id"]');
    if (branchSelect) {
        fd.append('source_company', branchSelect.options[branchSelect.selectedIndex].text);
    }

    var xhr = new XMLHttpRequest();
    xhr.open('POST', '/cases.php?action=ajax_create_customer');
    xhr.onload = function() {
        var res = JSON.parse(xhr.responseText);
        if (res.success) {
            closeNewCustomerModal();
            selectCustomer(res.customer);
        } else {
            alert(res.error || '建立客戶失敗');
        }
    };
    xhr.send(fd);
}

// 帳務計算已移至 cases-form.js
</script>

<!-- 新增客戶 Modal -->
<div id="newCustomerModal" class="modal-overlay" style="display:none">
    <div class="modal-content" style="max-width:500px">
        <div class="modal-header">
            <h3 style="margin:0">快速新增客戶</h3>
            <button type="button" onclick="closeNewCustomerModal()" style="background:none;border:none;font-size:1.5rem;cursor:pointer">&times;</button>
        </div>
        <div class="modal-body">
            <div class="form-group">
                <label>客戶名稱 *</label>
                <input type="text" id="modalCustomerName" class="form-control" required>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>聯絡人</label>
                    <input type="text" id="modalContactPerson" class="form-control">
                </div>
                <div class="form-group">
                    <label>電話</label>
                    <input type="text" id="modalPhone" class="form-control">
                </div>
            </div>
            <div class="form-group">
                <label>手機</label>
                <input type="text" id="modalMobile" class="form-control">
            </div>
            <div class="form-group">
                <label>地址</label>
                <input type="text" id="modalAddress" class="form-control">
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline" onclick="closeNewCustomerModal()">取消</button>
            <button type="button" class="btn btn-primary" onclick="saveNewCustomer()">建立客戶</button>
        </div>
    </div>
</div>

<!-- CSS moved to /css/cases-form.css -->

<!-- 帳務交易詳細 Modal -->
<div class="modal-overlay detail-modal" id="paymentDetailModal" style="display:none" onclick="if(event.target===this)closePaymentDetail()">
    <div class="modal-content">
        <div class="modal-header">
            <h3 style="margin:0;font-size:1.1rem">帳款交易詳細</h3>
            <button type="button" onclick="closePaymentDetail()" style="background:none;border:none;font-size:1.3rem;cursor:pointer">&times;</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="pd_id">
            <div class="form-row">
                <div class="form-group">
                    <label>交易日期 *</label>
                    <input type="date" id="pd_date" class="form-control">
                </div>
                <div class="form-group">
                    <label>帳款類別</label>
                    <select id="pd_type" class="form-control">
                        <option value="">--</option>
                        <option value="訂金">訂金</option>
                        <option value="第一期款">第一期款</option>
                        <option value="第二期款">第二期款</option>
                        <option value="第三期款">第三期款</option>
                        <option value="尾款">尾款</option>
                        <option value="保留款">保留款</option>
                        <option value="全款">全款</option>
                        <option value="退款">退款</option>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>交易方式</label>
                    <select id="pd_method" class="form-control">
                        <option value="">--</option>
                        <option value="匯款">匯款</option>
                        <option value="現金">現金</option>
                        <option value="支票">支票</option>
                        <option value="check">支票</option>
                        <option value="轉帳">轉帳</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>未稅金額</label>
                    <input type="number" id="pd_untaxed_amount" class="form-control" oninput="onPdUntaxedChange()">
                </div>
                <div class="form-group">
                    <label>稅額（5% 自動）</label>
                    <input type="number" id="pd_tax_amount" class="form-control" oninput="onPdTaxChange()">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>總金額 *</label>
                    <input type="number" id="pd_amount" class="form-control">
                </div>
                <?php if ($_canEditWireFee): ?>
                <div class="form-group">
                    <label>折讓/匯費 <small style="color:#888">(扣除後計尾款)</small></label>
                    <input type="number" id="pd_wire_fee" class="form-control" min="0" step="1" <?= $_canEditPayment ? '' : 'readonly style="background:#f5f5f5"' ?>>
                </div>
                <?php endif; ?>
                <div class="form-group" style="flex:2">
                    <label>收款單號 <small style="color:#888">(連動鎖定，不可修改)</small></label>
                    <input type="text" id="pd_receipt_number" class="form-control" placeholder="S2-..." readonly style="background:#f5f5f5">
                </div>
            </div>
            <div class="form-group">
                <label>備註</label>
                <textarea id="pd_note" class="form-control" rows="4"></textarea>
            </div>
            <div class="form-group">
                <label>憑證圖片</label>
                <div id="pd_current_image"></div>
                <input type="file" id="pd_image" accept="image/*" multiple>
            </div>
            <?php if (!$_canEditPayment): ?>
            <div style="margin-top:8px;padding:8px 12px;background:#fff8e1;border:1px solid #ffc107;border-radius:4px;font-size:.85rem;color:#856404">
                ℹ️ 此交易已存檔，僅可檢視。如需修改或刪除，請聯絡系統管理者。
            </div>
            <?php endif; ?>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline" onclick="closePaymentDetail()">關閉</button>
            <?php if ($_canEditPayment): ?>
            <button type="button" class="btn btn-primary" onclick="savePaymentEdit()">儲存變更</button>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- 施工回報詳細 Modal -->
<div class="modal-overlay detail-modal" id="worklogDetailModal" style="display:none" onclick="if(event.target===this)closeWorklogDetail()">
    <div class="modal-content">
        <div class="modal-header">
            <h3 style="margin:0;font-size:1.1rem">施工回報詳細</h3>
            <button type="button" onclick="closeWorklogDetail()" style="background:none;border:none;font-size:1.3rem;cursor:pointer">&times;</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="wd_id">
            <div class="form-row">
                <div class="form-group">
                    <label>施工日期 *</label>
                    <input type="date" id="wd_date" class="form-control">
                </div>
            </div>
            <div class="form-group">
                <label>施工內容 *</label>
                <textarea id="wd_content" class="form-control" rows="4"></textarea>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>使用器材</label>
                    <input type="text" id="wd_equipment" class="form-control">
                </div>
                <div class="form-group">
                    <label>使用線材</label>
                    <input type="text" id="wd_cable" class="form-control">
                </div>
            </div>
            <div class="form-group">
                <label>施工照片</label>
                <div class="wl-photo-grid" id="wd_current_photos"></div>
                <input type="file" id="wd_photos" multiple accept="image/*">
            </div>
        </div>
        <div class="modal-footer" style="justify-content:space-between">
            <button type="button" class="btn btn-outline" onclick="goWorklogDetail()" style="font-size:.85rem">詳細資訊 →</button>
            <div style="display:flex;gap:8px">
                <button type="button" class="btn btn-outline" onclick="closeWorklogDetail()">取消</button>
                <button type="button" class="btn btn-primary" onclick="saveWorklogEdit()">儲存變更</button>
            </div>
        </div>
    </div>
</div>

<script>
// 使用者是否為 boss（控制 modal 是否可編輯）
var __PD_CAN_EDIT = <?= $_canEditPayment ? 'true' : 'false' ?>;

// ===== 帳務交易 Modal =====
function openPaymentDetail(id) {
    var xhr = new XMLHttpRequest();
    xhr.open('GET', '/cases.php?action=get_payment&id=' + id);
    xhr.onload = function() {
        var res = JSON.parse(xhr.responseText);
        if (!res.success) { alert(res.error || '載入失敗'); return; }
        var d = res.data;
        document.getElementById('pd_id').value = d.id;
        document.getElementById('pd_date').value = d.payment_date || '';
        document.getElementById('pd_amount').value = d.amount || 0;
        document.getElementById('pd_untaxed_amount').value = d.untaxed_amount || '';
        document.getElementById('pd_tax_amount').value = d.tax_amount || '';
        document.getElementById('pd_receipt_number').value = d.receipt_number || '';
        document.getElementById('pd_note').value = d.note || '';
        var pdWireFill = document.getElementById('pd_wire_fee');
        if (pdWireFill) pdWireFill.value = (d.wire_fee && Number(d.wire_fee) > 0) ? Number(d.wire_fee) : '';

        // Set selects
        var typeEl = document.getElementById('pd_type');
        typeEl.value = d.payment_type || '';
        if (!typeEl.value && d.payment_type) {
            for (var i = 0; i < typeEl.options.length; i++) {
                if (typeEl.options[i].value === d.payment_type) { typeEl.selectedIndex = i; break; }
            }
        }
        var methodEl = document.getElementById('pd_method');
        methodEl.value = d.transaction_type || '';
        if (!methodEl.value && d.transaction_type) {
            var found = false;
            for (var i = 0; i < methodEl.options.length; i++) {
                if (methodEl.options[i].value === d.transaction_type) { methodEl.selectedIndex = i; found = true; break; }
            }
            if (!found) {
                var opt = document.createElement('option');
                opt.value = d.transaction_type;
                opt.textContent = d.transaction_type;
                methodEl.appendChild(opt);
                methodEl.value = d.transaction_type;
            }
        }

        // Show current images (support JSON array or single path)
        var imgDiv = document.getElementById('pd_current_image');
        var images = [];
        if (d.image_path) {
            try { images = JSON.parse(d.image_path); } catch(e) { images = [d.image_path]; }
            if (!Array.isArray(images)) images = [d.image_path];
        }
        if (images.length > 0) {
            var imgHtml = '';
            for (var ii = 0; ii < images.length; ii++) {
                if (!images[ii]) continue;
                imgHtml += '<img src="/' + images[ii] + '" class="current-image" style="margin:2px" onclick="event.stopPropagation();openLightbox(\'/' + images[ii] + '\')">';
            }
            imgDiv.innerHTML = imgHtml;
        } else {
            imgDiv.innerHTML = '<span class="text-muted">無憑證圖片</span>';
        }
        var imgInput = document.getElementById('pd_image');
        if (imgInput) imgInput.value = '';

        // 非 boss → 全部欄位設為唯讀（receipt_number 永遠唯讀）
        if (!__PD_CAN_EDIT) {
            ['pd_date','pd_amount','pd_untaxed_amount','pd_tax_amount','pd_note'].forEach(function(fid) {
                var el = document.getElementById(fid);
                if (el) {
                    el.setAttribute('readonly', 'readonly');
                    el.style.background = '#f5f5f5';
                }
            });
            ['pd_type','pd_method'].forEach(function(fid) {
                var el = document.getElementById(fid);
                if (el) {
                    el.setAttribute('disabled', 'disabled');
                    el.style.background = '#f5f5f5';
                }
            });
            if (imgInput) imgInput.style.display = 'none';
        }

        document.getElementById('paymentDetailModal').style.display = 'flex';
    };
    xhr.send();
}

function closePaymentDetail() {
    document.getElementById('paymentDetailModal').style.display = 'none';
}

function savePaymentEdit() {
    var id = document.getElementById('pd_id').value;
    if (!id) return;
    var fd = new FormData();
    fd.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);
    fd.append('payment_id', id);
    fd.append('payment_date', document.getElementById('pd_date').value);
    fd.append('payment_type', document.getElementById('pd_type').value);
    fd.append('transaction_type', document.getElementById('pd_method').value);
    fd.append('amount', document.getElementById('pd_amount').value);
    var pdWireEl = document.getElementById('pd_wire_fee');
    if (pdWireEl) fd.append('wire_fee', pdWireEl.value || 0);
    fd.append('note', document.getElementById('pd_note').value);
    var imgFiles = document.getElementById('pd_image').files;
    for (var fi = 0; fi < imgFiles.length; fi++) {
        fd.append('images[]', imgFiles[fi]);
    }

    var xhr = new XMLHttpRequest();
    xhr.open('POST', '/cases.php?action=edit_payment');
    xhr.onload = function() {
        var res = JSON.parse(xhr.responseText);
        if (res.success) { location.reload(); }
        else { alert(res.error || '儲存失敗'); }
    };
    xhr.send(fd);
}

// ===== 施工回報 Modal =====
function openWorklogDetail(id) {
    var xhr = new XMLHttpRequest();
    xhr.open('GET', '/cases.php?action=get_worklog&id=' + id);
    xhr.onload = function() {
        var res = JSON.parse(xhr.responseText);
        if (!res.success) { alert(res.error || '載入失敗'); return; }
        var d = res.data;
        document.getElementById('wd_id').value = d.id;
        document.getElementById('wd_date').value = d.work_date || '';
        document.getElementById('wd_content').value = d.work_content || '';
        document.getElementById('wd_equipment').value = d.equipment_used || '';
        document.getElementById('wd_cable').value = d.cable_used || '';

        // Show existing photos
        var photoDiv = document.getElementById('wd_current_photos');
        photoDiv.innerHTML = '';
        if (d.photo_paths) {
            try {
                var photos = JSON.parse(d.photo_paths);
                if (Array.isArray(photos)) {
                    photos.forEach(function(p) {
                        var img = document.createElement('img');
                        img.src = '/' + p;
                        img.onclick = function(e) { e.stopPropagation(); openLightbox('/' + p); };
                        photoDiv.appendChild(img);
                    });
                }
            } catch(e) {}
        }
        if (!photoDiv.innerHTML) {
            photoDiv.innerHTML = '<span class="text-muted">無施工照片</span>';
        }
        document.getElementById('wd_photos').value = '';

        document.getElementById('worklogDetailModal').style.display = 'flex';
    };
    xhr.send();
}

function closeWorklogDetail() {
    document.getElementById('worklogDetailModal').style.display = 'none';
}

function goWorklogDetail() {
    var id = document.getElementById('wd_id').value;
    if (id) window.location = '/worklog.php?action=edit_manual&id=' + id + '&from_case=<?= $case ? $case['id'] : 0 ?>';
}

function saveWorklogEdit() {
    var id = document.getElementById('wd_id').value;
    if (!id) return;
    var fd = new FormData();
    fd.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);
    fd.append('worklog_id', id);
    fd.append('work_date', document.getElementById('wd_date').value);
    fd.append('work_content', document.getElementById('wd_content').value);
    fd.append('equipment_used', document.getElementById('wd_equipment').value);
    fd.append('cable_used', document.getElementById('wd_cable').value);
    var files = document.getElementById('wd_photos').files;
    for (var i = 0; i < files.length; i++) {
        fd.append('photos[]', files[i]);
    }

    var xhr = new XMLHttpRequest();
    xhr.open('POST', '/cases.php?action=edit_worklog');
    xhr.onload = function() {
        var res = JSON.parse(xhr.responseText);
        if (res.success) { location.reload(); }
        else { alert(res.error || '儲存失敗'); }
    };
    xhr.send(fd);
}
</script>
<script src="/js/tw_districts.js"></script>
<script>
// 施工區域 - 縣市鄉鎮連動
(function() {
    var countySelect = document.getElementById('constructionCounty');
    var districtSelect = document.getElementById('constructionDistrict');
    var hiddenInput = document.getElementById('constructionAreaHidden');
    if (!countySelect || !districtSelect || !hiddenInput) return;

    // 優先排序：台中、彰化、南投、苗栗、新竹
    var priority = ['臺中市', '彰化縣', '南投縣', '苗栗縣', '新竹縣', '新竹市'];
    var allCounties = Object.keys(twDistricts);
    var sorted = [];
    for (var p = 0; p < priority.length; p++) {
        if (allCounties.indexOf(priority[p]) !== -1) sorted.push(priority[p]);
    }
    for (var a = 0; a < allCounties.length; a++) {
        if (sorted.indexOf(allCounties[a]) === -1) sorted.push(allCounties[a]);
    }

    for (var i = 0; i < sorted.length; i++) {
        var opt = document.createElement('option');
        opt.value = sorted[i];
        opt.textContent = sorted[i];
        countySelect.appendChild(opt);
    }

    // 解析現有值
    var currentVal = hiddenInput.value || '';
    var currentCounty = '';
    var currentDistrict = '';
    if (currentVal) {
        for (var j = 0; j < sorted.length; j++) {
            if (currentVal.indexOf(sorted[j]) === 0) {
                currentCounty = sorted[j];
                currentDistrict = currentVal.substring(sorted[j].length);
                break;
            }
        }
        if (currentCounty) {
            countySelect.value = currentCounty;
            fillDistricts(currentCounty, currentDistrict);
        }
    }

    countySelect.onchange = function() {
        fillDistricts(this.value, '');
        updateArea();
    };
    districtSelect.onchange = function() {
        updateArea();
    };

    function fillDistricts(county, preselect) {
        districtSelect.innerHTML = '<option value="">選擇鄉鎮區</option>';
        if (!county || !twDistricts[county]) return;
        var dists = twDistricts[county];
        for (var k = 0; k < dists.length; k++) {
            var opt = document.createElement('option');
            opt.value = dists[k].d;
            opt.textContent = dists[k].d;
            if (preselect && dists[k].d === preselect) opt.selected = true;
            districtSelect.appendChild(opt);
        }
    }

    function updateArea() {
        var c = countySelect.value || '';
        var d = districtSelect.value || '';
        hiddenInput.value = c + d;
        // 自動帶入施工地址
        if (c && d) {
            var addrInput = document.querySelector('input[name="address"]');
            if (addrInput) {
                addrInput.value = c + d;
                addrInput.focus();
            }
        }
    }
})();
// 全域函數供 onchange 使用
function updateDistricts() {
    var evt = new Event('change');
    document.getElementById('constructionCounty').dispatchEvent(evt);
}
function updateConstructionArea() {
    var evt = new Event('change');
    document.getElementById('constructionDistrict').dispatchEvent(evt);
}
</script>
<script>
// Last-resort: 強制按 customer_no 隱藏新增客戶按鈕（在其他 script 之後）
try {
    var _cno = document.getElementById('customerNoDisplay');
    var _btn = document.getElementById('btnNewCustomer');
    if (_cno && _cno.value && _btn) {
        _btn.style.setProperty('display', 'none', 'important');
    }
} catch(e) { console.error('force hide btnNewCustomer failed:', e); }
</script>

