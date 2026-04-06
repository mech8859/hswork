<?php
$isEdit = !empty($record);
$bankOptions = FinanceModel::bankAccountOptions();
?>

<div class="d-flex justify-between align-center mb-2">
    <h2><?= $isEdit ? '編輯銀行交易' : '新增銀行交易' ?></h2>
    <?= back_button('/bank_transactions.php') ?>
</div>

<form method="POST" action="/bank_transactions.php?action=store" id="bankTxForm">
    <?= csrf_field() ?>
    <?php if ($isEdit): ?>
    <input type="hidden" name="id" value="<?= (int)$record['id'] ?>">
    <?php endif; ?>

    <div class="card">
        <div class="card-header">交易資訊</div>
        <div class="form-row">
            <div class="form-group">
                <label>銀行帳戶 *</label>
                <select name="bank_account" class="form-control" required>
                    <option value="">請選擇</option>
                    <?php foreach ($bankOptions as $val => $label): ?>
                    <option value="<?= e($val) ?>" <?= ($isEdit && !empty($record['bank_account']) && $record['bank_account'] === $val) ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>交易日期 *</label>
                <input type="date" max="2099-12-31" name="transaction_date" class="form-control" required
                       value="<?= e($isEdit && !empty($record['transaction_date']) ? $record['transaction_date'] : date('Y-m-d')) ?>">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>摘要</label>
                <input type="text" name="summary" class="form-control" placeholder="摘要"
                       value="<?= e($isEdit && !empty($record['summary']) ? $record['summary'] : '') ?>">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>轉出（支出）</label>
                <input type="text" name="debit_amount" class="form-control" placeholder="0"
                       value="<?= $isEdit && !empty($record['debit_amount']) ? number_format($record['debit_amount'], 0, '', '') : '' ?>">
            </div>
            <div class="form-group">
                <label>轉入（存入）</label>
                <input type="text" name="credit_amount" class="form-control" placeholder="0"
                       value="<?= $isEdit && !empty($record['credit_amount']) ? number_format($record['credit_amount'], 0, '', '') : '' ?>">
            </div>
            <div class="form-group">
                <label>餘額</label>
                <input type="text" name="balance" class="form-control" placeholder="0"
                       value="<?= $isEdit && isset($record['balance']) ? number_format($record['balance'], 0, '', '') : '' ?>">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>對象說明</label>
                <input type="text" name="description" class="form-control" placeholder="對象說明"
                       value="<?= e($isEdit && !empty($record['description']) ? $record['description'] : '') ?>">
            </div>
            <div class="form-group">
                <label>備註</label>
                <input type="text" name="remark" class="form-control" placeholder="備註"
                       value="<?= e($isEdit && !empty($record['remark']) ? $record['remark'] : '') ?>">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>上傳編號</label>
                <input type="text" name="upload_no" class="form-control" placeholder="上傳編號"
                       value="<?= e($isEdit && !empty($record['upload_no']) ? $record['upload_no'] : '') ?>">
            </div>
            <div class="form-group">
                <label>存匯代號</label>
                <input type="text" name="remittance_code" class="form-control" placeholder="存匯代號"
                       value="<?= e($isEdit && !empty($record['remittance_code']) ? $record['remittance_code'] : '') ?>">
            </div>
            <div class="form-group">
                <label>對方帳號</label>
                <input type="text" name="counterparty_account" class="form-control" placeholder="對方帳號"
                       value="<?= e($isEdit && !empty($record['counterparty_account']) ? $record['counterparty_account'] : '') ?>">
            </div>
            <div class="form-group">
                <label>註記</label>
                <input type="text" name="memo" class="form-control" placeholder="註記"
                       value="<?= e($isEdit && !empty($record['memo']) ? $record['memo'] : '') ?>">
            </div>
        </div>
    </div>

    <div class="d-flex justify-between mt-2">
        <a href="/bank_transactions.php" class="btn btn-outline">取消</a>
        <button type="submit" class="btn btn-primary"><?= $isEdit ? '更新' : '新增' ?></button>
    </div>
</form>

<?php if ($isEdit): ?>
<form method="POST" action="/bank_transactions.php?action=delete" class="mt-2"
      onsubmit="return confirm('確定要刪除此筆交易嗎？')">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int)$record['id'] ?>">
    <button type="submit" class="btn btn-sm" style="color:var(--danger)">刪除此筆交易</button>
</form>
<?php endif; ?>
