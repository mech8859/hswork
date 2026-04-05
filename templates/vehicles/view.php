<?php
$typeLabels = VehicleModel::typeLabels();
$canManage = Auth::hasPermission('staff.manage');
?>

<div class="d-flex justify-between align-center flex-wrap gap-1 mb-2">
    <div>
        <h2><?= e($vehicle['plate_number']) ?></h2>
        <span class="badge <?= $vehicle['vehicle_type'] === 'truck' ? 'badge-warning' : ($vehicle['vehicle_type'] === 'van' ? 'badge-primary' : 'badge-success') ?>"><?= VehicleModel::typeLabel($vehicle['vehicle_type']) ?></span>
        <span class="text-muted"><?= e($vehicle['branch_name'] ?: '') ?></span>
    </div>
    <div class="d-flex gap-1 flex-wrap">
        <?php if ($canManage): ?>
        <a href="/vehicles.php?action=edit&id=<?= $vehicle['id'] ?>" class="btn btn-primary btn-sm">編輯</a>
        <?php endif; ?>
        <?= back_button('/vehicles.php') ?>
    </div>
</div>

<!-- 基本資料 -->
<div class="card">
    <div class="card-header">基本資料</div>
    <div class="detail-grid">
        <div class="detail-item"><span class="detail-label">車牌號碼</span><span class="detail-value"><?= e($vehicle['plate_number']) ?></span></div>
        <div class="detail-item"><span class="detail-label">車輛類型</span><span class="detail-value"><?= VehicleModel::typeLabel($vehicle['vehicle_type']) ?></span></div>
        <div class="detail-item"><span class="detail-label">品牌</span><span class="detail-value"><?= e($vehicle['brand'] ?: '-') ?></span></div>
        <div class="detail-item"><span class="detail-label">型號</span><span class="detail-value"><?= e($vehicle['model'] ?: '-') ?></span></div>
        <div class="detail-item"><span class="detail-label">出廠年份</span><span class="detail-value"><?= e($vehicle['year'] ?: '-') ?></span></div>
        <div class="detail-item"><span class="detail-label">顏色</span><span class="detail-value"><?= e($vehicle['color'] ?: '-') ?></span></div>
        <div class="detail-item"><span class="detail-label">保管人</span><span class="detail-value"><?= e($vehicle['custodian_name'] ?: '未指定') ?></span></div>
        <div class="detail-item"><span class="detail-label">所屬分公司</span><span class="detail-value"><?= e($vehicle['branch_name'] ?: '-') ?></span></div>
    </div>
    <?php if ($vehicle['note']): ?>
    <div class="mt-1">
        <span class="detail-label">備註</span>
        <p><?= nl2br(e($vehicle['note'])) ?></p>
    </div>
    <?php endif; ?>
</div>

<!-- 租賃合約 -->
<div class="card">
    <div class="card-header">租賃合約</div>
    <div class="detail-grid">
        <div class="detail-item"><span class="detail-label">車輛編號</span><span class="detail-value"><?= e($vehicle['vehicle_number'] ?: '-') ?></span></div>
        <div class="detail-item"><span class="detail-label">租賃公司</span><span class="detail-value"><?= e($vehicle['leasing_company'] ?: '-') ?></span></div>
        <div class="detail-item"><span class="detail-label">統一編號</span><span class="detail-value"><?= e($vehicle['leasing_tax_id'] ?: '-') ?></span></div>
        <div class="detail-item"><span class="detail-label">聯絡人</span><span class="detail-value"><?= e($vehicle['leasing_contact'] ?: '-') ?></span></div>
        <div class="detail-item"><span class="detail-label">公司電話</span><span class="detail-value"><?= e($vehicle['leasing_phone'] ?: '-') ?></span></div>
        <div class="detail-item"><span class="detail-label">行動電話</span><span class="detail-value"><?= e($vehicle['leasing_mobile'] ?: '-') ?></span></div>
        <div class="detail-item"><span class="detail-label">合約期數</span><span class="detail-value"><?= $vehicle['contract_months'] ? $vehicle['contract_months'] . ' 期' : '-' ?></span></div>
        <div class="detail-item"><span class="detail-label">月租金(含稅)</span><span class="detail-value"><?= $vehicle['monthly_rent'] ? '$' . number_format($vehicle['monthly_rent']) : '-' ?></span></div>
        <div class="detail-item"><span class="detail-label">合約開始日</span><span class="detail-value"><?= e($vehicle['contract_start'] ?: '-') ?></span></div>
        <div class="detail-item">
            <span class="detail-label">合約到期日</span>
            <?php if ($vehicle['contract_end']): ?>
                <?php $cExpired = $vehicle['contract_end'] < date('Y-m-d'); $cSoon = !$cExpired && $vehicle['contract_end'] <= date('Y-m-d', strtotime('+30 days')); ?>
                <span class="detail-value" style="color:<?= $cExpired ? 'var(--danger)' : ($cSoon ? '#E65100' : 'inherit') ?>; font-weight:<?= ($cExpired || $cSoon) ? '600' : 'normal' ?>">
                    <?= e($vehicle['contract_end']) ?>
                    <?php if ($cExpired): ?> (已到期)<?php elseif ($cSoon): ?> (即將到期)<?php endif; ?>
                </span>
            <?php else: ?>
                <span class="detail-value">-</span>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- 保養資訊 -->
