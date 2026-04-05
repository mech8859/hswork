<?php
$settledCount = isset($_GET['settled_count']) ? (int)$_GET['settled_count'] : 0;
if ($settledCount > 0): ?>
<div class="alert alert-success">已成功結算 <?= $settledCount ?> 筆出勤記錄</div>
<?php endif; ?>

<div class="d-flex justify-between align-center flex-wrap gap-1 mb-2">
    <h2>點工出勤結算</h2>
    <div class="d-flex gap-1">
        <a href="/inter_branch.php?action=attendance" class="btn btn-outline btn-sm">出勤登錄</a>
        <?= back_button('/inter_branch.php') ?>
    </div>
</div>

<!-- 篩選區 -->
<div class="card mb-2">
    <form method="GET" class="form-row align-center">
        <input type="hidden" name="action" value="attendance_settle_page">
        <div class="form-group">
            <label>起始日期</label>
            <input type="date" name="date_from" class="form-control" value="<?= e($filters['date_from']) ?>">
        </div>
        <div class="form-group">
            <label>結束日期</label>
            <input type="date" name="date_to" class="form-control" value="<?= e($filters['date_to']) ?>">
        </div>
        <div class="form-group">
            <label>點工人員</label>
            <select name="worker_id" class="form-control">
                <option value="">全部</option>
                <?php foreach ($allWorkers as $w): ?>
                <option value="<?= $w['id'] ?>" <?= $filters['worker_id'] == $w['id'] ? 'selected' : '' ?>><?= e($w['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>分公司</label>
            <select name="branch_id" class="form-control">
                <option value="">全部</option>
                <?php foreach ($branches as $b): ?>
                <option value="<?= $b['id'] ?>" <?= $filters['branch_id'] == $b['id'] ? 'selected' : '' ?>><?= e($b['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>結算狀態</label>
            <select name="settled" class="form-control">
                <option value="">全部</option>
                <option value="0" <?= $filters['settled'] === '0' ? 'selected' : '' ?>>未結算</option>
                <option value="1" <?= $filters['settled'] === '1' ? 'selected' : '' ?>>已結算</option>
            </select>
        </div>
        <div class="form-group" style="align-self:flex-end">
            <button type="submit" class="btn btn-primary btn-sm">篩選</button>
        </div>
    </form>
</div>

<!-- Tab 切換 -->
<div class="card mb-2">
    <div class="d-flex gap-1 mb-2" style="border-bottom:2px solid var(--gray-200);padding-bottom:8px">
        <button type="button" class="btn btn-sm tab-btn active" data-tab="byWorker" onclick="switchTab('byWorker')">按人員</button>
        <button type="button" class="btn btn-sm tab-btn" data-tab="byDate" onclick="switchTab('byDate')">按天</button>
        <button type="button" class="btn btn-sm tab-btn" data-tab="byBranch" onclick="switchTab('byBranch')">按分公司</button>
    </div>

    <!-- 按人員 Tab -->
    <div id="tab-byWorker" class="settle-tab">
        <?php if (empty($settleData['by_worker'])): ?>
        <p class="text-center text-muted">無出勤資料</p>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>點工人員</th>
                        <th class="text-right">全日</th>
                        <th class="text-right">半日</th>
                        <th class="text-right">出勤次數</th>
                        <th class="text-right">總金額</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $grandTotal = 0;
                    foreach ($settleData['by_worker'] as $w):
                        $grandTotal += $w['total_amount'];
                    ?>
                    <tr>
                        <td><strong><?= e($w['worker_name']) ?></strong></td>
                        <td class="text-right"><?= $w['full_days'] ?></td>
                        <td class="text-right"><?= $w['half_days'] ?></td>
                        <td class="text-right"><?= $w['total_records'] ?></td>
                        <td class="text-right"><strong>$<?= number_format($w['total_amount']) ?></strong></td>
                        <td>
                            <button type="button" class="btn btn-outline btn-sm" onclick="toggleDetail('worker-detail-<?= $w['dispatch_worker_id'] ?>')">明細</button>
                        </td>
                    </tr>
                    <tr id="worker-detail-<?= $w['dispatch_worker_id'] ?>" style="display:none">
                        <td colspan="6" style="padding:0">
                            <table class="table" style="margin:0;background:var(--gray-50,#fafafa)">
                                <thead>
                                    <tr>
                                        <th style="font-size:.8rem">日期</th>
                                        <th style="font-size:.8rem">分公司</th>
                                        <th style="font-size:.8rem">案件</th>
                                        <th style="font-size:.8rem">計費</th>
                                        <th style="font-size:.8rem" class="text-right">金額</th>
                                        <th style="font-size:.8rem">結算</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($settleData['detail'] as $d):
                                        if ($d['dispatch_worker_id'] != $w['dispatch_worker_id']) continue;
                                    ?>
                                    <tr>
                                        <td style="font-size:.85rem"><?= e($d['attendance_date']) ?></td>
                                        <td style="font-size:.85rem"><?= e($d['branch_name']) ?></td>
                                        <td style="font-size:.85rem"><?= $d['case_name'] ? e($d['case_name']) : '-' ?></td>
                                        <td style="font-size:.85rem"><?= $d['charge_type'] === 'full_day' ? '全日' : '半日' ?></td>
                                        <td style="font-size:.85rem" class="text-right">$<?= number_format($d['amount']) ?></td>
                                        <td style="font-size:.85rem">
                                            <?php if ($d['settled']): ?>
                                            <span class="badge badge-success">已結算</span>
                                            <?php else: ?>
                                            <span class="badge">未結算</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr style="font-weight:600;background:var(--gray-100)">
                        <td>合計</td>
                        <td class="text-right"><?= array_sum(array_column($settleData['by_worker'], 'full_days')) ?></td>
                        <td class="text-right"><?= array_sum(array_column($settleData['by_worker'], 'half_days')) ?></td>
                        <td class="text-right"><?= array_sum(array_column($settleData['by_worker'], 'total_records')) ?></td>
                        <td class="text-right"><strong>$<?= number_format($grandTotal) ?></strong></td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <!-- 按天 Tab -->
    <div id="tab-byDate" class="settle-tab" style="display:none">
        <?php if (empty($settleData['by_date'])): ?>
        <p class="text-center text-muted">無出勤資料</p>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>日期</th>
                        <th class="text-right">出勤人數</th>
                        <th class="text-right">費用</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $totalWorkers = 0;
                    $totalCost = 0;
                    foreach ($settleData['by_date'] as $dd):
                        $totalWorkers += $dd['worker_count'];
                        $totalCost += $dd['total_amount'];
                    ?>
                    <tr>
                        <td><?= e($dd['attendance_date']) ?></td>
                        <td class="text-right"><?= $dd['worker_count'] ?></td>
                        <td class="text-right">$<?= number_format($dd['total_amount']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr style="font-weight:600;background:var(--gray-100)">
                        <td>合計（<?= count($settleData['by_date']) ?> 天）</td>
                        <td class="text-right"><?= $totalWorkers ?> 人次</td>
                        <td class="text-right"><strong>$<?= number_format($totalCost) ?></strong></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <!-- 按分公司 Tab -->
    <div id="tab-byBranch" class="settle-tab" style="display:none">
        <?php if (empty($settleData['by_branch'])): ?>
        <p class="text-center text-muted">無出勤資料</p>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>分公司</th>
                        <th class="text-right">人數</th>
                        <th class="text-right">全日</th>
                        <th class="text-right">半日</th>
                        <th class="text-right">費用</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $branchTotal = 0;
                    foreach ($settleData['by_branch'] as $bb):
                        $branchTotal += $bb['total_amount'];
                    ?>
                    <tr>
                        <td><strong><?= e($bb['branch_name']) ?></strong></td>
                        <td class="text-right"><?= $bb['worker_count'] ?></td>
                        <td class="text-right"><?= $bb['full_days'] ?></td>
                        <td class="text-right"><?= $bb['half_days'] ?></td>
                        <td class="text-right"><strong>$<?= number_format($bb['total_amount']) ?></strong></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr style="font-weight:600;background:var(--gray-100)">
                        <td>合計</td>
                        <td class="text-right"><?= array_sum(array_column($settleData['by_branch'], 'worker_count')) ?></td>
                        <td class="text-right"><?= array_sum(array_column($settleData['by_branch'], 'full_days')) ?></td>
                        <td class="text-right"><?= array_sum(array_column($settleData['by_branch'], 'half_days')) ?></td>
                        <td class="text-right"><strong>$<?= number_format($branchTotal) ?></strong></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- 月結操作 -->
<div class="card">
    <div class="card-header">月結操作</div>
    <form method="POST" action="/inter_branch.php?action=attendance_do_settle" class="form-row align-center" style="padding:16px">
        <?= csrf_field() ?>
        <div class="form-group">
            <label>結算月份</label>
            <input type="month" name="settle_month" class="form-control" value="<?= date('Y-m') ?>" required>
        </div>
        <div class="form-group">
            <label>分公司（選填）</label>
            <select name="branch_id" class="form-control">
                <option value="">全部分公司</option>
                <?php foreach ($branches as $b): ?>
                <option value="<?= $b['id'] ?>"><?= e($b['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group" style="align-self:flex-end">
            <button type="submit" class="btn btn-primary" onclick="return confirm('確定執行月結？結算後記錄將無法修改或刪除。')">執行月結</button>
        </div>
    </form>
</div>

<script>
function switchTab(tab) {
    var tabs = document.querySelectorAll('.settle-tab');
    for (var i = 0; i < tabs.length; i++) {
        tabs[i].style.display = 'none';
    }
    document.getElementById('tab-' + tab).style.display = 'block';

    var btns = document.querySelectorAll('.tab-btn');
    for (var j = 0; j < btns.length; j++) {
        btns[j].classList.remove('active');
        if (btns[j].getAttribute('data-tab') === tab) {
            btns[j].classList.add('active');
        }
    }
}

function toggleDetail(id) {
    var el = document.getElementById(id);
    if (el) {
        el.style.display = el.style.display === 'none' ? '' : 'none';
    }
}
</script>

<style>
.form-row { display: flex; flex-wrap: wrap; gap: 12px; }
.form-row .form-group { flex: 1; min-width: 140px; }
.tab-btn { background: var(--gray-100); border: 1px solid var(--gray-200); cursor: pointer; }
.tab-btn.active { background: var(--primary); color: #fff; border-color: var(--primary); }
.badge-danger { background: var(--danger); color: #fff; }
</style>
