<div class="d-flex justify-between align-center flex-wrap gap-1 mb-2">
    <h2>選單管理</h2>
</div>

<!-- 主分頁 -->
<div class="filter-pills mb-1">
    <div class="pill-group">
        <a href="/dropdown_options.php?tab=dropdown" class="pill">表單選項設定</a>
        <a href="/dropdown_options.php?tab=roles" class="pill">人員角色</a>
        <a href="/dropdown_options.php?tab=numbering" class="pill">自動編號設定</a>
        <a href="/dropdown_options.php?tab=quotation" class="pill pill-active">報價單設定</a>
    </div>
</div>

<?php
$qCompany = isset($_GET['company']) ? $_GET['company'] : 'hershun';
$settingsPrefix = ($qCompany === 'lichuang') ? 'lc_' : '';

// 把 lc_ 開頭的設定映射回原本的 key 名（用於顯示理創時）
$qs = $settings;
if ($qCompany === 'lichuang') {
    foreach ($settings as $k => $v) {
        if (strpos($k, 'lc_quote_') === 0) {
            $qs[substr($k, 3)] = $v;
        }
    }
}

// 依公司取該公司的分公司清單
$hershunBranchNames  = array('潭子分公司', '清水分公司', '員林分公司', '竹南分公司');
$lichuangBranchNames = array('東區電子鎖', '清水電子鎖');
$branchNames = ($qCompany === 'lichuang') ? $lichuangBranchNames : $hershunBranchNames;
$ph = implode(',', array_fill(0, count($branchNames), '?'));
$bStmt = Database::getInstance()->prepare("SELECT id, name FROM branches WHERE name IN ($ph) ORDER BY id");
$bStmt->execute($branchNames);
$companyBranches = array();
foreach ($bStmt->fetchAll(PDO::FETCH_ASSOC) as $b) {
    $displayName = $b['name'];
    if ($displayName === '潭子分公司') $displayName = '台中（潭子）';
    $companyBranches[(int)$b['id']] = $displayName;
}

$currentBranchId = isset($_GET['branch']) ? (int)$_GET['branch'] : 0;
$isBranchMode = $currentBranchId > 0 && isset($companyBranches[$currentBranchId]);

// 分公司特定欄位值
$branchKey = function($baseKey) use ($qs, $settingsPrefix, $currentBranchId) {
    $k = $settingsPrefix . $baseKey . '_b' . $currentBranchId;
    return isset($qs[$k]) ? $qs[$k] : '';
};
?>

<!-- 公司子分頁 -->
<div class="filter-pills mb-1">
    <div class="pill-group">
        <a href="/dropdown_options.php?tab=quotation&company=hershun" class="pill <?= $qCompany === 'hershun' ? 'pill-active' : '' ?>">禾順報價單設定</a>
        <a href="/dropdown_options.php?tab=quotation&company=lichuang" class="pill <?= $qCompany === 'lichuang' ? 'pill-active' : '' ?>">理創報價單設定</a>
    </div>
</div>

<!-- 分公司子分頁（共用 + 各分公司） -->
<div class="filter-pills mb-2" style="padding-left:8px">
    <div class="pill-group">
        <a href="/dropdown_options.php?tab=quotation&company=<?= e($qCompany) ?>" class="pill <?= !$isBranchMode ? 'pill-active' : '' ?>">共用設定</a>
        <?php foreach ($companyBranches as $bid => $bname): ?>
        <a href="/dropdown_options.php?tab=quotation&company=<?= e($qCompany) ?>&branch=<?= $bid ?>"
           class="pill <?= ($currentBranchId === $bid) ? 'pill-active' : '' ?>"><?= e($bname) ?></a>
        <?php endforeach; ?>
    </div>
</div>

<?php if (!empty($flash_success)): ?>
<div class="alert alert-success"><?= e($flash_success) ?></div>
<?php endif; ?>

