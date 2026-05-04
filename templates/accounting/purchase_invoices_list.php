<div class="page-sticky-head">
<div class="d-flex justify-between align-center flex-wrap gap-1 mb-2">
    <h2>進項發票管理 <span style="font-size:.7em;color:var(--gray-500);font-weight:normal">(共 <?= number_format($result['total']) ?> 筆)</span></h2>
    <?php if ($canManage): ?>
    <a href="/purchase_invoices.php?action=create" class="btn btn-primary btn-sm">+ 新增進項發票</a>
    <?php endif; ?>
</div>

<div class="card">
    <form method="GET" action="/purchase_invoices.php" class="filter-form">
        <div class="filter-row">
            <?php
            // 未提交搜尋時預設禾順 94081455；有提交過就維持使用者選擇
            $_buyerTaxDefault = isset($_GET['buyer_tax_id']) ? $_GET['buyer_tax_id'] : '94081455';
            ?>
            <div class="form-group">
                <label>買方統一編號</label>
                <select name="buyer_tax_id" class="form-control">
                    <option value="" <?= $_buyerTaxDefault === '' ? 'selected' : '' ?>>全部買方</option>
                    <option value="94081455" <?= $_buyerTaxDefault === '94081455' ? 'selected' : '' ?>>94081455 禾順監視數位科技有限公司</option>
                    <option value="97002927" <?= $_buyerTaxDefault === '97002927' ? 'selected' : '' ?>>97002927 政遠企業有限公司</option>
                    <option value="__empty__" <?= $_buyerTaxDefault === '__empty__' ? 'selected' : '' ?> style="color:#c5221f">⚠ 未設定買方</option>
                </select>
            </div>
            <div class="form-group">
                <label>日期</label>
                <div style="display:flex;gap:6px;align-items:center">
                    <input type="date" name="date_from" class="form-control" value="<?= e(!empty($filters['date_from']) ? $filters['date_from'] : '') ?>" style="min-width:140px">
                    <span style="color:#888">~</span>
                    <input type="date" name="date_to" class="form-control" value="<?= e(!empty($filters['date_to']) ? $filters['date_to'] : '') ?>" style="min-width:140px">
                </div>
            </div>
            <div class="form-group">
                <label>申報期間</label>
                <select name="report_period" class="form-control">
                    <option value="">全部</option>
                    <?php foreach ($taxPeriodOptions as $_opt): ?>
                    <option value="<?= e($_opt['value']) ?>" <?= (!empty($filters['report_period']) && $filters['report_period'] === $_opt['value']) ? 'selected' : '' ?>><?= e($_opt['label']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>供應商</label>
                <input type="text" name="vendor" class="form-control" value="<?= e(!empty($filters['vendor']) ? $filters['vendor'] : '') ?>" placeholder="名稱/統編">
            </div>
            <div class="form-group">
                <label>狀態</label>
                <select name="status" class="form-control">
                    <option value="">全部</option>
                    <?php foreach (InvoiceModel::invoiceStatusOptions() as $k => $v): ?>
                    <option value="<?= e($k) ?>" <?= (!empty($filters['status']) && $filters['status'] === $k) ? 'selected' : '' ?>><?= e($v) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>進項發票聯式</label>
                <select name="invoice_format" class="form-control">
                    <option value="">全部</option>
                    <option value="__empty__" <?= (!empty($filters['invoice_format']) && $filters['invoice_format'] === '__empty__') ? 'selected' : '' ?> style="color:#c5221f">⚠ 未設定聯式</option>
                    <?php
                    $_formatOpts = array(
                        '21' => '21：進項三聯式、電子計算機統一發票',
                        '22' => '22：進項二聯式收銀機統一發票、載有稅額之其他憑證',
                        '23' => '23：三聯式進貨退出或折讓證明單',
                        '24' => '24：二聯式進貨退出或折讓證明單',
                        '25' => '25：進項三聯式收銀機統一發票、公用事業憑證',
                    );
                    foreach ($_formatOpts as $_k => $_v):
                    ?>
                    <option value="<?= e($_k) ?>" <?= (!empty($filters['invoice_format']) && $filters['invoice_format'] === $_k) ? 'selected' : '' ?>><?= e($_v) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>關鍵字</label>
                <input type="text" name="keyword" class="form-control" value="<?= e(!empty($filters['keyword']) ? $filters['keyword'] : '') ?>" placeholder="發票號碼/備註/統編/日期/申報期間/金額（$1500 精準比對）">
            </div>
            <div class="form-group">
                <label>排序</label>
                <?php $_sortVal = !empty($filters['sort']) && $filters['sort'] === 'asc' ? 'asc' : 'desc'; ?>
                <select name="sort" class="form-control">
                    <option value="desc" <?= $_sortVal === 'desc' ? 'selected' : '' ?>>新 → 舊</option>
                    <option value="asc"  <?= $_sortVal === 'asc'  ? 'selected' : '' ?>>舊 → 新</option>
                </select>
            </div>
            <div class="form-group" style="align-self:flex-end">
                <button type="submit" class="btn btn-primary btn-sm">搜尋</button>
                <a href="/purchase_invoices.php" class="btn btn-outline btn-sm">清除</a>
            </div>
        </div>
    </form>
</div>
</div><!-- /.page-sticky-head -->

<?php if (!empty($result['summary'])): ?>
<div class="card" style="padding:10px 14px;margin-bottom:10px;background:#e3f2fd;border-left:4px solid #1565c0">
    <div style="display:flex;gap:24px;flex-wrap:wrap;font-size:.95rem">
        <span>📊 <strong>聯式篩選合計</strong>（<?= number_format($result['total']) ?> 筆）</span>
        <span>未稅：<strong>$<?= number_format((int)$result['summary']['subtotal']) ?></strong></span>
        <span>稅額：<strong>$<?= number_format((int)$result['summary']['tax']) ?></strong></span>
        <span>含稅：<strong style="color:var(--primary)">$<?= number_format((int)$result['summary']['total']) ?></strong></span>
    </div>
</div>
<?php endif; ?>

<div class="card">
    <?php if (empty($records)): ?>
        <p class="text-muted text-center mt-2">目前無進項發票資料</p>
    <?php else: ?>
    <!-- 手機版卡片 -->
    <div class="staff-cards show-mobile">
        <?php foreach ($records as $r): ?>
        <div class="staff-card" onclick="location.href='/purchase_invoices.php?action=edit&id=<?= $r['id'] ?>'">
            <div class="d-flex justify-between align-center">
                <strong><?= e(!empty($r['invoice_number']) ? $r['invoice_number'] : '-') ?></strong>
                <span class="badge badge-<?= $r['status'] === 'voided' ? 'danger' : ($r['status'] === 'confirmed' ? 'success' : 'warning') ?>" style="font-size:.75rem">
                    <?= e(InvoiceModel::invoiceStatusOptions()[$r['status']]) ?>
                </span>
            </div>
            <div class="staff-card-meta">
                <span><?= e(!empty($r['invoice_date']) ? $r['invoice_date'] : '') ?></span>
                <?php
                    $rpDisplay = '';
                    $rp = !empty($r['report_period']) ? $r['report_period'] : '';
                    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $rp, $_mm)) {
                        $rpDisplay = $_mm[1] . '/' . (int)$_mm[2] . '-' . (int)$_mm[3] . '月';
                    } elseif (preg_match('/^(\d{4})-(\d{2})$/', $rp, $_mm)) {
                        $rpDisplay = $_mm[1] . '/' . $_mm[2];
                    } elseif (!empty($r['period']) && strlen($r['period']) >= 6) {
                        $rpDisplay = substr($r['period'], 0, 4) . '/' . substr($r['period'], 4, 2);
                    }
                    if ($rpDisplay): ?>
                <span style="color:#1565c0">申報 <?= e($rpDisplay) ?></span>
                <?php endif; ?>
                <span><?= e(!empty($r['vendor_name']) ? $r['vendor_name'] : '-') ?></span>
            </div>
            <div class="staff-card-meta">
                <span>未稅 $<?= number_format(!empty($r['amount_untaxed']) ? $r['amount_untaxed'] : 0) ?></span>
                <span>稅 $<?= number_format(!empty($r['tax_amount']) ? $r['tax_amount'] : 0) ?></span>
                <span>含稅 $<?= number_format(!empty($r['total_amount']) ? $r['total_amount'] : 0) ?></span>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- 桌面版表格 -->
    <div class="table-responsive hide-mobile">
        <table class="table">
            <thead class="sticky-thead">
                <tr>
                    <th style="width:32px"></th>
                    <th>發票號碼</th>
                    <th>日期</th>
                    <th>聯式</th>
                    <th>申報期間</th>
                    <th>供應商</th>
                    <th>統編</th>
                    <th class="text-right">未稅金額</th>
                    <th class="text-right">稅額</th>
                    <th class="text-right">含稅金額</th>
                    <th>類型</th>
                    <th>扣抵別</th>
                    <th>狀態</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $_pfLabels = array(
                    '21' => '進項三聯式、電子計算機統一發票',
                    '22' => '進項二聯式收銀機統一發票、載有稅額之其他憑證',
                    '23' => '三聯式進貨退出或折讓證明單',
                    '24' => '二聯式進貨退出或折讓證明單',
                    '25' => '進項三聯式收銀機統一發票、公用事業憑證',
                );
                ?>
                <?php foreach ($records as $r): $isStar = !empty($r['is_starred']); ?>
                <?php
                    $_fmt = !empty($r['invoice_format']) ? $r['invoice_format'] : '';
                    $_fmtTitle = isset($_pfLabels[$_fmt]) ? $_fmt . '：' . $_pfLabels[$_fmt] : $_fmt;
                    $_isAllowance = ($_fmt === '23' || $_fmt === '24');
                ?>
                <tr<?= $r['status'] === 'voided' ? ' style="opacity:.5;text-decoration:line-through"' : '' ?>>
                    <td class="text-center"><span class="star-toggle <?= $isStar ? 'is-on' : '' ?>" data-id="<?= (int)$r['id'] ?>" onclick="toggleStarPurchaseInvoice(this)" title="標記">&#9733;</span></td>
                    <td><a href="/purchase_invoices.php?action=edit&id=<?= $r['id'] ?>"><?= e(!empty($r['invoice_number']) ? $r['invoice_number'] : '-') ?></a></td>
                    <td><?= e(!empty($r['invoice_date']) ? $r['invoice_date'] : '') ?></td>
                    <td>
                        <?php if ($_fmt): ?>
                        <span class="badge" style="background:<?= $_isAllowance ? '#fce4ec;color:#c2185b' : '#e8f5e9;color:#2e7d32' ?>" title="<?= e($_fmtTitle) ?>"><?= e($_fmt) ?></span>
                        <?php else: ?>
                        <span style="color:#bbb">-</span>
                        <?php endif; ?>
                    </td>
                    <td><?php
                        $rpDisplay = '';
                        $rp = !empty($r['report_period']) ? $r['report_period'] : '';
                        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $rp, $_mm)) {
                            $rpDisplay = $_mm[1] . '/' . (int)$_mm[2] . '-' . (int)$_mm[3] . '月';
                        } elseif (preg_match('/^(\d{4})-(\d{2})$/', $rp, $_mm)) {
                            $rpDisplay = $_mm[1] . '/' . $_mm[2];
                        } elseif (!empty($r['period']) && strlen($r['period']) >= 6) {
                            $rpDisplay = substr($r['period'], 0, 4) . '/' . substr($r['period'], 4, 2);
                        }
                        echo $rpDisplay ? e($rpDisplay) : '-';
                    ?></td>
                    <td><?= e(!empty($r['vendor_name']) ? $r['vendor_name'] : '-') ?></td>
                    <td><?= e(!empty($r['vendor_tax_id']) ? $r['vendor_tax_id'] : '-') ?></td>
                    <td class="text-right">$<?= number_format(!empty($r['amount_untaxed']) ? $r['amount_untaxed'] : 0) ?></td>
                    <td class="text-right">$<?= number_format(!empty($r['tax_amount']) ? $r['tax_amount'] : 0) ?></td>
                    <td class="text-right">$<?= number_format(!empty($r['total_amount']) ? $r['total_amount'] : 0) ?></td>
                    <td><?= e(!empty($r['invoice_type']) ? $r['invoice_type'] : '-') ?></td>
                    <td><?php
                        $dcLabels = array(
                            'deductible_purchase' => '<span style="color:var(--success)">進項之費用</span>',
                            'deductible_asset'    => '<span style="color:#1565c0">固定資產</span>',
                            'non_deductible'      => '<span style="color:#999">不可扣抵</span>',
                        );
                        echo !empty($r['deduction_category']) && isset($dcLabels[$r['deduction_category']])
                            ? $dcLabels[$r['deduction_category']]
                            : '';
                    ?></td>
                    <td>
                        <span class="badge badge-<?= $r['status'] === 'voided' ? 'danger' : ($r['status'] === 'confirmed' ? 'success' : 'warning') ?>">
                            <?= e(InvoiceModel::invoiceStatusOptions()[$r['status']]) ?>
                        </span>
                    </td>
                    <td>
                        <a href="/purchase_invoices.php?action=edit&id=<?= $r['id'] ?>" class="btn btn-outline btn-sm">編輯</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
    <?php require __DIR__ . '/../layouts/pagination.php'; ?>
