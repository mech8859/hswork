<div class="d-flex justify-between align-center mb-2">
    <h2>施工回報</h2>
    <div class="d-flex gap-1">
        <?php if (!empty($worklog['schedule_id'])): ?>
        <a href="/schedule.php?action=view&id=<?= $worklog['schedule_id'] ?>" class="btn btn-outline btn-sm">返回排工</a>
        <?php endif; ?>
        <a href="/worklog.php?action=history" class="btn btn-outline btn-sm">歷史記錄</a>
        <a href="/worklog.php" class="btn btn-outline btn-sm">今日施工</a>
    </div>
</div>

<!-- 案件資訊 -->
<div class="card">
    <div class="d-flex justify-between align-center">
        <strong><?= e($worklog['case_title']) ?></strong>
        <span class="badge badge-primary"><?= e($worklog['case_number']) ?></span>
    </div>
    <div class="text-muted" style="font-size:.85rem;margin-top:4px">
        <?= format_date($worklog['schedule_date']) ?>
        <?php if ($worklog['total_visits'] > 1): ?> | 第<?= $worklog['visit_number'] ?>/<?= $worklog['total_visits'] ?>次<?php endif; ?>
        <?php if ($worklog['address']): ?> | <?= e($worklog['address']) ?><?php endif; ?>
    </div>
</div>

