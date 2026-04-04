<div class="d-flex justify-between align-center flex-wrap gap-1 mb-2">
    <h2>未繳回帳務</h2>
</div>

<?php
$totalUnremitted = 0;
$totalCount = 0;
foreach ($summary as $s) {
    $totalUnremitted += $s['unremitted_amount'];
    $totalCount += $s['unremitted_count'];
}
?>

<!-- 總計卡片 -->
<div class="card mb-2" style="background:linear-gradient(135deg,#1a73e8,#4285f4);color:#fff;padding:1.2rem">
    <div class="d-flex flex-wrap gap-2" style="justify-content:space-around;text-align:center">
        <div>
            <div style="font-size:.85rem;opacity:.8">全部未繳回</div>
            <div style="font-size:1.8rem;font-weight:700">$<?= number_format($totalUnremitted) ?></div>
        </div>
        <div>
            <div style="font-size:.85rem;opacity:.8">未繳回筆數</div>
            <div style="font-size:1.8rem;font-weight:700"><?= $totalCount ?> 筆</div>
        </div>
    </div>
</div>

<!-- 各分公司卡片 -->
<div class="d-flex flex-wrap gap-1">
    <?php foreach ($summary as $s): ?>
    <a href="/remittance.php?action=branch&id=<?= $s['branch_id'] ?>" class="card" style="flex:1;min-width:220px;padding:1rem;text-decoration:none;color:inherit;border-left:4px solid <?= $s['unremitted_amount'] > 0 ? 'var(--danger)' : 'var(--success)' ?>">
        <div style="font-weight:600;font-size:1.1rem;margin-bottom:.5rem"><?= e($s['branch_name']) ?></div>
        <div class="d-flex justify-between align-center">
            <div>
                <?php if ($s['unremitted_count'] > 0): ?>
                <span style="color:var(--danger);font-size:1.3rem;font-weight:700">$<?= number_format($s['unremitted_amount']) ?></span>
                <div style="font-size:.8rem;color:var(--gray-500)"><?= $s['unremitted_count'] ?> 筆未繳回</div>
                <?php else: ?>
                <span style="color:var(--success);font-weight:600">已全部繳回</span>
                <?php endif; ?>
            </div>
            <?php if ($s['earliest_date']): ?>
            <div style="font-size:.8rem;color:var(--gray-500);text-align:right">
                最早收款<br><?= e($s['earliest_date']) ?>
            </div>
            <?php endif; ?>
        </div>
    </a>
    <?php endforeach; ?>
</div>
