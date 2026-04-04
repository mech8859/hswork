<?php
$months = isset($analysis['months']) ? $analysis['months'] : array();
$nm = count($months);
$branches = array('潭子分公司','員林分公司','清水分公司','東區電子鎖','清水電子鎖','中區專案部','中區管理處');

// Helper: pivot row total
function pivotRowTotal2($data, $months) {
    $t = 0;
    foreach ($months as $m) { $t += isset($data[$m]) ? $data[$m] : 0; }
    return $t;
}
?>

<div class="d-flex justify-between align-center flex-wrap gap-1 mb-2">
    <h2>💰 財務綜合分析</h2>
    <form method="GET" class="d-flex gap-1 align-center">
        <input type="hidden" name="action" value="finance_analysis">
        <select name="year" class="form-control" style="width:auto" onchange="this.form.submit()">
            <?php for ($y = (int)date('Y'); $y >= 2024; $y--): ?>
            <option value="<?= $y ?>" <?= $analysis['year'] == $y ? 'selected' : '' ?>><?= ($y - 1911) ?>年 (<?= $y ?>)</option>
            <?php endfor; ?>
        </select>
        <a href="/reports.php" class="btn btn-outline btn-sm">返回報表</a>
    </form>
</div>

<?php if (empty($months)): ?>
<div class="card"><p class="text-muted text-center mt-2">該年度無財務資料</p></div>
<?php else: ?>

<div class="analysis-summary mb-1">
    <span>資料來源：財務管理</span>
    <span>收款筆數：<b><?= number_format($analysis['recv_count']) ?></b></span>
    <span>付款筆數：<b><?= number_format($analysis['pay_count']) ?></b></span>
    <span>分析月份：<?= e($months[0]) ?> ～ <?= e($months[$nm - 1]) ?></span>
</div>

<!-- 一、即時公司資金總覽 -->
<div class="card">
    <div class="card-header analysis-header">一、即時公司資金總覽</div>
    <div style="padding:16px;">
        <?php
        $grand_total = isset($analysis['grand_total']) ? $analysis['grand_total'] : 0;
        $bank_total = isset($analysis['bank_total']) ? $analysis['bank_total'] : 0;
        $weekly_fund = isset($analysis['weekly_fund']) ? $analysis['weekly_fund'] : 0;
        $petty_total = isset($analysis['petty_total']) ? $analysis['petty_total'] : 0;
        $reserve_net = isset($analysis['reserve_net']) ? $analysis['reserve_net'] : 0;
        $cash_net = isset($analysis['cash_net']) ? $analysis['cash_net'] : 0;
        ?>
        <!-- Big total card -->
        <div class="fund-card-big">
            <div class="fund-card-big-label">🏢 公司資金總和</div>
            <div class="fund-card-big-amount">NT$ <?= number_format($grand_total) ?></div>
        </div>

        <!-- 5 sub-cards -->
        <div class="fund-cards">
            <?php
            $subItems = array(
                array('icon' => '🏦', 'label' => '銀行合計', 'amount' => $bank_total),
                array('icon' => '🔄', 'label' => '週轉金', 'amount' => $weekly_fund),
                array('icon' => '🪙', 'label' => '零用金', 'amount' => $petty_total),
                array('icon' => '💼', 'label' => '備用金', 'amount' => $reserve_net),
                array('icon' => '💵', 'label' => '現金淨額', 'amount' => $cash_net),
            );
            foreach ($subItems as $item):
                $pct = $grand_total != 0 ? round($item['amount'] / $grand_total * 100, 1) : 0;
                $isNeg = $item['amount'] < 0;
                $bgColor = $isNeg ? '#FCE4D6' : '#E2EFDA';
                $textColor = $isNeg ? '#C00000' : '#375623';
            ?>
            <div class="fund-card" style="background:<?= $bgColor ?>;">
                <div class="fund-card-icon"><?= $item['icon'] ?></div>
                <div class="fund-card-label"><?= e($item['label']) ?></div>
                <div class="fund-card-amount" style="color:<?= $textColor ?>;">NT$ <?= number_format($item['amount']) ?></div>
                <div class="pct-label"><?= $pct ?>%</div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- 二、完整資金明細 -->