<form method="POST" action="/dropdown_options.php?action=save_quotation_settings" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <input type="hidden" name="quote_company_prefix" value="<?= e($settingsPrefix) ?>">
    <input type="hidden" name="quote_company" value="<?= e($qCompany) ?>">
    <input type="hidden" name="quote_branch_id" value="<?= $isBranchMode ? $currentBranchId : 0 ?>">

<?php if ($isBranchMode): ?>
    <!-- ========== 分公司專屬設定（4 欄） ========== -->
    <div class="card" style="border-left:4px solid #1565c0">
        <div class="card-header">
            <?= $qCompany === 'lichuang' ? '理創' : '禾順' ?> — <?= e($companyBranches[$currentBranchId]) ?>（分公司專屬）
        </div>
        <div class="form-row">
            <div class="form-group" style="flex:2">
                <label>公司抬頭（列印用）</label>
                <input type="text" name="quote_company_title" class="form-control" value="<?= e($branchKey('quote_company_title')) ?>" placeholder="例：禾順監視數位科技-清水分公司">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group" style="flex:2">
                <label>地址</label>
                <input type="text" name="quote_contact_address" class="form-control" value="<?= e($branchKey('quote_contact_address')) ?>">
            </div>
            <div class="form-group">
                <label>電話</label>
                <input type="text" name="quote_contact_phone" class="form-control" value="<?= e($branchKey('quote_contact_phone')) ?>">
            </div>
            <div class="form-group">
                <label>傳真</label>
                <input type="text" name="quote_contact_fax" class="form-control" value="<?= e($branchKey('quote_contact_fax')) ?>">
            </div>
        </div>
        <p class="text-muted" style="font-size:.85rem;margin:8px 0 0">
            留空時，報價單列印會自動 fallback 到「共用設定」的對應欄位。
        </p>
    </div>

