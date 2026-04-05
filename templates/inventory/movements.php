<div class="d-flex justify-between align-center flex-wrap gap-1 mb-2">
    <h2>庫存異動表</h2>
    <?= back_button('/inventory.php') ?>
</div>

<!-- Tab 切換 -->
<?php
$activeTab = $tab ?? 'detail';
$qs = http_build_query(array_filter(array(
    'action' => 'movements',
    'warehouse_id' => $filters['warehouse_id'] ?? '',
    'type' => $filters['type'] ?? '',
    'keyword' => $filters['keyword'] ?? '',
    'date_from' => $filters['date_from'] ?? '',
    'date_to' => $filters['date_to'] ?? '',
)));
?>
<div class="d-flex gap-1 mb-2">
    <a href="/inventory.php?<?= $qs ?>&tab=detail" class="btn btn-sm <?= $activeTab === 'detail' ? 'btn-primary' : 'btn-outline' ?>">異動明細</a>
    <a href="/inventory.php?<?= $qs ?>&tab=summary" class="btn btn-sm <?= $activeTab === 'summary' ? 'btn-primary' : 'btn-outline' ?>">期間匯總</a>
</div>

<!-- 篩選 -->
<div class="card mb-2">
    <form method="GET" action="/inventory.php">
        <input type="hidden" name="action" value="movements">
        <input type="hidden" name="tab" value="<?= e($activeTab) ?>">
        <div class="form-row" style="align-items:flex-end">
            <div class="form-group">
                <label>倉庫</label>
                <select name="warehouse_id" class="form-control">
                    <option value="">全部</option>
                    <?php foreach ($warehouses as $w): ?>
                    <option value="<?= e($w['id']) ?>" <?= (!empty($filters['warehouse_id']) && $filters['warehouse_id'] == $w['id']) ? 'selected' : '' ?>><?= e($w['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if ($activeTab === 'detail'): ?>
            <div class="form-group">
                <label>異動類型</label>
                <select name="type" class="form-control">
                    <option value="">全部</option>
                    <?php foreach ($typeOptions as $val => $label): ?>
                    <option value="<?= e($val) ?>" <?= (!empty($filters['type']) && $filters['type'] === $val) ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <div class="form-group">
                <label>關鍵字</label>
                <input type="text" name="keyword" class="form-control" value="<?= e($filters['keyword'] ?? '') ?>" placeholder="商品名稱/型號">
            </div>
            <div class="form-group">
                <label>起始日期</label>
                <input type="date" name="date_from" class="form-control" value="<?= e($filters['date_from'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>結束日期</label>
                <input type="date" name="date_to" class="form-control" value="<?= e($filters['date_to'] ?? '') ?>">
            </div>
            <div class="form-group" style="align-self:flex-end">
                <button type="submit" class="btn btn-primary btn-sm">搜尋</button>
                <a href="/inventory.php?action=movements&tab=<?= e($activeTab) ?>" class="btn btn-outline btn-sm">清除</a>
            </div>
        </div>
    </form>
</div>

<?php if ($activeTab === 'detail'): ?>
<!-- ============ 異動明細 ============ -->
<div class="card">
    <div class="d-flex justify-between align-center mb-1">
        <span class="text-muted"><?= count($transactions) ?> 筆</span>
    </div>
    <?php if (empty($transactions)): ?>
        <p class="text-muted text-center mt-2">目前無異動記錄</p>
    <?php else: ?>
    <!-- 手機卡片 -->
    <div class="staff-cards show-mobile">
        <?php foreach ($transactions as $t):
            $qty = (int)($t['quantity'] ?? 0);
            $qtyColor = ($qty > 0) ? 'var(--success)' : (($qty < 0) ? 'var(--danger)' : 'var(--gray-400)');
            $qtyPrefix = ($qty > 0) ? '+' : '';
            $typeLabel = InventoryModel::transactionTypeLabel($t['type'] ?? '');
        ?>
        <div class="staff-card">
            <div class="d-flex justify-between align-center">
                <strong><?= e($t['product_name'] ?? '-') ?></strong>
                <span class="badge" style="background:#e3f2fd;color:#1565c0"><?= e($typeLabel) ?></span>
            </div>
            <?php if (!empty($t['product_model'])): ?>
            <div style="font-size:.85rem;color:var(--gray-500)"><?= e($t['product_model']) ?></div>
            <?php endif; ?>
            <div class="staff-card-meta">
                <span><?= e($t['warehouse_name'] ?? '-') ?></span>
                <span style="color:<?= $qtyColor ?>;font-weight:600"><?= $qtyPrefix . $qty ?></span>
                <span>餘 <?= (int)($t['qty_after'] ?? 0) ?></span>
                <span><?= e(substr($t['created_at'] ?? '', 0, 16)) ?></span>
            </div>
            <?php if (!empty($t['note'])): ?>
            <div style="font-size:.8rem;color:var(--gray-500);margin-top:4px"><?= e($t['note']) ?></div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <!-- 桌面表格 -->
    <div class="table-responsive hide-mobile">
        <table class="table">
            <thead>
                <tr>
                    <th>時間</th>
                    <th>類型</th>
                    <th>商品名稱</th>
                    <th>型號</th>
                    <th>倉庫</th>
                    <th style="text-align:right">數量</th>
                    <th style="text-align:right">餘量</th>
                    <th>備註</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($transactions as $t):
                    $qty = (int)($t['quantity'] ?? 0);
                    $qtyColor = ($qty > 0) ? 'var(--success)' : (($qty < 0) ? 'var(--danger)' : 'var(--gray-400)');
                    $qtyPrefix = ($qty > 0) ? '+' : '';
                    $typeLabel = InventoryModel::transactionTypeLabel($t['type'] ?? '');
                ?>
                <tr>
                    <td style="white-space:nowrap"><?= e(substr($t['created_at'] ?? '', 0, 16)) ?></td>
                    <td><span class="badge" style="background:#e3f2fd;color:#1565c0;font-size:.75rem"><?= e($typeLabel) ?></span></td>
                    <td><?= e($t['product_name'] ?? '-') ?></td>
                    <td style="color:var(--gray-500);font-size:.85rem"><?= e($t['product_model'] ?? '') ?></td>
                    <td><?= e($t['warehouse_name'] ?? '-') ?></td>
                    <td style="text-align:right;font-weight:600;color:<?= $qtyColor ?>"><?= $qtyPrefix . $qty ?></td>
                    <td style="text-align:right"><?= (int)($t['qty_after'] ?? 0) ?></td>
                    <td style="font-size:.85rem;color:var(--gray-500)"><?= e($t['note'] ?? '') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php else: ?>
<!-- ============ 期間匯總 ============ -->
<div class="card">
    <div class="d-flex justify-between align-center mb-1">
        <span class="text-muted"><?= count($summaryData) ?> 項產品有異動</span>
    </div>
    <?php if (empty($summaryData)): ?>
        <p class="text-muted text-center mt-2">該期間無異動資料</p>
    <?php else: ?>
    <?php
        $grandIn = $grandOut = $grandAdj = $grandNet = 0;
        foreach ($summaryData as $s) {
            $grandIn += (int)$s['total_in'];
            $grandOut += (int)$s['total_out'];
            $grandAdj += (int)$s['total_adjust'];
            $grandNet += (int)$s['net_change'];
        }
    ?>
    <!-- 手機卡片 -->
    <div class="staff-cards show-mobile">
        <?php foreach ($summaryData as $s):
            $net = (int)$s['net_change'];
            $netColor = ($net > 0) ? 'var(--success)' : (($net < 0) ? 'var(--danger)' : 'var(--gray-400)');
        ?>
        <div class="staff-card">
            <strong><?= e($s['product_name']) ?></strong>
            <?php if ($s['product_model']): ?>
            <div style="font-size:.85rem;color:var(--gray-500)"><?= e($s['product_model']) ?></div>
            <?php endif; ?>
            <div class="staff-card-meta" style="margin-top:6px">
                <span style="color:var(--success)">入 +<?= (int)$s['total_in'] ?></span>
                <span style="color:var(--danger)">出 -<?= (int)$s['total_out'] ?></span>
                <?php if ((int)$s['total_adjust'] !== 0): ?>
                <span>調 <?= (int)$s['total_adjust'] > 0 ? '+' : '' ?><?= (int)$s['total_adjust'] ?></span>
                <?php endif; ?>
                <span style="color:<?= $netColor ?>;font-weight:600">淨 <?= $net > 0 ? '+' : '' ?><?= $net ?></span>
                <span>現有 <?= (int)$s['current_stock'] ?></span>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <!-- 桌面表格 -->
    <div class="table-responsive hide-mobile">
        <table class="table">
            <thead>
                <tr>
                    <th>商品名稱</th>
                    <th>型號</th>
                    <th style="text-align:right">入庫合計</th>
                    <th style="text-align:right">出庫合計</th>
                    <th style="text-align:right">盤點調整</th>
                    <th style="text-align:right">淨變動</th>
                    <th style="text-align:right">現有庫存</th>
                    <th style="text-align:right">異動筆數</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($summaryData as $s):
                    $net = (int)$s['net_change'];
                    $netColor = ($net > 0) ? 'var(--success)' : (($net < 0) ? 'var(--danger)' : 'var(--gray-400)');
                ?>
                <tr>
                    <td><?= e($s['product_name']) ?></td>
                    <td style="color:var(--gray-500);font-size:.85rem"><?= e($s['product_model'] ?? '') ?></td>
                    <td style="text-align:right;color:var(--success);font-weight:600"><?= (int)$s['total_in'] ? '+' . (int)$s['total_in'] : '-' ?></td>
                    <td style="text-align:right;color:var(--danger);font-weight:600"><?= (int)$s['total_out'] ? '-' . (int)$s['total_out'] : '-' ?></td>
                    <td style="text-align:right"><?= (int)$s['total_adjust'] !== 0 ? ((int)$s['total_adjust'] > 0 ? '+' : '') . (int)$s['total_adjust'] : '-' ?></td>
                    <td style="text-align:right;font-weight:700;color:<?= $netColor ?>"><?= $net > 0 ? '+' : '' ?><?= $net ?></td>
                    <td style="text-align:right;font-weight:600"><?= (int)$s['current_stock'] ?></td>
                    <td style="text-align:right;color:var(--gray-500)"><?= (int)$s['txn_count'] ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr style="font-weight:700;border-top:2px solid var(--gray-300)">
                    <td colspan="2">合計</td>
                    <td style="text-align:right;color:var(--success)">+<?= $grandIn ?></td>
                    <td style="text-align:right;color:var(--danger)">-<?= $grandOut ?></td>
                    <td style="text-align:right"><?= $grandAdj !== 0 ? ($grandAdj > 0 ? '+' : '') . $grandAdj : '-' ?></td>
                    <td style="text-align:right;color:<?= $grandNet >= 0 ? 'var(--success)' : 'var(--danger)' ?>"><?= $grandNet > 0 ? '+' : '' ?><?= $grandNet ?></td>
                    <td colspan="2"></td>
                </tr>
            </tfoot>
        </table>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>
