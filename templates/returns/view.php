<?php
function viewReturnStatusBadge($status) {
    $map = array(
        'draft'     => 'orange',
        'confirmed' => 'green',
        'cancelled' => 'gray',
    );
    $color = !empty($map[$status]) ? $map[$status] : 'gray';
    return '<span class="badge badge-' . $color . '">' . e(ReturnModel::statusLabel($status)) . '</span>';
}
function viewReturnTypeBadge($type) {
    $map = array(
        'customer_return' => 'blue',
        'vendor_return'   => 'purple',
    );
    $color = !empty($map[$type]) ? $map[$type] : 'gray';
    return '<span class="badge badge-' . $color . '">' . e(ReturnModel::returnTypeLabel($type)) . '</span>';
}
?>

<div class="d-flex justify-between align-center flex-wrap gap-1 mb-2">
    <div>
        <h2 style="margin-bottom:2px">退貨單 <?= e($record['return_number']) ?></h2>
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
        <?php if ($record['status'] === 'draft'): ?>
        <a href="/returns.php?action=confirm&id=<?= $record['id'] ?>" class="btn btn-success btn-sm" onclick="return confirm('確認此退貨單？確認後將更新庫存且無法再編輯。')">確認退貨</a>
        <a href="/returns.php?action=edit&id=<?= $record['id'] ?>" class="btn btn-primary btn-sm">編輯</a>
        <a href="/returns.php?action=delete&id=<?= $record['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('確定要刪除此退貨單？')">刪除</a>
        <?php endif; ?>
        <?php
        // ADMIN_TOOL_BLOCK_START
        $__rtAdmin = Auth::user();
        $__rtIsAdmin = $__rtAdmin && $__rtAdmin['role'] === 'boss';
        ?>
        <?php if ($__rtIsAdmin): ?>
        <button type="button" class="btn btn-sm" style="background:#9c27b0;color:#fff" onclick="rtAdminOpenEdit()">🔧 管理者改廠商</button>
        <button type="button" class="btn btn-sm" style="background:#c62828;color:#fff" onclick="rtAdminConfirmDelete()">🗑 管理者刪除整張單</button>
        <?php endif; ?>
        <!-- ADMIN_TOOL_BLOCK_END -->
        <?= back_button('/returns.php') ?>
    </div>
</div>

<?php /* ADMIN_TOOL_BLOCK_START */ if ($__rtIsAdmin): ?>
<form id="rtAdminDeleteForm" method="POST" action="/returns.php?action=admin_delete" style="display:none">
    <input type="hidden" name="csrf_token" value="<?= e(Session::getCsrfToken()) ?>">
    <input type="hidden" name="id" value="<?= (int)$record['id'] ?>">
</form>

<div id="rtAdminEditModal" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,.5);z-index:10000;align-items:center;justify-content:center">
    <div style="background:#fff;border-radius:8px;padding:20px;max-width:480px;width:90%">
        <h3 style="margin-top:0">🔧 管理者：修改廠商</h3>
        <form method="POST" action="/returns.php?action=admin_edit_basic" onsubmit="return rtAdminValidateBeforeSubmit()">
            <input type="hidden" name="csrf_token" value="<?= e(Session::getCsrfToken()) ?>">
            <input type="hidden" name="id" value="<?= (int)$record['id'] ?>">
            <input type="hidden" name="vendor_id" id="rtAdminVendorId" value="0">
            <div style="margin-bottom:12px;position:relative">
                <label style="font-size:.85rem;font-weight:600">廠商（必須從廠商管理選擇）<span style="color:#c62828">*</span></label>
                <input type="text" name="vendor_name" id="rtAdminVendor" autocomplete="off" class="form-control" value="<?= e(!empty($record['vendor_name']) ? $record['vendor_name'] : '') ?>" oninput="rtAdminSearchVendor(this)" required>
                <div id="rtAdminVendorDD" style="display:none;position:absolute;top:100%;left:0;right:0;background:#fff;border:1px solid #ddd;border-radius:6px;max-height:200px;overflow-y:auto;z-index:10001;box-shadow:0 4px 12px rgba(0,0,0,.15)"></div>
            </div>
            <p style="font-size:.78rem;color:#888;margin:0 0 12px 0">⚠ 廠商必須從下拉清單點選；找不到請先到 <a href="/vendors.php" target="_blank">廠商管理</a> 建立</p>
            <div style="display:flex;gap:8px;justify-content:flex-end">
                <button type="button" class="btn btn-outline" onclick="rtAdminCloseEdit()">取消</button>
                <button type="submit" class="btn btn-primary">儲存</button>
            </div>
        </form>
    </div>
