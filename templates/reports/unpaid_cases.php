<div class="d-flex justify-between align-center flex-wrap gap-1 mb-2">
    <h2>完工未收款 / 未完工</h2>
    <a href="/reports.php" class="btn btn-outline btn-sm">← 返回報表</a>
</div>

<?php
$today = date('Y-m-d');
// 分開統計
$incompleteRows = array();
$unpaidRows = array();
$incompleteTotal = 0;
$unpaidTotal = 0;
foreach ($data['rows'] as $r) {
    $balance = (int)$r['balance_amount'];
    if ($r['status'] === 'incomplete') {
        $startDate = $r['created_at'] ? substr($r['created_at'], 0, 10) : null;
        $days = $startDate ? (int)((strtotime($today) - strtotime($startDate)) / 86400) : null;
        $r['_days'] = $days;
        $incompleteRows[] = $r;
        $incompleteTotal += $balance;
    } elseif ($r['status'] === 'unpaid') {
        $startDate = $r['completion_date'] ?: null;
        $days = $startDate ? (int)((strtotime($today) - strtotime($startDate)) / 86400) : null;
        $r['_days'] = $days;
        $unpaidRows[] = $r;
        $unpaidTotal += $balance;
    }
}
// 天數多在前（null 放最前）
$daysSort = function($a, $b) {
    if ($a['_days'] === null && $b['_days'] === null) return 0;
    if ($a['_days'] === null) return -1;
    if ($b['_days'] === null) return 1;
    return $b['_days'] - $a['_days'];
};
usort($incompleteRows, $daysSort);
usort($unpaidRows, $daysSort);

$renderTable = function($rows, $startLabel, $isCompletionDate) {
    if (empty($rows)) {
        echo '<p class="text-muted text-center" style="padding:40px">目前沒有資料 ✅</p>';
        return;
    }
    echo '<div class="table-responsive"><table class="table">';
    echo '<thead><tr>';
    echo '<th>案件編號</th>';
    echo '<th style="white-space:nowrap">進件日期</th>';
    echo '<th style="white-space:nowrap">完工日期</th>';
    echo '<th style="white-space:nowrap" class="text-right">天數</th>';
    echo '<th>客戶名稱 / 案件名稱</th>';
    echo '<th class="text-right" style="white-space:nowrap">尾款金額</th>';
    echo '<th>分公司</th>';
    echo '<th>業務</th>';
    echo '</tr></thead><tbody>';
    foreach ($rows as $r) {
        $days = $r['_days'];
        $daysColor = $days === null ? '#c62828' : ($days > 60 ? '#c62828' : ($days > 30 ? '#e65100' : '#555'));
        $daysText = $days === null ? '-' : $days . ' 天';
        echo '<tr>';
        echo '<td><a href="/cases.php?action=edit&id=' . (int)$r['id'] . '" style="font-weight:600">' . e($r['case_number'] ?: '-') . '</a></td>';
        echo '<td style="white-space:nowrap">' . (!empty($r['created_at']) ? date('Y-m-d', strtotime($r['created_at'])) : '-') . '</td>';
        echo '<td style="white-space:nowrap">';
        if (!empty($r['completion_date'])) {
            echo e($r['completion_date']);
        } else {
            echo '<span style="color:#c62828;font-weight:600">未完工</span>';
        }
        echo '</td>';
        echo '<td class="text-right" style="white-space:nowrap;font-weight:600;color:' . $daysColor . '">' . $daysText . '</td>';
        echo '<td>';
        echo e($r['title'] ?: $r['customer_name'] ?: '-');
        if (!empty($r['title']) && !empty($r['customer_name']) && $r['title'] !== $r['customer_name']) {
            echo '<br><small class="text-muted">' . e($r['customer_name']) . '</small>';
        }
        echo '</td>';
        echo '<td class="text-right" style="white-space:nowrap;font-weight:600;color:#e65100">$' . number_format((int)$r['balance_amount']) . '</td>';
        echo '<td>' . e($r['branch_name'] ?: '-') . '</td>';
        echo '<td>' . e($r['sales_name'] ?: '-') . '</td>';
        echo '</tr>';
    }
    echo '</tbody></table></div>';
};
?>

<!-- 未完工 -->
<div class="card mb-3" style="border-left:4px solid #c62828">
    <div class="card-header d-flex justify-between align-center" style="background:#ffebee">
        <span style="font-weight:600;color:#c62828">🚧 未完工（以進件日期起算）</span>
        <div class="d-flex gap-3 align-center">
            <span><strong style="font-size:1.4rem;color:#c62828"><?= count($incompleteRows) ?></strong> <small>筆</small></span>
            <span>尾款合計：<strong style="font-size:1.2rem;color:#e65100">$<?= number_format($incompleteTotal) ?></strong></span>
        </div>
    </div>
    <?php $renderTable($incompleteRows, '進件日期', false); ?>
</div>

<!-- 完工未收款 -->
<div class="card" style="border-left:4px solid #e65100">
    <div class="card-header d-flex justify-between align-center" style="background:#fff3e0">
        <span style="font-weight:600;color:#e65100">💰 完工未收款（以完工日起算）</span>
        <div class="d-flex gap-3 align-center">
            <span><strong style="font-size:1.4rem;color:#e65100"><?= count($unpaidRows) ?></strong> <small>筆</small></span>
            <span>尾款合計：<strong style="font-size:1.2rem;color:#e65100">$<?= number_format($unpaidTotal) ?></strong></span>
        </div>
    </div>
    <?php $renderTable($unpaidRows, '完工日期', true); ?>
</div>

<style>
.table tbody tr:hover { background: #fafafa; }
.mb-3 { margin-bottom: 16px; }
</style>
