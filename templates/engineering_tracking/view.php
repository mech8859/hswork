<?php
$stageLabels = CaseModel::stageLabels();
$caseTypeOptions = CaseModel::caseTypeOptions();
$sourceOptions = CaseModel::caseSourceOptions();
// 自動計算階段
$caseModel = new CaseModel();
$currentStage = $caseModel->syncStage($case['id']);
?>
<div class="d-flex justify-between align-center flex-wrap gap-1 mb-2">
    <div>
        <h2><?= e($case['customer_name'] ?: $case['title']) ?></h2>
        <span class="badge"><?= e($case['case_number']) ?></span>
        <span class="badge" style="background:<?= CaseModel::stageColor($currentStage) ?>;color:#fff"><?= e(isset($stageLabels[$currentStage]) ? $stageLabels[$currentStage] : '') ?></span>
        <?php
        $liveReadiness = function_exists('compute_case_readiness_live') ? compute_case_readiness_live($case) : (isset($case['readiness']) ? $case['readiness'] : array());
        $warnings = get_readiness_warnings($liveReadiness, isset($case['case_type']) ? $case['case_type'] : 'new_install');
        if (!empty($warnings)):
        ?>
        <span style="color:#e65100;font-size:.85rem;font-weight:600;margin-left:12px">排工條件尚未備齊：<?= implode('、', array_map('e', $warnings)) ?></span>
        <?php endif; ?>
    </div>
    <div class="d-flex gap-1 flex-wrap">
        <a href="/cases.php?action=edit&id=<?= $case['id'] ?>" class="btn btn-primary btn-sm">編輯</a>
        <?= back_button('/engineering_tracking.php') ?>
    </div>
</div>

<!-- Stage Progress Bar -->
<div class="card mb-2">
    <div class="stage-progress">
        <?php for ($i = 1; $i <= 7; $i++): ?>
        <div class="stage-step <?= $i <= $currentStage ? 'active' : '' ?> <?= $i === $currentStage ? 'current' : '' ?>">
            <div class="stage-dot" style="<?= $i <= $currentStage ? 'background:'.CaseModel::stageColor($i) : '' ?>">
                <?= $i <= $currentStage ? '&#10003;' : $i ?>
            </div>
            <div class="stage-label"><?= e($stageLabels[$i]) ?></div>
        </div>
        <?php if ($i < 7): ?>
        <div class="stage-line <?= $i < $currentStage ? 'active' : '' ?>"></div>
        <?php endif; ?>
        <?php endfor; ?>
    </div>
</div>

<!-- 階段自動判斷提示 -->
<div class="card mb-2">
    <div style="padding:8px;font-size:.85rem;color:#666">
        <span style="color:<?= CaseModel::stageColor($currentStage) ?>;font-weight:600">● <?= e($stageLabels[$currentStage]) ?></span> — 系統自動判斷
        <span class="text-muted" style="margin-left:8px">（依據：場勘資料、報價單、成交金額、排工、施工回報、結案狀態）</span>
    </div>
</div>

<!-- Detail Cards -->
<div class="card mb-2">
    <div class="card-header">案件資訊</div>
    <div class="detail-grid">
        <div class="detail-item"><span class="detail-label">案件編號</span><span class="detail-value"><?= e($case['case_number']) ?></span></div>
        <div class="detail-item"><span class="detail-label">案件名稱</span><span class="detail-value"><?= e($case['title']) ?></span></div>
        <div class="detail-item"><span class="detail-label">案別</span><span class="detail-value"><?= e(isset($caseTypeOptions[$case['case_type']]) ? $caseTypeOptions[$case['case_type']] : '-') ?></span></div>
        <div class="detail-item"><span class="detail-label">案件來源</span><span class="detail-value"><?= e(isset($sourceOptions[$case['case_source']]) ? $sourceOptions[$case['case_source']] : '-') ?></span></div>
        <div class="detail-item"><span class="detail-label">進件公司</span><span class="detail-value"><?= e($case['company'] ?: '-') ?></span></div>
        <div class="detail-item"><span class="detail-label">分公司</span><span class="detail-value"><?= e(isset($case['branch_name']) ? $case['branch_name'] : '-') ?></span></div>
    </div>
</div>

