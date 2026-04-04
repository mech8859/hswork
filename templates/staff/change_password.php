<div class="d-flex justify-between align-center mb-2">
    <h2>修改密碼</h2>
    <a href="/staff.php?action=view&id=<?= Auth::id() ?>" class="btn btn-outline btn-sm">返回</a>
</div>

<div class="card" style="max-width:500px">
    <form method="POST" action="/staff.php?action=change_password">
        <?= csrf_field() ?>
        <div class="form-group">
            <label>目前密碼 *</label>
            <input type="password" name="current_password" class="form-control" required>
        </div>
        <div class="form-group">
            <label>新密碼 *</label>
            <input type="password" name="new_password" class="form-control" required minlength="8">
            <small class="text-muted">至少 8 碼，需包含大寫英文、小寫英文、數字</small>
        </div>
        <div class="form-group">
            <label>確認新密碼 *</label>
            <input type="password" name="confirm_password" class="form-control" required>
        </div>
        <button type="submit" class="btn btn-primary">更新密碼</button>
    </form>
</div>