<div class="card">
    <div class="card-header analysis-header">二、完整資金明細</div>
    <div class="table-responsive">
        <table class="table table-sm analysis-table">
            <thead><tr>
                <th>類別</th>
                <th>名稱</th>
                <th>轉入/收入</th>
                <th>轉出/支出</th>
                <th>最新餘額</th>
                <th>佔總資金</th>
                <th>狀態</th>
            </tr></thead>
            <tbody>
                <?php
                $detailTotal_in = 0;
                $detailTotal_out = 0;
                $detailTotal_bal = 0;

                // Bank accounts
                if (!empty($analysis['bank_latest'])):
                    foreach ($analysis['bank_latest'] as $bk):
                        $bal = isset($bk['balance']) ? $bk['balance'] : 0;
                        $bkIn = isset($bk['total_in']) ? $bk['total_in'] : 0;
                        $bkOut = isset($bk['total_out']) ? $bk['total_out'] : 0;
                        $pct = $grand_total != 0 ? round($bal / $grand_total * 100, 1) : 0;
                        $detailTotal_in += $bkIn;
                        $detailTotal_out += $bkOut;
                        $detailTotal_bal += $bal;
                ?>
                <tr>
                    <td>🏦 銀行</td>
                    <td><?= e($bk['name']) ?></td>
                    <td><?= $bkIn ? number_format($bkIn) : '-' ?></td>
                    <td><?= $bkOut ? number_format($bkOut) : '-' ?></td>
                    <td class="<?= $bal < 0 ? 'text-red' : '' ?>"><?= number_format($bal) ?></td>
                    <td><?= $pct ?>%</td>
                    <td><?= $bal > 0 ? '<span class="text-green">正常</span>' : '<span class="text-red">注意</span>' ?></td>
                </tr>
                <?php
                    endforeach;
                endif;

                // Weekly fund
                $wf = $weekly_fund;
                $wfPct = $grand_total != 0 ? round($wf / $grand_total * 100, 1) : 0;
                $detailTotal_bal += $wf;
                ?>
                <tr>
                    <td>🔄 週轉金</td>
                    <td>週轉金</td>
                    <td>-</td>
                    <td>-</td>
                    <td class="<?= $wf < 0 ? 'text-red' : '' ?>"><?= number_format($wf) ?></td>
                    <td><?= $wfPct ?>%</td>
                    <td><?= $wf >= 0 ? '<span class="text-green">正常</span>' : '<span class="text-red">注意</span>' ?></td>
                </tr>

                <?php
                // Petty cash per branch
                if (!empty($analysis['petty_balance'])):
                    foreach ($analysis['petty_balance'] as $pb):
                        $pIn = isset($pb['income']) ? $pb['income'] : 0;
                        $pOut = isset($pb['expense']) ? $pb['expense'] : 0;
                        $pNet = isset($pb['net']) ? $pb['net'] : 0;
                        $pPct = $grand_total != 0 ? round($pNet / $grand_total * 100, 1) : 0;
                        $detailTotal_in += $pIn;
                        $detailTotal_out += $pOut;
                        $detailTotal_bal += $pNet;
                ?>
                <tr>
                    <td>🪙 零用金</td>
                    <td><?= e($pb['branch']) ?></td>
                    <td><?= number_format($pIn) ?></td>
                    <td><?= number_format($pOut) ?></td>
                    <td class="<?= $pNet < 0 ? 'text-red' : '' ?>"><?= number_format($pNet) ?></td>
                    <td><?= $pPct ?>%</td>
                    <td><?= $pNet >= 0 ? '<span class="text-green">正常</span>' : '<span class="text-red">注意</span>' ?></td>
                </tr>
                <?php
                    endforeach;
                endif;

                // Reserve fund
                $resIn = isset($analysis['reserve_in']) ? $analysis['reserve_in'] : 0;
                $resOut = isset($analysis['reserve_out']) ? $analysis['reserve_out'] : 0;
                $resPct = $grand_total != 0 ? round($reserve_net / $grand_total * 100, 1) : 0;
                $detailTotal_in += $resIn;
                $detailTotal_out += $resOut;
                $detailTotal_bal += $reserve_net;
                ?>
                <tr>
                    <td>💼 備用金</td>
                    <td>備用金</td>
                    <td><?= number_format($resIn) ?></td>
                    <td><?= number_format($resOut) ?></td>
                    <td class="<?= $reserve_net < 0 ? 'text-red' : '' ?>"><?= number_format($reserve_net) ?></td>
                    <td><?= $resPct ?>%</td>
                    <td><?= $reserve_net >= 0 ? '<span class="text-green">正常</span>' : '<span class="text-red">注意</span>' ?></td>
                </tr>

                <?php
                // Cash
                $cashIn = isset($analysis['cash_in']) ? $analysis['cash_in'] : 0;
                $cashOut = isset($analysis['cash_out']) ? $analysis['cash_out'] : 0;
                $cashPct = $grand_total != 0 ? round($cash_net / $grand_total * 100, 1) : 0;
                $detailTotal_in += $cashIn;
                $detailTotal_out += $cashOut;
                $detailTotal_bal += $cash_net;
                ?>
                <tr>
                    <td>💵 現金</td>
                    <td>現金</td>
                    <td><?= number_format($cashIn) ?></td>
                    <td><?= number_format($cashOut) ?></td>
                    <td class="<?= $cash_net < 0 ? 'text-red' : '' ?>"><?= number_format($cash_net) ?></td>
                    <td><?= $cashPct ?>%</td>
                    <td><?= $cash_net >= 0 ? '<span class="text-green">正常</span>' : '<span class="text-red">注意</span>' ?></td>
                </tr>

                <tr class="row-total">
                    <td colspan="2">合計</td>
                    <td><?= number_format($detailTotal_in) ?></td>
                    <td><?= number_format($detailTotal_out) ?></td>
                    <td><?= number_format($grand_total) ?></td>
                    <td>100%</td>
                    <td></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<!-- 三、各分公司月別收款金額 -->
