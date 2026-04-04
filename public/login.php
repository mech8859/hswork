<?php
require_once __DIR__ . '/../includes/bootstrap.php';

// 已登入則導向首頁
if (Session::isLoggedIn()) {
    redirect('/index.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $error = '安全驗證失敗，請重新操作';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($username === '' || $password === '') {
            $error = '請輸入帳號和密碼';
        } else {
            $result = Auth::attempt($username, $password);
            if ($result === true) {
                $user = Auth::user();
                AuditLog::log('auth', 'login', $user['id'], $user['real_name']);
                // 更新登入IP
                try {
                    $db = Database::getInstance();
                    $db->prepare("UPDATE users SET last_login_ip = ?, last_active_at = NOW() WHERE id = ?")->execute(array($_SERVER['REMOTE_ADDR'] ?? '', $user['id']));
                } catch (Exception $e) {}
                // 強制更改密碼
                if (!empty($user['must_change_password'])) {
                    redirect('/staff.php?action=force_change_password');
                }
                // 手機版工程師導向施工回報頁
                if ($user['is_engineer'] && $user['is_mobile']) {
                    redirect('/worklog.php');
                }
                redirect('/index.php');
            } else {
                $error = $result;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>登入 - 禾順中區管理系統</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+TC:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body {
        font-family: 'Noto Sans TC', -apple-system, BlinkMacSystemFont, sans-serif;
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        background: linear-gradient(135deg, #0d47a1 0%, #1565c0 30%, #1e88e5 60%, #42a5f5 100%);
        position: relative;
        overflow: hidden;
    }
    /* 背景裝飾 */
    body::before {
        content: '';
        position: absolute;
        top: -50%;
        left: -50%;
        width: 200%;
        height: 200%;
        background: radial-gradient(ellipse at 30% 20%, rgba(255,255,255,.08) 0%, transparent 60%),
                    radial-gradient(ellipse at 70% 80%, rgba(255,255,255,.05) 0%, transparent 50%);
        animation: bgShift 20s ease-in-out infinite alternate;
    }
    @keyframes bgShift {
        0% { transform: translate(0, 0); }
        100% { transform: translate(-3%, -3%); }
    }
    .login-container {
        position: relative;
        z-index: 1;
        width: 100%;
        max-width: 420px;
        padding: 16px;
    }
    .login-card {
        background: rgba(255,255,255,.95);
        backdrop-filter: blur(20px);
        border-radius: 16px;
        padding: 40px 36px;
        box-shadow: 0 20px 60px rgba(0,0,0,.25), 0 0 0 1px rgba(255,255,255,.1);
    }
    .login-logo {
        text-align: center;
        margin-bottom: 8px;
    }
    .login-logo .logo-icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 64px;
        height: 64px;
        background: linear-gradient(135deg, #1565c0, #0d47a1);
        border-radius: 16px;
        margin-bottom: 12px;
        box-shadow: 0 4px 16px rgba(13,71,161,.4);
    }
    .login-logo .logo-icon svg {
        width: 36px;
        height: 36px;
        fill: #fff;
    }
    .login-title {
        font-size: 1.5rem;
        font-weight: 700;
        color: #1a237e;
        text-align: center;
        margin-bottom: 4px;
        letter-spacing: 1px;
    }
    .login-subtitle {
        font-size: .8rem;
        color: #78909c;
        text-align: center;
        margin-bottom: 28px;
        letter-spacing: 2px;
    }
    .form-group { margin-bottom: 18px; }
    .form-group label {
        display: block;
        font-size: .85rem;
        font-weight: 500;
        color: #455a64;
        margin-bottom: 6px;
    }
    .form-input {
        width: 100%;
        padding: 12px 16px;
        border: 2px solid #e0e0e0;
        border-radius: 10px;
        font-size: 1rem;
        font-family: inherit;
        transition: border-color .2s, box-shadow .2s;
        background: #fafafa;
    }
    .form-input:focus {
        outline: none;
        border-color: #1565c0;
        box-shadow: 0 0 0 3px rgba(21,101,192,.15);
        background: #fff;
    }
    .form-input::placeholder { color: #bdbdbd; }
    .pwd-wrap { position: relative; }
    .pwd-wrap .form-input { padding-right: 44px; }
    .pwd-toggle {
        position: absolute;
        right: 12px;
        top: 50%;
        transform: translateY(-50%);
        background: none;
        border: none;
        cursor: pointer;
        font-size: 1.1rem;
        color: #90a4ae;
        padding: 4px;
        line-height: 1;
    }
    .pwd-toggle:hover { color: #546e7a; }
    .btn-login {
        width: 100%;
        padding: 13px;
        background: linear-gradient(135deg, #1565c0, #0d47a1);
        color: #fff;
        border: none;
        border-radius: 10px;
        font-size: 1rem;
        font-weight: 600;
        font-family: inherit;
        cursor: pointer;
        transition: transform .15s, box-shadow .15s;
        letter-spacing: 2px;
    }
    .btn-login:hover {
        transform: translateY(-1px);
        box-shadow: 0 6px 20px rgba(13,71,161,.4);
    }
    .btn-login:active { transform: translateY(0); }
    .alert-error {
        background: #fce4ec;
        color: #c62828;
        padding: 10px 14px;
        border-radius: 8px;
        font-size: .85rem;
        margin-bottom: 16px;
        border-left: 3px solid #e53935;
    }
    .login-footer {
        text-align: center;
        margin-top: 20px;
        font-size: .75rem;
        color: rgba(255,255,255,.5);
    }
    @media (max-width: 480px) {
        .login-card { padding: 32px 24px; border-radius: 12px; }
        .login-title { font-size: 1.3rem; }
    }
    </style>
</head>
<body>
<div class="login-container">
    <div class="login-card">
        <div class="login-logo">
            <div class="logo-icon">
                <svg viewBox="0 0 24 24"><path d="M12 2L2 7v10l10 5 10-5V7L12 2zm0 2.18L19.35 7.5 12 10.82 4.65 7.5 12 4.18zM4 8.93l7 3.5v7.15l-7-3.5V8.93zm9 10.65V12.43l7-3.5v7.15l-7 3.5z"/></svg>
            </div>
        </div>
        <h1 class="login-title">禾順中區管理系統</h1>
        <p class="login-subtitle">HERSHUN CENTRAL MANAGEMENT</p>

        <?php if ($error): ?>
            <div class="alert-error"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="/login.php" autocomplete="off">
            <?= csrf_field() ?>

            <div class="form-group">
                <label for="username">帳號</label>
                <input type="text" id="username" name="username" class="form-input"
                       value="<?= e($_POST['username'] ?? '') ?>"
                       placeholder="請輸入帳號" required autofocus>
            </div>

            <div class="form-group">
                <label for="password">密碼</label>
                <div class="pwd-wrap">
                    <input type="password" id="password" name="password" class="form-input"
                           placeholder="請輸入密碼" required>
                    <button type="button" onclick="togglePassword()" id="togglePwdBtn" class="pwd-toggle" title="顯示/隱藏密碼">
                        <svg id="eyeIcon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                    </button>
                </div>
            </div>

            <div class="form-group" style="margin-top:24px">
                <button type="submit" class="btn-login">登 入</button>
            </div>
        </form>
    </div>
    <div class="login-footer">禾順監視數位科技有限公司</div>
</div>
<script>
function togglePassword() {
    var pwd = document.getElementById('password');
    var icon = document.getElementById('eyeIcon');
    if (pwd.type === 'password') {
        pwd.type = 'text';
        icon.innerHTML = '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/>';
    } else {
        pwd.type = 'password';
        icon.innerHTML = '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>';
    }
}
</script>
</body>
</html>
