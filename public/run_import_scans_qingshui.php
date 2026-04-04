<?php
error_reporting(E_ALL); ini_set('display_errors',1);
set_time_limit(600);
header('Content-Type: text/plain; charset=utf-8');

require_once __DIR__ . '/../includes/bootstrap.php';
$db = Database::getInstance();

// Build index from legacy_customer_no / original_customer_no
$stmt = $db->query("SELECT id, customer_no, legacy_customer_no, original_customer_no, name FROM customers WHERE is_active = 1");
$index = array();
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $c) {
    foreach (array($c['legacy_customer_no'], $c['original_customer_no']) as $no) {
        if (!$no) continue;
        $parts = explode('.', $no);
        foreach ($parts as $p) {
            $p = trim($p);
            if ($p) $index[$p] = $c;
        }
    }
}
echo "Index: " . count($index) . " entries\n";

$scanDir = __DIR__ . '/uploads/scan_import_qingshui/JPG客戶編號1-180/';
if (!is_dir($scanDir)) { echo "Dir not found: $scanDir\n"; exit; }

$files = array();
foreach (scandir($scanDir) as $f) {
    if (!preg_match('/\.jpg$/i', $f) || $f[0] === '.' || $f[0] === '~') continue;
    $files[] = $f;
}
sort($files);
echo "Files: " . count($files) . "\n\n";

$insertStmt = $db->prepare("INSERT INTO customer_files (customer_id, file_type, file_name, file_path, file_size, note, uploaded_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
$matched = 0; $imported = 0; $unmatched = 0;
$errors = array();

foreach ($files as $f) {
    $ftype = 'other';
    if (strpos($f, '請款單') !== false) $ftype = 'invoice';
    elseif (strpos($f, '保固書') !== false) $ftype = 'contract';
    elseif (strpos($f, '回簽') !== false) $ftype = 'contract';

    // Extract pattern: C01, C02, 客戶5551-3, etc.
    $patterns = array();
    if (preg_match('/^(C\d+)\s*/', $f, $m)) {
        $patterns[] = $m[1];
    } elseif (preg_match('/^(客戶\d+(?:-\d+)?)\s*/', $f, $m)) {
        $patterns[] = $m[1];
    } elseif (preg_match('/^(\d+(?:-\d+)?)\s*/', $f, $m)) {
        $patterns[] = '客戶' . $m[1];
        $patterns[] = $m[1];
    }

    $customer = null;
    foreach ($patterns as $p) {
        if (isset($index[$p])) { $customer = $index[$p]; break; }
    }
    // Fallback: C01-1 → C01
    if (!$customer) {
        foreach ($patterns as $p) {
            if (preg_match('/^(C\d+)-\d+$/', $p, $fb)) {
                if (isset($index[$fb[1]])) { $customer = $index[$fb[1]]; break; }
            }
            if (preg_match('/^(客戶\d+)-\d+$/', $p, $fb)) {
                if (isset($index[$fb[1]])) { $customer = $index[$fb[1]]; break; }
            }
        }
    }

    if (!$customer) {
        $unmatched++;
        $errors[] = "UNMATCHED: $f (patterns: " . implode(',', $patterns) . ")";
        continue;
    }
    $matched++;

    $custDir = __DIR__ . '/uploads/customers/' . $customer['id'] . '/';
    if (!is_dir($custDir)) mkdir($custDir, 0755, true);
    $dest = $custDir . $f;
    if (!copy($scanDir . $f, $dest)) { $errors[] = "COPY: $f"; continue; }

    $filePath = 'uploads/customers/' . $customer['id'] . '/' . $f;
    try {
        $insertStmt->execute(array($customer['id'], $ftype, $f, $filePath, filesize($dest), '掃描檔匯入(清水115)', 1));
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
