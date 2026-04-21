<?php
// 安全取值函式（避免 null 傳入 e() 造成 TypeError）
function pv($product, $field, $default = '') {
    if (!$product) return $default;
    return isset($product[$field]) && $product[$field] !== null ? (string)$product[$field] : $default;
}
?>
<div class="d-flex justify-between align-center flex-wrap gap-1 mb-2">
    <h2><?= $product ? '編輯產品' : '新增產品' ?></h2>
    <a href="<?= $product ? '/products.php?action=view&id=' . (int)$product['id'] : '/products.php' ?>" class="btn btn-outline btn-sm">返回</a>
</div>

<div class="card" style="max-width:900px">
    <form method="POST" action="/products.php?action=<?= $product ? 'edit&id=' . (int)$product['id'] : 'create' ?>" enctype="multipart/form-data">
        <?= csrf_field() ?>

        <div class="form-grid-2">
            <div class="form-group">
                <label>產品名稱 <span class="text-danger">*</span></label>
                <input type="text" name="name" class="form-control" value="<?= e(pv($product, 'name')) ?>" required>
            </div>
            <div class="form-group">
                <label>型號</label>
                <input type="text" name="model" class="form-control" value="<?= e(pv($product, 'model')) ?>">
            </div>
        </div>

        <div class="form-grid-2">
            <div class="form-group">
                <label>廠商型號</label>
                <input type="text" name="vendor_model" class="form-control" value="<?= e(pv($product, 'vendor_model')) ?>" placeholder="廠商原廠型號">
            </div>
            <div class="form-group">
                <label>品牌</label>
                <input type="text" name="brand" class="form-control" value="<?= e(pv($product, 'brand')) ?>">
            </div>
        </div>

        <div class="form-grid-2">
            <div class="form-group">
                <label>供應商</label>
                <input type="text" name="supplier" class="form-control" value="<?= e(pv($product, 'supplier')) ?>">
            </div>
        </div>

        <div class="form-grid-2">
            <div class="form-group">
                <label>分類</label>
                <select name="category_id" class="form-control">
                    <option value="">未分類</option>
                    <?php foreach ($allCategories as $cat): ?>
                    <option value="<?= (int)$cat['id'] ?>" <?= ($product && isset($product['category_id']) && $product['category_id'] == $cat['id']) ? 'selected' : '' ?>>
                        <?= e($cat['full_path']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>單位</label>
                <input type="text" name="unit" class="form-control" value="<?= e(pv($product, 'unit', '台')) ?>" placeholder="台">
            </div>
        </div>

        <div class="form-grid-2">
            <div class="form-group">
                <label>售價</label>
                <input type="number" name="price" class="form-control" value="<?= (int)pv($product, 'price', '0') ?>" min="0">
            </div>
            <div class="form-group">
                <label>成本 <span style="color:var(--gray-400);font-size:.8rem">(箱裝填整箱成本)</span></label>
                <input type="number" name="cost" id="prodCost" class="form-control" value="<?= (int)pv($product, 'cost', '0') ?>" min="0" oninput="calcCostPerUnit()">
            </div>
        </div>

        <div class="form-grid-3">
            <div class="form-group">
                <label>包裝單位 <span style="color:var(--gray-400);font-size:.8rem">(非箱裝留空)</span></label>
                <?php $curPackUnit = pv($product, 'pack_unit', ''); ?>
                <select name="pack_unit" id="prodPackUnit" class="form-control">
                    <option value="">— 無包裝單位 —</option>
                    <?php foreach (array('箱','卷','包','盒') as $pu): ?>
                    <option value="<?= $pu ?>" <?= $curPackUnit === $pu ? 'selected' : '' ?>><?= $pu ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>箱／卷／包／盒數量 <span style="color:var(--gray-400);font-size:.8rem">(如 305/箱，非箱裝留空)</span></label>
                <input type="number" name="pack_qty" id="prodPackQty" class="form-control" value="<?= pv($product, 'pack_qty') ? (float)pv($product, 'pack_qty') : '' ?>" min="0" step="0.01" placeholder="非箱裝留空" oninput="calcCostPerUnit()">
            </div>
            <div class="form-group">
                <label>每單位成本 <span style="color:var(--gray-400);font-size:.8rem">(自動計算)</span></label>
                <input type="text" id="prodCostPerUnit" class="form-control" value="<?= pv($product, 'cost_per_unit') ? number_format((float)pv($product, 'cost_per_unit'), 2) : '' ?>" readonly style="background:#f5f5f5">
                <input type="hidden" name="cost_per_unit" id="prodCostPerUnitHidden" value="<?= pv($product, 'cost_per_unit') ?: '' ?>">
            </div>
        </div>

        <div class="form-grid-2">
            <div class="form-group">
                <label>零售價</label>
                <input type="number" name="retail_price" class="form-control" value="<?= (int)pv($product, 'retail_price', '0') ?>" min="0">
            </div>
            <div class="form-group">
                <label>工資</label>
                <input type="number" name="labor_cost" class="form-control" value="<?= (int)pv($product, 'labor_cost', '0') ?>" min="0">
            </div>
        </div>

        <div class="form-group">
            <label>規格</label>
            <textarea name="specifications" class="form-control" rows="2"><?= e(pv($product, 'specifications')) ?></textarea>
        </div>

        <div class="form-group">
            <label>說明</label>
            <textarea name="description" class="form-control" rows="3"><?= e(pv($product, 'description')) ?></textarea>
        </div>

        <!-- 產品圖片 -->
        <div class="form-group">
            <label>產品圖片</label>
            <?php if ($product && !empty($product['image'])): ?>
            <div class="mb-1" style="display:flex;align-items:center;gap:8px">
                <img src="<?= e($product['image']) ?>" alt="" style="width:80px;height:80px;object-fit:contain;border:1px solid #eee;border-radius:4px" onerror="this.style.display='none'">
                <span class="text-muted" style="font-size:.8rem;word-break:break-all"><?= e($product['image']) ?></span>
            </div>
            <?php endif; ?>
            <input type="file" name="image_file" class="form-control" accept="image/*" style="padding:6px">
            <div style="font-size:.8rem;color:var(--gray-500);margin-top:4px">或輸入圖片網址：</div>
            <input type="text" name="image_url" class="form-control" value="" placeholder="https://...">
        </div>

        <!-- 規格書 -->
        <div class="form-group">
            <label>規格書</label>
            <?php if ($product && !empty($product['datasheet'])): ?>
            <div class="mb-1">
                <a href="<?= e($product['datasheet']) ?>" target="_blank" class="btn btn-outline btn-sm">📄 目前規格書</a>
            </div>
            <?php endif; ?>
            <input type="file" name="datasheet_file" class="form-control" accept=".pdf,.doc,.docx,.xls,.xlsx" style="padding:6px">
            <div style="font-size:.8rem;color:var(--gray-500);margin-top:4px">或輸入規格書網址：</div>
            <input type="text" name="datasheet_url" class="form-control" value="" placeholder="https://...">
        </div>

        <div class="form-group">
            <label>保固說明</label>
            <input type="text" name="warranty_text" class="form-control" value="<?= e(pv($product, 'warranty_text')) ?>">
        </div>

        <?php if ($product): ?>
        <div class="form-group">
            <label>歷史價格</label>
            <table class="price-history-table" id="priceHistoryTable">
                <thead>
                    <tr><th>起始日期</th><th>結束日期</th><th>進貨成本</th><th style="width:40px"></th></tr>
                </thead>
                <tbody>
                <?php
                $priceHistory = array();
                try {
                    $phStmt = Database::getInstance()->prepare('SELECT * FROM product_price_history WHERE product_id = ? ORDER BY date_from DESC');
                    $phStmt->execute(array($product['id']));
                    $priceHistory = $phStmt->fetchAll(PDO::FETCH_ASSOC);
                } catch (Exception $e) {}
                foreach ($priceHistory as $idx => $ph):
                ?>
                <tr>
                    <td><input type="date" name="ph_date_from[<?= $idx ?>]" class="form-control" value="<?= e($ph['date_from']) ?>"></td>
                    <td><input type="date" name="ph_date_to[<?= $idx ?>]" class="form-control" value="<?= e($ph['date_to'] ?? '') ?>"></td>
                    <td><input type="number" name="ph_cost[<?= $idx ?>]" class="form-control" value="<?= (int)$ph['cost'] ?>" min="0"></td>
                    <td>
                        <input type="hidden" name="ph_id[<?= $idx ?>]" value="<?= (int)$ph['id'] ?>">
                        <button type="button" class="btn btn-sm" style="color:var(--danger);padding:2px 6px" onclick="this.closest('tr').remove()">✕</button>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <button type="button" class="btn btn-outline btn-sm mt-1" onclick="addPriceRow()">+ 新增一列</button>
        </div>
        <?php endif; ?>

        <div class="form-group" style="display:flex;gap:24px;flex-wrap:wrap;align-items:center">
            <?php if ($product): ?>
            <label class="checkbox-label" style="cursor:pointer">
                <input type="checkbox" name="is_active" value="1" <?= (!empty($product['is_active'])) ? 'checked' : '' ?>>
                啟用
            </label>
            <?php endif; ?>
            <label class="checkbox-label" style="cursor:pointer">
                <input type="checkbox" name="discontinue_when_empty" value="1" <?= (!empty($product['discontinue_when_empty'])) ? 'checked' : '' ?>>
                <span style="color:#c5221f;font-weight:600">庫存用完不再進貨</span>
                <small style="color:#888;margin-left:4px">（庫存歸 0 時自動停用，報價單禁止選用）</small>
            </label>
        </div>

        <div class="d-flex gap-1 mt-2">
            <button type="submit" class="btn btn-primary"><?= $product ? '更新' : '新增' ?></button>
            <a href="<?= $product ? '/products.php?action=view&id=' . (int)$product['id'] : '/products.php' ?>" class="btn btn-outline">取消</a>
        </div>
    </form>
</div>

<style>
.form-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.form-grid-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px; }
@media (max-width: 480px) { .form-grid-2, .form-grid-3 { grid-template-columns: 1fr; } }
.checkbox-label { display: flex; align-items: center; gap: 6px; cursor: pointer; }
.price-history-table { width: 100%; border-collapse: collapse; }
.price-history-table th { font-size: .75rem; color: var(--gray-500); font-weight: 500; padding: 4px 4px 8px; text-align: left; }
.price-history-table td { padding: 2px 4px; }
.price-history-table .form-control { padding: 6px 8px; font-size: .85rem; }
</style>

