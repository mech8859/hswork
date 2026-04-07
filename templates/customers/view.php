<div class="d-flex justify-between align-center flex-wrap gap-1 mb-2">
    <div>
        <h2><?= e($customer['name']) ?></h2>
        <span class="badge"><?= e($customer['customer_no']) ?></span>
        <?php if (!empty($customer['legacy_customer_no'])): ?>
        <span class="badge" style="background:#f5f5f5;color:#666"><?= e($customer['legacy_customer_no']) ?></span>
        <?php endif; ?>
        <?php
        $categoryOptions = CustomerModel::categoryOptions();
        if (!empty($customer['category'])):
        ?>
        <span class="badge badge-primary"><?= e($categoryOptions[$customer['category']] ?? $customer['category']) ?></span>
        <?php endif; ?>
    </div>
    <div class="d-flex gap-1 flex-wrap">
        <?php if (Auth::hasPermission('customers.manage')): ?>
        <a href="/customers.php?action=edit&id=<?= $customer['id'] ?>" class="btn btn-primary btn-sm">編輯</a>
        <?php endif; ?>
        <a href="javascript:history.back()" class="btn btn-outline btn-sm">返回</a>
    </div>
</div>

<!-- Tab Navigation -->
<div class="tab-nav">
    <button class="tab-btn active" onclick="switchTab('info')">基本資料</button>
    <button class="tab-btn" onclick="switchTab('cases')">案件紀錄</button>
    <button class="tab-btn" onclick="switchTab('deals')">成交紀錄</button>
    <button class="tab-btn" onclick="switchTab('transactions')">帳款交易</button>
    <button class="tab-btn" onclick="switchTab('files')">文件管理</button>
</div>

<!-- Tab 1: 基本資料 -->
<div class="tab-content active" id="tab-info">
    <div class="card">
        <div class="card-header">客戶資訊</div>
        <div class="detail-grid">
            <div class="detail-item"><span class="detail-label">客戶編號</span><span class="detail-value"><?= e($customer['customer_no']) ?></span></div>
            <?php if (!empty($customer['legacy_customer_no'])): ?>
            <div class="detail-item"><span class="detail-label">原資料客戶編號</span><span class="detail-value" style="color:#999"><?= e($customer['legacy_customer_no']) ?></span></div>
            <?php endif; ?>
            <?php if (!empty($cases)): ?>
            <div class="detail-item"><span class="detail-label">進件編號</span><span class="detail-value"><?php
                $caseLinks = array();
                foreach ($cases as $cs) {
                    $caseLinks[] = '<a href="/cases.php?action=view&id=' . $cs['id'] . '">' . e($cs['case_number']) . '</a>';
                }
                echo implode('、', $caseLinks);
            ?></span></div>
            <?php endif; ?>
            <div class="detail-item"><span class="detail-label">客戶名稱</span><span class="detail-value"><?= e($customer['name']) ?></span></div>
            <div class="detail-item"><span class="detail-label">客戶分類</span><span class="detail-value"><?= e($categoryOptions[$customer['category']] ?? '-') ?></span></div>
            <div class="detail-item"><span class="detail-label">聯絡人</span><span class="detail-value"><?= e($customer['contact_person'] ?: '-') ?></span></div>
            <div class="detail-item"><span class="detail-label">電話</span><span class="detail-value"><?= e($customer['phone'] ?: '-') ?></span></div>
            <div class="detail-item"><span class="detail-label">手機</span><span class="detail-value"><?= e($customer['mobile'] ?: '-') ?></span></div>
            <div class="detail-item"><span class="detail-label">傳真</span><span class="detail-value"><?= e($customer['fax'] ?: '-') ?></span></div>
            <div class="detail-item"><span class="detail-label">Email</span><span class="detail-value"><?= e($customer['email'] ?: '-') ?></span></div>
            <div class="detail-item"><span class="detail-label">完工日期</span><span class="detail-value"><?= e($customer['completion_date'] ?: '-') ?></span></div>
            <div class="detail-item"><span class="detail-label">保固日期</span><span class="detail-value"><?= e($customer['warranty_date'] ?: '-') ?></span></div>
            <div class="detail-item"><span class="detail-label">承辦業務</span><span class="detail-value"><?= e($customer['sales_name'] ?: '-') ?></span></div>
            <div class="detail-item" style="grid-column: span 2">
                <span class="detail-label">帳單地址</span>
                <span class="detail-value"><?= e(trim(($customer['billing_city'] ?: '') . ($customer['billing_district'] ?: '') . ($customer['billing_address'] ?: '')) ?: '-') ?></span>
            </div>
            <div class="detail-item" style="grid-column: span 2">
                <span class="detail-label">施工地址</span>
                <span class="detail-value"><?= e(trim(($customer['site_city'] ?: '') . ($customer['site_district'] ?: '') . ($customer['site_address'] ?: '')) ?: '-') ?></span>
            </div>
        </div>
        <?php
        $siteFullAddr = trim(($customer['site_city'] ?: '') . ($customer['site_district'] ?: '') . ($customer['site_address'] ?: ''));
        if ($siteFullAddr):
        ?>
        <div class="mt-1">
            <iframe src="https://maps.google.com/maps?q=<?= urlencode($siteFullAddr) ?>&output=embed&hl=zh-TW" style="width:100%;max-width:480px;height:200px;border:1px solid var(--gray-200);border-radius:6px" allowfullscreen loading="lazy"></iframe>
        </div>
        <?php endif; ?>
    </div>

    <div class="card">
        <div class="card-header">發票資訊</div>
        <div class="detail-grid">
            <div class="detail-item"><span class="detail-label">發票抬頭</span><span class="detail-value"><?= e($customer['invoice_title'] ?: '-') ?></span></div>
            <div class="detail-item"><span class="detail-label">統一編號</span><span class="detail-value"><?= e($customer['tax_id'] ?: '-') ?></span></div>
            <div class="detail-item"><span class="detail-label">發票寄送 Email</span><span class="detail-value"><?= e($customer['invoice_email'] ?: '-') ?></span></div>
            <div class="detail-item"><span class="detail-label">付款方式</span><span class="detail-value"><?= e($customer['payment_method'] ?: '-') ?></span></div>
            <div class="detail-item"><span class="detail-label">付款條件</span><span class="detail-value"><?= e($customer['payment_terms'] ?: '-') ?></span></div>
        </div>
    </div>

    <?php if (!empty($customer['note'])): ?>
    <div class="card">
        <div class="card-header">備註</div>
        <p><?= nl2br(e($customer['note'])) ?></p>
    </div>
    <?php endif; ?>
