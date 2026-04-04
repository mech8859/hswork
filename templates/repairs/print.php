<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <title>維修單 <?= e($repair['repair_number']) ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: "Microsoft JhengHei", "PingFang TC", sans-serif; font-size: 12px; color: #333; }
        .print-page { width: 210mm; padding: 10mm 15mm; page-break-after: always; }
        .print-page:last-child { page-break-after: auto; }
        .header { text-align: center; margin-bottom: 8px; border-bottom: 2px solid #333; padding-bottom: 6px; }
        .header h1 { font-size: 18px; margin-bottom: 2px; }
        .header .sub { font-size: 11px; color: #666; }
        .info-row { display: flex; justify-content: space-between; margin-bottom: 4px; font-size: 12px; }
        .info-row .label { color: #666; }
        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 4px 16px; margin-bottom: 8px; padding: 6px 0; border-bottom: 1px solid #ddd; }
        .info-grid .item { display: flex; gap: 8px; }
        .info-grid .label { color: #666; white-space: nowrap; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        th, td { border: 1px solid #999; padding: 4px 6px; text-align: left; font-size: 11px; }
        th { background: #f0f0f0; font-weight: 600; }
        .text-right { text-align: right; }
        .total-row { font-weight: 600; background: #f5f5f5; }
        .note-box { border: 1px solid #ccc; padding: 6px; min-height: 40px; margin-bottom: 8px; font-size: 11px; }
        .note-box .label { font-weight: 600; margin-bottom: 4px; }
        .sign-area { display: flex; justify-content: space-between; margin-top: 16px; padding-top: 8px; }
        .sign-box { width: 30%; text-align: center; }
        .sign-box .line { border-bottom: 1px solid #333; height: 30px; margin-bottom: 4px; }
        .sign-box .label { font-size: 11px; color: #666; }
        .copy-label { text-align: right; font-size: 10px; color: #999; margin-bottom: 4px; }
        @media print {
            body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .print-page { padding: 8mm 12mm; }
        }
        @page { size: A4; margin: 0; }
    </style>
</head>
<body>
<?php
$copies = ['公司存根聯', '客戶收執聯'];
foreach ($copies as $copyLabel):
?>
<div class="print-page">
    <div class="copy-label"><?= $copyLabel ?></div>
    <div class="header">
        <h1>維修服務單</h1>
        <div class="sub"><?= e($repair['branch_name'] ?? '') ?></div>
    </div>

    <div class="info-row">
        <span><span class="label">單號：</span><?= e($repair['repair_number']) ?></span>
        <span><span class="label">日期：</span><?= e($repair['repair_date']) ?></span>
    </div>

    <div class="info-grid">
        <div class="item"><span class="label">客戶：</span><span><?= e($repair['customer_name']) ?></span></div>
        <div class="item"><span class="label">電話：</span><span><?= e($repair['customer_phone'] ?: '-') ?></span></div>
        <div class="item" style="grid-column: span 2"><span class="label">地址：</span><span><?= e($repair['customer_address'] ?: '-') ?></span></div>
        <div class="item"><span class="label">工程師：</span><span><?= e($repair['engineer_name'] ?: '-') ?></span></div>
    </div>

    <table>
        <thead><tr><th>項目說明</th><th style="width:60px" class="text-right">數量</th><th style="width:80px" class="text-right">單價</th><th style="width:80px" class="text-right">金額</th></tr></thead>
        <tbody>
            <?php if (!empty($repair['items'])): ?>
                <?php foreach ($repair['items'] as $item): ?>
                <tr>
                    <td><?= e($item['description']) ?></td>
                    <td class="text-right"><?= (int)$item['quantity'] ?></td>
                    <td class="text-right">$<?= number_format($item['unit_price']) ?></td>
                    <td class="text-right">$<?= number_format($item['amount']) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php for ($i = count($repair['items']); $i < 6; $i++): ?>
                <tr><td>&nbsp;</td><td></td><td></td><td></td></tr>
                <?php endfor; ?>
            <?php else: ?>
                <?php for ($i = 0; $i < 6; $i++): ?>
                <tr><td>&nbsp;</td><td></td><td></td><td></td></tr>
                <?php endfor; ?>
            <?php endif; ?>
        </tbody>
        <tfoot>
            <tr class="total-row">
                <td colspan="3" class="text-right">合計</td>
                <td class="text-right">$<?= number_format($repair['total_amount']) ?></td>
            </tr>
        </tfoot>
    </table>

    <div class="note-box">
        <div class="label">備註：</div>
        <?= nl2br(e($repair['note'] ?: '')) ?>
    </div>

    <div class="sign-area">
        <div class="sign-box"><div class="line"></div><div class="label">客戶簽名</div></div>
        <div class="sign-box"><div class="line"></div><div class="label">工程師簽名</div></div>
        <div class="sign-box"><div class="line"></div><div class="label">主管簽核</div></div>
    </div>
</div>
<?php endforeach; ?>

<script>window.onload = function() { window.print(); };</script>
</body>
</html>