<div class="card">
    <div class="card-header">保養資訊</div>
    <div class="detail-grid">
        <div class="detail-item"><span class="detail-label">保養日期</span><span class="detail-value"><?= e($vehicle['last_maintenance_date'] ?: '-') ?></span></div>
        <div class="detail-item"><span class="detail-label">保養里程數</span><span class="detail-value"><?= $vehicle['maintenance_mileage'] ? number_format($vehicle['maintenance_mileage']) . ' km' : '-' ?></span></div>
        <div class="detail-item"><span class="detail-label">下次保養里程數</span><span class="detail-value"><?= $vehicle['next_maintenance_mileage'] ? number_format($vehicle['next_maintenance_mileage']) . ' km' : '-' ?></span></div>
        <div class="detail-item"><span class="detail-label">目前里程</span><span class="detail-value"><?= $vehicle['current_mileage'] ? number_format($vehicle['current_mileage']) . ' km' : '-' ?></span></div>
        <div class="detail-item"><span class="detail-label">保養地址</span><span class="detail-value"><?= e($vehicle['maintenance_address'] ?: '-') ?></span></div>
        <div class="detail-item"><span class="detail-label">聯絡窗口</span><span class="detail-value"><?= nl2br(e($vehicle['maintenance_contact'] ?: '-')) ?></span></div>
    </div>
</div>

<!-- 驗車資訊 -->
<div class="card">
    <div class="card-header">驗車資訊</div>
    <div class="detail-grid">
        <div class="detail-item">
            <span class="detail-label">驗車日</span>
            <?php if ($vehicle['inspection_date']): ?>
                <?php $iExpired = $vehicle['inspection_date'] < date('Y-m-d'); $iSoon = !$iExpired && $vehicle['inspection_date'] <= date('Y-m-d', strtotime('+30 days')); ?>
                <span class="detail-value" style="color:<?= $iExpired ? 'var(--danger)' : ($iSoon ? '#E65100' : 'inherit') ?>; font-weight:<?= ($iExpired || $iSoon) ? '600' : 'normal' ?>">
                    <?= e($vehicle['inspection_date']) ?>
                    <?php if ($iExpired): ?> (已逾期)<?php elseif ($iSoon): ?> (即將到期)<?php endif; ?>
                </span>
            <?php else: ?>
                <span class="detail-value">-</span>
            <?php endif; ?>
        </div>
        <div class="detail-item"><span class="detail-label">驗車窗口</span><span class="detail-value"><?= e($vehicle['inspection_contact'] ?: '-') ?></span></div>
    </div>
</div>

<!-- 車輛配置工具 -->
<div class="card">
    <div class="card-header">車輛配置工具</div>
    <?php if (!empty($vehicle['tools'])): ?>
    <div class="table-responsive">
        <table class="table">
            <thead><tr><th>工具名稱</th><th style="width:80px" class="text-right">數量</th><th>備註</th></tr></thead>
            <tbody>
                <?php foreach ($vehicle['tools'] as $tool): ?>
                <tr>
                    <td><?= e($tool['tool_name']) ?></td>
                    <td class="text-right"><?= (int)$tool['quantity'] ?></td>
                    <td class="text-muted"><?= e($tool['note'] ?: '') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <p class="text-muted text-center" style="padding:12px">無工具配備記錄</p>
    <?php endif; ?>
</div>