<?php
$arrivalHM = $worklog['arrival_time'] ? date('H:i', strtotime($worklog['arrival_time'])) : '';
$departureHM = $worklog['departure_time'] ? date('H:i', strtotime($worklog['departure_time'])) : '';
?>
<form method="POST" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <?php if (!empty($_GET['from_schedule'])): ?>
    <input type="hidden" name="redirect_back" value="/schedule.php?action=view&id=<?= (int)$_GET['from_schedule'] ?>">
    <?php elseif (!empty($_GET['from_case'])): ?>
    <input type="hidden" name="redirect_back" value="/cases.php?action=edit&id=<?= (int)$_GET['from_case'] ?>#sec-worklog">
    <?php endif; ?>

    <!-- 上工/下工時間 -->
    <div class="card">
        <div class="card-header">施工時間</div>
        <div class="form-row">
            <div class="form-group">
                <label>上工時間</label>
                <input type="time" name="arrival_time" class="form-control" value="<?= e($arrivalHM) ?>" id="arrivalTimeInput">
            </div>
            <div class="form-group">
                <label>下工時間</label>
                <input type="time" name="departure_time" class="form-control" value="<?= e($departureHM) ?>" id="departureTimeInput">
            </div>
            <div class="form-group" style="align-self:flex-end">
                <span id="workHoursDisplay" class="text-muted" style="font-size:.85rem"></span>
            </div>
        </div>
    </div>

    <!-- 施作說明 -->
    <div class="card">
        <div class="card-header">施作說明</div>
        <div class="form-group">
            <label>施工內容 <span class="text-danger">*</span></label>
            <textarea name="work_description" class="form-control" rows="4" placeholder="施工項目、使用設備等" required><?= e($worklog['work_description'] ?? '') ?></textarea>
        </div>
        <div class="form-group">
            <label>問題/異常</label>
            <textarea name="issues" class="form-control" rows="3" placeholder="如有遇到問題或異常請填寫"><?= e($worklog['issues'] ?? '') ?></textarea>
        </div>

        <!-- 完工選項 -->
        <div class="form-row" style="margin-top:8px">
            <div class="form-group">
                <label class="checkbox-label">
                    <input type="hidden" name="is_completed" value="0">
                    <input type="checkbox" name="is_completed" value="1" <?= !empty($worklog['is_completed']) ? 'checked' : '' ?>
                           onchange="document.getElementById('nextVisitSection').style.display = this.checked ? 'none' : 'block'">
                    <span style="font-weight:600;color:var(--success)">已完工</span>
                </label>
            </div>
        </div>

        <!-- 再次施工 -->
        <div id="nextVisitSection" style="display:<?= !empty($worklog['is_completed']) ? 'none' : 'block' ?>;margin-top:8px">
            <div class="form-group">
                <label class="checkbox-label">
                    <input type="hidden" name="next_visit_needed" value="0">
                    <input type="checkbox" name="next_visit_needed" value="1" <?= !empty($worklog['next_visit_needed']) ? 'checked' : '' ?>
                           onchange="document.getElementById('nextVisitDetail').style.display = this.checked ? 'block' : 'none'">
                    <span>需要再次施工</span>
                </label>
            </div>
            <div id="nextVisitDetail" style="display:<?= !empty($worklog['next_visit_needed']) ? 'block' : 'none' ?>">
                <div class="form-row">
                    <div class="form-group">
                        <label>
                            <input type="radio" name="next_visit_type" value="scheduled" <?= ($worklog['next_visit_type'] ?? '') === 'scheduled' ? 'checked' : '' ?>
                                   onchange="document.getElementById('nextDatePicker').style.display='block'">
                            預計下次施工日期
                        </label>
                    </div>
                    <div class="form-group">
                        <label>
                            <input type="radio" name="next_visit_type" value="pending" <?= ($worklog['next_visit_type'] ?? 'pending') === 'pending' ? 'checked' : '' ?>
                                   onchange="document.getElementById('nextDatePicker').style.display='none'">
                            待安排
                        </label>
                    </div>
                </div>
                <div id="nextDatePicker" style="display:<?= ($worklog['next_visit_type'] ?? '') === 'scheduled' ? 'block' : 'none' ?>">
                    <div class="form-group">
                        <input type="date" name="next_visit_date" class="form-control" value="<?= e($worklog['next_visit_date'] ?? '') ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label>下次施工備註</label>
                    <textarea name="next_visit_note" class="form-control" rows="2" placeholder="下次施工需注意的事項"><?= e($worklog['next_visit_note'] ?? '') ?></textarea>
                </div>
            </div>
        </div>
    </div>

    <!-- 施工照片 -->
    <div class="card">
        <div class="card-header d-flex justify-between align-center">
            <span>施工照片</span>
            <span class="text-muted" style="font-size:.8rem"><?= count($worklog['photos'] ?? array()) ?> 張</span>
        </div>
        <div class="photo-grid-upload">
            <?php foreach (($worklog['photos'] ?? array()) as $photo): ?>
            <div class="photo-grid-item" id="photo-<?= $photo['id'] ?>">
                <img src="<?= e($photo['file_path']) ?>" alt="<?= e($photo['caption'] ?? '') ?>" onclick="openPhotoModal(this.src)">
                <button type="button" class="photo-grid-delete" onclick="deletePhoto(<?= $photo['id'] ?>)">&times;</button>
            </div>
            <?php endforeach; ?>
            <label class="photo-grid-add">
                <input type="file" name="photos[]" multiple accept="image/*" style="display:none"
                       onchange="previewPhotos(this)">
                <span>+ 上傳照片</span>
            </label>
        </div>
        <div id="photoPreviewContainer" class="photo-grid-upload" style="margin-top:4px"></div>
    </div>

    <!-- 收款資訊 -->
    <div class="card">
        <div class="card-header">收款資訊</div>
        <div class="form-group">
            <label class="checkbox-label">
                <input type="hidden" name="payment_collected" value="0">
                <input type="checkbox" name="payment_collected" value="1" <?= !empty($worklog['payment_collected']) ? 'checked' : '' ?>
                       onchange="document.getElementById('paymentSection').style.display = this.checked ? 'block' : 'none'">
                <span>本次有收款</span>
            </label>
        </div>
        <div id="paymentSection" style="display:<?= !empty($worklog['payment_collected']) ? 'block' : 'none' ?>">
            <div class="form-row">
                <div class="form-group">
                    <label>收款金額</label>
                    <input type="number" name="payment_amount" class="form-control" step="1" min="0" value="<?= e($worklog['payment_amount'] ?? '') ?>" placeholder="0">
                </div>
                <div class="form-group">
                    <label>收款方式</label>
                    <select name="payment_method" class="form-control">
                        <option value="">請選擇</option>
                        <option value="cash" <?= ($worklog['payment_method'] ?? '') === 'cash' ? 'selected' : '' ?>>現金</option>
                        <option value="transfer" <?= ($worklog['payment_method'] ?? '') === 'transfer' ? 'selected' : '' ?>>匯款</option>
                        <option value="check" <?= ($worklog['payment_method'] ?? '') === 'check' ? 'selected' : '' ?>>支票</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label>收款備註</label>
                <input type="text" name="payment_note" class="form-control" value="<?= e($worklog['payment_note'] ?? '') ?>" placeholder="收款備註（如支票號碼等）">
            </div>
        </div>
    </div>

    <!-- 材料使用 -->
    <?php
    $materials = $worklog['materials'] ?? array();
    if (empty($materials) && !empty($stockOutMaterials)) {
        $materials = array();
        foreach ($stockOutMaterials as $soi) {
            $materials[] = array(
                'material_type' => 'equipment',
                'product_id' => $soi['product_id'] ?? '',
                'material_name' => $soi['product_name'] ?? '',
                'unit' => $soi['unit'] ?? '',
                'shipped_qty' => $soi['quantity'] ?? 0,
                'used_qty' => '',
                'returned_qty' => '',
                'unit_cost' => $soi['unit_price'] ?? '',
                'material_note' => '',
                '_from_stock_out' => true,
            );
        }
    }
    // 從案件預估材料預填（無出庫單時）
    if (empty($materials) && !empty($estimateMaterials)) {
        $materials = array();
        foreach ($estimateMaterials as $em) {
            $materials[] = array(
                'material_type' => 'equipment',
                'product_id' => $em['product_id'] ?: '',
                'material_name' => $em['product_name'] ?: '',
                'unit' => $em['unit'] ?: '',
                'shipped_qty' => $em['quantity'] ?: 0,
                'used_qty' => '',
                'returned_qty' => '',
                'unit_cost' => '',
                'material_note' => '',
                '_from_estimate' => true,
            );
        }
    }
    if (empty($materials)) {
        $materials = array(array('material_type'=>'equipment','product_id'=>'','material_name'=>'','unit'=>'','shipped_qty'=>'','used_qty'=>'','returned_qty'=>'','unit_cost'=>'','material_note'=>''));
    }
    // 分類
    $equipMaterials = array();
    $consumMaterials = array();
    foreach ($materials as $idx => $m) {
        $m['_idx'] = $idx;
        if (($m['material_type'] ?? '') === 'consumable') {
            $consumMaterials[] = $m;
        } else {
            $equipMaterials[] = $m;
        }
    }
    $globalIdx = 0;
    ?>

    <!-- 器材 -->
    <div class="card">
        <div class="card-header d-flex justify-between align-center">
            <span>器材使用</span>
            <button type="button" class="btn btn-outline btn-sm" onclick="addMaterial('equipment')">+ 新增器材</button>
        </div>
        <div id="equipmentContainer">
            <?php foreach ($equipMaterials as $m):
                $idx = $globalIdx++;
                $fromStockOut = !empty($m['_from_stock_out']);
                $shippedQty = (float)($m['shipped_qty'] ?? 0);
                $usedQty = (float)($m['used_qty'] ?? 0);
                $returnQty = ($shippedQty > 0 && $usedQty > 0) ? ($shippedQty - $usedQty) : 0;
            ?>
            <div class="material-row" data-index="<?= $idx ?>"<?= $fromStockOut ? ' style="background:#fffde7;border-left:3px solid #FF9800;padding-left:8px;margin-bottom:8px"' : ' style="margin-bottom:8px;border-bottom:1px solid #eee;padding-bottom:8px"' ?>>
                <?php if ($fromStockOut): ?>
                <div style="font-size:.75rem;color:#FF9800;margin-bottom:4px">從出庫單帶入</div>
                <?php endif; ?>
                <input type="hidden" name="materials[<?= $idx ?>][material_type]" value="equipment">
                <div class="form-row">
                    <div class="form-group" style="flex:2;position:relative">
                        <label>品名</label>
                        <input type="text" name="materials[<?= $idx ?>][material_name]" class="form-control material-name-input"
                               value="<?= e($m['material_name'] ?? $m['product_name'] ?? '') ?>" placeholder="輸入關鍵字搜尋產品..."
                               autocomplete="off" oninput="searchProduct(this, <?= $idx ?>)"<?= $fromStockOut ? ' readonly style="background:#f5f5f5"' : '' ?>>
                        <input type="hidden" name="materials[<?= $idx ?>][product_id]" value="<?= e($m['product_id'] ?? '') ?>">
                        <div class="product-suggestions" id="suggestions-<?= $idx ?>" style="display:none"></div>
                    </div>
                    <div class="form-group" style="max-width:70px">
                        <label>單位</label>
                        <input type="text" name="materials[<?= $idx ?>][unit]" class="form-control" value="<?= e($m['unit'] ?? '') ?>" placeholder="個"<?= $fromStockOut ? ' readonly style="background:#f5f5f5"' : '' ?>>
                    </div>
                    <div class="form-group">
                        <label>出庫數量</label>
                        <input type="number" name="materials[<?= $idx ?>][shipped_qty]" class="form-control shipped-qty" step="0.1" min="0" value="<?= e($m['shipped_qty'] ?? '') ?>"<?= $fromStockOut ? ' readonly style="background:#f5f5f5"' : '' ?> data-idx="<?= $idx ?>">
                    </div>
                    <div class="form-group">
                        <label>安裝數量</label>
                        <input type="number" name="materials[<?= $idx ?>][used_qty]" class="form-control used-qty" step="0.1" min="0" value="<?= e($m['used_qty'] ?? '') ?>" oninput="calcReturn(<?= $idx ?>)" data-idx="<?= $idx ?>">
                    </div>
                    <div class="form-group">
                        <label>餘料數量</label>
                        <input type="number" name="materials[<?= $idx ?>][returned_qty]" class="form-control returned-qty" step="0.1" min="0" value="<?= $returnQty > 0 ? $returnQty : e($m['returned_qty'] ?? '') ?>" readonly style="background:#f5f5f5" data-idx="<?= $idx ?>">
                    </div>
                    <div class="form-group" style="max-width:90px">
                        <label>單價</label>
                        <input type="number" name="materials[<?= $idx ?>][unit_cost]" class="form-control" step="1" min="0" value="<?= e($m['unit_cost'] ?? '') ?>">
                    </div>
                    <div class="form-group" style="align-self:flex-end;flex:0">
                        <?php if (!$fromStockOut): ?>
                        <button type="button" class="btn btn-danger btn-sm" onclick="this.closest('.material-row').remove()">X</button>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="return-warning" id="return-warn-<?= $idx ?>" style="display:none;font-size:.8rem;color:#e65100;background:#fff3e0;padding:4px 8px;border-radius:4px;margin-top:4px"></div>
            </div>
            <?php endforeach; ?>
            <?php if (empty($equipMaterials)): ?>
            <p class="text-muted" style="font-size:.85rem;padding:8px 0">尚無器材項目</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- 耗材 -->
    <div class="card">
        <div class="card-header d-flex justify-between align-center">
            <span>耗材使用</span>
            <button type="button" class="btn btn-outline btn-sm" onclick="addMaterial('consumable')">+ 新增耗材</button>
        </div>
        <div id="consumableContainer">
            <?php foreach ($consumMaterials as $m):
                $idx = $globalIdx++;
                $fromStockOut = !empty($m['_from_stock_out']);
                $shippedQty = (float)($m['shipped_qty'] ?? 0);
                $usedQty = (float)($m['used_qty'] ?? 0);
                $returnQty = ($shippedQty > 0 && $usedQty > 0) ? ($shippedQty - $usedQty) : 0;
            ?>
            <div class="material-row" data-index="<?= $idx ?>" style="margin-bottom:8px;border-bottom:1px solid #eee;padding-bottom:8px">
                <input type="hidden" name="materials[<?= $idx ?>][material_type]" value="consumable">
                <div class="form-row">
                    <div class="form-group" style="flex:2;position:relative">
                        <label>品名</label>
                        <input type="text" name="materials[<?= $idx ?>][material_name]" class="form-control material-name-input"
                               value="<?= e($m['material_name'] ?? $m['product_name'] ?? '') ?>" placeholder="輸入關鍵字搜尋產品..."
                               autocomplete="off" oninput="searchProduct(this, <?= $idx ?>)">
                        <input type="hidden" name="materials[<?= $idx ?>][product_id]" value="<?= e($m['product_id'] ?? '') ?>">
                        <div class="product-suggestions" id="suggestions-<?= $idx ?>" style="display:none"></div>
                    </div>
                    <div class="form-group" style="max-width:70px">
                        <label>單位</label>
                        <input type="text" name="materials[<?= $idx ?>][unit]" class="form-control" value="<?= e($m['unit'] ?? '') ?>" placeholder="個">
                    </div>
                    <div class="form-group">
                        <label>使用數量</label>
                        <input type="number" name="materials[<?= $idx ?>][used_qty]" class="form-control used-qty" step="0.1" min="0" value="<?= e($m['used_qty'] ?? '') ?>" data-idx="<?= $idx ?>">
                        <input type="hidden" name="materials[<?= $idx ?>][shipped_qty]" value="0">
                        <input type="hidden" name="materials[<?= $idx ?>][returned_qty]" value="0">
                    </div>
                    <div class="form-group" style="max-width:90px">
                        <label>單價</label>
                        <input type="number" name="materials[<?= $idx ?>][unit_cost]" class="form-control" step="1" min="0" value="<?= e($m['unit_cost'] ?? '') ?>">
                    </div>
                    <div class="form-group" style="align-self:flex-end;flex:0">
                        <button type="button" class="btn btn-danger btn-sm" onclick="this.closest('.material-row').remove()">X</button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            <?php if (empty($consumMaterials)): ?>
            <p class="text-muted" style="font-size:.85rem;padding:8px 0">尚無耗材項目</p>
            <?php endif; ?>
        </div>
    </div>

    <button type="submit" class="btn btn-primary btn-block mt-1" id="wlrSubmitBtn">儲存回報</button>
