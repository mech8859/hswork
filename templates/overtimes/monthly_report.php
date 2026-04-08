<div class="d-flex justify-between align-center flex-wrap mb-2">
    <h2>加班月結報表 <small class="text-muted"><?= e($yearMonth) ?></small></h2>
    <div class="d-flex gap-1">
        <a href="/overtimes.php?month=<?= e($yearMonth) ?>" class="btn btn-outline btn-sm">返回明細</a>
        <button type="button" class="btn btn-outline btn-sm" onclick="window.print()">列印</button>
    </div>
</div>

<div class="card">
    <form method="GET" action="/overtimes.php" class="filter-form">
        <input type="hidden" name="action" value="monthly_report">
        <div class="filter-row">
            <div class="form-group">
                <label>月份</label>
                <input type="month" name="month" class="form-control" value="<?= e($yearMonth) ?>">
            </div>
            <div class="form-group">
                <label>狀態</label>
                <select name="status_filter" class="form-control">
                    <option value="approved" <?= $statusFilter === 'approved' ? 'selected' : '' ?>>只計已核准</option>
                    <option value="" <?= $statusFilter === '' ? 'selected' : '' ?>>包含所有狀態</option>
                    <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>只計待核准</option>
                </select>
            </div>
            <div class="form-group" style="align-self:flex-end">
                <button type="submit" class="btn btn-primary btn-sm">查詢</button>
            </div>
        </div>
    </form>
</div>

<?php
$grandTotal = 0;
$grandWeekday = 0;
$grandRestDay = 0;
$grandHoliday = 0;
$grandOther = 0;
foreach ($summary as $row) {
    $grandTotal += (float)$row['total_hours'];
    $grandWeekday += (float)$row['weekday_hours'];
    $grandRestDay += (float)$row['rest_day_hours'];
    $grandHoliday += (float)$row['holiday_hours'];
    $grandOther += (float)$row['other_hours'];
}
?>

<div class="card">
    <div style="padding:12px;background:#f9f9f9;border-bottom:1px solid var(--gray-200)">
        <div style="display:flex;flex-wrap:wrap;gap:16px;font-size:.9rem">
            <div><strong>月份：</strong><?= e($yearMonth) ?></div>
            <div><strong>人員數：</strong><?= count($summary) ?> 人</div>
            <div><strong>總時數：</strong><span style="color:#1565c0;font-weight:700;font-size:1.1rem"><?= number_format($grandTotal, 2) ?></span> 小時</div>
            <div><strong>平日：</strong><?= number_format($grandWeekday, 2) ?>h</div>
            <div><strong>例假日：</strong><?= number_format($grandRestDay, 2) ?>h</div>
            <div><strong>國定假日：</strong><?= number_format($grandHoliday, 2) ?>h</div>
            <div><strong>其他：</strong><?= number_format($grandOther, 2) ?>h</div>
        </div>
    </div>

    <?php if (empty($summary)): ?>
    <p class="text-muted text-center mt-2">本月無加班紀錄</p>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th style="width:50px">#</th>
                    <th>姓名</th>
                    <th>分公司</th>
                    <th class="text-right">筆數</th>
                    <th class="text-right">平日</th>
                    <th class="text-right">例假日</th>
                    <th class="text-right">國定假日</th>
                    <th class="text-right">其他</th>
                    <th class="text-right" style="background:#fff8e1">總時數</th>
                </tr>
            </thead>
            <tbody>
                <?php $i = 1; foreach ($summary as $row): ?>
                <tr>
                    <td><?= $i++ ?></td>
                    <td><strong><?= e($row['real_name']) ?></strong></td>
                    <td><?= e($row['branch_name']) ?></td>
                    <td class="text-right"><?= (int)$row['record_count'] ?></td>
                    <td class="text-right"><?= number_format($row['weekday_hours'], 2) ?></td>
                    <td class="text-right"><?= number_format($row['rest_day_hours'], 2) ?></td>
                    <td class="text-right"><?= number_format($row['holiday_hours'], 2) ?></td>
                    <td class="text-right"><?= number_format($row['other_hours'], 2) ?></td>
                    <td class="text-right" style="background:#fff8e1;font-weight:700;color:#1565c0"><?= number_format($row['total_hours'], 2) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr style="background:#f5f5f5;font-weight:700">
                    <td colspan="3" class="text-right">合計</td>
                    <td class="text-right"><?php
                        $cnt = 0; foreach ($summary as $r) $cnt += (int)$r['record_count']; echo $cnt;
                    ?></td>
                    <td class="text-right"><?= number_format($grandWeekday, 2) ?></td>
                    <td class="text-right"><?= number_format($grandRestDay, 2) ?></td>
                    <td class="text-right"><?= number_format($grandHoliday, 2) ?></td>
                    <td class="text-right"><?= number_format($grandOther, 2) ?></td>
                    <td class="text-right" style="background:#fff8e1;color:#1565c0"><?= number_format($grandTotal, 2) ?></td>
                </tr>
            </tfoot>
        </table>
    </div>
    <?php endif; ?>
</div>

<style>
.filter-row { display: flex; flex-wrap: wrap; gap: 12px; align-items: flex-end; }
.filter-row .form-group { flex: 1; min-width: 130px; margin-bottom: 0; }
@media print {
    .btn, form, .filter-form { display: none !important; }
}
</style>
