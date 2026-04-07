<?php
$stageLabels = CaseModel::stageLabels();
$caseTypeOptions = CaseModel::caseTypeOptions();
$sourceOptions = CaseModel::caseSourceOptions();
// 自動計算階段
$caseModel = new CaseModel();
$currentStage = $caseModel->syncStage($case['id']);
?>
<div class="d-flex justify-between align-center flex-wrap gap-1 mb-2">
    <div>
        <h2><?= e($case['customer_name'] ?: $case['title']) ?></h2>
        <span class="badge"><?= e($case['case_number']) ?></span>
        <span class="badge" style="background:<?= CaseModel::stageColor($currentStage) ?>;color:#fff"><?= e(isset($stageLabels[$currentStage]) ? $stageLabels[$currentStage] : '') ?></span>
        <?php
        $warnings = get_readiness_warnings(isset($case['readiness']) ? $case['readiness'] : array(), isset($case['case_type']) ? $case['case_type'] : 'new_install');
        if (!empty($warnings)):
        ?>
        <span style="color:#e65100;font-size:.85rem;font-weight:600;margin-left:12px">排工條件尚未備齊：<?= implode('、', array_map('e', $warnings)) ?></span>
        <?php endif; ?>
    </div>
    <div class="d-flex gap-1 flex-wrap">
        <a href="/business_tracking.php?action=edit&id=<?= $case['id'] ?>" class="btn btn-primary btn-sm">編輯</a>
        <?= back_button('/business_tracking.php') ?>
    </div>
</div>

<!-- Stage Progress Bar -->
<div class="card mb-2">
    <?php
    // 共同路徑: 進件→場勘→報價，之後分叉
    $commonStages = array(1, 2, 3);
    $isLost = ($currentStage === 8);
    $isSuccess = ($currentStage >= 4 && $currentStage <= 7);
    ?>
    <div class="stage-progress">
        <?php foreach ($commonStages as $idx => $i):
            $isActive = ($currentStage >= $i);
        ?>
        <div class="stage-step <?= $isActive ? 'active' : '' ?> <?= $currentStage === $i ? 'current' : '' ?>">
            <div class="stage-dot" style="<?= $isActive ? 'background:'.CaseModel::stageColor($i) : '' ?>">
                <?= $isActive ? '&#10003;' : ($idx + 1) ?>
            </div>
            <div class="stage-label"><?= e($stageLabels[$i]) ?></div>
        </div>
        <div class="stage-line <?= $currentStage > $i ? 'active' : '' ?>"></div>
        <?php endforeach; ?>

        <?php if ($isLost): ?>
        <!-- 未成交/無效 -->
        <div class="stage-step active current">
            <div class="stage-dot" style="background:<?= CaseModel::stageColor(8) ?>">&#10007;</div>
            <div class="stage-label"><?= e($stageLabels[8]) ?></div>
        </div>
        <?php else: ?>
        <!-- 成交待排工 -->
        <div class="stage-step <?= $isSuccess ? 'active' : '' ?> <?= $currentStage === 4 || ($currentStage >= 5 && $currentStage <= 7) ? 'current' : '' ?>">
            <div class="stage-dot" style="<?= $isSuccess ? 'background:'.CaseModel::stageColor(4) : '' ?>">
                <?= $isSuccess ? '&#10003;' : '4' ?>
            </div>
            <div class="stage-label"><?= e($stageLabels[4]) ?></div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- 階段自動判斷提示 -->
<div class="card mb-2">
    <div style="padding:8px;font-size:.85rem;color:#666">
        <span style="color:<?= CaseModel::stageColor($currentStage) ?>;font-weight:600">● <?= e($stageLabels[$currentStage]) ?></span> — 系統自動判斷
        <span class="text-muted" style="margin-left:8px">（依據：場勘資料、報價單、成交金額、排工、施工回報、結案狀態）</span>
    </div>
</div>

