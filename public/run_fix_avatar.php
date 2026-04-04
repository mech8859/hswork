<?php
require __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
header('Content-Type: text/plain; charset=utf-8');
$db = Database::getInstance();

// 把 doc_type=avatar 改成 photo
$stmt = $db->prepare("UPDATE staff_documents SET doc_type = 'photo', doc_label = 'photo' WHERE user_id = 119 AND doc_type = 'avatar'");
$stmt->execute();
echo "更新 " . $stmt->rowCount() . " 筆 avatar → photo\n";

// 確認結果
$docs = $db->prepare("SELECT id, doc_type, doc_label, file_path FROM staff_documents WHERE user_id = 119");
$docs->execute();
foreach ($docs->fetchAll(PDO::FETCH_ASSOC) as $d) {
    echo "ID:{$d['id']} | {$d['doc_type']} | {$d['file_path']}\n";
}
echo "\n完成\n";
