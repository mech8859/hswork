<div class="page-sticky-head">
<div class="d-flex justify-between align-center flex-wrap gap-1 mb-2">
    <h2>銷項發票管理 <span style="font-size:.7em;color:var(--gray-500);font-weight:normal">(共 <?= number_format($result['total']) ?> 筆)</span></h2>
    <?php if ($canManage): ?>
    <a href="/sales_invoices.php?action=create" class="btn btn-primary btn-sm">+ 新增銷項發票</a>
    <?php endif; ?>
</div>

<div class="card" style="padding:12px">
    <form method="GET" action="/sales_invoices.php" class="d-flex flex-wrap gap-1 align-center">
        <?php
        // 未提交搜尋時預設禾順 94081455；有提交過就維持使用者選擇（含空字串=全部）
        $_sellerTaxDefault = isset($_GET['seller_tax_id']) ? $_GET['seller_tax_id'] : '94081455';
        ?>
        <select name="seller_tax_id" class="form-control" style="width:auto;min-width:220px">
            <option value="" <?= $_sellerTaxDefault === '' ? 'selected' : '' ?>>全部賣方</option>
            <option value="94081455" <?= $_sellerTaxDefault === '94081455' ? 'selected' : '' ?>>94081455 禾順監視數位科技有限公司</option>
            <option value="97002927" <?= $_sellerTaxDefault === '97002927' ? 'selected' : '' ?>>97002927 政遠企業有限公司</option>
            <option value="__empty__" <?= $_sellerTaxDefault === '__empty__' ? 'selected' : '' ?> style="color:#c5221f">⚠ 未設定賣方</option>
        </select>
        <select name="period" class="form-control" style="width:auto;min-width:100px">
            <option value="">全部期間</option>
            <?php foreach ($periodOptions as $p): ?>
            <option value="<?= e($p) ?>" <?= (!empty($filters['period']) && $filters['period'] === $p) ? 'selected' : '' ?>><?= e(substr($p, 0, 4) . '/' . substr($p, 4, 2)) ?></option>
            <?php endforeach; ?>
        </select>
        <input type="text" name="customer" class="form-control" style="width:auto;min-width:120px" value="<?= e(!empty($filters['customer']) ? $filters['customer'] : '') ?>" placeholder="客戶名稱/統編">
        <select name="status" class="form-control" style="width:auto;min-width:80px">
            <option value="">全部狀態</option>
            <?php foreach (InvoiceModel::invoiceStatusOptions() as $k => $v): ?>
            <option value="<?= e($k) ?>" <?= (!empty($filters['status']) && $filters['status'] === $k) ? 'selected' : '' ?>><?= e($v) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="invoice_format" class="form-control" style="width:auto;min-width:140px">
            <option value="">銷項發票聯式（全部）</option>
            <option value="__empty__" <?= (!empty($filters['invoice_format']) && $filters['invoice_format'] === '__empty__') ? 'selected' : '' ?> style="color:#c5221f">⚠ 未設定聯式</option>
            <?php
            $_sfOpts = array(
                '31' => '31：銷項三聯式、電子計算機統一發票',
                '32' => '32：銷項二聯式、二聯式收銀機統一發票',
                '33' => '33：三聯式銷貨退回或折讓證明單',
                '34' => '34：二聯式銷貨退回或折讓證明單',
                '35' => '35：銷項三聯式收銀機統一發票、電子發票',
            );
            foreach ($_sfOpts as $_k => $_v):
            ?>
            <option value="<?= e($_k) ?>" <?= (!empty($filters['invoice_format']) && $filters['invoice_format'] === $_k) ? 'selected' : '' ?>><?= e($_v) ?></option>
            <?php endforeach; ?>
        </select>
        <input type="text" name="keyword" class="form-control" style="width:auto;min-width:180px" value="<?= e(!empty($filters['keyword']) ? $filters['keyword'] : '') ?>" placeholder="發票號碼/備註/統編/日期/申報年月/金額（$1500 精準比對）">
        <input type="text" name="invoice_no_from" class="form-control" style="width:auto;min-width:120px" value="<?= e(!empty($filters['invoice_no_from']) ? $filters['invoice_no_from'] : '') ?>" placeholder="號碼起">
        <span style="color:#888">~</span>
        <input type="text" name="invoice_no_to" class="form-control" style="width:auto;min-width:120px" value="<?= e(!empty($filters['invoice_no_to']) ? $filters['invoice_no_to'] : '') ?>" placeholder="號碼迄">
        <?php $_sortVal = !empty($filters['sort']) && $filters['sort'] === 'asc' ? 'asc' : 'desc'; ?>
        <select name="sort" class="form-control" style="width:auto;min-width:90px" title="排序">
            <option value="desc" <?= $_sortVal === 'desc' ? 'selected' : '' ?>>新 → 舊</option>
            <option value="asc"  <?= $_sortVal === 'asc'  ? 'selected' : '' ?>>舊 → 新</option>
        </select>
        <button type="submit" class="btn btn-primary btn-sm">搜尋</button>
        <a href="/sales_invoices.php" class="btn btn-outline btn-sm">清除</a>
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
        <p class="text-muted text-center mt-2">目前無銷項發票資料</p>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table">
            <thead class="sticky-thead">
                <tr>
                    <th style="width:32px"></th>
                    <th>發票號碼</th>
                    <th>日期</th>
                    <th>聯式</th>
                    <th>客戶名稱</th>
                    <th>統編</th>
                    <th class="text-right">未稅金額</th>
                    <th class="text-right">稅額</th>
                    <th class="text-right">含稅金額</th>
                    <th>類型</th>
                    <th>狀態</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $_sfLabels = array(
                    '31' => '銷項三聯式、電子計算機統一發票',
                    '32' => '銷項二聯式、二聯式收銀機統一發票',
                    '33' => '三聯式銷貨退回或折讓證明單',
                    '34' => '二聯式銷貨退回或折讓證明單',
                    '35' => '銷項三聯式收銀機統一發票、電子發票',
                );
                ?>
                <?php foreach ($records as $r): $isStar = !empty($r['is_starred']); ?>
                <?php
                    $_fmt = !empty($r['invoice_format']) ? $r['invoice_format'] : '';
                    $_fmtTitle = isset($_sfLabels[$_fmt]) ? $_fmt . '：' . $_sfLabels[$_fmt] : $_fmt;
                    $_isAllowance = ($_fmt === '33' || $_fmt === '34');
                ?>
                <tr<?= $r['status'] === 'voided' ? ' style="opacity:.5;text-decoration:line-through"' : '' ?>>
                    <td class="text-center"><span class="star-toggle <?= $isStar ? 'is-on' : '' ?>" data-id="<?= (int)$r['id'] ?>" onclick="toggleStarSalesInvoice(this)" title="標記">&#9733;</span></td>
                    <td><a href="/sales_invoices.php?action=edit&id=<?= $r['id'] ?>"><?= e(!empty($r['invoice_number']) ? $r['invoice_number'] : '-') ?></a></td>
                    <td><?= e(!empty($r['invoice_date']) ? $r['invoice_date'] : '') ?></td>
                    <td>
                        <?php if ($_fmt): ?>
                        <span class="badge" style="background:<?= $_isAllowance ? '#fce4ec;color:#c2185b' : '#e8f5e9;color:#2e7d32' ?>" title="<?= e($_fmtTitle) ?>"><?= e($_fmt) ?></span>
                        <?php else: ?>
                        <span style="color:#bbb">-</span>
                        <?php endif; ?>
                    </td>
                    <td><?= e(!empty($r['customer_name']) ? $r['customer_name'] : '-') ?></td>
                    <td><?= e(!empty($r['customer_tax_id']) ? $r['customer_tax_id'] : '-') ?></td>
                    <td class="text-right">$<?= number_format(!empty($r['amount_untaxed']) ? $r['amount_untaxed'] : 0) ?></td>
                    <td class="text-right">$<?= number_format(!empty($r['tax_amount']) ? $r['tax_amount'] : 0) ?></td>
                    <td class="text-right">$<?= number_format(!empty($r['total_amount']) ? $r['total_amount'] : 0) ?></td>
                    <td><?= e(!empty($r['invoice_type']) ? $r['invoice_type'] : '-') ?></td>
                    <td>
                        <span class="badge badge-<?= $r['status'] === 'voided' ? 'danger' : ($r['status'] === 'confirmed' ? 'success' : 'warning') ?>">
                            <?php $sopts = InvoiceModel::invoiceStatusOptions(); echo e(isset($sopts[$r['status']]) ? $sopts[$r['status']] : $r['status']); ?>
                        </span>
                    </td>
                    <td>
                        <a href="/sales_invoices.php?action=edit&id=<?= $r['id'] ?>" class="btn btn-outline btn-sm">編輯</a>
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
function toggleStarSalesInvoice(el) {
    if (el.classList.contains('saving')) return;
    var id = el.getAttribute('data-id'); if (!id) return;
    el.classList.add('saving');
    var fd = new FormData(); fd.append('id', id);
    var xhr = new XMLHttpRequest();
    xhr.open('POST', '/sales_invoices.php?action=toggle_star');
    xhr.onload = function() { el.classList.remove('saving'); try { var res = JSON.parse(xhr.responseText); if (res.error) { alert(res.error); return; } el.classList.toggle('is-on', !!res.starred); } catch (e) { alert('回應錯誤'); } };
    xhr.onerror = function() { el.classList.remove('saving'); alert('網路錯誤'); };
    xhr.send(fd);
}
</script>
