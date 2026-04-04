<h2>重設密碼 - <?= e($user['real_name']) ?></h2>

<div class="card mt-2">
    <div class="card-header">設定新密碼</div>
    <form method="POST">
        <?= csrf_field() ?>

        <div class="form-group">
            <label>帳號</label>
            <input type="text" class="form-control" value="<?= e($user['username']) ?>" disabled>
        </div>
        <div class="form-group">
            <label>新密碼 *</label>
            <div style="position:relative">
                <input type="password" name="new_password" id="pw1" class="form-control" required minlength="6" placeholder="至少 6 個字元" style="padding-right:40px">
                <span onclick="toggleField('pw1',this)" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);cursor:pointer;font-size:1.1rem;color:var(--gray-500);user-select:none">&#128065;</span>
            </div>
        </div>
        <div class="form-group">
            <label>確認新密碼 *</label>
            <div style="position:relative">
                <input type="password" name="confirm_password" id="pw2" class="form-control" required minlength="6" placeholder="再輸入一次密碼" style="padding-right:40px">
                <span onclick="toggleField('pw2',this)" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);cursor:pointer;font-size:1.1rem;color:var(--gray-500);user-select:none">&#128065;</span>
            </div>
        </div>

        <div class="d-flex gap-1 mt-2">
            <button type="submit" class="btn btn-primary">重設密碼</button>
            <a href="/staff.php?action=view&id=<?= $user['id'] ?>" class="btn btn-outline">取消</a>
        </div>
    </form>
</div>

<script>
function toggleField(id, eye) {
    var f = document.getElementById(id);
    if (f.type === 'password') { f.type = 'text'; eye.style.opacity = '1'; }
    else { f.type = 'password'; eye.style.opacity = '0.5'; }
}
</script>
