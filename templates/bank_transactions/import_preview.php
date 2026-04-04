<div class="d-flex justify-between align-center flex-wrap gap-1 mb-2">
    <h2>匯入預覽</h2>
    <a href="/bank_transactions.php?action=import" class="btn btn-outline btn-sm">重新上傳</a>
</div>

<div class="card mb-2">
    <div class="card-header">欄位對應結果</div>
    <p style="font-size:.9rem">共偵測到 <strong><?= $totalRows ?></strong> 筆資料，以下顯示前 <?= count($previewRows) ?> 筆預覽</p>

    <?php
    $fieldLabels = array(
        'bank_account'     => '銀行帳戶',
        'transaction_date' => '交易日期',
        'summary'          => '摘要',
        'debit_amount'     => '支出金額',
        'credit_amount'    => '存入金額',
        'balance'          => '餘額',
        'note'             => '備註',
        'remark'           => '註記',
        'description'      => '對象說明',
        'transfer_account' => '轉出入帳號',
        'counter_account'  => '對方帳號',
    );
    $matched = 0;
    $unmatched = 0;
    foreach ($colMap as $field => $idx) {
        if ($idx !== null) $matched++;
        else $unmatched++;
    }
    ?>
    <div class="d-flex gap-1 mb-1 flex-wrap">
        <span class="badge" style="background:#e8f5e9;color:#2e7d32">已對應 <?= $matched ?> 個欄位</span>
        <?php if ($unmatched > 0): ?>
        <span class="badge" style="background:#fff3e0;color:#e65100">未對應 <?= $unmatched ?> 個欄位</span>
        <?php endif; ?>
    </div>

    <div class="table-responsive" style="font-size:.8rem">
        <table class="table">
            <thead><tr>
                <th>#</th>
                <?php foreach ($fieldLabels as $field => $label): ?>
                <?php if ($colMap[$field] !== null): ?>
                <th><?= $label ?></th>
                <?php endif; ?>
                <?php endforeach; ?>
            </tr></thead>
            <tbody>
                <?php foreach ($previewRows as $i => $row): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <?php foreach ($fieldLabels as $field => $label): ?>
                    <?php if ($colMap[$field] !== null): ?>
                    <td><?php
                        $val = isset($row[$colMap[$field]]) ? trim($row[$colMap[$field]]) : '';
                        if ($field === 'debit_amount' || $field === 'credit_amount' || $field === 'balance') {
                            $num = (int)str_replace(',', '', $val);
                            echo $num > 0 ? '$' . number_format($num) : '-';
                        } else {
                            echo e(mb_strimwidth($val, 0, 20, '…'));
                        }
                    ?></td>
                    <?php endif; ?>
                    <?php endforeach; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <form method="POST" action="/bank_transactions.php?action=import_execute">
        <?= csrf_field() ?>
        <div class="d-flex justify-between align-center">
            <div>
                <p style="font-size:1.1rem;font-weight:600">確認匯入 <?= $totalRows ?> 筆銀行明細？</p>
                <p class="text-muted" style="font-size:.85rem">匯入後可在列表中查看所有資料</p>
            </div>
            <div class="d-flex gap-1">
                <a href="/bank_transactions.php?action=import" class="btn btn-outline">取消</a>
                <button type="submit" class="btn btn-primary">確認匯入</button>
            </div>
        </div>
    </form>
</div>