</form>
<script>
document.querySelector('form[method="POST"][enctype]').addEventListener('submit', function(e) {
    var fileInputs = this.querySelectorAll('input[name="photos[]"]');
    var hasFiles = false;
    fileInputs.forEach(function(fi) { if (fi.files.length) hasFiles = true; });
    if (!hasFiles) return;
    e.preventDefault();
    var form = this;
    var btn = document.getElementById('wlrSubmitBtn');
    btn.disabled = true;
    btn.textContent = '壓縮上傳中...';
    var allFiles = [];
    fileInputs.forEach(function(fi) {
        for (var i = 0; i < fi.files.length; i++) allFiles.push(fi.files[i]);
    });
    compressImages(allFiles).then(function(compressed) {
        var fd = new FormData(form);
        fd.delete('photos[]');
        for (var i = 0; i < compressed.length; i++) {
            fd.append('photos[]', compressed[i]);
        }
        var xhr = new XMLHttpRequest();
        xhr.open('POST', form.action || window.location.href);
        xhr.onload = function() { location.reload(); };
        xhr.send(fd);
    });
});
</script>

<!-- 照片預覽彈窗 -->
<div id="photoModal" class="modal-overlay hidden" onclick="if(event.target===this)this.classList.add('hidden')">
    <div style="max-width:90vw;max-height:90vh;position:relative">
        <img id="photoModalImg" src="" style="max-width:90vw;max-height:85vh;border-radius:8px">
        <span class="modal-close" onclick="document.getElementById('photoModal').classList.add('hidden')" style="position:absolute;top:-10px;right:-10px;background:#fff;border-radius:50%;width:30px;height:30px;display:flex;align-items:center;justify-content:center;cursor:pointer;box-shadow:0 2px 8px rgba(0,0,0,.3)">&times;</span>
    </div>