</div>

<style>
.filter-row { display: flex; flex-wrap: wrap; gap: 12px; align-items: flex-end; }
.filter-row .form-group { flex: 1; min-width: 130px; margin-bottom: 0; }
.staff-cards { display: flex; flex-direction: column; gap: 8px; }
.staff-card { border: 1px solid var(--gray-200); border-radius: var(--radius); padding: 12px; cursor: pointer; transition: box-shadow .15s; }
.staff-card:hover { box-shadow: var(--shadow); }
.staff-card-meta { font-size: .8rem; color: var(--gray-500); display: flex; gap: 8px; margin-top: 4px; }
.show-mobile { display: flex; }
.hide-mobile { display: none; }
.badge-danger { background: var(--danger); color: #fff; }
.badge-success { background: var(--success); color: #fff; }
.badge-warning { background: var(--warning); color: #fff; }
.badge { padding: 2px 8px; border-radius: 4px; font-size: .8rem; }
@media (min-width: 768px) { .show-mobile { display: none !important; } .hide-mobile { display: block !important; } }
.star-toggle { display:inline-block; cursor:pointer; font-size:1.2rem; color:#d0d0d0; transition:color .15s,transform .15s; user-select:none; line-height:1; }
.star-toggle:hover { color:#f1c40f; transform:scale(1.15); }
.star-toggle.is-on { color:#f1c40f; }
.star-toggle.saving { opacity:.5; pointer-events:none; }
</style>
<script>
function toggleStarPurchaseInvoice(el) {
    if (el.classList.contains('saving')) return;
    var id = el.getAttribute('data-id'); if (!id) return;
    el.classList.add('saving');
    var fd = new FormData(); fd.append('id', id);
    var xhr = new XMLHttpRequest();
    xhr.open('POST', '/purchase_invoices.php?action=toggle_star');
    xhr.onload = function() { el.classList.remove('saving'); try { var res = JSON.parse(xhr.responseText); if (res.error) { alert(res.error); return; } el.classList.toggle('is-on', !!res.starred); } catch (e) { alert('回應錯誤'); } };
    xhr.onerror = function() { el.classList.remove('saving'); alert('網路錯誤'); };
    xhr.send(fd);
}
</script>
