<div class="d-flex justify-between align-center flex-wrap gap-1 mb-2">
    <h2>批次匯入銀行明細</h2>
    <a href="/bank_transactions.php" class="btn btn-outline btn-sm">返回列表</a>
</div>

<div class="card">
    <div class="card-header">上傳 Excel 檔案</div>
    <p class="text-muted mb-1" style="font-size:.85rem">支援 .xlsx / .csv 格式，系統會自動辨識欄位並匯入</p>

    <form method="POST" action="/bank_transactions.php?action=import_preview" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <div class="form-group">
            <label>選擇檔案 *</label>
            <input type="file" name="excel_file" class="form-control" accept=".xlsx,.xls,.csv" required>
        </div>
        <p class="text-muted" style="font-size:.8rem">
            支援的欄位：銀行帳戶、交易日期、摘要、支出金額、存入金額、餘額、備註、轉出入帳號、對方帳號、註記、對象說明等
        </p>
        <button type="submit" class="btn btn-primary">上傳並預覽</button>
    </form>
</div>

<div class="card mt-2">
    <div class="card-header">匯入說明</div>
    <div style="font-size:.9rem;line-height:1.8">
        <p><strong>步驟：</strong></p>
        <ol>
            <li>準備好銀行交易明細的 Excel 檔案（從網銀下載或手動整理）</li>
            <li>上傳檔案後系統自動辨識欄位</li>
            <li>預覽確認資料正確</li>
            <li>確認匯入</li>
        </ol>
        <p><strong>注意事項：</strong></p>
        <ul>
            <li>第一列必須是欄位標題</li>
            <li>「交易日期」為必填欄位</li>
            <li>重複匯入不會自動排除，請勿重複上傳同一份檔案</li>
        </ul>
    </div>
</div>
