<div class="d-flex justify-between align-center flex-wrap gap-1 mb-2">
    <h2>選單管理</h2>
</div>

<!-- 主分頁 -->
<div class="filter-pills mb-1">
    <div class="pill-group">
        <a href="/dropdown_options.php?tab=dropdown" class="pill">表單選項設定</a>
        <a href="/dropdown_options.php?tab=roles" class="pill">人員角色</a>
        <a href="/dropdown_options.php?tab=numbering" class="pill pill-active">自動編號設定</a>
        <a href="/dropdown_options.php?tab=quotation" class="pill">報價單設定</a>
    </div>
</div>

<div class="card">
    <div class="card-header">自動編號格式設定</div>
    <p class="text-muted mb-1" style="font-size:.85rem">設定各模組新增資料時自動產生的編號格式，修改後立即生效</p>

    <form method="POST" action="/dropdown_options.php?action=save_numbering">
        <?= csrf_field() ?>
        <div class="table-responsive">
            <table class="table" style="font-size:.9rem">
                <thead>
                    <tr>
                        <th>模組</th>
                        <th>前綴</th>
                        <th>日期格式</th>
                        <th>分隔符</th>
                        <th>序號位數</th>
                        <th>預覽</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sequences as $i => $seq): ?>
                    <tr>
                        <td>
                            <strong><?= e($seq['module_label']) ?></strong>
                            <input type="hidden" name="seq_id[<?= $i ?>]" value="<?= $seq['id'] ?>">
                        </td>
                        <td>
                            <input type="text" name="seq_prefix[<?= $i ?>]" value="<?= e($seq['prefix']) ?>"
                                   class="form-control seq-input" data-row="<?= $i ?>" data-field="prefix"
                                   style="width:80px" placeholder="如 AR" maxlength="20">
                        </td>
                        <td>
                            <select name="seq_date_format[<?= $i ?>]" class="form-control seq-input" data-row="<?= $i ?>" data-field="date_format" style="width:140px">
                                <option value="" <?= $seq['date_format'] === '' ? 'selected' : '' ?>>無（純序號）</option>
                                <option value="Y" <?= $seq['date_format'] === 'Y' ? 'selected' : '' ?>>年 (<?= date('Y') ?>)</option>
                                <option value="Ym" <?= $seq['date_format'] === 'Ym' ? 'selected' : '' ?>>年月 (<?= date('Ym') ?>)</option>
                                <option value="Ymd" <?= $seq['date_format'] === 'Ymd' ? 'selected' : '' ?>>年月日 (<?= date('Ymd') ?>)</option>
                            </select>
                        </td>
                        <td>
                            <select name="seq_separator[<?= $i ?>]" class="form-control seq-input" data-row="<?= $i ?>" data-field="separator" style="width:70px">
                                <option value="-" <?= $seq['separator'] === '-' ? 'selected' : '' ?>>-</option>
                                <option value="" <?= $seq['separator'] === '' ? 'selected' : '' ?>>無</option>
                                <option value="/" <?= $seq['separator'] === '/' ? 'selected' : '' ?>>/</option>
                                <option value="_" <?= $seq['separator'] === '_' ? 'selected' : '' ?>>_</option>
                            </select>
                        </td>
                        <td>
                            <select name="seq_digits[<?= $i ?>]" class="form-control seq-input" data-row="<?= $i ?>" data-field="digits" style="width:90px">
                                <option value="0" <?= (int)$seq['seq_digits'] === 0 ? 'selected' : '' ?>>不使用</option>
                                <?php for ($d = 2; $d <= 6; $d++): ?>
                                <option value="<?= $d ?>" <?= (int)$seq['seq_digits'] === $d ? 'selected' : '' ?>><?= $d ?> 位</option>
                                <?php endfor; ?>
                            </select>
                        </td>
                        <td>
                            <code id="preview-<?= $i ?>" style="font-size:.85rem;color:var(--primary);font-weight:600">
                                <?= e(preview_doc_number($seq['prefix'], $seq['date_format'], $seq['separator'], $seq['seq_digits'])) ?>
                            </code>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="mt-1">
            <button type="submit" class="btn btn-primary">儲存設定</button>
        </div>
    </form>
</div>

<div class="card mt-2">
    <div class="card-header">格式說明</div>
    <div style="font-size:.85rem;color:var(--gray-600)">
        <p><strong>前綴</strong>：編號開頭的固定文字，如 AR、Q、PO 等。留空則無前綴。</p>
        <p><strong>日期格式</strong>：</p>
        <ul style="margin:4px 0 8px 20px">
            <li>年 → <?= date('Y') ?></li>
            <li>年月 → <?= date('Ym') ?>（每月序號重新計數）</li>
            <li>年月日 → <?= date('Ymd') ?>（每日序號重新計數）</li>
            <li>無 → 序號持續累加不重設</li>
        </ul>
        <p><strong>序號位數</strong>：序號的最小位數，不足補零。如 3 位 → 001, 002...</p>
        <p><strong>範例格式</strong>：</p>
        <ul style="margin:4px 0 0 20px">
            <li>前綴 "AR" + 年 + 分隔"-" + 4位 → <code>AR-<?= date('Y') ?>-0001</code></li>
            <li>無前綴 + 年月 + 分隔"-" + 3位 → <code><?= date('Ym') ?>-001</code></li>
            <li>前綴 "Q" + 年月日 + 分隔"-" + 3位 → <code>Q-<?= date('Ymd') ?>-001</code></li>
            <li>無前綴 + 無日期 + 4位 → <code>0001</code></li>
        </ul>
    </div>
</div>

<style>
.filter-pills { display: flex; flex-direction: column; gap: 8px; }
.pill-group { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
.pill { display: inline-block; padding: 6px 16px; border-radius: 16px; font-size: .85rem; background: var(--gray-100); color: var(--gray-700); text-decoration: none; transition: all .15s; cursor: pointer; }
.pill:hover { background: var(--gray-200); }
.pill-active { background: var(--primary); color: #fff; }
</style>

<script>
// 即時預覽
document.querySelectorAll('.seq-input').forEach(function(el) {
    el.addEventListener('change', updatePreview);
    el.addEventListener('input', updatePreview);
});

function updatePreview(e) {
    var row = e.target.getAttribute('data-row');
    var prefix = document.querySelector('[name="seq_prefix[' + row + ']"]').value;
    var dateFormat = document.querySelector('[name="seq_date_format[' + row + ']"]').value;
    var sep = document.querySelector('[name="seq_separator[' + row + ']"]').value;
    var digits = parseInt(document.querySelector('[name="seq_digits[' + row + ']"]').value);

    var dateMap = { '': '', 'Y': '<?= date('Y') ?>', 'Ym': '<?= date('Ym') ?>', 'Ymd': '<?= date('Ymd') ?>' };
    var datePart = dateMap[dateFormat] || '';

    var parts = [];
    if (prefix) parts.push(prefix);
    if (datePart) parts.push(datePart);
    if (digits > 0) {
        var seqPart = '';
        for (var i = 0; i < digits; i++) seqPart += '0';
        seqPart = seqPart.substring(0, seqPart.length - 1) + '1';
        parts.push(seqPart);
    }

    document.getElementById('preview-' + row).textContent = parts.join(sep);
}
</script>
