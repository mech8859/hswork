<?php
$statusOptions = FinanceModel::receivableStatusOptions();
$statusBadgeMap = array(
    '待請款'   => 'badge-info',
    '已請款'   => 'badge-primary',
    '部分收款' => 'badge-warning',
    '已收款'   => 'badge-success',
    '逾期'     => 'badge-danger',
    '取消'     => 'badge-secondary',
);
?>
<div class="d-flex justify-between align-center flex-wrap gap-1 mb-2">
    <h2>應收帳款 <span style="font-size:.6em;color:#888;font-weight:normal">共 <?= number_format($result['total']) ?> 筆</span></h2>
    <a href="/receivables.php?action=create" class="btn btn-primary btn-sm">+ 新增請款單</a>
</div>

<div class="card">
    <form method="GET" action="/receivables.php" class="filter-form">
        <div class="filter-row">
            <div class="form-group">
                <label>狀態</label>
                <select name="status" class="form-control">
                    <option value="">全部</option>
                    <?php foreach ($statusOptions as $sv => $sl): ?>
                    <option value="<?= e($sv) ?>" <?= (!empty($filters['status']) && $filters['status'] === $sv) ? 'selected' : '' ?>><?= e($sl) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>關鍵字</label>
                <input type="text" name="keyword" class="form-control" value="<?= e($filters['keyword'] ?? '') ?>" placeholder="請款單號/客戶名稱／$金額" autocomplete="off">
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
                <a href="/receivables.php" class="btn btn-outline btn-sm">清除</a>
            </div>
        </div>
    </form>
</div>

<div class="card">
    <?php if (empty($data)): ?>
        <p class="text-muted text-center mt-2">目前無應收帳款資料</p>
    <?php else: ?>

    <div class="receivable-cards show-mobile">
        <?php foreach ($data as $row): ?>
        <?php $badgeCls = !empty($statusBadgeMap[$row['status']]) ? $statusBadgeMap[$row['status']] : 'badge-secondary'; ?>
        <div class="receivable-card" onclick="location.href='/receivables.php?action=edit&id=<?= $row['id'] ?>'">
            <div class="d-flex justify-between align-center">
                <strong><?= e($row['receivable_number'] ?: $row['invoice_number'] ?: '') ?></strong>
                <span class="badge <?= $badgeCls ?>"><?= e($row['status'] ?: '-') ?></span>
            </div>
            <div class="receivable-card-title"><?= e($row['customer_name'] ?: '-') ?></div>
            <div class="receivable-card-meta">
                <span><?= e($row['branch_name'] ?? '') ?></span>
                <span>$<?= number_format($row['total_amount'] ?: $row['subtotal'] ?: 0) ?></span>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="table-responsive hide-mobile">
        <table class="table">
            <thead>
                <tr>
                    <th style="width:32px"></th>
                    <th>請款單號</th>
                    <th>傳票號碼</th>
                    <th>請款日期</th>
                    <th>客戶名稱</th>
                    <th>分公司</th>
                    <th>業務</th>
                    <th class="text-right">總計</th>
                    <th>狀態</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($data as $row): ?>
                <?php $badgeCls = !empty($statusBadgeMap[$row['status']]) ? $statusBadgeMap[$row['status']] : 'badge-secondary'; $isStar = !empty($row['is_starred']); ?>
                <tr>
                    <td class="text-center">
                        <span class="star-toggle <?= $isStar ? 'is-on' : '' ?>"
                              data-id="<?= (int)$row['id'] ?>"
                              onclick="toggleStarReceivable(this)"
                              title="標記/取消標記">&#9733;</span>
                    </td>
                    <td><a href="/receivables.php?action=edit&id=<?= $row['id'] ?>"><?= e($row['receivable_number'] ?: $row['invoice_number'] ?: '-') ?></a></td>
                    <td><?= e($row['voucher_number'] ?? '-') ?></td>
                    <td><?= !empty($row['invoice_date']) ? $row['invoice_date'] : '-' ?></td>
                    <td><?= e($row['customer_name'] ?: '-') ?></td>
                    <td><?= e($row['branch_name'] ?? '-') ?></td>
                    <td><?= e($row['sales_name'] ?? '-') ?></td>
                    <td class="text-right">$<?= number_format($row['total_amount'] ?: $row['subtotal'] ?: 0) ?></td>
                    <td><span class="badge <?= $badgeCls ?>"><?= e($row['status'] ?: '-') ?></span></td>
                    <td><a href="/receivables.php?action=edit&id=<?= $row['id'] ?>" class="btn btn-outline btn-sm">編輯</a></td>
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
.receivable-cards { display: flex; flex-direction: column; gap: 8px; }
.receivable-card { border: 1px solid var(--gray-200); border-radius: var(--radius); padding: 12px; cursor: pointer; transition: box-shadow .15s; }
.receivable-card:hover { box-shadow: var(--shadow); }
.receivable-card-title { font-weight: 500; margin: 4px 0; }
.receivable-card-meta { font-size: .8rem; color: var(--gray-500); display: flex; gap: 8px; }
.show-mobile { display: flex; }
.hide-mobile { display: none; }
@media (min-width: 768px) { .show-mobile { display: none !important; } .hide-mobile { display: block !important; } }

.star-toggle { display:inline-block; cursor:pointer; font-size:1.2rem; color:#d0d0d0; transition:color .15s,transform .15s; user-select:none; line-height:1; }
.star-toggle:hover { color:#f1c40f; transform:scale(1.15); }
.star-toggle.is-on { color:#f1c40f; }
.star-toggle.saving { opacity:.5; pointer-events:none; }
</style>

<script>
function toggleStarReceivable(el) {
    if (el.classList.contains('saving')) return;
    var id = el.getAttribute('data-id');
    if (!id) return;
    el.classList.add('saving');
    var form = new FormData();
    form.append('id', id);
    var xhr = new XMLHttpRequest();
    xhr.open('POST', '/receivables.php?action=toggle_star');
    xhr.onload = function() {
        el.classList.remove('saving');
        try {
            var res = JSON.parse(xhr.responseText);
            if (res.error) { alert(res.error); return; }
            el.classList.toggle('is-on', !!res.starred);
        } catch (e) { alert('回應錯誤'); }
    };
    xhr.onerror = function() { el.classList.remove('saving'); alert('網路錯誤'); };
    xhr.send(form);
}
</script>
