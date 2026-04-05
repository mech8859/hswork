<?php $isEdit = !empty($vendor); ?>
<div class="d-flex justify-between align-center mb-2">
    <h2><?= $isEdit ? '編輯廠商' : '新增廠商' ?></h2>
    <?= back_button('/dispatch_workers.php') ?>
</div>

<form method="POST">
    <?= csrf_field() ?>
    <div class="card">
        <div class="card-header">廠商資料</div>
        <div class="form-row">
            <div class="form-group">
                <label>廠商名稱 *</label>
                <input type="text" name="name" class="form-control" required value="<?= e($vendor['name'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>統一編號</label>
                <input type="text" name="tax_id" class="form-control" value="<?= e($vendor['tax_id'] ?? '') ?>">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>聯繫人</label>
                <input type="text" name="contact_person" class="form-control" value="<?= e($vendor['contact_person'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>電話</label>
                <input type="text" name="phone" class="form-control" value="<?= e($vendor['phone'] ?? '') ?>">
            </div>
        </div>
        <div class="form-group">
            <label>地址</label>
            <input type="text" name="address" class="form-control" value="<?= e($vendor['address'] ?? '') ?>">
        </div>
        <div class="form-group">
            <label>備註</label>
            <textarea name="note" class="form-control" rows="2"><?= e($vendor['note'] ?? '') ?></textarea>
        </div>
        <label class="checkbox-label">
            <input type="checkbox" name="is_active" value="1" <?= ($vendor['is_active'] ?? 1) ? 'checked' : '' ?>>
            <span>啟用中</span>
        </label>
    </div>
    <div class="d-flex gap-1">
        <button type="submit" class="btn btn-primary">儲存</button>
        <a href="/dispatch_workers.php?type=vendor" class="btn btn-outline">取消</a>
    </div>
</form>
<style>
.form-row { display:flex; flex-wrap:wrap; gap:12px; }
.form-row .form-group { flex:1; min-width:150px; }
</style>
