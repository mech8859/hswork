<?php
$matchedCnt = count($ivrData['matched']);
$missingCnt = count($ivrData['missing_voucher']);
$orphanCnt  = count($ivrData['orphan_voucher']);
$typeLabel = $ivrType === 'sales' ? '銷項' : '進項';
$partyLabel = $ivrType === 'sales' ? '客戶' : '供應商';

// 稅額相符篩選（從 GET 取，僅作用於 matched 分頁）
$ivrTaxMatch = isset($_GET['tax_match']) ? $_GET['tax_match'] : '';
if (!in_array($ivrTaxMatch, array('match', 'mismatch'))) $ivrTaxMatch = '';

// 預先計算 matched 內 相符/不符 數量（給統計卡顯示用）
$matchedTotalCnt    = $matchedCnt;
$matchedSubMatchCnt = 0;
$matchedSubMismatchCnt = 0;
foreach ($ivrData['matched'] as $r) {
    $diff = isset($r['tax_diff']) ? (int)$r['tax_diff'] : null;
    if ($diff === null) continue;
    if ($diff === 0) $matchedSubMatchCnt++;
    else $matchedSubMismatchCnt++;
}

// 篩選 matched 結果
if ($ivrTab === 'matched' && $ivrTaxMatch !== '') {
    $ivrData['matched'] = array_values(array_filter($ivrData['matched'], function($r) use ($ivrTaxMatch) {
        $diff = isset($r['tax_diff']) ? (int)$r['tax_diff'] : null;
        if ($diff === null) return $ivrTaxMatch === '';   // 沒匹配資訊的不計
        if ($ivrTaxMatch === 'match')    return $diff === 0;
        if ($ivrTaxMatch === 'mismatch') return $diff !== 0;
        return true;
    }));
}

// 額外篩選：發票號碼/金額/客戶/聯式
$ivrKeyword       = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';
$ivrPartyKeyword  = isset($_GET['party']) ? trim($_GET['party']) : '';
$ivrPartyTaxId    = isset($_GET['party_tax_id']) ? trim($_GET['party_tax_id']) : '';
$ivrInvoiceFormat = isset($_GET['invoice_format']) ? trim($_GET['invoice_format']) : '';

// 蒐集當前資料中的客戶/供應商清單與聯式清單（給下拉用，未篩前的全集）
$_partyList = array();
$_formatList = array();
foreach (array('matched', 'missing_voucher', 'orphan_voucher') as $_grpKey) {
    foreach ($ivrData[$_grpKey] as $r) {
        if (!empty($r['party_name'])) $_partyList[$r['party_name']] = true;
        if (!empty($r['invoice_format'])) $_formatList[(string)$r['invoice_format']] = true;
    }
}
ksort($_partyList);
ksort($_formatList);

// 統一篩選函式
$applyExtraFilters = function($rows) use ($ivrKeyword, $ivrPartyKeyword, $ivrPartyTaxId, $ivrInvoiceFormat) {
    return array_values(array_filter($rows, function($r) use ($ivrKeyword, $ivrPartyKeyword, $ivrPartyTaxId, $ivrInvoiceFormat) {
        // 客戶/供應商（部分包含即可）
        if ($ivrPartyKeyword !== '' && mb_stripos((string)($r['party_name'] ?? ''), $ivrPartyKeyword) === false) return false;
        // 客戶/供應商統編（部分包含）
        if ($ivrPartyTaxId !== '' && mb_stripos((string)($r['party_tax_id'] ?? ''), $ivrPartyTaxId) === false) return false;
        // 聯式
        if ($ivrInvoiceFormat !== '' && (string)($r['invoice_format'] ?? '') !== $ivrInvoiceFormat) return false;
        // 關鍵字（發票號碼 或 金額；金額用 $1500 格式精準比對）
        if ($ivrKeyword !== '') {
            $kw = $ivrKeyword;
            if (preg_match('/^\$\s*([\d,]+(?:\.\d+)?)$/u', $kw, $m)) {
                $amt = (float)str_replace(',', '', $m[1]);
                $tot = (float)($r['total_amount'] ?? 0);
                $dr  = (float)($r['total_debit'] ?? 0);
                if (abs($tot - $amt) > 0.01 && abs($dr - $amt) > 0.01) return false;
            } else {
                $haystack = (string)($r['invoice_number'] ?? '') . ' ' . (string)($r['voucher_number'] ?? '') . ' ' . (string)($r['inv_number'] ?? '');
                if (mb_stripos($haystack, $kw) === false) return false;
            }
        }
        return true;
    }));
};
$ivrData['matched']         = $applyExtraFilters($ivrData['matched']);
$ivrData['missing_voucher'] = $applyExtraFilters($ivrData['missing_voucher']);
$ivrData['orphan_voucher']  = $applyExtraFilters($ivrData['orphan_voucher']);