<!-- Detail Cards -->
<div class="card mb-2">
    <div class="card-header">案件資訊</div>
    <div class="detail-grid">
        <div class="detail-item"><span class="detail-label">案件編號</span><span class="detail-value"><?= e($case['case_number']) ?></span></div>
        <div class="detail-item"><span class="detail-label">案件名稱</span><span class="detail-value"><?= e($case['title']) ?></span></div>
        <div class="detail-item"><span class="detail-label">案別</span><span class="detail-value"><?= e(isset($caseTypeOptions[$case['case_type']]) ? $caseTypeOptions[$case['case_type']] : '-') ?></span></div>
        <div class="detail-item"><span class="detail-label">案件來源</span><span class="detail-value"><?= e(isset($sourceOptions[$case['case_source']]) ? $sourceOptions[$case['case_source']] : '-') ?></span></div>
        <div class="detail-item"><span class="detail-label">進件公司</span><span class="detail-value"><?= e($case['company'] ?: '-') ?></span></div>
        <div class="detail-item"><span class="detail-label">分公司</span><span class="detail-value"><?= e(isset($case['branch_name']) ? $case['branch_name'] : '-') ?></span></div>
    </div>
</div>

<div class="card mb-2">
    <div class="card-header">客戶資訊</div>
    <div class="detail-grid">
        <div class="detail-item"><span class="detail-label">客戶名稱</span><span class="detail-value"><?= e($case['customer_name'] ?: '-') ?></span></div>
        <div class="detail-item"><span class="detail-label">聯絡人</span><span class="detail-value"><?= e($case['contact_person'] ?: '-') ?></span></div>
        <div class="detail-item"><span class="detail-label">電話</span><span class="detail-value"><?= e($case['customer_phone'] ?: '-') ?></span></div>
        <div class="detail-item"><span class="detail-label">手機</span><span class="detail-value"><?= e($case['customer_mobile'] ?: '-') ?></span></div>
        <div class="detail-item" style="grid-column:span 2"><span class="detail-label">聯絡地址</span><span class="detail-value"><?= e($case['contact_address'] ?: '-') ?></span></div>
        <div class="detail-item" style="grid-column:span 2"><span class="detail-label">施工地址</span><span class="detail-value"><?= e(trim(($case['city'] ?: '') . ($case['district'] ?: '') . ($case['address'] ?: '')) ?: '-') ?></span></div>
    </div>
</div>

<div class="card mb-2">
    <div class="card-header">案件進度</div>
    <div class="detail-grid">
        <div class="detail-item"><span class="detail-label">狀態</span><span class="detail-value"><?= e($case['sub_status'] ?: '-') ?></span></div>
        <?php if (!empty($case['survey_date'])): ?>
        <div class="detail-item"><span class="detail-label">場勘日期</span><span class="detail-value"><?= e($case['survey_date']) ?><?= !empty($case['survey_time']) ? ' ' . e(substr($case['survey_time'], 0, 5)) : '' ?></span></div>
        <?php endif; ?>
        <?php if (!empty($case['visit_method'])): ?>
        <div class="detail-item"><span class="detail-label">拜訪方式</span><span class="detail-value"><?= e($case['visit_method']) ?></span></div>
        <?php endif; ?>
        <?php if (!empty($case['deal_date'])): ?>
        <div class="detail-item"><span class="detail-label">成交日期</span><span class="detail-value"><?= e($case['deal_date']) ?></span></div>
        <?php endif; ?>
    </div>
</div>

<div class="card mb-2">
    <div class="card-header">業務資訊</div>
    <div class="detail-grid">
        <div class="detail-item"><span class="detail-label">承辦業務</span><span class="detail-value"><?= e(isset($case['sales_name']) ? $case['sales_name'] : '-') ?></span></div>
        <div class="detail-item"><span class="detail-label">預估金額</span><span class="detail-value"><?= $case['deal_amount'] ? '$'.number_format($case['deal_amount']) : '-' ?></span></div>
        <div class="detail-item"><span class="detail-label">成交日期</span><span class="detail-value"><?= e($case['deal_date'] ?: '-') ?></span></div>
        <div class="detail-item"><span class="detail-label">建立日期</span><span class="detail-value"><?= e($case['created_at']) ?></span></div>
        <?php if (!empty($case['sales_note'])): ?>
        <div class="detail-item" style="grid-column:span 2"><span class="detail-label">備註</span><span class="detail-value"><?= nl2br(e($case['sales_note'])) ?></span></div>
        <?php endif; ?>
    </div>
</div>

