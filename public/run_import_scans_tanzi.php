<?php
/**
 * 匯入潭子客戶掃描檔
 * 檔案來源：本地 uploads/scan_import_tanzi/ (先從隨身碟複製到此)
 * 比對 legacy_customer_no / original_customer_no → customer_id
 */
error_reporting(E_ALL); ini_set('display_errors',1);
header('Content-Type: text/plain; charset=utf-8');

require_once __DIR__ . '/../includes/bootstrap.php';
$db = Database::getInstance();

// Build customer index from legacy_customer_no / original_customer_no
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

// Scan files directory
$scanDir = __DIR__ . '/uploads/scan_import_tanzi/';
if (!is_dir($scanDir)) {
    echo "ERROR: Directory not found: $scanDir\n";
    echo "Please copy scan files to this directory first.\n";
    exit;
}

$files = array_filter(scandir($scanDir), function($f) {
    return preg_match('/\.jpg$/i', $f) && $f[0] !== '~' && $f[0] !== '.';
});
sort($files);

echo "Files found: " . count($files) . "\n";
echo "Customer index size: " . count($index) . "\n\n";

$matched = 0;
$unmatched = 0;
$imported = 0;
$errors = array();

$insertStmt = $db->prepare("INSERT INTO customer_files (customer_id, file_type, file_name, file_path, file_size, note, uploaded_by) VALUES (?, ?, ?, ?, ?, ?, ?)");

foreach ($files as $f) {
    // Determine file_type
    $ftype = 'other';
    if (strpos($f, '請款單') !== false) $ftype = 'invoice';
    elseif (strpos($f, '保固書') !== false) $ftype = 'contract';
    elseif (strpos($f, '回簽') !== false) $ftype = 'contract';

    // Extract patterns
    $patterns = array();
    if (preg_match('/^(業|理)(\d+)\s/', $f, $m)) {
        $patterns[] = $m[1] . '#' . $m[2];
        $patterns[] = $m[1] . $m[2];
    } elseif (preg_match('/^(\d+(?:-\d+)?)\s/', $f, $m)) {
        $patterns[] = '客戶' . $m[1];
    }

    // Find customer (exact match, with fallback to parent)
    $customer = null;
    foreach ($patterns as $p) {
        if (isset($index[$p])) {
            $customer = $index[$p];
            break;
        }
    }
    if (!$customer) {
        // Fallback: 客戶4809-3 → 客戶4809
        foreach ($patterns as $p) {
            if (preg_match('/^(客戶\d+)-\d+$/', $p, $fb)) {
                if (isset($index[$fb[1]])) {
                    $customer = $index[$fb[1]];
                    break;
                }
            }
        }
    }

    if (!$customer) {
        $unmatched++;
        $errors[] = "UNMATCHED: $f (patterns: " . implode(', ', $patterns) . ")";
        continue;
    }

    $matched++;

    // Create customer upload directory
    $custDir = __DIR__ . '/uploads/customers/' . $customer['id'] . '/';
    if (!is_dir($custDir)) {
        mkdir($custDir, 0755, true);
    }

    // Copy file
    $src = $scanDir . $f;
    $dest = $custDir . $f;
    if (!copy($src, $dest)) {
        $errors[] = "COPY FAILED: $f";
        continue;
    }

    // Insert DB record (no duplicate check - always insert)
    $filePath = 'uploads/customers/' . $customer['id'] . '/' . $f;
    $fileSize = filesize($dest);
    try {
        $insertStmt->execute(array(
            $customer['id'],
            $ftype,
            $f,
            $filePath,
            $fileSize,
            '掃描檔匯入(潭子115)',
            1
        ));
        $imported++;
    } catch (PDOException $e) {
        $errors[] = "DB ERROR: $f => " . $e->getMessage();
    }
}

echo "Matched: $matched\n";
echo "Imported: $imported\n";
echo "Unmatched: $unmatched\n\n";

if (!empty($errors)) {
    echo "=== Errors ===\n";
    foreach ($errors as $e) echo "  $e\n";
}

echo "\nDone.\n";
