<?php
$sourceLabels = array(
    'bank'         => '銀行帳戶明細',
    'petty_cash'   => '零用金管理',
    'reserve_fund' => '備用金管理',
    'cash_details' => '現金明細',
);
$sourceViewUrls = array(
    'bank'         => '/bank_transactions.php?action=edit&id=',
    'petty_cash'   => '/petty_cash.php?action=edit&id=',
    'reserve_fund' => '/reserve_fund.php?action=edit&id=',
    'cash_details' => '/cash_details.php?action=edit&id=',
);
$statusLabels = array(
    'matched_precise'         => array('精準匹配', '#16a34a'),
    'matched_fuzzy'           => array('模糊匹配', '#3b82f6'),
    'matched_amount_mismatch' => array('金額不符', '#f59e0b'),
    'unmatched'               => array('未建傳票', '#ef4444'),
);
?>
<?php
// 當前網址（含查詢參數），供編輯傳票後帶回使用
$_reconReturnUrl = '/accounting.php?action=voucher_reconciliation&source=' . e($source)
    . '&start_date=' . e($startDate) . '&end_date=' . e($endDate);
if ($branchFilter && $source !== 'bank') $_reconReturnUrl .= '&branch_id=' . (int)$branchFilter;
if (!empty($statusFilter)) $_reconReturnUrl .= '&status_filter=' . e($statusFilter);
if (!empty($sortOrder) && $sortOrder === 'asc') $_reconReturnUrl .= '&sort=asc';
$_reconReturnEncoded = urlencode($_reconReturnUrl);
?>
<div class="page-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;margin-bottom:16px">
    <div>
        <h1 style="margin:0 0 4px">傳票核對報表</h1>
        <div style="font-size:.75rem;color:#888">資料時間：<?= date('H:i:s') ?>（每次開啟即時比對）</div>
    </div>
    <div style="display:flex;gap:8px">
        <button type="button" onclick="location.reload()" class="btn btn-outline" title="重新抓最新傳票狀態">🔄 重新整理</button>
        <a href="/accounting.php?action=journals" class="btn btn-secondary">傳票管理</a>
    </div>
</div>

<!-- 源頭切換 tabs -->
<div style="display:flex;gap:4px;margin-bottom:12px;border-bottom:2px solid #e5e7eb;flex-wrap:wrap">
    <?php foreach ($sourceLabels as $sKey => $sLabel):
        $active = ($source === $sKey);
        $href = '/accounting.php?action=voucher_reconciliation&source=' . $sKey . '&start_date=' . e($startDate) . '&end_date=' . e($endDate);
        if ($branchFilter && $sKey !== 'bank') $href .= '&branch_id=' . $branchFilter;
    ?>
    <a href="<?= $href ?>" style="padding:8px 16px;border-radius:6px 6px 0 0;text-decoration:none;color:<?= $active ? '#fff' : '#666' ?>;background:<?= $active ? '#1565c0' : 'transparent' ?>;font-weight:<?= $active ? '600' : '500' ?>"><?= e($sLabel) ?></a>
    <?php endforeach; ?>
</div>

