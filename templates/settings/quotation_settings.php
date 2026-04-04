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
// 理創設定用 lc_ 前綴，禾順用原本的 key
$qs = $settings; // 原本的 settings
if ($qCompany === 'lichuang') {
    // 把 lc_ 開頭的設定映射回原本的 key 名
    foreach ($settings as $k => $v) {
        if (strpos($k, 'lc_quote_') === 0) {
            $qs[substr($k, 3)] = $v; // lc_quote_xxx → quote_xxx
        }
    }
}
?>

<!-- 公司子分頁 -->
<div class="filter-pills mb-1">
    <div class="pill-group">
        <a href="/dropdown_options.php?tab=quotation&company=hershun" class="pill <?= $qCompany === 'hershun' ? 'pill-active' : '' ?>">禾順報價單設定</a>
        <a href="/dropdown_options.php?tab=quotation&company=lichuang" class="pill <?= $qCompany === 'lichuang' ? 'pill-active' : '' ?>">理創報價單設定</a>
    </div>
</div>

<?php if (!empty($flash_success)): ?>
<div class="alert alert-success"><?= e($flash_success) ?></div>
<?php endif; ?>

<form method="POST" action="/dropdown_options.php?action=save_quotation_settings" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <input type="hidden" name="quote_company_prefix" value="<?= e($settingsPrefix) ?>">

    <!-- 公司抬頭 -->
    <div class="card">
        <div class="card-header"><?= $qCompany === 'lichuang' ? '理創' : '禾順' ?> — 公司抬頭</div>
        <div class="form-row">
            <div class="form-group" style="flex:2">
                <label>公司名稱（列印用）</label>
                <input type="text" name="quote_company_title" class="form-control" value="<?= e($qs[$settingsPrefix . 'quote_company_title'] ?? ($qCompany === 'lichuang' ? '政達企業有限公司' : '禾順監視數位科技-台中分公司')) ?>">
            </div>
            <div class="form-group" style="flex:2">
                <label>副標題</label>
                <input type="text" name="quote_company_subtitle" class="form-control" value="<?= e($qs[$settingsPrefix . 'quote_company_subtitle'] ?? ($qCompany === 'lichuang' ? '電子鎖/保險箱/智能捲衣架/投下處理機' : '監控系統/電話總機/影視對講/門禁管制/商用音響/網路工程')) ?>">
            </div>
        </div>
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

    <!-- 連絡資訊 -->
    <div class="card">
        <div class="card-header">連絡資訊</div>
        <div class="form-row">
            <div class="form-group" style="flex:2">
                <label>地址</label>
                <input type="text" name="quote_contact_address" class="form-control" value="<?= e($qs[$settingsPrefix . 'quote_contact_address'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>電話</label>
                <input type="text" name="quote_contact_phone" class="form-control" value="<?= e($qs[$settingsPrefix . 'quote_contact_phone'] ?? '') ?>">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>傳真</label>
                <input type="text" name="quote_contact_fax" class="form-control" value="<?= e($qs[$settingsPrefix . 'quote_contact_fax'] ?? '') ?>">
            </div>
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

    <div class="d-flex justify-end gap-2 mt-2">
        <button type="submit" class="btn btn-primary">儲存設定</button>
    </div>
</form>
