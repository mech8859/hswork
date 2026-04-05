<?php
$months = isset($analysis['months']) ? $analysis['months'] : array();
$nm = count($months);
$info = isset($analysis['sales_info']) ? $analysis['sales_info'] : null;
$caseTypeLabels = CaseModel::caseTypeOptions();
$otherTypes = array('addition','old_repair','new_repair','maintenance');
?>

<div class="d-flex justify-between align-center flex-wrap gap-1 mb-2">
    <h2>📊 業務個人分析</h2>
    <form method="GET" class="d-flex gap-1 align-center">
        <input type="hidden" name="action" value="sales_personal">
        <select name="sales_id" class="form-control" style="width:auto" onchange="this.form.submit()">
            <?php foreach ($salespeople as $sp): ?>
            <option value="<?= $sp['id'] ?>" <?= $salesId == $sp['id'] ? 'selected' : '' ?>><?= e($sp['real_name']) ?><?= !$sp['is_active'] ? ' (離職)' : '' ?></option>
            <?php endforeach; ?>
        </select>
        <select name="year" class="form-control" style="width:auto" onchange="this.form.submit()">
            <?php for ($y = (int)date('Y'); $y >= 2024; $y--): ?>
            <option value="<?= $y ?>" <?= $analysis['year'] == $y ? 'selected' : '' ?>><?= ($y - 1911) ?>年 (<?= $y ?>)</option>
            <?php endfor; ?>
        </select>
        <?= back_button('/reports.php') ?>
    </form>
</div>

<?php if (!$info): ?>
<div class="card"><p class="text-muted text-center mt-2">查無此業務員資料</p></div>
<?php else: ?>

<div class="analysis-summary mb-1">
    <span>業務員：<b><?= e($info['real_name']) ?></b></span>
    <span>分公司：<?= e($info['branch_name'] ?: '未設定') ?></span>
    <span>年度：<?= $analysis['year'] ?>年</span>
</div>

<!-- 績效總覽 -->
<div class="card">
    <div class="card-header analysis-header">績效總覽（個人 vs 團隊平均）</div>
    <div class="table-responsive">
        <table class="table table-sm analysis-table">
            <thead><tr><th>項目</th><th>個人</th><th>團隊平均 (<?= $analysis['team_avg']['sales_count'] ?>人)</th><th>差異</th></tr></thead>
            <tbody>
                <?php
                $personal = $analysis['personal'];
                $teamAvg = $analysis['team_avg'];
                $compItems = array(
                    array('進件數', $personal['entry'], $teamAvg['entry'], false, false),
                    array('成交數', $personal['closed'], $teamAvg['closed'], false, false),
                    array('成交率', $personal['close_rate'], $teamAvg['close_rate'], false, true),
                    array('成交金額', $personal['deal_amount'], $teamAvg['deal_amount'], true, false),
                );
                foreach ($compItems as $ci):
                    $diff = $ci[1] - $ci[2];
                    $color = $diff > 0 ? 'green' : ($diff < 0 ? '#e53e3e' : '');
                    $sign = $diff > 0 ? '+' : '';
                    $isMoney = $ci[3];
                    $isPct = $ci[4];
                ?>
                <tr>
                    <td><?= $ci[0] ?></td>
                    <td style="font-weight:bold"><?= $isMoney ? '$' . number_format($ci[1]) : ($isPct ? $ci[1] . '%' : number_format($ci[1], is_float($ci[1]) ? 1 : 0)) ?></td>
                    <td><?= $isMoney ? '$' . number_format($ci[2]) : ($isPct ? $ci[2] . '%' : number_format($ci[2], is_float($ci[2]) ? 1 : 0)) ?></td>
                    <td style="color:<?= $color ?>; font-weight:bold"><?= $isMoney ? $sign . '$' . number_format($diff) : ($isPct ? $sign . round($diff, 1) . '%' : $sign . round($diff, 1)) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- 一、個人各月數量統計 -->
<div class="card">
    <div class="card-header analysis-header">一、個人各月數量統計</div>
    <div class="table-responsive">
        <table class="table table-sm analysis-table">
            <thead><tr>
                <th>統計項目</th>
                <?php foreach ($months as $m): ?><th><?= (int)substr($m, 5) ?>月</th><?php endforeach; ?>
                <th class="col-total">合計</th>
            </tr></thead>
            <tbody>
                <tr>
                    <td>進件數</td>
                    <?php $te = 0; foreach ($months as $m): $v = $analysis['monthly_entry'][$m]; $te += $v; ?>
                    <td><?= $v ?: '' ?></td>
                    <?php endforeach; ?>
                    <td class="col-total"><?= number_format($te) ?></td>
                </tr>
                <tr>
                    <td>成交數</td>
                    <?php $tc = 0; foreach ($months as $m): $v = $analysis['monthly_closed'][$m]; $tc += $v; ?>
                    <td><?= $v ?: '' ?></td>
                    <?php endforeach; ?>
                    <td class="col-total"><?= number_format($tc) ?></td>
                </tr>
                <tr class="row-highlight">
                    <td>成交率</td>
                    <?php foreach ($months as $m):
                        $e = $analysis['monthly_entry'][$m];
                        $c = $analysis['monthly_closed'][$m];
                    ?>
                    <td><?= $e > 0 ? round($c / $e * 100, 1) . '%' : '' ?></td>
                    <?php endforeach; ?>
                    <td class="col-total"><?= $te > 0 ? round($tc / $te * 100, 1) . '%' : '' ?></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<!-- 二、新案統計 -->
