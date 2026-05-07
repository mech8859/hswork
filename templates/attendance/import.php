<div class="d-flex justify-between align-center mb-2">
    <h2>匯入 MOA 出勤資料</h2>
    <div class="d-flex gap-1">
        <a href="/moa_attendance.php" class="btn btn-outline btn-sm">返回明細</a>
        <a href="/moa_attendance.php?action=employees" class="btn btn-outline btn-sm">員工對照</a>
    </div>
</div>

<div class="card mb-2">
    <div class="card-header">上傳 MOA 詳細報表</div>
    <div style="padding:14px 16px">
        <form method="POST" action="/moa_attendance.php?action=import" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <div class="form-group">
                <label>檔案（.xlsx）</label>
                <input type="file" name="file" accept=".xlsx" class="form-control" required>
                <div class="text-muted" style="font-size:.8rem;margin-top:6px">
                    來源：MOA 雲考勤 → 考勤統計 → 匯出報表 → <strong>詳細報表</strong>（不是簡易報表）→ 下載 .xlsx
                </div>
            </div>
            <div class="d-flex gap-1">
                <button type="submit" class="btn btn-primary">匯入</button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">最近匯入紀錄</div>
    <div class="table-responsive">
        <table class="table table-sm">
            <thead><tr>
                <th>匯入時間</th><th>檔名</th><th>日期區間</th>
                <th class="text-right">總筆數</th><th class="text-right">新增</th><th class="text-right">更新</th>
                <th class="text-right">未對應</th><th>匯入者</th>
            </tr></thead>
            <tbody>
                <?php if (empty($logs)): ?>
                <tr><td colspan="8" class="text-center text-muted" style="padding:14px">尚無匯入紀錄</td></tr>
                <?php else: foreach ($logs as $log): ?>
                <tr>
                    <td><?= e($log['imported_at']) ?></td>
                    <td style="word-break:break-all;font-size:.8rem"><?= e($log['file_name']) ?></td>
                    <td><?= e($log['date_from']) ?> ~ <?= e($log['date_to']) ?></td>
                    <td class="text-right"><?= (int)$log['total_rows'] ?></td>
                    <td class="text-right" style="color:#2e7d32"><?= (int)$log['inserted_rows'] ?></td>
                    <td class="text-right" style="color:#1976d2"><?= (int)$log['updated_rows'] ?></td>
                    <td class="text-right" style="color:<?= $log['unmatched_count'] > 0 ? '#c62828' : '#999' ?>"><?= (int)$log['unmatched_count'] ?></td>
                    <td><?= e($log['importer_name'] ?? '-') ?></td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
