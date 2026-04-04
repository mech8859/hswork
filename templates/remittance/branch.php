<?php
$totalUnremitted = 0;
foreach ($unremitted as $u) {
    $totalUnremitted += $u['amount'];
}
?>

<div class="d-flex justify-between align-center flex-wrap gap-1 mb-2">
    <h2><?= e($branchName) ?> — 未繳回帳務</h2>
    <a href="/remittance.php" class="btn btn-outline btn-sm">返回總覽</a>
</div>

<!-- 未繳回金額 -->
<div class="card mb-2" style="text-align:center;padding:1.2rem">
    <div class="text-muted" style="font-size:.85rem">未繳回合計</div>
    <div style="font-size:2rem;font-weight:700;color:<?= $totalUnremitted > 0 ? 'var(--danger)' : 'var(--success)' ?>">
        $<?= number_format($totalUnremitted) ?>
    </div>
    <div class="text-muted"><?= count($unremitted) ?> 筆</div>
</div>

<!-- 未繳回明細 -->
<div class="card mb-2">
    <div class="d-flex justify-between align-center mb-1">
        <h3>未繳回明細</h3>
    </div>

    <?php if (empty($unremitted)): ?>
    <p class="text-center text-muted" style="padding:1rem">無未繳回紀錄</p>
    <?php else: ?>

    <?php if ($canManage): ?>
    <form method="POST" action="/remittance.php?action=remit" id="remitForm">
        <?= csrf_field() ?>
        <input type="hidden" name="branch_id" value="<?= $branchId ?>">

        <div class="d-flex gap-1 mb-1 align-center flex-wrap">
            <label style="font-size:.85rem">繳回日期：</label>
            <input type="date" name="remit_date" class="form-control" value="<?= date('Y-m-d') ?>" style="width:auto">
            <label style="font-size:.85rem">備註：</label>
            <input type="text" name="remit_note" class="form-control" placeholder="選填" style="width:200px">
            <button type="submit" class="btn btn-primary btn-sm" onclick="return confirmRemit()">確認繳回勾選項目</button>
        </div>
    <?php endif; ?>

    <div class="table-responsive">
        <table class="table" style="font-size:.9rem">
            <thead>
                <tr>
                    <?php if ($canManage): ?>
                    <th style="width:40px"><input type="checkbox" id="checkAll" onchange="toggleAll(this)"></th>
                    <?php endif; ?>
                    <th>案件</th>
                    <th>客戶</th>
                    <th>收款日期</th>
                    <th style="text-align:right">金額</th>
                    <th>收款方式</th>
                    <th>備註</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($unremitted as $u): ?>
                <tr>
                    <?php if ($canManage): ?>
                    <td><input type="checkbox" name="payment_ids[]" value="<?= $u['id'] ?>" class="pay-check"></td>
                    <?php endif; ?>
                    <td>
                        <?php if (!empty($u['case_number'])): ?>
                        <a href="/cases.php?action=view&id=<?= $u['case_id'] ?>"><?= e($u['case_number']) ?></a>
                        <?php endif; ?>
                        <div style="font-size:.8rem;color:var(--gray-500)"><?= e($u['case_title']) ?></div>
                    </td>
                    <td><?= e($u['customer_name']) ?></td>
                    <td><?= e($u['payment_date']) ?></td>
                    <td style="text-align:right;font-weight:600">$<?= number_format($u['amount']) ?></td>
                    <td>
                        <?php
                        $typeMap = array('deposit' => '訂金', 'final_payment' => '尾款', 'full_payment' => '全款', 'other' => '其他');
                        $methodMap = array('cash' => '現金', 'transfer' => '匯款', 'check' => '支票');
                        ?>
                        <?= isset($typeMap[$u['payment_type']]) ? $typeMap[$u['payment_type']] : e($u['payment_type']) ?>
                        / <?= isset($methodMap[$u['transaction_type']]) ? $methodMap[$u['transaction_type']] : e($u['transaction_type']) ?>
                    </td>
                    <td style="max-width:150px;white-space:normal;font-size:.8rem"><?= e($u['note']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr style="font-weight:700;background:var(--gray-50)">
                    <?php if ($canManage): ?><td></td><?php endif; ?>
                    <td colspan="3">合計</td>
                    <td style="text-align:right;color:var(--danger)">$<?= number_format($totalUnremitted) ?></td>
                    <td colspan="2"></td>
                </tr>
            </tfoot>
        </table>
    </div>

    <?php if ($canManage): ?>
    </form>
    <?php endif; ?>

    <?php endif; ?>
</div>

<!-- 已繳回紀錄 -->
<div class="card">
    <h3 class="mb-1">已繳回紀錄</h3>
    <?php if (empty($remitted)): ?>
    <p class="text-center text-muted" style="padding:1rem">尚無繳回紀錄</p>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table" style="font-size:.85rem">
            <thead>
                <tr>
                    <th>案件</th>
                    <th>客戶</th>
                    <th>收款日期</th>
                    <th style="text-align:right">金額</th>
                    <th>繳回日期</th>
                    <th>繳回備註</th>
                    <?php if ($canManage): ?><th>操作</th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($remitted as $r): ?>
                <tr style="opacity:.7">
                    <td>
                        <?php if (!empty($r['case_number'])): ?>
                        <a href="/cases.php?action=view&id=<?= $r['case_id'] ?>"><?= e($r['case_number']) ?></a>
                        <?php endif; ?>
                        <div style="font-size:.75rem;color:var(--gray-400)"><?= e($r['case_title']) ?></div>
                    </td>
                    <td><?= e($r['customer_name']) ?></td>
                    <td><?= e($r['payment_date']) ?></td>
                    <td style="text-align:right">$<?= number_format($r['amount']) ?></td>
                    <td><?= e($r['remit_date']) ?></td>
                    <td><?= e($r['remit_note']) ?></td>
                    <?php if ($canManage): ?>
                    <td>
                        <a href="/remittance.php?action=cancel&payment_id=<?= $r['id'] ?>&branch_id=<?= $branchId ?>&csrf_token=<?= e(Session::getCsrfToken()) ?>"
                           class="btn btn-outline btn-sm" style="font-size:.75rem" onclick="return confirm('確定取消繳回？')">取消繳回</a>
                    </td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<script>
function toggleAll(master) {
    var checks = document.querySelectorAll('.pay-check');
    for (var i = 0; i < checks.length; i++) {
        checks[i].checked = master.checked;
    }
}
function confirmRemit() {
    var checks = document.querySelectorAll('.pay-check:checked');
    if (checks.length === 0) {
        alert('請先勾選要繳回的項目');
        return false;
    }
    var total = 0;
    checks.forEach(function(c) {
        var row = c.closest('tr');
        var amtCell = row.querySelectorAll('td')[4];
        var amt = parseInt(amtCell.textContent.replace(/[^0-9]/g, '')) || 0;
        total += amt;
    });
    return confirm('確認繳回 ' + checks.length + ' 筆，共 $' + total.toLocaleString() + '？');
}
</script>
