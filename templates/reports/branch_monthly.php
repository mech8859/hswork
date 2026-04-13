<?php
$currentBranchName = '全部分公司';
if ($viewBranchId > 0) {
    foreach ($allBranches as $ab) {
        if ($ab['id'] == $viewBranchId) { $currentBranchName = $ab['name']; break; }
    }
}
?>
<div class="d-flex justify-between align-center flex-wrap gap-1 mb-2">
    <h2>📊 分公司月報 — <?= e($currentBranchName) ?></h2>
    <div class="d-flex gap-1 align-center">
        <?php if ($isBoss): ?>
        <select onchange="location.href='/reports.php?action=branch_monthly&year=<?= $year ?>&branch_id='+this.value" class="form-control" style="width:auto">
            <option value="0">全部分公司</option>
            <?php foreach ($allBranches as $ab): ?>
            <option value="<?= $ab['id'] ?>" <?= $viewBranchId == $ab['id'] ? 'selected' : '' ?>><?= e($ab['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <?php endif; ?>
        <select onchange="location.href='/reports.php?action=branch_monthly&year='+this.value+'&branch_id=<?= $viewBranchId ?>'" class="form-control" style="width:auto">
            <?php for ($yi = (int)date('Y'); $yi >= 2025; $yi--): ?>
            <option value="<?= $yi ?>" <?= $year == $yi ? 'selected' : '' ?>><?= $yi - 1911 ?>年 (<?= $yi ?>)</option>
            <?php endfor; ?>
        </select>
        <a href="/reports.php" class="btn btn-outline btn-sm">返回列表</a>
    </div>
</div>

<div class="card">
    <div class="card-header analysis-header">收款 / 付款 月份統計</div>
    <div class="table-responsive">
        <table class="table table-sm analysis-table">
            <thead><tr>
                <th>項目</th>
                <?php foreach ($bmMonths as $bm): ?><th class="text-right"><?= $bm['month'] ?>月</th><?php endforeach; ?>
                <th class="text-right col-total">合計</th>
            </tr></thead>
            <tbody>
                <?php $recvTotal = 0; $payTotal = 0; ?>
                <tr>
                    <td style="font-weight:600;color:#2e7d32">💰 收款</td>
                    <?php foreach ($bmMonths as $bm): $v = $bmRecv[$bm['month']]; $recvTotal += $v; ?>
                    <td class="text-right <?= $v ? 'drillable' : '' ?>" <?= $v ? 'style="cursor:pointer;color:#2e7d32" onclick="showBranchDetail(' . $year . ',' . $bm['month'] . ',' . $viewBranchId . ')"' : '' ?>><?= $v ? number_format($v) : '' ?></td>
                    <?php endforeach; ?>
                    <td class="text-right col-total" style="color:#2e7d32;font-weight:600"><?= number_format($recvTotal) ?></td>
                </tr>
                <tr>
                    <td style="font-weight:600;color:#c62828">💸 付款</td>
                    <?php foreach ($bmMonths as $bm): $v = $bmPay[$bm['month']]; $payTotal += $v; ?>
                    <td class="text-right <?= $v ? 'drillable' : '' ?>" <?= $v ? 'style="cursor:pointer;color:#c62828" onclick="showBranchDetail(' . $year . ',' . $bm['month'] . ',' . $viewBranchId . ')"' : '' ?>><?= $v ? number_format($v) : '' ?></td>
                    <?php endforeach; ?>
                    <td class="text-right col-total" style="color:#c62828;font-weight:600"><?= number_format($payTotal) ?></td>
                </tr>
                <tr class="row-highlight">
                    <td style="font-weight:600">📈 淨額</td>
                    <?php foreach ($bmMonths as $bm): $net = $bmRecv[$bm['month']] - $bmPay[$bm['month']]; ?>
                    <td class="text-right" style="font-weight:600;color:<?= $net >= 0 ? '#2e7d32' : '#c62828' ?>"><?= ($bmRecv[$bm['month']] || $bmPay[$bm['month']]) ? number_format($net) : '' ?></td>
                    <?php endforeach; ?>
                    <td class="text-right col-total" style="font-weight:700;color:<?= ($recvTotal - $payTotal) >= 0 ? '#2e7d32' : '#c62828' ?>"><?= number_format($recvTotal - $payTotal) ?></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<!-- 明細彈窗 -->
<div id="branchDetailModal" class="modal-overlay" style="display:none" onclick="if(event.target===this)closeBranchDetail()">
    <div class="modal-content" style="max-width:900px;max-height:85vh;overflow-y:auto">
        <div class="d-flex justify-between align-center mb-2">
            <h3 id="branchDetailTitle" style="margin:0"></h3>
            <button class="btn btn-outline btn-sm" onclick="closeBranchDetail()">✕</button>
        </div>
        <div id="branchDetailBody">載入中...</div>
    </div>
</div>

<script>
function showBranchDetail(year, month, branchId) {
    document.getElementById('branchDetailTitle').textContent = month + '月 收款/付款明細';
    document.getElementById('branchDetailBody').innerHTML = '<div style="text-align:center;padding:20px;color:#999">載入中...</div>';
    document.getElementById('branchDetailModal').style.display = 'flex';

    fetch('/reports.php?action=branch_monthly_detail&year=' + year + '&month=' + month + '&branch_id=' + branchId)
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (!data.success) { document.getElementById('branchDetailBody').innerHTML = '載入失敗'; return; }

        var html = '';

        // 收款
        html += '<h4 style="color:#2e7d32;margin-bottom:8px">💰 收款 (' + data.recv.length + '筆)</h4>';
        if (data.recv.length > 0) {
            var recvSum = 0;
            html += '<table class="table" style="font-size:.85rem"><thead><tr><th>收款單號</th><th>入帳日期</th><th>客戶</th><th>方式</th><th class="text-right">金額</th></tr></thead><tbody>';
            for (var i = 0; i < data.recv.length; i++) {
                var r = data.recv[i];
                recvSum += parseInt(r.total_amount) || 0;
                html += '<tr><td>' + (r.receipt_number || '-') + '</td><td>' + (r.deposit_date || '-') + '</td><td>' + (r.customer_name || '-') + '</td><td>' + (r.receipt_method || '-') + '</td><td class="text-right">$' + Number(r.total_amount).toLocaleString() + '</td></tr>';
            }
            html += '<tr style="font-weight:600;border-top:2px solid #ccc"><td colspan="4" class="text-right">合計</td><td class="text-right">$' + recvSum.toLocaleString() + '</td></tr>';
            html += '</tbody></table>';
        } else {
            html += '<div style="color:#999;padding:8px">無收款紀錄</div>';
        }

        // 付款
        html += '<h4 style="color:#c62828;margin-top:16px;margin-bottom:8px">💸 付款 (' + data.pay.length + '筆)</h4>';
        if (data.pay.length > 0) {
            var paySum = 0;
            html += '<table class="table" style="font-size:.85rem"><thead><tr><th>付款單號</th><th>付款日期</th><th>廠商</th><th>分類</th><th class="text-right">金額</th></tr></thead><tbody>';
            for (var j = 0; j < data.pay.length; j++) {
                var p = data.pay[j];
                paySum += parseInt(p.amount) || 0;
                html += '<tr><td>' + (p.payment_number || '-') + '</td><td>' + (p.payment_date || '-') + '</td><td>' + (p.vendor_name || '-') + '</td><td>' + (p.main_category || '-') + '</td><td class="text-right">$' + Number(p.amount).toLocaleString() + '</td></tr>';
            }
            html += '<tr style="font-weight:600;border-top:2px solid #ccc"><td colspan="4" class="text-right">合計</td><td class="text-right">$' + paySum.toLocaleString() + '</td></tr>';
            html += '</tbody></table>';
        } else {
            html += '<div style="color:#999;padding:8px">無付款紀錄</div>';
        }

        document.getElementById('branchDetailBody').innerHTML = html;
    });
}
function closeBranchDetail() {
    document.getElementById('branchDetailModal').style.display = 'none';
}
</script>