<?php if (!empty($analysis['recv_pivot'])): ?>
<div class="card">
    <div class="card-header analysis-header">三、各分公司月別收款金額（元）</div>
    <div class="table-responsive">
        <table class="table table-sm analysis-table">
            <thead><tr>
                <th>分公司</th>
                <?php foreach ($months as $m): ?><th><?= substr($m, 5) ?>月</th><?php endforeach; ?>
                <th class="col-total">合計</th>
                <th>佔比</th>
            </tr></thead>
            <tbody>
                <?php
                $recvGrand = 0;
                foreach ($analysis['recv_pivot'] as $br => $mdata) {
                    $recvGrand += pivotRowTotal2($mdata, $months);
                }
                foreach ($analysis['recv_pivot'] as $br => $mdata):
                    $rt = pivotRowTotal2($mdata, $months);
                ?>
                <tr>
                    <td><?= e($br) ?></td>
                    <?php foreach ($months as $m): $v = isset($mdata[$m]) ? $mdata[$m] : 0; ?>
                    <td><?= $v > 0 ? number_format($v) : '' ?></td>
                    <?php endforeach; ?>
                    <td class="col-total"><?= number_format($rt) ?></td>
                    <td><?= $recvGrand > 0 ? round($rt / $recvGrand * 100, 1) . '%' : '' ?></td>
                </tr>
                <?php endforeach; ?>
                <tr class="row-total">
                    <td>合計</td>
                    <?php foreach ($months as $m):
                        $ct = 0;
                        foreach ($analysis['recv_pivot'] as $d) { $ct += isset($d[$m]) ? $d[$m] : 0; }
                    ?>
                    <td><?= $ct > 0 ? number_format($ct) : '' ?></td>
                    <?php endforeach; ?>
                    <td class="col-total"><?= number_format($recvGrand) ?></td>
                    <td>100%</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- 四、各分公司月別付款金額 -->
