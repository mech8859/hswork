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
            $warnings = get_readiness_warnings($case['readiness'] ?: array(), $case['case_type'] ?: 'new_install');
            if (!empty($warnings)):
        ?>
        <span style="color:#e65100;font-size:.85rem;font-weight:600;margin-left:12px">排工條件尚未備齊：<?= implode('、', array_map('e', $warnings)) ?></span>
        <?php endif; } ?>
        <?php endif; ?>
    </div>
    <div class="d-flex gap-1">
        <?php if ($case && Auth::hasPermission('schedule.manage') && !in_array($case['status'], array('無效','客戶取消'))): ?>
        <a href="/schedule.php?action=create&case_id=<?= $case['id'] ?>" class="btn btn-sm" style="background:#FF9800;color:#fff">手動排工</a>
        <?php if (!empty($warnings)): ?>
        <button type="button" class="btn btn-success btn-sm" onclick="alert('排工條件尚未備齊：<?= implode('、', array_map('e', $warnings)) ?>\n\n請先補齊資料再使用智慧排工。')">智慧排工</button>
        <?php else: ?>
        <a href="/schedule.php?action=smart&case_id=<?= $case['id'] ?>" class="btn btn-success btn-sm">智慧排工</a>
        <?php endif; ?>
        <?php endif; ?>
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
// 新增案件時全部可編輯
if (!$case) { foreach ($canEdit as $k => $v) { $canEdit[$k] = true; } }
?>

<!-- 區域導航 -->
<?php if ($case): ?>
<div class="section-nav">
    <a href="#sec-basic" class="sec-link active">基本資料<?= $canEdit['basic'] ? '' : ' 🔒' ?></a>
    <a href="#sec-finance" class="sec-link">帳務資訊<?= $canEdit['finance'] ? '' : ' 🔒' ?></a>
    <a href="#sec-case-payments" class="sec-link">帳款交易 <span class="badge" style="font-size:.7rem;padding:1px 6px;background:#eee;color:#666"><?= count($case['case_payments'] ?? array()) ?></span></a>
    <a href="#sec-schedule" class="sec-link">施工時程<?= $canEdit['schedule'] ? '' : ' 🔒' ?></a>
    <a href="#sec-attach" class="sec-link">附件管理<?= $canEdit['attach'] ? '' : ' 🔒' ?></a>
    <a href="#sec-worklog" class="sec-link">施工回報 <span class="badge" style="font-size:.7rem;padding:1px 6px;background:#eee;color:#666"><?= count($worklogTimeline) ?></span></a>
    <a href="#sec-readiness" class="sec-link">排工驗證</a>
    <a href="#sec-materials" class="sec-link">預計材料 <span class="badge" style="font-size:.7rem;padding:1px 6px;background:#eee;color:#666"><?= count($case['material_estimates'] ?? array()) ?></span></a>
    <a href="#sec-site" class="sec-link">現場環境<?= $canEdit['site'] ? '' : ' 🔒' ?></a>
    <a href="#sec-skills" class="sec-link">所需技能<?= $canEdit['skills'] ? '' : ' 🔒' ?></a>
</div>
<?php endif; ?>

