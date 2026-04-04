<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();

try {
    echo "<h3>journal_entry_lines table structure</h3>";
    $cols = $jdb->query("SHOW CREATE TABLE journal_entry_lines")->fetch(PDO::FETCH_ASSOC);
    echo "<pre>" . htmlspecialchars($cols['Create Table']) . "</pre>";
} catch (Exception $e) { echo "<p style='color:red'>" . $e->getMessage() . "</p>"; }

try {
    echo "<h3>Last 3 journal entries</h3>";
    $hdrs = $jdb->query("SELECT id, voucher_number, total_debit, total_credit FROM journal_entries ORDER BY id DESC LIMIT 3")->fetchAll(PDO::FETCH_ASSOC);
    echo "<pre>" . print_r($hdrs, true) . "</pre>";

    foreach ($hdrs as $h) {
        echo "<h4>Lines for {$h['voucher_number']} (id={$h['id']})</h4>";
        $lines = $jdb->prepare("SELECT * FROM journal_entry_lines WHERE journal_entry_id = ?");
        $lines->execute(array($h['id']));
        $rows = $lines->fetchAll(PDO::FETCH_ASSOC);
        echo "<pre>" . print_r($rows, true) . "</pre>";
    }
} catch (Exception $e) { echo "<p style='color:red'>" . $e->getMessage() . "</p>"; }

echo "<h3>Journal 014 lines (raw)</h3>";
$raw = $jdb->query("SELECT * FROM journal_entry_lines WHERE journal_entry_id = (SELECT id FROM journal_entries WHERE voucher_number = 'JV-20260325-014')")->fetchAll(PDO::FETCH_ASSOC);
echo "<pre>" . print_r($raw, true) . "</pre>";

echo "<h3>Journal 014 lines (with JOIN)</h3>";
$joined = $jdb->query("SELECT jl.id, jl.account_id, jl.cost_center_id, CAST(jl.debit_amount AS DECIMAL(12,0)) AS debit_amount, CAST(jl.credit_amount AS DECIMAL(12,0)) AS credit_amount, coa.account_code, coa.account_name, cc.name AS cost_center_name FROM journal_entry_lines jl LEFT JOIN chart_of_accounts coa ON jl.account_id = coa.id LEFT JOIN cost_centers cc ON jl.cost_center_id = cc.id WHERE jl.journal_entry_id = (SELECT id FROM journal_entries WHERE voucher_number = 'JV-20260325-014') ORDER BY jl.sort_order, jl.id")->fetchAll(PDO::FETCH_ASSOC);
echo "<pre>" . print_r($joined, true) . "</pre>";

echo "<h3>Latest journal entry lines</h3>";
$jdb = Database::getInstance();
$jstmt = $jdb->query("SELECT jl.*, coa.account_code, coa.account_name FROM journal_entry_lines jl LEFT JOIN chart_of_accounts coa ON jl.account_id = coa.id ORDER BY jl.id DESC LIMIT 10");
echo "<table border=1 cellpadding=3><tr><th>id</th><th>journal_id</th><th>account</th><th>debit</th><th>credit</th><th>desc</th></tr>";
foreach ($jstmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
    echo "<tr><td>{$r['id']}</td><td>{$r['journal_entry_id']}</td><td>{$r['account_code']} {$r['account_name']}</td><td>{$r['debit_amount']}</td><td>{$r['credit_amount']}</td><td>{$r['description']}</td></tr>";
}
echo "</table><hr>";
$db = Database::getInstance();

echo "<h3>Search tax_id containing '20322275'</h3>";
try {
    $stmt = $db->query("SELECT id, name, tax_id, tax_id1, header1, tax_id2, header2, status, is_active FROM vendors WHERE tax_id LIKE '%20322275%' OR tax_id1 LIKE '%20322275%' OR tax_id2 LIKE '%20322275%'");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "<pre>" . print_r($rows, true) . "</pre>";
    if (empty($rows)) echo "<p style='color:red'>找不到此統編的廠商</p>";
} catch (Exception $e) {
    echo "<p style='color:red'>" . $e->getMessage() . "</p>";
    // 可能沒有這些欄位，簡單查
    $stmt = $db->query("SELECT id, name, tax_id FROM vendors WHERE tax_id LIKE '%20322275%'");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "<pre>" . print_r($rows, true) . "</pre>";
}

echo "<h3>Total vendors</h3>";
echo $db->query("SELECT COUNT(*) FROM vendors")->fetchColumn() . " 筆";

echo "<h3>Sample vendors (first 5 with tax_id)</h3>";
try {
    $stmt = $db->query("SELECT id, name, tax_id, header1, tax_id1 FROM vendors WHERE tax_id != '' AND tax_id IS NOT NULL LIMIT 5");
    echo "<pre>" . print_r($stmt->fetchAll(PDO::FETCH_ASSOC), true) . "</pre>";
} catch (Exception $e) {
    $stmt = $db->query("SELECT id, name, tax_id FROM vendors WHERE tax_id != '' AND tax_id IS NOT NULL LIMIT 5");
    echo "<pre>" . print_r($stmt->fetchAll(PDO::FETCH_ASSOC), true) . "</pre>";
}

echo "<h3>Search customer tax_id '00098865'</h3>";
$stmt = $db->query("SELECT id, name, tax_id, invoice_title, is_active FROM customers WHERE tax_id LIKE '%00098865%'");
echo "<pre>" . print_r($stmt->fetchAll(PDO::FETCH_ASSOC), true) . "</pre>";

echo "<h3>Total customers</h3>";
echo $db->query("SELECT COUNT(*) FROM customers")->fetchColumn() . " 筆";

echo "<h3>Sample customers with tax_id (first 5)</h3>";
$stmt = $db->query("SELECT id, name, tax_id, invoice_title, is_active FROM customers WHERE tax_id IS NOT NULL AND tax_id != '' LIMIT 5");
echo "<pre>" . print_r($stmt->fetchAll(PDO::FETCH_ASSOC), true) . "</pre>";

echo "<h3>Vendor count by status</h3>";
$stmt2 = $db->query("SELECT status, is_active, COUNT(*) as cnt FROM vendors GROUP BY status, is_active");
echo "<pre>" . print_r($stmt2->fetchAll(PDO::FETCH_ASSOC), true) . "</pre>";

echo "<h3>Columns in vendors</h3>";
$cols = $db->query("SHOW COLUMNS FROM vendors")->fetchAll(PDO::FETCH_ASSOC);
foreach ($cols as $c) echo $c['Field'] . " | " . $c['Type'] . "<br>";
