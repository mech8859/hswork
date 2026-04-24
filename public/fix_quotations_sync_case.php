<?php
/**
 * 一次性：把所有已關聯案件的報價單，回填 site_name / invoice_title / invoice_tax_id
 *   site_name       ← cases.title
 *   invoice_title   ← cases.billing_title
 *   invoice_tax_id  ← cases.billing_tax_id
 *
 * 僅更新「值不同」的報價單；沒有關聯案件的報價單不動。
 */
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (!Auth::hasPermission('system.manage') && !Auth::hasPermission('all')) {
    die('No permission');
}

$db = Database::getInstance();
header('Content-Type: text/html; charset=utf-8');

$dryRun = !isset($_GET['go']) || $_GET['go'] !== '1';

echo "<h3>報價單回填：從案件同步 案件名稱 / 發票抬頭 / 統編</h3>";
echo "<p>模式：" . ($dryRun ? '<b style="color:#c62828">Dry-run (預覽)</b>' : '<b style="color:#2e7d32">實際執行</b>') . "</p>";

$stmt = $db->query("
    SELECT q.id AS qid, q.quotation_number, q.site_name AS q_site_name,
           q.invoice_title AS q_invoice_title, q.invoice_tax_id AS q_invoice_tax_id,
           c.id AS cid, c.case_number, c.title AS c_title,
           c.billing_title AS c_billing_title, c.billing_tax_id AS c_billing_tax_id
    FROM quotations q
    INNER JOIN cases c ON q.case_id = c.id
    ORDER BY q.id DESC
");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
$total = count($rows);

$changeCount = 0;
$rowsHtml = '';
$updStmt = $db->prepare("UPDATE quotations SET site_name = ?, invoice_title = ?, invoice_tax_id = ? WHERE id = ?");

foreach ($rows as $r) {
    $siteChanged   = ($r['q_site_name']       ?? '') !== ($r['c_title']          ?? '');
    $titleChanged  = ($r['q_invoice_title']   ?? '') !== ($r['c_billing_title']  ?? '');
    $taxChanged    = ($r['q_invoice_tax_id']  ?? '') !== ($r['c_billing_tax_id'] ?? '');
    if (!$siteChanged && !$titleChanged && !$taxChanged) continue;

    $changeCount++;
    if (!$dryRun) {
        $updStmt->execute(array(
            $r['c_title'],
            $r['c_billing_title'],
            $r['c_billing_tax_id'],
            $r['qid'],
        ));
    }

    $cell = function($old, $new, $changed) {
        $old = htmlspecialchars((string)$old);
        $new = htmlspecialchars((string)$new);
        if (!$changed) return "<td style='color:#aaa'>{$old}</td>";
        return "<td><span style='text-decoration:line-through;color:#c62828'>{$old}</span><br><span style='color:#2e7d32'>→ {$new}</span></td>";
    };

    $rowsHtml .= "<tr>"
              . "<td>{$r['qid']}</td>"
              . "<td>" . htmlspecialchars($r['quotation_number']) . "</td>"
              . "<td>" . htmlspecialchars($r['case_number']) . "</td>"
              . $cell($r['q_site_name'],       $r['c_title'],          $siteChanged)
              . $cell($r['q_invoice_title'],   $r['c_billing_title'],  $titleChanged)
              . $cell($r['q_invoice_tax_id'],  $r['c_billing_tax_id'], $taxChanged)
              . "</tr>";
}

echo "<p>掃描 {$total} 筆有關聯案件的報價單，有 <b>{$changeCount}</b> 筆欄位需更新。</p>";

if ($changeCount === 0) {
    echo "<p style='color:#2e7d32'>✓ 所有報價單皆已與案件一致，無需更新。</p>";
    exit;
}

echo "<table border='1' cellpadding='6' cellspacing='0' style='border-collapse:collapse;font-size:.85rem'>";
echo "<thead><tr><th>報價單 id</th><th>報價單號</th><th>案件編號</th><th>案件名稱 (site_name)</th><th>發票抬頭</th><th>統編</th></tr></thead><tbody>";
echo $rowsHtml;
echo "</tbody></table>";

if ($dryRun) {
    echo "<hr><p><a href='?go=1' onclick='return confirm(\"確定回填 {$changeCount} 筆？\")' "
       . "style='display:inline-block;padding:8px 20px;background:#c62828;color:#fff;text-decoration:none;border-radius:4px'>"
       . "執行回填 {$changeCount} 筆</a></p>";
} else {
    echo "<hr><p style='color:#2e7d32'>✓ 已完成</p><p><a href='/quotations.php'>回報價單管理</a></p>";
}
