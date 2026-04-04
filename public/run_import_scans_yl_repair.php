<?php
error_reporting(E_ALL); ini_set('display_errors',1);
set_time_limit(600);
header('Content-Type: text/plain; charset=utf-8');

require_once __DIR__ . '/../includes/bootstrap.php';
$db = Database::getInstance();

// Build index
$stmt = $db->query("SELECT id, customer_no, legacy_customer_no, original_customer_no, name FROM customers WHERE is_active = 1");
$index = array();
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $c) {
    foreach (array($c['legacy_customer_no'], $c['original_customer_no']) as $no) {
        if (!$no) continue;
        $parts = explode('.', $no);
        foreach ($parts as $p) {
            $p = trim($p);
            if (!$p) continue;
            $index[$p] = $c;
            if (preg_match('/員林客戶(\d+(?:-\d+)?)/', $p, $m)) $index[$m[1]] = $c;
            if (preg_match('/^客戶(\d+(?:-\d+)?)$/', $p, $m)) $index[$m[1]] = $c;
            if (preg_match('/^業#(\d+)$/', $p, $m)) $index['業' . $m[1]] = $c;
        }
    }
}
echo "Index: " . count($index) . "\n";

$scanDir = __DIR__ . '/uploads/scan_import_yl_repair/';
if (!is_dir($scanDir)) { echo "Dir not found\n"; exit; }

// Find all jpg recursively
$files = array();
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($scanDir));
foreach ($it as $file) {
    if ($file->isFile() && preg_match('/\.jpg$/i', $file->getFilename()) && $file->getFilename()[0] !== '.') {
        $files[] = array('path' => $file->getPathname(), 'file' => $file->getFilename());
    }
}
echo "Files: " . count($files) . "\n\n";

$insertStmt = $db->prepare("INSERT INTO customer_files (customer_id, file_type, file_name, file_path, file_size, note, uploaded_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
$matched = 0; $imported = 0; $unmatched = 0;
$errors = array();

foreach ($files as $item) {
    $f = $item['file'];

    // Extract: 業XXXX or 業#XXXX or number
    $patterns = array();
    if (preg_match('/^(業#?\d+)/', $f, $m)) {
        $raw = $m[1];
        $patterns[] = $raw;
        // Normalize: 業1836 → 業#1836
        if (preg_match('/^業(\d+)$/', $raw, $m2)) {
            $patterns[] = '業#' . $m2[1];
        }
    } elseif (preg_match('/^(\d+(?:-\d+)?)\s/', $f, $m)) {
        $patterns[] = '員林客戶' . $m[1];
        $patterns[] = $m[1];
        $patterns[] = '客戶' . $m[1];
    }

    $customer = null;
    foreach ($patterns as $p) {
        if (isset($index[$p])) { $customer = $index[$p]; break; }
    }

    if (!$customer) {
        $unmatched++;
        $errors[] = "UNMATCHED: $f (" . implode(',', $patterns) . ")";
        continue;
    }
    $matched++;

    $custDir = __DIR__ . '/uploads/customers/' . $customer['id'] . '/';
    if (!is_dir($custDir)) mkdir($custDir, 0755, true);
    $dest = $custDir . $f;
    if (!copy($item['path'], $dest)) { $errors[] = "COPY: $f"; continue; }

    $filePath = 'uploads/customers/' . $customer['id'] . '/' . $f;
    try {
        $insertStmt->execute(array($customer['id'], 'repair', $f, $filePath, filesize($dest), '維修單匯入(員林)', 1));
        $imported++;
    } catch (PDOException $e) {
        $errors[] = "DB: $f => " . $e->getMessage();
    }
}

echo "Matched: $matched\nImported: $imported\nUnmatched: $unmatched\n\n";
if (!empty($errors)) {
    echo "=== Errors (first 20) ===\n";
    foreach (array_slice($errors, 0, 20) as $e) echo "  $e\n";
    if (count($errors) > 20) echo "  ... and " . (count($errors)-20) . " more\n";
}
echo "\nDone.\n";
