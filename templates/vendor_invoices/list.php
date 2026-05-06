<?php
$currentStatus = $statusFilter;
?>
<div class="d-flex justify-between align-center flex-wrap gap-1 mb-2">
    <h2>📥 廠商請款單</h2>
    <button type="button" class="btn btn-primary" onclick="document.getElementById('viUploadModal').style.display='flex'">＋ 上傳請款單</button>
</div>

<!-- 分頁 -->
<div class="filter-pills mb-2">
    <div class="pill-group">
        <a href="/vendor_invoices.php?status=pending"
           class="pill <?= $currentStatus === 'pending' ? 'pill-active' : '' ?>">
            ⬜ 待辨識
            <?php if (!empty($statusCounts['pending'])): ?>
            <span class="badge" style="background:#ef6c00;color:#fff;font-size:.7rem"><?= (int)$statusCounts['pending'] ?></span>
            <?php endif; ?>
        </a>
        <a href="/vendor_invoices.php?status=recognized"
           class="pill <?= $currentStatus === 'recognized' ? 'pill-active' : '' ?>">
            ⚠ 待確認
            <?php if (!empty($statusCounts['recognized'])): ?>
            <span class="badge" style="background:#1565c0;color:#fff;font-size:.7rem"><?= (int)$statusCounts['recognized'] ?></span>
            <?php endif; ?>
        </a>
        <a href="/vendor_invoices.php?status=confirmed"
           class="pill <?= $currentStatus === 'confirmed' ? 'pill-active' : '' ?>">
            ✓ 已確認
            <?php if (!empty($statusCounts['confirmed'])): ?>
            <span class="badge" style="background:#16a34a;color:#fff;font-size:.7rem"><?= (int)$statusCounts['confirmed'] ?></span>
            <?php endif; ?>
        </a>
    </div>
</div>

<div class="card">
    <?php if (empty($result['data'])): ?>
    <p class="text-muted text-center mt-2 mb-2">
        <?php if ($currentStatus === 'pending'): ?>
            目前沒有待辨識的請款單。點右上「＋ 上傳請款單」開始。
        <?php elseif ($currentStatus === 'recognized'): ?>
            目前沒有待確認的請款單。
        <?php else: ?>
            目前沒有已確認的請款單。
        <?php endif; ?>
    </p>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th style="width:40px"></th>
                    <th>檔名</th>
                    <th>上傳時間</th>
                    <th>上傳者</th>
                    <?php if ($currentStatus !== 'pending'): ?>
                    <th>廠商</th>
                    <th>請款日期</th>
                    <th class="text-right">總金額</th>
                    <?php endif; ?>
                    <?php if ($currentStatus === 'confirmed'): ?>
                    <th>確認時間</th>
                    <th>確認者</th>
                    <?php endif; ?>
                    <th style="min-width:200px">動作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($result['data'] as $r):
                    $ext = strtolower(pathinfo($r['file_name'], PATHINFO_EXTENSION));
                    $iconMap = array('pdf' => '📄', 'jpg' => '🖼️', 'jpeg' => '🖼️', 'png' => '🖼️');
                    $icon = $iconMap[$ext] ?? '📎';
                ?>
                <tr>
                    <td><?= $icon ?></td>
                    <td>
                        <a href="/vendor_invoices.php?action=view&id=<?= (int)$r['id'] ?>" style="color:#1565c0;text-decoration:none">
                            <?= e($r['file_name']) ?>
                        </a>
                        <?php if (!empty($r['file_size'])): ?>
                        <small class="text-muted">(<?= number_format($r['file_size'] / 1024, 0) ?> KB)</small>
                        <?php endif; ?>
                    </td>
                    <td><?= e(date('Y-m-d H:i', strtotime($r['uploaded_at']))) ?></td>
                    <td><?= e($r['uploader'] ?: '-') ?></td>
                    <?php if ($currentStatus !== 'pending'): ?>
                    <td><?= e($r['vendor_name'] ?: '-') ?></td>
                    <td><?= e($r['invoice_date'] ?: '-') ?></td>
                    <td class="text-right">$<?= !empty($r['total_amount']) ? number_format((float)$r['total_amount']) : '-' ?></td>
                    <?php endif; ?>
                    <?php if ($currentStatus === 'confirmed'): ?>
                    <td><?= e(!empty($r['confirmed_at']) ? date('Y-m-d H:i', strtotime($r['confirmed_at'])) : '-') ?></td>
                    <td><?= e($r['confirmer'] ?: '-') ?></td>
                    <?php endif; ?>
                    <td>
                        <?php if ($r['status'] === 'pending'): ?>
                        <a href="/vendor_invoices.php?action=view&id=<?= (int)$r['id'] ?>" class="btn btn-primary btn-sm">🤖 辨識</a>
                        <?php elseif ($r['status'] === 'recognized'): ?>
                        <a href="/vendor_invoices.php?action=view&id=<?= (int)$r['id'] ?>" class="btn btn-warning btn-sm">📝 審核</a>
                        <?php else: ?>
                        <a href="/vendor_invoices.php?action=view&id=<?= (int)$r['id'] ?>" class="btn btn-outline btn-sm">查看</a>
                        <?php endif; ?>
                        <?php if ($r['status'] !== 'confirmed' || Auth::hasPermission('all')): ?>
                        <form method="POST" action="/vendor_invoices.php?action=delete" style="display:inline" onsubmit="return confirm('確定刪除「<?= e(addslashes($r['file_name'])) ?>」？檔案會一併移除。')">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                            <button type="submit" class="btn btn-outline btn-sm" style="color:#c62828">刪</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($result['lastPage'] > 1): ?>
    <div class="d-flex gap-1 mt-2 justify-end">
        <?php for ($p = 1; $p <= $result['lastPage']; $p++): ?>
        <a href="/vendor_invoices.php?status=<?= $currentStatus ?>&page=<?= $p ?>"
           class="btn btn-sm <?= $p === $result['page'] ? 'btn-primary' : 'btn-outline' ?>"><?= $p ?></a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

<!-- 上傳 Modal -->
<div id="viUploadModal" style="display:none;position:fixed;top:0;left:0;width:100vw;height:100vh;background:rgba(0,0,0,.5);z-index:1000;align-items:center;justify-content:center">
    <div style="background:#fff;border-radius:8px;padding:20px;max-width:520px;width:90%;max-height:90vh;overflow-y:auto">
        <h3 style="margin-top:0">上傳廠商請款單</h3>
        <form method="POST" action="/vendor_invoices.php?action=upload" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <div class="form-group">
                <label>選擇檔案（可多選）</label>
                <input type="file" name="files[]" class="form-control" accept=".pdf,.jpg,.jpeg,.png" multiple required>
                <small class="text-muted">支援 PDF / JPG / PNG，單檔上限 25MB。可一次選多張。</small>
            </div>
            <div class="d-flex gap-1 justify-end">
                <button type="button" class="btn btn-outline" onclick="document.getElementById('viUploadModal').style.display='none'">取消</button>
                <button type="submit" class="btn btn-primary">上傳到「待辨識」</button>
            </div>
        </form>
    </div>
</div>