</div>

<!-- Tab 2: 案件紀錄 -->
<div class="tab-content" id="tab-cases">
    <div class="card">
        <div class="card-header">案件紀錄</div>
        <?php if (empty($cases)): ?>
            <p class="text-muted text-center mt-2">目前無案件紀錄</p>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead><tr><th>進件編號</th><th>案件名稱</th><th>據點</th><th>進度</th><th>建立日期</th></tr></thead>
                <tbody>
                    <?php foreach ($cases as $c): ?>
                    <tr>
                        <td><a href="/cases.php?action=view&id=<?= $c['id'] ?>"><?= e($c['case_number']) ?></a></td>
                        <td><?= e($c['title']) ?></td>
                        <td><?= e($c['branch_name'] ?: '-') ?></td>
                        <td><?= e($c['status']) ?></td>
                        <td><?= e($c['created_at']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Tab 3: 成交紀錄 -->
<div class="tab-content" id="tab-deals">
    <div class="card">
        <div class="card-header">成交紀錄</div>
        <?php if (empty($deals)): ?>
            <p class="text-muted text-center mt-2">目前無成交紀錄</p>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead><tr><th>案件名稱</th><th>施工地址</th><th class="text-right">成交金額</th><th>完工日期</th><th>保固到期</th></tr></thead>
                <tbody>
                    <?php foreach ($deals as $d): ?>
                    <tr>
                        <td><?= e($d['title']) ?></td>
                        <td><?= e($d['site_address'] ?: '-') ?></td>
                        <td class="text-right">$<?= number_format($d['deal_amount']) ?></td>
                        <td><?= e($d['completion_date'] ?: '-') ?></td>
                        <td><?= e($d['warranty_date'] ?: '-') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Tab 4: 帳款交易 -->
<div class="tab-content" id="tab-transactions">
    <div class="card">
        <div class="card-header">帳款交易</div>
        <?php if (empty($transactions)): ?>
            <p class="text-muted text-center mt-2">目前無帳款交易紀錄</p>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead><tr><th>交易日期</th><th>說明</th><th class="text-right">金額</th><th>備註</th></tr></thead>
                <tbody>
                    <?php foreach ($transactions as $t): ?>
                    <tr>
                        <td><?= e($t['transaction_date']) ?></td>
                        <td><?= e($t['description']) ?></td>
                        <td class="text-right">$<?= number_format($t['amount']) ?></td>
                        <td><?= e($t['note'] ?: '-') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Tab 5: 文件管理 -->
<div class="tab-content" id="tab-files">
    <?php
    $fileTypeOptions = CustomerModel::fileTypeOptions();
    $groupedFiles = array();
    foreach ($fileTypeOptions as $k => $v) { $groupedFiles[$k] = array(); }
    if (!empty($files)) {
        foreach ($files as $f) {
            $ft = $f['file_type'] ?: 'other';
            if (!isset($groupedFiles[$ft])) $groupedFiles[$ft] = array();
            $groupedFiles[$ft][] = $f;
        }
    }
    $canManage = Auth::hasPermission('customers.manage');
    ?>
    <div class="card">
        <div class="card-header">文件管理</div>
        <div class="cust-attach-grid">
            <?php foreach ($fileTypeOptions as $typeKey => $typeLabel): ?>
            <div class="cust-atc-card" id="catc-<?= $typeKey ?>">
                <div class="cust-atc-header">
                    <span class="cust-atc-title"><?= e($typeLabel) ?></span>
                    <span class="cust-atc-count"><?= count($groupedFiles[$typeKey]) ?></span>
                </div>
                <div class="cust-atc-files" id="catc-files-<?= $typeKey ?>">
                    <?php foreach ($groupedFiles[$typeKey] as $f):
                        $ext = strtolower(pathinfo($f['file_name'], PATHINFO_EXTENSION));
                        $isImg = in_array($ext, array('jpg','jpeg','png','gif','webp','bmp'));
                    ?>
                    <div class="cust-atc-file <?= $isImg ? 'cust-atc-img' : '' ?>">
                        <?php if ($isImg): ?>
                        <img src="/<?= e($f['file_path']) ?>" class="cust-atc-thumb hs-photo" onclick="hsOpenImage('/<?= e($f['file_path']) ?>')" alt="<?= e($f['file_name']) ?>">
                        <?php else: ?>
                        <a href="javascript:void(0)" onclick="hsOpenFile('/<?= e($f['file_path']) ?>','<?= e($f['file_name']) ?>')" class="cust-atc-fname">📄 <?= e($f['file_name']) ?></a>
                        <?php endif; ?>
                        <?php if ($canManage): ?>
                        <button type="button" class="cust-atc-del" onclick="if(confirm('確定刪除？'))location.href='/customers.php?action=delete_file&file_id=<?= $f['id'] ?>&customer_id=<?= $customer['id'] ?>&csrf_token=<?= e(Session::getCsrfToken()) ?>'">✕</button>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php if ($canManage): ?>
                <label class="cust-atc-add">
                    <input type="file" multiple accept="image/*,.pdf,.doc,.docx,.xls,.xlsx" style="display:none" onchange="uploadCustFiles(this, '<?= $typeKey ?>')">
                    <span>＋ 上傳<?= e($typeLabel) ?></span>
                </label>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Lightbox -->
    <div class="cust-lightbox" id="custLightbox" onclick="if(event.target===this)closeCustLightbox()">
        <span class="cust-lb-close" onclick="closeCustLightbox()">&times;</span>
        <span class="cust-lb-prev" onclick="event.stopPropagation();custLbNav(-1)">&lsaquo;</span>
        <span class="cust-lb-next" onclick="event.stopPropagation();custLbNav(1)">&rsaquo;</span>
        <img id="custLbImg" src="" alt="預覽" onclick="event.stopPropagation()">
        <span class="cust-lb-counter" id="custLbCounter"></span>
    </div>
</div>

<style>
.cust-attach-grid { display:grid; grid-template-columns:repeat(3, 1fr); gap:12px; }
.cust-atc-card { border:1px solid var(--gray-200); border-radius:8px; padding:12px; }
.cust-atc-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:8px; }
.cust-atc-title { font-weight:600; font-size:.9rem; }
.cust-atc-count { background:var(--gray-100); color:var(--gray-500); border-radius:50%; width:24px; height:24px; display:flex; align-items:center; justify-content:center; font-size:.75rem; font-weight:600; }
.cust-atc-files { display:flex; flex-wrap:wrap; gap:8px; margin-bottom:8px; }
.cust-atc-file { position:relative; }
.cust-atc-img .cust-atc-thumb { width:72px; height:72px; object-fit:cover; border-radius:6px; cursor:pointer; border:1px solid var(--gray-200); }
.cust-atc-img .cust-atc-thumb:hover { opacity:.8; }
.cust-atc-del { position:absolute; top:-4px; right:-4px; background:#fff; border:1px solid var(--gray-300); border-radius:50%; width:20px; height:20px; display:flex; align-items:center; justify-content:center; font-size:.65rem; padding:0; cursor:pointer; box-shadow:0 1px 3px rgba(0,0,0,.15); }
.cust-atc-del:hover { background:#ffebee; color:#e53935; }
.cust-atc-fname { color:var(--primary); text-decoration:none; font-size:.8rem; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; max-width:160px; display:block; }
.cust-atc-fname:hover { text-decoration:underline; }
.cust-atc-add { display:flex; align-items:center; justify-content:center; padding:8px; border:2px dashed var(--gray-300); border-radius:6px; cursor:pointer; color:var(--gray-500); font-size:.85rem; transition:all .15s; }
.cust-atc-add:hover { border-color:var(--primary); color:var(--primary); background:rgba(33,150,243,.04); }
.cust-lightbox { display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,.85); z-index:9999; align-items:center; justify-content:center; cursor:pointer; }
.cust-lightbox.active { display:flex; }
.cust-lightbox img { max-width:90%; max-height:90%; border-radius:8px; box-shadow:0 4px 20px rgba(0,0,0,.5); }
.cust-lb-close { position:absolute; top:16px; right:16px; color:#fff; font-size:2.5rem; cursor:pointer; z-index:10000; width:48px; height:48px; display:flex; align-items:center; justify-content:center; background:rgba(0,0,0,.4); border-radius:50%; line-height:1; }
.cust-lb-prev, .cust-lb-next { position:absolute; top:50%; transform:translateY(-50%); color:#fff; font-size:2.5rem; cursor:pointer; padding:16px 12px; z-index:10000; background:rgba(0,0,0,.4); border-radius:8px; user-select:none; }
.cust-lb-prev { left:10px; } .cust-lb-next { right:10px; }
.cust-lb-counter { position:absolute; bottom:20px; left:50%; transform:translateX(-50%); color:#fff; font-size:.9rem; z-index:10000; background:rgba(0,0,0,.4); padding:4px 12px; border-radius:12px; }
@media (max-width: 767px) { .cust-attach-grid { grid-template-columns:repeat(2, 1fr); } }
@media (max-width: 480px) { .cust-attach-grid { grid-template-columns:1fr; } }
</style>

<script>
var custLbImages = [], custLbIndex = 0;
function openCustLightbox(src) {
    custLbImages = [];
    document.querySelectorAll('.cust-atc-thumb').forEach(function(img) {
        var oc = img.getAttribute('onclick') || '';
        var m = oc.match(/openCustLightbox\(['"]([^'"]+)['"]/);
        if (m && custLbImages.indexOf(m[1]) === -1) custLbImages.push(m[1]);
    });
    if (custLbImages.length === 0) custLbImages = [src];
    custLbIndex = custLbImages.indexOf(src);
    if (custLbIndex < 0) custLbIndex = 0;
    showCustLbImage();
    document.getElementById('custLightbox').classList.add('active');
}
function showCustLbImage() {
    document.getElementById('custLbImg').src = custLbImages[custLbIndex];
    var c = document.getElementById('custLbCounter');
    if (custLbImages.length > 1) {
        c.textContent = (custLbIndex + 1) + ' / ' + custLbImages.length;
        c.style.display = 'block';
        document.querySelector('.cust-lb-prev').style.display = 'block';
        document.querySelector('.cust-lb-next').style.display = 'block';
    } else {
        c.style.display = 'none';
        document.querySelector('.cust-lb-prev').style.display = 'none';
        document.querySelector('.cust-lb-next').style.display = 'none';
    }
}
function custLbNav(dir) {
    custLbIndex += dir;
    if (custLbIndex < 0) custLbIndex = custLbImages.length - 1;
    if (custLbIndex >= custLbImages.length) custLbIndex = 0;
    showCustLbImage();
}
function closeCustLightbox() { var o=document.getElementById('custLightbox'); o.classList.remove('active'); document.getElementById('custLbImg').src=''; }
document.addEventListener('keydown', function(e) {
    var o = document.getElementById('custLightbox');
    if (!o || !o.classList.contains('active')) return;
    if (e.key === 'Escape') closeCustLightbox();
    if (e.key === 'ArrowLeft') custLbNav(-1);
    if (e.key === 'ArrowRight') custLbNav(1);
});
// 觸控滑動
(function() {
    var sx=0, sy=0;
    document.addEventListener('DOMContentLoaded', function() {
        var o = document.getElementById('custLightbox');
        if (!o) return;
        o.addEventListener('touchstart', function(e) { sx = e.changedTouches[0].screenX; sy = e.changedTouches[0].screenY; }, {passive:true});
        o.addEventListener('touchend', function(e) {
            var dx = e.changedTouches[0].screenX - sx;
            var dy = e.changedTouches[0].screenY - sy;
            if (Math.abs(dx) < 50 && Math.abs(dy) < 50) return;
            if (Math.abs(dx) > Math.abs(dy)) {
                if (dx > 0) custLbNav(-1); else custLbNav(1);
            } else {
                closeCustLightbox();
            }
        }, {passive:true});
    });
})();

function uploadCustFiles(input, fileType) {
    if (!input.files.length) return;
    var formData = new FormData();
    formData.append('csrf_token', '<?= e(Session::getCsrfToken()) ?>');
    formData.append('customer_id', '<?= $customer['id'] ?>');
    formData.append('file_type', fileType);
    for (var i = 0; i < input.files.length; i++) {
        formData.append('file', input.files[i]);
        // 一次上傳一個
        var fd = new FormData();
        fd.append('csrf_token', '<?= e(Session::getCsrfToken()) ?>');
        fd.append('customer_id', '<?= $customer['id'] ?>');
        fd.append('file_type', fileType);
        fd.append('file', input.files[i]);

        var xhr = new XMLHttpRequest();
        xhr.open('POST', '/customers.php?action=upload_file', false);
        xhr.send(fd);
    }
    location.reload();
}
</script>

<style>
.detail-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 8px; }
.detail-item { display: flex; flex-direction: column; }
.detail-label { font-size: .8rem; color: var(--gray-500); }
.tab-nav { display: flex; gap: 0; border-bottom: 2px solid var(--gray-200); margin-bottom: 16px; overflow-x: auto; }
.tab-btn {
    padding: 10px 20px; border: none; background: none; cursor: pointer;
    font-size: .9rem; color: var(--gray-500); border-bottom: 2px solid transparent;
    margin-bottom: -2px; white-space: nowrap; transition: color .15s, border-color .15s;
}
.tab-btn:hover { color: var(--gray-700); }
.tab-btn.active { color: var(--primary); border-bottom-color: var(--primary); font-weight: 600; }
.tab-content { display: none; }
.tab-content.active { display: block; }
@media (max-width: 767px) { .detail-grid { grid-template-columns: 1fr; } }
</style>

<script>
function switchTab(tabName) {
    var contents = document.querySelectorAll('.tab-content');
    var buttons = document.querySelectorAll('.tab-btn');
    for (var i = 0; i < contents.length; i++) {
        contents[i].classList.remove('active');
    }
    for (var i = 0; i < buttons.length; i++) {
        buttons[i].classList.remove('active');
    }
    document.getElementById('tab-' + tabName).classList.add('active');
    event.currentTarget.classList.add('active');
}

// Auto-switch to tab from URL
var urlParams = new URLSearchParams(window.location.search);
var tabParam = urlParams.get('tab');
if (tabParam) {
    var tabEl = document.getElementById('tab-' + tabParam);
    if (tabEl) {
        var allC = document.querySelectorAll('.tab-content');
        var allB = document.querySelectorAll('.tab-btn');
        for (var i = 0; i < allC.length; i++) allC[i].classList.remove('active');
        for (var i = 0; i < allB.length; i++) allB[i].classList.remove('active');
        tabEl.classList.add('active');
        // Find matching button
        var btns = document.querySelectorAll('.tab-btn');
        for (var i = 0; i < btns.length; i++) {
            if (btns[i].textContent.indexOf('文件') >= 0 && tabParam === 'files') btns[i].classList.add('active');
        }
    }
}
</script>
