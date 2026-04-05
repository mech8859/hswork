<?php $isEdit = !empty($item); ?>
<div class="d-flex justify-between align-center mb-2">
    <h2><?= $isEdit ? '編輯工程項次' : '新增工程項次' ?></h2>
    <?= back_button('/engineering_items.php') ?>
</div>
<form method="POST">
    <?= csrf_field() ?>
    <div class="card">
        <div class="card-header">項次資料</div>
        <div class="form-row">
            <div class="form-group">
                <label>分類 *</label>
                <input type="text" name="category" class="form-control" required value="<?= e($item['category'] ?? '') ?>" placeholder="如：光纖工程" list="catList">
                <datalist id="catList">
                    <?php
                    $cats = Database::getInstance()->query("SELECT DISTINCT category FROM engineering_items ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);
                    foreach ($cats as $c): ?>
                    <option value="<?= e($c) ?>">
                    <?php endforeach; ?>
                </datalist>
            </div>
            <div class="form-group">
                <label>項目名稱 *</label>
                <input type="text" name="name" class="form-control" required value="<?= e($item['name'] ?? '') ?>">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>單位</label>
                <input type="text" name="unit" class="form-control" value="<?= e($item['unit'] ?? '式') ?>">
            </div>
            <div class="form-group">
                <label>預設定價</label>
                <input type="number" name="default_price" class="form-control" value="<?= e($item['default_price'] ?? 0) ?>">
            </div>
            <div class="form-group">
                <label>預設成本</label>
                <input type="number" name="default_cost" class="form-control" value="<?= e($item['default_cost'] ?? 0) ?>">
            </div>
            <div class="form-group">
                <label>排序</label>
                <input type="number" name="sort_order" class="form-control" value="<?= e($item['sort_order'] ?? 0) ?>">
            </div>
        </div>
        <label class="checkbox-label mt-1">
            <input type="checkbox" name="is_active" value="1" <?= ($item['is_active'] ?? 1) ? 'checked' : '' ?>>
            <span>啟用中</span>
        </label>
    </div>
    <div class="d-flex gap-1">
        <button type="submit" class="btn btn-primary">儲存</button>
        <a href="/engineering_items.php" class="btn btn-outline">取消</a>
    </div>
</form>
<style>.form-row{display:flex;flex-wrap:wrap;gap:12px}.form-row .form-group{flex:1;min-width:120px}</style>