// 日期排序（新→舊 / 舊→新）
$ivrSort = isset($_GET['sort']) && $_GET['sort'] === 'asc' ? 'asc' : 'desc';
$_dateKey = function($r) {
    if (!empty($r['invoice_date'])) return $r['invoice_date'];
    if (!empty($r['voucher_date'])) return $r['voucher_date'];
    return '0000-00-00';
};
usort($ivrData['matched'],         function($a, $b) use ($ivrSort, $_dateKey) { $c = strcmp($_dateKey($a), $_dateKey($b)); return $ivrSort === 'asc' ? $c : -$c; });
usort($ivrData['missing_voucher'], function($a, $b) use ($ivrSort, $_dateKey) { $c = strcmp($_dateKey($a), $_dateKey($b)); return $ivrSort === 'asc' ? $c : -$c; });
usort($ivrData['orphan_voucher'],  function($a, $b) use ($ivrSort, $_dateKey) { $c = strcmp($_dateKey($a), $_dateKey($b)); return $ivrSort === 'asc' ? $c : -$c; });

function _ivrFmtAmt($n) { return '$' . number_format((int)$n); }

// 聯式代碼 → 顯示文字
$_ivrFormatLabels = $ivrType === 'sales'
    ? array(
        '31' => '31：銷項三聯式',
        '32' => '32：銷項二聯式',
        '33' => '33：三聯式銷貨退回/折讓',
        '34' => '34：二聯式銷貨退回/折讓',
        '35' => '35：銷項三聯式收銀機/電子發票',
    )
    : array(
        '21' => '21：進項三聯式',
        '22' => '22：進項二聯式',
        '23' => '23：三聯式進貨退出/折讓',
        '24' => '24：二聯式進貨退出/折讓',
        '25' => '25：進項三聯式收銀機/電子發票',
    );
$_fmtLabel = function($code) use ($_ivrFormatLabels) {
    $code = (string)$code;
    return isset($_ivrFormatLabels[$code]) ? $_ivrFormatLabels[$code] : $code;
};

$baseUrl = '/accounting.php?action=invoice_voucher_reconciliation';
$qs = function($overrides = array()) use ($ivrType, $ivrStart, $ivrEnd, $ivrTaxId, $ivrTab, $ivrTaxMatch, $ivrSort, $ivrKeyword, $ivrPartyKeyword, $ivrPartyTaxId, $ivrInvoiceFormat) {
    $params = array_merge(array(
        'type' => $ivrType, 'start_date' => $ivrStart, 'end_date' => $ivrEnd,
        'company_tax_id' => $ivrTaxId, 'tab' => $ivrTab,
        'tax_match' => $ivrTaxMatch,
        'sort' => $ivrSort === 'desc' ? '' : $ivrSort,  // desc 是預設，不需放入 URL
        'keyword' => $ivrKeyword,
        'party' => $ivrPartyKeyword,
        'party_tax_id' => $ivrPartyTaxId,
        'invoice_format' => $ivrInvoiceFormat,
    ), $overrides);
    return http_build_query(array_filter($params, function($v){ return $v !== ''; }));
};

