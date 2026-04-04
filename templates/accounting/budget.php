<div class="page-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px">
    <h1>預算編輯</h1>
    <div style="display:flex;gap:8px">
        <a href="/accounting.php?action=financial_reports" class="btn btn-secondary">財務報表</a>
        <a href="/accounting.php?action=journals" class="btn btn-secondary">傳票管理</a>
    </div>
</div>

<?php if (isset($_GET['saved'])): ?>
<div class="alert alert-success">預算已儲存</div>
<?php endif; ?>
<?php if (isset($_GET['copied'])): ?>
<div class="alert alert-success">預算已從上年度複製</div>
<?php endif; ?>

<!-- 篩選區 -->
<div class="card" style="padding:16px;margin-bottom:16px">
    <form method="get" action="/accounting.php" style="display:flex;gap:8px;flex-wrap:wrap;align-items:end">
        <input type="hidden" name="action" value="budget">
        <div>
            <label style="font-size:0.85em">年度</label>
            <select name="year" class="form-control" style="width:120px">
                <?php for ($y = (int)date('Y') + 1; $y >= (int)date('Y') - 3; $y--): ?>
                <option value="<?= $y ?>" <?= $year == $y ? 'selected' : '' ?>><?= $y ?></option>
                <?php endfor; ?>
            </select>
        </div>
        <div>
            <label style="font-size:0.85em">成本中心</label>
            <select name="cost_center_id" class="form-control" style="width:150px">
                <option value="">全公司</option>
                <?php foreach ($costCenters as $cc): ?>
                <option value="<?= $cc['id'] ?>" <?= $ccId == $cc['id'] ? 'selected' : '' ?>><?= e($cc['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-primary">查詢</button>
    </form>
</div>

<!-- 複製上年度 -->
<div class="card" style="padding:12px;margin-bottom:16px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px">
    <div>
        <strong><?= $year ?> 年度預算</strong>
        <?php if ($ccId): ?>
         | 成本中心篩選
        <?php else: ?>
         | 全公司
        <?php endif; ?>
    </div>
    <form method="post" style="display:flex;gap:8px;align-items:center" onsubmit="return confirm('確定複製 ' + this.copy_from_year.value + ' 年度的預算？')">
        <label style="font-size:0.85em">從年度複製：</label>
        <select name="copy_from_year" class="form-control" style="width:100px">
            <?php for ($y = $year - 1; $y >= $year - 3; $y--): ?>
            <option value="<?= $y ?>"><?= $y ?></option>
            <?php endfor; ?>
        </select>
        <button type="submit" class="btn btn-secondary">複製</button>
    </form>
</div>

<!-- 預算表格 -->
<form method="post">
<div class="card" style="overflow-x:auto">
    <table class="data-table" style="width:100%;font-size:0.85em">
        <thead>
            <tr>
                <th style="width:80px;position:sticky;left:0;background:#f8f9fa;z-index:2">科目代碼</th>
                <th style="width:150px;position:sticky;left:80px;background:#f8f9fa;z-index:2">科目名稱</th>
                <?php for ($m = 1; $m <= 12; $m++): ?>
                <th style="width:100px;text-align:right"><?= $m ?>月</th>
                <?php endfor; ?>
                <th style="width:110px;text-align:right">合計</th>
            </tr>
        </thead>
        <tbody>
        <?php
        $groups = array(
            '4' => '營業收入',
            '5' => '營業成本',
            '6' => '營業費用',
            '7' => '營業外收支',
            '8' => '所得稅',
        );
        $currentGroup = '';
        $groupTotals = array();
        for ($m = 1; $m <= 12; $m++) { $groupTotals[$m] = 0; }
        $groupTotal = 0;

        foreach ($plAccounts as $idx => $acc):
            $prefix = substr($acc['code'], 0, 1);
            $nextPrefix = isset($plAccounts[$idx + 1]) ? substr($plAccounts[$idx + 1]['code'], 0, 1) : '';

            // 組別標題
            if ($prefix !== $currentGroup):
                if ($currentGroup !== ''):
                    // 輸出前一組小計
        ?>
            <tr style="background:#e9ecef;font-weight:bold">
                <td colspan="2" style="position:sticky;left:0;background:#e9ecef"><?= e($groups[$currentGroup] ?? '') ?> 小計</td>
                <?php for ($m = 1; $m <= 12; $m++): ?>
                <td style="text-align:right"><?= number_format($groupTotals[$m]) ?></td>
                <?php endfor; ?>
                <td style="text-align:right"><?= number_format($groupTotal) ?></td>
            </tr>
        <?php
                endif;
                $currentGroup = $prefix;
                for ($m = 1; $m <= 12; $m++) { $groupTotals[$m] = 0; }
                $groupTotal = 0;
        ?>
            <tr style="background:#dee2e6">
                <td colspan="15" style="font-weight:bold;position:sticky;left:0;background:#dee2e6"><?= e($groups[$prefix] ?? '其他') ?></td>
            </tr>
        <?php endif; ?>

            <tr>
                <td style="position:sticky;left:0;background:#fff"><?= e($acc['code']) ?></td>
                <td style="position:sticky;left:80px;background:#fff"><?= e($acc['name']) ?></td>
                <?php
                $rowTotal = 0;
                for ($m = 1; $m <= 12; $m++):
                    $val = isset($budgetMap[$acc['id']][$m]) ? $budgetMap[$acc['id']][$m] : 0;
                    $rowTotal += $val;
                    $groupTotals[$m] += $val;
                ?>
                <td style="text-align:right;padding:2px">
                    <input type="text" name="budget[<?= $acc['id'] ?>][<?= $m ?>]" value="<?= $val != 0 ? number_format($val, 0) : '' ?>"
                           class="form-control" style="width:90px;text-align:right;padding:2px 4px;font-size:0.85em"
                           onfocus="this.value=this.value.replace(/,/g,'')" onblur="formatBudgetInput(this)">
                </td>
                <?php endfor; ?>
                <td style="text-align:right;font-weight:bold" class="budget-row-total" data-account="<?= $acc['id'] ?>"><?= number_format($rowTotal) ?></td>
            </tr>
        <?php
            $groupTotal += $rowTotal;

            // 最後一組小計
            if ($nextPrefix !== $prefix && $currentGroup !== ''):
        ?>
            <tr style="background:#e9ecef;font-weight:bold">
                <td colspan="2" style="position:sticky;left:0;background:#e9ecef"><?= e($groups[$currentGroup] ?? '') ?> 小計</td>
                <?php for ($m = 1; $m <= 12; $m++): ?>
                <td style="text-align:right"><?= number_format($groupTotals[$m]) ?></td>
                <?php endfor; ?>
                <td style="text-align:right"><?= number_format($groupTotal) ?></td>
            </tr>
        <?php
                $currentGroup = '';
            endif;
        endforeach;
        ?>
        </tbody>
    </table>
</div>

<div style="margin-top:16px;text-align:right">
    <button type="submit" class="btn btn-primary" style="padding:10px 32px;font-size:1em">儲存預算</button>
</div>
</form>

<script>
function formatBudgetInput(el) {
    var v = parseFloat(el.value.replace(/,/g, ''));
    if (isNaN(v) || v === 0) { el.value = ''; return; }
    el.value = v.toLocaleString('en-US', {maximumFractionDigits: 0});
}
</script>
