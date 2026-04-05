<div class="d-flex justify-between align-center flex-wrap gap-1 mb-2">
    <h2>跨點點工費月結報表</h2>
    <?= back_button('/reports.php') ?>
</div>

<div class="card">
    <form method="GET" action="/reports.php" class="filter-form">
        <input type="hidden" name="action" value="inter_branch">
        <div class="filter-row">
            <div class="form-group">
                <label>月份</label>
                <input type="month" name="month" class="form-control" value="<?= e($month) ?>">
            </div>
            <div class="form-group" style="align-self:flex-end">
                <button type="submit" class="btn btn-primary btn-sm">查詢</button>
            </div>
        </div>
    </form>
</div>

<div class="card">
    <div class="card-header">
        <?php
        $monthTs = strtotime($month . '-01');
        echo date('Y', $monthTs) . '年' . date('n', $monthTs) . '月 跨點支援彙總';
        ?>
    </div>
    <?php if (empty($data)): ?>
        <p class="text-muted text-center">本月無跨點支援記錄</p>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>來源據點</th>
                    <th>支援據點</th>
                    <th>支援次數</th>
                    <th>整天</th>
                    <th>半天</th>
                    <th>時數合計</th>
                    <th>已結算</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $totalCount = 0;
                foreach ($data as $row):
                    $totalCount += $row['support_count'];
                ?>
                <tr>
                    <td><?= e($row['from_branch']) ?></td>
                    <td><?= e($row['to_branch']) ?></td>
                    <td class="text-center"><?= (int)$row['support_count'] ?></td>
                    <td class="text-center"><?= (int)$row['full_days'] ?></td>
                    <td class="text-center"><?= (int)$row['half_days'] ?></td>
                    <td class="text-center"><?= number_format($row['total_hours'], 1) ?>h</td>
                    <td class="text-center">
                        <?php if ($row['settled_count'] == $row['support_count']): ?>
                            <span class="badge badge-success">全部已結</span>
                        <?php elseif ($row['settled_count'] > 0): ?>
                            <span class="badge badge-warning"><?= (int)$row['settled_count'] ?>/<?= (int)$row['support_count'] ?></span>
                        <?php else: ?>
                            <span class="badge badge-danger">未結算</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr style="font-weight:600; background:var(--gray-100);">
                    <td colspan="2">合計</td>
                    <td class="text-center"><?= $totalCount ?></td>
                    <td colspan="4"></td>
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
