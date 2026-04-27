<meta name="csrf" content="<?= e(Session::getCsrfToken()) ?>">

<div class="d-flex justify-between align-center flex-wrap gap-1 mb-2">
    <h2>智慧排工</h2>
    <div class="d-flex gap-1">
        <a href="/schedule.php?action=create&case_id=<?= $case['id'] ?>" class="btn btn-outline btn-sm">手動排工</a>
        <a href="/cases.php?action=view&id=<?= $case['id'] ?>" class="btn btn-outline btn-sm">返回案件</a>
    </div>
</div>

<!-- 案件摘要 -->
<div class="card">
    <div class="card-header">案件摘要</div>
    <div class="smart-summary">
        <div class="summary-row">
            <div class="summary-item">
                <span class="detail-label">案件</span>
                <span><a href="/cases.php?action=view&id=<?= $case['id'] ?>"><?= e($case['case_number']) ?></a> <?= e($case['title']) ?></span>
            </div>
            <div class="summary-item">
                <span class="detail-label">難易度</span>
                <span class="stars"><?= str_repeat('&#9733;', (int)$case['difficulty']) ?><?= str_repeat('&#9734;', 5 - (int)$case['difficulty']) ?></span>
            </div>
            <div class="summary-item">
                <span class="detail-label">預估工時</span>
                <span><?= !empty($case['est_labor_hours']) ? $case['est_labor_hours'] . ' 小時' : '-' ?></span>
            </div>
        </div>
        <div class="summary-row">
            <div class="summary-item">
                <span class="detail-label">施工進度</span>
                <span>第 <?= (int)($case['current_visit'] ?: 1) ?> / <?= (int)($case['total_visits'] ?: 1) ?> 次</span>
            </div>
            <div class="summary-item">
                <span class="detail-label">預估人數</span>
                <span><?= (int)($case['est_labor_people'] ?: 4) ?> 人</span>
            </div>
            <div class="summary-item">
                <span class="detail-label">施工地址</span>
                <span><?= e($case['address'] ?: '-') ?></span>
            </div>
        </div>

        <?php if (!empty($case['required_skills'])): ?>
        <div class="mt-1">
            <span class="detail-label">所需技能：</span>
            <?php foreach ($case['required_skills'] as $rs): ?>
                <span class="badge badge-primary"><?= e($rs['skill_name']) ?> ≥<?= $rs['min_proficiency'] ?>★</span>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php
        $sc = $case['site_conditions'];
        if (!empty($sc)):
            $envParts = [];
            if (!empty($sc['structure_type'])) $envParts[] = str_replace(',', '/', $sc['structure_type']);
            if (!empty($sc['conduit_type'])) $envParts[] = str_replace(',', '/', $sc['conduit_type']);
            if (!empty($sc['floor_count'])) $envParts[] = $sc['floor_count'] . '樓';
            if (!empty($sc['has_elevator'])) $envParts[] = '有電梯';
            if (!empty($sc['has_ladder_needed'])) $envParts[] = '需梯子';
            if (!empty($sc['special_requirements'])) $envParts[] = $sc['special_requirements'];
        ?>
        <div class="mt-1">
            <span class="detail-label">現場環境：</span>
            <span><?= e(implode(', ', $envParts)) ?></span>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- 推薦方案 -->
<?php if (empty($recommendations)): ?>
<div class="card">
    <div style="text-align:center;padding:32px 16px">
        <div style="font-size:2rem;margin-bottom:8px">🤔</div>
        <p class="text-muted">目前 14 天內找不到合適的排工方案</p>
        <p class="text-muted" style="font-size:.85rem">可能原因：沒有可用工程師、全部請假或已排滿</p>
        <a href="/schedule.php?action=create&case_id=<?= $case['id'] ?>" class="btn btn-primary mt-2">手動排工</a>
    </div>
</div>
<?php else: ?>