// 給外連用的 return_to：當前完整 URL（不含 hash），讓細項頁可帶回
$_ivrReturnBase = '/accounting.php?action=invoice_voucher_reconciliation&' . $qs();
// 每列額外帶 focus_row_id（hash），讓回到本頁時可 scroll 到該列
$_ivrReturnFor = function($rowId) use ($_ivrReturnBase) {
    return $_ivrReturnBase . '#row-' . (int)$rowId;
};

$totalAmt = function($rows, $col) {
    $s = 0; foreach ($rows as $r) $s += (int)($r[$col] ?? 0); return $s;
};
?>
<div class="d-flex justify-between align-center flex-wrap gap-1 mb-2">
    <h2>發票↔傳票對帳
        <span class="badge" style="background:<?= $ivrType === 'sales' ? '#1565c0' : '#7b1fa2' ?>;color:#fff;font-size:.6em;vertical-align:middle">
            <?= e($typeLabel) ?>
        </span>
    </h2>
    <div class="d-flex gap-1">
        <a href="<?= $baseUrl ?>&<?= $qs(array('type' => 'sales', 'tab' => 'missing')) ?>"
           class="btn btn-sm <?= $ivrType === 'sales' ? 'btn-primary' : 'btn-outline' ?>">銷項</a>
        <a href="<?= $baseUrl ?>&<?= $qs(array('type' => 'purchase', 'tab' => 'missing')) ?>"
           class="btn btn-sm <?= $ivrType === 'purchase' ? 'btn-primary' : 'btn-outline' ?>">進項</a>
        <a href="/accounting.php?action=voucher_reconciliation" class="btn btn-outline btn-sm">傳票核對報表</a>
    </div>
</div>

<!-- 篩選 -->
<div class="card" style="padding:12px">
    <form method="GET" class="d-flex gap-1 flex-wrap align-center">
        <input type="hidden" name="action" value="invoice_voucher_reconciliation">
        <input type="hidden" name="type" value="<?= e($ivrType) ?>">
        <input type="hidden" name="tab" value="<?= e($ivrTab) ?>">
        <label style="font-size:.9rem">期間：</label>
        <input type="date" name="start_date" class="form-control" style="width:auto" value="<?= e($ivrStart) ?>">
        <span>~</span>
        <input type="date" name="end_date" class="form-control" style="width:auto" value="<?= e($ivrEnd) ?>">
        <label style="font-size:.9rem;margin-left:8px">公司：</label>
        <select name="company_tax_id" class="form-control" style="width:auto;min-width:200px">
            <option value="" <?= $ivrTaxId === '' ? 'selected' : '' ?>>全部</option>
            <option value="94081455" <?= $ivrTaxId === '94081455' ? 'selected' : '' ?>>94081455 禾順監視數位科技</option>
            <option value="97002927" <?= $ivrTaxId === '97002927' ? 'selected' : '' ?>>97002927 政遠企業</option>
        </select>
        <label style="font-size:.9rem;margin-left:8px">排序：</label>
        <select name="sort" class="form-control" style="width:auto">
            <option value="desc" <?= $ivrSort === 'desc' ? 'selected' : '' ?>>日期 新 → 舊</option>
            <option value="asc"  <?= $ivrSort === 'asc'  ? 'selected' : '' ?>>日期 舊 → 新</option>
        </select>
        <button type="submit" class="btn btn-primary btn-sm">查詢</button>
        <div style="flex-basis:100%;height:0"></div>
        <input type="text" name="keyword" class="form-control" style="width:auto;min-width:180px"
               value="<?= e($ivrKeyword) ?>" placeholder="發票/傳票號碼 或 $1500 金額">
        <input type="text" name="party" class="form-control" list="ivrPartyList"
               style="width:auto;min-width:200px" placeholder="<?= e($partyLabel) ?>：全部（可下拉選或輸入）"
               value="<?= e($ivrPartyKeyword) ?>">
        <datalist id="ivrPartyList">
            <?php foreach (array_keys($_partyList) as $_pn): ?>
            <option value="<?= e($_pn) ?>"></option>
            <?php endforeach; ?>
        </datalist>
        <input type="text" name="party_tax_id" class="form-control"
               style="width:auto;min-width:160px"
               placeholder="<?= $ivrType === 'sales' ? '客戶/買方統一編號' : '供應商/賣方統一編號' ?>"
               value="<?= e($ivrPartyTaxId) ?>">
        <select name="invoice_format" class="form-control" style="width:auto;min-width:180px">
            <option value="">聯式：全部</option>
            <?php
            $_allFmts = $ivrType === 'sales'
                ? array('31'=>'31 銷項三聯式','32'=>'32 銷項二聯式','33'=>'33 三聯式銷貨退回','34'=>'34 二聯式銷貨退回','35'=>'35 銷項三聯式收銀機/電子發票')
                : array('21'=>'21 進項三聯式','22'=>'22 進項二聯式','23'=>'23 三聯式進貨退出','24'=>'24 二聯式進貨退出','25'=>'25 進項三聯式收銀機/電子發票');
            foreach ($_allFmts as $_fk => $_fv): ?>
            <option value="<?= e($_fk) ?>" <?= (string)$ivrInvoiceFormat === (string)$_fk ? 'selected' : '' ?>><?= e($_fv) ?></option>
            <?php endforeach; ?>
        </select>
        <a href="<?= $baseUrl ?>&<?= $qs(array('keyword'=>'','party'=>'','party_tax_id'=>'','invoice_format'=>'')) ?>"
           class="btn btn-outline btn-sm" title="清除上排篩選條件">清除</a>
    </form>
