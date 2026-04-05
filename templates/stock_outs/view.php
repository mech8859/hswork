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
$isPending = ($record['status'] === 'pending' || $record['status'] === '待確認');
$isPartial = ($record['status'] === '部分出庫');
$canConfirmItems = $isPending || $isPartial;
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
    <div class="d-flex gap-1 flex-wrap">
        <?php if ($canManage && $canConfirmItems): ?>
        <button type="button" class="btn btn-sm" style="background:var(--success);color:#fff" onclick="confirmCheckedItems()">確認勾選出庫</button>
        <a href="/stock_outs.php?action=cancel&id=<?= $record['id'] ?>&csrf_token=<?= e(Session::getCsrfToken()) ?>" class="btn btn-outline btn-sm" style="color:var(--danger);border-color:var(--danger)" onclick="return confirm('確定取消此出庫單？已出庫品項紀錄將保留。')">取消出庫</a>
        <?php endif; ?>
        <?php if ($canManage && !$isPending && !empty($record['has_return_material'])): ?>
        <a href="/stock_ins.php?action=create_from_return&stock_out_id=<?= $record['id'] ?>&csrf_token=<?= e(Session::getCsrfToken()) ?>" class="btn btn-sm" style="background:#FF9800;color:#fff" onclick="return confirm('確認將餘料建立入庫單？')">餘料入庫</a>
        <?php endif; ?>
        <button type="button" class="btn btn-outline btn-sm no-print" onclick="window.print()">列印</button>
        <?= back_button('/stock_outs.php') ?>
    </div>
</div>

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
            <label>出庫日期</label>
            <div class="form-static"><?= e($soDate) ?></div>
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
    <?php
    $items = isset($record['items']) ? $record['items'] : array();
    ?>
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
                <th style="width:30px">#</th><th>品名</th><th>型號</th><th>單位</th><th class="text-right">庫存</th><th class="text-right">需求</th><?php if ($canConfirmItems): ?><th class="text-right print-hide-col" style="width:80px">出庫數量</th><?php endif; ?><th class="text-right">單價</th><th class="text-right">小計</th><th style="width:60px">狀態</th>
            </tr></thead>
            <tbody>
                <?php $totalCost = 0; ?>
                <?php foreach ($items as $idx => $item):
                    $productName = isset($item['product_name']) ? $item['product_name'] : (isset($item['db_product_name']) ? $item['db_product_name'] : '-');
                    $productModel = isset($item['product_model']) ? $item['product_model'] : (isset($item['db_model']) ? $item['db_model'] : (isset($item['model']) ? $item['model'] : '-'));
                    $unitDisplay = isset($item['unit']) ? $item['unit'] : '-';
                    $qty = (int)(isset($item['quantity']) ? $item['quantity'] : 0);
                    $requestQty = (int)(isset($item['request_qty']) && $item['request_qty'] > 0 ? $item['request_qty'] : $qty);
                    $unitCost = (int)(isset($item['unit_cost']) ? $item['unit_cost'] : (isset($item['unit_price']) ? $item['unit_price'] : 0));
                    $subtotal = $qty * $unitCost;
                    $isSpare = !empty($item['is_spare']);
                    $itemNote = isset($item['note']) ? $item['note'] : '';
                    $pid = !empty($item['product_id']) ? (int)$item['product_id'] : 0;
                    $itemStock = isset($stockMap[$pid]) ? (int)$stockMap[$pid] : 0;
                    $stockDisplay = isset($stockMap[$pid]) ? $stockMap[$pid] : '-';
                    $stockColor = ($itemStock > 0) ? '#2e7d32' : '#c62828';
                    $hasStock = ($itemStock >= $requestQty && $itemStock > 0);
                ?>
                <tr<?= $isSpare ? ' style="background:#fff8e1"' : '' ?>>
                    <?php if ($canManage && $canConfirmItems): ?>
                    <td class="print-hide-col"><?php if (empty($item['is_confirmed']) && $hasStock): ?><input type="checkbox" class="item-check" value="<?= (int)$item['id'] ?>" data-qty="<?= $qty ?>"><?php endif; ?></td>
                    <?php endif; ?>
                    <td><?= $idx + 1 ?></td>
                    <td>
                        <?= e($productName) ?>
                        <?php if ($isSpare): ?>
                        <span style="background:#ff9800;color:#fff;padding:1px 6px;border-radius:3px;font-size:.7rem;margin-left:4px;font-weight:600">備品</span>
                        <?php endif; ?>
                        <?php if ($itemNote && $itemNote !== '備品'): ?>
                        <span style="color:#888;font-size:.75rem;margin-left:4px">(<?= e($itemNote) ?>)</span>
                        <?php endif; ?>
                    </td>
                    <td><?= e($productModel) ?></td>
                    <td><?= e($unitDisplay) ?></td>
                    <td class="text-right" style="color:<?= $stockColor ?>"><?= $stockDisplay ?></td>
                    <td class="text-right"><?= $requestQty ?></td>
                    <?php if ($canConfirmItems): ?>
                    <td class="text-right print-hide-col">
                        <?php if (empty($item['is_confirmed']) && $hasStock): ?>
                        <input type="number" class="form-control confirm-qty" data-item-id="<?= (int)$item['id'] ?>" style="width:70px;display:inline-block;text-align:right;padding:2px 6px;font-size:.85rem" min="1" max="<?= $requestQty ?>" value="<?= $requestQty ?>">
                        <?php elseif (!empty($item['is_confirmed'])): ?>
                        <?= $qty ?>
                        <?php else: ?>
                        -
                        <?php endif; ?>
                    </td>
                    <?php endif; ?>
                    <td class="text-right">$<?= number_format($unitCost) ?></td>
                    <td class="text-right">$<?= number_format($subtotal) ?></td>
                    <td>
                        <?php if (!empty($item['is_confirmed'])): ?>
                        <span style="color:#1565c0;font-weight:600;font-size:.8rem">已出庫</span>
                        <?php elseif (!$hasStock): ?>
                        <span style="color:#c62828;font-size:.75rem">庫存不足</span>
                        <?php else: ?>
                        <span style="color:#2e7d32;font-size:.8rem">待出庫</span>
                        <?php endif; ?>
                        <?php if ($isSpare && $canManage && $canConfirmItems && empty($item['is_confirmed'])): ?>
                        <button type="button" class="btn btn-danger btn-sm" onclick="removeSpare(<?= (int)$item['id'] ?>)" title="移除備品" style="padding:2px 6px;font-size:.75rem;margin-left:4px">&times;</button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php $totalCost += $subtotal; ?>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <?php
                    $colSpan = 7;
                    if ($canManage && $canConfirmItems) $colSpan++; // checkbox
                    if ($canConfirmItems) $colSpan++; // 出庫數量
                    ?>
                    <td colspan="<?= $colSpan ?>" class="text-right"><strong>合計</strong></td>

                    <td class="text-right"><strong>$<?= number_format($totalCost) ?></strong></td>
                    <?php if ($canManage && $isPending): ?><td></td><?php endif; ?>
                </tr>
            </tfoot>
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
</div>

<style>
.form-row { display: flex; flex-wrap: wrap; gap: 12px; }
.form-row .form-group { flex: 1; min-width: 150px; }
.form-static { padding: 6px 0; color: var(--gray-700); }
</style>
