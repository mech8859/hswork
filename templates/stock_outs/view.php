<style>
@media print {
    .no-print, .item-check, #checkAll, .confirm-qty { display: none !important; }
    .print-hide-col { display: none !important; }
    body { font-size: 10px; margin: 0; padding: 0; }
    .card { box-shadow: none !important; border: 1px solid #ddd; padding: 4px !important; }
    .card-header { padding: 4px 8px !important; font-size: 12px; }
    .form-row { gap: 4px !important; margin-bottom: 4px !important; }
    .form-group { margin-bottom: 2px !important; }
    table { page-break-inside: auto; font-size: 10px; }
    table td, table th { padding: 3px 4px !important; }
    tr { page-break-inside: avoid; }
    thead { display: table-header-group; }
    h2 { font-size: 14px !important; margin: 4px 0 !important; }
    .sidebar, .top-nav { display: none !important; }
    .main-content { margin: 0 !important; padding: 0 !important; }
}
</style>
<?php
$canManage = Auth::hasPermission('inventory.manage');
$soNum = isset($record['stockout_number']) ? $record['stockout_number'] : (isset($record['so_number']) ? $record['so_number'] : '-');
$soDate = isset($record['stockout_date']) ? $record['stockout_date'] : (isset($record['so_date']) ? $record['so_date'] : '-');
$refType = isset($record['reference_type']) ? $record['reference_type'] : (isset($record['source_type']) ? $record['source_type'] : '');
$refId = isset($record['reference_id']) ? $record['reference_id'] : (isset($record['source_id']) ? $record['source_id'] : '');
$creatorName = isset($record['creator_name']) ? $record['creator_name'] : (isset($record['created_by_name']) ? $record['created_by_name'] : '-');
$confirmedName = isset($record['confirmed_by_name']) ? $record['confirmed_by_name'] : '-';
$isConfirmed = in_array($record['status'], array('已確認', 'confirmed'), true);
$isPending = ($record['status'] === 'pending' || $record['status'] === '待確認');
$isPartial = ($record['status'] === '部分出庫');
$isPreReserved = ($record['status'] === '已預扣');
$isReserved = ($record['status'] === '已備貨');
$items = isset($record['items']) ? $record['items'] : array();
// 新語意：還有剩餘 = shipped_qty < quantity
$hasUnshipped = false;
if (!empty($items)) {
    foreach ($items as $chkItem) {
        $itemNeed = isset($chkItem['quantity']) ? (float)$chkItem['quantity'] : 0;
        $itemShipped = isset($chkItem['shipped_qty']) ? (float)$chkItem['shipped_qty'] : 0;
        if ($itemShipped < $itemNeed) { $hasUnshipped = true; break; }
    }
}
$hasUnconfirmed = $hasUnshipped; // 相容舊變數名
$canConfirmItems = ($isPending || $isPartial || $isReserved || $isPreReserved) && $hasUnshipped;

// 編輯模式支援的狀態（待確認 / 已預扣 / 已備貨 / 部分出庫）
$canEdit = $canManage && in_array($record['status'], array('待確認', 'pending', '已預扣', '已備貨', '部分出庫'), true);
?>

<div class="d-flex justify-between align-center flex-wrap gap-1 mb-2">
    <div>
        <h2 style="margin-bottom:2px">出庫單 <?= e($soNum) ?></h2>
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
    <?php if (!empty($soReadonly)): ?>
    <div class="d-flex gap-1 flex-wrap">
        <span class="badge" style="background:#fff3e0;color:#e65100;padding:6px 12px;font-size:.85rem">唯讀檢視</span>
        <button type="button" class="btn btn-outline btn-sm" onclick="history.back()">← 返回</button>
    </div>
    <?php endif; ?>
    <div class="d-flex gap-1 flex-wrap" id="actionBtnBar"<?= !empty($soReadonly) ? ' style="display:none"' : '' ?>>
        <?php if ($canManage && $isPending && $hasUnconfirmed): ?>
        <a href="/stock_outs.php?action=reserve&id=<?= $record['id'] ?>&csrf_token=<?= e(Session::getCsrfToken()) ?>" class="btn btn-sm" style="background:#1565c0;color:#fff" onclick="return confirm('確認預扣庫存？將從可用庫存扣除。')">預扣庫存</a>
        <?php endif; ?>
        <?php if ($canManage && $isPreReserved): ?>
        <a href="/stock_outs.php?action=prepare&id=<?= $record['id'] ?>&csrf_token=<?= e(Session::getCsrfToken()) ?>" class="btn btn-sm" style="background:#7B1FA2;color:#fff" onclick="return confirm('確認備貨？預扣將轉為已備貨（不可取消）。')">確認備貨</a>
        <a href="/stock_outs.php?action=cancel_reserve&id=<?= $record['id'] ?>&csrf_token=<?= e(Session::getCsrfToken()) ?>" class="btn btn-outline btn-sm" style="color:#1565c0;border-color:#1565c0" onclick="return confirm('確認取消預扣？庫存將恢復為可用。')">取消預扣</a>
        <?php endif; ?>
        <?php if ($canManage && $canConfirmItems): ?>
        <button type="button" class="btn btn-sm" style="background:var(--success);color:#fff" onclick="confirmCheckedItems()">確認勾選出庫</button>
        <a href="/stock_outs.php?action=cancel&id=<?= $record['id'] ?>&csrf_token=<?= e(Session::getCsrfToken()) ?>" class="btn btn-outline btn-sm" style="color:var(--danger);border-color:var(--danger)" onclick="return confirm('確定取消此出庫單？已出庫品項紀錄將保留。')">取消出庫</a>
        <?php endif; ?>
        <?php if ($canEdit): ?>
        <button type="button" id="btnEnterEdit" class="btn btn-sm" style="background:#ff9800;color:#fff" onclick="enterEditMode()">✏ 編輯明細</button>
        <?php endif; ?>
        <?php if ($canManage && !$isPending && !empty($record['has_return_material'])): ?>
        <button type="button" class="btn btn-sm" style="background:#FF9800;color:#fff" onclick="submitReturnMaterial(<?= $record['id'] ?>)">餘料入庫</button>
        <?php endif; ?>
        <?php
        $allConfirmed = !$hasUnconfirmed && !empty($items);
        $isCancelled = ($record['status'] === '已取消');
        if ($canManage && $allConfirmed && !$isCancelled): ?>
        <button type="button" id="btnManualReturn" class="btn btn-sm" style="background:#7B1FA2;color:#fff" onclick="enterReturnMode()">手動餘料入庫</button>
        <?php endif; ?>
        <button type="button" class="btn btn-outline btn-sm no-print" onclick="window.print()">列印</button>
        <?php
        // ADMIN_TOOL_BLOCK_START - 測試期專用，完成後可整段移除
        $__adminUser = Auth::user();
        $__isAdmin = $__adminUser && $__adminUser['role'] === 'boss';
        ?>
        <?php if ($__isAdmin): ?>
        <button type="button" class="btn btn-sm no-print" style="background:#9c27b0;color:#fff" onclick="adminOpenEditCustomer()">🔧 管理者改客戶</button>
        <button type="button" class="btn btn-sm no-print" style="background:#c62828;color:#fff" onclick="adminConfirmDelete()">🗑 管理者刪除整張單</button>
        <?php endif; ?>
        <!-- ADMIN_TOOL_BLOCK_END -->
        <?= back_button('/stock_outs.php') ?>
    </div>
</div>

<?php /* ADMIN_TOOL_BLOCK_START */ if ($__isAdmin): ?>
<!-- 管理者：刪除表單 -->
<form id="adminDeleteForm" method="POST" action="/stock_outs.php?action=admin_delete" style="display:none">
    <input type="hidden" name="csrf_token" value="<?= e(Session::getCsrfToken()) ?>">
    <input type="hidden" name="id" value="<?= (int)$record['id'] ?>">
</form>

<!-- 管理者：改客戶 modal -->
<div id="adminEditCustomerModal" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,.5);z-index:10000;align-items:center;justify-content:center">
    <div style="background:#fff;border-radius:8px;padding:20px;max-width:480px;width:90%">
        <h3 style="margin-top:0">🔧 管理者：修改客戶</h3>
        <form method="POST" action="/stock_outs.php?action=admin_edit_basic">
            <input type="hidden" name="csrf_token" value="<?= e(Session::getCsrfToken()) ?>">
            <input type="hidden" name="id" value="<?= (int)$record['id'] ?>">
            <input type="hidden" name="customer_id" id="adminEditCustomerId" value="<?= (int)(!empty($record['customer_id']) ? $record['customer_id'] : 0) ?>">
            <div style="margin-bottom:12px;position:relative">
                <label style="font-size:.85rem;font-weight:600">客戶（從客戶管理選擇）</label>
                <input type="text" name="customer_name" id="adminEditCustomerName" autocomplete="off" class="form-control" value="<?= e(!empty($record['customer_name']) ? $record['customer_name'] : '') ?>" oninput="adminSearchCustomer(this)" required>
                <div id="adminCustomerDropdown" style="display:none;position:absolute;top:100%;left:0;right:0;background:#fff;border:1px solid #ddd;border-radius:6px;max-height:200px;overflow-y:auto;z-index:10001;box-shadow:0 4px 12px rgba(0,0,0,.15)"></div>
            </div>
            <div style="display:flex;gap:8px;justify-content:flex-end">
                <button type="button" class="btn btn-outline" onclick="adminCloseEditCustomer()">取消</button>
                <button type="submit" class="btn btn-primary">儲存</button>
            </div>
        </form>
    </div>
</div>

