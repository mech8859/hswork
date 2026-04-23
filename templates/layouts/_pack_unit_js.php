<?php
/**
 * 包裝單位換算共用 JS（入庫/出庫/進貨/退貨 4 個單據共用）
 *
 * 使用方式：
 * 1. 表單每列的「單位」td 放：
 *    <select name="items[X][display_unit]" class="form-control pack-unit-select"></select>
 *    <input type="hidden" name="items[X][unit]" class="pack-unit-hidden-unit" value="">
 *    <input type="hidden" name="items[X][input_unit]" class="pack-unit-hidden-input-unit" value="">
 *    <input type="hidden" name="items[X][input_qty]" class="pack-unit-hidden-input-qty" value="">
 *
 * 2. 數量欄位：
 *    <input type="number" class="pack-unit-qty" oninput="hswPackUnitRowSync(this)">
 *    <input type="hidden" name="items[X][quantity]" class="pack-unit-hidden-qty" value="">
 *    （若欄位名稱不是 quantity 而是 received_qty，hidden 的 name 改成對應）
 *
 * 3. 每列外層 tr 要加 class="pack-unit-row"
 *
 * 4. 選產品後呼叫：hswPackUnitSetupRow(rowElement, product)
 *    其中 product 需含：unit, pack_unit, pack_qty
 *
 * 5. 表單 onsubmit 呼叫：hswPackUnitPrepareSubmit(form)
 */
?>
<style>
.pack-unit-row .pack-unit-select { width:auto; min-width:60px; padding:4px 4px; }
.pack-unit-row .pack-unit-qty { width:100%; }
.pack-unit-row .pack-unit-hint { font-size:.7rem; color:var(--gray-500); margin-top:2px; }
</style>
<script>
// 設定某列的單位 select 選項（依產品 pack_unit / pack_qty）
function hswPackUnitSetupRow(row, product) {
    if (!row) return;
    var unitSel = row.querySelector('.pack-unit-select');
    if (!unitSel) return;
    var base = (product && product.unit) ? String(product.unit) : '';
    var pack = (product && product.pack_unit) ? String(product.pack_unit) : '';
    var packQty = (product && product.pack_qty) ? parseFloat(product.pack_qty) : 0;
    unitSel.dataset.baseUnit = base;
    unitSel.dataset.packUnit = pack;
    unitSel.dataset.packQty = packQty > 0 ? packQty : '';

    unitSel.innerHTML = '';
    if (base) {
        var o = document.createElement('option');
        o.value = base;
        o.textContent = base;
        unitSel.appendChild(o);
    }
    if (pack && packQty > 0) {
        var o2 = document.createElement('option');
        o2.value = pack;
        o2.textContent = pack + ' (1' + pack + '=' + packQty + base + ')';
        unitSel.appendChild(o2);
    }
    // 單位變更時觸發 hint 更新
    unitSel.onchange = function() { hswPackUnitRowSync(row.querySelector('.pack-unit-qty')); };
    hswPackUnitRowSync(row.querySelector('.pack-unit-qty'));
}

// 使用者輸入數量或切換單位時，更新 hint 顯示（預覽換算結果）
function hswPackUnitRowSync(qtyInp) {
    if (!qtyInp) return;
    var row = qtyInp.closest('.pack-unit-row');
    if (!row) return;
    var unitSel = row.querySelector('.pack-unit-select');
    var hintEl = row.querySelector('.pack-unit-hint');
    if (!unitSel) return;
    var chosen = unitSel.value;
    var baseUnit = unitSel.dataset.baseUnit || '';
    var packUnit = unitSel.dataset.packUnit || '';
    var packQty = parseFloat(unitSel.dataset.packQty || '0');
    var rawQty = parseFloat(qtyInp.value || '0');
    if (hintEl) {
        if (chosen && packUnit && chosen === packUnit && packQty > 0 && rawQty > 0) {
            hintEl.textContent = '= ' + (rawQty * packQty) + ' ' + baseUnit;
            hintEl.style.display = '';
        } else {
            hintEl.textContent = '';
            hintEl.style.display = 'none';
        }
    }
}

