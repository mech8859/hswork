<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (!in_array(Auth::user()['role'], array('boss','manager'))) die('需要管理員權限');

$db = Database::getInstance();
try {
    $db->query("SELECT attachment FROM journal_entries LIMIT 1");
    echo '<h1 style="color:gray">SKIP: attachment 欄位已存在</h1>';
} catch (Exception $e) {
    $db->exec("ALTER TABLE journal_entries ADD COLUMN attachment VARCHAR(255) DEFAULT NULL COMMENT '附件路徑'");
    echo '<h1 style="color:green">OK: journal_entries.attachment 已新增</h1>';
}
echo '<p><a href="/accounting.php?action=journal_create">新增傳票</a></p>';
