<div class="d-flex justify-between align-center flex-wrap gap-1 mb-2">
    <h2>案件利潤分析</h2>
    <a href="/reports.php" class="btn btn-outline btn-sm">返回報表</a>
</div>

<div class="card">
    <form method="GET" action="/reports.php" class="filter-form">
        <input type="hidden" name="action" value="case_profit">
        <div class="filter-row">
            <div class="form-group">
                <label>開始日期</label>
                <input type="date" max="2099-12-31" name="start_date" class="form-control" value="<?= e($startDate) ?>">
            </div>
            <div class="form-group">
                <label>結束日期</label>
                <input type="date" max="2099-12-31" name="end_date" class="form-control" value="<?= e($endDate) ?>">
            </div>
            <div class="form-group" style="align-self:flex-end">
                <button type="submit" class="btn btn-primary btn-sm">查詢</button>
            </div>
        </div>
    </form>
</div>

<div class="card">
    <?php if (empty($data)): ?>
        <p class="text-muted text-center">查無資料</p>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>案件編號</th>
                    <th>案件名稱</th>
                    <th>據點</th>
                    <th>類型</th>
                    <th>收款金額</th>
                    <th>施工天數</th>
                    <th>出勤人次</th>
                    <th>狀態</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $totalPaid = 0;
                $totalDays = 0;
                $totalEng = 0;
                foreach ($data as $row):
                    $totalPaid += $row['total_paid'];
                    $totalDays += $row['work_days'];
                    $totalEng += $row['total_engineer_days'];
                ?>
                <tr>
                    <td><a href="/cases.php?action=view&id=<?= $row['id'] ?>"><?= e($row['case_number']) ?></a></td>
                    <td><?= e($row['title']) ?></td>
                    <td><?= e($row['branch_name']) ?></td>
                    <td><?= e($row['case_type']) ?></td>
                    <td class="text-right">$<?= number_format($row['total_paid']) ?></td>
                    <td class="text-center"><?= (int)$row['work_days'] ?></td>
                    <td class="text-center"><?= (int)$row['total_engineer_days'] ?></td>
                    <td><?= e($row['status']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr style="font-weight:600; background:var(--gray-100);">
                    <td colspan="4">合計</td>
                    <td class="text-right">$<?= number_format($totalPaid) ?></td>
                    <td class="text-center"><?= $totalDays ?></td>
                    <td class="text-center"><?= $totalEng ?></td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    </div>
    <?php endif; ?>
</div>

<style>
.filter-row { display: flex; flex-wrap: wrap; gap: 12px; align-items: flex-end; }
.filter-row .form-group { flex: 1; min-width: 130px; margin-bottom: 0; }
</style>