<?php if (!empty($analysis['pay_pivot'])): ?>
<div class="card">
    <div class="card-header analysis-header">四、各分公司月別付款金額（元）</div>
    <div class="table-responsive">
        <table class="table table-sm analysis-table">
            <thead><tr>
                <th>分公司</th>
                <?php foreach ($months as $m): ?><th><?= substr($m, 5) ?>月</th><?php endforeach; ?>
                <th class="col-total">合計</th>
                <th>佔比</th>
            </tr></thead>
            <tbody>
                <?php
                $payGrand = 0;
                foreach ($analysis['pay_pivot'] as $br => $mdata) {
                    $payGrand += pivotRowTotal2($mdata, $months);
                }
                foreach ($analysis['pay_pivot'] as $br => $mdata):
                    $rt = pivotRowTotal2($mdata, $months);
                ?>
                <tr>
                    <td><?= e($br) ?></td>
                    <?php foreach ($months as $m): $v = isset($mdata[$m]) ? $mdata[$m] : 0; ?>
                    <td><?= $v > 0 ? number_format($v) : '' ?></td>
                    <?php endforeach; ?>
                    <td class="col-total"><?= number_format($rt) ?></td>
                    <td><?= $payGrand > 0 ? round($rt / $payGrand * 100, 1) . '%' : '' ?></td>
                </tr>
                <?php endforeach; ?>
                <tr class="row-total">
                    <td>合計</td>
                    <?php foreach ($months as $m):
                        $ct = 0;
                        foreach ($analysis['pay_pivot'] as $d) { $ct += isset($d[$m]) ? $d[$m] : 0; }
                    ?>
                    <td><?= $ct > 0 ? number_format($ct) : '' ?></td>
                    <?php endforeach; ?>
                    <td class="col-total"><?= number_format($payGrand) ?></td>
                    <td>100%</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- 五、各分公司收支差額 -->
<?php if (!empty($analysis['recv_pivot']) || !empty($analysis['pay_pivot'])): ?>
<div class="card">
    <div class="card-header analysis-header">五、各分公司收支差額（收款 - 付款）</div>
    <div class="table-responsive">
        <table class="table table-sm analysis-table">
            <thead><tr>
                <th>分公司</th>
                <?php foreach ($months as $m): ?><th><?= substr($m, 5) ?>月</th><?php endforeach; ?>
                <th class="col-total">合計</th>
            </tr></thead>
            <tbody>
                <?php
                $recv_pivot = isset($analysis['recv_pivot']) ? $analysis['recv_pivot'] : array();
                $pay_pivot = isset($analysis['pay_pivot']) ? $analysis['pay_pivot'] : array();
                $allBranches = array_unique(array_merge(array_keys($recv_pivot), array_keys($pay_pivot)));

                foreach ($allBranches as $br):
                    $rowTotal = 0;
                ?>
                <tr>
                    <td><?= e($br) ?></td>
                    <?php foreach ($months as $m):
                        $rv = isset($recv_pivot[$br][$m]) ? $recv_pivot[$br][$m] : 0;
                        $pv = isset($pay_pivot[$br][$m]) ? $pay_pivot[$br][$m] : 0;
                        $diff = $rv - $pv;
                        $rowTotal += $diff;
                    ?>
                    <td class="<?= $diff < 0 ? 'text-red' : ($diff > 0 ? 'text-green' : '') ?>"><?= $diff != 0 ? number_format($diff) : '' ?></td>
                    <?php endforeach; ?>
                    <td class="col-total <?= $rowTotal < 0 ? 'text-red' : ($rowTotal > 0 ? 'text-green' : '') ?>"><?= number_format($rowTotal) ?></td>
                </tr>
                <?php endforeach; ?>
                <tr class="row-total">
                    <td>合計</td>
                    <?php
                    $grandDiffTotal = 0;
                    foreach ($months as $m):
                        $mRecv = 0; $mPay = 0;
                        foreach ($recv_pivot as $d) { $mRecv += isset($d[$m]) ? $d[$m] : 0; }
                        foreach ($pay_pivot as $d) { $mPay += isset($d[$m]) ? $d[$m] : 0; }
                        $mDiff = $mRecv - $mPay;
                        $grandDiffTotal += $mDiff;
                    ?>
                    <td class="<?= $mDiff < 0 ? 'text-red' : ($mDiff > 0 ? 'text-green' : '') ?>"><?= number_format($mDiff) ?></td>
                    <?php endforeach; ?>
                    <td class="col-total <?= $grandDiffTotal < 0 ? 'text-red' : '' ?>"><?= number_format($grandDiffTotal) ?></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- 六、現金明細月別彙總 -->