<?php foreach ($recommendations as $idx => $rec): ?>
<div class="card recommendation-card"
     data-case-id="<?= $case['id'] ?>"
     data-date="<?= e($rec['date']) ?>"
     data-vehicle-id="<?= $rec['vehicle'] ? $rec['vehicle']['id'] : '' ?>"
     data-visit-number="<?= $rec['visit_number'] ?>"
     data-lead-id="<?= $rec['lead_id'] ?>"
     data-engineer-ids="<?= implode(',', $rec['engineer_ids']) ?>">

    <div class="card-header d-flex justify-between align-center">
        <span>
            方案 <?= $idx + 1 ?>
            <?php if ($idx === 0): ?><span class="badge badge-success">最佳</span><?php endif; ?>
        </span>
        <span class="score-badge"><?= $rec['score'] ?> / 100 分</span>
    </div>

    <div class="rec-content">
        <div class="rec-row">
            <div class="rec-item">
                <span class="rec-icon">📅</span>
                <span><?= e($rec['date_label']) ?><?= $rec['is_weekend'] ? ' <span class="badge badge-warning">週六</span>' : '' ?></span>
            </div>
            <div class="rec-item">
                <span class="rec-icon">⏱</span>
                <span>預估 <?= isset($rec['case_hours']) ? $rec['case_hours'] : '-' ?> 小時</span>
            </div>
        </div>
        <div class="rec-row">
            <div class="rec-item">
                <span class="rec-icon">👷</span>
                <span>
                    <?php foreach ($rec['engineers'] as $i => $eng):
                        $thInfo = isset($rec['team_hours'][$eng['id']]) ? $rec['team_hours'][$eng['id']] : null;
                        $hasUsed = $thInfo && $thInfo['hours_used'] > 0;
                    ?>
                        <?= e($eng['real_name']) ?><?= $eng['id'] == $rec['lead_id'] ? '<span class="text-primary">(主)</span>' : '' ?><?php if ($hasUsed): ?><span style="font-size:.7rem;color:#e65100;margin-left:2px">[已排<?= $thInfo['hours_used'] ?>h]</span><?php endif; ?><?= $i < count($rec['engineers']) - 1 ? '、' : '' ?>
                    <?php endforeach; ?>
                    <span class="text-muted">(<?= count($rec['engineers']) ?>人)</span>
                </span>
            </div>
        </div>
        <div class="rec-row">
            <div class="rec-item">
                <span class="rec-icon">🚗</span>
                <span>
                    <?php if ($rec['vehicle']): ?>
                        <?= e($rec['vehicle']['plate_number']) ?> <?= e($rec['vehicle']['vehicle_type']) ?> (<?= $rec['vehicle']['seats'] ?>座)
                    <?php else: ?>
                        <span class="text-muted">無可用車輛</span>
                    <?php endif; ?>
                </span>
            </div>
        </div>

        <!-- 推薦點工人員 -->
        <?php if (!empty($rec['dispatch_workers'])): ?>
        <div class="rec-row" style="margin-top:4px">
            <div class="rec-item" style="align-items:flex-start">
                <span class="rec-icon">🔧</span>
                <div style="flex:1">
                    <span style="font-weight:600;font-size:.85rem">推薦點工人員</span>
                    <div style="margin-top:4px;display:flex;flex-wrap:wrap;gap:4px">
                        <?php foreach ($rec['dispatch_workers'] as $dw): ?>
                        <label class="dw-check-label" style="display:inline-flex;align-items:center;gap:3px;padding:3px 8px;border:1px solid var(--gray-300);border-radius:4px;font-size:.8rem;cursor:pointer;background:<?= $dw['match_pct'] >= 80 ? '#e8f5e9' : ($dw['match_pct'] >= 50 ? '#fff8e1' : '#fff') ?>">
                            <input type="checkbox" class="dw-checkbox" value="<?= $dw['id'] ?>" checked style="margin:0">
                            <?= e($dw['name']) ?>
                            <?php if ($dw['total_required'] > 0): ?>
                            <span style="color:<?= $dw['match_pct'] >= 80 ? '#2e7d32' : ($dw['match_pct'] >= 50 ? '#f57f17' : '#c62828') ?>;font-size:.7rem">技能<?= $dw['match_pct'] ?>%</span>
                            <?php endif; ?>
                            <?php if (isset($dw['pair_score'])): ?>
                            <span style="color:#ff9800;font-size:.7rem">配對<?= $dw['pair_score'] ?>★</span>
                            <?php endif; ?>
                            <span style="color:#888;font-size:.7rem">$<?= number_format($dw['daily_rate']) ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                    <?php if (empty($rec['dispatch_workers'])): ?>
                    <span class="text-muted" style="font-size:.8rem">當天無可用點工</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- 評分明細 -->
        <div class="score-detail mt-1">
            <div class="score-bar-group">
                <div class="score-item">
                    <span class="score-label">技能</span>
                    <div class="score-bar"><div class="score-fill" style="width:<?= $rec['breakdown']['skill'] / 40 * 100 ?>%;background:var(--primary)"></div></div>
                    <span class="score-num"><?= $rec['breakdown']['skill'] ?>/40</span>
                </div>
                <div class="score-item">
                    <span class="score-label">默契</span>
                    <div class="score-bar"><div class="score-fill" style="width:<?= $rec['breakdown']['pair'] / 20 * 100 ?>%;background:#10b981"></div></div>
                    <span class="score-num"><?= $rec['breakdown']['pair'] ?>/20</span>
                </div>
                <div class="score-item">
                    <span class="score-label">負載</span>
                    <div class="score-bar"><div class="score-fill" style="width:<?= $rec['breakdown']['load'] / 15 * 100 ?>%;background:#f59e0b"></div></div>
                    <span class="score-num"><?= $rec['breakdown']['load'] ?>/15</span>
                </div>
                <div class="score-item">
                    <span class="score-label">車輛</span>
                    <div class="score-bar"><div class="score-fill" style="width:<?= $rec['breakdown']['vehicle'] / 10 * 100 ?>%;background:#8b5cf6"></div></div>
                    <span class="score-num"><?= $rec['breakdown']['vehicle'] ?>/10</span>
                </div>
                <div class="score-item">
                    <span class="score-label">連續</span>
                    <div class="score-bar"><div class="score-fill" style="width:<?= $rec['breakdown']['continuity'] / 15 * 100 ?>%;background:#ec4899"></div></div>
                    <span class="score-num"><?= $rec['breakdown']['continuity'] ?>/15</span>
                </div>
            </div>
        </div>

        <div class="mt-2" style="text-align:right">
            <button type="button" class="btn btn-primary" onclick="applyRecommendation(this)">套用此方案</button>
        </div>
    </div>
