<div class="d-flex justify-between align-center flex-wrap gap-1 mb-2">
    <h2>入庫 / 出庫</h2>
    <?= back_button('/inventory.php') ?>
</div>

<div class="card" style="max-width:600px">
    <form method="POST" action="/inventory.php?action=adjust" id="adjustForm">
        <?= csrf_field() ?>

        <div class="form-group">
            <label>異動類型 <span class="text-danger">*</span></label>
            <div class="d-flex gap-1">
                <label class="radio-card" style="flex:1">
                    <input type="radio" name="adjust_type" value="manual_in" checked>
                    <span class="radio-card-body" style="text-align:center;padding:12px">
                        <span style="font-size:1.5rem">📥</span><br>
                        <strong>入庫</strong>
                    </span>
                </label>
                <label class="radio-card" style="flex:1">
                    <input type="radio" name="adjust_type" value="manual_out">
                    <span class="radio-card-body" style="text-align:center;padding:12px">
                        <span style="font-size:1.5rem">📤</span><br>
                        <strong>出庫</strong>
                    </span>
                </label>
            </div>
        </div>

        <div class="form-group">
            <label>商品 <span class="text-danger">*</span></label>
            <input type="hidden" name="product_id" id="productId" required>
            <input type="text" id="productSearch" class="form-control" placeholder="輸入商品名稱或型號搜尋..." autocomplete="off">
            <div id="productResults" class="search-results" style="display:none"></div>
            <div id="productSelected" class="selected-item" style="display:none"></div>
        </div>

        <div class="form-group">
            <label>倉庫 <span class="text-danger">*</span></label>
            <select name="warehouse_id" class="form-control" required>
                <option value="">請選擇</option>
                <?php foreach ($warehouses as $w): ?>
                <option value="<?= e($w['id']) ?>"><?= e($w['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label>數量 <span class="text-danger">*</span></label>
            <input type="number" name="quantity" class="form-control" min="1" required>
        </div>

        <div class="form-group">
            <label>備註</label>
            <textarea name="note" class="form-control" rows="2" placeholder="異動原因"></textarea>
        </div>

        <div class="d-flex gap-1">
            <button type="submit" class="btn btn-primary">確認</button>
            <a href="/inventory.php" class="btn btn-outline">取消</a>
        </div>
    </form>
</div>

<style>
.radio-card { cursor: pointer; }
.radio-card input { display: none; }
.radio-card-body { border: 2px solid var(--gray-200); border-radius: var(--radius); display: block; transition: all .2s; }
.radio-card input:checked + .radio-card-body { border-color: var(--primary); background: #e3f2fd; }
.search-results { position: absolute; z-index: 100; background: #fff; border: 1px solid var(--gray-200); border-radius: var(--radius); max-height: 200px; overflow-y: auto; width: 100%; box-shadow: 0 4px 12px rgba(0,0,0,.1); }
.search-results .result-item { padding: 8px 12px; cursor: pointer; border-bottom: 1px solid var(--gray-100); }
.search-results .result-item:hover { background: var(--gray-50); }
.selected-item { padding: 8px 12px; background: #e8f5e9; border-radius: var(--radius); margin-top: 6px; display: flex; justify-content: space-between; align-items: center; }
.form-group { position: relative; }
</style>

<script>
var searchTimer = null;
var productSearchEl = document.getElementById('productSearch');
var productResultsEl = document.getElementById('productResults');
var productSelectedEl = document.getElementById('productSelected');
var productIdEl = document.getElementById('productId');

productSearchEl.addEventListener('input', function() {
    clearTimeout(searchTimer);
    var q = this.value.trim();
    if (q.length < 1) { productResultsEl.style.display = 'none'; return; }
    searchTimer = setTimeout(function() {
        fetch('/inventory.php?action=ajax_search_products&q=' + encodeURIComponent(q))
            .then(function(r) { return r.json(); })
            .then(function(data) {
                productResultsEl.innerHTML = '';
                if (data.length === 0) {
                    productResultsEl.innerHTML = '<div class="result-item" style="color:var(--gray-400)">無搜尋結果</div>';
                } else {
                    data.forEach(function(p) {
                        var div = document.createElement('div');
                        div.className = 'result-item';
                        div.innerHTML = '<strong>' + escHtml(p.name) + '</strong>' +
                            (p.model ? ' <span style="color:var(--gray-500);font-size:.85rem">' + escHtml(p.model) + '</span>' : '') +
                            (p.unit ? ' <span style="color:var(--gray-400);font-size:.8rem">(' + escHtml(p.unit) + ')</span>' : '');
                        div.onclick = function() { selectProduct(p); };
                        productResultsEl.appendChild(div);
                    });
                }
                productResultsEl.style.display = 'block';
            });
    }, 300);
});

function selectProduct(p) {
    productIdEl.value = p.id;
    productSearchEl.style.display = 'none';
    productResultsEl.style.display = 'none';
    productSelectedEl.innerHTML = '<span><strong>' + escHtml(p.name) + '</strong>' +
        (p.model ? ' (' + escHtml(p.model) + ')' : '') + '</span>' +
        '<a href="javascript:void(0)" onclick="clearProduct()" style="color:var(--danger)">&times;</a>';
    productSelectedEl.style.display = 'flex';
}

function clearProduct() {
    productIdEl.value = '';
    productSearchEl.value = '';
    productSearchEl.style.display = '';
    productSelectedEl.style.display = 'none';
    productSearchEl.focus();
}

function escHtml(s) {
    var div = document.createElement('div');
    div.appendChild(document.createTextNode(s));
    return div.innerHTML;
}

document.addEventListener('click', function(e) {
    if (!productResultsEl.contains(e.target) && e.target !== productSearchEl) {
        productResultsEl.style.display = 'none';
    }
});
</script>
