<?php
$caseTypeLabel = array(
    'new_install' => '新案',
    'addition'    => '老客戶追加',
    'old_repair'  => '舊客維修',
    'new_repair'  => '新客維修',
    'maintenance' => '維護保養',
);
$levelLabel = array(
    'leader'    => '組長',
    'senior'    => '資深',
    'regular'   => '一般',
    'junior'    => '初級',
    'probation' => '試用',
);

$summary = $analysis['summary'];
$total = $analysis['total'];

// 月份切換 URL helper
$mkUrl = function($y, $m, $bid = null) {
    global $branchFilter;
    $b = $bid !== null ? $bid : $branchFilter;
    $u = '/reports.php?action=schedule_analysis&year=' . $y . '&month=' . $m;
    if ($b) $u .= '&branch_id=' . $b;
    return $u;
};

// 上下月
$prevY = $year; $prevM = $month - 1;
if ($prevM < 1) { $prevM = 12; $prevY--; }
$nextY = $year; $nextM = $month + 1;
if ($nextM > 12) { $nextM = 1; $nextY++; }
?>
<div class="d-flex justify-between align-center flex-wrap gap-1 mb-2">
    <h2>主管排工模式分析 <span style="font-size:.7em;color:var(--gray-500);font-weight:normal"><?= $year ?>年<?= $month ?>月（共 <?= $total ?> 張排工）</span></h2>
    <a href="/reports.php" class="btn btn-outline btn-sm">← 返回報表</a>
</div>

