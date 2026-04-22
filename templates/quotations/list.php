<div class="d-flex justify-between align-center flex-wrap gap-1 mb-2">
    <h2>報價管理</h2>
    <?php if (Auth::hasPermission('quotations.manage')): ?>
    <a href="/quotations.php?action=create" class="btn btn-primary btn-sm">+ 新增報價單</a>
    <?php endif; ?>
</div>

<div class="card">
    <form method="GET" action="/quotations.php" class="filter-form">
        <div class="filter-row">
            <div class="form-group">
                <label>月份</label>
                <input type="month" name="month" class="form-control" value="<?= e($filters['month']) ?>">
            </div>
            <div class="form-group">
                <label>狀態</label>
                <select name="status" class="form-control">
                    <option value="">全部</option>
                    <option value="draft" <?= $filters['status'] === 'draft' ? 'selected' : '' ?>>草稿</option>
                    <option value="pending_approval" <?= $filters['status'] === 'pending_approval' ? 'selected' : '' ?>>待簽核</option>
                    <option value="approved" <?= $filters['status'] === 'approved' ? 'selected' : '' ?>>已核准</option>
                    <option value="rejected_internal" <?= $filters['status'] === 'rejected_internal' ? 'selected' : '' ?>>退回修改</option>
                    <option value="sent" <?= $filters['status'] === 'sent' ? 'selected' : '' ?>>已送客戶</option>
                    <option value="customer_accepted" <?= $filters['status'] === 'customer_accepted' ? 'selected' : '' ?>>客戶已接受</option>
                    <option value="customer_rejected" <?= $filters['status'] === 'customer_rejected' ? 'selected' : '' ?>>客戶已拒絕</option>
                    <option value="revision_needed" <?= $filters['status'] === 'revision_needed' ? 'selected' : '' ?>>待修改</option>
                </select>
            </div>
            <div class="form-group">
                <label>關鍵字</label>
                <input type="text" name="keyword" class="form-control" value="<?= e($filters['keyword']) ?>" placeholder="客戶名/單號/案場">
            </div>
            <div class="form-group" style="align-self:flex-end">
                <button type="submit" class="btn btn-primary btn-sm">搜尋</button>
                <a href="/quotations.php" class="btn btn-outline btn-sm">清除</a>
            </div>
        </div>
    </form>
</div>

<div class="card">
    <?php if (empty($quotations)): ?>
        <p class="text-muted text-center mt-2">目前無報價單</p>
    <?php else: ?>
    <div class="staff-cards show-mobile">
        <?php foreach ($quotations as $q):
            if (!function_exists('_quoteProfitBadge')) {
                function _quoteProfitBadge($pr) {
                    if ($pr === null || $pr === '') return null;
                    $pr = (float)$pr;
                    if ($pr >= 15)   return array('label' => '優質', 'bg' => '#e6f4ea', 'color' => '#137333');
                    if ($pr >= 10)   return array('label' => '警戒', 'bg' => '#fef7e0', 'color' => '#b26a00');
                    if ($pr >= 0)    return array('label' => '警告', 'bg' => '#fde7e7', 'color' => '#c5221f');
                    return array('label' => '虧損', 'bg' => '#fce8e6', 'color' => '#c5221f');
                }
            }
            $pb = _quoteProfitBadge(isset($q['profit_rate']) ? $q['profit_rate'] : null);
        ?>
        <div class="staff-card" onclick="location.href='/quotations.php?action=view&id=<?= $q['id'] ?>'">
            <div class="d-flex justify-between align-center">
                <span>
                    <strong><?= e($q['customer_name']) ?></strong>
                    <?php if ($pb): ?>
                    <span class="badge" style="background:<?= $pb['bg'] ?>;color:<?= $pb['color'] ?>;font-size:.65em;margin-left:4px;padding:2px 6px" title="利潤率 <?= number_format((float)$q['profit_rate'], 1) ?>%"><?= $pb['label'] ?></span>
                    <?php endif; ?>
                </span>
                <span class="badge badge-<?= QuotationModel::statusBadge($q['status']) ?>"><?= e(QuotationModel::statusLabel($q['status'])) ?></span>
            </div>
            <div class="staff-card-meta">
                <span><?= e($q['quotation_number']) ?></span>
                <span><?= e($q['quote_date']) ?></span>
                <span><?= QuotationModel::formatLabel($q['format']) ?></span>
                <span>$<?= number_format($q['total_amount']) ?></span>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="table-responsive hide-mobile">
        <table class="table">
            <thead><tr>
                <th>單號</th><th>日期</th><th>客戶</th><th>案場</th><th>格式</th><th>業務</th><th>金額</th><th>狀態</th><th>操作</th>
            </tr></thead>
            <tbody>
                <?php foreach ($quotations as $q): $pb = _quoteProfitBadge(isset($q['profit_rate']) ? $q['profit_rate'] : null); ?>
                <tr>
                    <td><a href="/quotations.php?action=view&id=<?= $q['id'] ?>"><?= e($q['quotation_number']) ?></a></td>
                    <td><?= e($q['quote_date']) ?></td>
                    <td>
                        <?= e($q['customer_name']) ?>
                        <?php if ($pb): ?>
                        <span class="badge" style="background:<?= $pb['bg'] ?>;color:<?= $pb['color'] ?>;font-size:.7em;margin-left:4px;padding:2px 6px" title="利潤率 <?= number_format((float)$q['profit_rate'], 1) ?>%"><?= $pb['label'] ?></span>
                        <?php endif; ?>
                    </td>
                    <td><?= e(mb_substr($q['site_name'] ?: '-', 0, 15)) ?></td>
                    <td><?= QuotationModel::formatLabel($q['format']) ?></td>
                    <td><?= e($q['sales_name'] ?: '-') ?></td>
                    <td class="text-right">$<?= number_format($q['total_amount']) ?></td>
                    <td><span class="badge badge-<?= QuotationModel::statusBadge($q['status']) ?>"><?= e(QuotationModel::statusLabel($q['status'])) ?></span></td>
                    <td>
                        <a href="/quotations.php?action=view&id=<?= $q['id'] ?>" class="btn btn-outline btn-sm">檢視</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<style>
.filter-row { display: flex; flex-wrap: wrap; gap: 12px; align-items: flex-end; }
.filter-row .form-group { flex: 1; min-width: 130px; margin-bottom: 0; }
.staff-cards { display: flex; flex-direction: column; gap: 8px; }
.staff-card { border: 1px solid var(--gray-200); border-radius: var(--radius); padding: 12px; cursor: pointer; transition: box-shadow .15s; }
.staff-card:hover { box-shadow: var(--shadow); }
.staff-card-meta { font-size: .8rem; color: var(--gray-500); display: flex; gap: 8px; margin-top: 4px; flex-wrap: wrap; }
.show-mobile { display: flex; }
.hide-mobile { display: none; }
@media (min-width: 768px) { .show-mobile { display: none !important; } .hide-mobile { display: block !important; } }
</style>