</div>

<style>
.worklog-time-display { display: flex; gap: 20px; font-size: .9rem; flex-wrap: wrap; }
.material-row {
    border: 1px solid var(--gray-200); border-radius: var(--radius);
    padding: 10px; margin-bottom: 8px;
}
.form-row { display: flex; flex-wrap: wrap; gap: 8px; }
.form-row .form-group { flex: 1; min-width: 80px; }
.checkbox-label { display: flex; align-items: center; gap: 6px; cursor: pointer; }
.checkbox-label input[type="checkbox"] { width: 18px; height: 18px; }

/* 照片方格上傳 */
.photo-grid-upload { display: grid; grid-template-columns: repeat(auto-fill, minmax(100px, 1fr)); gap: 8px; }
.photo-grid-item {
    position: relative; width: 100%; padding-top: 100%;
    border-radius: var(--radius); overflow: hidden;
    border: 1px solid var(--gray-200);
}
.photo-grid-item img {
    position: absolute; top: 0; left: 0; width: 100%; height: 100%;
    object-fit: cover; cursor: pointer;
}
.photo-grid-delete {
    position: absolute; top: 4px; right: 4px;
    background: rgba(0,0,0,.6); color: #fff; border: none;
    width: 22px; height: 22px; border-radius: 50%; cursor: pointer;
    font-size: .9rem; display: flex; align-items: center; justify-content: center;
}
.photo-grid-delete:hover { background: var(--danger); }
.photo-grid-add {
    display: flex; align-items: center; justify-content: center;
    width: 100%; padding-top: 100%; position: relative;
    border: 2px dashed var(--gray-300); border-radius: var(--radius);
    cursor: pointer; color: var(--gray-500); font-size: .85rem;
}
.photo-grid-add:hover { border-color: var(--primary); color: var(--primary); }
.photo-grid-add span {
    position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%);
}
.photo-preview-item {
    position: relative; width: 100%; padding-top: 100%;
    border-radius: var(--radius); overflow: hidden;
    border: 2px solid var(--success);
}
.photo-preview-item img {
    position: absolute; top: 0; left: 0; width: 100%; height: 100%;
    object-fit: cover;
}

