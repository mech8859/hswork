<?php
function grViewStatusBadge($status) {
    $color = GoodsReceiptModel::statusBadgeColor($status);
    return '<span class="badge badge-' . $color . '">' . e($status) . '</span>';
}
?>

<div class="d-flex justify-between align-center flex-wrap gap-1 mb-2">
    <div>
        <h2 style="margin-bottom:2px">進貨單 <?= e($record['gr_number']) ?> <?= grViewStatusBadge($record['status']) ?></h2>
        <?php if (!empty($record['updated_at'])): ?>
        <small class="text-muted">最後修改 <?= e($record['updated_at']) ?><?php
            if (!empty($record['updated_by'])) {
                $updater = Database::getInstance()->prepare('SELECT real_name FROM users WHERE id = ?');
                $updater->execute(array($record['updated_by']));
                $un = $updater->fetchColumn();
                if ($un) echo ' / ' . e($un);
            }
        ?></small>
        <?php endif; ?>
    </div>
    <div class="d-flex gap-1 flex-wrap">
        <?php if ($record['status'] === '草稿' || $record['status'] === '待確認'): ?>
        <a href="/goods_receipts.php?action=edit&id=<?= $record['id'] ?>" class="btn btn-primary btn-sm">編輯</a>
        <?php endif; ?>
        <?php if ($record['status'] !== '已確認' && $record['status'] !== '已取消'): ?>
        <a href="/goods_receipts.php?action=confirm&id=<?= $record['id'] ?>" class="btn btn-sm" style="background:#2e7d32;color:#fff" onclick="return confirm('確認進貨？確認後將自動建立入庫單並更新庫存。')">確認進貨</a>
        <?php endif; ?>
        <?php
        // ADMIN_TOOL_BLOCK_START
        $__grAdmin = Auth::user();
        $__grIsAdmin = $__grAdmin && $__grAdmin['role'] === 'boss';
        ?>
        <?php if ($__grIsAdmin): ?>
        <button type="button" class="btn btn-sm" style="background:#9c27b0;color:#fff" onclick="grAdminOpenEdit()">🔧 管理者改廠商</button>
        <button type="button" class="btn btn-sm" style="background:#c62828;color:#fff" onclick="grAdminConfirmDelete()">🗑 管理者刪除整張單</button>
        <?php endif; ?>
        <!-- ADMIN_TOOL_BLOCK_END -->
        <?= back_button('/goods_receipts.php') ?>
    </div>
</div>

<?php /* ADMIN_TOOL_BLOCK_START */ if ($__grIsAdmin): ?>
<form id="grAdminDeleteForm" method="POST" action="/goods_receipts.php?action=admin_delete" style="display:none">
    <input type="hidden" name="csrf_token" value="<?= e(Session::getCsrfToken()) ?>">
    <input type="hidden" name="id" value="<?= (int)$record['id'] ?>">
</form>

<div id="grAdminEditModal" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,.5);z-index:10000;align-items:center;justify-content:center">
    <div style="background:#fff;border-radius:8px;padding:20px;max-width:480px;width:90%">
        <h3 style="margin-top:0">🔧 管理者：修改廠商</h3>
        <form method="POST" action="/goods_receipts.php?action=admin_edit_basic" onsubmit="return grAdminValidateBeforeSubmit()">
            <input type="hidden" name="csrf_token" value="<?= e(Session::getCsrfToken()) ?>">
            <input type="hidden" name="id" value="<?= (int)$record['id'] ?>">
            <input type="hidden" name="vendor_id" id="grAdminVendorId" value="<?= (int)(!empty($record['vendor_id']) ? $record['vendor_id'] : 0) ?>">
            <div style="margin-bottom:12px;position:relative">
                <label style="font-size:.85rem;font-weight:600">廠商（必須從廠商管理選擇）<span style="color:#c62828">*</span></label>
                <input type="text" name="vendor_name" id="grAdminVendor" autocomplete="off" class="form-control" value="<?= e(!empty($record['vendor_name']) ? $record['vendor_name'] : '') ?>" oninput="grAdminSearchVendor(this)" required>
                <div id="grAdminVendorDD" style="display:none;position:absolute;top:100%;left:0;right:0;background:#fff;border:1px solid #ddd;border-radius:6px;max-height:200px;overflow-y:auto;z-index:10001;box-shadow:0 4px 12px rgba(0,0,0,.15)"></div>
            </div>
            <p style="font-size:.78rem;color:#888;margin:0 0 12px 0">⚠ 廠商必須從下拉清單點選；找不到請先到 <a href="/vendors.php" target="_blank">廠商管理</a> 建立</p>
            <div style="display:flex;gap:8px;justify-content:flex-end">
                <button type="button" class="btn btn-outline" onclick="grAdminCloseEdit()">取消</button>
                <button type="submit" class="btn btn-primary">儲存</button>
            </div>
        </form>
    </div>
