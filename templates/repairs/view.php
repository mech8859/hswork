<div class="d-flex justify-between align-center flex-wrap gap-1 mb-2">
    <div>
        <h2><?= e($repair['repair_number']) ?></h2>
        <span class="badge <?= $repair['status'] === 'completed' ? 'badge-success' : ($repair['status'] === 'invoiced' ? 'badge-primary' : '') ?>"><?= $statusLabels[$repair['status']] ?? $repair['status'] ?></span>
        <span class="text-muted"><?= e($repair['branch_name'] ?? '') ?></span>
    </div>
    <div class="d-flex gap-1 flex-wrap">
        <?php if (Auth::hasPermission('repairs.manage') || Auth::hasPermission('repairs.own')): ?>
        <a href="/repairs.php?action=edit&id=<?= $repair['id'] ?>" class="btn btn-primary btn-sm">編輯</a>
        <?php endif; ?>
        <a href="/repairs.php?action=print&id=<?= $repair['id'] ?>" class="btn btn-outline btn-sm" target="_blank">列印</a>
        <?php if (Auth::hasPermission('repairs.manage')): ?>
            <?php if ($repair['status'] === 'draft'): ?>
            <a href="/repairs.php?action=status&id=<?= $repair['id'] ?>&status=completed&csrf_token=<?= e(Session::getCsrfToken()) ?>"
               class="btn btn-success btn-sm" onclick="return confirm('確定標記為已完工?')">標記完工</a>
            <?php elseif ($repair['status'] === 'completed'): ?>
            <a href="/repairs.php?action=status&id=<?= $repair['id'] ?>&status=invoiced&csrf_token=<?= e(Session::getCsrfToken()) ?>"
               class="btn btn-primary btn-sm" onclick="return confirm('確定標記為已請款?')">標記已請款</a>
            <?php endif; ?>
        <?php endif; ?>
        <?= back_button('/repairs.php') ?>
    </div>
</div>

<!-- 客戶資料 -->
<div class="card">
    <div class="card-header">客戶資料</div>
    <div class="detail-grid">
        <div class="detail-item"><span class="detail-label">客戶名稱</span><span class="detail-value"><?= e($repair['customer_name']) ?></span></div>
        <div class="detail-item"><span class="detail-label">電話</span><span class="detail-value"><?= e($repair['customer_phone'] ?: '-') ?></span></div>
        <div class="detail-item"><span class="detail-label">地址</span><span class="detail-value"><?= e($repair['customer_address'] ?: '-') ?></span></div>
        <div class="detail-item"><span class="detail-label">維修日期</span><span class="detail-value"><?= e($repair['repair_date']) ?></span></div>
        <div class="detail-item"><span class="detail-label">工程師</span><span class="detail-value"><?= e($repair['engineer_name'] ?: '未指派') ?></span></div>
        <div class="detail-item"><span class="detail-label">建立者</span><span class="detail-value"><?= e($repair['creator_name'] ?? '-') ?></span></div>
        <?php if (!empty($repair['customer_address'])): ?>
        <div class="detail-item" style="grid-column: span 2">
            <span class="detail-label">地圖</span>
            <iframe src="https://maps.google.com/maps?q=<?= urlencode($repair['customer_address']) ?>&output=embed&hl=zh-TW" style="width:100%;max-width:480px;height:200px;border:1px solid var(--gray-200);border-radius:6px" allowfullscreen loading="lazy"></iframe>
        </div>
        <?php endif; ?>
    </div>
    <?php if ($repair['note']): ?>
    <div class="mt-1">
        <span class="detail-label">備註</span>
        <p><?= nl2br(e($repair['note'])) ?></p>
    </div>
    <?php endif; ?>
</div>

<!-- 維修項目 -->
<div class="card">
    <div class="card-header">維修項目</div>
    <div class="table-responsive">
        <table class="table">
            <thead><tr><th>項目說明</th><th style="width:80px" class="text-right">數量</th><th style="width:100px" class="text-right">單價</th><th style="width:100px" class="text-right">金額</th></tr></thead>
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
                <?php else: ?>
                    <tr><td colspan="4" class="text-center text-muted">無維修項目</td></tr>
                <?php endif; ?>
            </tbody>
            <tfoot>
                <tr style="font-weight:600; background:var(--gray-100);">
                    <td colspan="3" class="text-right">合計</td>
                    <td class="text-right">$<?= number_format($repair['total_amount']) ?></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<!-- 維修回報 -->
