<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?= e($pageTitle ?? '弱電工程排程系統') ?></title>
    <link rel="stylesheet" href="/css/style.css">
    <?php if (!empty($extraCss)): ?>
        <?php foreach ($extraCss as $css): ?>
            <link rel="stylesheet" href="<?= e($css) ?>">
        <?php endforeach; ?>
    <?php endif; ?>
</head>
<body>
<?php if (Session::isLoggedIn()): ?>
<nav class="navbar">
    <div class="navbar-brand">
        <button class="menu-toggle" id="menuToggle" aria-label="選單">&#9776;</button>
        <span class="navbar-title">弱電排程</span>
    </div>
    <div class="navbar-user">
        <span class="user-branch"><?= e(Session::getUser()['branch_name'] ?? '') ?></span>
        <span class="user-name"><?= e(Session::getUser()['real_name'] ?? '') ?></span>
        <span class="user-role"><?= e(role_name(Session::getUser()['role'] ?? '')) ?></span>
    </div>
</nav>
<aside class="sidebar" id="sidebar">
    <ul class="nav-menu">
        <li><a href="/index.php" class="<?= ($currentPage ?? '') === 'dashboard' ? 'active' : '' ?>">儀表板</a></li>
        <?php if (Auth::hasPermission('cases.manage') || Auth::hasPermission('cases.view') || Auth::hasPermission('cases.own')): ?>
        <li><a href="/cases.php" class="<?= ($currentPage ?? '') === 'cases' ? 'active' : '' ?>">案件管理</a></li>
        <?php endif; ?>
        <?php if (Auth::hasPermission('staff.manage') || Auth::hasPermission('staff.view')): ?>
        <li><a href="/staff.php" class="<?= ($currentPage ?? '') === 'staff' ? 'active' : '' ?>">人員管理</a></li>
        <?php endif; ?>
        <?php if (Auth::hasPermission('schedule.manage') || Auth::hasPermission('schedule.view')): ?>
        <li><a href="/schedule.php" class="<?= ($currentPage ?? '') === 'schedule' ? 'active' : '' ?>">排工行事曆</a></li>
        <?php endif; ?>
        <?php if (Auth::hasPermission('reports.view')): ?>
        <li><a href="/reports.php" class="<?= ($currentPage ?? '') === 'reports' ? 'active' : '' ?>">報表</a></li>
        <?php endif; ?>
        <?php $u = Session::getUser(); if ($u && $u['is_engineer']): ?>
        <li><a href="/worklog.php" class="<?= ($currentPage ?? '') === 'worklog' ? 'active' : '' ?>">施工回報</a></li>
        <?php endif; ?>
        <li class="nav-divider"></li>
        <li><a href="/logout.php">登出</a></li>
    </ul>
</aside>
<main class="content" id="mainContent">
<?php else: ?>
<main class="content content-full">
<?php endif; ?>

<?php
// Flash 訊息
$flashSuccess = Session::getFlash('success');
$flashError = Session::getFlash('error');
if ($flashSuccess): ?>
    <div class="alert alert-success"><?= e($flashSuccess) ?></div>
<?php endif; ?>
<?php if ($flashError): ?>
    <div class="alert alert-error"><?= e($flashError) ?></div>
<?php endif; ?>