<script>
var phIndex = <?= isset($priceHistory) ? count($priceHistory) : 0 ?>;
function addPriceRow() {
    var tbody = document.querySelector('#priceHistoryTable tbody');
    var tr = document.createElement('tr');
    tr.innerHTML = '<td><input type="date" name="ph_date_from[' + phIndex + ']" class="form-control" required></td>' +
        '<td><input type="date" name="ph_date_to[' + phIndex + ']" class="form-control"></td>' +
        '<td><input type="number" name="ph_cost[' + phIndex + ']" class="form-control" min="0" required></td>' +
        '<td><input type="hidden" name="ph_id[' + phIndex + ']" value="0"><button type="button" class="btn btn-sm" style="color:var(--danger);padding:2px 6px" onclick="this.closest(\'tr\').remove()">✕</button></td>';
    tbody.appendChild(tr);
    phIndex++;
}
function calcCostPerUnit() {
    var cost = parseFloat(document.getElementById('prodCost').value) || 0;
    var packQty = parseFloat(document.getElementById('prodPackQty').value) || 0;
    var display = document.getElementById('prodCostPerUnit');
    var hidden = document.getElementById('prodCostPerUnitHidden');
    if (packQty > 0) {
        var cpu = cost / packQty;
        display.value = '$' + cpu.toFixed(2) + ' / 單位';
        hidden.value = cpu.toFixed(4);
    } else {
        display.value = cost > 0 ? '$' + cost.toFixed(2) + ' / 單位（非箱裝）' : '';
        hidden.value = cost > 0 ? cost.toFixed(4) : '';
    }
}
calcCostPerUnit();
</script>