</div>

<!-- 統計卡 -->
<div class="d-flex gap-1 flex-wrap mb-2" style="margin-top:10px">
    <a href="<?= $baseUrl ?>&<?= $qs(array('tab' => 'missing')) ?>"
       style="text-decoration:none;flex:1;min-width:240px">
        <div class="card" style="padding:14px;background:<?= $ivrTab === 'missing' ? '#fff3e0' : '#fff' ?>;border-left:4px solid #ef6c00">
            <div style="font-size:.85rem;color:#666">⚠ 缺傳票（已確認發票，無對應傳票）</div>
            <div style="font-size:1.6rem;font-weight:700;color:#ef6c00"><?= $missingCnt ?> 筆</div>
            <div style="font-size:.85rem;color:#888">含稅合計 <?= _ivrFmtAmt($totalAmt($ivrData['missing_voucher'], 'total_amount')) ?></div>
        </div>
    </a>
    <a href="<?= $baseUrl ?>&<?= $qs(array('tab' => 'orphan')) ?>"
       style="text-decoration:none;flex:1;min-width:240px">
        <div class="card" style="padding:14px;background:<?= $ivrTab === 'orphan' ? '#ffebee' : '#fff' ?>;border-left:4px solid #c62828">
            <div style="font-size:.85rem;color:#666">🚨 孤兒傳票（傳票指向不存在/已作廢/未確認的發票）</div>
            <div style="font-size:1.6rem;font-weight:700;color:#c62828"><?= $orphanCnt ?> 筆</div>
            <div style="font-size:.85rem;color:#888">借方合計 <?= _ivrFmtAmt($totalAmt($ivrData['orphan_voucher'], 'total_debit')) ?></div>
        </div>
    </a>
    <div style="flex:1;min-width:280px;display:flex;flex-direction:column;gap:0">
        <a href="<?= $baseUrl ?>&<?= $qs(array('tab' => 'matched', 'tax_match' => '')) ?>"
           style="text-decoration:none;color:inherit">
            <div class="card" style="padding:14px;background:<?= $ivrTab === 'matched' ? '#e8f5e9' : '#fff' ?>;border-left:4px solid #16a34a;border-bottom:0;border-radius:8px 8px 0 0">
                <div style="font-size:.85rem;color:#666">✓ 已對應</div>
                <div style="font-size:1.6rem;font-weight:700;color:#16a34a"><?= $matchedTotalCnt ?> 筆</div>
                <div style="font-size:.85rem;color:#888">含稅合計 <?= _ivrFmtAmt($totalAmt($ivrData['matched'], 'total_amount')) ?></div>
            </div>
        </a>
        <?php if ($ivrTab === 'matched' && $matchedTotalCnt > 0): ?>
        <div style="display:flex;border:1px solid #c8e6c9;border-top:0;border-radius:0 0 8px 8px;background:#f1f8e9;padding:6px;gap:4px">
            <a href="<?= $baseUrl ?>&<?= $qs(array('tax_match' => '')) ?>"
               style="flex:1;text-align:center;padding:6px 4px;border-radius:4px;text-decoration:none;font-size:.8rem;<?= $ivrTaxMatch === '' ? 'background:#1565c0;color:#fff;font-weight:600' : 'background:#fff;color:#1565c0;border:1px solid #1565c0' ?>">
                全部 <?= $matchedTotalCnt ?>
            </a>
            <a href="<?= $baseUrl ?>&<?= $qs(array('tax_match' => 'match')) ?>"
               style="flex:1;text-align:center;padding:6px 4px;border-radius:4px;text-decoration:none;font-size:.8rem;<?= $ivrTaxMatch === 'match' ? 'background:#16a34a;color:#fff;font-weight:600' : 'background:#fff;color:#16a34a;border:1px solid #16a34a' ?>">
                ✓ 相符 <?= $matchedSubMatchCnt ?>
            </a>
            <a href="<?= $baseUrl ?>&<?= $qs(array('tax_match' => 'mismatch')) ?>"
               style="flex:1;text-align:center;padding:6px 4px;border-radius:4px;text-decoration:none;font-size:.8rem;<?= $ivrTaxMatch === 'mismatch' ? 'background:#c62828;color:#fff;font-weight:600' : 'background:#fff;color:#c62828;border:1px solid #c62828' ?>">
                ⚠ 不符 <?= $matchedSubMismatchCnt ?>
            </a>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- 列表 -->
