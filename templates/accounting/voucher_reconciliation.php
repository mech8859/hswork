<?php
$sourceLabels = array(
    'bank'           => '銀行帳戶明細',
    'petty_cash'     => '零用金管理',
    'reserve_fund'   => '備用金管理',
    'cash_details'   => '現金明細',
    'rf_pc_match'    => '備用金→零用金',
    'cash_pc_match'  => '現金→零用金',
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
    'merged_into_prev'        => array('已合併', '#9ca3af'),
);
// 備用金→零用金 專用狀態
$rfPcStatusLabels = array(
    'matched_precise'         => array('精準匹配', '#16a34a'),
    'matched_by_cash'         => array('精準匹配', '#16a34a'),
    'unmatched_rf'            => array('零用金未對應', '#ef4444'),
    'unmatched_pc'            => array('備用金未對應', '#ef4444'),
    'manually_confirmed'      => array('已人工核對', '#16a34a'),
);
// 現金→零用金 專用狀態（unmatched_pc = 現金未對應）
$cashPcStatusLabels = array(
    'matched_precise'         => array('精準匹配', '#16a34a'),
    'unmatched_rf'            => array('零用金未對應', '#ef4444'),
    'unmatched_pc'            => array('現金未對應', '#ef4444'),
    'manually_confirmed'      => array('已人工核對', '#16a34a'),
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
<?php
// 分頁切換（上一 / 下一）
$_sourceOrder = array_keys($sourceLabels);
$_curIdx = array_search($source, $_sourceOrder, true);
$_prevSource = $_curIdx !== false && $_curIdx > 0 ? $_sourceOrder[$_curIdx - 1] : null;
$_nextSource = $_curIdx !== false && $_curIdx < count($_sourceOrder) - 1 ? $_sourceOrder[$_curIdx + 1] : null;
$_buildTabUrl = function($s) use ($startDate, $endDate, $branchFilter, $statusFilter, $sortOrder) {
    $u = '/accounting.php?action=voucher_reconciliation&source=' . $s
        . '&start_date=' . e($startDate) . '&end_date=' . e($endDate);
    if ($branchFilter && $s !== 'bank') $u .= '&branch_id=' . (int)$branchFilter;
    if (!empty($statusFilter)) $u .= '&status_filter=' . e($statusFilter);
    if (!empty($sortOrder) && $sortOrder === 'asc') $u .= '&sort=asc';
    return $u;
};
?>
<div class="page-sticky-head">
<div class="page-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;margin-bottom:16px">
    <div>
        <h1 style="margin:0 0 4px">傳票核對報表</h1>
        <div style="font-size:.75rem;color:#888">資料時間：<?= date('H:i:s') ?>（每次開啟即時比對）</div>
    </div>
    <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
        <?php if ($_prevSource): ?>
        <a href="<?= e($_buildTabUrl($_prevSource)) ?>" class="btn btn-outline btn-sm" title="上一個：<?= e($sourceLabels[$_prevSource]) ?>">&laquo; 上一筆</a>
        <?php else: ?>
        <span class="btn btn-outline btn-sm" style="opacity:.4;cursor:not-allowed">&laquo; 上一筆</span>
        <?php endif; ?>
        <?php if ($_nextSource): ?>
        <a href="<?= e($_buildTabUrl($_nextSource)) ?>" class="btn btn-outline btn-sm" title="下一個：<?= e($sourceLabels[$_nextSource]) ?>">下一筆 &raquo;</a>
        <?php else: ?>
        <span class="btn btn-outline btn-sm" style="opacity:.4;cursor:not-allowed">下一筆 &raquo;</span>
        <?php endif; ?>
        <a href="/accounting.php?action=invoice_voucher_reconciliation&type=sales&start_date=<?= e($startDate) ?>&end_date=<?= e($endDate) ?>" class="btn btn-outline btn-sm" title="切換到發票↔傳票對帳工具">🧾 發票傳票對帳</a>
        <button type="button" onclick="location.reload()" class="btn btn-outline" title="重新抓最新傳票狀態">🔄 重新整理</button>
        <?php if ($source !== 'rf_pc_match' && (Auth::hasPermission('accounting.manage') || Auth::hasPermission('all'))): ?>
        <form method="POST" action="/accounting.php?action=voucher_reconciliation_batch" style="display:inline" onsubmit="return confirm('批次把所有「模糊匹配」的來源單綁定到對應傳票？\n\n• 只會綁定尚未綁定的傳票\n• 已綁定其他來源的傳票會被略過\n\n執行後才能精準匹配。');">
            <?= csrf_field() ?>
            <input type="hidden" name="source" value="<?= e($source) ?>">
            <input type="hidden" name="start_date" value="<?= e($startDate) ?>">
            <input type="hidden" name="end_date" value="<?= e($endDate) ?>">
            <input type="hidden" name="branch_id" value="<?= (int)$branchFilter ?>">
            <button type="submit" class="btn btn-success" title="把所有模糊匹配自動變成精準匹配">⚡ 批次自動核對</button>
        </form>
        <?php endif; ?>
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
            <input type="date" name="start_date" value="<?= e($startDate) ?>" class="form-control" min="2026-01-01" max="2099-12-31" title="2026/01/01 之前資料不核對">
        </div>
        <div>
            <label style="display:block;font-size:.85rem;color:#666;margin-bottom:2px">結束日期</label>
            <input type="date" name="end_date" value="<?= e($endDate) ?>" class="form-control" min="2026-01-01" max="2099-12-31">
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
            <select name="status_filter" class="form-control" style="min-width:160px">
                <option value="">全部（隱藏精準）</option>
                <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>全部（含精準匹配）</option>
                <?php if ($source === 'rf_pc_match' || $source === 'cash_pc_match'): ?>
                <option value="unmatched_all" <?= $statusFilter === 'unmatched_all' ? 'selected' : '' ?>>全部未對應</option>
                <?php endif; ?>
                <?php
                if ($source === 'cash_pc_match') $_filterLabels = $cashPcStatusLabels;
                elseif ($source === 'rf_pc_match') $_filterLabels = $rfPcStatusLabels;
                else $_filterLabels = $statusLabels;
                // 精準匹配子類型加註說明，避免 dropdown 出現兩個一樣的「精準匹配」
                $_optExtra = array(
                    'matched_precise' => '精準匹配（自動配對）',
                    'matched_by_cash' => '精準匹配（現金已核對）',
                );
                foreach ($_filterLabels as $sk => $sv):
                    if ($sk === 'merged_into_prev') continue;
                    $_optLabel = isset($_optExtra[$sk]) ? $_optExtra[$sk] : $sv[0];
                ?>
                <option value="<?= e($sk) ?>" <?= $statusFilter === $sk ? 'selected' : '' ?>><?= e($_optLabel) ?></option>
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
    <?php
    if ($source === 'cash_pc_match') $_statCardLabels = $cashPcStatusLabels;
    elseif ($source === 'rf_pc_match') $_statCardLabels = $rfPcStatusLabels;
    else $_statCardLabels = $statusLabels;
    // 兩個「精準匹配」加註區分（自動配對 / 現金已核對）
    $_cardLabelExtra = array(
        'matched_precise' => array('精準匹配', '自動配對'),
        'matched_by_cash' => array('精準匹配', '現金已核對'),
    );
    foreach ($_statCardLabels as $sk => $sv):
        if ($sk === 'merged_into_prev') continue;
        $_mainLabel = isset($_cardLabelExtra[$sk]) ? $_cardLabelExtra[$sk][0] : $sv[0];
        $_subLabel  = isset($_cardLabelExtra[$sk]) ? $_cardLabelExtra[$sk][1] : '';
    ?>
    <a href="/accounting.php?action=voucher_reconciliation&source=<?= e($source) ?>&start_date=<?= e($startDate) ?>&end_date=<?= e($endDate) ?><?= $branchFilter && $source !== 'bank' ? '&branch_id=' . $branchFilter : '' ?>&status_filter=<?= e($sk) ?>" class="card" style="padding:12px;text-align:center;text-decoration:none;color:inherit;border-left:3px solid <?= $sv[1] ?>">
        <div style="font-size:.8rem;color:<?= $sv[1] ?>;font-weight:600">
            <?= e($_mainLabel) ?>
            <?php if ($_subLabel): ?><span style="font-size:.7rem;font-weight:400;opacity:.85">（<?= e($_subLabel) ?>）</span><?php endif; ?>
        </div>
        <div style="font-size:1.3rem;font-weight:700;color:<?= $sv[1] ?>"><?= number_format(isset($stats[$sk]) ? $stats[$sk] : 0) ?></div>
    </a>
    <?php endforeach; ?>
</div>
</div><!-- /.page-sticky-head -->

<!-- 結果表格 -->
<div class="card" style="padding:0">
    <?php if (empty($records)): ?>
    <div style="text-align:center;padding:40px;color:#999">此範圍無資料</div>
    <?php elseif ($source === 'rf_pc_match' || $source === 'cash_pc_match'): ?>
    <?php
    $_isCash = $source === 'cash_pc_match';
    $_leftLabel = $_isCash ? '現金支出' : '備用金支出';
    $_leftEditUrl = $_isCash ? '/cash_details.php?action=edit&id=' : '/reserve_fund.php?action=edit&id=';
    $_confirmAction = $_isCash ? 'cash_pc_match_confirm' : 'rf_pc_match_confirm';
    $_leftIdField = $_isCash ? 'cash_id' : 'rf_id';
    $_rfPcReturn = '/accounting.php?action=voucher_reconciliation&source=' . $source
        . '&start_date=' . urlencode($startDate) . '&end_date=' . urlencode($endDate);
    if ($branchFilter) $_rfPcReturn .= '&branch_id=' . (int)$branchFilter;
    if (!empty($statusFilter)) $_rfPcReturn .= '&status_filter=' . urlencode($statusFilter);
    if (!empty($sortOrder) && $sortOrder === 'asc') $_rfPcReturn .= '&sort=asc';
    ?>
    <div class="table-responsive" style="--table-sticky-top:240px">
    <table style="width:100%;border-collapse:collapse;font-size:.88rem">
        <thead class="sticky-thead">
            <tr style="background:#f5f5f5">
                <th style="padding:10px 8px;text-align:left;width:80px">分公司</th>
                <th colspan="4" style="padding:10px 8px;text-align:center;background:#fef3c7;border-right:2px solid #fff"><?= e($_leftLabel) ?></th>
                <th colspan="4" style="padding:10px 8px;text-align:center;background:#dcfce7">零用金收入</th>
                <th style="padding:10px 8px;text-align:center;width:110px">狀態</th>
                <th style="padding:10px 8px;text-align:center;width:100px">動作</th>
            </tr>
            <tr style="background:#f5f5f5">
                <th></th>
                <th style="padding:8px;text-align:left;width:90px;background:#fef3c7">日期</th>
                <th style="padding:8px;text-align:left;width:120px;background:#fef3c7">單號</th>
                <th style="padding:8px;text-align:right;width:80px;background:#fef3c7">金額</th>
                <th style="padding:8px;text-align:left;background:#fef3c7;border-right:2px solid #fff">用途說明</th>
                <th style="padding:8px;text-align:left;width:90px;background:#dcfce7">日期</th>
                <th style="padding:8px;text-align:left;width:120px;background:#dcfce7">單號</th>
                <th style="padding:8px;text-align:right;width:80px;background:#dcfce7">金額</th>
                <th style="padding:8px;text-align:left;background:#dcfce7">用途說明</th>
                <th></th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php
            $_rowLabels = ($source === 'cash_pc_match') ? $cashPcStatusLabels : $rfPcStatusLabels;
            foreach ($records as $r):
                $st = isset($_rowLabels[$r['match_status']]) ? $_rowLabels[$r['match_status']] : array($r['match_status'], '#666');
                // 精準匹配（自動配對成功） + 已人工核對 → 視為已核對
                $isConfirmed = !empty($r['is_confirmed']) || $r['match_status'] === 'matched_precise';
                if ($isConfirmed) {
                    $bg = '#f0fdf4'; // 綠
                } else {
                    $bg = '#fef2f2'; // 紅
                }
                $branch = $r['rf_branch_name'] ?: $r['pc_branch_name'] ?: '-';
                $hasPair = $r['rf_id'] && $r['pc_id'];
            ?>
            <tr id="rfpc-<?= (int)($r['rf_id'] ?: 0) ?>-<?= (int)($r['pc_id'] ?: 0) ?>" style="border-top:1px solid #eee;background:<?= $bg ?>;scroll-margin-top:260px">
                <td style="padding:8px;font-size:.82rem;color:#555"><?= e($branch) ?></td>
                <!-- 備用金 -->
                <td style="padding:8px;background:rgba(254,243,199,.3)"><?= $r['date'] !== null && $r['rf_id'] ? e($r['date']) : '<span style="color:#999">—</span>' ?></td>
                <td style="padding:8px;font-family:monospace;font-size:.82rem;background:rgba(254,243,199,.3)">
                    <?php if ($r['rf_id']): ?>
                    <a href="<?= e($_leftEditUrl) ?><?= (int)$r['rf_id'] ?>" style="color:#1565c0;text-decoration:none"><?= e($r['rf_number'] ?: '(無單號)') ?></a>
                    <?php else: ?>
                    <span style="color:#999">—</span>
                    <?php endif; ?>
                </td>
                <td style="padding:8px;text-align:right;color:#d32f2f;background:rgba(254,243,199,.3)">
                    <?= $r['rf_amount'] !== null ? number_format($r['rf_amount']) : '<span style="color:#999">—</span>' ?>
                </td>
                <td style="padding:8px;font-size:.82rem;border-right:2px solid #eee;background:rgba(254,243,199,.3)" title="<?= e($r['rf_description']) ?>">
                    <?= $r['rf_description'] !== null ? e(mb_substr($r['rf_description'], 0, 30)) : '<span style="color:#999">—</span>' ?>
                </td>
                <!-- 零用金 -->
                <td style="padding:8px;background:rgba(220,252,231,.3)"><?= $r['pc_date'] ? e($r['pc_date']) : '<span style="color:#999">—</span>' ?></td>
                <td style="padding:8px;font-family:monospace;font-size:.82rem;background:rgba(220,252,231,.3)">
                    <?php if ($r['pc_id']): ?>
                    <a href="/petty_cash.php?action=edit&id=<?= (int)$r['pc_id'] ?>" style="color:#1565c0;text-decoration:none"><?= e($r['pc_number'] ?: '(無單號)') ?></a>
                    <?php else: ?>
                    <span style="color:#999">—</span>
                    <?php endif; ?>
                </td>
                <td style="padding:8px;text-align:right;color:#16a34a;background:rgba(220,252,231,.3)">
                    <?= $r['pc_amount'] !== null ? number_format($r['pc_amount']) : '<span style="color:#999">—</span>' ?>
                </td>
                <td style="padding:8px;font-size:.82rem;background:rgba(220,252,231,.3)" title="<?= e($r['pc_description']) ?>">
                    <?= $r['pc_description'] !== null ? e(mb_substr($r['pc_description'], 0, 30)) : '<span style="color:#999">—</span>' ?>
                </td>
                <td style="padding:8px;text-align:center;white-space:nowrap">
                    <span style="display:inline-block;padding:2px 8px;border-radius:4px;background:<?= $st[1] ?>;color:#fff;font-size:.72rem;font-weight:600"><?= e($st[0]) ?></span>
                    <?php if ($isConfirmed): ?>
                    <div style="color:#16a34a;font-size:.7rem;margin-top:2px;font-weight:600">
                        <?php
                        if ($r['match_status'] === 'matched_by_cash') {
                            echo '✓ 現金已核對';
                        } elseif (!empty($r['is_confirmed'])) {
                            echo '✓ 已人工核對';
                        } else {
                            // matched_precise 但無真實人工確認 → 自動配對
                            echo '（自動配對）';
                        }
                        ?>
                    </div>
                    <?php endif; ?>
                </td>
                <td style="padding:8px;text-align:center">
                    <?php
                    $rfIdVal = (int)($r['rf_id'] ?: 0);
                    $pcIdVal = (int)($r['pc_id'] ?: 0);
                    $hasAny = $rfIdVal > 0 || $pcIdVal > 0;
                    $confirmMsg = $hasPair
                        ? '確認此備用金支出與零用金收入配對無誤？\n確認後此筆會鎖定為精準匹配。'
                        : '確認此筆人工核對無誤？\n（單邊未對應也標記為已核對，下次預設視圖會隱藏）';
                    ?>
                    <?php if ($r['match_status'] === 'matched_by_cash'): ?>
                    <span style="color:#16a34a;font-size:.72rem">已被現金核對</span>
                    <?php elseif ($hasAny && ($canManage ?? false)): ?>
                        <?php if ($isConfirmed): ?>
                        <form method="POST" action="/accounting.php?action=<?= e($_confirmAction) ?>" style="display:inline" onsubmit="return confirm('取消此筆人工核對？');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="act" value="unconfirm">
                            <input type="hidden" name="<?= e($_leftIdField) ?>" value="<?= $rfIdVal ?>">
                            <input type="hidden" name="pc_id" value="<?= $pcIdVal ?>">
                            <input type="hidden" name="return_to" value="<?= e($_rfPcReturn) ?>#rfpc-<?= $rfIdVal ?>-<?= $pcIdVal ?>">
                            <button type="submit" class="btn btn-sm" style="padding:3px 10px;font-size:.72rem;background:#16a34a;color:#fff" title="已核對，點擊取消">✓ 標示已核對</button>
                        </form>
                        <?php else: ?>
                        <form method="POST" action="/accounting.php?action=<?= e($_confirmAction) ?>" style="display:inline" onsubmit="return confirm('<?= $confirmMsg ?>');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="act" value="confirm">
                            <input type="hidden" name="<?= e($_leftIdField) ?>" value="<?= $rfIdVal ?>">
                            <input type="hidden" name="pc_id" value="<?= $pcIdVal ?>">
                            <input type="hidden" name="return_to" value="<?= e($_rfPcReturn) ?>#rfpc-<?= $rfIdVal ?>-<?= $pcIdVal ?>">
                            <button type="submit" class="btn btn-sm" style="padding:3px 10px;font-size:.72rem;background:#ef4444;color:#fff" title="標示為待核對，點擊後變為已核對">標示待核對</button>
                        </form>
                        <?php endif; ?>
                    <?php else: ?>
                    <span style="color:#bbb">—</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
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
            <?php
            // 追蹤上一筆主列是否已成功比對；已合併的手續費列在主列匹配成功時隱藏
            $_prevMainMatched = false;
            foreach ($records as $r):
                $st = isset($statusLabels[$r['match_status']]) ? $statusLabels[$r['match_status']] : array($r['match_status'], '#666');
                // 已合併手續費：若上一筆主列已比對成功 → 跳過不顯示
                if ($r['match_status'] === 'merged_into_prev' && $_prevMainMatched) {
                    continue;
                }
                // 更新主列狀態（非 merged 都算主列）
                if ($r['match_status'] !== 'merged_into_prev') {
                    $_prevMainMatched = in_array($r['match_status'], array('matched_precise','matched_fuzzy'), true);
                }
                $bgColor = $r['match_status'] === 'unmatched' ? '#fef2f2'
                         : ($r['match_status'] === 'matched_amount_mismatch' ? '#fffbeb'
                         : ($r['match_status'] === 'merged_into_prev' ? '#f5f5f5' : '#fff'));
                // 每筆的返回 URL 附加錨點，讓從傳票檢視返回時自動捲到原位
                $_rowAnchor = '#src-' . $source . '-' . (int)$r['source_id'];
                $_rowReturnUrl = $_reconReturnUrl . $_rowAnchor;
                $_rowReturnEncoded = urlencode($_rowReturnUrl);
            ?>
            <tr id="src-<?= e($source) ?>-<?= (int)$r['source_id'] ?>" style="border-top:1px solid #eee;background:<?= $bgColor ?>;scroll-margin-top:260px">
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
                    <a href="/accounting.php?action=journal_view&id=<?= (int)$r['voucher_id'] ?>&return_to=<?= $_rowReturnEncoded ?>" style="color:#1565c0;text-decoration:none;font-family:monospace"><?= e($r['voucher_number']) ?></a>
                    <?php if ($r['match_status'] === 'matched_amount_mismatch' && $r['voucher_amount'] !== null): ?>
                    <div style="color:#f59e0b;font-size:.75rem">傳票: <?= number_format($r['voucher_amount']) ?></div>
                    <?php endif; ?>
                    <?php if (in_array($r['match_status'], array('matched_fuzzy', 'matched_amount_mismatch')) && $canManage): ?>
                    <form method="POST" action="/accounting.php?action=confirm_voucher_match" style="display:inline;margin-top:4px" onsubmit="return confirm('確認此傳票與此來源對應無誤？確認後下次開啟會直接精準匹配。');">
                        <?= csrf_field() ?>
                        <input type="hidden" name="source_module" value="<?= e($source) ?>">
                        <input type="hidden" name="source_id" value="<?= (int)$r['source_id'] ?>">
                        <input type="hidden" name="voucher_id" value="<?= (int)$r['voucher_id'] ?>">
                        <input type="hidden" name="return_to" value="<?= e($_rowReturnUrl) ?>">
                        <button type="submit" class="btn btn-sm" style="margin-top:3px;padding:2px 8px;font-size:.72rem;background:#16a34a;color:#fff">✓ 確認匹配</button>
                    </form>
                    <?php endif; ?>
                    <?php elseif ($r['match_status'] !== 'merged_into_prev'): ?>
                    <a href="/accounting.php?action=journal_create&return_to=<?= $_rowReturnEncoded ?>" style="color:#999;text-decoration:none">+ 建立</a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<script>
// 核對後返回：依 URL 的 focus_src 或 hash 捲到原列；若該列已被過濾掉則回到送出前的捲動位置
(function() {
    var STORAGE_KEY = 'vrScrollY';

    // 送出確認匹配表單前，先記住目前 scrollY
    document.querySelectorAll('form[action*="confirm_voucher_match"]').forEach(function(f) {
        f.addEventListener('submit', function() {
            try { sessionStorage.setItem(STORAGE_KEY, String(window.scrollY)); } catch (e) {}
        });
    });

    // 頁面載入：優先捲到該筆；找不到就恢復送出前的位置
    var qs = new URLSearchParams(window.location.search);
    var focus = qs.get('focus_src');
    var target = null;
    if (focus) {
        target = document.getElementById('src-' + focus);
    } else if (window.location.hash && window.location.hash.indexOf('#src-') === 0) {
        target = document.querySelector(window.location.hash);
    }
    function restorePrevScroll() {
        try {
            var y = sessionStorage.getItem(STORAGE_KEY);
            if (y !== null) {
                window.scrollTo({ top: parseInt(y, 10), behavior: 'auto' });
                sessionStorage.removeItem(STORAGE_KEY);
            }
        } catch (e) {}
    }
    setTimeout(function() {
        if (target) {
            target.scrollIntoView({ block: 'center', behavior: 'auto' });
            target.style.boxShadow = '0 0 0 3px rgba(22,163,74,.45)';
            setTimeout(function() { target.style.boxShadow = ''; }, 1500);
            try { sessionStorage.removeItem(STORAGE_KEY); } catch (e) {}
        } else if (focus) {
            // 有 focus_src 但該列已被過濾隱藏 → 恢復到送出前位置
            restorePrevScroll();
        }
    }, 50);
})();
</script>