<?= csrf_field() ?>
<!-- 附件管理（連動案件管理） -->
<?php
$attachTypes = array(
    'drawing'    => '施工圖',
    'quotation'  => '報價單',
    'warranty'   => '保固書',
    'wire_plan'  => '預計使用線材',
    'site_photo' => '現場照片',
    'other'      => '其他',
);
$groupedAtt = array();
foreach ($attachTypes as $ak => $av) { $groupedAtt[$ak] = array(); }
if (!empty($case['attachments'])) {
    foreach ($case['attachments'] as $att) {
        $t = isset($groupedAtt[$att['file_type']]) ? $att['file_type'] : 'other';
        $groupedAtt[$t][] = $att;
    }
}
?>
<div class="card mb-2">
    <div class="card-header">附件管理</div>
    <div class="attach-grid">
        <?php foreach ($attachTypes as $typeKey => $typeLabel): ?>
        <div class="attach-type-card" id="atc-<?= $typeKey ?>" data-file-type="<?= $typeKey ?>">
            <div class="atc-header">
                <span class="atc-title"><?= e($typeLabel) ?></span>
                <span class="atc-count" id="atc-count-<?= $typeKey ?>"><?= count($groupedAtt[$typeKey]) ?></span>
            </div>
            <div class="atc-files" id="atc-files-<?= $typeKey ?>">
                <?php foreach ($groupedAtt[$typeKey] as $att):
                    $ext = strtolower(pathinfo($att['file_name'], PATHINFO_EXTENSION));
                    $isImg = in_array($ext, array('jpg','jpeg','png','gif','webp','bmp'));
                ?>
                <div class="atc-file <?= $isImg ? 'atc-file-img' : '' ?>" id="att-<?= $att['id'] ?>">
                    <?php if ($isImg): ?>
                    <img src="<?= e($att['file_path']) ?>" class="atc-thumb hs-photo" onclick="hsOpenImage('<?= e($att['file_path']) ?>')" alt="<?= e($att['file_name']) ?>">
                    <?php else: ?>
                    <a href="javascript:void(0)" onclick="hsOpenFile('<?= e($att['file_path']) ?>','<?= e($att['file_name']) ?>')" class="atc-filename">📄 <?= e($att['file_name']) ?></a>
                    <?php endif; ?>
                    <button type="button" class="atc-del" onclick="deleteAttachment(<?= $att['id'] ?>, '<?= $typeKey ?>')">✕</button>
                </div>
                <?php endforeach; ?>
            </div>
            <label class="atc-add-btn">
                <input type="file" multiple accept="image/*,.pdf,.doc,.docx,.xls,.xlsx" style="display:none" onchange="uploadFiles(this, '<?= $typeKey ?>')">
                <span>＋ 上傳<?= e($typeLabel) ?>（或拖曳檔案進來）</span>
            </label>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Lightbox -->
<div class="lightbox-overlay" id="lightboxOverlay" onclick="closeLightbox()">
    <span class="lightbox-close">&times;</span>
    <img id="lightboxImg" src="" alt="預覽">
</div>

<script>
function openLightbox(src) { var o = document.getElementById('lightboxOverlay'); document.getElementById('lightboxImg').src = src; o.style.display = 'flex'; }
function closeLightbox() { document.getElementById('lightboxOverlay').style.display = 'none'; }

