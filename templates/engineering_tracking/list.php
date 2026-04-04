<?php
$stageLabels = CaseModel::stageLabels();
$caseTypeOptions = CaseModel::caseTypeOptions();
$viewMode = isset($_GET['view']) ? $_GET['view'] : 'board';
$isOwnOnly = Auth::hasPermission('engineering_tracking.own') && !Auth::hasPermission('engineering_tracking.manage') && !Auth::hasPermission('engineering_tracking.view');

// 依階段分組 (ad + 4-7 + 已完工待簽核)
$grouped = array('ad' => array(), 4 => array(), 5 => array(), 6 => array(), 'cp' => array(), 7 => array());
foreach ($cases as $c) {
    $s = (int)$c['stage'];
    // 待安排派工查修
    if ($c['status'] === 'awaiting_dispatch') {
        $grouped['ad'][] = $c;
    // 已完工待簽核：stage=7 但 status=completed_pending
    } elseif ($s === 7 && $c['status'] === 'completed_pending') {
        $grouped['cp'][] = $c;
    } elseif (isset($grouped[$s])) {
        $grouped[$s][] = $c;
    }
}
$stageLabels['ad'] = '待安排派工查修';
$stageLabels['cp'] = '已完工待簽核';

function etBuildQS($exclude = array()) {
    $qs = '';
    $keys = array('engineer_id','branch_id','case_type','keyword','stage');
    foreach ($keys as $k) {
        if (in_array($k, $exclude)) continue;
        if (isset($_GET[$k]) && $_GET[$k] !== '') {
            $qs .= '&' . $k . '=' . urlencode($_GET[$k]);
        }
    }
    return $qs;
}

$currentEngineerId = isset($filters['engineer_id']) ? $filters['engineer_id'] : '';
$currentBranchId = isset($filters['branch_id']) ? $filters['branch_id'] : '';
$currentStage = isset($filters['stage']) ? $filters['stage'] : '';
?>

<div class="d-flex justify-between align-center flex-wrap gap-1 mb-2">
    <h2>工程追蹤表</h2>
    <div class="d-flex gap-1 align-center">
        <div class="view-toggle">
            <a href="?view=board<?= etBuildQS() ?>" class="toggle-btn <?= $viewMode === 'board' ? 'active' : '' ?>">看板</a>
            <a href="?view=list<?= etBuildQS() ?>" class="toggle-btn <?= $viewMode === 'list' ? 'active' : '' ?>">列表</a>
        </div>
    </div>
</div>

