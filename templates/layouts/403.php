<?php
$pageTitle = '權限不足';
require __DIR__ . '/header.php';
?>
<div class="error-page">
    <h1>403</h1>
    <p>您沒有權限存取此頁面</p>
    <?= back_button('/index.php') ?>
</div>
<?php require __DIR__ . '/footer.php'; ?>