var CASE_ID_BT = '<?= $case['id'] ?>';
function uploadFiles(input, fileType) {
    if (!input.files.length) return;
    doUpload(input.files, fileType, input.parentElement, function(){ input.value = ''; });
}
function doUpload(files, fileType, addBtn, doneCb) {
    var csrfToken = document.querySelector('input[name="csrf_token"]').value;
    var origText = addBtn.querySelector('span').textContent;
    var uploaded = 0, total = files.length;
    addBtn.querySelector('span').textContent = '上傳中 0/' + total + '...';
    for (var i = 0; i < files.length; i++) {
        (function(file) {
            if (file.size > 20 * 1024 * 1024) {
                alert(file.name + ' 超過 20MB');
                uploaded++;
                if (uploaded >= total) { addBtn.querySelector('span').textContent = origText; if (doneCb) doneCb(); }
                return;
            }
            var fd = new FormData();
            fd.append('file', file);
            fd.append('file_type', fileType);
            fd.append('csrf_token', csrfToken);
            var xhr = new XMLHttpRequest();
            xhr.open('POST', '/cases.php?action=upload_attachment&id=' + CASE_ID_BT);
            xhr.onload = function() {
                uploaded++;
                addBtn.querySelector('span').textContent = '上傳中 ' + uploaded + '/' + total + '...';
                if (xhr.status === 200) {
                    try {
                        var res = JSON.parse(xhr.responseText);
                        if (res.success) {
                            var imgExts = ['jpg','jpeg','png','gif','webp','bmp'];
                            var ext = res.file_name.split('.').pop().toLowerCase();
                            var html;
                            if (imgExts.indexOf(ext) !== -1) {
                                html = '<div class="atc-file atc-file-img" id="att-' + res.id + '"><img src="' + res.file_path + '" class="atc-thumb hs-photo" onclick="hsOpenImage(\'' + res.file_path + '\')" alt="' + res.file_name + '"><button type="button" class="atc-del" onclick="deleteAttachment(' + res.id + ',\'' + fileType + '\')">✕</button></div>';
                            } else {
                                html = '<div class="atc-file" id="att-' + res.id + '"><a href="javascript:void(0)" onclick="hsOpenFile(\'' + res.file_path + '\',\'' + res.file_name + '\')" class="atc-filename">📄 ' + res.file_name + '</a><button type="button" class="atc-del" onclick="deleteAttachment(' + res.id + ',\'' + fileType + '\')">✕</button></div>';
                            }
                            document.getElementById('atc-files-' + fileType).insertAdjacentHTML('beforeend', html);
                            updateCount(fileType, 1);
                        } else { alert(res.error || '上傳失敗'); }
                    } catch(e) { alert('上傳失敗：' + e.message); }
                } else {
                    alert('上傳失敗 (HTTP ' + xhr.status + ')');
                }
                if (uploaded >= total) { addBtn.querySelector('span').textContent = origText; if (doneCb) doneCb(); }
            };
            xhr.onerror = function() {
                uploaded++;
                alert('網路錯誤');
                if (uploaded >= total) { addBtn.querySelector('span').textContent = origText; if (doneCb) doneCb(); }
            };
            xhr.send(fd);
        })(files[i]);
    }
}

// 拖曳上傳
(function() {
    function bindCard(card) {
        var fileType = card.getAttribute('data-file-type');
        var addBtn = card.querySelector('.atc-add-btn');
        ['dragenter','dragover'].forEach(function(ev){
            card.addEventListener(ev, function(e){ e.preventDefault(); e.stopPropagation(); card.classList.add('atc-drag-over'); });
        });
        ['dragleave','drop'].forEach(function(ev){
            card.addEventListener(ev, function(e){ e.preventDefault(); e.stopPropagation(); if (ev === 'dragleave' && card.contains(e.relatedTarget)) return; card.classList.remove('atc-drag-over'); });
        });
        card.addEventListener('drop', function(e) {
            var files = e.dataTransfer && e.dataTransfer.files;
            if (files && files.length) doUpload(files, fileType, addBtn);
        });
    }
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.attach-type-card[data-file-type]').forEach(bindCard);
    });
    // 防止整頁被瀏覽器當成檔案開啟
    ['dragover','drop'].forEach(function(ev){
        window.addEventListener(ev, function(e){
            if (e.target.closest('.attach-type-card')) return;
            e.preventDefault();
        });
    });
})();

function updateCount(fileType, delta) {
    var el = document.getElementById('atc-count-' + fileType);
    if (el) el.textContent = parseInt(el.textContent || '0') + delta;
}

function deleteAttachment(id, fileType) {
    if (!confirm('確定刪除此附件?')) return;
    var csrfToken = document.querySelector('input[name="csrf_token"]').value;
    var xhr = new XMLHttpRequest();
    xhr.open('POST', '/cases.php?action=delete_attachment');
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onload = function() {
        if (xhr.status === 200) {
            try {
                var res = JSON.parse(xhr.responseText);
                if (res.success) { var el = document.getElementById('att-' + id); if (el) el.remove(); if (fileType) updateCount(fileType, -1); }
                else { alert(res.error || '刪除失敗'); }
            } catch(e) { alert('刪除失敗'); }
        }
    };
    xhr.send('attachment_id=' + id + '&csrf_token=' + csrfToken);
}
</script>

