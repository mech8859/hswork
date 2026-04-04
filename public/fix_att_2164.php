<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
$db = Database::getInstance();

// 更新 case 2164 的報價單附件
$filePath = 'uploads/cases/2164/峰達海運 - 中橫十一路1160316 conv 1.jpeg';
$fullPath = __DIR__ . '/' . $filePath;
$fileSize = file_exists($fullPath) ? filesize($fullPath) : 0;

// 檢查是否有待上傳的空記錄
$stmt = $db->prepare("SELECT id FROM case_attachments WHERE case_id = 2164 AND file_path = '' LIMIT 1");
$stmt->execute();
$row = $stmt->fetch();

if ($row) {
    $db->prepare("UPDATE case_attachments SET file_path = ?, file_size = ? WHERE id = ?")
       ->execute(array($filePath, $fileSize, $row['id']));
    echo 'Updated existing record ID: ' . $row['id'];
} else {
    $db->prepare("INSERT INTO case_attachments (case_id, file_type, file_name, file_path, file_size, uploaded_by) VALUES (?, ?, ?, ?, ?, ?)")
       ->execute(array(2164, 'quotation', '峰達海運 - 中橫十一路1160316 conv 1.jpeg', $filePath, $fileSize, Auth::id()));
    echo 'Inserted new record';
}
echo '<br>Done. <a href="/cases.php?action=edit&id=2164#sec-attach">查看案件附件</a>';