</div>

<script>
function grAdminConfirmDelete() {
    if (confirm('確定要刪除此進貨單？\n\n注意：此操作無法復原。\n如有下游引用會被防呆擋下。')) {
        document.getElementById('grAdminDeleteForm').submit();
    }
}
function grAdminOpenEdit() { document.getElementById('grAdminEditModal').style.display = 'flex'; }
function grAdminCloseEdit() { document.getElementById('grAdminEditModal').style.display = 'none'; }
function grAdminValidateBeforeSubmit() {
    var vid = document.getElementById('grAdminVendorId').value;
    if (!vid || parseInt(vid) <= 0) {
        alert('請從下拉清單選擇廠商，不可手動輸入');
        return false;
    }
    return true;
}
var grAdminTimer = null;
function grAdminSearchVendor(inp) {
    document.getElementById('grAdminVendorId').value = '';  // 改字立刻清掉 id
    clearTimeout(grAdminTimer);
    var q = inp.value.trim();
    var dd = document.getElementById('grAdminVendorDD');
    if (q.length < 1) { dd.style.display = 'none'; return; }
    grAdminTimer = setTimeout(function() {
        var xhr = new XMLHttpRequest();
        xhr.open('GET', '/payments_out.php?action=ajax_vendor_search&q=' + encodeURIComponent(q));
        xhr.onload = function() {
            try { var list = JSON.parse(xhr.responseText); } catch(e) { return; }
            if (!list.length) {
                dd.innerHTML = '<div style="padding:8px;color:#c62828">無符合廠商，請先到 <a href="/vendors.php" target="_blank">廠商管理</a> 建立</div>';
                dd.style.display = 'block';
                return;
            }
            var html = '';
            for (var i = 0; i < list.length; i++) {
                html += '<div style="padding:8px 12px;cursor:pointer;border-bottom:1px solid #eee" '
                    + 'data-id="' + (list[i].id||'') + '" data-name="' + (list[i].name||'').replace(/"/g,'&quot;') + '" '
                    + 'onmouseover="this.style.background=\'#f0f7ff\'" onmouseout="this.style.background=\'\'">'
                    + '<div style="font-weight:600">' + (list[i].name||'') + '</div>'
                    + '<div style="font-size:.75rem;color:#888">' + (list[i].vendor_code ? list[i].vendor_code : '') + '</div></div>';
            }
            dd.innerHTML = html;
            dd.style.display = 'block';
            dd.querySelectorAll('div[data-id]').forEach(function(it) {
                it.addEventListener('click', function() {
                    document.getElementById('grAdminVendor').value = this.getAttribute('data-name');
                    document.getElementById('grAdminVendorId').value = this.getAttribute('data-id');
                    dd.style.display = 'none';
                });
            });
        };
        xhr.send();
    }, 250);
}
document.addEventListener('click', function(e) {
    var dd = document.getElementById('grAdminVendorDD');
    var inp = document.getElementById('grAdminVendor');
    if (dd && !dd.contains(e.target) && e.target !== inp) dd.style.display = 'none';
});
</script>
<?php endif; /* ADMIN_TOOL_BLOCK_END */ ?>

<div class="card">
    <div class="card-header">基本資訊</div>
    <div class="form-row">
        <div class="form-group">
            <label>進貨單號</label>
            <div class="form-value"><?= e($record['gr_number']) ?></div>
        </div>
        <div class="form-group">
            <label>進貨日期</label>
            <div class="form-value"><?= e(!empty($record['gr_date']) ? $record['gr_date'] : '-') ?></div>
        </div>
        <div class="form-group">
            <label>狀態</label>
            <div class="form-value"><?= grViewStatusBadge($record['status']) ?></div>
        </div>
    </div>
    <div class="form-row">
        <div class="form-group">
            <label>關聯採購單</label>
            <div class="form-value">
                <?php if (!empty($record['po_number'])): ?>
                <a href="/purchase_orders.php?action=edit&id=<?= $record['po_id'] ?>"><?= e($record['po_number']) ?></a>
                <?php else: ?>
                -
                <?php endif; ?>
            </div>
        </div>
        <div class="form-group">
            <label>廠商</label>
            <div class="form-value"><?= e(!empty($record['vendor_name']) ? $record['vendor_name'] : '-') ?></div>
        </div>
        <div class="form-group">
            <label>倉庫</label>
            <div class="form-value"><?= e(!empty($record['warehouse_name']) ? $record['warehouse_name'] : '-') ?></div>
        </div>
    </div>
    <div class="form-row">
        <div class="form-group">
            <label>收貨人</label>
            <div class="form-value"><?= e(!empty($record['receiver_name']) ? $record['receiver_name'] : '-') ?></div>
        </div>
        <div class="form-group">
            <label>建立者</label>
            <div class="form-value"><?= e(!empty($record['created_by_name']) ? $record['created_by_name'] : '-') ?></div>
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
    <div class="card-header">進貨明細</div>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th style="width:40px">#</th>
                    <th>型號</th>
                    <th>品名</th>
                    <th>規格</th>
                    <th>單位</th>
                    <th class="text-right">採購數量</th>
                    <th class="text-right">收貨數量</th>
                    <th class="text-right">單價</th>
                    <th class="text-right">金額</th>
                </tr>
            </thead>
            <tbody>
                <?php $totalQty = 0; $totalAmt = 0; ?>
                <?php foreach ($items as $idx => $item): ?>
                <tr>
                    <td><?= $idx + 1 ?></td>
                    <td><?= e(!empty($item['model']) ? $item['model'] : '-') ?></td>
                    <td><?= e(!empty($item['product_name']) ? $item['product_name'] : '-') ?></td>
                    <td><?= e(!empty($item['spec']) ? $item['spec'] : '') ?></td>
                    <td><?= e(!empty($item['unit']) ? $item['unit'] : '') ?></td>
                    <td class="text-right"><?= number_format(!empty($item['po_qty']) ? $item['po_qty'] : 0) ?></td>
                    <td class="text-right"><?= number_format(!empty($item['received_qty']) ? $item['received_qty'] : 0) ?></td>
                    <td class="text-right">$<?= number_format(!empty($item['unit_price']) ? $item['unit_price'] : 0) ?></td>
                    <td class="text-right">$<?= number_format(!empty($item['amount']) ? $item['amount'] : 0) ?></td>
                </tr>
                <?php $totalQty += (!empty($item['received_qty']) ? $item['received_qty'] : 0); ?>
                <?php $totalAmt += (!empty($item['amount']) ? $item['amount'] : 0); ?>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr style="font-weight:bold">
                    <td colspan="6" class="text-right">合計</td>
                    <td class="text-right"><?= number_format($totalQty) ?></td>
                    <td></td>
                    <td class="text-right">$<?= number_format($totalAmt) ?></td>
                </tr>
                <?php $taxAmt = round($totalAmt * 0.05); ?>
                <tr style="font-size:.85rem;color:var(--gray-500)">
                    <td colspan="8" class="text-right">未稅金額</td>
                    <td class="text-right">$<?= number_format($totalAmt) ?></td>
                </tr>
                <tr style="font-size:.85rem;color:var(--gray-500)">
                    <td colspan="8" class="text-right">稅額 (5%)</td>
                    <td class="text-right">$<?= number_format($taxAmt) ?></td>
                </tr>
                <tr style="font-weight:bold;font-size:1.05rem;color:var(--primary)">
                    <td colspan="8" class="text-right">總金額（含稅）</td>
                    <td class="text-right">$<?= number_format($totalAmt + $taxAmt) ?></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<!-- 付款資訊 -->
<div class="card">
    <div class="card-header">付款資訊</div>
    <div class="form-row">
        <div class="form-group">
            <label>已付金額</label>
            <div class="form-value" style="<?= !empty($record['paid_amount']) ? 'color:#2e7d32;font-weight:700' : '' ?>"><?= !empty($record['paid_amount']) ? '$' . number_format($record['paid_amount']) : '-' ?></div>
        </div>
        <div class="form-group">
            <label>付款日</label>
            <div class="form-value"><?= !empty($record['paid_date']) ? e($record['paid_date']) : '-' ?></div>
        </div>
        <div class="form-group">
            <label>付款單號</label>
            <div class="form-value">
                <?php if (!empty($record['payment_number'])):
                    // 查 payment_out id 以便連結
                    $_poStmt = Database::getInstance()->prepare("SELECT id FROM payments_out WHERE payment_number = ? LIMIT 1");
                    $_poStmt->execute(array($record['payment_number']));
                    $_poId = (int)$_poStmt->fetchColumn();
                ?>
                <?php if ($_poId > 0): ?>
                <a href="/payments_out.php?action=edit&id=<?= $_poId ?>" style="color:#1565c0;font-weight:600"><?= e($record['payment_number']) ?></a>
                <?php else: ?>
                <?= e($record['payment_number']) ?>
                <?php endif; ?>
                <?php else: ?>-<?php endif; ?>
            </div>
        </div>
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