<!-- 保養紀錄 -->
<div class="card">
    <div class="card-header d-flex justify-between align-center">
        <span>保養紀錄</span>
        <?php if ($canManage): ?>
        <button type="button" class="btn btn-primary btn-sm" onclick="showMaintModal()">+ 新增保養</button>
        <?php endif; ?>
    </div>
    <div id="maintList">
    <?php if (!empty($maintenanceHistory)): ?>
        <?php foreach ($maintenanceHistory as $m): ?>
        <div class="maint-item">
            <div class="d-flex justify-between align-center">
                <div>
                    <strong><?= e($m['maintenance_date']) ?></strong>
                    <span class="badge"><?= e($m['maintenance_type'] === 'regular' ? '定期保養' : ($m['maintenance_type'] === 'repair' ? '維修' : $m['maintenance_type'])) ?></span>
                </div>
                <div class="text-muted" style="font-size:.8rem">
                    <?php if ($m['mileage']): ?>里程: <?= number_format($m['mileage']) ?>km<?php endif; ?>
                    <?php if ($m['cost']): ?> | 費用: $<?= number_format($m['cost']) ?><?php endif; ?>
                </div>
            </div>
            <?php if ($m['description']): ?>
            <div style="font-size:.9rem;margin-top:4px"><?= nl2br(e($m['description'])) ?></div>
            <?php endif; ?>
            <div class="text-muted" style="font-size:.75rem;margin-top:2px">
                建立者: <?= e($m['created_by_name'] ?: '-') ?>
                <?php if ($m['next_date']): ?> | 下次保養: <?= e($m['next_date']) ?><?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    <?php else: ?>
        <p class="text-muted text-center" style="padding:12px" id="noMaint">尚無保養紀錄</p>
    <?php endif; ?>
    </div>
</div>

<!-- 檔案 -->
<div class="card">
    <div class="card-header d-flex justify-between align-center">
        <span>檔案</span>
        <?php if ($canManage): ?>
        <button type="button" class="btn btn-outline btn-sm" onclick="document.getElementById('fileUploadInput').click()">+ 上傳檔案</button>
        <input type="file" id="fileUploadInput" style="display:none" onchange="uploadVehicleFile()" multiple>
        <?php endif; ?>
    </div>
    <div id="fileList">
    <?php if (!empty($vehicle['files'])): ?>
        <?php foreach ($vehicle['files'] as $f): ?>
        <div class="file-item d-flex justify-between align-center" id="vfile-<?= $f['id'] ?>">
            <div>
                <a href="<?= e($f['file_path']) ?>" target="_blank"><?= e($f['file_name']) ?></a>
                <span class="text-muted" style="font-size:.75rem"><?= e($f['uploader_name'] ?: '') ?></span>
            </div>
            <?php if ($canManage): ?>
            <button type="button" class="btn btn-outline btn-sm" onclick="deleteVehicleFile(<?= $f['id'] ?>)" style="color:var(--danger);padding:2px 6px">&times;</button>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    <?php else: ?>
        <p class="text-muted text-center" style="padding:12px" id="noFiles">尚無檔案</p>
    <?php endif; ?>
    </div>
</div>

<!-- 新增保養 Modal -->
<?php if ($canManage): ?>
<div id="maintModal" class="modal-overlay" style="display:none" onclick="if(event.target===this)hideMaintModal()">
    <div class="modal-box">
        <div class="modal-header">
            <h3>新增保養紀錄</h3>
            <button type="button" onclick="hideMaintModal()" style="background:none;border:none;font-size:1.5rem;cursor:pointer">&times;</button>
        </div>
        <div class="form-group">
            <label>保養日期 <span style="color:var(--danger)">*</span></label>
            <input type="date" max="2099-12-31" id="maintDate" class="form-control" value="<?= date('Y-m-d') ?>">
        </div>
        <div class="form-group">
            <label>保養類型</label>
            <select id="maintType" class="form-control">
                <option value="regular">定期保養</option>
                <option value="repair">維修</option>
                <option value="inspection">檢驗</option>
                <option value="other">其他</option>
            </select>
        </div>
        <div class="form-grid" style="gap:8px">
            <div class="form-group">
                <label>保養時里程 (km)</label>
                <input type="number" id="maintMileage" class="form-control" min="0">
            </div>
            <div class="form-group">
                <label>費用</label>
                <input type="number" id="maintCost" class="form-control" min="0">
            </div>
        </div>
        <div class="form-group">
            <label>保養內容</label>
            <textarea id="maintDesc" class="form-control" rows="3"></textarea>
        </div>
        <div class="form-grid" style="gap:8px">
            <div class="form-group">
                <label>下次保養日期</label>
                <input type="date" max="2099-12-31" id="maintNextDate" class="form-control">
            </div>
            <div class="form-group">
                <label>下次保養里程 (km)</label>
                <input type="number" id="maintNextMileage" class="form-control" min="0">
            </div>
        </div>
        <div class="d-flex gap-1 mt-2">
            <button type="button" class="btn btn-primary" onclick="submitMaintenance()">確定新增</button>
            <button type="button" class="btn btn-outline" onclick="hideMaintModal()">取消</button>
        </div>
    </div>
