<h2><?= $user ? '編輯人員 - ' . e($user['real_name']) : '新增人員' ?></h2>

<form method="POST" class="mt-2">
    <?= csrf_field() ?>

    <div class="card">
        <div class="card-header">帳號資料</div>
        <div class="form-row">
            <div class="form-group">
                <label>帳號 *</label>
                <?php if ($user): ?>
                    <input type="text" class="form-control" value="<?= e($user['username']) ?>" disabled>
                <?php else: ?>
                    <input type="text" name="username" class="form-control" value="<?= e($_POST['username'] ?? '') ?>" required>
                <?php endif; ?>
            </div>
            <div class="form-group">
                <label><?= $user ? '新密碼 (留空不修改)' : '密碼 *' ?></label>
                <input type="password" name="password" class="form-control" <?= $user ? '' : 'required' ?>>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>真實姓名 *</label>
                <input type="text" name="real_name" class="form-control" value="<?= e($user['real_name'] ?? '') ?>" required>
            </div>
            <div class="form-group">
                <label>據點 *</label>
                <select name="branch_id" class="form-control" required>
                    <option value="">請選擇</option>
                    <?php foreach ($branches as $b): ?>
                    <option value="<?= $b['id'] ?>" <?= ($user['branch_id'] ?? '') == $b['id'] ? 'selected' : '' ?>><?= e($b['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>角色 *</label>
                <select name="role" class="form-control" required>
                    <?php foreach ($appConfig['roles'] as $v => $l): ?>
                    <option value="<?= $v ?>" <?= ($user['role'] ?? '') === $v ? 'selected' : '' ?>><?= e($l) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>電話</label>
                <input type="text" name="phone" class="form-control" value="<?= e($user['phone'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" class="form-control" value="<?= e($user['email'] ?? '') ?>">
            </div>
        </div>
        <div class="checkbox-row mt-1">
            <label class="checkbox-label">
                <input type="hidden" name="is_engineer" value="0">
                <input type="checkbox" name="is_engineer" value="1" <?= !empty($user['is_engineer']) ? 'checked' : '' ?>>
                <span>工程師 (可排工)</span>
            </label>
            <label class="checkbox-label">
                <input type="hidden" name="is_mobile" value="0">
                <input type="checkbox" name="is_mobile" value="1" <?= ($user['is_mobile'] ?? 1) ? 'checked' : '' ?>>
                <span>手機介面</span>
            </label>
            <label class="checkbox-label">
                <input type="hidden" name="can_view_all_branches" value="0">
                <input type="checkbox" name="can_view_all_branches" value="1" <?= !empty($user['can_view_all_branches']) ? 'checked' : '' ?>>
                <span>可查看全區</span>
            </label>
        </div>
    </div>

    <div class="d-flex gap-1 mt-2">
        <button type="submit" class="btn btn-primary"><?= $user ? '儲存變更' : '建立人員' ?></button>
        <a href="/staff.php" class="btn btn-outline">取消</a>
    </div>
</form>

<style>
.form-row { display: flex; flex-wrap: wrap; gap: 12px; }
.form-row .form-group { flex: 1; min-width: 150px; }
.checkbox-row { display: flex; flex-wrap: wrap; gap: 16px; }
.checkbox-label { display: flex; align-items: center; gap: 6px; cursor: pointer; }
.checkbox-label input[type="checkbox"] { width: 18px; height: 18px; }
</style>