<?php
$niEntry = isset($analysis['case_type_monthly_entry']['new_install']) ? $analysis['case_type_monthly_entry']['new_install'] : array();
$niClosed = isset($analysis['case_type_monthly_closed']['new_install']) ? $analysis['case_type_monthly_closed']['new_install'] : array();
?>
<div class="card">
    <div class="card-header analysis-header">二、新案 各月統計</div>
    <div class="table-responsive">
        <table class="table table-sm analysis-table">
            <thead><tr>
                <th>統計項目</th>
                <?php foreach ($months as $m): ?><th><?= (int)substr($m, 5) ?>月</th><?php endforeach; ?>
                <th class="col-total">合計</th>
            </tr></thead>
            <tbody>
                <tr>
                    <td>進件數</td>
                    <?php $ne = 0; foreach ($months as $m): $v = isset($niEntry[$m]) ? $niEntry[$m] : 0; $ne += $v; ?>
                    <td><?= $v ?: '' ?></td>
                    <?php endforeach; ?>
                    <td class="col-total"><?= $ne ?: '' ?></td>
                </tr>
                <tr>
                    <td>成交數</td>
                    <?php $nc = 0; foreach ($months as $m): $v = isset($niClosed[$m]) ? $niClosed[$m] : 0; $nc += $v; ?>
                    <td><?= $v ?: '' ?></td>
                    <?php endforeach; ?>
                    <td class="col-total"><?= $nc ?: '' ?></td>
                </tr>
                <tr class="row-highlight">
                    <td>成交率</td>
                    <?php foreach ($months as $m):
                        $e2 = isset($niEntry[$m]) ? $niEntry[$m] : 0;
                        $c2 = isset($niClosed[$m]) ? $niClosed[$m] : 0;
                    ?>
                    <td><?= $e2 > 0 ? round($c2 / $e2 * 100, 1) . '%' : '' ?></td>
                    <?php endforeach; ?>
                    <td class="col-total"><?= $ne > 0 ? round($nc / $ne * 100, 1) . '%' : '' ?></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<!-- 三、其他案別統計 -->