<?php if (!empty($analysis['cash_monthly'])): ?>
<div class="card">
    <div class="card-header analysis-header">六、現金明細月別彙總</div>
    <div class="table-responsive">
        <table class="table table-sm analysis-table">
            <thead><tr>
                <th>月份</th>
                <?php foreach ($branches as $br): ?>
                <th colspan="3" style="text-align:center;border-left:2px solid var(--gray-300);"><?= e($br) ?></th>
                <?php endforeach; ?>
                <th colspan="3" style="text-align:center;border-left:2px solid var(--gray-300);">合計</th>
            </tr>
            <tr>
                <th></th>
                <?php foreach ($branches as $br): ?>
                <th style="border-left:2px solid var(--gray-300);font-size:.7rem;">收入</th>
                <th style="font-size:.7rem;">支出</th>
                <th style="font-size:.7rem;">淨額</th>
                <?php endforeach; ?>
                <th style="border-left:2px solid var(--gray-300);font-size:.7rem;">收入</th>
                <th style="font-size:.7rem;">支出</th>
                <th style="font-size:.7rem;">淨額</th>
            </tr></thead>
            <tbody>
                <?php
                $cashMonthly = $analysis['cash_monthly'];
                $colTotals = array();
                foreach ($branches as $br) {
                    $colTotals[$br] = array('in' => 0, 'out' => 0);
                }
                $colTotals['_all'] = array('in' => 0, 'out' => 0);

                foreach ($months as $m):
                    $mBranches = isset($cashMonthly[$m]) ? $cashMonthly[$m] : array();
                    $mTotalIn = 0; $mTotalOut = 0;
                ?>
                <tr>
                    <td><?= substr($m, 5) ?>月</td>
                    <?php foreach ($branches as $br):
                        $bIn = isset($mBranches[$br]['in']) ? $mBranches[$br]['in'] : 0;
                        $bOut = isset($mBranches[$br]['out']) ? $mBranches[$br]['out'] : 0;
                        $bNet = $bIn - $bOut;
                        $mTotalIn += $bIn; $mTotalOut += $bOut;
                        $colTotals[$br]['in'] += $bIn;
                        $colTotals[$br]['out'] += $bOut;
                    ?>
                    <td style="border-left:2px solid var(--gray-300);"><?= $bIn > 0 ? number_format($bIn) : '' ?></td>
                    <td><?= $bOut > 0 ? number_format($bOut) : '' ?></td>
                    <td class="<?= $bNet < 0 ? 'text-red' : ($bNet > 0 ? 'text-green' : '') ?>"><?= $bNet != 0 ? number_format($bNet) : '' ?></td>
                    <?php endforeach; ?>
                    <?php
                    $mNet = $mTotalIn - $mTotalOut;
                    $colTotals['_all']['in'] += $mTotalIn;
                    $colTotals['_all']['out'] += $mTotalOut;
                    ?>
                    <td style="border-left:2px solid var(--gray-300);"><?= $mTotalIn > 0 ? number_format($mTotalIn) : '' ?></td>
                    <td><?= $mTotalOut > 0 ? number_format($mTotalOut) : '' ?></td>
                    <td class="<?= $mNet < 0 ? 'text-red' : ($mNet > 0 ? 'text-green' : '') ?>"><?= $mNet != 0 ? number_format($mNet) : '' ?></td>
                </tr>
                <?php endforeach; ?>
                <tr class="row-total">
                    <td>合計</td>
                    <?php foreach ($branches as $br):
                        $tIn = $colTotals[$br]['in'];
                        $tOut = $colTotals[$br]['out'];
                        $tNet = $tIn - $tOut;
                    ?>
                    <td style="border-left:2px solid var(--gray-300);"><?= number_format($tIn) ?></td>
                    <td><?= number_format($tOut) ?></td>
                    <td class="<?= $tNet < 0 ? 'text-red' : ($tNet > 0 ? 'text-green' : '') ?>"><?= number_format($tNet) ?></td>
                    <?php endforeach; ?>
                    <?php $allNet = $colTotals['_all']['in'] - $colTotals['_all']['out']; ?>
                    <td style="border-left:2px solid var(--gray-300);"><?= number_format($colTotals['_all']['in']) ?></td>
                    <td><?= number_format($colTotals['_all']['out']) ?></td>
                    <td class="<?= $allNet < 0 ? 'text-red' : ($allNet > 0 ? 'text-green' : '') ?>"><?= number_format($allNet) ?></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- 七、每日收支差額總表 -->