/* 產品搜尋建議 */
.product-suggestions {
    position: absolute; z-index: 100; background: #fff;
    border: 1px solid var(--gray-300); border-radius: var(--radius);
    max-height: 200px; overflow-y: auto; width: 100%;
    box-shadow: var(--shadow);
}
.product-suggestion-item {
    padding: 6px 10px; cursor: pointer; font-size: .85rem;
    border-bottom: 1px solid var(--gray-100);
}
.product-suggestion-item:hover { background: var(--gray-50); }
.product-suggestion-item .text-muted { font-size: .75rem; }

.form-group { position: relative; }
</style>

<script>
var materialIndex = <?= count($materials) ?>;
var searchTimeout = null;

function addMaterial(type) {
    var i = materialIndex;
    var html = '';
    if (type === 'consumable') {
        html = '<div class="material-row" data-index="' + i + '" style="margin-bottom:8px;border-bottom:1px solid #eee;padding-bottom:8px">' +
            '<input type="hidden" name="materials[' + i + '][material_type]" value="consumable">' +
            '<div class="form-row">' +
            '<div class="form-group" style="flex:2;position:relative"><label>品名</label><input type="text" name="materials[' + i + '][material_name]" class="form-control material-name-input" placeholder="輸入關鍵字搜尋產品..." autocomplete="off" oninput="searchProduct(this,' + i + ')"><input type="hidden" name="materials[' + i + '][product_id]" value=""><div class="product-suggestions" id="suggestions-' + i + '" style="display:none"></div></div>' +
            '<div class="form-group" style="max-width:70px"><label>單位</label><input type="text" name="materials[' + i + '][unit]" class="form-control" placeholder="個"></div>' +
            '<div class="form-group"><label>使用數量</label><input type="number" name="materials[' + i + '][used_qty]" class="form-control used-qty" step="0.1" min="0" data-idx="' + i + '"><input type="hidden" name="materials[' + i + '][shipped_qty]" value="0"><input type="hidden" name="materials[' + i + '][returned_qty]" value="0"></div>' +
            '<div class="form-group" style="max-width:90px"><label>單價</label><input type="number" name="materials[' + i + '][unit_cost]" class="form-control" step="1" min="0"></div>' +
            '<div class="form-group" style="align-self:flex-end;flex:0"><button type="button" class="btn btn-danger btn-sm" onclick="this.closest(\'.material-row\').remove()">X</button></div>' +
            '</div></div>';
        document.getElementById('consumableContainer').insertAdjacentHTML('beforeend', html);
    } else {
        html = '<div class="material-row" data-index="' + i + '" style="margin-bottom:8px;border-bottom:1px solid #eee;padding-bottom:8px">' +
            '<input type="hidden" name="materials[' + i + '][material_type]" value="equipment">' +
            '<div class="form-row">' +
            '<div class="form-group" style="flex:2;position:relative"><label>品名</label><input type="text" name="materials[' + i + '][material_name]" class="form-control material-name-input" placeholder="輸入關鍵字搜尋產品..." autocomplete="off" oninput="searchProduct(this,' + i + ')"><input type="hidden" name="materials[' + i + '][product_id]" value=""><div class="product-suggestions" id="suggestions-' + i + '" style="display:none"></div></div>' +
            '<div class="form-group" style="max-width:70px"><label>單位</label><input type="text" name="materials[' + i + '][unit]" class="form-control" placeholder="個"></div>' +
            '<div class="form-group"><label>出庫數量</label><input type="number" name="materials[' + i + '][shipped_qty]" class="form-control shipped-qty" step="0.1" min="0" data-idx="' + i + '"></div>' +
            '<div class="form-group"><label>安裝數量</label><input type="number" name="materials[' + i + '][used_qty]" class="form-control used-qty" step="0.1" min="0" oninput="calcReturn(' + i + ')" data-idx="' + i + '"></div>' +
            '<div class="form-group"><label>餘料數量</label><input type="number" name="materials[' + i + '][returned_qty]" class="form-control returned-qty" step="0.1" min="0" readonly style="background:#f5f5f5" data-idx="' + i + '"><div class="return-warning" id="return-warn-' + i + '" style="display:none;font-size:.8rem;color:#e65100;background:#fff3e0;padding:4px 8px;border-radius:4px;margin-top:4px"></div></div>' +
            '<div class="form-group" style="max-width:90px"><label>單價</label><input type="number" name="materials[' + i + '][unit_cost]" class="form-control" step="1" min="0"></div>' +
            '<div class="form-group" style="align-self:flex-end;flex:0"><button type="button" class="btn btn-danger btn-sm" onclick="this.closest(\'.material-row\').remove()">X</button></div>' +
            '</div>' +
            '<div class="return-warning" id="return-warn-' + i + '" style="display:none;font-size:.8rem;color:#e65100;background:#fff3e0;padding:4px 8px;border-radius:4px;margin-top:4px"></div>' +
            '</div>';
        document.getElementById('equipmentContainer').insertAdjacentHTML('beforeend', html);
    }
    materialIndex++;
}

