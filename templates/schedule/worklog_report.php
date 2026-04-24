<?php
$_wlCanDelete = ($worklog['user_id'] == Auth::id())
    || Auth::hasPermission('schedule.manage')
    || Auth::hasPermission('all');
$_fromCaseId = isset($_GET['from_case']) ? (int)$_GET['from_case'] : 0;
$_fromScheduleId = isset($_GET['from_schedule']) ? (int)$_GET['from_schedule'] : 0;
?>
<div class="d-flex justify-between align-center mb-2">
    <h2>施工回報</h2>
    <div class="d-flex gap-1">
        <?php if (!empty($worklog['schedule_id'])): ?>
        <a href="/schedule.php?action=view&id=<?= $worklog['schedule_id'] ?>" class="btn btn-outline btn-sm">返回排工</a>
        <?php endif; ?>
        <a href="/worklog.php?action=history" class="btn btn-outline btn-sm">歷史記錄</a>
        <a href="/worklog.php" class="btn btn-outline btn-sm">今日施工</a>
        <?php if ($_wlCanDelete): ?>
        <form method="POST" action="/worklog.php?action=delete" style="display:inline" onsubmit="return confirm('確定刪除此筆施工回報？照片、材料使用、案件紀錄將一併移除，且無法復原。');">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int)$worklog['id'] ?>">
            <?php if ($_fromCaseId): ?><input type="hidden" name="from_case" value="<?= $_fromCaseId ?>"><?php endif; ?>
            <?php if ($_fromScheduleId): ?><input type="hidden" name="from_schedule" value="<?= $_fromScheduleId ?>"><?php endif; ?>
            <button type="submit" class="btn btn-danger btn-sm">刪除</button>
        </form>
        <?php endif; ?>
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
// 同案件前次施工紀錄（排除當前這筆、排除空白內容）
$prevWorklogs = array();
if (!empty($worklog['case_id'])) {
    try {
        $_pwStmt = Database::getInstance()->prepare("
            SELECT wl.id, wl.work_description, wl.issues, wl.next_visit_note,
                   wl.is_completed, wl.payment_amount, wl.payment_method,
                   s.schedule_date, u.real_name
            FROM work_logs wl
            JOIN schedules s ON wl.schedule_id = s.id
            LEFT JOIN users u ON wl.user_id = u.id
            WHERE s.case_id = ?
              AND wl.id <> ?
              AND wl.work_description IS NOT NULL AND wl.work_description <> ''
            ORDER BY s.schedule_date DESC, wl.id DESC
            LIMIT 20
        ");
        $_pwStmt->execute(array((int)$worklog['case_id'], (int)$worklog['id']));
        $prevWorklogs = $_pwStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) { $prevWorklogs = array(); }
}
?>
<?php if (!empty($prevWorklogs)): ?>
<div class="card" style="border-left:4px solid #1565c0;background:#f5f9ff">
    <div class="card-header d-flex justify-between align-center" style="padding:8px 12px">
        <span>📋 前次施工紀錄（同案件，共 <?= count($prevWorklogs) ?> 筆）</span>
        <button type="button" class="btn btn-outline btn-xs" onclick="togglePrevLogs(this)" style="font-size:.75rem">收合</button>
    </div>
    <div id="prevLogsBody" style="padding:4px 12px 8px">
        <?php foreach ($prevWorklogs as $pw): ?>
        <div style="border-top:1px dashed #cbd5e1;padding:8px 0">
            <div style="font-size:.82rem;color:#555;margin-bottom:4px">
                <strong><?= e($pw['schedule_date']) ?></strong>
                <?php if (!empty($pw['real_name'])): ?><span class="text-muted" style="margin-left:6px"><?= e($pw['real_name']) ?></span><?php endif; ?>
                <?php if (!empty($pw['is_completed'])): ?><span class="badge badge-success" style="font-size:.7rem;margin-left:6px">已完工</span><?php endif; ?>
                <?php if (!empty($pw['payment_amount'])): ?><span class="badge" style="background:#e8f5e9;color:#2e7d32;font-size:.7rem;margin-left:6px">收款 $<?= number_format($pw['payment_amount']) ?></span><?php endif; ?>
            </div>
            <div style="white-space:pre-line;font-size:.88rem;line-height:1.5"><?= e($pw['work_description']) ?></div>
            <?php if (!empty($pw['issues'])): ?>
            <div style="margin-top:4px;padding:4px 8px;background:#fff3e0;border-radius:4px;font-size:.82rem;color:#e65100"><strong>問題：</strong><?= e($pw['issues']) ?></div>
            <?php endif; ?>
            <?php if (!empty($pw['next_visit_note'])): ?>
            <div style="margin-top:4px;padding:4px 8px;background:#fff8e1;border-radius:4px;font-size:.82rem;color:#bf360c"><strong>下次備註：</strong><?= e($pw['next_visit_note']) ?></div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<script>
function togglePrevLogs(btn) {
    var body = document.getElementById('prevLogsBody');
    if (body.style.display === 'none') { body.style.display = ''; btn.textContent = '收合'; }
    else { body.style.display = 'none'; btn.textContent = '展開'; }
}
</script>
<?php endif; ?>

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
                <img src="<?= e($photo['file_path']) ?>" alt="<?= e($photo['caption'] ?? '') ?>" onclick="location.href='/photo_view.php?src='+encodeURIComponent(this.src)">
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
    // 繼承同排工其他工程師已填的 materials（X 方案：誰先填誰當基準）
    $_inheritedFrom = '';
    if (empty($materials) && !empty($siblingMaterials)) {
        $materials = array();
        foreach ($siblingMaterials as $sm) {
            $materials[] = array(
                'material_type' => $sm['material_type'] ?? 'equipment',
                'product_id'    => $sm['product_id'] ?? '',
                'material_name' => $sm['material_name'] ?? ($sm['product_name'] ?? ''),
                'unit'          => $sm['unit'] ?? '',
                'shipped_qty'   => $sm['shipped_qty'] ?? '',
                'used_qty'      => $sm['used_qty'] ?? '',
                'returned_qty'  => $sm['returned_qty'] ?? '',
                'unit_cost'     => $sm['unit_cost'] ?? '',
                'material_note' => $sm['note'] ?? '',
                '_from_sibling' => true,
            );
        }
        $_inheritedFrom = isset($siblingFromName) ? $siblingFromName : '';
    }
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

    <?php if (!empty($_inheritedFrom)): ?>
    <div style="background:#e8f5e9;padding:10px 14px;margin:8px 0;border-left:3px solid #2e7d32;border-radius:4px;font-size:.88rem;color:#1b5e20">
        📋 已帶入 <strong><?= e($_inheritedFrom) ?></strong> 填的器材/耗材資料，你可以直接儲存確認，或修改數量/新增品項。
    </div>
    <?php endif; ?>

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
        btn.textContent = '上傳中...';
        var xhr = new XMLHttpRequest();
        xhr.open('POST', form.action || window.location.href);
        xhr.upload.onprogress = function(e) {
            if (e.lengthComputable) {
                var pct = Math.round(e.loaded / e.total * 100);
                btn.textContent = '上傳中 ' + pct + '%...';
            }
        };
        xhr.onload = function() {
            btn.textContent = '儲存成功 ✓';
            btn.style.background = '#22c55e';
            setTimeout(function() { location.reload(); }, 800);
        };
        xhr.onerror = function() {
            btn.disabled = false;
            btn.textContent = '儲存回報';
            alert('上傳失敗，請檢查網路連線後重試');
        };
        xhr.ontimeout = function() {
            btn.disabled = false;
            btn.textContent = '儲存回報';
            alert('上傳逾時，照片可能太大，請減少張數後重試');
        };
        xhr.timeout = 120000; // 2 分鐘逾時
        xhr.send(fd);
    }).catch(function() {
        btn.disabled = false;
        btn.textContent = '儲存回報';
        alert('圖片壓縮失敗，請重試');
    });
});
</script>