</div>
<?php endif; ?>

<style>
.detail-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 8px; }
.detail-item { display: flex; flex-direction: column; }
.detail-label { font-size: .8rem; color: var(--gray-500); }
.form-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; }
.maint-item { border-bottom: 1px solid var(--gray-200); padding: 10px 0; }
.maint-item:last-child { border-bottom: none; }
.file-item { padding: 8px 0; border-bottom: 1px solid var(--gray-100); }
.file-item:last-child { border-bottom: none; }
.modal-overlay { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,.5); z-index: 1000; display: flex; align-items: center; justify-content: center; }
.modal-box { background: #fff; border-radius: var(--radius); padding: 20px; width: 90%; max-width: 500px; max-height: 80vh; overflow-y: auto; }
.modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
@media (max-width: 767px) { .detail-grid, .form-grid { grid-template-columns: 1fr; } }
</style>

<script>
var vehicleId = <?= $vehicle['id'] ?>;

function showMaintModal() { document.getElementById('maintModal').style.display = 'flex'; }
function hideMaintModal() { document.getElementById('maintModal').style.display = 'none'; }

function submitMaintenance() {
    var date = document.getElementById('maintDate').value;
    if (!date) { alert('請選擇保養日期'); return; }

    var fd = new FormData();
    fd.append('vehicle_id', vehicleId);
    fd.append('maintenance_date', date);
    fd.append('maintenance_type', document.getElementById('maintType').value);
    fd.append('mileage', document.getElementById('maintMileage').value);
    fd.append('cost', document.getElementById('maintCost').value);
    fd.append('description', document.getElementById('maintDesc').value);
    fd.append('next_date', document.getElementById('maintNextDate').value);
    fd.append('next_mileage', document.getElementById('maintNextMileage').value);

    var xhr = new XMLHttpRequest();
    xhr.open('POST', '/vehicles.php?action=add_maintenance');
    xhr.onload = function() {
        if (xhr.status === 200) {
            try {
                var res = JSON.parse(xhr.responseText);
                if (res.success) { location.reload(); } else { alert(res.error || '新增失敗'); }
            } catch(e) { alert('新增失敗'); }
        }
    };
    xhr.send(fd);
}

function uploadVehicleFile() {
    var input = document.getElementById('fileUploadInput');
    if (input.files.length === 0) return;
    for (var i = 0; i < input.files.length; i++) {
        var fd = new FormData();
        fd.append('file', input.files[i]);
        fd.append('file_type', 'other');
        var xhr = new XMLHttpRequest();
        xhr.open('POST', '/vehicles.php?action=upload_file&id=' + vehicleId);
        xhr.onload = function() {
            if (this.status === 200) {
                try {
                    var res = JSON.parse(this.responseText);
                    if (res.success) {
                        var noFiles = document.getElementById('noFiles');
                        if (noFiles) noFiles.remove();
                        var html = '<div class="file-item d-flex justify-between align-center" id="vfile-' + res.file.id + '">' +
                            '<div><a href="' + res.file.file_path + '" target="_blank">' + escHtml(res.file.file_name) + '</a></div>' +
                            '<button type="button" class="btn btn-outline btn-sm" onclick="deleteVehicleFile(' + res.file.id + ')" style="color:var(--danger);padding:2px 6px">&times;</button>' +
                            '</div>';
                        document.getElementById('fileList').insertAdjacentHTML('beforeend', html);
                    } else { alert(res.error || '上傳失敗'); }
                } catch(e) { alert('上傳失敗'); }
            }
        };
        xhr.send(fd);
    }
    input.value = '';
}

function deleteVehicleFile(id) {
    if (!confirm('確定刪除此檔案?')) return;
    var xhr = new XMLHttpRequest();
    xhr.open('POST', '/vehicles.php?action=delete_file');
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onload = function() {
        if (xhr.status === 200) {
            try {
                var res = JSON.parse(xhr.responseText);
                if (res.success) { var el = document.getElementById('vfile-' + id); if (el) el.remove(); }
            } catch(e) {}
        }
    };
    xhr.send('file_id=' + id);
}

function escHtml(s) {
    var d = document.createElement('div');
    d.appendChild(document.createTextNode(s));
    return d.innerHTML;
}
</script>