<div class="card">
<?php if ($ivrTab === 'missing'): ?>
    <div class="card-header">
        ⚠ <?= $typeLabel ?>缺傳票清單（<?= $missingCnt ?> 筆）
        <small style="color:#888;font-weight:normal">這些已確認發票還沒建傳票，會計人員應補建</small>
    </div>
    <?php if (empty($ivrData['missing_voucher'])): ?>
        <p class="text-muted text-center mt-2">✓ 此期間所有已確認<?= $typeLabel ?>發票都有對應傳票</p>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table">
            <thead><tr>
                <th>發票號碼</th><th>日期</th><th>聯式</th><th><?= $partyLabel ?></th>
                <th class="text-right">未稅</th><th class="text-right">稅額</th><th class="text-right">含稅</th><th>動作</th>
            </tr></thead>
            <tbody>
            <?php foreach ($ivrData['missing_voucher'] as $r):
                $_back = urlencode($_ivrReturnFor($r['id']));
            ?>
                <tr id="row-<?= (int)$r['id'] ?>">
                    <td><a href="/<?= $ivrType === 'sales' ? 'sales' : 'purchase' ?>_invoices.php?action=edit&id=<?= (int)$r['id'] ?>&return_to=<?= $_back ?>"><?= e($r['invoice_number']) ?></a></td>
                    <td><?= e($r['invoice_date']) ?></td>
                    <td><span class="badge" style="background:#fce4ec;color:#880e4f;white-space:nowrap"><?= e($_fmtLabel($r['invoice_format'])) ?></span></td>
                    <td><?= e($r['party_name']) ?></td>
                    <td class="text-right"><?= _ivrFmtAmt($r['amount_untaxed']) ?></td>
                    <td class="text-right"><?= _ivrFmtAmt($r['tax_amount']) ?></td>
                    <td class="text-right"><strong><?= _ivrFmtAmt($r['total_amount']) ?></strong></td>
                    <td><a href="/accounting.php?action=journal_create&return_to=<?= $_back ?>" class="btn btn-outline btn-xs" style="font-size:.7rem">+ 建傳票</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