// 自動計算餘料數量 = 出庫 - 安裝
function calcReturn(idx) {
    var row = document.querySelector('.material-row[data-index="' + idx + '"]');
    if (!row) return;
    var shipped = parseFloat(row.querySelector('.shipped-qty').value) || 0;
    var used = parseFloat(row.querySelector('.used-qty').value) || 0;
    var retInput = row.querySelector('.returned-qty');
    var warn = document.getElementById('return-warn-' + idx);
    var ret = shipped - used;
    if (ret < 0) ret = 0;
    retInput.value = ret > 0 ? ret : '';
    if (warn) {
        if (ret > 0) {
            var unit = row.querySelector('input[name*="[unit]"]');
            warn.textContent = '需繳回 ' + ret + ' ' + (unit ? unit.value : '');
            warn.style.display = 'block';
        } else {
            warn.style.display = 'none';
        }
    }
}

function searchProduct(input, idx) {
    var keyword = input.value.trim();
    var sugBox = document.getElementById('suggestions-' + idx);
    if (keyword.length < 2) { sugBox.style.display = 'none'; return; }

    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(function() {
        fetch('/products.php?action=ajax_search&keyword=' + encodeURIComponent(keyword))
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (!data.data || data.data.length === 0) { sugBox.style.display = 'none'; return; }
            var html = '';
            data.data.forEach(function(p) {
                html += '<div class="product-suggestion-item" onclick="selectProduct(' + idx + ',' + p.id + ',\'' + p.name.replace(/'/g, "\\'") + '\',\'' + (p.unit || '').replace(/'/g, "\\'") + '\',' + (p.price || 0) + ')">' +
                    '<div>' + p.name + '</div>' +
                    '<div class="text-muted">' + (p.model_number || '') + ' | $' + (p.price || 0) + '</div></div>';
            });
            sugBox.innerHTML = html;
            sugBox.style.display = 'block';
        });
    }, 300);
}