<?php else: ?>
    <!-- ========== 共用設定（副標題/匯款/服務/保固/圖章/QR） ========== -->
    <!-- 副標題 -->
    <div class="card">
        <div class="card-header"><?= $qCompany === 'lichuang' ? '理創' : '禾順' ?> — 共用資訊</div>
        <div class="form-row">
            <div class="form-group" style="flex:2">
                <label>副標題</label>
                <input type="text" name="quote_company_subtitle" class="form-control" value="<?= e($qs[$settingsPrefix . 'quote_company_subtitle'] ?? ($qCompany === 'lichuang' ? '電子鎖/保險箱/智能捲衣架/投下處理機' : '監控系統/電話總機/影視對講/門禁管制/商用音響/網路工程')) ?>">
            </div>
        </div>
        <p class="text-muted" style="font-size:.85rem;margin:0 0 4px">
            分公司專屬的「公司抬頭 / 地址 / 電話 / 傳真」請點上方對應分公司設定。
        </p>
    </div>

    <!-- 匯款資料 -->
    <div class="card">
        <div class="card-header">匯款資料</div>
        <div class="form-row">
            <div class="form-group">
                <label>戶名</label>
                <input type="text" name="quote_bank_name" class="form-control" value="<?= e($qs[$settingsPrefix . 'quote_bank_name'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>銀行/分行</label>
                <input type="text" name="quote_bank_branch" class="form-control" value="<?= e($qs[$settingsPrefix . 'quote_bank_branch'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>帳號</label>
                <input type="text" name="quote_bank_account" class="form-control" value="<?= e($qs[$settingsPrefix . 'quote_bank_account'] ?? '') ?>">
            </div>
        </div>
    </div>

    <!-- 服務／LINE -->
    <div class="card">
        <div class="card-header">服務專線 / LINE</div>
        <div class="form-row">
            <div class="form-group">
                <label>服務專線</label>
                <input type="text" name="quote_service_phone" class="form-control" value="<?= e($qs[$settingsPrefix . 'quote_service_phone'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>LINE ID</label>
                <input type="text" name="quote_line_id" class="form-control" value="<?= e($qs[$settingsPrefix . 'quote_line_id'] ?? '') ?>">
            </div>
        </div>
    </div>

    <!-- 提醒文字 -->
    <div class="card">
        <div class="card-header">提醒與附註</div>
        <div class="form-row">
            <div class="form-group" style="flex:1">
                <label>匯款提醒</label>
                <input type="text" name="quote_bank_reminder" class="form-control" value="<?= e($qs[$settingsPrefix . 'quote_bank_reminder'] ?? '') ?>">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group" style="flex:1">
                <label>訂金附註</label>
                <input type="text" name="quote_deposit_notice" class="form-control" value="<?= e($qs[$settingsPrefix . 'quote_deposit_notice'] ?? '') ?>">
            </div>
        </div>
    </div>

    <!-- 保固設定 -->
    <div class="card">
        <div class="card-header">保固條款</div>
        <div class="form-row">
            <div class="form-group" style="flex:0 0 200px">
                <label>預設保固月數</label>
                <?php $wm = $qs[$settingsPrefix . 'quote_warranty_months'] ?? '12'; ?>
                <select name="quote_warranty_months" class="form-control">
                    <option value="12" <?= $wm == '12' ? 'selected' : '' ?>>12 個月</option>
                    <option value="24" <?= $wm == '24' ? 'selected' : '' ?>>24 個月</option>
                    <option value="36" <?= $wm == '36' ? 'selected' : '' ?>>36 個月</option>
                </select>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group" style="flex:1">
                <label>保固條款一 <small class="text-muted">（{months} 會自動替換為保固月數）</small></label>
                <textarea name="quote_warranty_text_1" class="form-control" rows="3"><?= e($qs[$settingsPrefix . 'quote_warranty_text_1'] ?? '') ?></textarea>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group" style="flex:1">
                <label>保固條款二</label>
                <textarea name="quote_warranty_text_2" class="form-control" rows="3"><?= e($qs[$settingsPrefix . 'quote_warranty_text_2'] ?? '') ?></textarea>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group" style="flex:1">
                <label>保固條款三（付款方式）</label>
                <textarea name="quote_warranty_text_3" class="form-control" rows="2"><?= e($qs[$settingsPrefix . 'quote_warranty_text_3'] ?? '') ?></textarea>
            </div>
        </div>
    </div>

    <!-- 圖章與 QR Code -->
    <div class="card">
        <div class="card-header">報價章 / QR Code</div>
        <div class="form-row">
            <div class="form-group">
                <label>報價章圖片</label>
                <?php if (!empty($qs[$settingsPrefix . 'quote_stamp_image'])): ?>
                <div class="mb-1"><img src="/<?= e($qs[$settingsPrefix . 'quote_stamp_image']) ?>" style="max-height:80px;border:1px solid #ddd;border-radius:4px;padding:4px"></div>
                <label class="checkbox-label"><input type="checkbox" name="remove_stamp" value="1"> 移除現有圖片</label>
                <?php endif; ?>
                <input type="file" name="stamp_image" class="form-control" accept="image/*">
                <small class="text-muted">建議 PNG 透明背景，寬度 200px 左右</small>
            </div>
            <div class="form-group">
                <label>QR Code 圖片</label>
                <?php if (!empty($qs[$settingsPrefix . 'quote_qrcode_image'])): ?>
                <div class="mb-1"><img src="/<?= e($qs[$settingsPrefix . 'quote_qrcode_image']) ?>" style="max-height:80px;border:1px solid #ddd;border-radius:4px;padding:4px"></div>
                <label class="checkbox-label"><input type="checkbox" name="remove_qrcode" value="1"> 移除現有圖片</label>
                <?php endif; ?>
                <input type="file" name="qrcode_image" class="form-control" accept="image/*">
                <small class="text-muted">建議 PNG，寬度 150px 左右</small>
            </div>
        </div>
    </div>
<?php endif; ?>

    <div class="d-flex justify-end gap-2 mt-2">
        <button type="submit" class="btn btn-primary">儲存設定</button>
    </div>
</form>