</div>

<script>
function rtAdminConfirmDelete() {
    if (confirm('確定要刪除此退貨單？\n\n注意：此操作無法復原。\n如有下游引用會被防呆擋下。')) {
        document.getElementById('rtAdminDeleteForm').submit();
    }
}
function rtAdminOpenEdit() { document.getElementById('rtAdminEditModal').style.display = 'flex'; }
function rtAdminCloseEdit() { document.getElementById('rtAdminEditModal').style.display = 'none'; }
function rtAdminValidateBeforeSubmit() {
    var vid = document.getElementById('rtAdminVendorId').value;
    if (!vid || parseInt(vid) <= 0) {
        alert('請從下拉清單選擇廠商，不可手動輸入');
        return false;
    }
    return true;
}
var rtAdminTimer = null;
function rtAdminSearchVendor(inp) {
    document.getElementById('rtAdminVendorId').value = '';
    clearTimeout(rtAdminTimer);
    var q = inp.value.trim();
    var dd = document.getElementById('rtAdminVendorDD');
    if (q.length < 1) { dd.style.display = 'none'; return; }
    rtAdminTimer = setTimeout(function() {
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
                    document.getElementById('rtAdminVendor').value = this.getAttribute('data-name');
                    document.getElementById('rtAdminVendorId').value = this.getAttribute('data-id');
                    dd.style.display = 'none';
                });
            });
        };
        xhr.send();
    }, 250);
}
document.addEventListener('click', function(e) {
    var dd = document.getElementById('rtAdminVendorDD');
    var inp = document.getElementById('rtAdminVendor');
    if (dd && !dd.contains(e.target) && e.target !== inp) dd.style.display = 'none';
});
</script>
<?php endif; /* ADMIN_TOOL_BLOCK_END */ ?>

<div class="card">
    <div class="card-header">基本資訊</div>
    <table class="table detail-table">
        <tr>
            <th style="width:120px">退貨單號</th>
            <td><?= e($record['return_number']) ?></td>
            <th style="width:120px">狀態</th>
            <td><?= viewReturnStatusBadge($record['status']) ?></td>
        </tr>
        <tr>
            <th>退貨日期</th>
            <td><?= e(!empty($record['return_date']) ? $record['return_date'] : '-') ?></td>
            <th>退貨類型</th>
            <td><?= viewReturnTypeBadge($record['return_type']) ?></td>
        </tr>
        <tr>
            <th>倉庫</th>
            <td><?= e(!empty($record['warehouse_name']) ? $record['warehouse_name'] : '-') ?></td>
            <th>合計金額</th>
            <td><strong>$<?= number_format(!empty($record['total_amount']) ? $record['total_amount'] : 0) ?></strong></td>
        </tr>
        <?php if ($record['return_type'] === 'customer_return'): ?>
        <tr>
            <th>客戶名稱</th>
            <td colspan="3"><?= e(!empty($record['customer_name']) ? $record['customer_name'] : '-') ?></td>
        </tr>
        <?php else: ?>
        <tr>
            <th>廠商名稱</th>
            <td colspan="3"><?= e(!empty($record['vendor_name']) ? $record['vendor_name'] : '-') ?></td>
        </tr>
        <?php endif; ?>
        <?php if (!empty($record['reference_type'])): ?>
        <tr>
            <th>來源單據</th>
            <td colspan="3"><?= e($record['reference_type']) ?> #<?= e(!empty($record['reference_id']) ? $record['reference_id'] : '') ?></td>
        </tr>
        <?php endif; ?>
        <?php if (!empty($record['reason'])): ?>
        <tr>
            <th>退貨原因</th>
            <td colspan="3"><?= e($record['reason']) ?></td>
        </tr>
        <?php endif; ?>
        <?php if (!empty($record['note'])): ?>
        <tr>
            <th>備註</th>
            <td colspan="3"><?= nl2br(e($record['note'])) ?></td>
        </tr>
        <?php endif; ?>
        <tr>
            <th>建立人</th>
            <td><?= e(!empty($record['created_by_name']) ? $record['created_by_name'] : '-') ?></td>
            <th>建立時間</th>
            <td><?= e(!empty($record['created_at']) ? $record['created_at'] : '-') ?></td>
        </tr>
    </table>
