<div class="d-flex justify-between align-center flex-wrap gap-1 mb-2">
    <h2>入庫單 <small class="text-muted">(<?= $pagination['total'] ?> 筆<?= $pagination['totalPages'] > 1 ? '，第' . $pagination['page'] . '/' . $pagination['totalPages'] . '頁' : '' ?>)</small></h2>
    <?php if (Auth::hasPermission('inventory.manage')): ?>
    <a href="/stock_ins.php?action=create" class="btn btn-primary btn-sm">+ 新增入庫單</a>
    <?php endif; ?>
</div>

<div class="card">
    <form method="GET" action="/stock_ins.php" class="filter-form">
        <div class="filter-row">
            <div class="form-group">
                <label>狀態</label>
                <select name="status" class="form-control">
                    <option value="">全部</option>
                    <?php foreach (StockModel::stockInStatusOptions() as $k => $v): ?>
                    <option value="<?= e($k) ?>" <?= (!empty($filters['status']) ? $filters['status'] : '') === $k ? 'selected' : '' ?>><?= e($v) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>倉庫</label>
                <select name="warehouse_id" class="form-control">
                    <option value="">全部</option>
                    <?php foreach ($warehouses as $wh): ?>
                    <option value="<?= $wh['id'] ?>" <?= (!empty($filters['warehouse_id']) ? $filters['warehouse_id'] : '') == $wh['id'] ? 'selected' : '' ?>><?= e($wh['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>來源類型</label>
                <select name="source_type" class="form-control">
                    <option value="">全部</option>
                    <option value="goods_receipt" <?= (!empty($filters['source_type']) ? $filters['source_type'] : '') === 'goods_receipt' ? 'selected' : '' ?>>進貨單</option>
                    <option value="manual" <?= (!empty($filters['source_type']) ? $filters['source_type'] : '') === 'manual' ? 'selected' : '' ?>>手動入庫</option>
                    <option value="return_material" <?= (!empty($filters['source_type']) ? $filters['source_type'] : '') === 'return_material' ? 'selected' : '' ?>>餘料入庫</option>
                    <option value="manual_return" <?= (!empty($filters['source_type']) ? $filters['source_type'] : '') === 'manual_return' ? 'selected' : '' ?>>手動餘料入庫</option>
                </select>
            </div>
            <div class="form-group" style="position:relative">
                <label>廠商名稱</label>
                <input type="text" id="siVendorName" autocomplete="off" name="vendor_name" class="form-control" value="<?= e(!empty($filters['vendor_name']) ? $filters['vendor_name'] : '') ?>" placeholder="廠商名稱">
                <div id="siVendorSuggestions" class="si-vendor-suggestions"></div>
            </div>
            <div class="form-group">
                <label>關鍵字</label>
                <input type="text" name="keyword" class="form-control" value="<?= e(!empty($filters['keyword']) ? $filters['keyword'] : '') ?>" placeholder="單號">
            </div>
            <div class="form-group">
                <label>日期起</label>
                <input type="date" max="2099-12-31" name="date_from" class="form-control" value="<?= e(!empty($filters['date_from']) ? $filters['date_from'] : '') ?>">
            </div>
            <div class="form-group">
                <label>日期迄</label>
                <input type="date" max="2099-12-31" name="date_to" class="form-control" value="<?= e(!empty($filters['date_to']) ? $filters['date_to'] : '') ?>">
            </div>
            <div class="form-group" style="align-self:flex-end">
                <button type="submit" class="btn btn-primary btn-sm">搜尋</button>
                <a href="/stock_ins.php" class="btn btn-outline btn-sm">清除</a>
            </div>
        </div>
    </form>
</div>

<?php
function siStatusBadge($status) {
    $color = StockModel::statusBadgeColor($status);
    return '<span class="badge badge-' . $color . '">' . e($status) . '</span>';
}
?>

<div class="card">
    <?php if (empty($records)): ?>
        <p class="text-muted text-center mt-2">目前無入庫單</p>
    <?php else: ?>
    <div class="staff-cards show-mobile">
        <?php foreach ($records as $r): ?>
        <div class="staff-card" onclick="location.href='/stock_ins.php?action=view&id=<?= $r['id'] ?>'">
            <div class="d-flex justify-between align-center">
                <strong><?= e(!empty($r['si_number']) ? $r['si_number'] : '-') ?></strong>
                <?= siStatusBadge(!empty($r['status']) ? $r['status'] : '') ?>
            </div>
            <div class="staff-card-meta">
                <span><?= e(!empty($r['si_date']) ? $r['si_date'] : '') ?></span>
                <span><?= e(!empty($r['warehouse_name']) ? $r['warehouse_name'] : '') ?></span>
                <?php if (!empty($r['vendor_name'])): ?><span style="color:#1565c0"><?= e($r['vendor_name']) ?></span>
                <?php elseif (!empty($r['customer_name'])): ?><span style="color:#e65100"><?= e($r['customer_name']) ?></span>
                <?php else: ?><span>-</span><?php endif; ?>
            </div>
            <div class="staff-card-meta">
                <span>數量 <?= number_format(!empty($r['total_qty']) ? $r['total_qty'] : 0) ?></span>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="table-responsive hide-mobile">
        <table class="table">
            <thead>
                <tr>
                    <th>入庫單號</th>
                    <th>日期</th>
                    <th>狀態</th>
                    <th>來源類型</th>
                    <th>客戶 / 廠商</th>
                    <th>倉庫</th>
                    <th class="text-right">總數量</th>
                    <th>建立者</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($records as $r): ?>
                <tr>
                    <td><a href="/stock_ins.php?action=view&id=<?= $r['id'] ?>"><?= e(!empty($r['si_number']) ? $r['si_number'] : '') ?></a></td>
                    <td><?= e(!empty($r['si_date']) ? $r['si_date'] : '') ?></td>
                    <td><?= siStatusBadge(!empty($r['status']) ? $r['status'] : '') ?></td>
                    <td><?= e(StockModel::sourceTypeLabel(!empty($r['source_type']) ? $r['source_type'] : '')) ?></td>
                    <td><?php
                        if (!empty($r['vendor_name'])) {
                            echo '<span style="color:#1565c0;font-weight:500">' . e($r['vendor_name']) . '</span>';
                        } elseif (!empty($r['customer_name'])) {
                            echo '<span style="color:#e65100;font-weight:500">' . e($r['customer_name']) . '</span>';
                        } else {
                            echo '-';
                        }
                    ?></td>
                    <td><?= e(!empty($r['warehouse_name']) ? $r['warehouse_name'] : '') ?></td>
                    <td class="text-right"><?= number_format(!empty($r['total_qty']) ? $r['total_qty'] : 0) ?></td>
                    <td><?= e(!empty($r['created_by_name']) ? $r['created_by_name'] : '') ?></td>
                    <td>
                        <a href="/stock_ins.php?action=view&id=<?= $r['id'] ?>" class="btn btn-outline btn-sm">檢視</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php if ($pagination['totalPages'] > 1): ?>
<div class="d-flex justify-center gap-1 mt-2" style="flex-wrap:wrap">
    <?php
    $qp = $_GET; unset($qp['page']);
    $qs = http_build_query($qp);
    $base = '/stock_ins.php?' . ($qs ? $qs . '&' : '');
    ?>
    <?php if ($pagination['page'] > 1): ?>
    <a href="<?= $base ?>page=<?= $pagination['page'] - 1 ?>" class="btn btn-outline btn-sm">&laquo; 上一頁</a>
    <?php endif; ?>
    <?php for ($p = 1; $p <= $pagination['totalPages']; $p++): ?>
    <a href="<?= $base ?>page=<?= $p ?>" class="btn btn-sm <?= $p == $pagination['page'] ? 'btn-primary' : 'btn-outline' ?>"><?= $p ?></a>
    <?php endfor; ?>
    <?php if ($pagination['page'] < $pagination['totalPages']): ?>
    <a href="<?= $base ?>page=<?= $pagination['page'] + 1 ?>" class="btn btn-outline btn-sm">下一頁 &raquo;</a>
    <?php endif; ?>
</div>
<?php endif; ?>

<style>
.filter-row { display: flex; flex-wrap: wrap; gap: 12px; align-items: flex-end; }
.filter-row .form-group { flex: 1; min-width: 130px; margin-bottom: 0; }
.staff-cards { display: flex; flex-direction: column; gap: 8px; }
.staff-card { border: 1px solid var(--gray-200); border-radius: var(--radius); padding: 12px; cursor: pointer; transition: box-shadow .15s; }
.staff-card:hover { box-shadow: var(--shadow); }
.staff-card-meta { font-size: .8rem; color: var(--gray-500); display: flex; gap: 8px; margin-top: 4px; }
.badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: .75rem; font-weight: 500; }
.badge-orange { background: #fff3e0; color: #e65100; }
.badge-green { background: #e8f5e9; color: #2e7d32; }
.badge-gray { background: #f5f5f5; color: #757575; }
.show-mobile { display: flex; }
.hide-mobile { display: none; }
@media (min-width: 768px) { .show-mobile { display: none !important; } .hide-mobile { display: block !important; } }
.justify-center { justify-content: center; }
.si-vendor-suggestions { display:none; position:absolute; top:100%; left:0; right:0; max-height:260px; overflow-y:auto; background:#fff; border:1px solid var(--gray-200); border-radius:var(--radius); box-shadow:var(--shadow); z-index:50; }
.si-vendor-suggestions.show { display:block; }
.si-vendor-suggestions .ac-item { padding:8px 12px; cursor:pointer; border-bottom:1px solid var(--gray-100); }
.si-vendor-suggestions .ac-item:hover { background:var(--gray-50); }
.si-vendor-suggestions .ac-main { font-weight:600; font-size:.9rem; }
.si-vendor-suggestions .ac-sub { font-size:.78rem; color:var(--gray-500); }
</style>
<script>
(function() {
    var input = document.getElementById('siVendorName');
    var list = document.getElementById('siVendorSuggestions');
    if (!input || !list) return;
    var timer = null;
    input.addEventListener('input', function() {
        clearTimeout(timer);
        var q = this.value.trim();
        if (q.length < 1) { list.classList.remove('show'); return; }
        timer = setTimeout(function() {
            var xhr = new XMLHttpRequest();
            xhr.open('GET', '/payments_out.php?action=ajax_vendor_search&q=' + encodeURIComponent(q));
            xhr.onload = function() {
                try {
                    var data = JSON.parse(xhr.responseText);
                    if (!data || data.length === 0) { list.classList.remove('show'); return; }
                    var html = '';
                    for (var i = 0; i < data.length; i++) {
                        var name = (data[i].name || '').replace(/"/g, '&quot;');
                        var code = data[i].vendor_code || '';
                        var contact = data[i].contact_person || '';
                        var phone = data[i].phone || '';
                        html += '<div class="ac-item" data-name="' + name + '">';
                        html += '<div class="ac-main">' + (data[i].name || '') + '</div>';
                        html += '<div class="ac-sub">' + (code ? '編號:' + code + ' | ' : '') + contact + ' ' + phone + '</div>';
                        html += '</div>';
                    }
                    list.innerHTML = html;
                    list.classList.add('show');
                    list.querySelectorAll('.ac-item').forEach(function(el) {
                        el.addEventListener('click', function() {
                            input.value = this.getAttribute('data-name');
                            list.classList.remove('show');
                        });
                    });
                } catch (ex) {
                    list.classList.remove('show');
                }
            };
            xhr.send();
        }, 250);
    });
    document.addEventListener('click', function(e) {
        if (!input.contains(e.target) && !list.contains(e.target)) list.classList.remove('show');
    });
})();
</script>