<!-- 篩選 -->
<div class="card" style="padding:12px 16px;margin-bottom:16px">
    <form method="GET" action="/accounting.php" style="display:flex;gap:10px;flex-wrap:wrap;align-items:end">
        <input type="hidden" name="action" value="voucher_reconciliation">
        <input type="hidden" name="source" value="<?= e($source) ?>">
        <div>
            <label style="display:block;font-size:.85rem;color:#666;margin-bottom:2px">起始日期</label>
            <input type="date" name="start_date" value="<?= e($startDate) ?>" class="form-control" max="2099-12-31">
        </div>
        <div>
            <label style="display:block;font-size:.85rem;color:#666;margin-bottom:2px">結束日期</label>
            <input type="date" name="end_date" value="<?= e($endDate) ?>" class="form-control" max="2099-12-31">
        </div>
        <?php if ($source !== 'bank'): ?>
        <div>
            <label style="display:block;font-size:.85rem;color:#666;margin-bottom:2px">分公司</label>
            <select name="branch_id" class="form-control" style="min-width:130px">
                <option value="0">全部分公司</option>
                <?php foreach ($branches as $br): ?>
                <option value="<?= (int)$br['id'] ?>" <?= $branchFilter == $br['id'] ? 'selected' : '' ?>><?= e($br['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
        <div>
            <label style="display:block;font-size:.85rem;color:#666;margin-bottom:2px">狀態</label>
            <select name="status_filter" class="form-control" style="min-width:140px">
                <option value="">全部</option>
                <?php foreach ($statusLabels as $sk => $sv): ?>
                <option value="<?= e($sk) ?>" <?= $statusFilter === $sk ? 'selected' : '' ?>><?= e($sv[0]) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label style="display:block;font-size:.85rem;color:#666;margin-bottom:2px">排序</label>
            <?php $sortOrder = isset($sortOrder) ? $sortOrder : 'desc'; ?>
            <select name="sort" class="form-control" style="min-width:110px">
                <option value="desc" <?= $sortOrder === 'desc' ? 'selected' : '' ?>>新 → 舊</option>
                <option value="asc"  <?= $sortOrder === 'asc'  ? 'selected' : '' ?>>舊 → 新</option>
            </select>
        </div>
        <button type="submit" class="btn btn-primary">查詢</button>
        <a href="/accounting.php?action=voucher_reconciliation&source=<?= e($source) ?>" class="btn btn-outline">清除</a>
    </form>
</div>

<!-- 統計卡片 -->
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:10px;margin-bottom:16px">
    <div class="card" style="padding:12px;text-align:center">
        <div style="font-size:.8rem;color:#666">總筆數</div>
        <div style="font-size:1.3rem;font-weight:700"><?= number_format($stats['total']) ?></div>
    </div>
    <?php foreach ($statusLabels as $sk => $sv): ?>
    <a href="/accounting.php?action=voucher_reconciliation&source=<?= e($source) ?>&start_date=<?= e($startDate) ?>&end_date=<?= e($endDate) ?><?= $branchFilter && $source !== 'bank' ? '&branch_id=' . $branchFilter : '' ?>&status_filter=<?= e($sk) ?>" class="card" style="padding:12px;text-align:center;text-decoration:none;color:inherit;border-left:3px solid <?= $sv[1] ?>">
        <div style="font-size:.8rem;color:<?= $sv[1] ?>;font-weight:600"><?= e($sv[0]) ?></div>
        <div style="font-size:1.3rem;font-weight:700;color:<?= $sv[1] ?>"><?= number_format($stats[$sk]) ?></div>
    </a>
    <?php endforeach; ?>
</div>

<!-- 結果表格 -->
<div class="card" style="padding:0">
    <?php if (empty($records)): ?>
    <div style="text-align:center;padding:40px;color:#999">此範圍無資料</div>
    <?php else: ?>
    <table style="width:100%;border-collapse:collapse;font-size:.88rem">
        <thead>
            <tr style="background:#f5f5f5">
                <th style="padding:10px 8px;text-align:left;width:90px">日期</th>
                <th style="padding:10px 8px;text-align:left;width:120px">單號</th>
                <?php if ($source === 'bank'): ?>
                <th style="padding:10px 8px;text-align:left;width:140px">銀行帳戶</th>
                <?php endif; ?>
                <th style="padding:10px 8px;text-align:left">摘要</th>
                <th style="padding:10px 8px;text-align:right;width:100px">支出</th>
                <th style="padding:10px 8px;text-align:right;width:100px">收入</th>
                <th style="padding:10px 8px;text-align:center;width:110px">狀態</th>
                <th style="padding:10px 8px;text-align:left;width:180px">對應傳票</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($records as $r):
                $st = isset($statusLabels[$r['match_status']]) ? $statusLabels[$r['match_status']] : array($r['match_status'], '#666');
                $bgColor = $r['match_status'] === 'unmatched' ? '#fef2f2'
                         : ($r['match_status'] === 'matched_amount_mismatch' ? '#fffbeb' : '#fff');
            ?>
            <tr style="border-top:1px solid #eee;background:<?= $bgColor ?>">
                <td style="padding:8px"><?= e($r['date']) ?></td>
                <td style="padding:8px;font-family:monospace;font-size:.82rem">
                    <a href="<?= e($sourceViewUrls[$source]) . (int)$r['source_id'] ?>" style="color:#1565c0;text-decoration:none" title="查看原單"><?= e($r['number']) ?: '(無單號)' ?></a>
                </td>
                <?php if ($source === 'bank'): ?>
                <td style="padding:8px;font-size:.82rem;color:#555"><?= e($r['extra']) ?></td>
                <?php endif; ?>
                <td style="padding:8px;max-width:300px;overflow:hidden;text-overflow:ellipsis" title="<?= e($r['description']) ?>"><?= e(mb_substr($r['description'], 0, 40)) ?><?= mb_strlen($r['description']) > 40 ? '...' : '' ?></td>
                <td style="padding:8px;text-align:right;color:#d32f2f"><?= $r['debit'] > 0 ? number_format($r['debit']) : '' ?></td>
                <td style="padding:8px;text-align:right;color:#16a34a"><?= $r['credit'] > 0 ? number_format($r['credit']) : '' ?></td>
                <td style="padding:8px;text-align:center">
                    <span style="display:inline-block;padding:2px 8px;border-radius:4px;background:<?= $st[1] ?>;color:#fff;font-size:.72rem;font-weight:600"><?= e($st[0]) ?></span>
                </td>
                <td style="padding:8px;font-size:.82rem">
                    <?php if ($r['voucher_id']): ?>
                    <a href="/accounting.php?action=journal_view&id=<?= (int)$r['voucher_id'] ?>&return_to=<?= $_reconReturnEncoded ?>" style="color:#1565c0;text-decoration:none;font-family:monospace"><?= e($r['voucher_number']) ?></a>
                    <?php if ($r['match_status'] === 'matched_amount_mismatch' && $r['voucher_amount'] !== null): ?>
                    <div style="color:#f59e0b;font-size:.75rem">傳票: <?= number_format($r['voucher_amount']) ?></div>
                    <?php endif; ?>
                    <?php else: ?>
                    <a href="/accounting.php?action=journal_create&return_to=<?= $_reconReturnEncoded ?>" style="color:#999;text-decoration:none">+ 建立</a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>
