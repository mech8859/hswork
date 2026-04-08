<?php
$caseTypeOptions = CaseModel::caseTypeOptions();
$sourceOptions = CaseModel::caseSourceOptions();
$isEdit = !empty($case);
if (!isset($canEdit)) $canEdit = true;
$readOnly = $isEdit && !$canEdit;
require __DIR__ . '/../_readonly_form_helper.php';
?>
<div class="d-flex justify-between align-center mb-2">
    <h2><?= $isEdit ? ($readOnly ? '檢視案件' : '編輯案件') : '新增進件' ?></h2>
    <?= back_button('/business_tracking.php') ?>
</div>

<form method="POST" action="/business_tracking.php?action=<?= $isEdit ? 'edit&id='.$case['id'] : 'create' ?>" class="<?= $readOnly ? 'form-readonly' : '' ?>">
    <input type="hidden" name="csrf_token" value="<?= e(Session::getCsrfToken()) ?>">

    <div class="card mb-2">
        <div class="card-header">案件資訊</div>
        <div class="form-grid">
            <div class="form-group" style="grid-column: span 2">
                <label>案件名稱 <span class="text-danger">*</span></label>
                <input type="text" name="title" class="form-control" value="<?= e(isset($case['title']) ? $case['title'] : '') ?>" required>
            </div>
            <div class="form-group">
                <label>案別</label>
                <select name="case_type" class="form-control">
                    <option value="">請選擇</option>
                    <?php foreach ($caseTypeOptions as $k => $v): ?>
                    <option value="<?= e($k) ?>" <?= (isset($case['case_type']) ? $case['case_type'] : '') === $k ? 'selected' : '' ?>><?= e($v) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>案件來源</label>
                <select name="case_source" class="form-control">
                    <option value="">請選擇</option>
                    <?php foreach ($sourceOptions as $k => $v): ?>
                    <option value="<?= e($k) ?>" <?= (isset($case['case_source']) ? $case['case_source'] : '') === $k ? 'selected' : '' ?>><?= e($v) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>進件公司</label>
                <select name="company" class="form-control">
                    <option value="">請選擇</option>
                    <option value="禾順監視數位" <?= (isset($case['company']) ? $case['company'] : '') === '禾順監視數位' ? 'selected' : '' ?>>禾順監視數位</option>
                    <option value="理創(政遠企業)" <?= (isset($case['company']) ? $case['company'] : '') === '理創(政遠企業)' ? 'selected' : '' ?>>理創(政遠企業)</option>
                </select>
            </div>
            <div class="form-group">
                <label>分公司</label>
                <select name="branch_id" class="form-control">
                    <option value="">請選擇</option>
                    <?php foreach ($branches as $b): ?>
                    <option value="<?= $b['id'] ?>" <?= (isset($case['branch_id']) ? $case['branch_id'] : '') == $b['id'] ? 'selected' : '' ?>><?= e($b['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>

    <div class="card mb-2">
        <div class="card-header">客戶資訊</div>
        <div class="form-grid">
            <div class="form-group">
                <label>客戶名稱</label>
                <input type="text" name="customer_name" class="form-control" value="<?= e(isset($case['customer_name']) ? $case['customer_name'] : '') ?>" id="customer_name_input">
                <input type="hidden" name="customer_id" value="<?= e(isset($case['customer_id']) ? $case['customer_id'] : '') ?>" id="customer_id_input">
            </div>
            <div class="form-group">
                <label>聯絡人</label>
                <input type="text" name="contact_person" class="form-control" value="<?= e(isset($case['contact_person']) ? $case['contact_person'] : '') ?>">
            </div>
            <div class="form-group">
                <label>電話</label>
                <input type="text" name="customer_phone" class="form-control" value="<?= e(isset($case['customer_phone']) ? $case['customer_phone'] : '') ?>">
            </div>
            <div class="form-group">
                <label>手機</label>
                <input type="text" name="customer_mobile" class="form-control" value="<?= e(isset($case['customer_mobile']) ? $case['customer_mobile'] : '') ?>">
            </div>
            <div class="form-group" style="grid-column: span 2">
                <label>聯絡地址</label>
                <input type="text" name="contact_address" class="form-control" value="<?= e(isset($case['contact_address']) ? $case['contact_address'] : '') ?>" placeholder="客戶公司地址或約定地點（非施工地址）">
            </div>
        </div>
    </div>

    <div class="card mb-2">
        <div class="card-header">施工地址</div>
        <div class="form-grid">
            <div class="form-group">
                <label>縣市</label>
                <input type="text" name="city" class="form-control" value="<?= e(isset($case['city']) ? $case['city'] : '') ?>" placeholder="如：台中市">
            </div>
            <div class="form-group">
                <label>鄉鎮區</label>
                <input type="text" name="district" class="form-control" value="<?= e(isset($case['district']) ? $case['district'] : '') ?>">
            </div>
            <div class="form-group" style="grid-column: span 2">
                <label>地址</label>
                <input type="text" name="address" class="form-control" value="<?= e(isset($case['address']) ? $case['address'] : '') ?>">
            </div>
        </div>
    </div>

    <div class="card mb-2">
        <div class="card-header">業務資訊</div>
        <div class="form-grid">
            <div class="form-group">
                <label>承辦業務</label>
                <select name="sales_id" class="form-control">
                    <option value="">請選擇</option>
                    <?php foreach ($salespeople as $sp): ?>
                    <option value="<?= $sp['id'] ?>" <?= (isset($case['sales_id']) ? $case['sales_id'] : Auth::id()) == $sp['id'] ? 'selected' : '' ?>><?= e($sp['real_name']) ?><?= $sp['is_active'] ? '' : '(離職)' ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>預估金額</label>
                <input type="number" name="deal_amount" class="form-control" value="<?= e(isset($case['deal_amount']) ? $case['deal_amount'] : '') ?>" placeholder="未稅">
            </div>
            <div class="form-group" style="grid-column: span 2">
                <label>備註</label>
                <textarea name="sales_note" class="form-control" rows="3"><?= e(isset($case['sales_note']) ? $case['sales_note'] : '') ?></textarea>
            </div>
        </div>
    </div>

    <div class="card mb-2">
        <div class="card-header">案件進度</div>
        <div class="form-grid">
            <div class="form-group">
                <label>狀態</label>
                <select name="sub_status" class="form-control" id="sub_status_select">
                    <option value="">請選擇</option>
                    <optgroup label="── 進件 ──">
                        <option value="未指派" <?= (isset($case['sub_status']) ? $case['sub_status'] : '') === '未指派' ? 'selected' : '' ?>>未指派</option>
                        <option value="待聯絡" <?= (isset($case['sub_status']) ? $case['sub_status'] : '') === '待聯絡' ? 'selected' : '' ?>>待聯絡</option>
                        <option value="電話不通或未接" <?= (isset($case['sub_status']) ? $case['sub_status'] : '') === '電話不通或未接' ? 'selected' : '' ?>>電話不通或未接</option>
                    </optgroup>
                    <optgroup label="── 場勘 ──">
                        <option value="已聯絡安排場勘" <?= (isset($case['sub_status']) ? $case['sub_status'] : '') === '已聯絡安排場勘' ? 'selected' : '' ?>>已聯絡安排場勘</option>
                    </optgroup>
                    <optgroup label="── 報價 ──">
                        <option value="已聯絡電話報價" <?= (isset($case['sub_status']) ? $case['sub_status'] : '') === '已聯絡電話報價' ? 'selected' : '' ?>>已聯絡電話報價</option>
                        <option value="已會勘未報價" <?= (isset($case['sub_status']) ? $case['sub_status'] : '') === '已會勘未報價' ? 'selected' : '' ?>>已會勘未報價</option>
                        <option value="已報價待追蹤" <?= (isset($case['sub_status']) ? $case['sub_status'] : '') === '已報價待追蹤' ? 'selected' : '' ?>>已報價待追蹤</option>
                        <option value="規劃或預算案" <?= (isset($case['sub_status']) ? $case['sub_status'] : '') === '規劃或預算案' ? 'selected' : '' ?>>規劃或預算案</option>
                    </optgroup>
                    <optgroup label="── 成交 ──">
                        <option value="電話報價成交" <?= (isset($case['sub_status']) ? $case['sub_status'] : '') === '電話報價成交' ? 'selected' : '' ?>>電話報價成交</option>
                        <option value="已成交" <?= (isset($case['sub_status']) ? $case['sub_status'] : '') === '已成交' ? 'selected' : '' ?>>已成交</option>
                        <option value="跨月成交" <?= (isset($case['sub_status']) ? $case['sub_status'] : '') === '跨月成交' ? 'selected' : '' ?>>跨月成交</option>
                        <option value="現簽" <?= (isset($case['sub_status']) ? $case['sub_status'] : '') === '現簽' ? 'selected' : '' ?>>現簽</option>
                    </optgroup>
                    <optgroup label="── 未成交/無效 ──">
                        <option value="已報價無意願" <?= (isset($case['sub_status']) ? $case['sub_status'] : '') === '已報價無意願' ? 'selected' : '' ?>>已報價無意願</option>
                        <option value="報價無下文" <?= (isset($case['sub_status']) ? $case['sub_status'] : '') === '報價無下文' ? 'selected' : '' ?>>報價無下文</option>
                        <option value="無效" <?= (isset($case['sub_status']) ? $case['sub_status'] : '') === '無效' ? 'selected' : '' ?>>無效</option>
                        <option value="客戶毀約" <?= (isset($case['sub_status']) ? $case['sub_status'] : '') === '客戶毀約' ? 'selected' : '' ?>>客戶毀約</option>
                    </optgroup>
                </select>
            </div>
            <!-- 場勘日期+時間+拜訪方式 -->
            <div class="form-group" id="survey_date_group" style="<?= (isset($case['sub_status']) && $case['sub_status'] === '已聯絡安排場勘') ? '' : 'display:none' ?>">
                <label>場勘日期 <span class="text-danger">*</span></label>
                <input type="date" name="survey_date" class="form-control" value="<?= e(isset($case['survey_date']) ? $case['survey_date'] : '') ?>">
            </div>
            <div class="form-group" id="survey_time_group" style="<?= (isset($case['sub_status']) && $case['sub_status'] === '已聯絡安排場勘') ? '' : 'display:none' ?>">
                <label>場勘時間</label>
                <input type="time" name="survey_time" class="form-control" value="<?= e(isset($case['survey_time']) ? $case['survey_time'] : '') ?>">
            </div>
            <div class="form-group" id="visit_method_group" style="<?= (isset($case['sub_status']) && $case['sub_status'] === '已聯絡安排場勘') ? '' : 'display:none' ?>">
                <label>拜訪方式</label>
                <select name="visit_method" class="form-control">
                    <option value="">請選擇</option>
                    <option value="現場" <?= (isset($case['visit_method']) ? $case['visit_method'] : '') === '現場' ? 'selected' : '' ?>>現場</option>
                    <option value="電話" <?= (isset($case['visit_method']) ? $case['visit_method'] : '') === '電話' ? 'selected' : '' ?>>電話</option>
                    <option value="LINE" <?= (isset($case['visit_method']) ? $case['visit_method'] : '') === 'LINE' ? 'selected' : '' ?>>LINE</option>
                    <option value="視訊" <?= (isset($case['visit_method']) ? $case['visit_method'] : '') === '視訊' ? 'selected' : '' ?>>視訊</option>
                </select>
            </div>
            <!-- 成交日期 -->
            <div class="form-group" id="deal_date_group" style="<?= (isset($case['sub_status']) && in_array($case['sub_status'], array('已成交','跨月成交','現簽','電話報價成交'))) ? '' : 'display:none' ?>">
                <label>成交日期 <span class="text-danger">*</span></label>
                <input type="date" name="deal_date" class="form-control" value="<?= e(isset($case['deal_date']) ? $case['deal_date'] : '') ?>">
            </div>
            <!-- 電話不通撥打紀錄 -->
            <?php
                $callAttempts = array();
                if ($isEdit && !empty($case['call_attempts'])) {
                    $decoded = json_decode($case['call_attempts'], true);
                    if (is_array($decoded)) $callAttempts = $decoded;
                }
            ?>
            <div class="form-group" id="call_attempts_group" style="grid-column:span 2;<?= (isset($case['sub_status']) && $case['sub_status'] === '電話不通或未接') ? '' : 'display:none' ?>">
                <label>撥打紀錄</label>
                <div id="call_list">
                    <?php foreach ($callAttempts as $i => $ca): ?>
                    <div class="d-flex gap-1 mb-1" style="align-items:center">
                        <span style="min-width:24px"><?= $i + 1 ?>.</span>
                        <input type="date" name="call_dates[]" class="form-control" value="<?= e($ca['date']) ?>" style="flex:1">
                        <input type="text" name="call_notes[]" class="form-control" value="<?= e(isset($ca['note']) ? $ca['note'] : '') ?>" placeholder="備註" style="flex:2">
                    </div>
                    <?php endforeach; ?>
                </div>
                <button type="button" class="btn btn-outline btn-sm" onclick="addCallAttempt()" style="margin-top:4px">+ 新增撥打紀錄</button>
                <?php if (count($callAttempts) >= 3): ?>
                <div style="margin-top:8px;padding:8px 12px;background:#fff3cd;border-radius:6px;font-size:.85rem;color:#856404">
                    已撥打 <?= count($callAttempts) ?> 次，是否考慮移至「無效」？
                </div>
                <?php endif; ?>
            </div>
            <!-- 未成交原因 -->
            <div class="form-group" id="lost_reason_group" style="grid-column:span 2;<?= (isset($case['sub_status']) && in_array($case['sub_status'], array('已報價無意願','報價無下文','無效','客戶毀約'))) ? '' : 'display:none' ?>">
                <label>未成交原因 <span class="text-danger">*</span></label>
                <textarea name="lost_reason" class="form-control" rows="2" placeholder="請填寫未成交原因"><?= e(isset($case['lost_reason']) ? $case['lost_reason'] : '') ?></textarea>
            </div>
        </div>
    </div>

    <?php if ($isEdit && isset($case['sub_status']) && in_array($case['sub_status'], array('已聯絡安排場勘','已聯絡電話報價','已會勘未報價','已報價待追蹤','規劃或預算案'))): ?>
    <div class="card mb-2" style="background:#f0f7ff;border-color:var(--primary)">
        <div style="padding:12px;display:flex;align-items:center;justify-content:space-between">
            <span>此案件可建立報價單</span>
            <a href="/quotations.php?action=create&case_id=<?= $case['id'] ?>" class="btn btn-primary btn-sm">建立報價單</a>
        </div>
    </div>
    <?php endif; ?>

    <div class="d-flex gap-1">
        <button type="submit" class="btn btn-primary"><?= $isEdit ? '儲存變更' : '建立進件' ?></button>
        <a href="/business_tracking.php" class="btn btn-outline">取消</a>
    </div>
</form>

<script>
(function() {
    var sel = document.getElementById('sub_status_select');
    var surveyGroup = document.getElementById('survey_date_group');
    var surveyTimeGroup = document.getElementById('survey_time_group');
    var visitMethodGroup = document.getElementById('visit_method_group');
    var dealGroup = document.getElementById('deal_date_group');
    var lostGroup = document.getElementById('lost_reason_group');
    var dealStatuses = ['已成交','跨月成交','現簽','電話報價成交'];
    var lostStatuses = ['已報價無意願','報價無下文','無效','客戶毀約'];

    var callGroup = document.getElementById('call_attempts_group');

    sel.addEventListener('change', function() {
        var v = this.value;
        surveyGroup.style.display = (v === '已聯絡安排場勘') ? '' : 'none';
        surveyTimeGroup.style.display = (v === '已聯絡安排場勘') ? '' : 'none';
        visitMethodGroup.style.display = (v === '已聯絡安排場勘') ? '' : 'none';
        dealGroup.style.display = (dealStatuses.indexOf(v) !== -1) ? '' : 'none';
        lostGroup.style.display = (lostStatuses.indexOf(v) !== -1) ? '' : 'none';
        callGroup.style.display = (v === '電話不通或未接') ? '' : 'none';
    });
})();

var callCount = <?= count($callAttempts) ?>;
function addCallAttempt() {
    callCount++;
    var list = document.getElementById('call_list');
    var div = document.createElement('div');
    div.className = 'd-flex gap-1 mb-1';
    div.style.alignItems = 'center';
    div.innerHTML = '<span style="min-width:24px">' + callCount + '.</span>' +
        '<input type="date" name="call_dates[]" class="form-control" value="' + new Date().toISOString().substring(0,10) + '" style="flex:1">' +
        '<input type="text" name="call_notes[]" class="form-control" placeholder="備註" style="flex:2">';
    list.appendChild(div);

    if (callCount >= 3) {
        if (confirm('已撥打 ' + callCount + ' 次，是否移至「無效」？')) {
            document.getElementById('sub_status_select').value = '無效';
            document.getElementById('sub_status_select').dispatchEvent(new Event('change'));
        }
    }
}
</script>

<style>
.form-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; }
@media (max-width: 767px) { .form-grid { grid-template-columns: 1fr; } .form-grid .form-group[style*="span 2"] { grid-column: span 1; } }
</style>
