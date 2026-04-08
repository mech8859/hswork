<?php
function siViewStatusBadge($status) {
    $color = StockModel::statusBadgeColor($status);
    return '<span class="badge badge-' . $color . '">' . e($status) . '</span>';
}
?>

<div class="d-flex justify-between align-center flex-wrap gap-1 mb-2">
    <div>
        <h2 style="margin-bottom:2px">入庫單 <?= e($record['si_number']) ?> <?= siViewStatusBadge($record['status']) ?></h2>
        <?php if (!empty($record['updated_at'])): ?>
        <small class="text-muted">最後修改 <?= e($record['updated_at']) ?><?php
            if (!empty($record['updated_by'])) {
                $updater = Database::getInstance()->prepare('SELECT real_name FROM users WHERE id = ?');
                $updater->execute(array($record['updated_by']));
                $updaterName = $updater->fetchColumn();
                if ($updaterName) echo ' / ' . e($updaterName);
            }
        ?></small>
        <?php endif; ?>
    </div>
    <div class="d-flex gap-1 flex-wrap">
        <?php if ($record['status'] === '待確認'): ?>
        <a href="/stock_ins.php?action=confirm&id=<?= $record['id'] ?>" class="btn btn-sm" style="background:#2e7d32;color:#fff" onclick="return confirm('確認入庫？確認後將更新庫存數量。')">確認入庫</a>
        <?php endif; ?>
        <?php
        // ADMIN_TOOL_BLOCK_START
        $__siAdmin = Auth::user();
        $__siIsAdmin = $__siAdmin && $__siAdmin['role'] === 'boss';
        ?>
        <?php if ($__siIsAdmin): ?>
        <button type="button" class="btn btn-sm" style="background:#9c27b0;color:#fff" onclick="siAdminOpenEdit()">🔧 管理者改廠商/客戶</button>
        <button type="button" class="btn btn-sm" style="background:#c62828;color:#fff" onclick="siAdminConfirmDelete()">🗑 管理者刪除整張單</button>
        <?php endif; ?>
        <!-- ADMIN_TOOL_BLOCK_END -->
        <?= back_button('/stock_ins.php') ?>
    </div>
</div>

<?php /* ADMIN_TOOL_BLOCK_START */ if ($__siIsAdmin): ?>
<form id="siAdminDeleteForm" method="POST" action="/stock_ins.php?action=admin_delete" style="display:none">
    <input type="hidden" name="csrf_token" value="<?= e(Session::getCsrfToken()) ?>">
    <input type="hidden" name="id" value="<?= (int)$record['id'] ?>">
</form>

<div id="siAdminEditModal" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,.5);z-index:10000;align-items:center;justify-content:center">
    <div style="background:#fff;border-radius:8px;padding:20px;max-width:500px;width:90%">
        <h3 style="margin-top:0">🔧 管理者：修改廠商/客戶</h3>
        <form method="POST" action="/stock_ins.php?action=admin_edit_basic">
            <input type="hidden" name="csrf_token" value="<?= e(Session::getCsrfToken()) ?>">
            <input type="hidden" name="id" value="<?= (int)$record['id'] ?>">
            <div style="margin-bottom:12px;position:relative">
                <label style="font-size:.85rem;font-weight:600">廠商</label>
                <input type="text" name="vendor_name" id="siAdminVendor" autocomplete="off" class="form-control" value="<?= e(!empty($record['vendor_name']) ? $record['vendor_name'] : '') ?>" oninput="siAdminSearchVendor(this)">
                <div id="siAdminVendorDD" style="display:none;position:absolute;top:100%;left:0;right:0;background:#fff;border:1px solid #ddd;border-radius:6px;max-height:200px;overflow-y:auto;z-index:10001;box-shadow:0 4px 12px rgba(0,0,0,.15)"></div>
            </div>
            <div style="margin-bottom:12px">
                <label style="font-size:.85rem;font-weight:600">客戶</label>
                <input type="text" name="customer_name" class="form-control" value="<?= e(!empty($record['customer_name']) ? $record['customer_name'] : '') ?>">
            </div>
            <p style="font-size:.78rem;color:#888;margin:0 0 12px 0">客戶為純文字（入庫單未連結 customer_id），廠商可從廠商管理選擇</p>
            <div style="display:flex;gap:8px;justify-content:flex-end">
                <button type="button" class="btn btn-outline" onclick="siAdminCloseEdit()">取消</button>
                <button type="submit" class="btn btn-primary">儲存</button>
            </div>
        </form>
    </div>
