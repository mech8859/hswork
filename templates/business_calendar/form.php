<?php
$activityTypes = BusinessCalendarModel::activityTypes();
$regionOptions = BusinessCalendarModel::regionOptions();
$isEdit = !empty($event);
if (!isset($canEdit)) $canEdit = true;
$readOnly = $isEdit && !$canEdit;
$statusOptions = array(
    'planned' => '計劃中',
    'completed' => '已完成',
    'cancelled' => '已取消',
);
require __DIR__ . '/../_readonly_form_helper.php';
?>

<h2><?= $isEdit ? ($readOnly ? '檢視業務行程' : '編輯業務行程') : '新增業務行程' ?></h2>

<form method="POST" class="mt-2 <?= $readOnly ? 'form-readonly' : '' ?>" id="bcForm">
    <?= csrf_field() ?>

    <div class="card">
        <div class="card-header">行程資料</div>
        <div class="form-row">
            <div class="form-group">
                <label>行程日期 *</label>
                <input type="date" max="2099-12-31" name="event_date" class="form-control" value="<?= e($event['event_date'] ?? ($_GET['event_date'] ?? date('Y-m-d'))) ?>" required>
            </div>
            <div class="form-group">
                <label>業務人員 *</label>
                <select name="staff_id" class="form-control" required>
                    <option value="">請選擇</option>
                    <?php foreach ($salespeople as $sp): ?>
                    <option value="<?= $sp['id'] ?>" <?= ($event['staff_id'] ?? '') == $sp['id'] ? 'selected' : '' ?>><?= e($sp['real_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>活動類型 *</label>
                <select name="activity_type" class="form-control" required>
                    <option value="">請選擇</option>
                    <?php foreach ($activityTypes as $atKey => $atLabel): ?>
                    <option value="<?= e($atKey) ?>" <?= ($event['activity_type'] ?? '') === $atKey ? 'selected' : '' ?>><?= e($atLabel) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>客戶名稱</label>
                <input type="text" name="customer_name" class="form-control" value="<?= e($event['customer_name'] ?? '') ?>" placeholder="客戶或公司名稱">
            </div>
            <div class="form-group">
                <label>電話</label>
                <input type="text" name="phone" class="form-control" value="<?= e($event['phone'] ?? '') ?>">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>地區</label>
                <select name="region" class="form-control">
                    <option value="">請選擇</option>
                    <?php foreach ($regionOptions as $rKey => $rLabel): ?>
                    <option value="<?= e($rKey) ?>" <?= ($event['region'] ?? '') === $rKey ? 'selected' : '' ?>><?= e($rLabel) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="flex:2">
                <label>地址</label>
                <input type="text" name="address" class="form-control" value="<?= e($event['address'] ?? '') ?>" placeholder="客戶地址">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>開始時間</label>
                <input type="time" name="start_time" class="form-control" value="<?= e($event['start_time'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>結束時間</label>
                <input type="time" name="end_time" class="form-control" value="<?= e($event['end_time'] ?? '') ?>">
            </div>
        </div>
        <?php if ($isEdit && !empty($event['case_id'])): ?>
        <div class="form-group">
            <label>客戶需求 <small style="color:#888">(同步案件，不能於此修改)</small></label>
            <textarea class="form-control" rows="2" readonly style="background:#f5f5f5;color:#555"><?= e($event['case_customer_demand'] ?? '') ?></textarea>
        </div>
        <?php endif; ?>
        <div class="form-group">
            <label>備註</label>
            <textarea name="note" class="form-control" rows="3"><?= e($event['note'] ?? '') ?></textarea>
        </div>
    </div>

    <?php if ($isEdit): ?>
    <div class="card">
        <div class="card-header">執行狀態</div>
        <div class="form-row">
            <div class="form-group">
                <label>狀態</label>
                <select name="status" class="form-control">
                    <?php foreach ($statusOptions as $sKey => $sLabel): ?>
                    <option value="<?= e($sKey) ?>" <?= ($event['status'] ?? 'planned') === $sKey ? 'selected' : '' ?>><?= e($sLabel) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="form-group">
            <label>執行結果</label>
            <textarea name="result" class="form-control" rows="3" placeholder="拜訪結果、客戶回饋..."><?= e($event['result'] ?? '') ?></textarea>
        </div>
    </div>
    <?php endif; ?>

    <div class="d-flex gap-1 mt-2">
        <button type="submit" class="btn btn-primary"><?= $isEdit ? '儲存變更' : '建立行程' ?></button>
        <a href="/business_calendar.php" class="btn btn-outline">返回</a>
        <?php if ($isEdit && $canEdit): ?>
        <a href="/business_calendar.php?action=delete&id=<?= $event['id'] ?>&csrf_token=<?= e(Session::getCsrfToken()) ?>"
           class="btn btn-danger" style="margin-left:auto"
           onclick="return confirm('確定要刪除此行程嗎？')">刪除</a>
        <?php endif; ?>
    </div>
</form>

<?php if ($isEdit && !empty($event['case_id'])): ?>
<!-- 現場照片（連動案件管理 site_photo） -->
<div class="card mt-2" id="bcSitePhotoCard">
    <div class="card-header d-flex justify-between align-center">
        <span>📷 現場照片 <span class="text-muted" style="font-size:.8rem">（同步顯示在案件管理）</span></span>
        <?php if ($canEdit): ?>
        <label class="btn btn-primary btn-sm" style="cursor:pointer;margin:0">
            <input type="file" id="bcPhotoInput" accept="image/*" multiple capture="environment" style="display:none" onchange="bcUploadPhotos(this)">
            ＋ 上傳照片
        </label>
        <?php endif; ?>
    </div>
    <div id="bcPhotoGrid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(120px,1fr));gap:8px;margin-top:8px">
        <?php foreach ($sitePhotos as $p): ?>
        <div class="bc-photo-item" id="bcph-<?= (int)$p['id'] ?>" style="position:relative;background:#f5f5f5;border-radius:6px;overflow:hidden;aspect-ratio:1">
            <img src="<?= e($p['file_path']) ?>" class="hs-photo" onclick="hsOpenImage('<?= e($p['file_path']) ?>')" style="width:100%;height:100%;object-fit:cover;cursor:pointer" alt="<?= e($p['file_name']) ?>">
            <?php if ($canEdit): ?>
            <button type="button" onclick="bcDeletePhoto(<?= (int)$p['id'] ?>)" style="position:absolute;top:3px;right:3px;width:24px;height:24px;border:none;background:rgba(0,0,0,.6);color:#fff;border-radius:50%;cursor:pointer;font-size:.85rem;line-height:1">×</button>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
        <?php if (empty($sitePhotos)): ?>
        <div style="grid-column:1/-1;text-align:center;color:#999;padding:20px;font-size:.9rem">尚無現場照片</div>
        <?php endif; ?>
    </div>
    <div id="bcPhotoStatus" style="margin-top:8px;font-size:.85rem;color:#1565c0;display:none"></div>
</div>

<script>
(function(){
    var eventId = <?= (int)$event['id'] ?>;
    var csrfToken = <?= json_encode(Session::getCsrfToken()) ?>;
    var canEdit = <?= $canEdit ? 'true' : 'false' ?>;

    window.bcUploadPhotos = function(input) {
        if (!input.files || !input.files.length) return;
        var files = Array.from(input.files);
        var statusEl = document.getElementById('bcPhotoStatus');
        var grid = document.getElementById('bcPhotoGrid');
        var emptyHint = grid.querySelector('[style*="grid-column:1/-1"]');
        if (emptyHint) emptyHint.remove();

        statusEl.style.display = 'block';
        statusEl.textContent = '上傳中 0/' + files.length + '...';

        var done = 0;
        files.forEach(function(file, idx) {
            var fd = new FormData();
            fd.append('event_id', eventId);
            fd.append('csrf_token', csrfToken);
            fd.append('photo', file);
            fetch('/business_calendar.php?action=upload_site_photo', { method:'POST', body:fd, credentials:'same-origin' })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    done++;
                    if (data.ok) {
                        var item = document.createElement('div');
                        item.className = 'bc-photo-item';
                        item.id = 'bcph-' + data.id;
                        item.style.cssText = 'position:relative;background:#f5f5f5;border-radius:6px;overflow:hidden;aspect-ratio:1';
                        item.innerHTML = '<img src="' + data.path + '" class="hs-photo" onclick="hsOpenImage(\'' + data.path + '\')" style="width:100%;height:100%;object-fit:cover;cursor:pointer">' +
                            (canEdit ? '<button type="button" onclick="bcDeletePhoto(' + data.id + ')" style="position:absolute;top:3px;right:3px;width:24px;height:24px;border:none;background:rgba(0,0,0,.6);color:#fff;border-radius:50%;cursor:pointer;font-size:.85rem;line-height:1">×</button>' : '');
                        grid.appendChild(item);
                        statusEl.textContent = '上傳中 ' + done + '/' + files.length + '...';
                    } else {
                        statusEl.innerHTML = '<span style="color:#d32f2f">' + (data.message || '上傳失敗') + '</span>';
                    }
                    if (done === files.length) {
                        setTimeout(function(){ statusEl.style.display = 'none'; }, 1500);
                    }
                })
                .catch(function(err) {
                    done++;
                    statusEl.innerHTML = '<span style="color:#d32f2f">網路錯誤: ' + err + '</span>';
                });
        });
        input.value = '';
    };

    window.bcDeletePhoto = function(attId) {
        if (!confirm('確定刪除這張照片？（案件管理也會同步移除）')) return;
        var fd = new FormData();
        fd.append('event_id', eventId);
        fd.append('attachment_id', attId);
        fd.append('csrf_token', csrfToken);
        fetch('/business_calendar.php?action=delete_site_photo', { method:'POST', body:fd, credentials:'same-origin' })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.ok) {
                    var el = document.getElementById('bcph-' + attId);
                    if (el) el.remove();
                } else {
                    alert(data.message || '刪除失敗');
                }
            })
            .catch(function(err) { alert('網路錯誤: ' + err); });
    };
})();
</script>
<?php elseif ($isEdit && empty($event['case_id'])): ?>
<div class="card mt-2" style="background:#fffbea;border-left:3px solid #f9a825">
    <div style="padding:12px 16px;font-size:.88rem;color:#6a4800">
        📷 現場照片：此行程尚未關聯案件，無法上傳現場照片。請先至案件管理建立案件並關聯。
    </div>
</div>
<?php endif; ?>

<style>
.form-row { display: flex; flex-wrap: wrap; gap: 12px; }
.form-row .form-group { flex: 1; min-width: 150px; }
</style>
