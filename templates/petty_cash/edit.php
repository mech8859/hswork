<?php
$isBoss = Auth::hasPermission('all');
$disabled = $isBoss ? '' : 'disabled';
$amount = 0;
if (!empty($record['income_amount']) && $record['income_amount'] > 0) {
    $amount = (float)$record['income_amount'];
} elseif (!empty($record['expense_amount']) && $record['expense_amount'] > 0) {
    $amount = (float)$record['expense_amount'];
}
?>
<div class="d-flex justify-between align-center flex-wrap gap-1 mb-2">
    <div>
        <h2 style="margin-bottom:2px">零用金 - <?= e(!empty($record['entry_number']) ? $record['entry_number'] : '檢視') ?></h2>
        <?php if (!empty($record['updated_at'])): ?>
        <small style="color:#888;font-size:.72rem">
            最後修改：<?= e(substr($record['updated_at'], 0, 16)) ?>
            <?php if (!empty($record['updater_name'])): ?> ｜ <?= e($record['updater_name']) ?><?php endif; ?>
        </small>
        <?php endif; ?>
    </div>
    <div class="d-flex gap-1 align-center flex-wrap">
        <?php if (!empty($prevId)): ?>
        <a href="/petty_cash.php?action=edit&id=<?= (int)$prevId ?>" class="btn btn-outline btn-sm" title="上一筆">&laquo; 上一筆</a>
        <?php else: ?>
        <span class="btn btn-outline btn-sm" style="opacity:.4;cursor:not-allowed">&laquo; 上一筆</span>
        <?php endif; ?>
        <?php if (!empty($nextId)): ?>
        <a href="/petty_cash.php?action=edit&id=<?= (int)$nextId ?>" class="btn btn-outline btn-sm" title="下一筆">下一筆 &raquo;</a>
        <?php else: ?>
        <span class="btn btn-outline btn-sm" style="opacity:.4;cursor:not-allowed">下一筆 &raquo;</span>
        <?php endif; ?>
        <?= back_button('/petty_cash.php') ?>
    </div>
</div>

<div class="card">
    <form method="POST" action="/petty_cash.php?action=update&id=<?= e($record['id']) ?>">
        <?= csrf_field() ?>
        <div class="edit-form-grid">
            <div class="form-group">
                <label>編號</label>
                <input type="text" class="form-control" value="<?= e(!empty($record['entry_number']) ? $record['entry_number'] : '-') ?>" disabled>
            </div>
            <div class="form-group">
                <label>登記日期</label>
                <input type="text" class="form-control" value="<?= e(!empty($record['created_at']) ? substr($record['created_at'], 0, 10) : '-') ?>" disabled>
            </div>
            <div class="form-group">
                <label>收支別</label>
                <select name="type" class="form-control" <?= $disabled ?>>
                    <option value="支出" <?= (!empty($record['type']) && $record['type'] === '支出') ? 'selected' : '' ?>>支出</option>
                    <option value="收入" <?= (!empty($record['type']) && $record['type'] === '收入') ? 'selected' : '' ?>>收入</option>
                </select>
            </div>
            <div class="form-group">
                <label>收支日期</label>
                <input type="date" max="2099-12-31" name="entry_date" class="form-control" value="<?= e(!empty($record['entry_date']) ? $record['entry_date'] : '') ?>" <?= $disabled ?>>
            </div>
            <div class="form-group">
                <label>金額</label>
                <input type="number" name="amount" class="form-control" min="0" step="1" value="<?= (int)$amount ?>" <?= $disabled ?>>
            </div>
            <div class="form-group">
                <label>有無發票</label>
                <select name="has_invoice" class="form-control" <?= $disabled ?>>
                    <option value="">-</option>
                    <option value="有發票" <?= (!empty($record['has_invoice']) && $record['has_invoice'] === '有發票') ? 'selected' : '' ?>>有發票</option>
                    <option value="無發票" <?= (!empty($record['has_invoice']) && $record['has_invoice'] === '無發票') ? 'selected' : '' ?>>無發票</option>
                </select>
            </div>
            <div class="form-group">
                <label>發票資訊</label>
                <input type="text" name="invoice_info" class="form-control" value="<?= e(!empty($record['invoice_info']) ? $record['invoice_info'] : '') ?>" <?= $disabled ?>>
            </div>
            <div class="form-group">
                <label>用途說明</label>
                <input type="text" name="description" class="form-control" value="<?= e(!empty($record['description']) ? $record['description'] : '') ?>" <?= $disabled ?>>
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
                <label>登記人</label>
                <input type="text" name="registrar" class="form-control" value="<?= e(!empty($record['registrar']) ? $record['registrar'] : '') ?>" <?= $disabled ?>>
                <?php if (!empty($record['created_at'])): ?>
                <small style="color:#888;font-size:.72rem">登記時間：<?= e(substr($record['created_at'], 0, 16)) ?></small>
                <?php endif; ?>
            </div>
            <div class="form-group">
                <label>簽核狀態</label>
                <input type="text" name="approval_status" class="form-control" value="<?= e(!empty($record['approval_status']) ? $record['approval_status'] : '') ?>" <?= $disabled ?>>
            </div>
            <div class="form-group">
                <label>支出金額</label>
                <input type="text" class="form-control" value="$<?= number_format(!empty($record['expense_amount']) ? $record['expense_amount'] : 0) ?>" disabled>
            </div>
            <div class="form-group">
                <label>收入金額</label>
                <input type="text" class="form-control" value="$<?= number_format(!empty($record['income_amount']) ? $record['income_amount'] : 0) ?>" disabled>
            </div>
        </div>

        <div class="d-flex gap-1 mt-2">
            <?php if ($isBoss): ?>
            <button type="submit" class="btn btn-primary btn-sm">儲存變更</button>
            <a href="/petty_cash.php?action=delete&id=<?= e($record['id']) ?>&csrf_token=<?= e(Session::getCsrfToken()) ?>"
               class="btn btn-danger btn-sm"
               onclick="return confirm('確定要刪除此筆零用金記錄嗎？此操作無法復原。')">刪除</a>
            <?php endif; ?>
            <?= back_button('/petty_cash.php') ?>
        </div>
    </form>
</div>

<style>
.edit-form-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 12px; }
.edit-form-grid .form-group { margin-bottom: 0; }
</style>
