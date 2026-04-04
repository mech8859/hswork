<?php
error_reporting(E_ALL); ini_set('display_errors',1);
set_time_limit(600);
header('Content-Type: text/plain; charset=utf-8');

require_once __DIR__ . '/../includes/bootstrap.php';
$db = Database::getInstance();

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
            if (preg_match('/^業#(\d+)$/', $p, $m)) $index['業' . $m[1]] = $c;
            if (preg_match('/^維#(\d+)$/', $p, $m)) $index['維' . $m[1]] = $c;
        }
    }
}
echo "Index: " . count($index) . "\n";

$scanDir = __DIR__ . '/uploads/scan_import_qs_repair/';
if (!is_dir($scanDir)) { echo "Dir not found\n"; exit; }

$files = array();
foreach (scandir($scanDir) as $f) {
    if (!preg_match('/\.jpg$/i', $f) || $f[0] === '.' || $f[0] === '~') continue;
    $files[] = $f;
}
echo "Files: " . count($files) . "\n\n";

$insertStmt = $db->prepare("INSERT INTO customer_files (customer_id, file_type, file_name, file_path, file_size, note, uploaded_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
$matched = 0; $imported = 0; $unmatched = 0;
$errors = array();

foreach ($files as $f) {
    $patterns = array();

    // C01, C01-1, C112-1
    if (preg_match('/^(C\d+(?:-\d+)?)\s*/', $f, $m)) {
        $patterns[] = $m[1];
    }
    // 維#XXXX or 維 XXXX or 維XXXX
    if (preg_match('/維#?(\d+)/', $f, $m)) {
        $patterns[] = '維#' . $m[1];
        $patterns[] = '維' . $m[1];
    }
    // 業XXXX or 業#XXXX
    if (preg_match('/業[#_]?(\d+)/', $f, $m)) {
        $patterns[] = '業#' . $m[1];
        $patterns[] = '業' . $m[1];
    }
    // 客戶XXXX
    if (preg_match('/客戶(\d+(?:-\d+)?)/', $f, $m)) {
        $patterns[] = '客戶' . $m[1];
    }

    $customer = null;
    foreach ($patterns as $p) {
        if (isset($index[$p])) { $customer = $index[$p]; break; }
    }
    // Fallback: C120-2 → C120
    if (!$customer) {
        foreach ($patterns as $p) {
            if (preg_match('/^(C\d+)-\d+$/', $p, $fb)) {
                if (isset($index[$fb[1]])) { $customer = $index[$fb[1]]; break; }
            }
        }
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
    if (!copy($scanDir . $f, $dest)) { $errors[] = "COPY: $f"; continue; }

    $filePath = 'uploads/customers/' . $customer['id'] . '/' . $f;
    try {
        $insertStmt->execute(array($customer['id'], 'repair', $f, $filePath, filesize($dest), '維修單匯入(清水)', 1));
        $imported++;
    } catch (PDOException $e) {
        $errors[] = "DB: $f => " . $e->getMessage();
    }
}

echo "Matched: $matched\nImported: $imported\nUnmatched: $unmatched\n\n";
if (!empty($errors)) {
    echo "=== Errors (first 30) ===\n";
    foreach (array_slice($errors, 0, 30) as $e) echo "  $e\n";
    if (count($errors) > 30) echo "  ... and " . (count($errors)-30) . " more\n";
}
echo "\nDone.\n";
