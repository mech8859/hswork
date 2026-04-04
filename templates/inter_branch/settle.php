<div class="d-flex justify-between align-center flex-wrap gap-1 mb-2">
    <h2>月結確認</h2>
    <a href="/inter_branch.php" class="btn btn-outline btn-sm">返回列表</a>
</div>

<!-- 月份選擇 -->
<div class="card mb-2">
    <form method="GET" class="d-flex align-center gap-1">
        <input type="hidden" name="action" value="settle">
        <div class="form-group" style="margin:0">
            <label>結算月份</label>
            <input type="month" name="month" class="form-control" value="<?= e($month) ?>" onchange="this.form.submit()">
        </div>
    </form>
</div>

<!-- 摘要表格 -->
<div class="card">
    <div class="card-header">
        <?= e($month) ?> 點工費摘要
    </div>
    <?php if (empty($summary)): ?>
    <p class="text-muted text-center">本月無跨點支援記錄</p>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>原據點</th>
                    <th>支援據點</th>
                    <th class="text-right">總次數</th>
                    <th class="text-right">整日</th>
                    <th class="text-right">半日</th>
                    <th class="text-right">時數合計</th>
                    <th class="text-right">已結算</th>
                    <th class="text-right">未結算</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $totalCount = 0;
                $totalFull = 0;
                $totalHalf = 0;
                $totalHours = 0;
                $totalSettled = 0;
                foreach ($summary as $s):
                    $unsettled = $s['support_count'] - $s['settled_count'];
                    $totalCount += $s['support_count'];
                    $totalFull += $s['full_days'];
                    $totalHalf += $s['half_days'];
                    $totalHours += $s['total_hours'];
                    $totalSettled += $s['settled_count'];
                ?>
                <tr>
                    <td><?= e($s['from_branch']) ?></td>
                    <td><?= e($s['to_branch']) ?></td>
                    <td class="text-right"><?= $s['support_count'] ?></td>
                    <td class="text-right"><?= $s['full_days'] ?></td>
                    <td class="text-right"><?= $s['half_days'] ?></td>
                    <td class="text-right"><?= $s['total_hours'] ? $s['total_hours'] . 'h' : '-' ?></td>
                    <td class="text-right"><?= $s['settled_count'] ?></td>
                    <td class="text-right"><?= $unsettled > 0 ? '<span class="text-danger">' . $unsettled . '</span>' : '0' ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr style="font-weight:600; background:var(--gray-100);">
                    <td colspan="2" class="text-right">合計</td>
                    <td class="text-right"><?= $totalCount ?></td>
                    <td class="text-right"><?= $totalFull ?></td>
                    <td class="text-right"><?= $totalHalf ?></td>
                    <td class="text-right"><?= $totalHours ? $totalHours . 'h' : '-' ?></td>
                    <td class="text-right"><?= $totalSettled ?></td>
                    <td class="text-right"><?= ($totalCount - $totalSettled) > 0 ? '<span class="text-danger">' . ($totalCount - $totalSettled) . '</span>' : '0' ?></td>
                </tr>
            </tfoot>
        </table>
    </div>

    <?php $unsettledTotal = $totalCount - $totalSettled; ?>
    <?php if ($unsettledTotal > 0): ?>
    <div class="mt-2 d-flex justify-between align-center">
        <p>共 <strong><?= $unsettledTotal ?></strong> 筆未結算記錄</p>
        <form method="POST" style="display:inline">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-primary" onclick="return confirm('確定將 <?= e($month) ?> 所有未結算記錄標記為已結算？')">
                確認月結
            </button>
        </form>
    </div>
    <?php else: ?>
    <p class="text-success mt-1">本月全部已結算 ✓</p>
    <?php endif; ?>
    <?php endif; ?>
</div>