<style>
.detail-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 8px; }
.detail-item { display: flex; flex-direction: column; }
.detail-label { font-size: .8rem; color: var(--gray-500); }
.stage-progress { display: flex; align-items: center; justify-content: center; padding: 16px 8px; flex-wrap: nowrap; overflow-x: auto; }
.stage-step { display: flex; flex-direction: column; align-items: center; min-width: 50px; }
.stage-dot { width: 32px; height: 32px; border-radius: 50%; background: var(--gray-200); color: var(--gray-500); display: flex; align-items: center; justify-content: center; font-size: .8rem; font-weight: 600; }
.stage-step.active .stage-dot { color: #fff; }
.stage-step.current .stage-dot { box-shadow: 0 0 0 3px rgba(33,150,243,.3); }
.stage-label { font-size: .7rem; color: var(--gray-500); margin-top: 4px; white-space: nowrap; }
.stage-step.active .stage-label { color: var(--gray-700); font-weight: 600; }
.stage-line { flex: 1; height: 2px; background: var(--gray-200); min-width: 20px; margin: 0 4px; margin-bottom: 20px; }
.stage-line.active { background: var(--primary); }
@media (max-width: 767px) { .detail-grid { grid-template-columns: 1fr; } }

/* 附件 */
.attach-grid { display:grid; grid-template-columns:repeat(3, 1fr); gap:12px; }
.attach-type-card { border:1px solid var(--gray-200); border-radius:8px; padding:12px; display:flex; flex-direction:column; min-height:100px; }
.atc-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:8px; }
.atc-title { font-weight:600; font-size:.9rem; }
.atc-count { background:var(--gray-100); color:var(--gray-500); font-size:.75rem; padding:2px 8px; border-radius:10px; }
.atc-files { flex:1; display:flex; flex-wrap:wrap; gap:6px; margin-bottom:8px; }
.atc-file { position:relative; }
.atc-file-img { display:inline-block; }
.atc-file:not(.atc-file-img) { display:flex; align-items:center; justify-content:space-between; padding:4px 8px; background:var(--gray-50, #f9fafb); border-radius:4px; font-size:.8rem; width:100%; }
.atc-thumb { width:72px; height:72px; object-fit:cover; border-radius:6px; cursor:pointer; border:1px solid var(--gray-200); transition:opacity .15s; }
.atc-thumb:hover { opacity:.8; }
.atc-file-img .atc-del { position:absolute; top:-4px; right:-4px; background:#fff; border:1px solid var(--gray-300); border-radius:50%; width:20px; height:20px; display:flex; align-items:center; justify-content:center; font-size:.65rem; padding:0; box-shadow:0 1px 3px rgba(0,0,0,.15); }
.atc-filename { color:var(--primary); text-decoration:none; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; max-width:160px; font-size:.8rem; }
.atc-filename:hover { text-decoration:underline; }
.atc-file:not(.atc-file-img) .atc-del { background:none; border:none; color:var(--gray-400); cursor:pointer; font-size:.8rem; padding:2px 4px; }
.atc-file:not(.atc-file-img) .atc-del:hover { color:#e53935; }
.atc-add-btn { display:flex; align-items:center; justify-content:center; padding:8px; border:2px dashed var(--gray-300); border-radius:6px; cursor:pointer; color:var(--gray-500); font-size:.85rem; transition:all .15s; }
.atc-add-btn:hover { border-color:var(--primary); color:var(--primary); background:rgba(33,150,243,.04); }
.attach-type-card.atc-drag-over { border-color:var(--primary); background:rgba(33,150,243,.08); box-shadow:0 0 0 3px rgba(33,150,243,.15); }
.attach-type-card.atc-drag-over .atc-add-btn { border-color:var(--primary); color:var(--primary); }
.lightbox-overlay { display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,.85); z-index:9999; align-items:center; justify-content:center; cursor:pointer; }
.lightbox-overlay img { max-width:90%; max-height:90%; border-radius:8px; box-shadow:0 4px 20px rgba(0,0,0,.5); }
.lightbox-close { position:absolute; top:16px; right:24px; color:#fff; font-size:2rem; cursor:pointer; z-index:10000; }
@media (max-width: 767px) { .attach-grid { grid-template-columns:repeat(2, 1fr); } .atc-thumb { width:56px; height:56px; } }
@media (max-width: 480px) { .attach-grid { grid-template-columns:1fr; } }
</style>
