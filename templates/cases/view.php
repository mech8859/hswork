<div class="d-flex justify-between align-center flex-wrap gap-1 mb-2">
    <div>
        <h2><?= e($case['case_number']) ?></h2>
        <span class="badge <?= CaseModel::statusBadge($case['status']) ?>"><?= e(CaseModel::statusLabel($case['status'])) ?></span>
        <span class="badge badge-primary"><?= e(CaseModel::typeLabel($case['case_type'])) ?></span>
        <span class="text-muted" style="font-size:.85rem"><?= e($case['branch_name']) ?></span>
        <?php if (!empty($case['support_branches'])): ?>
            <?php foreach ($case['support_branches'] as $sb): ?>
                <span class="badge" style="background:#ede9fe;color:#6366f1;font-size:.78rem">支援：<?= e($sb['branch_name']) ?></span>
            <?php endforeach; ?>
        <?php endif; ?>
        <?php
        $liveReadiness = function_exists('compute_case_readiness_live') ? compute_case_readiness_live($case) : ($case['readiness'] ?: []);
        $warnings = get_readiness_warnings($liveReadiness, $case['case_type'] ?: 'new_install');
        if (!empty($warnings)):
        ?>
        <span style="color:#e65100;font-size:.85rem;font-weight:600;margin-left:12px">排工條件尚未備齊：<?= implode('、', array_map('e', $warnings)) ?></span>
        <?php endif; ?>
    </div>
    <div class="d-flex gap-1">
        <?php if (Auth::hasPermission('schedule.manage') && in_array($case['status'], ['ready','scheduled','in_progress','incomplete'])): ?>
        <a href="/schedule.php?action=create&case_id=<?= $case['id'] ?>" class="btn btn-sm" style="background:#FF9800;color:#fff">手動排工</a>
        <?php if (!empty($warnings)): ?>
        <button type="button" class="btn btn-success btn-sm" onclick="alert('排工條件尚未備齊：<?= implode('、', array_map('e', $warnings)) ?>\n\n請先補齊資料再使用智慧排工。')">智慧排工</button>
        <?php else: ?>
        <a href="/schedule.php?action=smart&case_id=<?= $case['id'] ?>" class="btn btn-success btn-sm">智慧排工</a>
        <?php endif; ?>
        <?php endif; ?>
        <?php if (Auth::hasPermission('cases.manage') || Auth::hasPermission('all')): ?>
        <button type="button" class="btn btn-sm" style="background:#6366f1;color:#fff" onclick="openSupportModal()">支援</button>
        <?php endif; ?>
        <a href="/cases.php?action=edit&id=<?= $case['id'] ?>" class="btn btn-primary btn-sm">編輯</a>
        <?= back_button('/cases.php') ?>
    </div>
</div>

<!-- 基本資料 -->
<div class="card">
    <div class="card-header">基本資料</div>
    <div class="detail-grid">
        <div class="detail-item">
            <span class="detail-label">案件名稱</span>
            <span class="detail-value"><?= e($case['title']) ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">施工地址</span>
            <span class="detail-value"><?= e($case['address'] ?: '-') ?></span>
        </div>
        <?php if (!empty($case['address'])): ?>
        <div class="detail-item" style="grid-column: span 2">
            <span class="detail-label">地圖</span>
            <iframe src="https://maps.google.com/maps?q=<?= urlencode($case['address']) ?>&output=embed&hl=zh-TW" style="width:100%;max-width:480px;height:200px;border:1px solid var(--gray-200);border-radius:6px" allowfullscreen loading="lazy"></iframe>
        </div>
        <?php endif; ?>
        <div class="detail-item">
            <span class="detail-label">難易度</span>
            <span class="detail-value stars"><?= str_repeat('&#9733;', $case['difficulty']) ?><?= str_repeat('&#9734;', 5 - $case['difficulty']) ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">預估工時</span>
            <span class="detail-value"><?= $case['estimated_hours'] ? $case['estimated_hours'] . ' 小時' : '-' ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">施工進度</span>
            <span class="detail-value"><?= $case['current_visit'] ?> / <?= $case['total_visits'] ?> 次</span>
        </div>
        <div class="detail-item">
            <span class="detail-label">最多施工人數</span>
            <span class="detail-value"><?= $case['max_engineers'] ?> 人</span>
        </div>
        <div class="detail-item">
            <span class="detail-label">承辦業務</span>
            <span class="detail-value"><?= e($case['sales_name'] ?: '-') ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">進件公司</span>
            <span class="detail-value"><?= e(!empty($case['company']) ? $case['company'] : '-') ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">案件來源</span>
            <span class="detail-value"><?= e(!empty($case['case_source']) ? $case['case_source'] : '-') ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">施工區域</span>
            <span class="detail-value"><?= e(!empty($case['construction_area']) ? $case['construction_area'] : '-') ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">是否已完工</span>
            <span class="detail-value"><?= !empty($case['is_completed']) ? '<span class="badge badge-success">已完工</span>' : '<span class="badge badge-secondary">未完工</span>' ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">完工日期</span>
            <span class="detail-value"><?= e($case['completion_date'] ?: '-') ?></span>
        </div>
        <?php if ($case['ragic_id']): ?>
        <div class="detail-item">
            <span class="detail-label">Ragic ID</span>
            <span class="detail-value"><?= e($case['ragic_id']) ?></span>
        </div>
        <?php endif; ?>
    </div>
    <?php if ($case['description']): ?>
    <div class="mt-1">
        <span class="detail-label">案件說明</span>
        <p><?= nl2br(e($case['description'])) ?></p>
    </div>
    <?php endif; ?>