<?php elseif ($ivrTab === 'orphan'): ?>
    <div class="card-header">
        🚨 <?= $typeLabel ?>孤兒傳票（<?= $orphanCnt ?> 筆）
        <small style="color:#888;font-weight:normal">傳票標 source 是發票但發票不存在/已作廢/未確認</small>
    </div>
    <?php if (empty($ivrData['orphan_voucher'])): ?>
        <p class="text-muted text-center mt-2">✓ 沒有孤兒傳票</p>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table">
            <thead><tr>
                <th>傳票號碼</th><th>傳票日期</th><th>摘要</th>
                <th>原綁發票 ID</th><th>發票實際狀態</th><th><?= $partyLabel ?></th>
                <th class="text-right">借方金額</th>
            </tr></thead>
            <tbody>
            <?php foreach ($ivrData['orphan_voucher'] as $r):
                if (empty($r['inv_id'])) { $statusBadge = '<span class="badge badge-danger">不存在/已刪</span>'; }
                else if ($r['inv_status'] === 'voided') { $statusBadge = '<span class="badge badge-warning">已作廢</span>'; }
                else { $statusBadge = '<span class="badge" style="background:#fff3e0;color:#bf360c">' . e($r['inv_status']) . '</span>'; }
                $_back = urlencode($_ivrReturnFor($r['voucher_id']));
            ?>
                <tr id="row-<?= (int)$r['voucher_id'] ?>">
                    <td><a href="/accounting.php?action=journal_view&id=<?= (int)$r['voucher_id'] ?>&return_to=<?= $_back ?>"><?= e($r['voucher_number']) ?></a></td>
                    <td><?= e($r['voucher_date']) ?></td>
                    <td style="font-size:.85rem;color:#555;max-width:280px"><?= e(mb_strimwidth((string)$r['description'], 0, 60, '…', 'UTF-8')) ?></td>
                    <td><?= (int)$r['ref_invoice_id'] ?></td>
                    <td><?= $statusBadge ?></td>
                    <td><?= e($r['party_name'] ?? '-') ?></td>
                    <td class="text-right"><?= _ivrFmtAmt($r['total_debit']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
<?php else: /* matched */ ?>
    <div class="card-header">
        ✓ <?= $typeLabel ?>已對應清單
        <?php if ($ivrTaxMatch === 'match'): ?>
            — 稅額相符（<?= count($ivrData['matched']) ?> / <?= $matchedCnt ?> 筆）
        <?php elseif ($ivrTaxMatch === 'mismatch'): ?>
            — 稅額不符（<?= count($ivrData['matched']) ?> / <?= $matchedCnt ?> 筆）
        <?php else: ?>
            （<?= $matchedCnt ?> 筆）
        <?php endif; ?>
    </div>
    <?php if (empty($ivrData['matched'])): ?>
        <p class="text-muted text-center mt-2">此期間無已對應的發票</p>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table">
            <thead><tr>
                <th>發票號碼</th><th>日期</th><th>聯式</th><th><?= $partyLabel ?></th>
                <th class="text-right">發票稅額</th>
                <th>傳票號碼</th>
                <th class="text-right">對應稅額</th>
                <th>匹配方式</th>
                <th>稅額相符</th>
            </tr></thead>
            <tbody>
            <?php foreach ($ivrData['matched'] as $r):
                // 33/34/23/24 視為減項，發票稅額顯示負值
                $invTax = isset($r['invoice_signed_tax']) ? (int)round((float)$r['invoice_signed_tax']) : (int)$r['tax_amount'];
                $matchedTax = isset($r['matched_tax_sum']) ? (int)round((float)$r['matched_tax_sum']) : null;
                $taxDiff = isset($r['tax_diff']) ? (int)$r['tax_diff'] : null;
                $_back = urlencode($_ivrReturnFor($r['id']));
            ?>
                <tr id="row-<?= (int)$r['id'] ?>" <?= ($taxDiff !== null && $taxDiff !== 0) ? 'style="background:#fff3e0"' : '' ?>>
                    <td><a href="/<?= $ivrType === 'sales' ? 'sales' : 'purchase' ?>_invoices.php?action=edit&id=<?= (int)$r['id'] ?>&return_to=<?= $_back ?>"><?= e($r['invoice_number']) ?></a></td>
                    <td><?= e($r['invoice_date']) ?></td>
                    <td><span class="badge" style="background:#e8eaf6;color:#283593;white-space:nowrap"><?= e($_fmtLabel($r['invoice_format'])) ?></span></td>
                    <td><?= e($r['party_name']) ?></td>
                    <td class="text-right"><?= _ivrFmtAmt($invTax) ?></td>
                    <td>
                        <?php if (($r['matched_voucher_count'] ?? 1) > 1 && !empty($r['matched_vouchers'])): ?>
                            <details style="display:inline-block">
                                <summary style="cursor:pointer;list-style:none;color:#1565c0;user-select:none">📚 <?= (int)$r['matched_voucher_count'] ?> 張 ▼</summary>
                                <div style="background:#fff;border:1px solid #ddd;border-radius:4px;padding:6px 10px;margin-top:4px;font-size:.85rem;min-width:200px">
                                <?php foreach ($r['matched_vouchers'] as $mv): ?>
                                    <div style="padding:2px 0;border-bottom:1px dashed #eee">
                                        <a href="/accounting.php?action=journal_view&id=<?= (int)$mv['voucher_id'] ?>&return_to=<?= $_back ?>"><?= e($mv['voucher_number']) ?></a>
                                        <span style="color:#888;float:right"><?= _ivrFmtAmt($mv['tax_amount']) ?></span>
                                    </div>
                                <?php endforeach; ?>
                                </div>
                            </details>
                        <?php else: ?>
                            <a href="/accounting.php?action=journal_view&id=<?= (int)$r['voucher_id'] ?>&return_to=<?= $_back ?>"><?= e($r['voucher_number']) ?></a>
                        <?php endif; ?>
                    </td>
                    <td class="text-right">
                        <?= $matchedTax !== null ? _ivrFmtAmt($matchedTax) : '—' ?>
                    </td>
                    <td>
                        <?php $mt = $r['match_type'] ?? ''; ?>
                        <?php if ($mt === 'tax_account'): ?>
                            <span class="badge" style="background:#16a34a;color:#fff;font-size:.7rem" title="稅額科目 + 發票號碼，最可信">🎯 稅額科目</span>
                        <?php elseif ($mt === 'precise'): ?>
                            <span class="badge" style="background:#1565c0;color:#fff;font-size:.7rem" title="source_module + source_id">✓ 精準</span>
                        <?php else: ?>
                            <span class="badge" style="background:#f9a825;color:#fff;font-size:.7rem" title="文字匹配 fallback">📝 摘要</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($taxDiff === null): ?>
                            <span style="color:#888">—</span>
                        <?php elseif ($taxDiff === 0): ?>
                            <span style="color:#16a34a">✓</span>
                        <?php else: ?>
                            <span style="color:#c62828">⚠ 差 <?= _ivrFmtAmt(abs($taxDiff)) ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
<?php endif; ?>
</div>

<script>
// 從細項頁返回時，scroll 到原來那一列並 highlight 一下
(function(){
    if (window.location.hash && window.location.hash.indexOf('#row-') === 0) {
        var el = document.querySelector(window.location.hash);
        if (el) {
            el.scrollIntoView({behavior:'auto', block:'center'});
            var origBg = el.style.background;
            el.style.transition = 'background .8s';
            el.style.background = '#fff59d';
            setTimeout(function(){ el.style.background = origBg; }, 1500);
        }
    }
})();
</script>