<div class="card mb-2">
    <div class="card-header">客戶資訊</div>
    <div class="detail-grid">
        <div class="detail-item"><span class="detail-label">客戶名稱</span><span class="detail-value"><?= e($case['customer_name'] ?: '-') ?></span></div>
        <div class="detail-item"><span class="detail-label">聯絡人</span><span class="detail-value"><?= e($case['contact_person'] ?: '-') ?></span></div>
        <div class="detail-item"><span class="detail-label">電話</span><span class="detail-value"><?= e($case['customer_phone'] ?: '-') ?></span></div>
        <div class="detail-item"><span class="detail-label">手機</span><span class="detail-value"><?= e($case['customer_mobile'] ?: '-') ?></span></div>
        <div class="detail-item" style="grid-column:span 2"><span class="detail-label">地址</span><span class="detail-value"><?= e(trim(($case['city'] ?: '') . ($case['district'] ?: '') . ($case['address'] ?: '')) ?: '-') ?></span></div>
    </div>
</div>

<div class="card mb-2">
    <div class="card-header">工程資訊</div>
    <div class="detail-grid">
        <div class="detail-item"><span class="detail-label">預定開工日</span><span class="detail-value"><?= e($case['planned_start_date'] ?: '-') ?></span></div>
        <div class="detail-item"><span class="detail-label">預定完工日</span><span class="detail-value"><?= e($case['planned_end_date'] ?: '-') ?></span></div>
        <div class="detail-item"><span class="detail-label">預估工時</span><span class="detail-value"><?= $case['estimated_hours'] ? $case['estimated_hours'] . ' 小時' : '-' ?></span></div>
        <div class="detail-item"><span class="detail-label">施工進度</span><span class="detail-value"><?= !empty($case['total_visits']) ? '第' . (int)($case['current_visit'] ?: 1) . '/' . (int)$case['total_visits'] . ' 次' : '-' ?></span></div>
        <div class="detail-item"><span class="detail-label">難易度</span><span class="detail-value"><?= $case['difficulty'] ? $case['difficulty'] . '/5' : '-' ?></span></div>
        <div class="detail-item"><span class="detail-label">完工日期</span><span class="detail-value"><?= e($case['completed_date'] ?: '-') ?></span></div>
    </div>
</div>

<div class="card mb-2">
    <div class="card-header">業務資訊</div>
    <div class="detail-grid">
        <div class="detail-item"><span class="detail-label">承辦業務</span><span class="detail-value"><?= e(isset($case['sales_name']) ? $case['sales_name'] : '-') ?></span></div>
        <div class="detail-item"><span class="detail-label">成交金額</span><span class="detail-value"><?= $case['deal_amount'] ? '$'.number_format($case['deal_amount']) : '-' ?></span></div>
        <div class="detail-item"><span class="detail-label">成交日期</span><span class="detail-value"><?= e($case['deal_date'] ?: '-') ?></span></div>
        <div class="detail-item"><span class="detail-label">建立日期</span><span class="detail-value"><?= e($case['created_at']) ?></span></div>
    </div>
</div>

<!-- 排工紀錄 -->
<?php if (!empty($schedules)): ?>
<div class="card mb-2">
    <div class="card-header">排工紀錄</div>
    <div class="table-responsive">
        <table class="table">
            <thead><tr><th>排工日期</th><th>狀態</th><th>工程師</th></tr></thead>
            <tbody>
                <?php foreach ($schedules as $sch): ?>
                <tr>
                    <td><?= e($sch['schedule_date']) ?></td>
                    <td><?= e($sch['status']) ?></td>
                    <td><?= e($sch['engineers'] ?: '-') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<style>
.detail-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 8px; }
.detail-item { display: flex; flex-direction: column; }
.detail-label { font-size: .8rem; color: var(--gray-500); }
.stage-progress { display: flex; align-items: center; justify-content: center; padding: 16px 8px; flex-wrap: nowrap; overflow-x: auto; }
.stage-step { display: flex; flex-direction: column; align-items: center; min-width: 50px; }
.stage-dot { width: 32px; height: 32px; border-radius: 50%; background: var(--gray-200); color: var(--gray-500); display: flex; align-items: center; justify-content: center; font-size: .8rem; font-weight: 600; }
.stage-step.active .stage-dot { color: #fff; }
.stage-step.current .stage-dot { box-shadow: 0 0 0 3px rgba(33,150,243,.3); }
.stage-label { font-size: .7rem; color: var(--gray-500); margin-top: 4px; white-space: nowrap; }
.stage-step.active .stage-label { color: var(--gray-700); font-weight: 600; }
.stage-line { flex: 1; height: 2px; background: var(--gray-200); min-width: 20px; margin: 0 4px; margin-bottom: 20px; }
.stage-line.active { background: var(--primary); }
@media (max-width: 767px) { .detail-grid { grid-template-columns: 1fr; } }
</style>
