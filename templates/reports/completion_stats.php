<div class="d-flex justify-between align-center mb-2 flex-wrap gap-1">
    <h2>完工統計</h2>
    <a href="/reports.php" class="btn btn-outline btn-sm">← 返回報表首頁</a>
</div>

<!-- 篩選器 -->
<div class="card">
    <form method="GET" class="d-flex align-center gap-1 flex-wrap" style="margin:0">
        <input type="hidden" name="action" value="completion_stats">
        <label style="font-size:.9rem">年度：</label>
        <select name="year" class="form-control form-control-sm" style="width:auto" onchange="this.form.submit()">
            <?php for ($y = (int)date('Y'); $y >= 2024; $y--): ?>
            <option value="<?= $y ?>" <?= $statsYear === $y ? 'selected' : '' ?>><?= ($y - 1911) ?>年（<?= $y ?>）</option>
            <?php endfor; ?>
        </select>

        <label style="font-size:.9rem;margin-left:8px">分公司：</label>
        <select name="branch_id" class="form-control form-control-sm" style="width:auto" onchange="this.form.submit()">
            <option value="0">全部</option>
            <?php foreach ($allBranches as $b): ?>
            <option value="<?= (int)$b['id'] ?>" <?= $statsBranchId === (int)$b['id'] ? 'selected' : '' ?>><?= e($b['name']) ?></option>
            <?php endforeach; ?>
        </select>

        <span style="font-size:.8rem;color:var(--gray-500);margin-left:auto">基準：以完工日 + 成交金額</span>
    </form>
</div>

<!-- 年度合計 -->
<div class="card">
    <div class="card-header">年度總覽（各年完工件數與金額）</div>
    <?php if (empty($yearStats)): ?>
        <p class="text-muted text-center mt-2">尚無完工資料</p>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>年度</th>
                    <th class="text-right">完工件數</th>
                    <th class="text-right">完工金額（成交）</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $grandCnt = 0; $grandAmt = 0;
                foreach ($yearStats as $ys):
                    $grandCnt += (int)$ys['cnt'];
                    $grandAmt += (int)$ys['amt'];
                ?>
                <tr>
                    <td>
                        <a href="/reports.php?action=completion_stats&year=<?= (int)$ys['y'] ?>&branch_id=<?= (int)$statsBranchId ?>">
                            <?= ((int)$ys['y'] - 1911) ?>年（<?= (int)$ys['y'] ?>）
                        </a>
                    </td>
                    <td class="text-right"><?= number_format((int)$ys['cnt']) ?></td>
                    <td class="text-right">$<?= number_format((int)$ys['amt']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr style="font-weight:600;background:var(--gray-50,#f8f9fa)">
                    <td>合計</td>
                    <td class="text-right"><?= number_format($grandCnt) ?></td>
                    <td class="text-right">$<?= number_format($grandAmt) ?></td>
                </tr>
            </tfoot>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- 月份明細 -->
<div class="card">
    <div class="card-header"><?= ($statsYear - 1911) ?>年（<?= $statsYear ?>）月份明細</div>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>月份</th>
                    <th class="text-right">完工件數</th>
                    <th class="text-right">完工金額（成交）</th>
                    <th class="text-right">累計件數</th>
                    <th class="text-right">累計金額</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $cumCnt = 0; $cumAmt = 0;
                $yearTotalCnt = 0; $yearTotalAmt = 0;
                for ($mi = 1; $mi <= 12; $mi++):
                    $cnt = (int)$monthStats[$mi]['cnt'];
                    $amt = (int)$monthStats[$mi]['amt'];
                    $cumCnt += $cnt;
                    $cumAmt += $amt;
                    $yearTotalCnt += $cnt;
                    $yearTotalAmt += $amt;
                ?>
                <tr<?= $cnt === 0 ? ' style="color:#aaa"' : '' ?>>
                    <td><?= $mi ?>月</td>
                    <td class="text-right"><?= $cnt > 0 ? number_format($cnt) : '-' ?></td>
                    <td class="text-right"><?= $amt > 0 ? '$' . number_format($amt) : '-' ?></td>
                    <td class="text-right"><?= number_format($cumCnt) ?></td>
                    <td class="text-right">$<?= number_format($cumAmt) ?></td>
                </tr>
                <?php endfor; ?>
            </tbody>
            <tfoot>
                <tr style="font-weight:600;background:var(--gray-50,#f8f9fa)">
                    <td>年度合計</td>
                    <td class="text-right"><?= number_format($yearTotalCnt) ?></td>
                    <td class="text-right">$<?= number_format($yearTotalAmt) ?></td>
                    <td class="text-right">—</td>
                    <td class="text-right">—</td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