<div class="card">
    <div class="card-header analysis-header">三、其他案別 各月統計</div>
    <div class="table-responsive">
        <table class="table table-sm analysis-table">
            <thead><tr>
                <th>統計項目</th>
                <?php foreach ($months as $m): ?><th><?= (int)substr($m, 5) ?>月</th><?php endforeach; ?>
                <th class="col-total">合計</th>
            </tr></thead>
            <tbody>
                <?php $oe = 0; ?>
                <tr style="font-weight:bold; background:#f8f9fa;">
                    <td>進件數（合計）</td>
                    <?php foreach ($months as $m):
                        $v = 0;
                        foreach ($otherTypes as $ot) { $v += isset($analysis['case_type_monthly_entry'][$ot][$m]) ? $analysis['case_type_monthly_entry'][$ot][$m] : 0; }
                        $oe += $v;
                    ?>
                    <td><?= $v ?: '' ?></td>
                    <?php endforeach; ?>
                    <td class="col-total"><?= $oe ?: '' ?></td>
                </tr>
                <?php foreach ($otherTypes as $ct):
                    $label = isset($caseTypeLabels[$ct]) ? $caseTypeLabels[$ct] : $ct;
                    $data = isset($analysis['case_type_monthly_entry'][$ct]) ? $analysis['case_type_monthly_entry'][$ct] : array();
                    $rt = 0;
                ?>
                <tr>
                    <td style="padding-left:24px; color:#666;">└ <?= e($label) ?></td>
                    <?php foreach ($months as $m): $v = isset($data[$m]) ? $data[$m] : 0; $rt += $v; ?>
                    <td><?= $v ?: '' ?></td>
                    <?php endforeach; ?>
                    <td class="col-total"><?= $rt ?: '' ?></td>
                </tr>
                <?php endforeach; ?>

                <?php $oc = 0; ?>
                <tr style="font-weight:bold; background:#f8f9fa;">
                    <td>成交數（合計）</td>
                    <?php foreach ($months as $m):
                        $v = 0;
                        foreach ($otherTypes as $ot) { $v += isset($analysis['case_type_monthly_closed'][$ot][$m]) ? $analysis['case_type_monthly_closed'][$ot][$m] : 0; }
                        $oc += $v;
                    ?>
                    <td><?= $v ?: '' ?></td>
                    <?php endforeach; ?>
                    <td class="col-total"><?= $oc ?: '' ?></td>
                </tr>
                <?php foreach ($otherTypes as $ct):
                    $label = isset($caseTypeLabels[$ct]) ? $caseTypeLabels[$ct] : $ct;
                    $data = isset($analysis['case_type_monthly_closed'][$ct]) ? $analysis['case_type_monthly_closed'][$ct] : array();
                    $rt = 0;
                ?>
                <tr>
                    <td style="padding-left:24px; color:#666;">└ <?= e($label) ?></td>
                    <?php foreach ($months as $m): $v = isset($data[$m]) ? $data[$m] : 0; $rt += $v; ?>
                    <td><?= $v ?: '' ?></td>
                    <?php endforeach; ?>
                    <td class="col-total"><?= $rt ?: '' ?></td>
                </tr>
                <?php endforeach; ?>

                <tr class="row-highlight">
                    <td>成交率</td>
                    <?php foreach ($months as $m):
                        $eOth = 0; $cOth = 0;
                        foreach ($otherTypes as $ot) {
                            $eOth += isset($analysis['case_type_monthly_entry'][$ot][$m]) ? $analysis['case_type_monthly_entry'][$ot][$m] : 0;
                            $cOth += isset($analysis['case_type_monthly_closed'][$ot][$m]) ? $analysis['case_type_monthly_closed'][$ot][$m] : 0;
                        }
                    ?>
                    <td><?= $eOth > 0 ? round($cOth / $eOth * 100, 1) . '%' : '' ?></td>
                    <?php endforeach; ?>
                    <td class="col-total"><?= $oe > 0 ? round($oc / $oe * 100, 1) . '%' : '' ?></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<!-- 四、個人各月金額統計 -->
<div class="card">
    <div class="card-header analysis-header">四、個人各月金額統計（元）</div>
    <div class="table-responsive">
        <table class="table table-sm analysis-table">
            <thead><tr>
                <th>統計項目</th>
                <?php foreach ($months as $m): ?><th><?= (int)substr($m, 5) ?>月</th><?php endforeach; ?>
                <th class="col-total">合計</th>
            </tr></thead>
            <tbody>
                <?php
                $amtRows = array(
                    array('報價金額', 'monthly_quote_amount'),
                    array('含稅成交金額', 'monthly_deal_amount'),
                    array('收款金額', 'monthly_receipt'),
                );
                foreach ($amtRows as $ar):
                    $rt = 0;
                ?>
                <tr>
                    <td><?= $ar[0] ?></td>
                    <?php foreach ($months as $m): $v = isset($analysis[$ar[1]][$m]) ? (int)$analysis[$ar[1]][$m] : 0; $rt += $v; ?>
                    <td><?= $v > 0 ? number_format($v) : '' ?></td>
                    <?php endforeach; ?>
                    <td class="col-total"><?= $rt > 0 ? number_format($rt) : '' ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- 五、案件明細 -->
<?php if (!empty($analysis['case_list'])): ?>
<div class="card">
    <div class="card-header analysis-header">五、案件明細（共 <?= count($analysis['case_list']) ?> 筆）</div>
    <div class="table-responsive">
        <table class="table table-sm">
            <thead><tr>
                <th>編號</th><th>案件名稱</th><th>案別</th><th>進度</th><th>狀態</th><th>成交金額</th><th>成交日期</th>
            </tr></thead>
            <tbody>
                <?php foreach ($analysis['case_list'] as $c): ?>
                <tr>
                    <td><a href="/cases.php?action=edit&id=<?= urlencode($c['case_number']) ?>"><?= e($c['case_number']) ?></a></td>
                    <td><?= e($c['title']) ?></td>
                    <td><?= e(isset($caseTypeLabels[$c['case_type']]) ? $caseTypeLabels[$c['case_type']] : $c['case_type']) ?></td>
                    <td><span class="badge badge-<?= CaseModel::statusBadgeClass($c['status']) ?>"><?= e(CaseModel::statusLabel($c['status'])) ?></span></td>
                    <td><?= e($c['sub_status'] ?: '') ?></td>
                    <td style="text-align:right"><?= $c['deal_amount'] > 0 ? number_format($c['deal_amount']) : '' ?></td>
                    <td><?= e($c['deal_date'] ?: '') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php endif; ?>
