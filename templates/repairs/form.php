<h2><?= $repair ? '編輯維修單 - ' . e($repair['repair_number']) : '新增維修單' ?></h2>

<form method="POST" class="mt-2" id="repairForm">
    <?= csrf_field() ?>

    <div class="card">
        <div class="card-header">客戶資料</div>
        <div class="form-row">
            <div class="form-group">
                <label>客戶名稱 *</label>
                <input type="text" name="customer_name" class="form-control" value="<?= e($repair['customer_name'] ?? '') ?>" required>
            </div>
            <div class="form-group">
                <label>電話</label>
                <input type="text" name="customer_phone" class="form-control" value="<?= e($repair['customer_phone'] ?? '') ?>">
            </div>
        </div>
        <div class="form-group">
            <label>地址</label>
            <input type="text" name="customer_address" class="form-control" value="<?= e($repair['customer_address'] ?? '') ?>">
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>維修日期 *</label>
                <input type="date" max="2099-12-31" name="repair_date" class="form-control" value="<?= e($repair['repair_date'] ?? date('Y-m-d')) ?>" required>
            </div>
            <div class="form-group">
                <label>據點 *</label>
                <select name="branch_id" class="form-control" required>
                    <?php foreach ($branches as $b): ?>
                    <option value="<?= $b['id'] ?>" <?= ($repair['branch_id'] ?? Auth::user()['branch_id']) == $b['id'] ? 'selected' : '' ?>><?= e($b['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>工程師</label>
                <select name="engineer_id" class="form-control">
                    <option value="">請選擇</option>
                    <?php foreach ($engineers as $eng): ?>
                    <option value="<?= $eng['id'] ?>" <?= ($repair['engineer_id'] ?? '') == $eng['id'] ? 'selected' : '' ?>><?= e($eng['real_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="form-group">
            <label>備註</label>
            <textarea name="note" class="form-control" rows="2"><?= e($repair['note'] ?? '') ?></textarea>
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex justify-between align-center">
            <span>維修項目</span>
            <button type="button" class="btn btn-primary btn-sm" onclick="addItemRow()">+ 新增項目</button>
        </div>
        <div class="table-responsive">
            <table class="table" id="itemsTable">
                <thead><tr><th>項目說明</th><th style="width:80px">數量</th><th style="width:100px">單價</th><th style="width:100px">金額</th><th style="width:50px"></th></tr></thead>
                <tbody id="itemsBody">
                    <?php if ($repair && !empty($repair['items'])): ?>
                        <?php foreach ($repair['items'] as $idx => $item): ?>
                        <tr>
                            <td><input type="text" name="items[<?= $idx ?>][description]" class="form-control" value="<?= e($item['description']) ?>"></td>
                            <td><input type="number" name="items[<?= $idx ?>][quantity]" class="form-control item-qty" value="<?= (int)$item['quantity'] ?>" min="1" onchange="calcRow(this)"></td>
                            <td><input type="number" name="items[<?= $idx ?>][unit_price]" class="form-control item-price" value="<?= (int)$item['unit_price'] ?>" min="0" onchange="calcRow(this)"></td>
                            <td class="item-amount text-right">$<?= number_format($item['amount']) ?></td>
                            <td><button type="button" class="btn btn-danger btn-sm" onclick="this.closest('tr').remove();calcTotal()">X</button></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td><input type="text" name="items[0][description]" class="form-control" placeholder="維修項目說明"></td>
                            <td><input type="number" name="items[0][quantity]" class="form-control item-qty" value="1" min="1" onchange="calcRow(this)"></td>
                            <td><input type="number" name="items[0][unit_price]" class="form-control item-price" value="0" min="0" onchange="calcRow(this)"></td>
                            <td class="item-amount text-right">$0</td>
                            <td><button type="button" class="btn btn-danger btn-sm" onclick="this.closest('tr').remove();calcTotal()">X</button></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
                <tfoot>
                    <tr style="font-weight:600; background:var(--gray-100);">
                        <td colspan="3" class="text-right">合計</td>
                        <td id="totalAmount" class="text-right">$0</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <div class="d-flex gap-1 mt-2">
        <button type="submit" class="btn btn-primary"><?= $repair ? '儲存變更' : '建立維修單' ?></button>
        <a href="/repairs.php" class="btn btn-outline">取消</a>
    </div>
</form>

<script>
var itemIdx = <?= $repair && !empty($repair['items']) ? count($repair['items']) : 1 ?>;
function addItemRow() {
    var tbody = document.getElementById('itemsBody');
    var tr = document.createElement('tr');
    tr.innerHTML = '<td><input type="text" name="items['+itemIdx+'][description]" class="form-control" placeholder="維修項目說明"></td>'
        + '<td><input type="number" name="items['+itemIdx+'][quantity]" class="form-control item-qty" value="1" min="1" onchange="calcRow(this)"></td>'
        + '<td><input type="number" name="items['+itemIdx+'][unit_price]" class="form-control item-price" value="0" min="0" onchange="calcRow(this)"></td>'
        + '<td class="item-amount text-right">$0</td>'
        + '<td><button type="button" class="btn btn-danger btn-sm" onclick="this.closest(\'tr\').remove();calcTotal()">X</button></td>';
    tbody.appendChild(tr);
    itemIdx++;
}
function calcRow(el) {
    var tr = el.closest('tr');
    var qty = parseInt(tr.querySelector('.item-qty').value) || 0;
    var price = parseInt(tr.querySelector('.item-price').value) || 0;
    tr.querySelector('.item-amount').textContent = '$' + (qty * price).toLocaleString();
    calcTotal();
}
function calcTotal() {
    var total = 0;
    document.querySelectorAll('#itemsBody tr').forEach(function(tr) {
        var qty = parseInt(tr.querySelector('.item-qty').value) || 0;
        var price = parseInt(tr.querySelector('.item-price').value) || 0;
        total += qty * price;
    });
    document.getElementById('totalAmount').textContent = '$' + total.toLocaleString();
}
calcTotal();
</script>

<style>
.form-row { display: flex; flex-wrap: wrap; gap: 12px; }
.form-row .form-group { flex: 1; min-width: 150px; }
</style>