</div>

<!-- 帳務資訊 -->
<?php
$hasFinancial = !empty($case['quote_amount']) || !empty($case['deal_amount']) || !empty($case['total_amount'])
    || !empty($case['deposit_amount']) || !empty($case['balance_amount']) || !empty($case['completion_amount'])
    || !empty($case['total_collected'])
    || isset($case['settlement_confirmed']) && $case['settlement_confirmed'] !== null && $case['settlement_confirmed'] !== ''
    || !empty($case['settlement_date']);
if ($hasFinancial):
?>
<div class="card">
    <div class="card-header">帳務資訊</div>
    <div class="detail-grid">
        <?php if (!empty($case['quote_amount'])): ?>
        <div class="detail-item">
            <span class="detail-label">報價金額</span>
            <span class="detail-value">$<?= number_format($case['quote_amount']) ?></span>
        </div>
        <?php endif; ?>
        <?php if (!empty($case['deal_amount'])): ?>
        <div class="detail-item">
            <span class="detail-label">成交金額 (未稅)</span>
            <span class="detail-value">$<?= number_format($case['deal_amount']) ?></span>
        </div>
        <?php endif; ?>
        <?php if (!empty($case['is_tax_included'])): ?>
        <div class="detail-item">
            <span class="detail-label">是否含稅</span>
            <span class="detail-value"><?= e($case['is_tax_included']) ?></span>
        </div>
        <?php endif; ?>
        <?php if (!empty($case['tax_amount'])): ?>
        <div class="detail-item">
            <span class="detail-label">稅金</span>
            <span class="detail-value">$<?= number_format($case['tax_amount']) ?></span>
        </div>
        <?php endif; ?>
        <?php if (!empty($case['total_amount'])): ?>
        <div class="detail-item">
            <span class="detail-label">含稅金額</span>
            <span class="detail-value financial-highlight">$<?= number_format($case['total_amount']) ?></span>
        </div>
        <?php endif; ?>
        <?php if (!empty($case['deposit_amount'])): ?>
        <div class="detail-item">
            <span class="detail-label">訂金金額</span>
            <span class="detail-value">$<?= number_format($case['deposit_amount']) ?></span>
        </div>
        <?php endif; ?>
        <?php if (!empty($case['deposit_method'])): ?>
        <div class="detail-item">
            <span class="detail-label">訂金支付方式</span>
            <span class="detail-value"><?= e($case['deposit_method']) ?></span>
        </div>
        <?php endif; ?>
        <?php if (!empty($case['deposit_payment_date'])): ?>
        <div class="detail-item">
            <span class="detail-label">訂金付款日</span>
            <span class="detail-value"><?= format_date($case['deposit_payment_date']) ?></span>
        </div>
        <?php endif; ?>
        <?php if (!empty($case['balance_amount'])): ?>
        <div class="detail-item">
            <span class="detail-label">尾款</span>
            <span class="detail-value">$<?= number_format($case['balance_amount']) ?></span>
        </div>
        <?php endif; ?>
        <?php if (!empty($case['completion_amount'])): ?>
        <div class="detail-item">
            <span class="detail-label">完工金額 (含稅)</span>
            <span class="detail-value">$<?= number_format($case['completion_amount']) ?></span>
        </div>
        <?php endif; ?>
        <?php if (!empty($case['total_collected'])): ?>
        <div class="detail-item">
            <span class="detail-label">總收款金額</span>
            <span class="detail-value financial-highlight">$<?= number_format($case['total_collected']) ?></span>
        </div>
        <?php endif; ?>
        <?php if (isset($case['settlement_confirmed']) && $case['settlement_confirmed'] !== null && $case['settlement_confirmed'] !== ''): ?>
        <div class="detail-item">
            <span class="detail-label">帳款是否結清</span>
            <span class="detail-value">
                <?php if ((int)$case['settlement_confirmed'] === 1): ?>
                <span class="badge badge-success">已結清</span>
                <?php else: ?>
                <span class="badge badge-warning">未結清</span>
                <?php endif; ?>
            </span>
        </div>
        <?php endif; ?>
        <?php if (!empty($case['settlement_date'])): ?>
        <div class="detail-item">
            <span class="detail-label">帳款結清日期</span>
            <span class="detail-value"><?= format_date($case['settlement_date']) ?></span>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- 施工時程與條件 -->
