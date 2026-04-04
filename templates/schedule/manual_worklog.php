<?php
    $isEdit = !empty($isEditMode) && !empty($editWorklog);
    $ewl = $isEdit ? $editWorklog : array();
    $ewlPhotos = array();
    if ($isEdit && !empty($ewl['photo_paths'])) {
        $decoded = json_decode($ewl['photo_paths'], true);
        if (is_array($decoded)) $ewlPhotos = $decoded;
    }
?>
<div class="d-flex justify-between align-center mb-2">
    <h2><?= $isEdit ? '編輯施工回報' : '手動施工回報' ?></h2>
    <a href="/cases.php?action=edit&id=<?= $caseId ?>#sec-worklog" class="btn btn-outline btn-sm">← 返回案件</a>
</div>

<div class="card mb-2">
    <div class="card-header">案件資訊</div>
    <div style="padding:12px">
        <strong><?= e($caseData['customer_name'] ?? '') ?></strong>
        <span class="text-muted" style="margin-left:8px"><?= e($caseData['title'] ?? '') ?></span>
    </div>
</div>

<form method="POST" action="/worklog.php?action=<?= $isEdit ? 'update_manual_report' : 'save_manual_report' ?>" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <input type="hidden" name="case_id" value="<?= $caseId ?>">
    <?php if ($isEdit): ?><input type="hidden" name="worklog_id" value="<?= $ewl['id'] ?>"><?php endif; ?>

    <div class="card mb-2">
        <div class="card-header">施工時間</div>
        <div style="padding:12px">
            <div class="form-row" style="display:flex;gap:12px;flex-wrap:wrap">
                <div class="form-group" style="flex:1;min-width:140px">
                    <label>施工日期 *</label>
                    <input type="date" name="work_date" class="form-control" value="<?= $isEdit ? e($ewl['work_date']) : date('Y-m-d') ?>" required>
                </div>
                <div class="form-group" style="flex:1;min-width:120px">
                    <label>上工時間</label>
                    <input type="time" name="arrival_time" class="form-control">
                </div>
                <div class="form-group" style="flex:1;min-width:120px">
                    <label>下工時間</label>
                    <input type="time" name="departure_time" class="form-control">
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-2">
        <div class="card-header">施作說明</div>
        <div style="padding:12px">
            <div class="form-group">
                <label>施工內容 *</label>
                <textarea name="work_content" class="form-control" rows="4" placeholder="施工項目、使用設備等" required><?= $isEdit ? e($ewl['work_content']) : '' ?></textarea>
            </div>
            <div class="form-group">
                <label>問題/異常</label>
                <textarea name="issues" class="form-control" rows="3" placeholder="如有遇到問題或異常請填寫"><?= $isEdit ? e($ewl['equipment_used'] ?? '') : '' ?></textarea>
            </div>
            <div style="margin:12px 0">
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer;color:var(--success)">
                    <input type="checkbox" name="is_completed" value="1"> 已完工
                </label>
            </div>
            <div style="margin:12px 0">
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
                    <input type="checkbox" name="next_visit_needed" value="1" onchange="document.getElementById('nextVisitOpts').style.display=this.checked?'block':'none'"> 需要再次施工
                </label>
                <div id="nextVisitOpts" style="display:none;margin-top:8px;padding-left:24px">
                    <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap">
                        <label><input type="radio" name="next_visit_type" value="scheduled"> 預計日期：</label>
                        <input type="date" name="next_visit_date" class="form-control" style="width:auto">
                        <label><input type="radio" name="next_visit_type" value="pending" checked> 待安排</label>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-2">
        <div class="card-header">施工照片</div>
        <div style="padding:12px">
            <?php if ($isEdit && !empty($ewlPhotos)): ?>
            <div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:12px">
                <?php foreach ($ewlPhotos as $pp): ?>
                <img src="<?= e($pp) ?>" style="width:100px;height:100px;object-fit:cover;border-radius:6px;cursor:pointer" onclick="window.open('<?= e($pp) ?>')">
                <?php endforeach; ?>
            </div>
            <small class="text-muted" style="display:block;margin-bottom:8px">上方為現有照片，下方可追加新照片</small>
            <?php endif; ?>
            <input type="file" name="photos[]" multiple accept="image/*" class="form-control">
            <small class="text-muted">可選擇多張照片（支援 JPG/PNG/GIF/WebP）</small>
        </div>
    </div>

    <!-- 器材/耗材使用 -->
    <div class="card mb-2">
        <div class="card-header d-flex justify-between align-center">
            <span>器材/耗材使用</span>
            <button type="button" class="btn btn-outline btn-sm" onclick="addManualMaterial()">+ 新增材料</button>
        </div>
        <div style="padding:12px">
            <small class="text-muted" style="display:block;margin-bottom:8px">記錄出庫、使用、歸還數量</small>
            <div id="manualMaterialContainer">
