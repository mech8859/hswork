<div class="d-flex justify-between align-center flex-wrap gap-1 mb-2">
    <div>
        <h2><?= e($case['case_number']) ?></h2>
        <span class="badge <?= CaseModel::statusBadge($case['status']) ?>"><?= e(CaseModel::statusLabel($case['status'])) ?></span>
        <span class="badge badge-primary"><?= e(CaseModel::typeLabel($case['case_type'])) ?></span>
        <span class="text-muted" style="font-size:.85rem"><?= e($case['branch_name']) ?></span>
    </div>
    <div class="d-flex gap-1">
        <a href="/cases.php?action=edit&id=<?= $case['id'] ?>" class="btn btn-primary btn-sm">編輯</a>
        <a href="/cases.php" class="btn btn-outline btn-sm">返回列表</a>
    </div>
</div>

<!-- 排工條件驗證 -->
<?php
$warnings = get_readiness_warnings($case['readiness'] ?: []);
if (!empty($warnings)):
?>
<div class="alert alert-warning">
    <strong>排工條件尚未備齊：</strong>
    <?= implode('、', array_map('e', $warnings)) ?>
</div>
<?php endif; ?>

<!-- 基本資料 -->
<div class="card">
    <div class="card-header">基本資料</div>
    <div class="detail-grid">
        <div class="detail-item">
            <span class="detail-label">案件名稱</span>
            <span class="detail-value"><?= e($case['title']) ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">施工地址</span>
            <span class="detail-value"><?= e($case['address'] ?: '-') ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">難易度</span>
            <span class="detail-value stars"><?= str_repeat('&#9733;', $case['difficulty']) ?><?= str_repeat('&#9734;', 5 - $case['difficulty']) ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">預估工時</span>
            <span class="detail-value"><?= $case['estimated_hours'] ? $case['estimated_hours'] . ' 小時' : '-' ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">施工進度</span>
            <span class="detail-value"><?= $case['current_visit'] ?> / <?= $case['total_visits'] ?> 次</span>
        </div>
        <div class="detail-item">
            <span class="detail-label">最多施工人數</span>
            <span class="detail-value"><?= $case['max_engineers'] ?> 人</span>
        </div>
        <div class="detail-item">
            <span class="detail-label">業務負責人</span>
            <span class="detail-value"><?= e($case['sales_name'] ?: '-') ?></span>
        </div>
        <?php if ($case['ragic_id']): ?>
        <div class="detail-item">
            <span class="detail-label">Ragic ID</span>
            <span class="detail-value"><?= e($case['ragic_id']) ?></span>
        </div>
        <?php endif; ?>
    </div>
    <?php if ($case['description']): ?>
    <div class="mt-1">
        <span class="detail-label">案件說明</span>
        <p><?= nl2br(e($case['description'])) ?></p>
    </div>
    <?php endif; ?>
</div>

<!-- 現場環境 -->
<?php if (!empty($case['site_conditions'])): ?>
<?php $sc = $case['site_conditions']; ?>
<div class="card">
    <div class="card-header">現場環境</div>
    <div class="detail-grid">
        <?php if ($sc['structure_type']): ?>
        <div class="detail-item">
            <span class="detail-label">建築結構</span>
            <span class="detail-value">
                <?php
                $structMap = ['RC'=>'RC結構','steel_sheet'=>'鐵皮','open_area'=>'空曠地','construction_site'=>'建築工地'];
                echo implode('、', array_map(fn($v) => $structMap[$v] ?? $v, explode(',', $sc['structure_type'])));
                ?>
            </span>
        </div>
        <?php endif; ?>
        <?php if ($sc['conduit_type']): ?>
        <div class="detail-item">
            <span class="detail-label">管線需求</span>
            <span class="detail-value">
                <?php
                $condMap = ['PVC'=>'PVC','EMT'=>'EMT','RSG'=>'RSG','molding'=>'壓條','wall_penetration'=>'穿牆','aerial'=>'架空','underground'=>'切地埋管'];
                echo implode('、', array_map(fn($v) => $condMap[$v] ?? $v, explode(',', $sc['conduit_type'])));
                ?>
            </span>
        </div>
        <?php endif; ?>
        <?php if ($sc['floor_count']): ?>
        <div class="detail-item"><span class="detail-label">樓層數</span><span class="detail-value"><?= $sc['floor_count'] ?></span></div>
        <?php endif; ?>
        <div class="detail-item">
            <span class="detail-label">設施</span>
            <span class="detail-value">
                <?= $sc['has_elevator'] ? '有電梯' : '' ?>
                <?= $sc['has_ladder_needed'] ? '需要梯子' : '' ?>
                <?= (!$sc['has_elevator'] && !$sc['has_ladder_needed']) ? '-' : '' ?>
            </span>
        </div>
    </div>
    <?php if (!empty($sc['special_requirements'])): ?>
    <div class="mt-1"><span class="detail-label">特殊需求</span><p><?= nl2br(e($sc['special_requirements'])) ?></p></div>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- 聯絡人 -->
<?php if (!empty($case['contacts'])): ?>
<div class="card">
    <div class="card-header">聯絡人</div>
    <div class="table-responsive">
        <table class="table">
            <thead><tr><th>姓名</th><th>電話</th><th>角色</th></tr></thead>
            <tbody>
                <?php foreach ($case['contacts'] as $c): ?>
                <tr>
                    <td><?= e($c['contact_name']) ?></td>
                    <td><a href="tel:<?= e($c['contact_phone'] ?? '') ?>"><?= e($c['contact_phone'] ?? '-') ?></a></td>
                    <td><?= e($c['contact_role'] ?? '-') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- 所需技能 -->
<?php if (!empty($case['required_skills'])): ?>
<div class="card">
    <div class="card-header">所需技能</div>
    <div class="d-flex flex-wrap gap-1">
        <?php foreach ($case['required_skills'] as $rs): ?>
        <div class="skill-badge">
            <span><?= e($rs['skill_name']) ?></span>
            <span class="stars"><?= str_repeat('&#9733;', $rs['min_proficiency']) ?></span>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- 收款記錄 -->
<?php if (!empty($case['payments'])): ?>
<div class="card">
    <div class="card-header">收款記錄</div>
    <div class="table-responsive">
        <table class="table">
            <thead><tr><th>類型</th><th>方式</th><th>金額</th><th>日期</th></tr></thead>
            <tbody>
                <?php
                $paymentTypes = ['deposit'=>'訂金','final_payment'=>'尾款'];
                $paymentMethods = ['cash'=>'現金','transfer'=>'匯款','check'=>'支票'];
                foreach ($case['payments'] as $p):
                ?>
                <tr>
                    <td><?= $paymentTypes[$p['payment_type']] ?? $p['payment_type'] ?></td>
                    <td><?= $paymentMethods[$p['payment_method']] ?? $p['payment_method'] ?></td>
                    <td>$<?= number_format($p['amount']) ?></td>
                    <td><?= format_date($p['payment_date']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<style>
.detail-grid { display: grid; grid-template-columns: 1fr; gap: 8px; }
.detail-item { display: flex; flex-direction: column; }
.detail-label { font-size: .8rem; color: var(--gray-500); }
.detail-value { font-size: .95rem; }
.skill-badge {
    display: inline-flex; align-items: center; gap: 4px;
    background: var(--gray-100); padding: 4px 10px; border-radius: 16px;
    font-size: .85rem;
}
@media (min-width: 768px) {
    .detail-grid { grid-template-columns: repeat(2, 1fr); }
}
@media (min-width: 1024px) {
    .detail-grid { grid-template-columns: repeat(3, 1fr); }
}
</style>