<?php if ($case['planned_start_date'] || $case['urgency'] || $case['work_time_start'] || $case['is_large_project']): ?>
<div class="card">
    <div class="card-header">施工時程與條件</div>
    <div class="detail-grid">
        <?php if ($case['planned_start_date']): ?>
        <div class="detail-item">
            <span class="detail-label">預計施工日</span>
            <span class="detail-value"><?= format_date($case['planned_start_date']) ?></span>
        </div>
        <?php endif; ?>
        <?php if ($case['planned_end_date']): ?>
        <div class="detail-item">
            <span class="detail-label">預計完工日</span>
            <span class="detail-value"><?= format_date($case['planned_end_date']) ?></span>
        </div>
        <?php endif; ?>
        <?php if ($case['urgency']): ?>
        <div class="detail-item">
            <span class="detail-label">急迫性</span>
            <span class="detail-value"><?= $case['urgency'] ?> / 5 <?= $case['urgency'] <= 2 ? '(低)' : ($case['urgency'] >= 4 ? '(高)' : '(中)') ?></span>
        </div>
        <?php endif; ?>
        <?php if ($case['system_difficulty']): ?>
        <div class="detail-item">
            <span class="detail-label">系統評估難度</span>
            <span class="detail-value stars"><?= str_repeat('&#9733;', $case['system_difficulty']) ?><?= str_repeat('&#9734;', 5 - $case['system_difficulty']) ?></span>
        </div>
        <?php endif; ?>
        <?php if ($case['work_time_start']): ?>
        <div class="detail-item">
            <span class="detail-label">施工時間</span>
            <span class="detail-value"><?= substr($case['work_time_start'], 0, 5) ?> ~ <?= $case['work_time_end'] ? substr($case['work_time_end'], 0, 5) : '' ?></span>
        </div>
        <?php endif; ?>
        <?php if ($case['customer_break_time']): ?>
        <div class="detail-item">
            <span class="detail-label">客戶休息時間</span>
            <span class="detail-value"><?= e($case['customer_break_time']) ?></span>
        </div>
        <?php endif; ?>
    </div>
    <?php
    $tags = array();
    if (!empty($case['has_time_restriction'])) $tags[] = '有施工時間限制';
    if (!empty($case['allow_night_work'])) $tags[] = '可夜間加班';
    if (!empty($case['is_flexible'])) $tags[] = '可隨時安排';
    if (!empty($case['is_large_project'])) $tags[] = '大型案件';
    if (!empty($tags)):
    ?>
    <div class="d-flex flex-wrap gap-1 mt-1">
        <?php foreach ($tags as $tag): ?>
        <span class="badge badge-info"><?= $tag ?></span>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- 附件 -->