</div>

<script>
function siAdminConfirmDelete() {
    if (confirm('確定要刪除此入庫單？\n\n注意：此操作無法復原。\n如有下游引用會被防呆擋下。')) {
        document.getElementById('siAdminDeleteForm').submit();
    }
}
function siAdminOpenEdit() { document.getElementById('siAdminEditModal').style.display = 'flex'; }
function siAdminCloseEdit() { document.getElementById('siAdminEditModal').style.display = 'none'; }
var siAdminTimer = null;
function siAdminSearchVendor(inp) {
    clearTimeout(siAdminTimer);
    var q = inp.value.trim();
    var dd = document.getElementById('siAdminVendorDD');
    if (q.length < 1) { dd.style.display = 'none'; return; }
    siAdminTimer = setTimeout(function() {
        var xhr = new XMLHttpRequest();
        xhr.open('GET', '/payments_out.php?action=ajax_vendor_search&q=' + encodeURIComponent(q));
        xhr.onload = function() {
            try { var list = JSON.parse(xhr.responseText); } catch(e) { return; }
            if (!list.length) { dd.innerHTML = '<div style="padding:8px;color:#999">無符合廠商</div>'; dd.style.display = 'block'; return; }
            var html = '';
            for (var i = 0; i < list.length; i++) {
                html += '<div style="padding:8px 12px;cursor:pointer;border-bottom:1px solid #eee" '
                    + 'data-name="' + (list[i].name||'').replace(/"/g,'&quot;') + '" '
                    + 'onmouseover="this.style.background=\'#f0f7ff\'" onmouseout="this.style.background=\'\'">'
                    + '<div style="font-weight:600">' + (list[i].name||'') + '</div>'
                    + '<div style="font-size:.75rem;color:#888">' + (list[i].vendor_code ? list[i].vendor_code : '') + '</div></div>';
            }
            dd.innerHTML = html;
            dd.style.display = 'block';
            dd.querySelectorAll('div[data-name]').forEach(function(it) {
                it.addEventListener('click', function() {
                    document.getElementById('siAdminVendor').value = this.getAttribute('data-name');
                    dd.style.display = 'none';
                });
            });
        };
        xhr.send();
    }, 250);
}
document.addEventListener('click', function(e) {
    var dd = document.getElementById('siAdminVendorDD');
    var inp = document.getElementById('siAdminVendor');
    if (dd && !dd.contains(e.target) && e.target !== inp) dd.style.display = 'none';
});
</script>
<?php endif; /* ADMIN_TOOL_BLOCK_END */ ?>

<div class="card">
    <div class="card-header">基本資訊</div>
    <div class="form-row">
        <div class="form-group">
            <label>入庫單號</label>
            <div class="form-value"><?= e($record['si_number']) ?></div>
        </div>
        <div class="form-group">
            <label>入庫日期</label>
            <div class="form-value"><?= e(!empty($record['si_date']) ? $record['si_date'] : '-') ?></div>
        </div>
        <div class="form-group">
            <label>狀態</label>
            <div class="form-value"><?= siViewStatusBadge($record['status']) ?></div>
        </div>
    </div>
    <div class="form-row">
        <div class="form-group">
            <label>來源類型</label>
            <div class="form-value"><?= e(StockModel::sourceTypeLabel(!empty($record['source_type']) ? $record['source_type'] : '')) ?></div>
        </div>
        <div class="form-group">
            <label>來源單號</label>
            <div class="form-value">
                <?php
                $srcNum = !empty($record['source_number']) ? $record['source_number'] : '';
                $srcLinked = false;
                if ($srcNum) {
                    // S/D 開頭 → 出庫單
                    if (strpos($srcNum, 'S/D-') === 0) {
                        $srcRow = Database::getInstance()->prepare("SELECT id FROM stock_outs WHERE so_number = ?")->execute(array($srcNum));
                        $srcRow = Database::getInstance()->prepare("SELECT id FROM stock_outs WHERE so_number = ?");
                        $srcRow->execute(array($srcNum));
                        $srcId = $srcRow->fetchColumn();
                        if ($srcId) { echo '<a href="/stock_outs.php?action=view&id=' . $srcId . '">' . e($srcNum) . '</a>'; $srcLinked = true; }
                    }
                    // GR 開頭 → 進貨單
                    elseif (strpos($srcNum, 'GR-') === 0) {
                        $srcRow = Database::getInstance()->prepare("SELECT id FROM goods_receipts WHERE gr_number = ?");
                        $srcRow->execute(array($srcNum));
                        $srcId = $srcRow->fetchColumn();
                        if ($srcId) { echo '<a href="/goods_receipts.php?action=view&id=' . $srcId . '">' . e($srcNum) . '</a>'; $srcLinked = true; }
                    }
                    if (!$srcLinked) echo e($srcNum);
                } else {
                    echo '-';
                }
                ?>
            </div>
        </div>
        <div class="form-group">
            <label>分公司</label>
            <div class="form-value"><?= e(!empty($record['branch_name']) ? $record['branch_name'] : '-') ?></div>
        </div>
        <div class="form-group">
            <label>倉庫</label>
            <div class="form-value"><?= e(!empty($record['warehouse_name']) ? $record['warehouse_name'] : '-') ?></div>
        </div>
    </div>
    <div class="form-row">
        <div class="form-group">
            <label>客戶名稱</label>
            <div class="form-value"><?= e(!empty($record['customer_name']) ? $record['customer_name'] : '-') ?></div>
        </div>
        <div class="form-group">
            <label>廠商名稱</label>
            <div class="form-value"><?= e(!empty($record['vendor_name']) ? $record['vendor_name'] : '-') ?></div>
        </div>
    </div>
    <div class="form-row">
        <div class="form-group">
            <label>建立者</label>
            <div class="form-value"><?= e(!empty($record['created_by_name']) ? $record['created_by_name'] : '-') ?></div>
        </div>
        <div class="form-group">
            <label>建立時間</label>
            <div class="form-value"><?= e(!empty($record['created_at']) ? $record['created_at'] : '-') ?></div>
        </div>
        <?php if ($record['status'] === '已確認'): ?>
        <div class="form-group">
            <label>確認者</label>
            <div class="form-value"><?= e(!empty($record['confirmed_by_name']) ? $record['confirmed_by_name'] : '-') ?> (<?= e(!empty($record['confirmed_at']) ? $record['confirmed_at'] : '') ?>)</div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- 明細 -->
<div class="card">
    <div class="card-header">入庫明細</div>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th style="width:40px">#</th>
                    <th>型號</th>
                    <th>品名</th>
                    <th>規格</th>
                    <th>單位</th>
                    <th class="text-right">數量</th>
                    <th class="text-right">單價</th>
                </tr>
            </thead>
            <tbody>
                <?php $totalQty = 0; ?>
                <?php foreach ($items as $idx => $item): ?>
                <tr>
                    <td><?= $idx + 1 ?></td>
                    <td><?= e(!empty($item['model']) ? $item['model'] : '-') ?></td>
                    <td><?= e(!empty($item['product_name']) ? $item['product_name'] : '-') ?></td>
                    <td><?= e(!empty($item['spec']) ? $item['spec'] : '') ?></td>
                    <td><?= e(!empty($item['unit']) ? $item['unit'] : '') ?></td>
                    <td class="text-right"><?= number_format(!empty($item['quantity']) ? $item['quantity'] : 0) ?></td>
                    <td class="text-right">$<?= number_format(!empty($item['unit_price']) ? $item['unit_price'] : 0) ?></td>
                </tr>
                <?php $totalQty += (!empty($item['quantity']) ? $item['quantity'] : 0); ?>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr style="font-weight:bold">
                    <td colspan="5" class="text-right">合計</td>
                    <td class="text-right"><?= number_format($totalQty) ?></td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<?php if (!empty($record['note'])): ?>
<div class="card">
    <div class="card-header">備註</div>
    <p><?= nl2br(e($record['note'])) ?></p>
</div>
<?php endif; ?>

<style>
.form-row { display: flex; flex-wrap: wrap; gap: 12px; }
.form-row .form-group { flex: 1; min-width: 150px; }
.form-value { padding: 6px 0; font-weight: 500; }
.badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: .75rem; font-weight: 500; }
.badge-orange { background: #fff3e0; color: #e65100; }
.badge-green { background: #e8f5e9; color: #2e7d32; }
.badge-gray { background: #f5f5f5; color: #757575; }
</style>