function selectProduct(idx, productId, name, unit, price) {
    var row = document.querySelector('.material-row[data-index="' + idx + '"]');
    row.querySelector('[name="materials[' + idx + '][material_name]"]').value = name;
    row.querySelector('[name="materials[' + idx + '][product_id]"]').value = productId;
    if (unit) row.querySelector('[name="materials[' + idx + '][unit]"]').value = unit;
    if (price) row.querySelector('[name="materials[' + idx + '][unit_cost]"]').value = price;
    document.getElementById('suggestions-' + idx).style.display = 'none';
}

// 點擊外部關閉建議
document.addEventListener('click', function(e) {
    if (!e.target.classList.contains('material-name-input')) {
        document.querySelectorAll('.product-suggestions').forEach(function(el) { el.style.display = 'none'; });
    }
});

function openPhotoModal(src) {
    document.getElementById('photoModalImg').src = src;
    document.getElementById('photoModal').classList.remove('hidden');
}

function deletePhoto(photoId) {
    if (!confirm('確定刪除此照片?')) return;
    fetch('/worklog.php?action=delete_photo', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({photo_id: photoId})
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) {
            var el = document.getElementById('photo-' + photoId);
            if (el) el.remove();
        } else {
            alert(data.error || '刪除失敗');
        }
    });
}