<?php if (!empty($analysis['daily_net'])): ?>
<div class="card">
    <div class="card-header analysis-header">七、每日收支差額總表</div>
    <div class="table-responsive">
        <table class="table table-sm analysis-table">
            <thead><tr>
                <th>日期</th>
                <?php foreach ($branches as $br): ?>
                <th colspan="2" style="text-align:center;border-left:2px solid var(--gray-300);"><?= e($br) ?></th>
                <?php endforeach; ?>
                <th style="border-left:2px solid var(--gray-300);">日收款合計</th>
                <th>日付款合計</th>
                <th>日淨額</th>
                <th style="border-left:2px solid var(--gray-300);">銀行餘額</th>
                <th>週轉金</th>
            </tr>
            <tr>
                <th></th>
                <?php foreach ($branches as $br): ?>
                <th style="border-left:2px solid var(--gray-300);font-size:.7rem;">收</th>
                <th style="font-size:.7rem;">付</th>
                <?php endforeach; ?>
                <th style="border-left:2px solid var(--gray-300);"></th>
                <th></th>
                <th></th>
                <th style="border-left:2px solid var(--gray-300);"></th>
                <th></th>
            </tr></thead>
            <tbody>
                <?php
                $dailyNet = $analysis['daily_net'];
                ksort($dailyNet);
                foreach ($dailyNet as $date => $dd):
                    $dRecv = isset($dd['recv']) ? $dd['recv'] : array();
                    $dPay = isset($dd['pay']) ? $dd['pay'] : array();
                    $dayBank = isset($dd['bank']) ? $dd['bank'] : 0;
                    $dayWF = isset($dd['weekly_fund']) ? $dd['weekly_fund'] : 0;
                    $dayRecvTotal = 0; $dayPayTotal = 0;
                ?>
                <tr>
                    <td><?= e($date) ?></td>
                    <?php foreach ($branches as $br):
                        $rv = isset($dRecv[$br]) ? $dRecv[$br] : 0;
                        $pv = isset($dPay[$br]) ? $dPay[$br] : 0;
                        $dayRecvTotal += $rv;
                        $dayPayTotal += $pv;
                    ?>
                    <td style="border-left:2px solid var(--gray-300);"><?= $rv > 0 ? number_format($rv) : '' ?></td>
                    <td><?= $pv > 0 ? number_format($pv) : '' ?></td>
                    <?php endforeach; ?>
                    <?php $dayNet = $dayRecvTotal - $dayPayTotal; ?>
                    <td style="border-left:2px solid var(--gray-300);"><?= $dayRecvTotal > 0 ? number_format($dayRecvTotal) : '' ?></td>
                    <td><?= $dayPayTotal > 0 ? number_format($dayPayTotal) : '' ?></td>
                    <td class="<?= $dayNet < 0 ? 'text-red' : ($dayNet > 0 ? 'text-green' : '') ?>"><?= $dayNet != 0 ? number_format($dayNet) : '' ?></td>
                    <td style="border-left:2px solid var(--gray-300);"><?= $dayBank > 0 ? number_format($dayBank) : '' ?></td>
                    <td><?= $dayWF > 0 ? number_format($dayWF) : '' ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php endif; ?>

