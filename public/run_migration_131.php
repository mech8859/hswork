<?php
/**
 * Migration 131: dispatch_workers 加 worker_no（點工人員編號）
 * - VARCHAR(10) NULL，格式 3 碼零補齊（001, 002, ...）
 * - 既有資料按 id 順序補編號
 */
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (Session::getUser()['role'] !== 'boss') die('需要 boss 權限');
header('Content-Type: text/plain; charset=utf-8');

$db = Database::getInstance();
echo "=== Migration 131 ===\n\n";

$cols = $db->query("SHOW COLUMNS FROM dispatch_workers")->fetchAll(PDO::FETCH_ASSOC);
$names = array_column($cols, 'Field');

if (!in_array('worker_no', $names)) {
    $db->exec("ALTER TABLE dispatch_workers ADD COLUMN worker_no VARCHAR(10) NULL COMMENT '點工人員編號（001 起）' AFTER id");
    echo "ADDED: worker_no\n";
} else {
    echo "EXISTS: worker_no\n";
}

// 回填既有資料（ORDER BY id 重新分配 001, 002, ...）
$rows = $db->query("SELECT id, worker_no FROM dispatch_workers ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
echo "\n總筆數：" . count($rows) . "\n";
$upd = $db->prepare("UPDATE dispatch_workers SET worker_no = ? WHERE id = ?");
$seq = 0;
foreach ($rows as $r) {
    $seq++;
    $newNo = str_pad($seq, 3, '0', STR_PAD_LEFT);
    if ($r['worker_no'] !== $newNo) {
        $upd->execute(array($newNo, $r['id']));
    }
}
echo "回填完成，最後編號：" . str_pad($seq, 3, '0', STR_PAD_LEFT) . "\n";

echo "\n=== 完 ===\n";
