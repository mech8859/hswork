<?php
$isEdit = !empty($worker);
$wType = $worker['worker_type'] ?? ($_GET['type'] ?? 'dispatch');
$files = $isEdit ? ($worker['files'] ?? array()) : array();
$filesByType = array();
foreach ($files as $f) { $filesByType[$f['file_type']][] = $f; }
?>

<div class="d-flex justify-between align-center mb-2">
    <h2><?= $isEdit ? '編輯' : '新增' ?><?= $wType === 'outsource' ? '外包人員' : '點工人員' ?></h2>
    <a href="/dispatch_workers.php?type=<?= e($wType) ?>" class="btn btn-outline">返回列表</a>
</div>

<form method="POST" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <input type="hidden" name="worker_type" value="<?= e($wType) ?>">

    <div class="card">
        <div class="card-header">基本資料</div>
        <div class="form-row">
            <div class="form-group">
                <label>姓名 *</label>
                <input type="text" name="name" class="form-control" required value="<?= e($worker['name'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>身分證字號</label>
                <input type="text" name="id_number" class="form-control" value="<?= e($worker['id_number'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>電話</label>
                <input type="text" name="phone" class="form-control" value="<?= e($worker['phone'] ?? '') ?>">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>出生年月日</label>
                <input type="date" max="2099-12-31" name="birth_date" class="form-control" value="<?= e($worker['birth_date'] ?? '') ?>" onchange="calcAge(this)">
            </div>
            <div class="form-group">
                <label>年齡</label>
                <input type="text" class="form-control" id="ageDisplay" readonly
                       value="<?php if (!empty($worker['birth_date'])) { $bd = new DateTime($worker['birth_date']); echo $bd->diff(new DateTime())->y . ' 歲'; } ?>">
            </div>
            <div class="form-group">
                <label>點工狀態</label>
                <select name="status" class="form-control">
                    <option value="primary" <?= ($worker['status'] ?? '') === 'primary' ? 'selected' : '' ?>>優先</option>
                    <option value="backup" <?= ($worker['status'] ?? '') === 'backup' ? 'selected' : '' ?>>備用</option>
                </select>
            </div>
        </div>
        <div class="form-group">
            <label>聯絡地址</label>
            <input type="text" name="address" class="form-control" value="<?= e($worker['address'] ?? '') ?>">
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>專長</label>
                <input type="text" name="specialty" class="form-control" value="<?= e($worker['specialty'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>日薪</label>
                <input type="number" name="daily_rate" class="form-control" value="<?= e($worker['daily_rate'] ?? '') ?>">
            </div>
        </div>
        <?php if ($wType === 'outsource'): ?>
        <div class="form-group">
            <label>所屬廠商</label>
            <select name="vendor_id" class="form-control">
                <option value="">不指定</option>
                <?php foreach ($vendors as $v): ?>
                <option value="<?= $v['id'] ?>" <?= ($worker['vendor_id'] ?? '') == $v['id'] ? 'selected' : '' ?>><?= e($v['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
    </div>

    <div class="card">
        <div class="card-header">緊急聯絡人</div>
        <div class="form-row">
            <div class="form-group">
                <label>緊急聯絡人</label>
                <input type="text" name="emergency_contact" class="form-control" value="<?= e($worker['emergency_contact'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>緊急聯絡人電話</label>
                <input type="text" name="emergency_phone" class="form-control" value="<?= e($worker['emergency_phone'] ?? '') ?>">
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">附件管理</div>
        <?php
        $fileTypes = array(
            'id_front' => '身分證正面',
            'id_back' => '身分證反面',
            'photo' => '大頭貼',
            'license' => '證照',
            'other' => '其他',
        );
        ?>
        <div class="attach-grid">
            <?php foreach ($fileTypes as $ftKey => $ftLabel): ?>
            <div class="atc-box">
                <div class="atc-box-header">
                    <strong><?= $ftLabel ?></strong>
                    <span class="badge"><?= count($filesByType[$ftKey] ?? array()) ?></span>
                </div>
                <?php if (!empty($filesByType[$ftKey])): ?>
                    <?php foreach ($filesByType[$ftKey] as $f): ?>
                    <div class="atc-file-item">
                        <?php
                        $ext = strtolower(pathinfo($f['file_name'], PATHINFO_EXTENSION));
                        $isImg = in_array($ext, array('jpg','jpeg','png','gif','webp'));
                        ?>
                        <?php if ($isImg): ?>
                        <img src="/<?= e($f['file_path']) ?>" class="atc-thumb-sm" onclick="openLightbox('/<?= e($f['file_path']) ?>')">
                        <?php else: ?>
                        <a href="/<?= e($f['file_path']) ?>" target="_blank" class="atc-filename"><?= e($f['file_name']) ?></a>
                        <?php endif; ?>
                        <a href="/dispatch_workers.php?action=delete_file&file_id=<?= $f['id'] ?>" class="text-danger" style="font-size:.8rem" onclick="return confirm('確定刪除？')">✕</a>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                <label class="atc-add-btn">
                    <input type="file" name="files[]" style="display:none" multiple onchange="addFileType(this, '<?= $ftKey ?>')">
                    + 上傳<?= $ftLabel ?>
                </label>
            </div>
            <?php endforeach; ?>
        </div>
        <div id="fileTypeInputs"></div>
    </div>

    <div class="card">
        <div class="form-row">
            <div class="form-group">
                <label>備註</label>
                <textarea name="note" class="form-control" rows="2"><?= e($worker['note'] ?? '') ?></textarea>
            </div>
        </div>
        <label class="checkbox-label">
            <input type="checkbox" name="is_active" value="1" <?= ($worker['is_active'] ?? 1) ? 'checked' : '' ?>>
            <span>啟用中</span>
        </label>
    </div>

    <div class="d-flex gap-1">
        <button type="submit" class="btn btn-primary">儲存</button>
        <a href="/dispatch_workers.php?type=<?= e($wType) ?>" class="btn btn-outline">取消</a>
    </div>
</form>

<!-- Lightbox -->
<div class="lightbox-overlay" id="lightboxOverlay" onclick="closeLightbox()">
    <span class="lightbox-close">&times;</span>
    <img id="lightboxImg" src="" alt="預覽">
</div>

<style>
.form-row { display:flex; flex-wrap:wrap; gap:12px; }
.form-row .form-group { flex:1; min-width:150px; }
.attach-grid { display:grid; grid-template-columns:repeat(3, 1fr); gap:12px; }
.atc-box { border:1px solid var(--gray-200); border-radius:8px; padding:12px; }
.atc-box-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:8px; font-size:.9rem; }
.atc-file-item { display:flex; align-items:center; gap:8px; margin-bottom:6px; }
.atc-thumb-sm { width:60px; height:60px; object-fit:cover; border-radius:4px; cursor:pointer; border:1px solid var(--gray-200); }
.atc-add-btn { display:flex; align-items:center; justify-content:center; padding:8px; border:2px dashed var(--gray-300); border-radius:6px; cursor:pointer; color:var(--gray-500); font-size:.85rem; transition:all .15s; }
.atc-add-btn:hover { border-color:var(--primary); color:var(--primary); }
.lightbox-overlay { display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,.85); z-index:9999; align-items:center; justify-content:center; cursor:pointer; }
.lightbox-overlay.active { display:flex; }
.lightbox-overlay img { max-width:90%; max-height:90%; border-radius:8px; }
.lightbox-close { position:absolute; top:16px; right:24px; color:#fff; font-size:2rem; cursor:pointer; z-index:10000; }
@media (max-width:767px) { .attach-grid { grid-template-columns:repeat(2, 1fr); } }
@media (max-width:480px) { .attach-grid { grid-template-columns:1fr; } }
</style>

<script>
function calcAge(el) {
    if (!el.value) return;
    var bd = new Date(el.value);
    var today = new Date();
    var age = today.getFullYear() - bd.getFullYear();
    var m = today.getMonth() - bd.getMonth();
    if (m < 0 || (m === 0 && today.getDate() < bd.getDate())) age--;
    document.getElementById('ageDisplay').value = age + ' 歲';
}
function openLightbox(src) { var o = document.getElementById('lightboxOverlay'); o.classList.add('active'); document.getElementById('lightboxImg').src = src; }
function closeLightbox() { document.getElementById('lightboxOverlay').classList.remove('active'); document.getElementById('lightboxImg').src = ''; }
document.addEventListener('keydown', function(e) { if (e.key === 'Escape') closeLightbox(); });

var fileTypeCounter = 0;
function addFileType(input, type) {
    var container = document.getElementById('fileTypeInputs');
    for (var i = 0; i < input.files.length; i++) {
        var hidden = document.createElement('input');
        hidden.type = 'hidden';
        hidden.name = 'file_types[' + fileTypeCounter + ']';
        hidden.value = type;
        container.appendChild(hidden);
        fileTypeCounter++;
    }
}
</script>