<?php if (!empty($case['attachments'])): ?>
<div class="card">
    <div class="card-header">附件</div>
    <?php
    $typeLabels = array('drawing'=>'施工圖','quotation'=>'報價單','warranty'=>'保固書','wire_plan'=>'線材','site_photo'=>'現場照片','other'=>'其他');
    foreach ($case['attachments'] as $att):
        $isImg = preg_match('/\.(jpg|jpeg|png|gif|webp|heic)$/i', $att['file_name']);
    ?>
    <div class="attachment-item">
        <span class="badge badge-info" style="font-size:.7rem"><?= e(isset($typeLabels[$att['file_type']]) ? $typeLabels[$att['file_type']] : $att['file_type']) ?></span>
        <?php if ($isImg): ?>
        <a href="javascript:void(0)" onclick="openCaseLightbox('<?= e($att['file_path']) ?>')"><?= e($att['file_name']) ?></a>
        <?php else: ?>
        <a href="javascript:void(0)" onclick="openFileModal('<?= e($att['file_path']) ?>','<?= e($att['file_name']) ?>')"><?= e($att['file_name']) ?></a>
        <?php endif; ?>
        <span class="text-muted" style="font-size:.75rem"><?= e($att['uploader_name'] ?? '') ?></span>
    </div>
    <?php endforeach; ?>
</div>

<!-- Case View Lightbox -->
<div class="case-lightbox" id="caseLightbox" onclick="if(event.target===this)closeCaseLightbox()">
    <span class="case-lb-close" onclick="closeCaseLightbox()">&times;</span>
    <span class="case-lb-prev" onclick="event.stopPropagation();caseLbNav(-1)">&lsaquo;</span>
    <span class="case-lb-next" onclick="event.stopPropagation();caseLbNav(1)">&rsaquo;</span>
    <img id="caseLbImg" src="" alt="預覽" onclick="event.stopPropagation()">
    <span class="case-lb-counter" id="caseLbCounter"></span>
</div>

<!-- 檔案檢視 Modal（PDF 等非圖片）-->
<div class="file-modal" id="fileModal">
    <div class="file-modal-header">
        <span id="fileModalTitle"></span>
        <div class="file-modal-actions">
            <a id="fileModalDownload" href="" download class="file-modal-btn">下載</a>
            <span class="file-modal-close" onclick="closeFileModal()">&times;</span>
        </div>
    </div>
    <iframe id="fileModalFrame" src="" frameborder="0"></iframe>
</div>