<div class="card">
    <div class="card-header">維修回報</div>

    <!-- 新增回報表單 -->
    <form id="reportForm" enctype="multipart/form-data" style="margin-bottom:16px">
        <input type="hidden" name="repair_id" value="<?= $repair['id'] ?>">
        <input type="hidden" name="csrf_token" value="<?= e(Session::getCsrfToken()) ?>">
        <div class="form-group">
            <textarea name="report_text" id="reportText" class="form-control" rows="3" placeholder="輸入回報內容..."></textarea>
        </div>
        <div class="form-group">
            <label style="font-size:.85rem">附加照片</label>
            <input type="file" name="photos[]" id="reportPhotos" class="form-control" multiple accept="image/*">
        </div>
        <button type="button" class="btn btn-primary btn-sm" onclick="submitReport()">送出回報</button>
    </form>

    <!-- 回報列表 -->
    <div id="reportList">
    <?php if (!empty($repair['reports'])): ?>
        <?php foreach ($repair['reports'] as $rpt): ?>
        <div class="report-item">
            <div class="report-header">
                <strong><?= e($rpt['reporter_name']) ?></strong>
                <span class="text-muted" style="font-size:.8rem"><?= format_datetime($rpt['created_at']) ?></span>
            </div>
            <?php if ($rpt['report_text']): ?>
            <div class="report-text"><?= nl2br(e($rpt['report_text'])) ?></div>
            <?php endif; ?>
            <?php if (!empty($rpt['photos'])): ?>
            <div class="report-photos">
                <?php foreach ($rpt['photos'] as $photo): ?>
                <div class="report-photo" id="rphoto-<?= $photo['id'] ?>">
                    <a href="<?= e($photo['file_path']) ?>" target="_blank">
                        <img src="<?= e($photo['file_path']) ?>" alt="" loading="lazy">
                    </a>
                    <button type="button" class="photo-del" onclick="deleteRepairPhoto(<?= $photo['id'] ?>)" title="刪除">&times;</button>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    <?php else: ?>
        <p class="text-muted text-center" id="noReports">尚無回報記錄</p>
    <?php endif; ?>
    </div>
</div>

<style>
.detail-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 8px; }
.detail-item { display: flex; flex-direction: column; }
.detail-label { font-size: .8rem; color: var(--gray-500); }
.report-item {
    border-bottom: 1px solid var(--gray-200); padding: 12px 0;
}
.report-item:last-child { border-bottom: none; }
.report-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px; }
.report-text { font-size: .9rem; line-height: 1.5; color: var(--gray-700); }
.report-photos { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 8px; }
.report-photo { position: relative; width: 100px; height: 100px; }
.report-photo img { width: 100%; height: 100%; object-fit: cover; border-radius: var(--radius); border: 1px solid var(--gray-200); }
.report-photo .photo-del {
    position: absolute; top: -6px; right: -6px;
    width: 20px; height: 20px; border-radius: 50%;
    background: var(--danger); color: #fff; border: none;
    font-size: 14px; line-height: 1; cursor: pointer;
    display: flex; align-items: center; justify-content: center;
}
@media (max-width: 767px) { .detail-grid { grid-template-columns: 1fr; } }
</style>

<script>
function submitReport() {
    var text = document.getElementById('reportText').value.trim();
    var fileInput = document.getElementById('reportPhotos');
    if (!text && fileInput.files.length === 0) {
        alert('請輸入回報內容或選擇照片');
        return;
    }
    var fd = new FormData(document.getElementById('reportForm'));
    var btn = event.target;
    btn.disabled = true;
    btn.textContent = '壓縮上傳中...';
    compressFormData(fd).then(function(cfd) {
        var xhr = new XMLHttpRequest();
        xhr.open('POST', '/repairs.php?action=add_report');
        xhr.onload = function() {
            btn.disabled = false;
            btn.textContent = '送出回報';
            if (xhr.status === 200) {
                try {
                    var res = JSON.parse(xhr.responseText);
                    if (res.success) {
                        var noRpt = document.getElementById('noReports');
                        if (noRpt) noRpt.remove();
                        var photosHtml = '';
                        if (res.photos && res.photos.length) {
                            photosHtml = '<div class="report-photos">';
                            for (var i = 0; i < res.photos.length; i++) {
                                photosHtml += '<div class="report-photo" id="rphoto-' + res.photos[i].id + '">' +
                                    '<a href="' + res.photos[i].file_path + '" target="_blank"><img src="' + res.photos[i].file_path + '"></a>' +
                                    '<button type="button" class="photo-del" onclick="deleteRepairPhoto(' + res.photos[i].id + ')">&times;</button></div>';
                            }
                            photosHtml += '</div>';
                        }
                        var html = '<div class="report-item">' +
                            '<div class="report-header"><strong>' + escHtml(res.reporter_name) + '</strong>' +
                            '<span class="text-muted" style="font-size:.8rem">' + res.created_at + '</span></div>' +
                            (text ? '<div class="report-text">' + escHtml(text).replace(/\n/g, '<br>') + '</div>' : '') +
                            photosHtml + '</div>';
                        var list = document.getElementById('reportList');
                        list.insertAdjacentHTML('afterbegin', html);
                        document.getElementById('reportText').value = '';
                        fileInput.value = '';
                    } else {
                        alert(res.error || '送出失敗');
                    }
                } catch(e) { alert('送出失敗'); }
            }
        };
        xhr.onerror = function() { btn.disabled = false; btn.textContent = '送出回報'; alert('網路錯誤'); };
        xhr.send(cfd);
    });
}
function deleteRepairPhoto(id) {
    if (!confirm('確定刪除此照片?')) return;
    var xhr = new XMLHttpRequest();
    xhr.open('POST', '/repairs.php?action=delete_photo');
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onload = function() {
        if (xhr.status === 200) {
            try {
                var res = JSON.parse(xhr.responseText);
                if (res.success) {
                    var el = document.getElementById('rphoto-' + id);
                    if (el) el.remove();
                }
            } catch(e) {}
        }
    };
    xhr.send('photo_id=' + id + '&csrf_token=<?= e(Session::getCsrfToken()) ?>');
}
function escHtml(s) {
    var d = document.createElement('div');
    d.appendChild(document.createTextNode(s));
    return d.innerHTML;
}
</script>
