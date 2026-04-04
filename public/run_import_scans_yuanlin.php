<?php
/**
 * 匯入員林客戶掃描檔
 */
error_reporting(E_ALL); ini_set('display_errors',1);
set_time_limit(600);
header('Content-Type: text/plain; charset=utf-8');

require_once __DIR__ . '/../includes/bootstrap.php';
$db = Database::getInstance();

// Build customer index from 員林
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
            // 員林客戶XXX → also index by number
            if (preg_match('/員林客戶(\d+(?:-\d+)?)/', $p, $m)) {
                $index[$m[1]] = $c;
            }
            // 客戶XXX → index by number
            if (preg_match('/^客戶(\d+(?:-\d+)?)$/', $p, $m)) {
                $index[$m[1]] = $c;
            }
            // 業#XXX
            if (preg_match('/^業#(\d+)$/', $p, $m)) {
                $index['業' . $m[1]] = $c;
            }
        }
    }
}
echo "Index: " . count($index) . " entries\n";

// Scan directories
$scanBase = __DIR__ . '/uploads/scan_import_yuanlin/';
$dirs = array('1677後', 'JPG員林客戶編號1-200', 'JPG員林客戶編號201-400', 'JPG員林客戶編號401-600');

$files = array();
foreach ($dirs as $d) {
    $dirPath = $scanBase . $d . '/';
    if (!is_dir($dirPath)) { echo "SKIP dir: $d (not found)\n"; continue; }
    foreach (scandir($dirPath) as $f) {
        if (!preg_match('/\.jpg$/i', $f) || $f[0] === '.' || $f[0] === '~') continue;
        $files[] = array('dir' => $d, 'file' => $f, 'path' => $dirPath . $f);
    }
}
echo "Files: " . count($files) . "\n\n";

$insertStmt = $db->prepare("INSERT INTO customer_files (customer_id, file_type, file_name, file_path, file_size, note, uploaded_by) VALUES (?, ?, ?, ?, ?, ?, ?)");

$matched = 0; $imported = 0; $unmatched = 0;
$errors = array();

foreach ($files as $item) {
    $f = $item['file'];

    // File type
    $ftype = 'other';
    if (strpos($f, '請款單') !== false) $ftype = 'invoice';
    elseif (strpos($f, '保固書') !== false) $ftype = 'contract';
    elseif (strpos($f, '回簽') !== false) $ftype = 'contract';
    elseif (strpos($f, '採購單') !== false) $ftype = 'other';

    // Extract number
    $num = null;
    if (preg_match('/^(\d+(?:-\d+)?)\s*/', $f, $m)) {
        $num = $m[1];
    }
    if (!$num) {
        $unmatched++;
        $errors[] = "NO_NUM: $f";
        continue;
    }

    // Find customer: exact → parent fallback
    $customer = null;
    // Try: 員林客戶XXX, then plain number, then 客戶XXX
    $patterns = array('員林客戶' . $num, $num, '客戶' . $num);
    foreach ($patterns as $p) {
        if (isset($index[$p])) { $customer = $index[$p]; break; }
    }
    // Fallback: remove -N
    if (!$customer && preg_match('/^(\d+)-\d+$/', $num, $fb)) {
        $parentPatterns = array('員林客戶' . $fb[1], $fb[1], '客戶' . $fb[1]);
        foreach ($parentPatterns as $p) {
            if (isset($index[$p])) { $customer = $index[$p]; break; }
        }
    }

    if (!$customer) {
        $unmatched++;
        $errors[] = "UNMATCHED: {$item['dir']}/{$f} (num: $num)";
        continue;
    }

    $matched++;

    // Copy to customer dir
    $custDir = __DIR__ . '/uploads/customers/' . $customer['id'] . '/';
    if (!is_dir($custDir)) mkdir($custDir, 0755, true);

    $dest = $custDir . $f;
    if (!copy($item['path'], $dest)) {
        $errors[] = "COPY_FAIL: $f";
        continue;
    }

    $filePath = 'uploads/customers/' . $customer['id'] . '/' . $f;
    try {
        $insertStmt->execute(array($customer['id'], $ftype, $f, $filePath, filesize($dest), '掃描檔匯入(員林115)', 1));
        $imported++;
    } catch (PDOException $e) {
        $errors[] = "DB: $f => " . $e->getMessage();
    }
}

echo "Matched: $matched\n";
echo "Imported: $imported\n";
echo "Unmatched: $unmatched\n\n";

if (!empty($errors)) {
    echo "=== Errors (first 30) ===\n";
    foreach (array_slice($errors, 0, 30) as $e) echo "  $e\n";
    if (count($errors) > 30) echo "  ... and " . (count($errors)-30) . " more\n";
}
echo "\nDone.\n";
