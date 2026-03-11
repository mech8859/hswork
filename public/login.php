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
        } elseif (Auth::attempt($username, $password)) {
            $user = Auth::user();
            // 手機版工程師導向施工回報頁
            if ($user['is_engineer'] && $user['is_mobile']) {
                redirect('/worklog.php');
            }
            redirect('/index.php');
        } else {
            $error = '帳號或密碼錯誤';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>登入 - 弱電工程排程系統</title>
    <link rel="stylesheet" href="/css/style.css">
</head>
<body>
<div class="login-wrapper">
    <div class="login-box">
        <h1>弱電工程排程系統</h1>
        <p class="subtitle">Low Voltage Engineering Scheduling</p>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="/login.php" autocomplete="off">
            <?= csrf_field() ?>

            <div class="form-group">
                <label for="username">帳號</label>
                <input type="text" id="username" name="username" class="form-control"
                       value="<?= e($_POST['username'] ?? '') ?>"
                       placeholder="請輸入帳號" required autofocus>
            </div>

            <div class="form-group">
                <label for="password">密碼</label>
                <input type="password" id="password" name="password" class="form-control"
                       placeholder="請輸入密碼" required>
            </div>

            <div class="form-group">
                <button type="submit" class="btn btn-primary btn-block">登入</button>
            </div>
        </form>
    </div>
</div>
</body>
</html>
