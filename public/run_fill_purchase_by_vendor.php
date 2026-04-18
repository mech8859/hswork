<?php
/**
 * 以有完整資料（賣方統編+賣方名稱+供應商）的發票為模板，
 * 回補其他只有賣方統編的進項發票：vendor_name / vendor_id / invoice_format / deduction_type / deduction_category
 *
 * 預覽：不帶 confirm
 * 執行：?confirm=1
 */
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
Auth::requireRole('boss');
header('Content-Type: text/plain; charset=utf-8');

$db = Database::getInstance();
$confirm = isset($_GET['confirm']) && $_GET['confirm'] === '1';

// 1) 抓所有發票，依 vendor_tax_id 分群
$all = $db->query("
    SELECT id, vendor_tax_id, vendor_name, vendor_id, invoice_format, deduction_type, deduction_category, invoice_number
    FROM purchase_invoices
    WHERE status != 'voided' AND vendor_tax_id IS NOT NULL AND vendor_tax_id != ''
")->fetchAll(PDO::FETCH_ASSOC);

$byTid = array();
foreach ($all as $r) {
    $tid = trim($r['vendor_tax_id']);
    if (!$tid) continue;
    $byTid[$tid][] = $r;
}

// 2) 從 vendors 表建立 tax_id → id/name 對照（優先）
$vendorIdx = array();
$vstmt = $db->query("SELECT id, name, tax_id, tax_id1, tax_id2 FROM vendors WHERE is_active = 1");
foreach ($vstmt as $v) {
    foreach (array('tax_id', 'tax_id1', 'tax_id2') as $k) {
        $t = !empty($v[$k]) ? trim($v[$k]) : '';
        if ($t) $vendorIdx[$t] = array('id' => (int)$v['id'], 'name' => trim($v['name']));
    }
}

// 3) 找每個 tax_id 的模板（最齊全的一筆）
function _scoreRow($r)
{
    $s = 0;
    if (!empty($r['vendor_name'])) $s += 4;
    if (!empty($r['vendor_id'])) $s += 2;
    if (!empty($r['invoice_format']) && in_array($r['invoice_format'], array('21','22','23','24','25'), true)) $s += 2;
    if (!empty($r['deduction_category']) && in_array($r['deduction_category'], array('deductible_purchase','deductible_asset'), true)) $s += 2;
    if (!empty($r['deduction_type']) && $r['deduction_type'] === 'deductible') $s += 1;
    return $s;
}
$templates = array();
foreach ($byTid as $tid => $rows) {
    $best = null; $bestScore = -1;
    foreach ($rows as $r) {
        $sc = _scoreRow($r);
        if ($sc > $bestScore) { $bestScore = $sc; $best = $r; }
    }
    if ($best && $bestScore > 0) $templates[$tid] = $best;
}

// 4) 掃描需回補的發票
$toUpdate = array();
foreach ($byTid as $tid => $rows) {
    $tpl = isset($templates[$tid]) ? $templates[$tid] : null;
    $vend = isset($vendorIdx[$tid]) ? $vendorIdx[$tid] : null;
    foreach ($rows as $r) {
        $fill = array();
        // vendor_name：優先用 vendors 表，退而用模板
        if (empty($r['vendor_name'])) {
            if ($vend) $fill['vendor_name'] = $vend['name'];
            elseif ($tpl && !empty($tpl['vendor_name'])) $fill['vendor_name'] = $tpl['vendor_name'];
        }
        // vendor_id：優先用 vendors 表
        if (empty($r['vendor_id'])) {
            if ($vend) $fill['vendor_id'] = $vend['id'];
            elseif ($tpl && !empty($tpl['vendor_id'])) $fill['vendor_id'] = (int)$tpl['vendor_id'];
        }
        // invoice_format：用模板
        if ((empty($r['invoice_format']) || !in_array($r['invoice_format'], array('21','22','23','24','25'), true))
                && $tpl && !empty($tpl['invoice_format'])
                && in_array($tpl['invoice_format'], array('21','22','23','24','25'), true)) {
            $fill['invoice_format'] = $tpl['invoice_format'];
        }
        // deduction_type / deduction_category：用模板
        if (empty($r['deduction_type']) && $tpl && !empty($tpl['deduction_type'])) {
            $fill['deduction_type'] = $tpl['deduction_type'];
        }
        if (empty($r['deduction_category']) && $tpl && !empty($tpl['deduction_category'])) {
            $fill['deduction_category'] = $tpl['deduction_category'];
        }
        if (!empty($fill)) {
            $toUpdate[] = array('id' => (int)$r['id'], 'invoice_number' => $r['invoice_number'], 'tid' => $tid, 'fill' => $fill);
        }
    }
}

echo "進項發票共 " . count($all) . " 筆（依 vendor_tax_id 分群 " . count($byTid) . " 群）\n";
echo "可回補的發票：" . count($toUpdate) . " 筆\n";
echo str_repeat('-', 90) . "\n";

// 預覽前 20 筆
foreach (array_slice($toUpdate, 0, 20) as $u) {
    $parts = array();
    foreach ($u['fill'] as $k => $v) $parts[] = "{$k}={$v}";
    echo sprintf("  %s  (統編 %s)  ← %s\n", $u['invoice_number'], $u['tid'], implode(', ', $parts));
}
if (count($toUpdate) > 20) echo "  ... 另有 " . (count($toUpdate) - 20) . " 筆\n";
echo str_repeat('-', 90) . "\n";

if (!$confirm) {
    echo "\n⚠ 預覽模式，未執行寫入。\n";
    echo "確認無誤後加 ?confirm=1 執行：\n";
    echo "https://hswork.com.tw/run_fill_purchase_by_vendor.php?confirm=1\n";
    exit;
}

echo "\n=== 開始寫入 ===\n";
$db->beginTransaction();
try {
    $updated = 0;
    foreach ($toUpdate as $u) {
        $sets = array();
        $params = array();
        foreach ($u['fill'] as $k => $v) {
            $sets[] = "{$k} = ?";
            $params[] = $v;
        }
        $params[] = $u['id'];
        $sql = "UPDATE purchase_invoices SET " . implode(', ', $sets) . " WHERE id = ?";
        $db->prepare($sql)->execute($params);
        $updated++;
    }
    $db->commit();
    echo "✓ 已回補 {$updated} 筆\n";
    echo "Done.\n";
} catch (Exception $ex) {
    $db->rollBack();
    echo "ERROR: " . $ex->getMessage() . "\n";
}
