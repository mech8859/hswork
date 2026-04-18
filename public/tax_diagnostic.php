<?php
/**
 * 401 申報資料診斷：找出進項發票中可扣抵但資料不齊的，供手動補齊
 */
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (!Auth::hasPermission('finance.manage') && !Auth::hasPermission('finance.view')) {
    Session::flash('error', '無權限存取');
    redirect('/');
}
require_once __DIR__ . '/../modules/accounting/InvoiceModel.php';

$model = new InvoiceModel();
$taxPeriodOptions = $model->getTaxPeriodOptions();
$period = !empty($_GET['period']) ? $_GET['period'] : $taxPeriodOptions[0]['value'];

// 解析期間取月份清單
$db = Database::getInstance();
$parsed = null;
if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $period, $m)) {
    $parsed = array('start' => $m[1] . $m[2], 'end' => $m[1] . $m[3]);
}
$months = array();
if ($parsed) {
    $current = $parsed['start'];
    while ($current <= $parsed['end']) {
        $months[] = $current;
        $y = (int)substr($current, 0, 4);
        $mo = (int)substr($current, 4, 2) + 1;
        if ($mo > 12) { $mo = 1; $y++; }
        $current = sprintf('%04d%02d', $y, $mo);
    }
}

$problems = array();
if (!empty($months)) {
    // 用發票日期範圍做篩選（避開 period 欄位 NULL 或未同步問題）
    $startMonth = $months[0];
    $endMonth = end($months);
    $dateStart = substr($startMonth, 0, 4) . '-' . substr($startMonth, 4, 2) . '-01';
    $endY = (int)substr($endMonth, 0, 4);
    $endM = (int)substr($endMonth, 4, 2);
    $dateEnd = sprintf('%04d-%02d-%02d', $endY, $endM, (int)date('t', strtotime("$endY-$endM-01")));
    // 以 report_period 或發票日期區間任一符合：更完整地抓到應屬本期的發票
    $sql = "SELECT id, invoice_number, invoice_date, vendor_name, amount_untaxed, tax_amount,
                    deduction_type, deduction_category, invoice_format, status, period, report_period
            FROM purchase_invoices
            WHERE status != 'voided'
              AND (
                    report_period = ?
                    OR (invoice_date BETWEEN ? AND ?)
                  )
            ORDER BY invoice_date ASC";
    $stmt = $db->prepare($sql);
    $stmt->execute(array($period, $dateStart, $dateEnd));
    $allRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stdFormats = array('21', '22', '23', '24', '25');
    foreach ($allRows as $r) {
        $issues = array();
        $dt = $r['deduction_type'] ?? '';
        $dc = $r['deduction_category'] ?? '';
        $fmt = $r['invoice_format'] ?? '';
        $per = $r['period'] ?? '';
        $rp  = $r['report_period'] ?? '';

        // period（YYYYMM）欄位檢查 — 401 彙總靠這個欄位
        $expectedPeriod = !empty($r['invoice_date']) ? date('Ym', strtotime($r['invoice_date'])) : '';
        if (empty($per)) $issues[] = '缺 period（YYYYMM）— 401 彙總會漏計';
        elseif ($expectedPeriod && $per !== $expectedPeriod) $issues[] = "period={$per} 與發票日期不符(應={$expectedPeriod})";
        if (empty($rp)) $issues[] = '缺 report_period';

        if ($dt === '' || $dt === null) $issues[] = '缺扣抵別(deduction_type)';
        if ($fmt === '' || $fmt === null) $issues[] = '缺聯式';
        elseif (!in_array($fmt, $stdFormats, true)) $issues[] = "聯式碼 {$fmt} 非標準(應為 21-25)";

        if ($dt === 'deductible') {
            if ($dc === '' || $dc === null) $issues[] = '缺扣抵類別(deduction_category)';
            elseif ($dc === 'non_deductible') $issues[] = '扣抵類別=不可扣抵 與 deduction_type=可扣抵 矛盾';
            // 聯式 23/24 是退出折讓，類別應為 deductible_purchase 或 deductible_asset
            if (in_array($fmt, array('23', '24'), true) && !in_array($dc, array('deductible_purchase', 'deductible_asset'), true)) {
                $issues[] = '退出折讓未設為進貨或固定資產';
            }
        }
        if (!empty($issues)) {
            $r['_issues'] = $issues;
            $problems[] = $r;
        }
    }
}

$pageTitle = '401 申報資料診斷';
$currentPage = 'tax_report';
require __DIR__ . '/../templates/layouts/header.php';
?>
<div class="d-flex justify-between align-center flex-wrap gap-1 mb-2">
    <h2>401 申報資料診斷 - 進項發票</h2>
    <a href="/tax_report.php?period=<?= e($period) ?>" class="btn btn-outline btn-sm">← 回 401 稅報</a>
</div>

<div class="card">
    <form method="GET" class="d-flex gap-1 align-center">
        <label>申報期間</label>
        <select name="period" class="form-control" style="width:auto" onchange="this.form.submit()">
            <?php foreach ($taxPeriodOptions as $opt): ?>
            <option value="<?= e($opt['value']) ?>" <?= $period === $opt['value'] ? 'selected' : '' ?>><?= e($opt['label']) ?></option>
            <?php endforeach; ?>
        </select>
    </form>
</div>

<div class="card mt-2">
    <div class="card-header">
        需補齊的進項發票（<?= count($problems) ?> 筆）
        <small style="color:#888;font-weight:normal;display:block;margin-top:4px">檢查項目：缺扣抵別 / 缺聯式 / 聯式碼非 21-25 / 可扣抵但缺扣抵類別 / 類別矛盾 / 退出折讓類別未設</small>
    </div>
    <?php if (empty($problems)): ?>
        <p class="text-muted text-center mt-2" style="padding:20px">✓ 此期間資料完整，無需補齊</p>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>發票號碼</th>
                    <th>日期</th>
                    <th>供應商</th>
                    <th class="text-right">未稅</th>
                    <th class="text-right">稅額</th>
                    <th>聯式</th>
                    <th>扣抵類別</th>
                    <th>問題</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $totalTax = 0;
                foreach ($problems as $p):
                    $issues = $p['_issues'];
                    $totalTax += (int)$p['tax_amount'];
                ?>
                <tr>
                    <td><a href="/purchase_invoices.php?action=edit&id=<?= (int)$p['id'] ?>" target="_blank"><?= e($p['invoice_number'] ?: '-') ?></a></td>
                    <td><?= e($p['invoice_date']) ?></td>
                    <td><?= e($p['vendor_name']) ?></td>
                    <td class="text-right">$<?= number_format((int)$p['amount_untaxed']) ?></td>
                    <td class="text-right">$<?= number_format((int)$p['tax_amount']) ?></td>
                    <td><?= e($p['invoice_format'] ?: '-') ?></td>
                    <td><?= e($p['deduction_category'] ?: '-') ?></td>
                    <td style="color:var(--danger);font-size:.85rem"><?= e(implode('；', $issues)) ?></td>
                    <td><a href="/purchase_invoices.php?action=edit&id=<?= (int)$p['id'] ?>" target="_blank" class="btn btn-primary btn-sm">編輯</a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr style="font-weight:600;background:var(--gray-50,#f8f9fa)">
                    <td colspan="4">合計稅額</td>
                    <td class="text-right">$<?= number_format($totalTax) ?></td>
                    <td colspan="4">← 這就是頂部卡片與格107的差額來源</td>
                </tr>
            </tfoot>
        </table>
    </div>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/../templates/layouts/footer.php'; ?>