</div>

<!-- 退貨明細 -->
<div class="card">
    <div class="card-header">退貨明細</div>
    <?php if (empty($record['items'])): ?>
        <p class="text-muted text-center mt-2">無退貨品項</p>
    <?php else: ?>
    <div class="staff-cards show-mobile">
        <?php foreach ($record['items'] as $idx => $item): ?>
        <div class="staff-card">
            <div><strong>#<?= $idx + 1 ?></strong> <?= e(!empty($item['product_name']) ? $item['product_name'] : '-') ?></div>
            <div class="staff-card-meta">
                <span>數量: <?= (int)$item['quantity'] ?></span>
                <span>未稅單價: $<?= number_format(!empty($item['unit_price']) ? $item['unit_price'] : 0) ?></span>
                <span>稅額: $<?= number_format(!empty($item['tax_amount']) ? $item['tax_amount'] : 0) ?></span>
                <span>小計: $<?= number_format(!empty($item['amount']) ? $item['amount'] : 0) ?></span>
            </div>
            <?php if (!empty($item['reason'])): ?>
            <div class="staff-card-meta"><span>原因: <?= e($item['reason']) ?></span></div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="table-responsive hide-mobile">
        <table class="table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>品名</th>
                    <th>型號</th>
                    <th class="text-right">數量</th>
                    <th class="text-right">未稅單價</th>
                    <th class="text-right">稅額</th>
                    <th class="text-right">小計</th>
                    <th>原因</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($record['items'] as $idx => $item): ?>
                <tr>
                    <td><?= $idx + 1 ?></td>
                    <td><?= e(!empty($item['product_name']) ? $item['product_name'] : '-') ?></td>
                    <td><?= e(!empty($item['model_number']) ? $item['model_number'] : '-') ?></td>
                    <td class="text-right"><?= (int)$item['quantity'] ?></td>
                    <td class="text-right">$<?= number_format(!empty($item['unit_price']) ? $item['unit_price'] : 0) ?></td>
                    <td class="text-right">$<?= number_format(!empty($item['tax_amount']) ? $item['tax_amount'] : 0) ?></td>
                    <td class="text-right">$<?= number_format(!empty($item['amount']) ? $item['amount'] : 0) ?></td>
                    <td><?= e(!empty($item['reason']) ? $item['reason'] : '-') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="6" class="text-right"><strong>合計</strong></td>
                    <td class="text-right"><strong>$<?= number_format(!empty($record['total_amount']) ? $record['total_amount'] : 0) ?></strong></td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    </div>
    <?php endif; ?>
</div>

<style>
.detail-table th { background: var(--gray-50); font-weight: 600; white-space: nowrap; }
.detail-table td, .detail-table th { padding: 8px 12px; border-bottom: 1px solid var(--gray-200); }
.badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: .75rem; font-weight: 500; }
.badge-orange { background: #fff3e0; color: #e65100; }
.badge-blue { background: #e3f2fd; color: #1565c0; }
.badge-green { background: #e8f5e9; color: #2e7d32; }
.badge-gray { background: #f5f5f5; color: #757575; }
.badge-purple { background: #f3e5f5; color: #7b1fa2; }
.btn-success { background: #2e7d32; color: #fff; border: none; }
.btn-success:hover { background: #1b5e20; }
.staff-cards { display: flex; flex-direction: column; gap: 8px; }
.staff-card { border: 1px solid var(--gray-200); border-radius: var(--radius); padding: 12px; }
.staff-card-meta { font-size: .8rem; color: var(--gray-500); display: flex; gap: 8px; margin-top: 4px; }
.show-mobile { display: flex; }
.hide-mobile { display: none; }
@media (min-width: 768px) { .show-mobile { display: none !important; } .hide-mobile { display: block !important; } }
</style>
