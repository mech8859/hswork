<div class="d-flex justify-between align-center flex-wrap gap-1 mb-2">
    <h2>員工產值統計</h2>
    <?= back_button('/reports.php') ?>
</div>

<div class="card">
    <form method="GET" action="/reports.php" class="filter-form">
        <input type="hidden" name="action" value="staff_productivity">
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
                    <th>工程師</th>
                    <th>據點</th>
                    <th>排工次數</th>
                    <th>參與案件數</th>
                    <th>回報次數</th>
                    <th>總工時</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($data as $row): ?>
                <tr>
                    <td><a href="/staff.php?action=view&id=<?= $row['id'] ?>"><?= e($row['real_name']) ?></a></td>
                    <td><?= e($row['branch_name']) ?></td>
                    <td class="text-center"><?= (int)$row['schedule_count'] ?></td>
                    <td class="text-center"><?= (int)$row['case_count'] ?></td>
                    <td class="text-center"><?= (int)$row['worklog_count'] ?></td>
                    <td class="text-center">
                        <?php
                        $mins = (int)$row['total_minutes'];
                        echo floor($mins / 60) . '時' . ($mins % 60) . '分';
                        ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<style>
.filter-row { display: flex; flex-wrap: wrap; gap: 12px; align-items: flex-end; }
.filter-row .form-group { flex: 1; min-width: 130px; margin-bottom: 0; }
</style>