<style>
/* Fund overview cards */
.fund-card-big {
    background: #1F3864;
    color: #FFD700;
    border-radius: 12px;
    padding: 24px 32px;
    text-align: center;
    margin-bottom: 16px;
    box-shadow: 0 4px 12px rgba(31,56,100,0.3);
}
.fund-card-big-label {
    font-size: 1rem;
    font-weight: 600;
    margin-bottom: 8px;
    letter-spacing: 1px;
}
.fund-card-big-amount {
    font-size: 2rem;
    font-weight: 700;
    letter-spacing: 1px;
}

.fund-cards {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 12px;
}
.fund-card {
    border-radius: 10px;
    padding: 16px;
    text-align: center;
    box-shadow: 0 2px 6px rgba(0,0,0,0.08);
    transition: transform 0.15s;
}
.fund-card:hover {
    transform: translateY(-2px);
}
.fund-card-icon {
    font-size: 1.5rem;
    margin-bottom: 4px;
}
.fund-card-label {
    font-size: .8rem;
    color: #555;
    font-weight: 600;
    margin-bottom: 6px;
}
.fund-card-amount {
    font-size: 1.1rem;
    font-weight: 700;
}
.pct-label {
    font-size: .7rem;
    color: #888;
    margin-top: 4px;
}

/* Text colors */
.text-red { color: #C00000 !important; font-weight: 600; }
.text-green { color: #375623 !important; font-weight: 600; }

/* Reuse analysis styles from case_analysis */
.analysis-summary { font-size: .85rem; color: var(--gray-500); display: flex; gap: 16px; flex-wrap: wrap; }
.analysis-header { background: var(--primary); color: #fff; font-weight: 600; }
.analysis-table { font-size: .8rem; white-space: nowrap; }
.analysis-table th { background: var(--gray-100); font-weight: 600; text-align: center; padding: 6px 8px; }
.analysis-table td { text-align: center; padding: 4px 8px; }
.analysis-table td:first-child, .analysis-table th:first-child { text-align: left; position: sticky; left: 0; background: #fff; z-index: 1; }
.analysis-table thead th:first-child { background: var(--gray-100); z-index: 2; }
.col-total { font-weight: 600; background: #fef3e2 !important; }
.row-total td { font-weight: 600; background: #fef3e2 !important; border-top: 2px solid var(--gray-300); }

/* Responsive */
@media (max-width: 768px) {
    .fund-cards {
        grid-template-columns: repeat(2, 1fr);
    }
    .fund-card-big-amount {
        font-size: 1.5rem;
    }
    .fund-card-amount {
        font-size: .95rem;
    }
}
@media (max-width: 480px) {
    .fund-cards {
        grid-template-columns: 1fr 1fr;
        gap: 8px;
    }
    .fund-card {
        padding: 12px 8px;
    }
    .fund-card-big {
        padding: 16px;
    }
    .fund-card-big-amount {
        font-size: 1.2rem;
    }
}
</style>