<form method="POST" class="mt-2" onsubmit="return validateCaseForm()">
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
            <div class="form-group" style="flex:0 0 100px">
                <label>客戶編號</label>
                <input type="text" class="form-control" value="<?= e($case ? ($case['customer_no'] ?? '') : peek_next_doc_number('customers')) ?>" readonly style="background:#f0f7ff;font-weight:600;color:var(--primary)">
            </div>
            <div class="form-group" style="flex:1;min-width:160px;position:relative">
                <label>客戶名稱</label>
                <input type="hidden" name="customer_id" id="customerId" value="<?= e($case['customer_id'] ?? '') ?>">
                <input type="text" name="customer_name" id="customerNameInput" class="form-control" value="<?= e($case['customer_name'] ?? '') ?>" placeholder="輸入客戶名稱搜尋..." autocomplete="off" onkeyup="onCustomerKeyup(event)">
                <div id="customerDropdown" class="customer-dropdown" style="display:none"></div>
                <?php if ($case && !empty($case['customer_id'])): ?>
                <small class="text-muted" id="customerInfo" style="position:absolute;bottom:-18px;left:0;font-size:.75rem">已關聯客戶 #<?= e($case['customer_id']) ?></small>
                <?php endif; ?>
            </div>
            <div class="form-group" style="flex:0 0 160px">
                <label>客戶分類</label>
                <select name="customer_category" class="form-control">
                    <option value="">請選擇</option>
                    <?php foreach (array('個人 / 住戶','一般公司 / 企業','製造 / 工廠','餐飲業','零售 / 店面','社區 / 管委會','機關 / 政府','金融 / 保險','醫療 / 健康照護','建設 / 營造','教育','宗教','旅宿業','上市櫃企業','休閒娛樂','物流 / 倉儲','協會 / 團體') as $cc): ?>
                    <option value="<?= $cc ?>" <?= ($case['customer_category'] ?? '') === $cc ? 'selected' : '' ?>><?= $cc ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="flex:0 0 auto">
                <button type="button" class="btn btn-outline btn-sm" onclick="openNewCustomerModal()" style="white-space:nowrap">+ 新增客戶</button>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group" style="flex:2;min-width:200px">
                <label>案件名稱 *</label>
                <input type="text" name="title" class="form-control" value="<?= e($case['title'] ?? '') ?>" required>
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
                <select name="company" id="companyInput" class="form-control">
                    <option value="">請選擇</option>
                    <?php if (isset($caseCompanyOptions)): foreach ($caseCompanyOptions as $opt): ?>
                    <option value="<?= e($opt['label']) ?>" <?= ($case['company'] ?? '') === $opt['label'] ? 'selected' : '' ?>><?= e($opt['label']) ?></option>
                    <?php endforeach; endif; ?>
                    <?php if (!empty($case['company']) && !in_array($case['company'], array_column($caseCompanyOptions ?? array(), 'label'))): ?>
                    <option value="<?= e($case['company']) ?>" selected><?= e($case['company']) ?></option>
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
            <div class="form-group">
                <label>聯絡人 *</label>
                <input type="text" name="contact_person" id="contactPersonInput" class="form-control" value="<?= e($case['contact_person'] ?? '') ?>" placeholder="聯絡人姓名" required>
            </div>
            <div class="form-group">
                <label>市話</label>
                <input type="text" name="customer_phone" id="customerPhoneInput" class="form-control" value="<?= e($case['customer_phone'] ?? '') ?>" placeholder="如 04-2222-3333">
            </div>
            <div class="form-group">
                <label>手機</label>
                <input type="text" name="customer_mobile" id="customerMobileInput" class="form-control" value="<?= e($case['customer_mobile'] ?? '') ?>" placeholder="如 0912-345-678">
            </div>
            <div class="form-group">
                <label>LINE ID</label>
                <input type="text" name="contact_line_id" id="contactLineInput" class="form-control" value="<?= e($case['contact_line_id'] ?? '') ?>" placeholder="LINE ID">
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
                <select name="status" class="form-control">
                    <?php foreach (CaseModel::progressOptions() as $v => $l): ?>
                    <option value="<?= $v ?>" <?= ($case['status'] ?? 'tracking') === $v ? 'selected' : '' ?>><?= e($l) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>狀態</label>
                <?php $defaultSubStatus = isset($case['sub_status']) ? $case['sub_status'] : '未指派'; ?>
                <select name="sub_status" class="form-control">
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
                <input type="text" name="address" class="form-control" value="<?= e($case['address'] ?? '') ?>">
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

    <div class="card" id="sec-quote-drawing">
        <div class="card-header d-flex justify-between align-center">
            <span>報價單 / 圖面</span>
            <?php if (Auth::hasPermission('quotations.manage') || Auth::hasPermission('quotations.view')): ?>
            <a href="/quotations.php?action=create&case_id=<?= $case['id'] ?>&customer_id=<?= urlencode($case['customer_id'] ?? '') ?>&customer_name=<?= urlencode($case['customer_name'] ?? $case['title'] ?? '') ?>&address=<?= urlencode($case['address'] ?? '') ?>&contact=<?= urlencode($case['contact_name'] ?? '') ?>&phone=<?= urlencode($case['contact_phone'] ?? '') ?>"
               class="btn btn-primary btn-sm">+ 建立報價單</a>
            <?php endif; ?>
        </div>

        <!-- 已關聯的報價單 -->
        <?php
        $caseQuotes = array();
        try {
            $qStmt = Database::getInstance()->prepare("SELECT id, quote_number, customer_name, total_amount, status, created_at FROM quotations WHERE case_id = ? ORDER BY created_at DESC");
            $qStmt->execute(array($case['id']));
            $caseQuotes = $qStmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {}
        ?>
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
                    <td><span class="badge"><?= e($q['status'] ?? '-') ?></span></td>
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
            <div class="form-group">
                <label>帳款是否結清</label>
                <select name="settlement_confirmed" class="form-control">
                    <option value="">請選擇</option>
                    <option value="0" <?= isset($case['settlement_confirmed']) && $case['settlement_confirmed'] === '0' ? 'selected' : '' ?>>未結清</option>
                    <option value="1" <?= ($case['settlement_confirmed'] ?? '') == '1' ? 'selected' : '' ?>>已結清</option>
                </select>
            </div>
            <div class="form-group">
                <label>帳款結清日期</label>
                <input type="date" name="settlement_date" class="form-control" value="<?= e($case['settlement_date'] ?? '') ?>">
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
    ?>
    <div class="card" id="sec-case-payments">
        <div class="card-header d-flex justify-between align-center">
            <span>帳款交易紀錄</span>
            <?php if (Auth::canEditSection('finance')): ?>
            <button type="button" class="btn btn-primary btn-sm" onclick="togglePaymentForm()">+ 新增交易</button>
            <?php endif; ?>
        </div>

        <?php if (Auth::canEditSection('finance')): ?>
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
                        <option value="尾款">尾款</option>
                        <option value="全款">全款</option>
                        <option value="其他">其他</option>
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
                    <label>金額 *</label>
                    <input type="number" id="pay_amount" class="form-control" placeholder="0">
                </div>
            </div>
            <div class="form-row">
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
                <thead><tr><th style="width:100px">日期</th><th style="width:60px">類別</th><th style="width:80px">方式</th><th class="text-right" style="width:90px">金額</th><th>備註</th><th style="width:50px">憑證</th><?php if (Auth::canEditSection('finance')): ?><th style="width:60px">操作</th><?php endif; ?></tr></thead>
                <tbody>
                    <?php $payTotal = 0; foreach ($casePayments as $cp): $payTotal += $cp['amount']; ?>
                    <tr style="cursor:pointer" onclick="openPaymentDetail(<?= $cp['id'] ?>)">
                        <td><?= e($cp['payment_date']) ?></td>
                        <td><span class="badge"><?= e($cp['payment_type'] ?: '-') ?></span></td>
                        <td><?= e($cp['transaction_type'] ?: '-') ?></td>
                        <td class="text-right" style="font-weight:600">$<?= number_format($cp['amount']) ?></td>
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
                        <?php if (Auth::canEditSection('finance')): ?>
                        <td><button type="button" class="btn btn-outline btn-sm" style="color:var(--danger);font-size:.75rem" onclick="deleteCasePayment(<?= $cp['id'] ?>)">刪除</button></td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot><tr><td colspan="3" class="text-right"><strong>合計</strong></td><td class="text-right" style="font-weight:700;color:var(--primary)">$<?= number_format($payTotal) ?></td><td colspan="3"></td></tr></tfoot>
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
                <input type="text" name="billing_tax_id" class="form-control" value="<?= e($case['billing_tax_id'] ?? '') ?>" placeholder="8碼統編">
            </div>
        </div>
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
                <input type="date" max="2099-12-31" name="planned_start_date" class="form-control" value="<?= e($case['planned_start_date'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>預計完工日</label>
                <input type="date" max="2099-12-31" name="planned_end_date" class="form-control" value="<?= e($case['planned_end_date'] ?? '') ?>">
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
                    <th style="width:40px"></th>
                </tr></thead>
                <tbody id="estMaterialsContainer">
                <?php
                $estMaterials = $case['material_estimates'] ?: array();
                $estIdx = 0;
                foreach ($estMaterials as $em):
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
                    <td><button type="button" class="btn btn-sm" style="background:#e53935;color:#fff;padding:4px 8px" onclick="this.closest('tr').remove()">✕</button></td>
                </tr>
                <?php $estIdx++; endforeach; ?>
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
            <div class="attach-type-card" id="atc-<?= $typeKey ?>">
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
                        <img src="<?= e($att['file_path']) ?>" class="atc-thumb" onclick="openLightbox('<?= e($att['file_path']) ?>')" alt="<?= e($att['file_name']) ?>">
                        <?php else: ?>
                        <a href="<?= e($att['file_path']) ?>" target="_blank" class="atc-filename">📄 <?= e($att['file_name']) ?></a>
                        <?php endif; ?>
                        <button type="button" class="atc-del" onclick="deleteAttachment(<?= $att['id'] ?>, '<?= $typeKey ?>')">✕</button>
                    </div>
                    <?php endforeach; ?>
                </div>
                <label class="atc-add-btn">
                    <input type="file" multiple accept="image/*,.pdf,.doc,.docx,.xls,.xlsx" style="display:none" onchange="uploadFiles(this, '<?= $typeKey ?>')">
                    <span>＋ 上傳<?= e($typeLabel) ?></span>
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
        foreach ($caseWorkLogs as $cwl) {
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
                        <input type="checkbox" name="needs_scissor_lift" value="1" <?= !empty($sc['needs_scissor_lift']) ? 'checked' : '' ?>>
                        <span>自走車</span>
                    </label>
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
<script src="/js/cases-form.js?v=20260405g"></script>

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
                        <option value="尾款">尾款</option>
                        <option value="全款">全款</option>
                        <option value="balance">尾款</option>
                        <option value="其他">其他</option>
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
                    <label>金額 *</label>
                    <input type="number" id="pd_amount" class="form-control">
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
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline" onclick="closePaymentDetail()">取消</button>
            <button type="button" class="btn btn-primary" onclick="savePaymentEdit()">儲存變更</button>
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
                                html = '<div class="atc-file atc-file-img" id="att-' + res.id + '"><img src="' + res.file_path + '" class="atc-thumb" onclick="openLightbox(\'' + res.file_path + '\')" alt="' + res.file_name + '"><button type="button" class="atc-del" onclick="deleteAttachment(' + res.id + ',\'' + fileType + '\')">✕</button></div>';
                            } else {
                                html = '<div class="atc-file" id="att-' + res.id + '"><a href="' + res.file_path + '" target="_blank" class="atc-filename">📄 ' + res.file_name + '</a><button type="button" class="atc-del" onclick="deleteAttachment(' + res.id + ',\'' + fileType + '\')">✕</button></div>';
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
    info.textContent = '已關聯客戶 ' + c.name + (c.customer_no ? ' (' + c.customer_no + ')' : '');
}

// 點擊外面關閉下拉
document.addEventListener('click', function(e) {
    if (!e.target.closest('#customerNameInput') && !e.target.closest('#customerDropdown')) {
        document.getElementById('customerDropdown').style.display = 'none';
    }
});

// 清除客戶關聯已整合到 onCustomerKeyup

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
                        <option value="尾款">尾款</option>
                        <option value="全款">全款</option>
                        <option value="balance">尾款</option>
                        <option value="其他">其他</option>
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
                    <label>金額 *</label>
                    <input type="number" id="pd_amount" class="form-control">
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
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline" onclick="closePaymentDetail()">取消</button>
            <button type="button" class="btn btn-primary" onclick="savePaymentEdit()">儲存變更</button>
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
        document.getElementById('pd_note').value = d.note || '';

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
        document.getElementById('pd_image').value = '';

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