// 照片預覽
function previewPhotos(input) {
    var container = document.getElementById('photoPreviewContainer');
    container.innerHTML = '';
    if (!input.files) return;
    for (var i = 0; i < input.files.length; i++) {
        (function(file) {
            var reader = new FileReader();
            reader.onload = function(e) {
                var div = document.createElement('div');
                div.className = 'photo-preview-item';
                div.innerHTML = '<img src="' + e.target.result + '">';
                container.appendChild(div);
            };
            reader.readAsDataURL(file);
        })(input.files[i]);
    }
}

// 工時計算
function calcWorkHours() {
    var a = document.getElementById('arrivalTimeInput').value;
    var d = document.getElementById('departureTimeInput').value;
    var display = document.getElementById('workHoursDisplay');
    if (!a || !d) { display.textContent = ''; return; }
    var ap = a.split(':'), dp = d.split(':');
    var mins = (parseInt(dp[0]) * 60 + parseInt(dp[1])) - (parseInt(ap[0]) * 60 + parseInt(ap[1]));
    if (mins < 0) mins += 1440;
    var h = Math.floor(mins / 60), m = mins % 60;
    display.textContent = '工時: ' + h + '時' + m + '分';
}
document.getElementById('arrivalTimeInput').addEventListener('change', calcWorkHours);
document.getElementById('departureTimeInput').addEventListener('change', calcWorkHours);
calcWorkHours();
</script>