// 送出前把每列換算成基本單位，填好 hidden inputs
function hswPackUnitPrepareSubmit(form) {
    if (!form) return true;
    var rows = form.querySelectorAll('.pack-unit-row');
    for (var i = 0; i < rows.length; i++) {
        var row = rows[i];
        var unitSel = row.querySelector('.pack-unit-select');
        var qtyInp = row.querySelector('.pack-unit-qty');
        var unitH = row.querySelector('.pack-unit-hidden-unit');
        var qtyH = row.querySelector('.pack-unit-hidden-qty');
        var inpUnitH = row.querySelector('.pack-unit-hidden-input-unit');
        var inpQtyH = row.querySelector('.pack-unit-hidden-input-qty');
        if (!unitSel || !qtyInp || !unitH || !qtyH) continue;

        var chosen = unitSel.value;
        var baseUnit = unitSel.dataset.baseUnit || '';
        var packUnit = unitSel.dataset.packUnit || '';
        var packQty = parseFloat(unitSel.dataset.packQty || '0');
        var rawQty = parseFloat(qtyInp.value || '0');

        if (chosen && packUnit && chosen === packUnit && packQty > 0) {
            unitH.value = baseUnit;
            qtyH.value = rawQty * packQty;
            if (inpUnitH) inpUnitH.value = packUnit;
            if (inpQtyH) inpQtyH.value = rawQty;
        } else {
            unitH.value = chosen || baseUnit;
            qtyH.value = rawQty;
            if (inpUnitH) inpUnitH.value = '';
            if (inpQtyH) inpQtyH.value = '';
        }
    }
    return true;
}

// 編輯模式：以 select 上的 data-base-unit/data-pack-unit/data-pack-qty/data-preselect 初始化
// 表單載入後呼叫一次，自動套用每列
function hswPackUnitInitFromDOM(container) {
    container = container || document;
    var selects = container.querySelectorAll('.pack-unit-select');
    for (var i = 0; i < selects.length; i++) {
        var sel = selects[i];
        var base = sel.dataset.baseUnit || '';
        var pack = sel.dataset.packUnit || '';
        var packQty = parseFloat(sel.dataset.packQty || '0');
        var preselect = sel.dataset.preselect || '';
        if (!base && !pack) continue;
        var row = sel.closest('.pack-unit-row');
        if (!row) continue;
        hswPackUnitSetupRow(row, { unit: base, pack_unit: pack, pack_qty: packQty });
        if (preselect) {
            var found = false;
            for (var j = 0; j < sel.options.length; j++) {
                if (sel.options[j].value === preselect) { sel.value = preselect; found = true; break; }
            }
            if (!found && preselect && !base) {
                // 後備：加上 preselect 當選項
                var opt = document.createElement('option');
                opt.value = preselect;
                opt.textContent = preselect;
                sel.appendChild(opt);
                sel.value = preselect;
            }
        }
        hswPackUnitRowSync(row.querySelector('.pack-unit-qty'));
    }
}

document.addEventListener('DOMContentLoaded', function() {
    hswPackUnitInitFromDOM(document);
});

// 取得列的基本單位當前「基本單位數量」（for 即時檢查庫存）
function hswPackUnitGetBaseQty(row) {
    if (!row) return 0;
    var unitSel = row.querySelector('.pack-unit-select');
    var qtyInp = row.querySelector('.pack-unit-qty');
    if (!unitSel || !qtyInp) return 0;
    var chosen = unitSel.value;
    var packUnit = unitSel.dataset.packUnit || '';
    var packQty = parseFloat(unitSel.dataset.packQty || '0');
    var rawQty = parseFloat(qtyInp.value || '0');
    if (chosen && packUnit && chosen === packUnit && packQty > 0) {
        return rawQty * packQty;
    }
    return rawQty;
}
</script>