<!-- 照片預覽彈窗（支援雙指縮放+拖曳） -->
<div id="photoModal" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,.92);z-index:10000">
    <div id="photoModalWrap" style="width:100%;height:100%;overflow:hidden;touch-action:none">
        <img id="photoModalImg" src="" style="display:block;border-radius:4px">
    </div>
    <span onclick="closePhotoModal()" style="position:fixed;top:12px;right:16px;color:#fff;font-size:2rem;cursor:pointer;z-index:10001;background:rgba(0,0,0,.5);border-radius:50%;width:40px;height:40px;display:flex;align-items:center;justify-content:center;line-height:1">&times;</span>
    <div style="position:fixed;bottom:16px;left:0;right:0;text-align:center;color:rgba(255,255,255,.5);font-size:.75rem;z-index:10001">雙指縮放 · 單指拖曳 · 雙擊還原</div>
</div>
<script>
(function() {
    var modal = document.getElementById('photoModal');
    var wrap = document.getElementById('photoModalWrap');
    var img = document.getElementById('photoModalImg');
    var S = 1, TX = 0, TY = 0; // scale, translateX, translateY
    var imgW = 0, imgH = 0;

    window.openPhotoModal = function(src) {
        S = 1; TX = 0; TY = 0;
        img.src = src;
        modal.style.display = 'block';
        document.body.style.overflow = 'hidden';
        img.onload = function() {
            imgW = Math.min(img.naturalWidth, window.innerWidth * 0.94);
            imgH = imgW / img.naturalWidth * img.naturalHeight;
            if (imgH > window.innerHeight * 0.88) { imgH = window.innerHeight * 0.88; imgW = imgH / img.naturalHeight * img.naturalWidth; }
            img.style.width = imgW + 'px';
            img.style.height = imgH + 'px';
            TX = (window.innerWidth - imgW) / 2;
            TY = (window.innerHeight - imgH) / 2;
            apply();
        };
    };
    window.closePhotoModal = function() {
        modal.style.display = 'none';
        document.body.style.overflow = '';
        img.src = '';
    };
    function apply() {
        img.style.transform = 'translate3d(' + TX + 'px,' + TY + 'px,0) scale(' + S + ')';
        img.style.transformOrigin = '0 0';
    }
    function dist(a, b) { return Math.hypot(a.clientX - b.clientX, a.clientY - b.clientY); }

    var t0 = {}, pinching = false, dragging = false;

    wrap.addEventListener('touchstart', function(e) {
        e.preventDefault();
        if (e.touches.length === 2) {
            pinching = true; dragging = false;
            t0.dist = dist(e.touches[0], e.touches[1]);
            t0.scale = S;
            t0.mx = (e.touches[0].clientX + e.touches[1].clientX) / 2;
            t0.my = (e.touches[0].clientY + e.touches[1].clientY) / 2;
            t0.tx = TX; t0.ty = TY;
        } else if (e.touches.length === 1) {
            dragging = true; pinching = false;
            t0.x = e.touches[0].clientX; t0.y = e.touches[0].clientY;
            t0.tx = TX; t0.ty = TY;
        }
    }, { passive: false });

    wrap.addEventListener('touchmove', function(e) {
        e.preventDefault();
        if (pinching && e.touches.length === 2) {
            var d = dist(e.touches[0], e.touches[1]);
            S = Math.max(0.5, Math.min(10, t0.scale * d / t0.dist));
            var mx = (e.touches[0].clientX + e.touches[1].clientX) / 2;
            var my = (e.touches[0].clientY + e.touches[1].clientY) / 2;
            TX = t0.tx + (mx - t0.mx);
            TY = t0.ty + (my - t0.my);
            apply();
        } else if (dragging && e.touches.length === 1) {
            TX = t0.tx + (e.touches[0].clientX - t0.x);
            TY = t0.ty + (e.touches[0].clientY - t0.y);
            apply();
        }
    }, { passive: false });

    wrap.addEventListener('touchend', function(e) {
        if (e.touches.length === 0) { pinching = false; dragging = false; }
        else if (e.touches.length === 1) { pinching = false; }
    });

    // 雙擊還原 or 放大2x
    var lastTap = 0;
    wrap.addEventListener('click', function(e) {
        if (e.target === wrap) { closePhotoModal(); return; }
        var now = Date.now();
        if (now - lastTap < 300) {
            if (S > 1.2) { S = 1; TX = (window.innerWidth - imgW) / 2; TY = (window.innerHeight - imgH) / 2; }
            else { S = 2.5; TX = window.innerWidth / 2 - e.clientX * 2.5 + e.clientX; TY = window.innerHeight / 2 - e.clientY * 2.5 + e.clientY; }
            apply();
        }
        lastTap = now;
    });
})();
</script>

<style>
.worklog-time-display { display: flex; gap: 20px; font-size: .9rem; flex-wrap: wrap; }
.material-row {
    border: 1px solid var(--gray-200); border-radius: var(--radius);
    padding: 10px; margin-bottom: 8px;
}
.form-row { display: flex; flex-wrap: wrap; gap: 8px; }
.form-row .form-group { flex: 1; min-width: 80px; }
/* 手機隱藏單價欄位 */
@media (max-width: 767px) {
    .form-group:has(> input[name$="[unit_cost]"]) { display: none; }
}
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
