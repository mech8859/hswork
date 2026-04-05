<?php
$isBoss = Auth::hasPermission('all');
$disabled = $isBoss ? '' : 'disabled';
$amount = 0;
if (!empty($record['income_amount']) && $record['income_amount'] > 0) {
    $amount = (float)$record['income_amount'];
} elseif (!empty($record['expense_amount']) && $record['expense_amount'] > 0) {
    $amount = (float)$record['expense_amount'];
}
$type = '支出';
if (!empty($record['income_amount']) && $record['income_amount'] > 0 && (empty($record['expense_amount']) || $record['expense_amount'] == 0)) {
    $type = '收入';
}
?>
<div class="d-flex justify-between align-center flex-wrap gap-1 mb-2">
    <h2>現金明細 - <?= e(!empty($record['entry_number']) ? $record['entry_number'] : '檢視') ?></h2>
    <?= back_button('/cash_details.php') ?>
</div>

<div class="card">
    <form method="POST" action="/cash_details.php?action=update&id=<?= e($record['id']) ?>">
        <?= csrf_field() ?>
        <div class="edit-form-grid">
            <div class="form-group">
                <label>編號</label>
                <input type="text" class="form-control" value="<?= e(!empty($record['entry_number']) ? $record['entry_number'] : '-') ?>" disabled>
            </div>
            <div class="form-group">
                <label>登記日期</label>
                <input type="text" class="form-control" value="<?= e(!empty($record['register_date']) ? $record['register_date'] : (!empty($record['created_at']) ? substr($record['created_at'], 0, 10) : '-')) ?>" disabled>
            </div>
            <div class="form-group">
                <label>收支別</label>
                <select name="type" class="form-control" <?= $disabled ?>>
                    <option value="支出" <?= $type === '支出' ? 'selected' : '' ?>>支出</option>
                    <option value="收入" <?= $type === '收入' ? 'selected' : '' ?>>收入</option>
                </select>
            </div>
            <div class="form-group">
                <label>交易日期</label>
                <input type="date" max="2099-12-31" name="transaction_date" class="form-control" value="<?= e(!empty($record['transaction_date']) ? $record['transaction_date'] : '') ?>" <?= $disabled ?>>
            </div>
            <div class="form-group">
                <label>金額</label>
                <input type="number" name="amount" class="form-control" min="0" step="1" value="<?= (int)$amount ?>" <?= $disabled ?>>
            </div>
            <div class="form-group">
                <label>明細/說明</label>
                <input type="text" name="description" class="form-control" value="<?= e(!empty($record['description']) ? $record['description'] : '') ?>" <?= $disabled ?>>
            </div>
            <div class="form-group">
                <label>承辦業務</label>
                <input type="text" name="sales_name" class="form-control" value="<?= e(!empty($record['sales_name']) ? $record['sales_name'] : '') ?>" <?= $disabled ?>>
            </div>
            <div class="form-group">
                <label>分公司</label>
                <select name="branch_id" class="form-control" <?= $disabled ?>>
                    <option value="">請選擇</option>
                    <?php foreach ($branches as $b): ?>
                    <option value="<?= e($b['id']) ?>" <?= (!empty($record['branch_id']) && $record['branch_id'] == $b['id']) ? 'selected' : '' ?>><?= e($b['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>收入金額</label>
                <input type="text" class="form-control" value="$<?= number_format(!empty($record['income_amount']) ? $record['income_amount'] : 0) ?>" disabled>
            </div>
            <div class="form-group">
                <label>支出金額</label>
                <input type="text" class="form-control" value="$<?= number_format(!empty($record['expense_amount']) ? $record['expense_amount'] : 0) ?>" disabled>
            </div>
        </div>

        <div class="d-flex gap-1 mt-2">
            <?php if ($isBoss): ?>
            <button type="submit" class="btn btn-primary btn-sm">儲存變更</button>
            <a href="/cash_details.php?action=delete&id=<?= e($record['id']) ?>&csrf_token=<?= e(Session::getCsrfToken()) ?>"
               class="btn btn-danger btn-sm"
               onclick="return confirm('確定要刪除此筆現金明細記錄嗎？此操作無法復原。')">刪除</a>
            <?php endif; ?>
            <?= back_button('/cash_details.php') ?>
        </div>
    </form>
</div>

<style>
.edit-form-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 12px; }
.edit-form-grid .form-group { margin-bottom: 0; }
</style>