<div class="card" style="padding:10px 14px;margin-bottom:10px">
    <form method="GET" action="/reports.php" class="d-flex gap-2 align-center flex-wrap" style="margin:0">
        <input type="hidden" name="action" value="schedule_analysis">
        <a href="<?= $mkUrl($prevY, $prevM) ?>" class="btn btn-outline btn-sm">← <?= $prevY ?>/<?= $prevM ?></a>
        <select name="year" onchange="this.form.submit()" class="form-control" style="width:auto">
            <?php for ($y = (int)date('Y'); $y >= (int)date('Y') - 2; $y--): ?>
            <option value="<?= $y ?>" <?= $year == $y ? 'selected' : '' ?>><?= $y ?> 年</option>
            <?php endfor; ?>
        </select>
        <select name="month" onchange="this.form.submit()" class="form-control" style="width:auto">
            <?php for ($m = 1; $m <= 12; $m++): ?>
            <option value="<?= $m ?>" <?= $month == $m ? 'selected' : '' ?>><?= $m ?> 月</option>
            <?php endfor; ?>
        </select>
        <a href="<?= $mkUrl($nextY, $nextM) ?>" class="btn btn-outline btn-sm"><?= $nextY ?>/<?= $nextM ?> →</a>
        <span style="color:#ccc">|</span>
        <select name="branch_id" onchange="this.form.submit()" class="form-control" style="width:auto;min-width:140px">
            <option value="">全部分公司</option>
            <?php foreach ($allBranches as $b): ?>
            <option value="<?= (int)$b['id'] ?>" <?= $branchFilter == (int)$b['id'] ? 'selected' : '' ?>><?= e($b['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <span style="flex:1"></span>
        <a href="<?= $mkUrl((int)date('Y', strtotime('-1 month')), (int)date('n', strtotime('-1 month')), 0) ?>"
           class="btn btn-outline btn-sm" title="跳到上個月">📅 上個月</a>
    </form>
</div>

<?php if ($total === 0): ?>
<div class="card"><p class="text-muted text-center" style="padding:40px">此月份尚無排工資料</p></div>
<?php else: ?>

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(360px,1fr));gap:14px;margin-bottom:14px">

    <!-- 1. 隊伍大小分布 -->
    <div class="card">
        <div class="card-header">隊伍大小分布</div>
        <div style="padding:8px 14px">
            <?php
            $tsTotal = array_sum($summary['team_size']);
            foreach ($summary['team_size'] as $size => $n):
                $pct = $tsTotal > 0 ? round($n / $tsTotal * 100, 1) : 0;
            ?>
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px">
                <span style="width:60px;font-weight:600"><?= $size ?> 人</span>
                <div style="flex:1;height:18px;background:#eee;border-radius:9px;overflow:hidden">
                    <div style="height:100%;background:#1565c0;width:<?= $pct ?>%"></div>
                </div>
                <span style="width:80px;text-align:right;font-size:.85rem"><?= $n ?> 張 (<?= $pct ?>%)</span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- 2. 主工程師人數分布 -->
    <div class="card">
        <div class="card-header">組內主工程師（can_lead）人數</div>
        <div style="padding:8px 14px">
            <?php
            $lcTotal = array_sum($summary['lead_count']);
            foreach ($summary['lead_count'] as $n => $cnt):
                $pct = $lcTotal > 0 ? round($cnt / $lcTotal * 100, 1) : 0;
                $color = $n >= 2 ? '#c62828' : ($n === 1 ? '#2e7d32' : '#666');
                $note = $n >= 2 ? ' ⚠ 多主工程師' : ($n === 1 ? '' : ' (無主工程師)');
            ?>
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px">
                <span style="width:80px;font-weight:600;color:<?= $color ?>"><?= $n ?> 個<?= $note ?></span>
                <div style="flex:1;height:18px;background:#eee;border-radius:9px;overflow:hidden">
                    <div style="height:100%;background:<?= $color ?>;width:<?= $pct ?>%"></div>
                </div>
                <span style="width:80px;text-align:right;font-size:.85rem"><?= $cnt ?> 張 (<?= $pct ?>%)</span>
            </div>
            <?php endforeach; ?>
            <?php if (!empty($summary['multi_lead'])): ?>
            <div style="margin-top:8px;padding:8px 10px;background:#fff3e0;border-radius:4px;font-size:.85rem">
                <strong style="color:#e65100">⚠ 雙主工程師案例 <?= count($summary['multi_lead']) ?> 張</strong>
                <a href="javascript:void(0)" onclick="document.getElementById('multiLeadList').style.display=document.getElementById('multiLeadList').style.display==='none'?'block':'none'" style="margin-left:6px;font-size:.8rem">展開/收合</a>
                <div id="multiLeadList" style="display:none;margin-top:6px;max-height:300px;overflow:auto">
                    <?php foreach ($summary['multi_lead'] as $s):
                        $names = array_map(function($e) { return $e['name'] . ($e['can_lead'] ? '(主)' : ''); }, $s['engs']);
                    ?>
                    <div style="font-size:.8rem;padding:3px 0;border-bottom:1px dashed #eee">
                        <?= e($s['meta']['date']) ?> <?= e($s['meta']['case_number']) ?> <?= e($s['meta']['branch_name']) ?> ｜ <?= e(implode(' + ', $names)) ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

</div>

<!-- 3. 配對熱力圖 -->
<div class="card mb-2">
    <div class="card-header">最常配對的工程師對 (Top 30)</div>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th style="width:60px;text-align:right">次數</th>
                    <th>工程師 A</th>
                    <th>工程師 B</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($analysis['pairs'] as $p): ?>
                <tr>
                    <td style="text-align:right;font-weight:600;color:<?= $p['count'] >= 10 ? '#c62828' : ($p['count'] >= 5 ? '#e65100' : '#1565c0') ?>"><?= $p['count'] ?></td>
                    <td><?= e($p['a_name']) ?></td>
                    <td><?= e($p['b_name']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div style="padding:8px 14px;font-size:.82rem;color:#666;background:#f5f5f5">
        💡 高頻配對代表「默契對」— 可考慮自動補進 engineer_pairs 表加成默契分數
    </div>
</div>

<!-- 4. 案件類型 vs 隊伍組成 -->
<div class="card mb-2">
    <div class="card-header">案件類型 × 隊伍大小</div>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>案件類型</th>
                    <th class="text-right">總張數</th>
                    <?php
                    $allSizes = array();
                    foreach ($summary['case_type_size'] as $sizes) {
                        foreach (array_keys($sizes) as $sz) $allSizes[$sz] = true;
                    }
                    ksort($allSizes);
                    foreach (array_keys($allSizes) as $sz):
                    ?>
                    <th class="text-right"><?= $sz ?> 人</th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($summary['case_type_size'] as $ct => $sizes):
                    $ctTotal = array_sum($sizes);
                ?>
                <tr>
                    <td><?= e(isset($caseTypeLabel[$ct]) ? $caseTypeLabel[$ct] : $ct) ?></td>
                    <td class="text-right" style="font-weight:600"><?= $ctTotal ?></td>
                    <?php foreach (array_keys($allSizes) as $sz):
                        $cnt = isset($sizes[$sz]) ? $sizes[$sz] : 0;
                        $pct = $ctTotal > 0 ? round($cnt / $ctTotal * 100) : 0;
                    ?>
                    <td class="text-right"><?= $cnt ? $cnt . ' (' . $pct . '%)' : '-' ?></td>
                    <?php endforeach; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(340px,1fr));gap:14px;margin-bottom:14px">

    <!-- 5. 各分公司排工 -->
    <div class="card">
        <div class="card-header">各分公司排工</div>
        <div class="table-responsive">
            <table class="table" style="font-size:.9rem">
                <thead>
                    <tr><th>分公司</th><th class="text-right">總張數</th><th class="text-right">雙主</th><th class="text-right">比例</th></tr>
                </thead>
                <tbody>
                    <?php
                    uasort($analysis['branches'], function($a, $b) { return $b['total'] - $a['total']; });
                    foreach ($analysis['branches'] as $bn => $st):
                        $pct = $st['total'] > 0 ? round($st['multi_lead'] / $st['total'] * 100) : 0;
                    ?>
                    <tr>
                        <td><?= e($bn) ?></td>
                        <td class="text-right"><?= $st['total'] ?></td>
                        <td class="text-right" style="color:<?= $st['multi_lead'] > 0 ? '#c62828' : '#666' ?>"><?= $st['multi_lead'] ?></td>
                        <td class="text-right" style="color:<?= $pct > 30 ? '#c62828' : ($pct > 0 ? '#e65100' : '#999') ?>"><?= $pct ?>%</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- 6. 由誰排工 -->
    <div class="card">
        <div class="card-header">由誰排工</div>
        <div class="table-responsive">
            <table class="table" style="font-size:.9rem">
                <thead>
                    <tr><th>排工人</th><th class="text-right">總張數</th><th class="text-right">雙主</th><th class="text-right">比例</th></tr>
                </thead>
                <tbody>
                    <?php
                    uasort($analysis['schedulers'], function($a, $b) { return $b['total'] - $a['total']; });
                    foreach ($analysis['schedulers'] as $sn => $st):
                        $pct = $st['total'] > 0 ? round($st['multi_lead'] / $st['total'] * 100) : 0;
                    ?>
                    <tr>
                        <td><?= e($sn) ?></td>
                        <td class="text-right"><?= $st['total'] ?></td>
                        <td class="text-right" style="color:<?= $st['multi_lead'] > 0 ? '#c62828' : '#666' ?>"><?= $st['multi_lead'] ?></td>
                        <td class="text-right" style="color:<?= $pct > 50 ? '#c62828' : ($pct > 20 ? '#e65100' : '#999') ?>"><?= $pct ?>%</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<!-- 7. 主工程師當月出勤 -->
<div class="card">
    <div class="card-header">主工程師（can_lead=1）當月出勤次數</div>
    <div class="table-responsive">
        <table class="table" style="font-size:.9rem">
            <thead>
                <tr><th>姓名</th><th>等級</th><th class="text-right">出勤次數</th><th>分布</th></tr>
            </thead>
            <tbody>
                <?php
                $maxLeader = 0;
                foreach ($analysis['leaders'] as $l) if ($l['count'] > $maxLeader) $maxLeader = $l['count'];
                foreach ($analysis['leaders'] as $name => $info):
                    $pct = $maxLeader > 0 ? round($info['count'] / $maxLeader * 100) : 0;
                ?>
                <tr>
                    <td><?= e($name) ?></td>
                    <td><span class="badge" style="background:#1565c0;color:#fff;font-size:.75rem"><?= e(isset($levelLabel[$info['level']]) ? $levelLabel[$info['level']] : $info['level']) ?></span></td>
                    <td class="text-right" style="font-weight:600"><?= $info['count'] ?></td>
                    <td>
                        <div style="height:14px;background:#eee;border-radius:7px;overflow:hidden">
                            <div style="height:100%;background:#2e7d32;width:<?= $pct ?>%"></div>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php endif; ?>

<style>
.text-right { text-align: right; }
.mb-2 { margin-bottom: 14px; }
.gap-2 { gap: 8px; }
</style>