<?php
$editMaterials = array();
if ($isEdit && !empty($ewl['id'])) {
    try {
        $mStmt = Database::getInstance()->prepare('SELECT * FROM worklog_materials WHERE worklog_id = ? AND worklog_type = ? ORDER BY id');
        $mStmt->execute(array($ewl['id'], 'manual'));
        $editMaterials = $mStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {}
}
$mIdx = 0;
if (!empty($editMaterials)):
    foreach ($editMaterials as $em):
?>
                <div class="material-row" data-index="<?= $mIdx ?>" style="margin-bottom:10px;border-bottom:1px solid #eee;padding-bottom:10px">
                    <div class="form-row" style="display:flex;gap:8px;flex-wrap:wrap">
                        <div class="form-group" style="min-width:60px;max-width:80px">
                            <label>類型</label>
                            <select name="materials[<?= $mIdx ?>][material_type]" class="form-control" style="font-size:.85rem">
                                <option value="equipment" <?= ($em['material_type'] ?? '') === 'equipment' ? 'selected' : '' ?>>器材</option>
                                <option value="consumable" <?= ($em['material_type'] ?? '') === 'consumable' ? 'selected' : '' ?>>耗材</option>
                            </select>
                        </div>
                        <div class="form-group" style="flex:2;position:relative">
                            <label>名稱</label>
                            <input type="text" name="materials[<?= $mIdx ?>][material_name]" class="form-control" value="<?= e($em['material_name'] ?? '') ?>" placeholder="輸入關鍵字搜尋產品...">
                            <input type="hidden" name="materials[<?= $mIdx ?>][product_id]" value="<?= e($em['product_id'] ?? '') ?>">
                        </div>
                        <div class="form-group" style="max-width:60px">
                            <label>單位</label>
                            <input type="text" name="materials[<?= $mIdx ?>][unit]" class="form-control" value="<?= e($em['unit'] ?? '') ?>" placeholder="個/米">
                        </div>
                        <div class="form-group" style="max-width:80px">
                            <label>出庫數量</label>
                            <input type="number" name="materials[<?= $mIdx ?>][shipped_qty]" class="form-control" step="0.1" min="0" value="<?= e($em['shipped_qty'] ?? '') ?>">
                        </div>
                        <div class="form-group" style="max-width:80px">
                            <label>使用數量</label>
                            <input type="number" name="materials[<?= $mIdx ?>][used_qty]" class="form-control" step="0.1" min="0" value="<?= e($em['used_qty'] ?? '') ?>">
                        </div>
                        <div class="form-group" style="max-width:80px">
                            <label>歸還數量</label>
                            <input type="number" name="materials[<?= $mIdx ?>][returned_qty]" class="form-control" step="0.1" min="0" value="<?= e($em['returned_qty'] ?? '') ?>">
                        </div>
                        <div class="form-group" style="max-width:80px">
                            <label>單價</label>
                            <input type="number" name="materials[<?= $mIdx ?>][unit_cost]" class="form-control" step="1" min="0" value="<?= e($em['unit_cost'] ?? '') ?>">
                        </div>
                        <div class="form-group" style="align-self:flex-end;flex:0">
                            <button type="button" class="btn btn-danger btn-sm" onclick="this.closest('.material-row').remove()">X</button>
                        </div>
                    </div>
                </div>
<?php $mIdx++; endforeach; else: ?>
                <p class="text-muted" style="font-size:.85rem">尚無材料項目，點「+ 新增材料」新增</p>
<?php endif; ?>
            </div>
            <small class="text-muted">當按下儲存回報後生成一張入庫單</small>
        </div>
    </div>

    <div class="card mb-2">
        <div class="card-header">收款資訊</div>
        <div style="padding:12px">
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
                <input type="checkbox" name="payment_collected" value="1" onchange="document.getElementById('paymentFields').style.display=this.checked?'flex':'none'"> 本次有收款
            </label>
            <div id="paymentFields" style="display:none;margin-top:8px;gap:12px;flex-wrap:wrap">
                <div class="form-group" style="flex:1;min-width:120px">
                    <label>收款金額</label>
                    <input type="number" name="payment_amount" class="form-control" min="0">
                </div>
                <div class="form-group" style="flex:1;min-width:120px">
                    <label>收款方式</label>
                    <select name="payment_method" class="form-control">
                        <option value="cash">現金</option>
                        <option value="transfer">匯款</option>
                        <option value="check">支票</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <button type="submit" class="btn btn-primary" id="wlSubmitBtn" style="width:100%;padding:12px;font-size:1.1rem"><?= $isEdit ? '更新回報' : '儲存回報' ?></button>
</form>
<script>
var manualMaterialIdx = <?= $mIdx ?>;
function addManualMaterial() {
    var i = manualMaterialIdx++;
    var html = '<div class="material-row" data-index="' + i + '" style="margin-bottom:10px;border-bottom:1px solid #eee;padding-bottom:10px">' +
        '<div class="form-row" style="display:flex;gap:8px;flex-wrap:wrap">' +
        '<div class="form-group" style="min-width:60px;max-width:80px"><label>類型</label><select name="materials[' + i + '][material_type]" class="form-control" style="font-size:.85rem"><option value="equipment">器材</option><option value="consumable">耗材</option></select></div>' +
        '<div class="form-group" style="flex:2"><label>名稱</label><input type="text" name="materials[' + i + '][material_name]" class="form-control" placeholder="輸入關鍵字搜尋產品..."><input type="hidden" name="materials[' + i + '][product_id]" value=""></div>' +
        '<div class="form-group" style="max-width:60px"><label>單位</label><input type="text" name="materials[' + i + '][unit]" class="form-control" placeholder="個/米"></div>' +
        '<div class="form-group" style="max-width:80px"><label>出庫數量</label><input type="number" name="materials[' + i + '][shipped_qty]" class="form-control" step="0.1" min="0"></div>' +
        '<div class="form-group" style="max-width:80px"><label>使用數量</label><input type="number" name="materials[' + i + '][used_qty]" class="form-control" step="0.1" min="0"></div>' +
        '<div class="form-group" style="max-width:80px"><label>歸還數量</label><input type="number" name="materials[' + i + '][returned_qty]" class="form-control" step="0.1" min="0"></div>' +
        '<div class="form-group" style="max-width:80px"><label>單價</label><input type="number" name="materials[' + i + '][unit_cost]" class="form-control" step="1" min="0"></div>' +
        '<div class="form-group" style="align-self:flex-end;flex:0"><button type="button" class="btn btn-danger btn-sm" onclick="this.closest(\'.material-row\').remove()">X</button></div>' +
        '</div></div>';
    var container = document.getElementById('manualMaterialContainer');
    var placeholder = container.querySelector('p.text-muted');
    if (placeholder) placeholder.remove();
    container.insertAdjacentHTML('beforeend', html);
}
document.querySelector('form[action*="manual_report"]').addEventListener('submit', function(e) {
    var fileInput = this.querySelector('input[name="photos[]"]');
    if (!fileInput || !fileInput.files.length) return;
    e.preventDefault();
    var form = this;
    var btn = document.getElementById('wlSubmitBtn');
    btn.disabled = true;
    btn.textContent = '壓縮上傳中...';
    compressImages(Array.prototype.slice.call(fileInput.files)).then(function(compressed) {
        var fd = new FormData(form);
        fd.delete('photos[]');
        for (var i = 0; i < compressed.length; i++) {
            fd.append('photos[]', compressed[i]);
        }
        var xhr = new XMLHttpRequest();
        xhr.open('POST', form.action);
        xhr.onload = function() {
            if (xhr.status >= 200 && xhr.status < 400) {
                window.location.href = xhr.responseURL || form.action.replace(/action=.*/, '');
                location.reload();
            }
        };
        xhr.send(fd);
    });
});
</script>
