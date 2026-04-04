<?php
// 預填值：從 URL 參數或現有記錄
$prefillName = $record ? $record['contact_name'] : (isset($_GET['contact_name']) ? $_GET['contact_name'] : '');
$prefillTarget = $record ? $record['target_type'] : (isset($_GET['target_type']) ? $_GET['target_type'] : '');
$prefillCategory = $record ? $record['category'] : '';
$backUrl = !empty($_GET['contact_name']) ? '/transactions.php?action=contact&name=' . urlencode($_GET['contact_name']) : '/transactions.php';
?>
<div class="d-flex justify-between align-center flex-wrap gap-1 mb-2">
    <h2><?= $record ? '編輯交易' : '新增交易' ?><?= $prefillName && !$record ? ' — ' . e($prefillName) : '' ?></h2>
    <a href="<?= e($backUrl) ?>" class="btn btn-outline btn-sm">返回</a>
</div>

<form method="POST" id="txForm">
    <?= csrf_field() ?>

    <div class="card mb-2">
        <h3 class="mb-1">基本資訊</h3>
        <div class="form-row flex-wrap gap-1">
            <?php if ($record): ?>
            <div class="form-group">
                <label>登記編號</label>
                <input type="text" class="form-control" value="<?= e($record['register_no']) ?>" readonly>
            </div>
            <?php endif; ?>
            <div class="form-group">
                <label>登記日期 <span style="color:var(--danger)">*</span></label>
                <input type="date" name="register_date" class="form-control" required
                       value="<?= $record ? e($record['register_date']) : date('Y-m-d') ?>">
            </div>
            <div class="form-group">
                <label>交易對象 <span style="color:var(--danger)">*</span></label>
                <select name="target_type" class="form-control" required>
                    <option value="">請選擇</option>
                    <option value="employee" <?= $prefillTarget === 'employee' ? 'selected' : '' ?>>員工</option>
                    <option value="partner" <?= $prefillTarget === 'partner' ? 'selected' : '' ?>>合作夥伴</option>
                </select>
            </div>
            <div class="form-group">
                <label>交易分類 <span style="color:var(--danger)">*</span></label>
                <select name="category" class="form-control" required>
                    <option value="">請選擇</option>
                    <option value="purchase" <?= $prefillCategory === 'purchase' ? 'selected' : '' ?>>購買商品</option>
                    <option value="loan" <?= $prefillCategory === 'loan' ? 'selected' : '' ?>>員工借支</option>
                </select>
            </div>
            <div class="form-group" style="flex:1;min-width:200px">
                <label>姓名/廠商名稱 <span style="color:var(--danger)">*</span></label>
                <input type="text" name="contact_name" class="form-control" required
                       value="<?= e($prefillName) ?>" placeholder="輸入姓名或廠商名稱">
            </div>
        </div>
    </div>

    <div class="card mb-2">
        <div class="d-flex justify-between align-center mb-1">
            <h3>交易明細</h3>
            <button type="button" class="btn btn-primary btn-sm" onclick="addItemRow()">+ 新增明細</button>
        </div>

        <div class="table-responsive">
            <table class="table" id="itemsTable">
                <thead>
                    <tr>
                        <th style="min-width:120px">交易日期</th>
                        <th style="min-width:150px">交易內容</th>
                        <th style="min-width:120px">商品</th>
                        <th style="min-width:100px">總金額</th>
                        <th style="min-width:120px">預計付款日</th>
                        <th style="min-width:80px">已結清</th>
                        <th style="min-width:120px">備註</th>
                        <th style="width:50px"></th>
                    </tr>
                </thead>
                <tbody id="itemsBody">
                    <?php
                    $items = ($record && !empty($record['items'])) ? $record['items'] : array();
                    if (empty($items)) {
                        $items = array(array('trade_date' => '', 'description' => '', 'product' => '', 'amount' => '', 'due_date' => '', 'is_settled' => 0, 'note' => ''));
                    }
                    foreach ($items as $idx => $item):
                    ?>
                    <tr>
                        <td><input type="date" name="item_trade_date[]" class="form-control" value="<?= e($item['trade_date']) ?>"></td>
                        <td><input type="text" name="item_description[]" class="form-control" value="<?= e($item['description']) ?>"></td>
                        <td><input type="text" name="item_product[]" class="form-control" value="<?= e($item['product']) ?>"></td>
                        <td><input type="number" name="item_amount[]" class="form-control" value="<?= $item['amount'] ? $item['amount'] : '' ?>" step="1"></td>
                        <td><input type="text" name="item_due_date[]" class="form-control" value="<?= e($item['due_date']) ?>" placeholder="日期或說明"></td>
                        <td style="text-align:center">
                            <select name="item_is_settled[]" class="form-control">
                                <option value="0" <?= empty($item['is_settled']) ? 'selected' : '' ?>>未結清</option>
                                <option value="1" <?= !empty($item['is_settled']) ? 'selected' : '' ?>>已結清</option>
                            </select>
                        </td>
                        <td><input type="text" name="item_note[]" class="form-control" value="<?= e(isset($item['note']) ? $item['note'] : '') ?>"></td>
                        <td><button type="button" class="btn btn-danger btn-sm" onclick="removeItemRow(this)">&times;</button></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="d-flex gap-1">
        <button type="submit" class="btn btn-primary"><?= $record ? '更新' : '新增' ?></button>
        <a href="/transactions.php" class="btn btn-outline">取消</a>
    </div>
</form>

<script>
function addItemRow() {
    var tbody = document.getElementById('itemsBody');
    var tr = document.createElement('tr');
    tr.innerHTML = '<td><input type="date" name="item_trade_date[]" class="form-control"></td>'
        + '<td><input type="text" name="item_description[]" class="form-control"></td>'
        + '<td><input type="text" name="item_product[]" class="form-control"></td>'
        + '<td><input type="number" name="item_amount[]" class="form-control" step="1"></td>'
        + '<td><input type="text" name="item_due_date[]" class="form-control" placeholder="日期或說明"></td>'
        + '<td style="text-align:center"><select name="item_is_settled[]" class="form-control"><option value="0">未結清</option><option value="1">已結清</option></select></td>'
        + '<td><input type="text" name="item_note[]" class="form-control"></td>'
        + '<td><button type="button" class="btn btn-danger btn-sm" onclick="removeItemRow(this)">&times;</button></td>';
    tbody.appendChild(tr);
}
function removeItemRow(btn) {
    var tbody = document.getElementById('itemsBody');
    if (tbody.rows.length > 1) {
        btn.closest('tr').remove();
    }
}
</script>
