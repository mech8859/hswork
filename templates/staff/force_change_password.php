<div style="max-width:500px;margin:40px auto">
    <div class="card">
        <div class="card-header" style="background:var(--primary);color:#fff;text-align:center;font-size:1.2em">
            首次登入 — 請設定新密碼
        </div>
        <div style="padding:20px">
            <p style="color:#666;margin-bottom:16px">為確保帳號安全，首次登入需設定新密碼。</p>
            <form method="POST" action="/staff.php?action=force_change_password">
                <?= csrf_field() ?>
                <div class="form-group">
                    <label>新密碼 *</label>
                    <div style="position:relative">
                        <input type="password" name="new_password" class="form-control" required minlength="8" id="newPw" style="padding-right:45px">
                        <button type="button" onclick="togglePw('newPw',this)" style="position:absolute;right:8px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;font-size:1.1em" title="顯示/隱藏">👁</button>
                    </div>
                    <small class="text-muted">至少 8 碼，需包含大寫英文、小寫英文、數字</small>
                </div>
                <div class="form-group">
                    <label>確認新密碼 *</label>
                    <div style="position:relative">
                        <input type="password" name="confirm_password" class="form-control" required id="confirmPw" style="padding-right:45px">
                        <button type="button" onclick="togglePw('confirmPw',this)" style="position:absolute;right:8px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;font-size:1.1em" title="顯示/隱藏">👁</button>
                    </div>
                </div>
                <div id="pwStrength" style="margin-bottom:12px"></div>
                <button type="submit" class="btn btn-primary" style="width:100%">設定密碼並進入系統</button>
            </form>
        </div>
    </div>
</div>
<script>
function togglePw(id, btn) {
    var inp = document.getElementById(id);
    if (inp.type === 'password') { inp.type = 'text'; btn.textContent = '🙈'; }
    else { inp.type = 'password'; btn.textContent = '👁'; }
}
document.getElementById('newPw').addEventListener('input', function() {
    var pw = this.value;
    var el = document.getElementById('pwStrength');
    var checks = [];
    if (pw.length >= 8) checks.push('✓ 8碼以上'); else checks.push('✗ 需8碼以上');
    if (/[A-Z]/.test(pw)) checks.push('✓ 大寫英文'); else checks.push('✗ 需大寫英文');
    if (/[a-z]/.test(pw)) checks.push('✓ 小寫英文'); else checks.push('✗ 需小寫英文');
    if (/[0-9]/.test(pw)) checks.push('✓ 數字'); else checks.push('✗ 需數字');
    el.innerHTML = checks.map(function(c) {
        var ok = c.charAt(0) === '✓';
        return '<span style="color:' + (ok ? '#38a169' : '#e53e3e') + ';font-size:.85rem;margin-right:12px">' + c + '</span>';
    }).join('');
});
</script>