<style>
.case-lightbox { display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,.85); z-index:9999; align-items:center; justify-content:center; cursor:pointer; }
.case-lightbox.active { display:flex; }
.case-lightbox img { max-width:90%; max-height:90%; border-radius:8px; box-shadow:0 4px 20px rgba(0,0,0,.5); }
.case-lb-close { position:absolute; top:16px; right:16px; color:#fff; font-size:2.5rem; cursor:pointer; z-index:10000; width:48px; height:48px; display:flex; align-items:center; justify-content:center; background:rgba(0,0,0,.4); border-radius:50%; line-height:1; }
.case-lb-prev, .case-lb-next { position:absolute; top:50%; transform:translateY(-50%); color:#fff; font-size:2.5rem; cursor:pointer; padding:16px 12px; z-index:10000; background:rgba(0,0,0,.4); border-radius:8px; user-select:none; }
.case-lb-prev { left:10px; } .case-lb-next { right:10px; }
.case-lb-counter { position:absolute; bottom:20px; left:50%; transform:translateX(-50%); color:#fff; font-size:.9rem; z-index:10000; background:rgba(0,0,0,.4); padding:4px 12px; border-radius:12px; }

.file-modal { display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:#fff; z-index:9999; flex-direction:column; }
.file-modal.active { display:flex; }
.file-modal-header { display:flex; justify-content:space-between; align-items:center; padding:12px 16px; background:#1a73e8; color:#fff; flex-shrink:0; }
.file-modal-header span { font-weight:600; font-size:.95rem; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; flex:1; margin-right:12px; }
.file-modal-actions { display:flex; align-items:center; gap:8px; flex-shrink:0; }
.file-modal-btn { background:rgba(255,255,255,.2); color:#fff; padding:6px 14px; border-radius:6px; text-decoration:none; font-size:.85rem; }
.file-modal-btn:hover { background:rgba(255,255,255,.3); color:#fff; }
.file-modal-close { color:#fff; font-size:1.8rem; cursor:pointer; width:36px; height:36px; display:flex; align-items:center; justify-content:center; line-height:1; }
.file-modal iframe { flex:1; width:100%; border:0; }
</style>

<script>
var caseLbImages = [], caseLbIndex = 0;
function openCaseLightbox(src) {
    caseLbImages = [];
    document.querySelectorAll('.attachment-item a[onclick*="openCaseLightbox"]').forEach(function(a) {
        var m = (a.getAttribute('onclick') || '').match(/openCaseLightbox\(['"]([^'"]+)['"]/);
        if (m && caseLbImages.indexOf(m[1]) === -1) caseLbImages.push(m[1]);
    });
    if (caseLbImages.length === 0) caseLbImages = [src];
    caseLbIndex = caseLbImages.indexOf(src);
    if (caseLbIndex < 0) caseLbIndex = 0;
    showCaseLbImage();
    document.getElementById('caseLightbox').classList.add('active');
}
function showCaseLbImage() {
    document.getElementById('caseLbImg').src = caseLbImages[caseLbIndex];
    var c = document.getElementById('caseLbCounter');
    if (caseLbImages.length > 1) {
        c.textContent = (caseLbIndex + 1) + ' / ' + caseLbImages.length;
        c.style.display = 'block';
        document.querySelector('.case-lb-prev').style.display = 'block';
        document.querySelector('.case-lb-next').style.display = 'block';
    } else {
        c.style.display = 'none';
        document.querySelector('.case-lb-prev').style.display = 'none';
        document.querySelector('.case-lb-next').style.display = 'none';
    }
}
function caseLbNav(dir) {
    caseLbIndex += dir;
    if (caseLbIndex < 0) caseLbIndex = caseLbImages.length - 1;
    if (caseLbIndex >= caseLbImages.length) caseLbIndex = 0;
    showCaseLbImage();
}
function closeCaseLightbox() { document.getElementById('caseLightbox').classList.remove('active'); document.getElementById('caseLbImg').src=''; }
function openFileModal(src, name) {
    document.getElementById('fileModalTitle').textContent = name || '檔案';
    document.getElementById('fileModalDownload').href = src;
    document.getElementById('fileModalFrame').src = src;
    document.getElementById('fileModal').classList.add('active');
}
function closeFileModal() {
    document.getElementById('fileModal').classList.remove('active');
    document.getElementById('fileModalFrame').src = '';
}
document.addEventListener('keydown', function(e) {
    var o = document.getElementById('caseLightbox');
    if (o && o.classList.contains('active')) {
        if (e.key === 'Escape') closeCaseLightbox();
        if (e.key === 'ArrowLeft') caseLbNav(-1);
        if (e.key === 'ArrowRight') caseLbNav(1);
        return;
    }
    var fm = document.getElementById('fileModal');
    if (fm && fm.classList.contains('active') && e.key === 'Escape') closeFileModal();
});
(function() {
    var sx=0, sy=0;
    document.addEventListener('DOMContentLoaded', function() {
        var o = document.getElementById('caseLightbox');
        if (!o) return;
        o.addEventListener('touchstart', function(e) { sx=e.changedTouches[0].screenX; sy=e.changedTouches[0].screenY; }, {passive:true});
        o.addEventListener('touchend', function(e) {
            var dx = e.changedTouches[0].screenX - sx;
            var dy = e.changedTouches[0].screenY - sy;
            if (Math.abs(dx) < 50 && Math.abs(dy) < 50) return;
            if (Math.abs(dx) > Math.abs(dy)) {
                if (dx > 0) caseLbNav(-1); else caseLbNav(1);
            } else {
                closeCaseLightbox();
            }
        }, {passive:true});
    });
})();
</script>
<?php endif; ?>

<!-- 預計使用線材與配件 -->
<?php if (!empty($case['material_estimates'])): ?>
<div class="card">
    <div class="card-header">預計使用線材與配件</div>
    <div class="table-responsive">
        <table class="table" style="font-size:.9rem">
            <thead><tr><th>品名</th><th>型號</th><th>單位</th><th class="text-right">預估數量</th></tr></thead>
            <tbody>
            <?php foreach ($case['material_estimates'] as $em): ?>
            <tr>
                <td><?= e($em['material_name']) ?></td>
                <td><?= e($em['model_number'] ?: '-') ?></td>
                <td><?= e($em['unit'] ?: '-') ?></td>
                <td class="text-right"><?= $em['estimated_qty'] ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- 現場環境 -->
<?php if (!empty($case['site_conditions'])): ?>
<?php $sc = $case['site_conditions']; ?>
<div class="card">
    <div class="card-header">現場環境</div>
    <div class="detail-grid">
        <?php if ($sc['structure_type']): ?>
        <div class="detail-item">
            <span class="detail-label">建築結構</span>
            <span class="detail-value">
                <?php
                $structMap = ['RC'=>'RC結構','steel_sheet'=>'鐵皮','open_area'=>'空曠地','construction_site'=>'建築工地'];
                echo implode('、', array_map(function($v) use ($structMap) { return isset($structMap[$v]) ? $structMap[$v] : $v; }, explode(',', $sc['structure_type'])));
                ?>
            </span>
        </div>
        <?php endif; ?>
        <?php if ($sc['conduit_type']): ?>
        <div class="detail-item">
            <span class="detail-label">管線需求</span>
            <span class="detail-value">
                <?php
                $condMap = ['PVC'=>'PVC','EMT'=>'EMT','RSG'=>'RSG','molding'=>'壓條','wall_penetration'=>'穿牆','aerial'=>'架空','underground'=>'切地埋管'];
                echo implode('、', array_map(function($v) use ($condMap) { return isset($condMap[$v]) ? $condMap[$v] : $v; }, explode(',', $sc['conduit_type'])));
                ?>
            </span>
        </div>
        <?php endif; ?>
        <?php if ($sc['floor_count']): ?>
        <div class="detail-item"><span class="detail-label">樓層數</span><span class="detail-value"><?= $sc['floor_count'] ?></span></div>
        <?php endif; ?>
        <div class="detail-item">
            <span class="detail-label">設施</span>
            <span class="detail-value">
                <?php
                $facilities = array();
                if (!empty($sc['has_elevator'])) $facilities[] = '有電梯';
                if (!empty($sc['has_ladder_needed'])) {
                    $facilities[] = '拉梯' . (!empty($sc['ladder_size']) ? '(' . e($sc['ladder_size']) . '米)' : '');
                }
                if (!empty($sc['high_ceiling_height'])) $facilities[] = '挑高' . e($sc['high_ceiling_height']) . '米';
                if (!empty($sc['needs_scissor_lift'])) {
                    $facilities[] = '自走車' . (!empty($sc['scissor_lift_height']) ? '(' . e($sc['scissor_lift_height']) . '米)' : '');
                }
                echo $facilities ? implode('、', $facilities) : '-';
                ?>
            </span>
        </div>
        <?php if (!empty($sc['safety_equipment'])): ?>
        <div class="detail-item">
            <span class="detail-label">工安需求</span>
            <span class="detail-value">
                <?php
                $safetyMap = array('helmet'=>'安全帽','reflective_vest'=>'反光背心','safety_shoes'=>'安全鞋','harness'=>'背負式安全帶','tool_lanyard'=>'工具防墜');
                echo implode('、', array_map(function($v) use ($safetyMap) { return isset($safetyMap[$v]) ? $safetyMap[$v] : $v; }, explode(',', $sc['safety_equipment'])));
                ?>
            </span>
        </div>
        <?php endif; ?>
    </div>
    <?php if (!empty($sc['special_requirements'])): ?>
    <div class="mt-1"><span class="detail-label">特殊需求</span><p><?= nl2br(e($sc['special_requirements'])) ?></p></div>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- 聯絡人 -->
<?php if (!empty($case['contacts'])): ?>
<div class="card">
    <div class="card-header">聯絡人</div>
    <div class="table-responsive">
        <table class="table">
            <thead><tr><th>姓名</th><th>電話</th><th>角色</th></tr></thead>
            <tbody>
                <?php foreach ($case['contacts'] as $c): ?>
                <tr>
                    <td><?= e($c['contact_name']) ?></td>
                    <td><a href="tel:<?= e($c['contact_phone'] ?? '') ?>"><?= e($c['contact_phone'] ?? '-') ?></a></td>
                    <td><?= e($c['contact_role'] ?? '-') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- 所需技能 -->
<?php if (!empty($case['required_skills'])): ?>
<div class="card">
    <div class="card-header">所需技能</div>
    <div class="d-flex flex-wrap gap-1">
        <?php foreach ($case['required_skills'] as $rs): ?>
        <div class="skill-badge">
            <span><?= e($rs['skill_name']) ?></span>
            <span class="stars"><?= str_repeat('&#9733;', $rs['min_proficiency']) ?></span>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- 收款記錄 -->
<?php if (!empty($case['payments'])): ?>
<div class="card">
    <div class="card-header">收款記錄</div>
    <div class="table-responsive">
        <table class="table">
            <thead><tr><th>類型</th><th>方式</th><th>金額</th><th>日期</th></tr></thead>
            <tbody>
                <?php
                $paymentTypes = ['deposit'=>'訂金','final_payment'=>'尾款'];
                $paymentMethods = ['cash'=>'現金','transfer'=>'匯款','check'=>'支票'];
                foreach ($case['payments'] as $p):
                ?>
                <tr>
                    <td><?= $paymentTypes[$p['payment_type']] ?? $p['payment_type'] ?></td>
                    <td><?= $paymentMethods[$p['payment_method']] ?? $p['payment_method'] ?></td>
                    <td>$<?= number_format($p['amount']) ?></td>
                    <td><?= format_date($p['payment_date']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<style>
.detail-grid { display: grid; grid-template-columns: 1fr; gap: 8px; }
.detail-item { display: flex; flex-direction: column; }
.detail-label { font-size: .8rem; color: var(--gray-500); }
.detail-value { font-size: .95rem; }
.financial-highlight { font-weight: 600; color: var(--primary); }
.skill-badge {
    display: inline-flex; align-items: center; gap: 4px;
    background: var(--gray-100); padding: 4px 10px; border-radius: 16px;
    font-size: .85rem;
}
.attachment-item {
    display: flex; align-items: center; gap: 8px; padding: 6px 10px;
    background: var(--gray-50); border-radius: var(--radius); margin-bottom: 6px;
    font-size: .9rem;
}
.attachment-item a { color: var(--primary); flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
@media (min-width: 768px) {
    .detail-grid { grid-template-columns: repeat(2, 1fr); }
}
@media (min-width: 1024px) {
    .detail-grid { grid-template-columns: repeat(3, 1fr); }
}
.support-modal-overlay {
    position: fixed; top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0,0,0,.5); display: flex; align-items: center;
    justify-content: center; z-index: 1000;
}
.support-modal-content {
    background: #fff; border-radius: 12px; width: 90%; max-width: 420px; padding: 0;
    box-shadow: 0 20px 60px rgba(0,0,0,.3);
}
.support-modal-header {
    display: flex; justify-content: space-between; align-items: center;
    padding: 16px 20px; border-bottom: 1px solid var(--gray-200);
}
.support-modal-body { padding: 20px; max-height: 60vh; overflow-y: auto; }
.support-modal-footer {
    display: flex; justify-content: flex-end; gap: 8px;
    padding: 16px 20px; border-top: 1px solid var(--gray-200);
}
.support-branch-item {
    display: flex; align-items: center; gap: 10px;
    padding: 10px 12px; border-radius: 8px; margin-bottom: 6px;
    background: var(--gray-50); cursor: pointer;
}
.support-branch-item:hover { background: var(--gray-100); }
.support-branch-item input[type="checkbox"] { width: 18px; height: 18px; }
.support-branch-item label { cursor: pointer; flex: 1; font-size: .95rem; }
</style>

<?php if (Auth::hasPermission('cases.manage') || Auth::hasPermission('all')): ?>
<!-- 支援分公司 Modal -->
<div id="supportBranchModal" class="support-modal-overlay" style="display:none" onclick="if(event.target===this)closeSupportModal()">
    <div class="support-modal-content">
        <div class="support-modal-header">
            <h3 style="margin:0;font-size:1.1rem">支援分公司設定</h3>
            <button type="button" onclick="closeSupportModal()" style="background:none;border:none;font-size:1.5rem;cursor:pointer;line-height:1">&times;</button>
        </div>
        <div class="support-modal-body">
            <p style="color:var(--gray-500);font-size:.85rem;margin-bottom:12px">選擇可以看到此案件的其他分公司：</p>
            <div id="supportBranchList">載入中...</div>
        </div>
        <div class="support-modal-footer">
            <button type="button" class="btn btn-outline btn-sm" onclick="closeSupportModal()">取消</button>
            <button type="button" class="btn btn-primary btn-sm" id="saveSupportBtn" onclick="saveSupportBranches()">儲存</button>
        </div>
    </div>
</div>

<script>
var SUPPORT_CASE_ID = <?= (int)$case['id'] ?>;
var SUPPORT_OWN_BRANCH = <?= (int)$case['branch_id'] ?>;
var SUPPORT_CSRF = '<?= e(Session::getCsrfToken()) ?>';
var SUPPORT_BRANCHES = <?= json_encode($model->getAllBranches()) ?>;

function openSupportModal() {
    document.getElementById('supportBranchModal').style.display = 'flex';
    loadSupportBranches();
}
function closeSupportModal() {
    document.getElementById('supportBranchModal').style.display = 'none';
}
function loadSupportBranches() {
    var container = document.getElementById('supportBranchList');
    container.innerHTML = '載入中...';
    var xhr = new XMLHttpRequest();
    xhr.open('GET', '/cases.php?action=get_support_branches&id=' + SUPPORT_CASE_ID);
    xhr.onload = function() {
        try {
            var res = JSON.parse(xhr.responseText);
            var currentIds = {};
            if (res.success && res.data) {
                for (var i = 0; i < res.data.length; i++) {
                    currentIds[res.data[i].branch_id] = true;
                }
            }
            renderBranchCheckboxes(currentIds);
        } catch(e) { container.innerHTML = '載入失敗'; }
    };
    xhr.onerror = function() { container.innerHTML = '載入失敗'; };
    xhr.send();
}
function renderBranchCheckboxes(currentIds) {
    var container = document.getElementById('supportBranchList');
    var html = '';
    for (var i = 0; i < SUPPORT_BRANCHES.length; i++) {
        var b = SUPPORT_BRANCHES[i];
        if (parseInt(b.id) === SUPPORT_OWN_BRANCH) continue;
        var checked = currentIds[b.id] ? ' checked' : '';
        html += '<div class="support-branch-item" onclick="toggleSupportCb(this)">';
        html += '<input type="checkbox" id="sb_' + b.id + '" value="' + b.id + '"' + checked + ' onclick="event.stopPropagation()">';
        html += '<label for="sb_' + b.id + '" onclick="event.stopPropagation()">' + escSupportHtml(b.name) + '</label>';
        html += '</div>';
    }
    if (!html) html = '<p style="color:var(--gray-400)">沒有其他分公司可選擇</p>';
    container.innerHTML = html;
}
function toggleSupportCb(el) {
    var cb = el.querySelector('input[type="checkbox"]');
    cb.checked = !cb.checked;
}
function saveSupportBranches() {
    var btn = document.getElementById('saveSupportBtn');
    btn.disabled = true;
    btn.textContent = '儲存中...';
    var cbs = document.querySelectorAll('#supportBranchList input[type="checkbox"]:checked');
    var fd = new FormData();
    fd.append('csrf_token', SUPPORT_CSRF);
    fd.append('case_id', SUPPORT_CASE_ID);
    for (var i = 0; i < cbs.length; i++) {
        fd.append('branch_ids[]', cbs[i].value);
    }
    var xhr = new XMLHttpRequest();
    xhr.open('POST', '/cases.php?action=save_support_branches');
    xhr.onload = function() {
        try {
            var res = JSON.parse(xhr.responseText);
            if (res.success) {
                location.reload();
            } else {
                alert(res.error || '儲存失敗');
                btn.disabled = false; btn.textContent = '儲存';
            }
        } catch(e) { alert('儲存失敗'); btn.disabled = false; btn.textContent = '儲存'; }
    };
    xhr.onerror = function() { alert('網路錯誤'); btn.disabled = false; btn.textContent = '儲存'; };
    xhr.send(fd);
}
function escSupportHtml(s) {
    var d = document.createElement('div');
    d.textContent = s;
    return d.innerHTML;
}
</script>
<?php endif; ?>
