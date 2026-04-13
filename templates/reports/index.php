<h2>報表與分析</h2>

<!-- 案件狀態彙總（年度） -->
<div class="card">
    <div class="card-header d-flex justify-between align-center">
        <span>案件狀態總覽（年度）</span>
        <form method="GET" class="d-flex align-center gap-1" style="margin:0">
            <select name="year" class="form-control form-control-sm" style="width:auto" onchange="this.form.submit()">
                <?php for ($y = (int)date('Y'); $y >= 2024; $y--): ?>
                <option value="<?= $y ?>" <?= $summaryYear == $y ? 'selected' : '' ?>><?= ($y - 1911) ?>年 (<?= $y ?>)</option>
                <?php endfor; ?>
            </select>
            <?php if (!empty($_GET['month'])): ?><input type="hidden" name="month" value="<?= e($_GET['month']) ?>"><?php endif; ?>
        </form>
    </div>
    <div class="stat-grid">
        <?php
        foreach ($caseSummary as $cs):
            $label = CaseModel::statusLabel($cs['status'] ?: '');
        ?>
        <a href="/cases.php?status=<?= urlencode($cs['status'] ?: '') ?>" class="stat-card stat-link">
            <div class="stat-number"><?= (int)$cs['cnt'] ?></div>
            <div class="stat-label"><?= e($label) ?></div>
        </a>
        <?php endforeach; ?>
    </div>
</div>

<!-- 案件狀態彙總（月份） -->
<div class="card">
    <div class="card-header d-flex justify-between align-center">
        <span>案件狀態總覽（月份）</span>
        <form method="GET" class="d-flex align-center gap-1" style="margin:0">
            <input type="month" name="month" class="form-control form-control-sm" style="width:auto"
                   value="<?= e($summaryMonth) ?>" onchange="this.form.submit()">
            <?php if (!empty($_GET['year'])): ?><input type="hidden" name="year" value="<?= e($_GET['year']) ?>"><?php endif; ?>
        </form>
    </div>
    <div class="stat-grid">
        <?php
        foreach ($caseSummaryMonth as $cs):
            $label = CaseModel::statusLabel($cs['status'] ?: '');
        ?>
        <a href="/cases.php?status=<?= urlencode($cs['status'] ?: '') ?>" class="stat-card stat-link">
            <div class="stat-number"><?= (int)$cs['cnt'] ?></div>
            <div class="stat-label"><?= e($label) ?></div>
        </a>
        <?php endforeach; ?>
        <?php if (empty($caseSummaryMonth)): ?>
        <p class="text-muted text-center" style="grid-column:1/-1">該月無案件資料</p>
        <?php endif; ?>
    </div>
</div>

<!-- 報表入口 -->
<div class="card">
    <div class="card-header">報表選擇</div>
    <?php
    $reportCards = array(
        array('key' => 'case_summary', 'action' => 'case_analysis', 'icon' => '&#128202;', 'title' => '案件綜合分析', 'desc' => '各月進件/成交/金額/業務績效統計'),
        array('key' => 'case_profit', 'action' => 'case_profit', 'icon' => '&#128200;', 'title' => '案件利潤分析', 'desc' => '收款金額 vs 人力成本統計'),
        array('key' => 'staff_value', 'action' => 'staff_productivity', 'icon' => '&#128101;', 'title' => '員工產值統計', 'desc' => '每月施工案件數、工時統計'),
        array('key' => 'finance_summary', 'action' => 'finance_analysis', 'icon' => '&#128181;', 'title' => '帳務綜合分析', 'desc' => '資金總覽、收支差額、銀行餘額、現金月報'),
        array('key' => 'inter_branch_monthly', 'action' => 'inter_branch', 'icon' => '&#128176;', 'title' => '跨點點工費月結', 'desc' => '各據點互相支援費用明細'),
        array('key' => 'sales_personal', 'action' => 'sales_personal', 'icon' => '&#128100;', 'title' => '業務個人分析', 'desc' => '個人進件/成交/金額/案別統計與團隊比較'),
        array('key' => 'branch_monthly', 'action' => 'branch_monthly', 'icon' => '&#127970;', 'title' => '分公司月報', 'desc' => '各分公司每月收款/付款統計與明細'),
    );
    ?>
    <div class="report-links">
        <?php foreach ($reportCards as $rc):
            if (!Auth::canAccessReport($rc['key'])) continue;
        ?>
        <a href="/reports.php?action=<?= $rc['action'] ?>" class="report-link-card">
            <div class="report-icon"><?= $rc['icon'] ?></div>
            <div class="report-title"><?= $rc['title'] ?></div>
            <div class="report-desc"><?= $rc['desc'] ?></div>
        </a>
        <?php endforeach; ?>
        <?php
        $hasAny = false;
        foreach ($reportCards as $rc) { if (Auth::canAccessReport($rc['key'])) { $hasAny = true; break; } }
        if (!$hasAny): ?>
        <p class="text-muted text-center" style="grid-column:1/-1">您目前沒有可查看的報表</p>
        <?php endif; ?>
    </div>
</div>

<!-- 本月排工統計 -->
<div class="card">
    <div class="card-header">本月排工統計 (<?= date('Y年m月') ?>)</div>
    <?php if (empty($monthlyStats)): ?>
        <p class="text-muted text-center">本月尚無排工記錄</p>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table">
            <thead><tr><th>日期</th><th>排工數</th><th>出勤人次</th></tr></thead>
            <tbody>
                <?php foreach ($monthlyStats as $ms): ?>
                <tr>
                    <td><?= e($ms['schedule_date']) ?></td>
                    <td><?= (int)$ms['schedule_count'] ?></td>
                    <td><?= (int)$ms['engineer_count'] ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<style>
.stat-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 12px; }
.stat-card { text-align: center; padding: 16px 8px; border: 1px solid var(--gray-200); border-radius: var(--radius); }
.stat-number { font-size: 2rem; font-weight: 700; color: var(--primary); }
.stat-label { font-size: .85rem; color: var(--gray-500); margin-top: 4px; }
.report-links { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; }
.report-link-card {
    display: block; padding: 20px; border: 1px solid var(--gray-200);
    border-radius: var(--radius); text-decoration: none; color: inherit;
    transition: box-shadow .15s, border-color .15s;
}
.report-link-card:hover { box-shadow: var(--shadow); border-color: var(--primary); text-decoration: none; }
.report-icon { font-size: 2rem; margin-bottom: 8px; }
.report-title { font-weight: 600; font-size: 1rem; }
.report-desc { font-size: .8rem; color: var(--gray-500); margin-top: 4px; }
</style>