<script>
function adminConfirmDelete() {
    if (confirm('確定要刪除此出庫單？\n\n注意：此操作無法復原。\n如有下游引用會被防呆擋下。')) {
        document.getElementById('adminDeleteForm').submit();
    }
}
function adminOpenEditCustomer() { document.getElementById('adminEditCustomerModal').style.display = 'flex'; }
function adminCloseEditCustomer() { document.getElementById('adminEditCustomerModal').style.display = 'none'; }
var adminCustTimer = null;
function adminSearchCustomer(inp) {
    clearTimeout(adminCustTimer);
    var q = inp.value.trim();
    var dd = document.getElementById('adminCustomerDropdown');
    if (q.length < 1) { dd.style.display = 'none'; return; }
    adminCustTimer = setTimeout(function() {
        var xhr = new XMLHttpRequest();
        xhr.open('GET', '/stock_outs.php?action=ajax_search_customer&keyword=' + encodeURIComponent(q));
        xhr.onload = function() {
            try { var list = JSON.parse(xhr.responseText); } catch(e) { return; }
            if (!list.length) { dd.innerHTML = '<div style="padding:8px;color:#999">無符合客戶</div>'; dd.style.display = 'block'; return; }
            var html = '';
            for (var i = 0; i < list.length; i++) {
                html += '<div style="padding:8px 12px;cursor:pointer;border-bottom:1px solid #eee" '
                    + 'data-id="' + (list[i].id||'') + '" data-name="' + (list[i].name||'').replace(/"/g,'&quot;') + '" '
                    + 'onmouseover="this.style.background=\'#f0f7ff\'" onmouseout="this.style.background=\'\'">'
                    + '<div style="font-weight:600">' + (list[i].name||'') + '</div>'
                    + '<div style="font-size:.75rem;color:#888">' + (list[i].customer_no||'') + '</div></div>';
            }
            dd.innerHTML = html;
            dd.style.display = 'block';
            dd.querySelectorAll('div[data-id]').forEach(function(it) {
                it.addEventListener('click', function() {
                    document.getElementById('adminEditCustomerName').value = this.getAttribute('data-name');
                    document.getElementById('adminEditCustomerId').value = this.getAttribute('data-id');
                    dd.style.display = 'none';
                });
            });
        };
        xhr.send();
    }, 250);
}
document.addEventListener('click', function(e) {
    var dd = document.getElementById('adminCustomerDropdown');
    var inp = document.getElementById('adminEditCustomerName');
    if (dd && !dd.contains(e.target) && e.target !== inp) dd.style.display = 'none';
});
</script>
<?php endif; /* ADMIN_TOOL_BLOCK_END */ ?>

<!-- 基本資訊 -->
<div class="card">
    <div class="card-header d-flex justify-between align-center">
        <span>出庫單資訊</span>
        <span class="badge badge-<?= StockModel::statusBadge($record['status']) ?>"><?= e(StockModel::statusLabel($record['status'])) ?></span>
    </div>
    <div class="form-row">
        <div class="form-group">
            <label>出庫單號</label>
            <div class="form-static"><?= e($soNum) ?></div>
        </div>
        <div class="form-group">
            <label><?= $isConfirmed ? '出庫日期' : '預計出庫日' ?></label>
            <?php if (!$isConfirmed && $canManage): ?>
            <div class="d-flex align-center gap-1">
                <input type="date" id="soDateInput" class="form-control" value="<?= e($soDate) ?>" style="max-width:170px">
                <button type="button" id="soDateSaveBtn" class="btn btn-outline btn-sm" style="display:none;padding:2px 10px;font-size:.8rem" onclick="saveSoDate()">儲存</button>
            </div>
            <?php else: ?>
            <div class="form-static"><?= e($soDate) ?></div>
            <?php endif; ?>
        </div>
        <div class="form-group">
            <label>倉庫</label>
            <div class="form-static"><?= e(isset($record['warehouse_name']) ? $record['warehouse_name'] : '-') ?></div>
        </div>
    </div>
    <div class="form-row">
        <div class="form-group">
            <label>來源類型</label>
            <div class="form-static"><?= $refType ? e(StockModel::referenceTypeLabel($refType)) : '-' ?></div>
        </div>
        <div class="form-group">
            <label>來源單據</label>
            <?php
            $srcNum = isset($record['source_number']) ? $record['source_number'] : '';
            $srcLabel = $srcNum ? $srcNum : ($refId ? '#' . (int)$refId : '-');
            ?>
            <div class="form-static">
                <?php
                $srcLinked = false;
                if ($refType === 'delivery_order' && $refId) {
                    echo '<a href="/delivery_orders.php?action=view&id=' . (int)$refId . '">' . e($srcLabel) . '</a>';
                    $srcLinked = true;
                } elseif ($refType === 'quotation' && $refId) {
                    echo '<a href="/quotations.php?action=view&id=' . (int)$refId . '">' . e($srcLabel) . '</a>';
                    $srcLinked = true;
                } elseif ($refType === 'case' && $refId) {
                    echo '<a href="/cases.php?action=edit&id=' . (int)$refId . '">' . e($srcLabel) . '</a>';
                    $srcLinked = true;
                } elseif ($srcNum) {
                    // 用單號反查
                    if (strpos($srcNum, 'SR-') === 0) {
                        $lnk = Database::getInstance()->prepare("SELECT id FROM stock_ins WHERE si_number = ?");
                        $lnk->execute(array($srcNum));
                        $lnkId = $lnk->fetchColumn();
                        if ($lnkId) { echo '<a href="/stock_ins.php?action=view&id=' . $lnkId . '">' . e($srcNum) . '</a>'; $srcLinked = true; }
                    } elseif (strpos($srcNum, 'S/D-') === 0) {
                        $lnk = Database::getInstance()->prepare("SELECT id FROM stock_outs WHERE so_number = ?");
                        $lnk->execute(array($srcNum));
                        $lnkId = $lnk->fetchColumn();
                        if ($lnkId) { echo '<a href="/stock_outs.php?action=view&id=' . $lnkId . '">' . e($srcNum) . '</a>'; $srcLinked = true; }
                    }
                    if (!$srcLinked) echo e($srcNum);
                } else {
                    echo '-';
                }
                ?>
            </div>
        </div>
        <div class="form-group">
            <label>客戶名稱</label>
            <div class="form-static"><?= e(!empty($record['customer_name']) ? $record['customer_name'] : '-') ?></div>
        </div>
        <div class="form-group">
            <label>建立者</label>
            <div class="form-static"><?= e($creatorName) ?><?php if (!empty($record['created_at'])): ?><br><small class="text-muted"><?= e($record['created_at']) ?></small><?php endif; ?></div>
        </div>
    </div>
    <?php if (!empty($record['note'])): ?>
    <div class="form-group">
        <label>備註</label>
        <div class="form-static"><?= nl2br(e($record['note'])) ?></div>
    </div>
    <?php endif; ?>
    <?php if ($record['status'] === 'confirmed' || $record['status'] === '已確認'): ?>
    <div class="form-row" style="margin-top:8px">
        <div class="form-group">
            <label>確認者</label>
            <div class="form-static"><?= e($confirmedName) ?></div>
        </div>
        <div class="form-group">
            <label>確認時間</label>
            <div class="form-static"><?= e(isset($record['confirmed_at']) ? $record['confirmed_at'] : '-') ?></div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- 出庫明細 -->
<div class="card">
    <div class="card-header">出庫明細</div>
    <?php if (empty($items)): ?>
        <p class="text-muted text-center mt-2">無明細</p>
    <?php else: ?>
    <?php
    // 查詢各產品庫存（依出庫單倉庫）
    $stockMap = array();
    $whId = isset($record['warehouse_id']) ? (int)$record['warehouse_id'] : 0;
    if (!empty($items) && $whId) {
        $pids = array();
        foreach ($items as $it) { if (!empty($it['product_id'])) $pids[] = (int)$it['product_id']; }
        if ($pids) {
            $pids = array_unique($pids);
            $ph = implode(',', array_fill(0, count($pids), '?'));
            try {
                $stkStmt = Database::getInstance()->prepare("SELECT product_id, stock_qty FROM inventory WHERE warehouse_id = ? AND product_id IN ($ph)");
                $stkStmt->execute(array_merge(array($whId), $pids));
                foreach ($stkStmt->fetchAll(PDO::FETCH_ASSOC) as $sr) { $stockMap[(int)$sr['product_id']] = (int)$sr['stock_qty']; }
            } catch (Exception $e) {}
        }
    }
    ?>
    <div class="table-responsive">
        <table class="table" id="soItemsTable">
            <thead><tr>
                <?php if ($canManage && $canConfirmItems): ?><th class="print-hide-col" style="width:35px"><input type="checkbox" id="checkAll" onchange="toggleCheckAll(this)"></th><?php endif; ?>
                <th class="return-col" style="width:35px;display:none"><input type="checkbox" id="returnCheckAll" onchange="toggleReturnCheckAll(this)"></th>
                <th style="width:30px">#</th><th>品名</th><th>型號</th><th>單位</th><th class="text-right">庫存</th><th class="text-right">需求</th><th class="text-right" style="width:60px">已出</th><th class="text-right" style="width:60px">剩餘</th><?php if (!empty($returnedQtyMap)): ?><th class="text-right" style="width:70px">已退回</th><th class="text-right" style="width:70px">實際使用</th><?php endif; ?><?php if ($canConfirmItems): ?><th class="text-right print-hide-col" style="width:80px">本次出貨</th><?php endif; ?><th class="text-right">單價</th><th class="text-right">小計</th><th style="width:90px">狀態</th>
                <th class="return-col" style="width:80px;display:none">入庫數量</th>
                <th class="return-col" style="width:140px;display:none">備註</th>
            </tr></thead>
            <tbody>
                <?php $totalCost = 0; ?>
                <?php foreach ($items as $idx => $item):
                    $productName = isset($item['product_name']) ? $item['product_name'] : (isset($item['db_product_name']) ? $item['db_product_name'] : '-');
                    $productModel = isset($item['product_model']) ? $item['product_model'] : (isset($item['db_model']) ? $item['db_model'] : (isset($item['model']) ? $item['model'] : '-'));
                    $unitDisplay = isset($item['unit']) ? $item['unit'] : '-';
                    // 新語意：quantity = 需求量，shipped_qty = 累計已出
                    $needQty = (int)(isset($item['quantity']) ? $item['quantity'] : 0);
                    $shippedQty = (int)(isset($item['shipped_qty']) ? $item['shipped_qty'] : 0);
                    $remainingQty = max(0, $needQty - $shippedQty);
                    $isFullyShipped = ($shippedQty >= $needQty && $needQty > 0);
                    $isPartialShipped = ($shippedQty > 0 && $shippedQty < $needQty);
                    $unitCost = (int)(isset($item['unit_cost']) ? $item['unit_cost'] : (isset($item['unit_price']) ? $item['unit_price'] : 0));
                    $subtotal = $needQty * $unitCost; // 以需求量計算
                    $isSpare = !empty($item['is_spare']);
                    $itemNote = isset($item['note']) ? $item['note'] : '';
                    $pid = !empty($item['product_id']) ? (int)$item['product_id'] : 0;
                    $itemStock = isset($stockMap[$pid]) ? (int)$stockMap[$pid] : 0;
                    $stockDisplay = isset($stockMap[$pid]) ? $stockMap[$pid] : '-';
                    $stockColor = ($itemStock > 0) ? '#2e7d32' : '#c62828';
                    // 庫存判斷：只要能夠出剩餘的部分就算 ok
                    $hasStock = ($remainingQty > 0 && $itemStock >= $remainingQty);
                ?>
                <?php $isEditableRow = ($shippedQty == 0); // 未開始出貨的列才能編輯 ?>
                <tr<?= $isSpare ? ' style="background:#fff8e1"' : '' ?>
                    data-row-item-id="<?= (int)$item['id'] ?>"
                    data-editable="<?= $isEditableRow ? '1' : '0' ?>"
                    data-orig-qty="<?= $needQty ?>"
                    data-orig-price="<?= $unitCost ?>"
                    data-orig-note="<?= e($itemNote) ?>">
                    <td class="return-col" style="display:none"><?php if ($shippedQty > 0): ?><input type="checkbox" class="return-check" value="<?= (int)$item['id'] ?>" data-max="<?= $shippedQty ?>" data-product="<?= e($productName) ?>"><?php endif; ?></td>
                    <?php if ($canManage && $canConfirmItems): ?>
                    <td class="print-hide-col"><?php if (!$isFullyShipped && $hasStock): ?><input type="checkbox" class="item-check" value="<?= (int)$item['id'] ?>" data-qty="<?= $remainingQty ?>"><?php endif; ?></td>
                    <?php endif; ?>
                    <td><?= $idx + 1 ?></td>
                    <td>
                        <?= e($productName) ?>
                        <?php if ($isSpare): ?>
                        <span style="background:#ff9800;color:#fff;padding:1px 6px;border-radius:3px;font-size:.7rem;margin-left:4px;font-weight:600">備品</span>
                        <?php endif; ?>
                        <?php if (!$isSpare && $canManage): ?>
                            <span class="item-note-edit" data-item-id="<?= (int)$item['id'] ?>" data-note="<?= e($itemNote) ?>" style="color:#888;font-size:.75rem;margin-left:4px;cursor:pointer" title="點擊編輯備註">
                                <?php if ($itemNote): ?>(<?= e($itemNote) ?>)<?php else: ?><span style="color:#bbb;font-style:italic">+ 備註</span><?php endif; ?>
                            </span>
                        <?php elseif ($itemNote && $itemNote !== '備品'): ?>
                            <span style="color:#888;font-size:.75rem;margin-left:4px">(<?= e($itemNote) ?>)</span>
                        <?php endif; ?>
                    </td>
                    <td><?= e($productModel) ?></td>
                    <td><?= e($unitDisplay) ?></td>
                    <td class="text-right" style="color:<?= $stockColor ?>"><?= $stockDisplay ?></td>
                    <td class="text-right need-cell" style="font-weight:600"><?= $needQty ?></td>
                    <td class="text-right" style="color:<?= $shippedQty > 0 ? '#1565c0' : '#999' ?>"><?= $shippedQty ?></td>
                    <td class="text-right" style="color:<?= $remainingQty > 0 ? '#e65100' : '#999' ?>;font-weight:<?= $remainingQty > 0 ? '600' : 'normal' ?>"><?= $remainingQty ?></td>
                    <?php if (!empty($returnedQtyMap)):
                        $itemReturned = isset($returnedQtyMap[$pid]) ? $returnedQtyMap[$pid] : 0;
                        $actualUsed = max(0, $shippedQty - $itemReturned);
                    ?>
                    <td class="text-right" style="color:<?= $itemReturned > 0 ? '#e65100' : '#999' ?>"><?= $itemReturned > 0 ? $itemReturned : '-' ?></td>
                    <td class="text-right" style="font-weight:600;color:<?= $actualUsed > 0 ? '#1565c0' : '#999' ?>"><?= $actualUsed > 0 ? $actualUsed : '0' ?></td>
                    <?php endif; ?>
                    <?php if ($canConfirmItems): ?>
                    <td class="text-right print-hide-col">
                        <?php if (!$isFullyShipped && $hasStock): ?>
                        <input type="number" class="form-control confirm-qty" data-item-id="<?= (int)$item['id'] ?>" style="width:70px;display:inline-block;text-align:right;padding:2px 6px;font-size:.85rem" min="1" max="<?= $remainingQty ?>" value="<?= $remainingQty ?>">
                        <?php elseif ($isFullyShipped): ?>
                        <span style="color:#999">-</span>
                        <?php else: ?>
                        <span style="color:#c62828;font-size:.75rem">庫存不足</span>
                        <?php endif; ?>
                    </td>
                    <?php endif; ?>
                    <td class="text-right price-cell">$<?= number_format($unitCost) ?></td>
                    <td class="text-right subtotal-cell">$<?= number_format($subtotal) ?></td>
                    <td>
                        <?php
                        // 新狀態顯示邏輯
                        $itmRet = !empty($returnedQtyMap) && isset($returnedQtyMap[$pid]) ? $returnedQtyMap[$pid] : 0;
                        if ($isFullyShipped):
                            if ($itmRet >= $shippedQty && $shippedQty > 0): ?>
                        <span style="color:#e65100;font-weight:600;font-size:.75rem">已出庫｜全退</span>
                        <?php elseif ($itmRet > 0): ?>
                        <span style="color:#1976d2;font-weight:600;font-size:.75rem">已出庫｜部分退</span>
                        <?php else: ?>
                        <span style="color:#1565c0;font-weight:600;font-size:.8rem">已出庫</span>
                        <?php endif; ?>
                        <?php elseif ($isPartialShipped): ?>
                        <span style="color:#e65100;font-weight:600;font-size:.78rem">部分出貨<br><small>(<?= $shippedQty ?>/<?= $needQty ?>)</small></span>
                        <?php elseif (!$hasStock && $remainingQty > 0): ?>
                        <span style="color:#c62828;font-size:.75rem">庫存不足</span>
                        <?php else: ?>
                        <span style="color:#2e7d32;font-size:.8rem">待出庫</span>
                        <?php endif; ?>
                        <?php if ($isSpare && $canManage && $canConfirmItems && !$isFullyShipped): ?>
                        <button type="button" class="btn btn-danger btn-sm" onclick="removeSpare(<?= (int)$item['id'] ?>)" title="移除備品" style="padding:2px 6px;font-size:.75rem;margin-left:4px">&times;</button>
                        <?php endif; ?>
                    </td>
                    <td class="return-col" style="display:none"><?php if ($shippedQty > 0): ?><input type="number" class="form-control return-qty" data-item-id="<?= (int)$item['id'] ?>" style="width:70px;text-align:right;padding:2px 6px;font-size:.85rem" min="0" max="<?= $shippedQty ?>" value="0"><?php endif; ?></td>
                    <td class="return-col" style="display:none"><?php if ($shippedQty > 0): ?><input type="text" class="form-control return-note" data-item-id="<?= (int)$item['id'] ?>" placeholder="備註" style="width:130px;padding:2px 6px;font-size:.85rem"><?php endif; ?></td>
                </tr>
                <?php $totalCost += $subtotal; ?>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <?php
                    $colSpan = 9; // # 品名 型號 單位 庫存 需求 已出 剩餘 + 合計前佔位
                    if ($canManage && $canConfirmItems) $colSpan++; // checkbox
                    if (!empty($returnedQtyMap)) $colSpan += 2; // 已退回 + 實際使用
                    if ($canConfirmItems) $colSpan++; // 本次出貨
                    ?>
                    <td colspan="<?= $colSpan ?>" class="text-right"><strong>合計</strong></td>

                    <td class="text-right"><strong>$<?= number_format($totalCost) ?></strong></td>
                    <?php if ($canManage && $isPending): ?><td></td><?php endif; ?>
                </tr>
            </tfoot>
        </table>
    </div>
    <?php endif; ?>

    <!-- 餘料入庫紀錄 -->
    <?php if (!empty($returnStockIns)): ?>
    <div style="padding:12px 16px;border-top:1px solid var(--gray-200);background:#fafafa">
        <div style="font-weight:600;color:#7B1FA2;margin-bottom:8px;font-size:.9rem">📦 餘料入庫紀錄</div>
        <table class="table" style="font-size:.85rem;margin:0;background:#fff">
            <thead><tr>
                <th style="width:160px">入庫單號</th>
                <th style="width:110px">日期</th>
                <th style="width:80px">狀態</th>
                <th class="text-right" style="width:80px">品項數</th>
                <th class="text-right" style="width:80px">總數量</th>
                <th>備註</th>
                <th style="width:60px">操作</th>
            </tr></thead>
            <tbody>
            <?php foreach ($returnStockIns as $ri):
                $statusBg = '#fff';
                if ($ri['status'] === '已確認') $statusBg = '#e8f5e9';
                elseif ($ri['status'] === '已取消') $statusBg = '#fafafa';
                else $statusBg = '#fff8e1';
            ?>
            <tr style="background:<?= $statusBg ?>">
                <td style="color:var(--primary);font-weight:600"><?= e($ri['si_number']) ?></td>
                <td><?= e($ri['si_date']) ?></td>
                <td><span class="badge"><?= e($ri['status']) ?></span></td>
                <td class="text-right"><?= (int)$ri['item_count'] ?></td>
                <td class="text-right"><?= (int)$ri['total_qty'] ?></td>
                <td style="color:#666;font-size:.78rem"><?= e($ri['note']) ?></td>
                <td><a href="/stock_ins.php?action=view&id=<?= $ri['id'] ?>" class="btn btn-outline btn-sm" style="font-size:.7rem;padding:2px 8px">檢視</a></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <?php if ($canManage && $isPending): ?>
    <div style="padding:12px;border-top:1px solid var(--gray-200)">
        <button type="button" class="btn btn-outline btn-sm" onclick="toggleSpareForm()" id="btnAddSpare" style="color:#ff9800;border-color:#ff9800">+ 新增備品</button>
        <div id="spareForm" style="display:none;margin-top:12px;padding:12px;background:#fff8e1;border-radius:8px">
            <!-- 分類選擇列 -->
            <div style="display:flex;gap:6px;margin-bottom:10px;flex-wrap:wrap;align-items:center">
                <select id="spareCat1" class="form-control" onchange="onSpareCat1(this)" style="flex:1;min-width:140px;font-size:.85rem"><option value="">主分類</option></select>
                <select id="spareCat2" class="form-control" onchange="onSpareCat2(this)" style="flex:1;min-width:140px;font-size:.85rem"><option value="">子分類</option></select>
                <select id="spareCat3" class="form-control" onchange="onSpareCat3(this)" style="flex:1;min-width:140px;font-size:.85rem"><option value="">細分類</option></select>
                <select id="spareProduct" class="form-control" onchange="onSpareProductSelect(this)" style="flex:2;min-width:200px;font-size:.85rem"><option value="">產品名稱</option></select>
                <div style="position:relative;flex:1;min-width:160px">
                    <input type="text" id="spareKeyword" class="form-control" placeholder="關鍵字搜尋" autocomplete="off" style="font-size:.85rem">
                    <div id="spareKwDropdown" style="display:none;position:absolute;top:100%;left:0;z-index:100;background:#fff;border:1px solid var(--gray-200);border-radius:6px;max-height:250px;overflow-y:auto;box-shadow:0 4px 12px rgba(0,0,0,.15);width:450px;right:0"></div>
                </div>
            </div>
            <!-- 選中的產品資訊 + 數量 -->
            <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end">
                <div style="flex:2;min-width:180px">
                    <label style="font-size:.8rem;font-weight:600">品名</label>
                    <input type="text" id="spareName" class="form-control" readonly style="background:#f5f5f5;font-weight:600">
                    <input type="hidden" id="spareProductId">
                </div>
                <div style="flex:0 0 120px">
                    <label style="font-size:.8rem;font-weight:600">型號</label>
                    <input type="text" id="spareModel" class="form-control" readonly style="background:#f5f5f5;color:#1565c0">
                </div>
                <div style="flex:0 0 80px">
                    <label style="font-size:.8rem;font-weight:600">庫存</label>
                    <input type="text" id="spareStock" class="form-control" readonly style="background:#f5f5f5;text-align:center">
                </div>
                <div style="flex:0 0 80px">
                    <label style="font-size:.8rem;font-weight:600">數量</label>
                    <input type="number" id="spareQty" class="form-control" value="1" min="1">
                </div>
                <div style="flex:1;min-width:120px">
                    <label style="font-size:.8rem;font-weight:600">備註</label>
                    <input type="text" id="spareNote" class="form-control" placeholder="備品用途說明">
                </div>
                <div>
                    <button type="button" class="btn btn-sm" style="background:#ff9800;color:#fff" onclick="saveSpare()">新增</button>
                    <button type="button" class="btn btn-outline btn-sm" onclick="toggleSpareForm()">取消</button>
                </div>
            </div>
        </div>
    </div>
    <script>
    var spareWarehouseId = <?= (int)($record['warehouse_id'] ?? 0) ?>;
    var spareCats = [];

    function toggleSpareForm() {
        var form = document.getElementById('spareForm');
        var show = form.style.display === 'none';
        form.style.display = show ? 'block' : 'none';
        if (show && spareCats.length === 0) loadSpareCat1();
    }

    function escHtml(s) {
        if (!s) return '';
        var d = document.createElement('div');
        d.appendChild(document.createTextNode(s));
        return d.innerHTML;
    }

    // 載入主分類
    function loadSpareCat1() {
        var xhr = new XMLHttpRequest();
        xhr.open('GET', '/stock_outs.php?action=ajax_categories');
        xhr.onload = function() {
            spareCats = JSON.parse(xhr.responseText);
            var sel = document.getElementById('spareCat1');
            sel.innerHTML = '<option value="">主分類 (' + spareCats.length + ')</option>';
            for (var i = 0; i < spareCats.length; i++) {
                sel.innerHTML += '<option value="' + spareCats[i].id + '">' + escHtml(spareCats[i].name) + '</option>';
            }
        };
        xhr.send();
    }

    function onSpareCat1(sel) {
        var cat2 = document.getElementById('spareCat2');
        var cat3 = document.getElementById('spareCat3');
        var prod = document.getElementById('spareProduct');
        cat2.innerHTML = '<option value="">子分類</option>';
        cat3.innerHTML = '<option value="">細分類</option>';
        prod.innerHTML = '<option value="">產品名稱</option>';
        if (!sel.value) return;
        var xhr = new XMLHttpRequest();
        xhr.open('GET', '/stock_outs.php?action=ajax_categories&parent_id=' + sel.value);
        xhr.onload = function() {
            var subs = JSON.parse(xhr.responseText);
            cat2.innerHTML = '<option value="">子分類 (' + subs.length + ')</option>';
            for (var i = 0; i < subs.length; i++) cat2.innerHTML += '<option value="' + subs[i].id + '">' + escHtml(subs[i].name) + '</option>';
        };
        xhr.send();
        loadSpareProducts(sel.value);
    }

    function onSpareCat2(sel) {
        var cat3 = document.getElementById('spareCat3');
        var prod = document.getElementById('spareProduct');
        cat3.innerHTML = '<option value="">細分類</option>';
        prod.innerHTML = '<option value="">產品名稱</option>';
        if (!sel.value) { var c1 = document.getElementById('spareCat1'); if (c1.value) loadSpareProducts(c1.value); return; }
        var xhr = new XMLHttpRequest();
        xhr.open('GET', '/stock_outs.php?action=ajax_categories&parent_id=' + sel.value);
        xhr.onload = function() {
            var subs = JSON.parse(xhr.responseText);
            cat3.innerHTML = '<option value="">細分類 (' + subs.length + ')</option>';
            for (var i = 0; i < subs.length; i++) cat3.innerHTML += '<option value="' + subs[i].id + '">' + escHtml(subs[i].name) + '</option>';
        };
        xhr.send();
        loadSpareProducts(sel.value);
    }

    function onSpareCat3(sel) {
        if (!sel.value) { var c2 = document.getElementById('spareCat2'); if (c2.value) loadSpareProducts(c2.value); return; }
        loadSpareProducts(sel.value);
    }

    function loadSpareProducts(categoryId) {
        var xhr = new XMLHttpRequest();
        xhr.open('GET', '/stock_outs.php?action=ajax_products&category_id=' + categoryId + '&warehouse_id=' + spareWarehouseId);
        xhr.onload = function() {
            var data = JSON.parse(xhr.responseText);
            var prod = document.getElementById('spareProduct');
            prod.innerHTML = '<option value="">選擇產品 (' + data.length + '項)</option>';
            for (var i = 0; i < data.length; i++) {
                var stockLabel = data[i].stock_qty > 0 ? ' [庫存:' + data[i].stock_qty + ']' : ' [無庫存]';
                prod.innerHTML += '<option value="' + i + '" ' +
                    'data-id="' + data[i].id + '" data-name="' + escHtml(data[i].name) + '" ' +
                    'data-model="' + escHtml(data[i].model || '') + '" data-unit="' + escHtml(data[i].unit || '台') + '" ' +
                    'data-cost="' + (data[i].cost || 0) + '" data-stock="' + (data[i].stock_qty || 0) + '">' +
                    escHtml(data[i].name) + (data[i].model ? ' ' + data[i].model : '') + stockLabel + '</option>';
            }
        };
        xhr.send();
    }

    function onSpareProductSelect(sel) {
        var opt = sel.options[sel.selectedIndex];
        if (!opt || !opt.getAttribute('data-id')) return;
        document.getElementById('spareProductId').value = opt.getAttribute('data-id');
        document.getElementById('spareName').value = opt.getAttribute('data-name');
        document.getElementById('spareModel').value = opt.getAttribute('data-model');
        var stock = opt.getAttribute('data-stock') || '0';
        var stockEl = document.getElementById('spareStock');
        stockEl.value = stock;
        stockEl.style.color = parseInt(stock) > 0 ? '#2e7d32' : '#c62828';
    }

    // 關鍵字搜尋
    var spareKwTimer = null;
    document.getElementById('spareKeyword').addEventListener('input', function() {
        clearTimeout(spareKwTimer);
        var q = this.value.trim();
        var dd = document.getElementById('spareKwDropdown');
        if (q.length < 2) { dd.style.display = 'none'; return; }
        spareKwTimer = setTimeout(function() {
            var xhr = new XMLHttpRequest();
            xhr.open('GET', '/stock_outs.php?action=ajax_products&keyword=' + encodeURIComponent(q) + '&warehouse_id=' + spareWarehouseId);
            xhr.onload = function() {
                var data = JSON.parse(xhr.responseText);
                if (!data.length) { dd.innerHTML = '<div style="padding:8px;color:#999;font-size:.85rem">無符合產品</div>'; dd.style.display = 'block'; return; }
                var html = '';
                for (var i = 0; i < data.length; i++) {
                    var stockColor = data[i].stock_qty > 0 ? '#2e7d32' : '#c62828';
                    html += '<div style="padding:6px 10px;cursor:pointer;font-size:.85rem;border-bottom:1px solid #eee" ' +
                        'data-id="' + data[i].id + '" data-name="' + escHtml(data[i].name) + '" ' +
                        'data-model="' + escHtml(data[i].model || '') + '" data-unit="' + escHtml(data[i].unit || '台') + '" ' +
                        'data-cost="' + (data[i].cost || 0) + '" data-stock="' + (data[i].stock_qty || 0) + '" ' +
                        'onmouseover="this.style.background=\'#f0f7ff\'" onmouseout="this.style.background=\'\'" ' +
                        'onclick="selectSpareFromKw(this)">' +
                        '<div style="font-weight:600">' + escHtml(data[i].name) + '</div>' +
                        '<div style="font-size:.75rem;color:#888">' +
                            (data[i].model ? '<span style="color:#1565c0">' + escHtml(data[i].model) + '</span> | ' : '') +
                            '<span style="color:' + stockColor + '">庫存: ' + data[i].stock_qty + '</span>' +
                            (data[i].category_name ? ' | ' + escHtml(data[i].category_name) : '') +
                        '</div></div>';
                }
                dd.innerHTML = html;
                dd.style.display = 'block';
            };
            xhr.send();
        }, 300);
    });

    function selectSpareFromKw(el) {
        document.getElementById('spareProductId').value = el.getAttribute('data-id');
        document.getElementById('spareName').value = el.getAttribute('data-name');
        document.getElementById('spareModel').value = el.getAttribute('data-model');
        var stock = el.getAttribute('data-stock') || '0';
        var stockEl = document.getElementById('spareStock');
        stockEl.value = stock;
        stockEl.style.color = parseInt(stock) > 0 ? '#2e7d32' : '#c62828';
        document.getElementById('spareKwDropdown').style.display = 'none';
        document.getElementById('spareKeyword').value = '';
    }

    document.addEventListener('click', function(e) {
        if (!e.target.closest('#spareKeyword') && !e.target.closest('#spareKwDropdown')) {
            document.getElementById('spareKwDropdown').style.display = 'none';
        }
    });

    function saveSpare() {
        var productId = document.getElementById('spareProductId').value;
        var name = document.getElementById('spareName').value.trim();
        if (!name) { alert('請先選擇產品'); return; }
        var qty = parseInt(document.getElementById('spareQty').value) || 1;
        var note = document.getElementById('spareNote').value.trim() || '備品';
        var model = document.getElementById('spareModel').value;
        var unit = '台';

        var fd = new FormData();
        fd.append('product_id', productId);
        fd.append('product_name', name);
        fd.append('model', model);
        fd.append('quantity', qty);
        fd.append('note', note);
        fd.append('unit', unit);

        var xhr = new XMLHttpRequest();
        xhr.open('POST', '/stock_outs.php?action=ajax_add_spare&id=<?= $record['id'] ?>');
        xhr.onload = function() {
            var res = JSON.parse(xhr.responseText);
            if (res.success) { location.reload(); }
            else { alert(res.error || '新增失敗'); }
        };
        xhr.send(fd);
    }

    function removeSpare(itemId) {
        if (!confirm('確定移除此備品？')) return;
        var xhr = new XMLHttpRequest();
        xhr.open('GET', '/stock_outs.php?action=ajax_remove_spare&id=<?= $record['id'] ?>&item_id=' + itemId);
        xhr.onload = function() {
            var res = JSON.parse(xhr.responseText);
            if (res.success) { location.reload(); }
            else { alert(res.error || '移除失敗'); }
        };
        xhr.send();
    }
    </script>
    <?php endif; ?>

    <?php if ($canManage && $canConfirmItems): ?>
    <script>
    function toggleCheckAll(el) {
        var checks = document.querySelectorAll('.item-check');
        for (var i = 0; i < checks.length; i++) checks[i].checked = el.checked;
    }
    function confirmCheckedItems() {
        var checks = document.querySelectorAll('.item-check:checked');
        if (checks.length === 0) { alert('請先勾選要出庫的品項'); return; }
        if (!confirm('確認勾選的 ' + checks.length + ' 個品項出庫？')) return;
        var form = document.createElement('form');
        form.method = 'POST';
        form.action = '/stock_outs.php?action=confirm&id=<?= $record['id'] ?>';
        var csrf = document.createElement('input');
        csrf.type = 'hidden'; csrf.name = 'csrf_token'; csrf.value = '<?= e(Session::getCsrfToken()) ?>';
        form.appendChild(csrf);
        for (var i = 0; i < checks.length; i++) {
            var itemId = checks[i].value;
            var inp = document.createElement('input');
            inp.type = 'hidden'; inp.name = 'item_ids[]'; inp.value = itemId;
            form.appendChild(inp);
            // 取出庫數量
            var qtyInput = document.querySelector('.confirm-qty[data-item-id="' + itemId + '"]');
            if (qtyInput) {
                var qInp = document.createElement('input');
                qInp.type = 'hidden'; qInp.name = 'item_qtys[' + itemId + ']'; qInp.value = qtyInput.value;
                form.appendChild(qInp);
            }
        }
        document.body.appendChild(form);
        form.submit();
    }
    </script>
    <?php endif; ?>

    <!-- ============ 編輯明細模式 ============ -->
    <?php if ($canEdit): ?>
    <div id="editToolbar" style="display:none;padding:12px 16px;background:#fff3e0;border-top:2px solid #ff9800">
        <div class="d-flex justify-between align-center flex-wrap gap-1">
            <div style="font-size:.9rem;color:#e65100;font-weight:600">
                ✏ 編輯模式
                <small style="color:#888;font-weight:normal">｜可修改「未出貨」的列，已出貨列完全鎖定</small>
            </div>
            <div class="d-flex gap-1">
                <button type="button" class="btn btn-outline btn-sm" style="color:#ff9800;border-color:#ff9800" onclick="showEditAddProductPicker()">+ 新增項目</button>
                <button type="button" class="btn btn-sm" style="background:var(--success);color:#fff" onclick="saveEditChanges()">儲存變更</button>
                <button type="button" class="btn btn-outline btn-sm" onclick="cancelEditMode()">取消</button>
            </div>
        </div>
    </div>

    <!-- 編輯模式新增項目的 Modal -->
    <div id="editAddModal" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,.5);z-index:2000;align-items:center;justify-content:center">
        <div style="background:#fff;border-radius:8px;padding:20px;max-width:680px;width:90%;max-height:85vh;overflow:auto">
            <div class="d-flex justify-between align-center mb-2">
                <h3 style="margin:0">新增項目</h3>
                <button type="button" onclick="closeEditAddModal()" style="background:none;border:none;font-size:1.5rem;cursor:pointer">&times;</button>
            </div>
            <div style="margin-bottom:10px">
                <label style="font-size:.85rem;font-weight:600">搜尋產品</label>
                <input type="text" id="editAddKeyword" class="form-control" placeholder="輸入關鍵字（至少 2 字）搜尋產品..." autocomplete="off" oninput="editAddSearch()">
                <div id="editAddResults" style="margin-top:6px;max-height:220px;overflow-y:auto;border:1px solid var(--gray-200);border-radius:4px;display:none"></div>
            </div>
            <div class="form-row">
                <div class="form-group" style="flex:2;min-width:180px">
                    <label style="font-size:.85rem;font-weight:600">品名 *</label>
                    <input type="text" id="editAddName" class="form-control" readonly style="background:#f5f5f5;font-weight:600">
                    <input type="hidden" id="editAddPid">
                </div>
                <div class="form-group" style="flex:1;min-width:100px">
                    <label style="font-size:.85rem;font-weight:600">型號</label>
                    <input type="text" id="editAddModel" class="form-control" readonly style="background:#f5f5f5;color:#1565c0">
                </div>
                <div class="form-group" style="flex:0 0 60px">
                    <label style="font-size:.85rem;font-weight:600">單位</label>
                    <input type="text" id="editAddUnit" class="form-control" readonly style="background:#f5f5f5">
                </div>
                <div class="form-group" style="flex:0 0 80px">
                    <label style="font-size:.85rem;font-weight:600">庫存</label>
                    <input type="text" id="editAddStock" class="form-control" readonly style="background:#f5f5f5;text-align:center">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group" style="flex:0 0 100px">
                    <label style="font-size:.85rem;font-weight:600">需求量 *</label>
                    <input type="number" id="editAddQty" class="form-control" min="1" value="1">
                </div>
                <div class="form-group" style="flex:0 0 120px">
                    <label style="font-size:.85rem;font-weight:600">單價</label>
                    <input type="number" id="editAddPrice" class="form-control" min="0" value="0">
                </div>
                <div class="form-group" style="flex:1">
                    <label style="font-size:.85rem;font-weight:600">備註</label>
                    <input type="text" id="editAddNote" class="form-control" placeholder="選填">
                </div>
            </div>
            <div class="d-flex justify-end gap-1 mt-2">
                <button type="button" class="btn btn-primary" onclick="addPendingItem()">加入明細</button>
                <button type="button" class="btn btn-outline" onclick="closeEditAddModal()">取消</button>
            </div>
        </div>
    </div>

    <style>
    .edit-mode-active .edit-hide { display: none !important; }
    tr.edit-row-deleted { opacity: .3; text-decoration: line-through; background: #ffebee !important; }
    tr.edit-row-new { background: #e8f5e9 !important; }
    .edit-input { width: 70px; padding: 2px 4px; text-align: right; font-size: .85rem; border: 1px solid var(--primary); border-radius: 3px; }
    .edit-input-text { width: 100%; padding: 2px 4px; font-size: .85rem; border: 1px solid var(--primary); border-radius: 3px; }
    .edit-row-btn { padding: 2px 8px; font-size: .75rem; }
    </style>

    <script>
    var editModeActive = false;
    var pendingDeletes = [];       // 要刪除的既有 item id
    var pendingAdds = [];          // 要新增的 item objects
    var tempIdCounter = -1;        // 新增列的臨時 id (負數)
    var editProducts = [];         // 搜尋結果快取

    function enterEditMode() {
        if (editModeActive) return;
        editModeActive = true;
        pendingDeletes = [];
        pendingAdds = [];
        tempIdCounter = -1;

        document.body.classList.add('edit-mode-active');
        document.getElementById('editToolbar').style.display = 'block';
        // 隱藏其他 action 按鈕
        var bar = document.getElementById('actionBtnBar');
        if (bar) {
            var btns = bar.querySelectorAll('a.btn, button.btn');
            for (var i = 0; i < btns.length; i++) {
                if (btns[i].id !== 'btnEnterEdit') btns[i].classList.add('edit-hide');
            }
        }
        document.getElementById('btnEnterEdit').classList.add('edit-hide');

        // 把可編輯列變成輸入框
        var rows = document.querySelectorAll('tr[data-row-item-id][data-editable="1"]');
        for (var r = 0; r < rows.length; r++) {
            makeRowEditable(rows[r]);
        }
    }

    function makeRowEditable(row) {
        var itemId = row.getAttribute('data-row-item-id');
        var origQty = row.getAttribute('data-orig-qty') || '1';
        var origPrice = row.getAttribute('data-orig-price') || '0';
        var origNote = row.getAttribute('data-orig-note') || '';

        // 需求量變輸入框
        var needCell = row.querySelector('.need-cell');
        if (needCell) {
            needCell.innerHTML = '<input type="number" class="edit-input edit-qty" min="1" value="' + origQty + '" onchange="onRowQtyChange(this)">';
        }

        // 單價變輸入框
        var priceCell = row.querySelector('.price-cell');
        if (priceCell) {
            priceCell.innerHTML = '<input type="number" class="edit-input edit-price" min="0" value="' + origPrice + '" onchange="onRowPriceChange(this)">';
        }

        // 狀態欄加 × 刪除按鈕
        var statusCell = row.querySelector('td:last-child');
        // 因為 return-col 可能是 last child，先找非 return-col
        var tds = row.querySelectorAll('td');
        var statCell = tds[tds.length - 2]; // 倒數第二個是狀態，倒數第一是 return-col
        if (statCell) {
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'btn btn-danger btn-sm edit-delete-btn';
            btn.style.cssText = 'padding:2px 8px;font-size:.75rem;margin-left:4px';
            btn.innerHTML = '&times; 刪';
            btn.onclick = function() { markRowDeleted(row); };
            statCell.appendChild(btn);
        }
    }

    function onRowQtyChange(inp) {
        var row = inp.closest('tr');
        var priceInp = row.querySelector('.edit-price');
        var subtotalCell = row.querySelector('.subtotal-cell');
        if (priceInp && subtotalCell) {
            var qty = parseFloat(inp.value) || 0;
            var price = parseFloat(priceInp.value) || 0;
            subtotalCell.textContent = '$' + (qty * price).toLocaleString();
        }
    }
    function onRowPriceChange(inp) {
        var row = inp.closest('tr');
        var qtyInp = row.querySelector('.edit-qty');
        var subtotalCell = row.querySelector('.subtotal-cell');
        if (qtyInp && subtotalCell) {
            var qty = parseFloat(qtyInp.value) || 0;
            var price = parseFloat(inp.value) || 0;
            subtotalCell.textContent = '$' + (qty * price).toLocaleString();
        }
    }

    function markRowDeleted(row) {
        var itemId = parseInt(row.getAttribute('data-row-item-id'));
        if (itemId > 0) {
            // 既有列 → 加入 pendingDeletes
            if (pendingDeletes.indexOf(itemId) === -1) pendingDeletes.push(itemId);
            row.classList.add('edit-row-deleted');
            // 把刪除按鈕改成復原
            var btn = row.querySelector('.edit-delete-btn');
            if (btn) {
                btn.innerHTML = '↺ 還原';
                btn.className = 'btn btn-outline btn-sm edit-delete-btn';
                btn.style.cssText = 'padding:2px 8px;font-size:.75rem;margin-left:4px;color:#1976d2;border-color:#1976d2';
                btn.onclick = function() { unmarkRowDeleted(row); };
            }
        } else {
            // 新增的列 → 直接從 pendingAdds 移除 + 從 DOM 移除
            var newId = itemId; // 負數
            pendingAdds = pendingAdds.filter(function(p) { return p._tempId !== newId; });
            row.parentNode.removeChild(row);
        }
    }

    function unmarkRowDeleted(row) {
        var itemId = parseInt(row.getAttribute('data-row-item-id'));
        pendingDeletes = pendingDeletes.filter(function(x) { return x !== itemId; });
        row.classList.remove('edit-row-deleted');
        var btn = row.querySelector('.edit-delete-btn');
        if (btn) {
            btn.innerHTML = '&times; 刪';
            btn.className = 'btn btn-danger btn-sm edit-delete-btn';
            btn.style.cssText = 'padding:2px 8px;font-size:.75rem;margin-left:4px';
            btn.onclick = function() { markRowDeleted(row); };
        }
    }

    function cancelEditMode() {
        if (pendingDeletes.length > 0 || pendingAdds.length > 0 || hasModifiedInputs()) {
            if (!confirm('尚有未儲存的變更，確定放棄？')) return;
        }
        location.reload();
    }

    function hasModifiedInputs() {
        var rows = document.querySelectorAll('tr[data-row-item-id][data-editable="1"]');
        for (var i = 0; i < rows.length; i++) {
            var row = rows[i];
            var origQty = row.getAttribute('data-orig-qty');
            var origPrice = row.getAttribute('data-orig-price');
            var qtyInp = row.querySelector('.edit-qty');
            var priceInp = row.querySelector('.edit-price');
            if (qtyInp && qtyInp.value != origQty) return true;
            if (priceInp && priceInp.value != origPrice) return true;
        }
        return false;
    }

    function saveEditChanges() {
        // 收集 updated
        var updated = [];
        var rows = document.querySelectorAll('tr[data-row-item-id][data-editable="1"]');
        for (var i = 0; i < rows.length; i++) {
            var row = rows[i];
            var itemId = parseInt(row.getAttribute('data-row-item-id'));
            if (itemId <= 0) continue;
            if (row.classList.contains('edit-row-deleted')) continue;
            var origQty = parseFloat(row.getAttribute('data-orig-qty') || '0');
            var origPrice = parseFloat(row.getAttribute('data-orig-price') || '0');
            var qtyInp = row.querySelector('.edit-qty');
            var priceInp = row.querySelector('.edit-price');
            var newQty = qtyInp ? parseFloat(qtyInp.value) : origQty;
            var newPrice = priceInp ? parseFloat(priceInp.value) : origPrice;
            if (newQty !== origQty || newPrice !== origPrice) {
                updated.push({
                    id: itemId,
                    quantity: newQty,
                    unit_price: newPrice
                });
            }
        }

        // 清理 pendingAdds（移除臨時 _tempId）
        var addPayload = [];
        for (var j = 0; j < pendingAdds.length; j++) {
            var p = pendingAdds[j];
            addPayload.push({
                product_id: p.product_id,
                product_name: p.product_name,
                model: p.model,
                unit: p.unit,
                quantity: p.quantity,
                unit_price: p.unit_price,
                note: p.note
            });
        }

        if (pendingDeletes.length === 0 && updated.length === 0 && addPayload.length === 0) {
            alert('沒有任何變更');
            return;
        }

        var summary = '即將儲存:\n';
        if (pendingDeletes.length > 0) summary += '  刪除 ' + pendingDeletes.length + ' 個品項\n';
        if (updated.length > 0) summary += '  修改 ' + updated.length + ' 個品項\n';
        if (addPayload.length > 0) summary += '  新增 ' + addPayload.length + ' 個品項\n';
        summary += '\n庫存會自動同步調整，確定儲存？';
        if (!confirm(summary)) return;

        var payload = {
            deleted: pendingDeletes,
            updated: updated,
            added: addPayload
        };

        var xhr = new XMLHttpRequest();
        xhr.open('POST', '/stock_outs.php?action=edit_items&id=<?= $record['id'] ?>');
        xhr.setRequestHeader('Content-Type', 'application/json');
        xhr.setRequestHeader('X-CSRF-Token', '<?= e(Session::getCsrfToken()) ?>');
        xhr.onload = function() {
            try {
                var res = JSON.parse(xhr.responseText);
                if (res.success) {
                    alert('儲存成功！刪除 ' + res.results.deleted + ' / 修改 ' + res.results.updated + ' / 新增 ' + res.results.added);
                    location.reload();
                } else {
                    alert('儲存失敗：' + (res.error || '未知錯誤'));
                }
            } catch (e) {
                alert('回應錯誤：' + xhr.responseText.substring(0, 200));
            }
        };
        xhr.onerror = function() { alert('網路錯誤'); };
        xhr.send(JSON.stringify(payload));
    }

    // ===== 新增項目 Modal =====
    function showEditAddProductPicker() {
        document.getElementById('editAddModal').style.display = 'flex';
        document.getElementById('editAddKeyword').value = '';
        document.getElementById('editAddName').value = '';
        document.getElementById('editAddPid').value = '';
        document.getElementById('editAddModel').value = '';
        document.getElementById('editAddUnit').value = '';
        document.getElementById('editAddStock').value = '';
        document.getElementById('editAddQty').value = '1';
        document.getElementById('editAddPrice').value = '0';
        document.getElementById('editAddNote').value = '';
        document.getElementById('editAddResults').style.display = 'none';
        setTimeout(function() { document.getElementById('editAddKeyword').focus(); }, 100);
    }
    function closeEditAddModal() {
        document.getElementById('editAddModal').style.display = 'none';
    }

    var editAddTimer = null;
    var editAddWarehouseId = <?= (int)($record['warehouse_id'] ?? 0) ?>;
    function editAddSearch() {
        clearTimeout(editAddTimer);
        var kw = document.getElementById('editAddKeyword').value.trim();
        if (kw.length < 2) {
            document.getElementById('editAddResults').style.display = 'none';
            return;
        }
        editAddTimer = setTimeout(function() {
            var xhr = new XMLHttpRequest();
            xhr.open('GET', '/stock_outs.php?action=ajax_products&keyword=' + encodeURIComponent(kw) + '&warehouse_id=' + editAddWarehouseId);
            xhr.onload = function() {
                try {
                    var data = JSON.parse(xhr.responseText);
                    renderEditAddResults(data);
                } catch (e) {}
            };
            xhr.send();
        }, 300);
    }
    function renderEditAddResults(data) {
        var box = document.getElementById('editAddResults');
        if (!data.length) {
            box.innerHTML = '<div style="padding:8px;color:#999;font-size:.85rem">無符合產品</div>';
            box.style.display = 'block';
            return;
        }
        var html = '';
        for (var i = 0; i < data.length; i++) {
            var d = data[i];
            var stockColor = d.stock_qty > 0 ? '#2e7d32' : '#c62828';
            html += '<div style="padding:6px 10px;cursor:pointer;font-size:.85rem;border-bottom:1px solid #eee" ' +
                'data-idx="' + i + '" ' +
                'onmouseover="this.style.background=\'#f0f7ff\'" onmouseout="this.style.background=\'\'" ' +
                'onclick="selectEditAddProduct(' + i + ')">' +
                '<div style="font-weight:600">' + escHtml(d.name) + '</div>' +
                '<div style="font-size:.75rem;color:#888">' +
                (d.model ? '<span style="color:#1565c0">' + escHtml(d.model) + '</span> | ' : '') +
                '<span style="color:' + stockColor + '">庫存: ' + d.stock_qty + '</span>' +
                (d.category_name ? ' | ' + escHtml(d.category_name) : '') +
                '</div></div>';
        }
        box.innerHTML = html;
        box.style.display = 'block';
        editProducts = data;
    }
    function escHtml(s) {
        if (!s) return '';
        var d = document.createElement('div');
        d.appendChild(document.createTextNode(s));
        return d.innerHTML;
    }
    function selectEditAddProduct(idx) {
        var d = editProducts[idx];
        if (!d) return;
        document.getElementById('editAddPid').value = d.id;
        document.getElementById('editAddName').value = d.name;
        document.getElementById('editAddModel').value = d.model || '';
        document.getElementById('editAddUnit').value = d.unit || '台';
        document.getElementById('editAddStock').value = d.stock_qty || 0;
        document.getElementById('editAddPrice').value = d.cost || 0;
        document.getElementById('editAddResults').style.display = 'none';
    }

    function addPendingItem() {
        var name = document.getElementById('editAddName').value.trim();
        if (!name) { alert('請先選擇產品'); return; }
        var qty = parseInt(document.getElementById('editAddQty').value) || 0;
        if (qty <= 0) { alert('數量必須大於 0'); return; }
        var pid = parseInt(document.getElementById('editAddPid').value) || 0;
        var model = document.getElementById('editAddModel').value;
        var unit = document.getElementById('editAddUnit').value || '台';
        var price = parseFloat(document.getElementById('editAddPrice').value) || 0;
        var note = document.getElementById('editAddNote').value.trim();

        var tempId = tempIdCounter--;
        pendingAdds.push({
            _tempId: tempId,
            product_id: pid,
            product_name: name,
            model: model,
            unit: unit,
            quantity: qty,
            unit_price: price,
            note: note
        });

        // 動態插入一列到表格
        insertPendingAddRow(tempId, pid, name, model, unit, qty, price, note);

        closeEditAddModal();
    }

    function insertPendingAddRow(tempId, pid, name, model, unit, qty, price, note) {
        var tbody = document.querySelector('#soItemsTable tbody');
        var tfoot = document.querySelector('#soItemsTable tfoot');
        var row = document.createElement('tr');
        row.className = 'edit-row-new';
        row.setAttribute('data-row-item-id', tempId);
        row.setAttribute('data-editable', '1');
        row.setAttribute('data-orig-qty', qty);
        row.setAttribute('data-orig-price', price);
        row.setAttribute('data-orig-note', note);

        var rowCount = tbody.children.length + 1;
        var html = '';
        // return-col (hidden)
        html += '<td class="return-col" style="display:none"></td>';
        <?php if ($canManage && $canConfirmItems): ?>
        html += '<td class="print-hide-col"></td>';
        <?php endif; ?>
        html += '<td>' + rowCount + '</td>';
        html += '<td>' + escHtml(name) + ' <span style="background:#4caf50;color:#fff;padding:1px 6px;border-radius:3px;font-size:.7rem">新</span>' + (note ? ' <span class="note-display" style="color:#888;font-size:.75rem">(' + escHtml(note) + ')</span>' : '') + '</td>';
        html += '<td>' + escHtml(model) + '</td>';
        html += '<td>' + escHtml(unit) + '</td>';
        html += '<td class="text-right" style="color:#999">-</td>';
        html += '<td class="text-right need-cell"><input type="number" class="edit-input edit-qty" min="1" value="' + qty + '" onchange="onRowQtyChange(this)"></td>';
        html += '<td class="text-right" style="color:#999">0</td>';
        html += '<td class="text-right" style="color:#e65100;font-weight:600">' + qty + '</td>';
        <?php if (!empty($returnedQtyMap)): ?>
        html += '<td></td><td></td>';
        <?php endif; ?>
        <?php if ($canConfirmItems): ?>
        html += '<td class="text-right print-hide-col"><span style="color:#999">-</span></td>';
        <?php endif; ?>
        html += '<td class="text-right price-cell"><input type="number" class="edit-input edit-price" min="0" value="' + price + '" onchange="onRowPriceChange(this)"></td>';
        html += '<td class="text-right subtotal-cell">$' + (qty * price).toLocaleString() + '</td>';
        html += '<td><span style="color:#2e7d32;font-size:.8rem">新增</span> <button type="button" class="btn btn-danger btn-sm edit-delete-btn" style="padding:2px 8px;font-size:.75rem;margin-left:4px" onclick="markRowDeleted(this.closest(\'tr\'))">&times; 移除</button></td>';
        html += '<td class="return-col" style="display:none"></td>';

        row.innerHTML = html;
        tbody.appendChild(row);
    }
    </script>
    <?php endif; ?>
</div>

<?php if ($canManage && $allConfirmed && !$isCancelled): ?>
<div id="returnBar" style="display:none;position:fixed;bottom:0;left:0;right:0;background:#7B1FA2;color:#fff;padding:12px 24px;z-index:100;display:none;align-items:center;justify-content:space-between;box-shadow:0 -2px 8px rgba(0,0,0,.2);flex-wrap:wrap;gap:8px">
    <span id="returnInfo" style="white-space:nowrap">已選 0 項</span>
    <input type="text" id="manualReturnNote" placeholder="備註（選填）" style="flex:1;min-width:200px;padding:6px 10px;border:1px solid #ddd;border-radius:4px;background:#fff;color:#333">
    <div style="white-space:nowrap">
        <button type="button" class="btn btn-sm" style="background:#fff;color:#7B1FA2;font-weight:600" onclick="confirmManualReturn()">確認退料入庫</button>
        <button type="button" class="btn btn-sm" style="background:transparent;color:#fff;border:1px solid #fff;margin-left:8px" onclick="exitReturnMode()">取消</button>
    </div>
</div>
<script>
var returnModeActive = false;
function enterReturnMode() {
    returnModeActive = true;
    var cols = document.querySelectorAll('.return-col');
    for (var i = 0; i < cols.length; i++) cols[i].style.display = '';
    document.getElementById('returnBar').style.display = 'flex';
    document.getElementById('btnManualReturn').style.display = 'none';
    updateReturnInfo();
}
function exitReturnMode() {
    returnModeActive = false;
    var cols = document.querySelectorAll('.return-col');
    for (var i = 0; i < cols.length; i++) cols[i].style.display = 'none';
    document.getElementById('returnBar').style.display = 'none';
    document.getElementById('btnManualReturn').style.display = '';
    // reset checkboxes and qty
    var checks = document.querySelectorAll('.return-check');
    for (var j = 0; j < checks.length; j++) checks[j].checked = false;
    var qtys = document.querySelectorAll('.return-qty');
    for (var k = 0; k < qtys.length; k++) qtys[k].value = 0;
}
function toggleReturnCheckAll(el) {
    var checks = document.querySelectorAll('.return-check');
    for (var i = 0; i < checks.length; i++) {
        checks[i].checked = el.checked;
        if (el.checked) {
            var qtyInput = document.querySelector('.return-qty[data-item-id="' + checks[i].value + '"]');
            if (qtyInput && qtyInput.value == 0) qtyInput.value = 1;
        }
    }
    updateReturnInfo();
}
function updateReturnInfo() {
    var checks = document.querySelectorAll('.return-check:checked');
    document.getElementById('returnInfo').textContent = '已選 ' + checks.length + ' 項';
}
// 勾選時自動帶入數量 1
document.addEventListener('change', function(e) {
    if (e.target.classList.contains('return-check')) {
        var qtyInput = document.querySelector('.return-qty[data-item-id="' + e.target.value + '"]');
        if (e.target.checked && qtyInput && qtyInput.value == 0) qtyInput.value = 1;
        if (!e.target.checked && qtyInput) qtyInput.value = 0;
        updateReturnInfo();
    }
});
// 一般餘料入庫：輸入備註後送出
function submitReturnMaterial(soId) {
    var userNote = prompt('確認將餘料建立入庫單？\n\n可輸入備註（選填，直接按確定則不加備註）：', '');
    if (userNote === null) return; // 使用者按取消
    var form = document.createElement('form');
    form.method = 'POST';
    form.action = '/stock_ins.php?action=create_from_return&stock_out_id=' + soId;
    var csrf = document.createElement('input');
    csrf.type = 'hidden'; csrf.name = 'csrf_token'; csrf.value = '<?= e(Session::getCsrfToken()) ?>';
    form.appendChild(csrf);
    if (userNote.trim() !== '') {
        var noteHidden = document.createElement('input');
        noteHidden.type = 'hidden'; noteHidden.name = 'return_note'; noteHidden.value = userNote;
        form.appendChild(noteHidden);
    }
    document.body.appendChild(form);
    form.submit();
}

function confirmManualReturn() {
    var checks = document.querySelectorAll('.return-check:checked');
    if (checks.length === 0) { alert('請先勾選要退回的品項'); return; }
    var hasQty = false;
    for (var i = 0; i < checks.length; i++) {
        var qtyInput = document.querySelector('.return-qty[data-item-id="' + checks[i].value + '"]');
        var q = qtyInput ? parseInt(qtyInput.value) : 0;
        var max = parseInt(checks[i].dataset.max);
        if (q <= 0) { alert('品項「' + checks[i].dataset.product + '」入庫數量必須大於 0'); return; }
        if (q > max) { alert('品項「' + checks[i].dataset.product + '」入庫數量不可超過出庫數量 ' + max); return; }
        hasQty = true;
    }
    if (!hasQty) return;
    if (!confirm('確認將 ' + checks.length + ' 項餘料建立入庫單？')) return;

    var form = document.createElement('form');
    form.method = 'POST';
    form.action = '/stock_outs.php?action=manual_return&id=<?= $record['id'] ?>';
    var csrf = document.createElement('input');
    csrf.type = 'hidden'; csrf.name = 'csrf_token'; csrf.value = '<?= e(Session::getCsrfToken()) ?>';
    form.appendChild(csrf);
    // 備註
    var noteInput = document.getElementById('manualReturnNote');
    if (noteInput && noteInput.value.trim() !== '') {
        var noteHidden = document.createElement('input');
        noteHidden.type = 'hidden'; noteHidden.name = 'manual_note'; noteHidden.value = noteInput.value;
        form.appendChild(noteHidden);
    }
    for (var j = 0; j < checks.length; j++) {
        var itemId = checks[j].value;
        var inp = document.createElement('input');
        inp.type = 'hidden'; inp.name = 'item_ids[]'; inp.value = itemId;
        form.appendChild(inp);
        var qInput = document.querySelector('.return-qty[data-item-id="' + itemId + '"]');
        var qInp = document.createElement('input');
        qInp.type = 'hidden'; qInp.name = 'return_qtys[' + itemId + ']'; qInp.value = qInput ? qInput.value : 0;
        form.appendChild(qInp);
        var nInput = document.querySelector('.return-note[data-item-id="' + itemId + '"]');
        if (nInput && nInput.value.trim() !== '') {
            var nInp = document.createElement('input');
            nInp.type = 'hidden'; nInp.name = 'return_notes[' + itemId + ']'; nInp.value = nInput.value;
            form.appendChild(nInp);
        }
    }
    document.body.appendChild(form);
    form.submit();
}
</script>
<?php endif; ?>

<script>
// ===== 預計出庫日 inline 編輯 =====
(function(){
    var inp = document.getElementById('soDateInput');
    if (!inp) return;
    var origVal = inp.value;
    var btn = document.getElementById('soDateSaveBtn');
    inp.addEventListener('change', function(){
        btn.style.display = (inp.value !== origVal) ? '' : 'none';
    });
})();
function saveSoDate() {
    var inp = document.getElementById('soDateInput');
    var btn = document.getElementById('soDateSaveBtn');
    var newDate = inp.value;
    if (!newDate) return;
    btn.disabled = true;
    btn.textContent = '儲存中...';
    var xhr = new XMLHttpRequest();
    xhr.open('POST', '/stock_outs.php?action=update_date&id=<?= (int)$record['id'] ?>');
    xhr.setRequestHeader('Content-Type', 'application/json');
    xhr.setRequestHeader('X-CSRF-TOKEN', '<?= Session::getCsrfToken() ?>');
    xhr.onload = function(){
        try { var res = JSON.parse(xhr.responseText); } catch(e) { alert('回應錯誤: ' + xhr.responseText); btn.disabled = false; btn.textContent = '儲存'; return; }
        if (res.success) {
            btn.style.display = 'none';
            btn.disabled = false;
            btn.textContent = '儲存';
            inp.value = res.so_date;
            btn.insertAdjacentHTML('afterend', '<span style="color:#2e7d32;font-size:.8rem;margin-left:6px" id="soDateOk">已儲存 ✓</span>');
            setTimeout(function(){ var ok = document.getElementById('soDateOk'); if(ok) ok.remove(); }, 3000);
        } else {
            alert('儲存失敗: ' + (res.error || '未知錯誤'));
            btn.disabled = false;
            btn.textContent = '儲存';
        }
    };
    xhr.onerror = function(){ alert('網路錯誤'); btn.disabled = false; btn.textContent = '儲存'; };
    xhr.send(JSON.stringify({so_date: newDate}));
}

// ===== 品項備註原地編輯 =====
(function(){
    var CSRF_NOTE = '<?= e(Session::getCsrfToken()) ?>';
    document.addEventListener('click', function(e) {
        var span = e.target.closest('.item-note-edit');
        if (!span) return;
        if (span.querySelector('input')) return; // 已在編輯
        var itemId = span.dataset.itemId;
        var oldNote = span.dataset.note || '';
        var input = document.createElement('input');
        input.type = 'text';
        input.value = oldNote;
        input.maxLength = 500;
        input.style.cssText = 'width:200px;padding:2px 6px;font-size:.78rem;border:1px solid #1976d2;border-radius:3px;color:#333;background:#fff';
        input.placeholder = '備註';
        span.innerHTML = '';
        span.appendChild(input);
        input.focus();
        input.select();

        var done = false;
        function save() {
            if (done) return; done = true;
            var newNote = input.value;
            if (newNote === oldNote) { restore(oldNote); return; }
            var fd = new FormData();
            fd.append('item_id', itemId);
            fd.append('note', newNote);
            var xhr = new XMLHttpRequest();
            xhr.open('POST', '/stock_outs.php?action=update_item_note');
            xhr.setRequestHeader('X-CSRF-Token', CSRF_NOTE);
            xhr.onload = function() {
                try {
                    var data = JSON.parse(xhr.responseText);
                    if (data.error) { alert(data.error); restore(oldNote); }
                    else { span.dataset.note = data.note; restore(data.note); }
                } catch (err) { alert('儲存失敗'); restore(oldNote); }
            };
            xhr.onerror = function() { alert('網路錯誤'); restore(oldNote); };
            xhr.send(fd);
        }
        function cancel() {
            if (done) return; done = true;
            restore(oldNote);
        }
        function restore(note) {
            if (note) {
                span.innerHTML = '(' + escapeNoteHtml(note) + ')';
            } else {
                span.innerHTML = '<span style="color:#bbb;font-style:italic">+ 備註</span>';
            }
        }
        function escapeNoteHtml(s) {
            return String(s).replace(/[&<>"']/g, function(c){
                return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];
            });
        }
        input.addEventListener('blur', save);
        input.addEventListener('keydown', function(ev) {
            if (ev.key === 'Enter') { ev.preventDefault(); input.blur(); }
            else if (ev.key === 'Escape') { ev.preventDefault(); cancel(); }
        });
    });
})();
</script>

<style>
.form-row { display: flex; flex-wrap: wrap; gap: 12px; }
.form-row .form-group { flex: 1; min-width: 150px; }
.form-static { padding: 6px 0; color: var(--gray-700); }
</style>
