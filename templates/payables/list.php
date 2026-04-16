<div class="d-flex justify-between align-center flex-wrap gap-1 mb-2">
    <h2>應付帳款單 <span style="font-size:.6em;color:#888;font-weight:normal">共 <?= number_format($result['total']) ?> 筆</span></h2>
    <a href="/payables.php?action=create" class="btn btn-primary btn-sm">+ 新增應付帳款</a>
</div>

<div class="card">
    <form method="GET" action="/payables.php" class="filter-form">
        <div class="filter-row">
            <div class="form-group">
                <label>關鍵字</label>
                <input type="text" name="keyword" class="form-control" value="<?= e($filters['keyword'] ?? '') ?>" placeholder="廠商名稱/付款單號／$金額">
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
                <a href="/payables.php" class="btn btn-outline btn-sm">清除</a>
            </div>
        </div>
    </form>
</div>

<div class="card">
    <?php if (empty($records)): ?>
        <p class="text-muted text-center mt-2">目前無應付帳款單</p>
    <?php else: ?>
    <div class="staff-cards show-mobile">
        <?php foreach ($records as $r): ?>
        <div class="staff-card" onclick="location.href='/payables.php?action=edit&id=<?= $r['id'] ?>'">
            <div class="d-flex justify-between align-center">
                <strong><?= e(!empty($r['vendor_name']) ? $r['vendor_name'] : '-') ?></strong>
                <span class="text-muted" style="font-size:.8rem"><?= e(!empty($r['payable_number']) ? $r['payable_number'] : '') ?></span>
            </div>
            <div class="staff-card-meta">
                <span><?= e(!empty($r['create_date']) ? $r['create_date'] : '') ?></span>
                <span>應付 $<?= number_format(!empty($r['payable_amount']) ? $r['payable_amount'] : 0) ?></span>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="table-responsive hide-mobile">
        <table class="table">
            <thead>
                <tr>
                    <th style="width:32px"></th>
                    <th>付款單號</th>
                    <th>建立日期</th>
                    <th>廠商名稱</th>
                    <th>付款期間</th>
                    <th class="text-right">未稅總額</th>
                    <th class="text-right">稅金</th>
                    <th class="text-right">總計</th>
                    <th class="text-right">應付總額</th>
                    <th>備註</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($records as $r): $isStar = !empty($r['is_starred']); ?>
                <tr>
                    <td class="text-center">
                        <span class="star-toggle <?= $isStar ? 'is-on' : '' ?>" data-id="<?= (int)$r['id'] ?>" onclick="toggleStarPayable(this)" title="標記">&#9733;</span>
                    </td>
                    <td><a href="/payables.php?action=edit&id=<?= $r['id'] ?>"><?= e(!empty($r['payable_number']) ? $r['payable_number'] : '') ?></a></td>
                    <td><?= e(!empty($r['create_date']) ? $r['create_date'] : '') ?></td>
                    <td><?= e(!empty($r['vendor_name']) ? $r['vendor_name'] : '-') ?></td>
                    <td><?= e(!empty($r['payment_period']) ? $r['payment_period'] : (!empty($r['payment_terms']) ? $r['payment_terms'] : '-')) ?></td>
                    <td class="text-right">$<?= number_format(!empty($r['subtotal']) ? $r['subtotal'] : 0) ?></td>
                    <td class="text-right">$<?= number_format(!empty($r['tax']) ? $r['tax'] : 0) ?></td>
                    <td class="text-right">$<?= number_format(!empty($r['total_amount']) ? $r['total_amount'] : 0) ?></td>
                    <td class="text-right">$<?= number_format(!empty($r['payable_amount']) ? $r['payable_amount'] : 0) ?></td>
                    <td style="max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:#666;font-size:.85rem" title="<?= e(!empty($r['note']) ? $r['note'] : '') ?>"><?= e(!empty($r['note']) ? $r['note'] : '') ?></td>
                    <td>
                        <a href="/payables.php?action=edit&id=<?= $r['id'] ?>" class="btn btn-outline btn-sm">編輯</a>
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
function toggleStarPayable(el) {
    if (el.classList.contains('saving')) return;
    var id = el.getAttribute('data-id'); if (!id) return;
    el.classList.add('saving');
    var fd = new FormData(); fd.append('id', id);
    var xhr = new XMLHttpRequest();
    xhr.open('POST', '/payables.php?action=toggle_star');
    xhr.onload = function() {
        el.classList.remove('saving');
        try { var res = JSON.parse(xhr.responseText); if (res.error) { alert(res.error); return; } el.classList.toggle('is-on', !!res.starred); } catch (e) { alert('回應錯誤'); }
    };
    xhr.onerror = function() { el.classList.remove('saving'); alert('網路錯誤'); };
    xhr.send(fd);
}
</script>