</div>
<?php endforeach; ?>

<div class="text-center mt-2">
    <span class="text-muted">找不到合適方案？</span>
    <a href="/schedule.php?action=create&case_id=<?= $case['id'] ?>">手動排工 →</a>
</div>
<?php endif; ?>

<script>
function applyRecommendation(btn) {
    if (!confirm('確定套用此排工方案？')) return;
    btn.disabled = true;
    btn.textContent = '建立中...';

    var card = btn.closest('.recommendation-card');
    var data = new FormData();
    data.append('csrf_token', document.querySelector('meta[name="csrf"]').getAttribute('content'));
    data.append('case_id', card.dataset.caseId);
    data.append('schedule_date', card.dataset.date);
    data.append('vehicle_id', card.dataset.vehicleId || '');
    data.append('visit_number', card.dataset.visitNumber);
    data.append('lead_engineer_id', card.dataset.leadId);

    var ids = card.dataset.engineerIds.split(',');
    for (var i = 0; i < ids.length; i++) {
        data.append('engineer_ids[]', ids[i]);
    }

    // 勾選的點工人員
    var dwChecks = card.querySelectorAll('.dw-checkbox:checked');
    for (var j = 0; j < dwChecks.length; j++) {
        data.append('dispatch_worker_ids[]', dwChecks[j].value);
    }

    fetch('/schedule.php?action=smart_apply', {
        method: 'POST',
        body: data
    })
    .then(function(r) { return r.json(); })
    .then(function(result) {
        if (result.success) {
            window.location.href = result.redirect;
        } else {
            alert(result.error || '建立失敗');
            btn.disabled = false;
            btn.textContent = '套用此方案';
        }
    })
    .catch(function() {
        alert('網路錯誤，請重試');
        btn.disabled = false;
        btn.textContent = '套用此方案';
    });
}
</script>

<style>
.smart-summary { display: flex; flex-direction: column; gap: 8px; }
.summary-row { display: flex; flex-wrap: wrap; gap: 16px; }
.summary-item { display: flex; flex-direction: column; min-width: 120px; }
.detail-label { font-size: .8rem; color: var(--gray-500); }

.recommendation-card { border-left: 4px solid var(--primary); }
.score-badge { font-weight: 700; font-size: 1.1rem; color: var(--primary); }

.rec-content { padding-top: 8px; }
.rec-row { display: flex; align-items: center; gap: 8px; margin-bottom: 6px; }
.rec-item { display: flex; align-items: center; gap: 6px; }
.rec-icon { font-size: 1.1rem; }

.score-detail { border-top: 1px solid var(--gray-200); padding-top: 8px; }
.score-bar-group { display: flex; flex-wrap: wrap; gap: 6px; }
.score-item { display: flex; align-items: center; gap: 6px; flex: 1; min-width: 140px; }
.score-label { font-size: .75rem; color: var(--gray-500); width: 30px; text-align: right; }
.score-bar { flex: 1; height: 8px; background: var(--gray-100); border-radius: 4px; overflow: hidden; }
.score-fill { height: 100%; border-radius: 4px; transition: width .3s; }
.score-num { font-size: .75rem; color: var(--gray-500); width: 36px; }

@media (max-width: 767px) {
    .summary-row { flex-direction: column; gap: 8px; }
    .score-bar-group { flex-direction: column; }
}
</style>
