<?php $isEdit = !empty($account); ?>
<div class="d-flex justify-between align-center mb-2">
    <h2><?= $isEdit ? '編輯科目' : '新增科目' ?></h2>
    <?= back_button('/chart_of_accounts.php') ?>
</div>

<form method="POST">
    <?= csrf_field() ?>
    <div class="card">
        <div class="card-header">科目資料</div>
        <div class="form-row">
            <div class="form-group">
                <label>一階科目</label>
                <input type="text" name="level1" class="form-control" placeholder="如：1-資產" value="<?= e($account['level1'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>二階科目</label>
                <input type="text" name="level2" class="form-control" placeholder="如：11-流動資產" value="<?= e($account['level2'] ?? '') ?>">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>三階科目</label>
                <input type="text" name="level3" class="form-control" placeholder="如：111-現金及約當現金" value="<?= e($account['level3'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>三階科目編號</label>
                <input type="text" name="level3_code" class="form-control" value="<?= e($account['level3_code'] ?? '') ?>">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>四階科目 * <span style="color:var(--danger)">（完整名稱）</span></label>
                <input type="text" name="name" class="form-control" required placeholder="如：2173-應付帳款一關係人" value="<?= e($account['name'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>科目編號 *</label>
                <input type="text" name="code" class="form-control" required placeholder="如：2173" value="<?= e($account['code'] ?? '') ?>">
            </div>
        </div>
        <label class="checkbox-label mt-1">
            <input type="checkbox" name="is_active" value="1" <?= ($account['is_active'] ?? 1) ? 'checked' : '' ?>>
            <span>啟用中</span>
        </label>
    </div>
    <div class="d-flex gap-1">
        <button type="submit" class="btn btn-primary">儲存</button>
        <a href="/chart_of_accounts.php" class="btn btn-outline">取消</a>
    </div>
</form>
<style>
.form-row { display:flex; flex-wrap:wrap; gap:12px; }
.form-row .form-group { flex:1; min-width:150px; }
</style>
