<?php
require __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (!Auth::hasPermission('admin')) { die('需要管理員權限'); }
header('Content-Type: text/plain; charset=utf-8');
$db = Database::getInstance();

$pid = $db->query("SELECT id FROM product_categories WHERE name = '03.對講機系統' AND (parent_id IS NULL OR parent_id = 0)")->fetchColumn();
if (!$pid) die('找不到 03.對講機系統');

// 新增「影視對講」子分類
$chk = $db->prepare("SELECT id FROM product_categories WHERE name = '影視對講' AND parent_id = ?");
$chk->execute(array($pid));
$subId = $chk->fetchColumn();
if (!$subId) {
    $db->prepare("INSERT INTO product_categories (name, parent_id, sort) VALUES ('影視對講', ?, 1)")->execute(array($pid));
    $subId = (int)$db->lastInsertId();
    echo "[新增] 影視對講 (ID: {$subId})\n";
} else {
    echo "[已存在] 影視對講 (ID: {$subId})\n";
}

echo "\n完成\n";
