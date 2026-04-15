<?php
$statusOptions = FinanceModel::paymentOutStatusOptions();
$statusBadgeMap = array(
    '草稿' => 'badge-secondary',
    '已付款' => 'badge-success',
    '預付待查' => 'badge-info',
    '已付待查' => 'badge-warning',
    '待付款' => 'badge-warning',
    '取消'   => 'badge-secondary',
);
?>
<div class="d-flex justify-between align-center flex-wrap gap-1 mb-2">
    <h2>付款單 <span style="font-size:.6em;color:#888;font-weight:normal">共 <?= number_format($result['total']) ?> 筆</span> <span style="font-size:.55em;color:var(--primary);font-weight:600">合計 $<?= number_format(isset($result['sum_amount']) ? $result['sum_amount'] : 0) ?></span></h2>
    <a href="/payments_out.php?action=create" class="btn btn-primary btn-sm">+ 新增付款單</a>
</div>

<div class="card">
    <form method="GET" action="/payments_out.php" class="filter-form">
        <div class="filter-row">
            <div class="form-group">
                <label>狀態</label>
                <select name="status" class="form-control">
                    <option value="">全部</option>
                    <?php foreach ($statusOptions as $val => $label): ?>
                    <option value="<?= e($val) ?>" <?= (!empty($filters['status']) && $filters['status'] === $val) ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>主分類</label>
                <select name="main_category" class="form-control">
                    <option value="">全部</option>
                    <?php foreach (FinanceModel::mainCategoryOptions() as $label): ?>
                    <option value="<?= e($label) ?>" <?= (!empty($filters['main_category']) && $filters['main_category'] === $label) ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>分公司</label>
                <select name="branch_id" class="form-control">
                    <option value="">全部</option>
                    <?php foreach ($branches as $b): ?>
                    <option value="<?= $b['id'] ?>" <?= (!empty($filters['branch_id']) && $filters['branch_id'] == $b['id']) ? 'selected' : '' ?>><?= e($b['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>關鍵字</label>
                <input type="text" name="keyword" class="form-control" value="<?= e($filters['keyword'] ?? '') ?>" placeholder="廠商名/單號／$金額" autocomplete="off">
            </div>
            <div class="form-group">
                <label>日期類型</label>
                <select name="date_type" class="form-control">
                    <option value="create" <?= ($filters['date_type'] ?? 'create') === 'create' ? 'selected' : '' ?>>建立日期</option>
                    <option value="payment" <?= ($filters['date_type'] ?? '') === 'payment' ? 'selected' : '' ?>>付款日期</option>
                </select>
            </div>
            <div class="form-group">
                <label>起始日期</label>
                <input type="date" name="date_from" class="form-control" value="<?= e($filters['date_from'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>結束日期</label>
                <input type="date" name="date_to" class="form-control" value="<?= e($filters['date_to'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>排序</label>
                <select name="sort" class="form-control">
                    <option value="desc" <?= ($filters['sort'] ?? 'desc') === 'desc' ? 'selected' : '' ?>>由新到舊</option>
                    <option value="asc" <?= ($filters['sort'] ?? '') === 'asc' ? 'selected' : '' ?>>由舊到新</option>
                </select>
            </div>
            <div class="form-group" style="align-self:flex-end">
                <button type="submit" class="btn btn-primary btn-sm">搜尋</button>
                <a href="/payments_out.php" class="btn btn-outline btn-sm">清除</a>
            </div>
        </div>
    </form>
</div>

<div class="card">
    <?php if (empty($records)): ?>
        <p class="text-muted text-center mt-2">目前無付款單</p>
    <?php else: ?>
    <!-- 手機卡片 -->
    <div class="staff-cards show-mobile">
        <?php foreach ($records as $r): ?>
        <?php $badgeCls = !empty($statusBadgeMap[$r['status']]) ? $statusBadgeMap[$r['status']] : 'badge-secondary'; ?>
        <?php $isExcluded = !empty($r['exclude_from_branch_stats']); ?>
        <div class="staff-card" onclick="location.href='/payments_out.php?action=edit&id=<?= $r['id'] ?>'">
            <div class="d-flex justify-between align-center">
                <strong>
                    <?= e(!empty($r['vendor_name']) ? $r['vendor_name'] : '-') ?>
                    <?php if ($isExcluded): ?>
                    <span style="background:#ff9800;color:#fff;font-size:.7rem;padding:1px 6px;border-radius:3px;margin-left:4px">📌補帳</span>
                    <?php endif; ?>
                </strong>
                <span class="badge <?= $badgeCls ?>"><?= e(!empty($r['status']) ? $r['status'] : '-') ?></span>
            </div>
            <div class="staff-card-meta">
                <span><?= e(!empty($r['payment_number']) ? $r['payment_number'] : '-') ?></span>
                <span><?= e(!empty($r['payment_date']) ? $r['payment_date'] : '-') ?></span>
                <span>$<?= number_format(!empty($r['total_amount']) ? $r['total_amount'] : 0) ?></span>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- 桌面表格 -->
    <div class="table-responsive hide-mobile">
        <table class="table">
            <thead>
                <tr>
                    <th style="width:32px"></th>
                    <th>付款單編號</th>
                    <th>建立日期</th>
                    <th>付款日期</th>
                    <th>廠商名稱</th>
                    <th>主分類</th>
                    <th class="text-right">付款金額</th>
                    <th>狀態</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($records as $r): ?>
                <?php $badgeCls = !empty($statusBadgeMap[$r['status']]) ? $statusBadgeMap[$r['status']] : 'badge-secondary'; ?>
                <?php $isExcluded = !empty($r['exclude_from_branch_stats']); $isStar = !empty($r['is_starred']); ?>
                <tr<?= $isExcluded ? ' style="background:#fff8e1"' : '' ?>>
                    <td class="text-center">
                        <span class="star-toggle <?= $isStar ? 'is-on' : '' ?>" data-id="<?= (int)$r['id'] ?>" onclick="toggleStarPaymentOut(this)" title="標記">&#9733;</span>
                    </td>
                    <td>
                        <a href="/payments_out.php?action=edit&id=<?= $r['id'] ?>"><?= e(!empty($r['payment_number']) ? $r['payment_number'] : '-') ?></a>
                        <?php if ($isExcluded): ?>
                        <span style="background:#ff9800;color:#fff;font-size:.7rem;padding:1px 6px;border-radius:3px;margin-left:4px;font-weight:600" title="不列入分公司年度統計">📌補帳</span>
                        <?php endif; ?>
                    </td>
                    <td><?= e(!empty($r['create_date']) ? $r['create_date'] : '-') ?></td>
                    <td><?= e(!empty($r['payment_date']) ? $r['payment_date'] : '-') ?></td>
                    <td><?= e(!empty($r['vendor_name']) ? $r['vendor_name'] : '-') ?></td>
                    <td><?= e(!empty($r['main_category']) ? $r['main_category'] : '-') ?></td>
                    <td class="text-right">$<?= number_format(!empty($r['total_amount']) ? $r['total_amount'] : 0) ?></td>
                    <td><span class="badge <?= $badgeCls ?>"><?= e(!empty($r['status']) ? $r['status'] : '-') ?></span></td>
                    <td>
                        <a href="/payments_out.php?action=edit&id=<?= $r['id'] ?>" class="btn btn-outline btn-sm">編輯</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
    <?php require __DIR__ . '/../layouts/pagination.php'; ?>
</div>

<style>
.filter-row { display: flex; flex-wrap: wrap; gap: 12px; align-items: flex-end; }
.filter-row .form-group { flex: 1; min-width: 130px; margin-bottom: 0; }
.staff-cards { display: flex; flex-direction: column; gap: 8px; }
.staff-card { border: 1px solid var(--gray-200); border-radius: var(--radius); padding: 12px; cursor: pointer; transition: box-shadow .15s; }
.staff-card:hover { box-shadow: var(--shadow); }
.staff-card-meta { font-size: .8rem; color: var(--gray-500); display: flex; gap: 8px; margin-top: 4px; }
.show-mobile { display: flex; }
.hide-mobile { display: none; }
@media (min-width: 768px) { .show-mobile { display: none !important; } .hide-mobile { display: block !important; } }
.star-toggle { display:inline-block; cursor:pointer; font-size:1.2rem; color:#d0d0d0; transition:color .15s,transform .15s; user-select:none; line-height:1; }
.star-toggle:hover { color:#f1c40f; transform:scale(1.15); }
.star-toggle.is-on { color:#f1c40f; }
.star-toggle.saving { opacity:.5; pointer-events:none; }
</style>

<script>
function toggleStarPaymentOut(el) {
    if (el.classList.contains('saving')) return;
    var id = el.getAttribute('data-id'); if (!id) return;
    el.classList.add('saving');
    var fd = new FormData(); fd.append('id', id);
    var xhr = new XMLHttpRequest();
    xhr.open('POST', '/payments_out.php?action=toggle_star');
    xhr.onload = function() {
        el.classList.remove('saving');
        try { var res = JSON.parse(xhr.responseText); if (res.error) { alert(res.error); return; } el.classList.toggle('is-on', !!res.starred); } catch (e) { alert('回應錯誤'); }
    };
    xhr.onerror = function() { el.classList.remove('saving'); alert('網路錯誤'); };
    xhr.send(fd);
}
</script>