<!-- 篩選 -->
<div class="card mb-2">
    <form method="GET" class="filter-form">
        <div class="filter-row">
            <?php if (!$isOwnOnly): ?>
            <div class="form-group">
                <label>工程師</label>
                <select name="engineer_id" class="form-control">
                    <option value="">全部</option>
                    <?php foreach ($engineers as $eng): ?>
                    <option value="<?= $eng['id'] ?>" <?= ($currentEngineerId != '' && $currentEngineerId == $eng['id']) ? 'selected' : '' ?>><?= e($eng['real_name']) ?><?= $eng['is_active'] ? '' : '(離職)' ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <div class="form-group">
                <label>分公司</label>
                <select name="branch_id" class="form-control">
                    <option value="">全部</option>
                    <?php foreach ($branches as $b): ?>
                    <option value="<?= $b['id'] ?>" <?= ($currentBranchId != '' && $currentBranchId == $b['id']) ? 'selected' : '' ?>><?= e($b['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>案別</label>
                <select name="case_type" class="form-control">
                    <option value="">全部</option>
                    <?php foreach ($caseTypeOptions as $k => $v): ?>
                    <option value="<?= $k ?>" <?= (isset($_GET['case_type']) && $_GET['case_type'] === $k) ? 'selected' : '' ?>><?= e($v) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>關鍵字</label>
                <input type="text" name="keyword" class="form-control" placeholder="案件/客戶/地址" value="<?= e(isset($_GET['keyword']) ? $_GET['keyword'] : '') ?>">
            </div>
            <div class="form-group" style="align-self:flex-end">
                <input type="hidden" name="view" value="<?= e($viewMode) ?>">
                <input type="hidden" name="all" value="1">
                <?php if ($currentStage !== ''): ?><input type="hidden" name="stage" value="<?= e($currentStage) ?>"><?php endif; ?>
                <button type="submit" class="btn btn-primary btn-sm">篩選</button>
                <a href="/engineering_tracking.php?view=<?= e($viewMode) ?>&all=1" class="btn btn-outline btn-sm">清除</a>
            </div>
        </div>
    </form>
</div>

<!-- 統計卡片 -->
<?php
$engStages = array('ad', 4, 5, 6, 'cp', 7);
$engStageColors = array('ad' => '#9c27b0', 4 => '#4caf50', 5 => '#2196f3', 6 => '#f44336', 'cp' => '#ff9800', 7 => '#607d8b');
?>
<div class="bt-stats-row mb-2">
    <?php foreach ($engStages as $i):
        // 已完工待簽核從 grouped 算（model 沒拆）；其餘用 model stats
        if ($i === 'cp') {
            $cnt = count($grouped['cp']);
            $amt = 0;
            foreach ($grouped['cp'] as $gc) { $amt += (float)($gc['deal_amount'] ?: 0); }
            // 從 stage 7 扣掉 cp
            if (isset($stats[7])) {
                $stats[7]['count'] = $stats[7]['count'] - $cnt;
                $stats[7]['amount'] = $stats[7]['amount'] - $amt;
            }
        } else {
            $cnt = isset($stats[$i]) ? $stats[$i]['count'] : count($grouped[$i]);
            $amt = isset($stats[$i]) ? $stats[$i]['amount'] : 0;
        }
        $color = isset($engStageColors[$i]) ? $engStageColors[$i] : '#999';
    ?>
    <div class="bt-stat-card" style="border-left:4px solid <?= $color ?>">
        <div class="bt-stat-label"><?= e($stageLabels[$i]) ?></div>
        <div class="bt-stat-value"><?= $cnt ?> 件</div>
        <?php if ($amt > 0): ?>
        <div class="bt-stat-sub">$<?= number_format($amt) ?></div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
</div>

<?php if ($currentStage !== ''): ?>
<div class="stage-filter-bar mb-2">
    <span>篩選階段：<strong style="color:<?= CaseModel::stageColor((int)$currentStage) ?>"><?= e($stageLabels[(int)$currentStage]) ?></strong>（<?= count($cases) ?> 件）</span>
    <?php
        $clearStageQS = '?view=' . urlencode($viewMode);
        if ($currentEngineerId !== '') $clearStageQS .= '&engineer_id=' . urlencode($currentEngineerId);
        if ($currentBranchId !== '') $clearStageQS .= '&branch_id=' . urlencode($currentBranchId);
        if (isset($_GET['all'])) $clearStageQS .= '&all=1';
    ?>
    <a href="/engineering_tracking.php<?= $clearStageQS ?>" class="btn btn-outline btn-sm">✕ 取消篩選</a>
</div>
<?php endif; ?>

<?php if ($viewMode === 'board'): ?>
<!-- 看板模式 -->
<div class="kanban-board" id="kanbanBoard">
    <?php foreach ($engStages as $stage): ?>
    <div class="kanban-col">
        <div class="kanban-header" style="background:<?= isset($engStageColors[$stage]) ? $engStageColors[$stage] : '#999' ?>">
            <?= e($stageLabels[$stage]) ?> (<?= count($grouped[$stage]) ?>)
        </div>
        <div class="kanban-body">
            <?php if (empty($grouped[$stage])): ?>
            <div class="kanban-empty">暫無案件</div>
            <?php else: ?>
                <?php foreach ($grouped[$stage] as $c): ?>
                <a href="/engineering_tracking.php?action=view&id=<?= $c['id'] ?>" class="kanban-card">
                    <div class="kc-title"><?= e($c['title']) ?></div>
                    <div class="kc-customer"><?= e($c['customer_name'] ?: '-') ?></div>
                    <?php if ((int)$c['stage'] === 6 && isset($nextVisitMap[$c['id']])): ?>
                    <div class="kc-visit-warn">&#9888;&#65039; 預計 <?= date('m/d', strtotime($nextVisitMap[$c['id']])) ?> 施工</div>
                    <?php endif; ?>
                    <div class="kc-meta">
                        <span><?= e($c['branch_name'] ?: '-') ?></span>
                        <?php if (!empty($c['case_type']) && isset($caseTypeOptions[$c['case_type']])): ?>
                        <span><?= e($caseTypeOptions[$c['case_type']]) ?></span>
                        <?php endif; ?>
                    </div>
                    <?php if ($c['deal_amount'] > 0): ?>
                    <div class="kc-amount">$<?= number_format($c['deal_amount']) ?></div>
                    <?php endif; ?>
                    <div class="kc-footer">
                        <span><?= (int)$c['engineer_count'] > 0 ? $c['engineer_count'] . '人' : '未指派' ?></span>
                        <?php if (!empty($c['total_visits']) && $c['total_visits'] > 0): ?>
                        <span>第<?= (int)($c['current_visit'] ?: 1) ?>/<?= (int)$c['total_visits'] ?>次</span>
                        <?php endif; ?>
                        <span><?= !empty($c['planned_start_date']) ? date('m/d', strtotime($c['planned_start_date'])) : '-' ?></span>
                    </div>
                </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php else: ?>
<!-- 列表模式 -->
<div class="card">
    <?php if (empty($cases)): ?>
        <p class="text-muted text-center mt-2 mb-2">目前無追蹤案件</p>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>階段</th>
                    <th>案件名稱</th>
                    <th>客戶</th>
                    <th>據點</th>
                    <th>案別</th>
                    <th class="text-right">金額</th>
                    <th>施工進度</th>
                    <th>工程師</th>
                    <th>預定日期</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($cases as $c): ?>
                <tr>
                    <td><span class="stage-badge" style="background:<?= CaseModel::stageColor($c['stage']) ?>"><?= e($stageLabels[$c['stage']]) ?></span></td>
                    <td>
                        <a href="/engineering_tracking.php?action=view&id=<?= $c['id'] ?>"><?= e($c['title']) ?></a>
                        <?php if ((int)$c['stage'] === 6 && isset($nextVisitMap[$c['id']])): ?>
                        <span class="visit-warn-badge">&#9888;&#65039; 預計 <?= date('m/d', strtotime($nextVisitMap[$c['id']])) ?> 施工</span>
                        <?php endif; ?>
                    </td>
                    <td><?= e($c['customer_name'] ?: '-') ?></td>
                    <td><?= e($c['branch_name'] ?: '-') ?></td>
                    <td><?= isset($caseTypeOptions[$c['case_type']]) ? e($caseTypeOptions[$c['case_type']]) : '-' ?></td>
                    <td class="text-right"><?= $c['deal_amount'] > 0 ? '$' . number_format($c['deal_amount']) : '-' ?></td>
                    <td><?= !empty($c['total_visits']) && $c['total_visits'] > 0 ? '第' . (int)($c['current_visit'] ?: 1) . '/' . (int)$c['total_visits'] . '次' : '-' ?></td>
                    <td><?= (int)$c['engineer_count'] > 0 ? $c['engineer_count'] . '人' : '未指派' ?></td>
                    <td><?= !empty($c['planned_start_date']) ? date('m/d', strtotime($c['planned_start_date'])) : '-' ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<style>
.view-toggle { display:flex; border:1px solid var(--gray-300); border-radius:6px; overflow:hidden; }
.toggle-btn { padding:6px 14px; font-size:.85rem; color:var(--gray-600); text-decoration:none; background:#fff; border-right:1px solid var(--gray-300); }
.toggle-btn:last-child { border-right:none; }
.toggle-btn.active { background:var(--primary); color:#fff; }

.bt-stats-row { display:flex; gap:12px; flex-wrap:wrap; }
.bt-stat-card { background:#fff; border-radius:8px; padding:12px 16px; flex:1; min-width:120px; box-shadow:0 1px 3px rgba(0,0,0,.08); text-decoration:none; color:inherit; transition:box-shadow .15s, transform .15s; cursor:pointer; }
.bt-stat-card:hover { box-shadow:0 3px 10px rgba(0,0,0,.15); transform:translateY(-2px); text-decoration:none; }
.bt-stat-label { font-size:.8rem; color:var(--gray-500); }
.bt-stat-value { font-size:1.3rem; font-weight:700; }
.bt-stat-sub { font-size:.8rem; color:var(--gray-500); margin-top:2px; }

.kanban-board { display:flex; gap:12px; overflow-x:auto; padding-bottom:12px; min-height:300px; }
.kanban-col { flex:1; min-width:200px; background:var(--gray-100, #f3f4f6); border-radius:8px; display:flex; flex-direction:column; }
.kanban-header { color:#fff; font-weight:600; padding:10px 14px; border-radius:8px 8px 0 0; font-size:.9rem; }
.kanban-body { padding:8px; flex:1; overflow-y:auto; max-height:600px; }
.kanban-empty { text-align:center; color:var(--gray-400); padding:24px 0; font-size:.85rem; }
.kanban-card { display:block; background:#fff; border-radius:6px; padding:10px 12px; margin-bottom:8px; box-shadow:0 1px 2px rgba(0,0,0,.06); text-decoration:none; color:inherit; transition:box-shadow .15s; }
.kanban-card:hover { box-shadow:0 2px 8px rgba(0,0,0,.12); }
.kc-title { font-weight:600; font-size:.9rem; margin-bottom:4px; color:var(--gray-800); }
.kc-customer { font-size:.8rem; color:var(--gray-600); margin-bottom:4px; }
.kc-meta { display:flex; gap:6px; flex-wrap:wrap; font-size:.75rem; color:var(--gray-500); margin-bottom:4px; }
.kc-amount { font-size:.85rem; font-weight:600; color:var(--primary); }
.kc-footer { display:flex; justify-content:space-between; font-size:.75rem; color:var(--gray-400); margin-top:6px; padding-top:6px; border-top:1px solid var(--gray-100, #f3f4f6); }

.stage-badge { display:inline-block; padding:2px 10px; border-radius:10px; color:#fff; font-size:.8rem; font-weight:500; }
.stage-filter-bar { display:flex; align-items:center; justify-content:space-between; background:#fff; padding:10px 16px; border-radius:8px; border:1px solid var(--gray-200); }
.kc-visit-warn { font-size:.75rem; color:#e65100; font-weight:600; background:#fff3e0; padding:2px 6px; border-radius:4px; margin-bottom:4px; }
.visit-warn-badge { display:inline-block; font-size:.7rem; color:#e65100; font-weight:600; background:#fff3e0; padding:1px 6px; border-radius:4px; margin-left:6px; }

@media (max-width: 767px) {
    .kanban-board { flex-direction:column; }
    .kanban-col { max-width:none; min-width:auto; }
    .bt-stats-row { flex-direction:column; }
    .bt-stat-card { min-width:auto; }
}
</style>

<script>
if (window.innerWidth <= 767 && window.location.search.indexOf('view=') === -1) {
    var url = new URL(window.location.href);
    url.searchParams.set('view', 'list');
    window.location.replace(url.toString());
}
</script>
