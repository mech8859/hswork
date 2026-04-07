<?php
$categoryOptions = CustomerModel::categoryOptions();
$fileTypeOptions = CustomerModel::fileTypeOptions();
$canManage = isset($canManage) ? $canManage : Auth::hasPermission('customers.manage');
$isEdit = !empty($customer);
?>

<!-- 頁首 -->
<div class="d-flex justify-between align-center flex-wrap gap-1 mb-2">
    <div>
        <h2><?= $isEdit ? e($customer['name']) : '新增客戶' ?></h2>
        <?php if ($isEdit): ?>
        <span class="badge"><?= e($customer['customer_no']) ?></span>
        <?php if (!empty($customer['case_number'])): ?>
        <span class="badge" style="background:#e8f5e9;color:#2e7d32"><?= e($customer['case_number']) ?></span>
        <?php endif; ?>
        <?php if (!empty($customer['legacy_customer_no'])): ?>
        <span class="badge" style="background:#f5f5f5;color:#666"><?= e($customer['legacy_customer_no']) ?></span>
        <?php endif; ?>
        <?php if (!empty($customer['category'])): ?>
        <span class="badge badge-primary"><?= e($categoryOptions[$customer['category']] ?? $customer['category']) ?></span>
        <?php endif; ?>
        <?php if (!empty($customer['is_blacklisted'])): ?>
        <span class="badge" style="background:var(--danger);color:#fff">黑名單</span>
        <?php endif; ?>
        <?php endif; ?>
    </div>
    <div class="d-flex gap-1">
        <a href="javascript:history.back()" class="btn btn-outline btn-sm">返回</a>
    </div>
</div>

<!-- 區域導航 -->
<?php if ($isEdit): ?>
<div class="section-nav">
    <a href="#sec-basic" class="sec-link active">基本資料</a>
    <a href="#sec-invoice" class="sec-link">發票資訊</a>
    <a href="#sec-addresses" class="sec-link">地址資訊</a>
    <a href="#sec-cases" class="sec-link">關聯案件 <span class="badge" style="font-size:.7rem;padding:1px 6px;background:#eee;color:#666"><?= count($cases) ?></span></a>
    <a href="#sec-deals" class="sec-link">成交紀錄 <span class="badge" style="font-size:.7rem;padding:1px 6px;background:#eee;color:#666"><?= count($deals) ?></span></a>
    <a href="#sec-transactions" class="sec-link">帳款交易 <span class="badge" style="font-size:.7rem;padding:1px 6px;background:#eee;color:#666"><?= count($transactions) ?></span></a>
    <a href="#sec-files" class="sec-link">文件管理 <span class="badge" style="font-size:.7rem;padding:1px 6px;background:#eee;color:#666"><?= count($files) + (isset($repairPhotos) ? count($repairPhotos) : 0) ?></span></a>
    <a href="#sec-notes" class="sec-link">備註</a>
</div>
<?php endif; ?>

<?php require __DIR__ . '/../layouts/editing_lock_warning.php'; ?>

<form method="POST" class="mt-2" id="customerForm">
    <?= csrf_field() ?>

    <!-- sec-basic: 基本資料 -->
    <div class="card <?= $canManage ? '' : 'section-readonly' ?>" id="sec-basic">
        <div class="card-header">基本資料</div>
        <div class="form-row">
            <div class="form-group" style="flex:0 0 160px">
                <label>客戶編號</label>
                <input type="text" class="form-control" value="<?= e($isEdit ? $customer['customer_no'] : peek_next_doc_number('customers')) ?>" readonly style="background:#f0f7ff;font-weight:600;color:var(--primary)">
            </div>
            <div class="form-group" style="flex:0 0 140px">
                <label>進件編號</label>
                <input type="text" name="case_number" class="form-control" value="<?= e($customer['case_number'] ?? '') ?>" readonly style="background:#f5f5f5;color:#666">
            </div>
            <div class="form-group" style="flex:0 0 120px">
                <label>進件日期</label>
                <input type="text" class="form-control" value="<?= e($customer['case_date'] ?? '') ?>" readonly style="background:#f5f5f5;color:#666">
            </div>
            <div class="form-group" style="flex:0 0 120px">
                <label>進件分公司</label>
                <input type="text" class="form-control" value="<?= e($customer['source_company'] ?? '') ?>" readonly style="background:#f5f5f5;color:#666">
            </div>
            <div class="form-group" style="flex:0 0 140px">
                <label>原客戶編號</label>
                <input type="text" name="legacy_customer_no" class="form-control" value="<?= e($customer['legacy_customer_no'] ?? '') ?>" placeholder="匯入用">
            </div>
            <div class="form-group" style="flex:2;min-width:200px">
                <label>客戶名稱 *</label>
                <input type="text" name="name" class="form-control" value="<?= e($customer['name'] ?? '') ?>" required>
            </div>
            <div class="form-group">
                <label>客戶分類</label>
                <select name="category" class="form-control">
                    <option value="">請選擇</option>
                    <?php foreach ($categoryOptions as $key => $label): ?>
                    <option value="<?= e($key) ?>" <?= ($customer['category'] ?? '') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <!-- 多筆聯絡人 -->
        <div style="border-top:1px solid var(--gray-200);padding-top:12px;margin-top:8px">
            <div class="d-flex justify-between align-center mb-1">
                <label style="font-weight:600;margin:0">聯絡人</label>
                <?php if ($canManage): ?>
                <button type="button" class="btn btn-outline btn-sm" onclick="addCustContact()">+ 新增聯絡人</button>
                <?php endif; ?>
            </div>
            <div id="custContactsContainer">
                <?php
                $custContacts = isset($customer['contacts']) ? $customer['contacts'] : array();
                if (empty($custContacts) && !empty($customer['contact_person'])) {
                    $custContacts = array(array(
                        'contact_name' => $customer['contact_person'],
                        'phone' => $customer['phone'] ?? '',
                        'mobile' => $customer['mobile'] ?? '',
                        'role' => '',
                    ));
                }
                foreach ($custContacts as $ci => $cc):
                ?>
                <div class="cust-contact-row" data-index="<?= $ci ?>">
                    <div class="form-row">
                        <div class="form-group"><label>姓名</label><input type="text" name="contacts[<?= $ci ?>][contact_name]" class="form-control" value="<?= e($cc['contact_name'] ?? '') ?>"></div>
                        <div class="form-group"><label>電話</label><input type="text" name="contacts[<?= $ci ?>][phone]" class="form-control" value="<?= e($cc['phone'] ?? '') ?>"></div>
                        <div class="form-group"><label>手機</label><input type="text" name="contacts[<?= $ci ?>][mobile]" class="form-control" value="<?= e($cc['mobile'] ?? '') ?>"></div>
                        <div class="form-group"><label>角色</label><input type="text" name="contacts[<?= $ci ?>][role]" class="form-control" value="<?= e($cc['role'] ?? '') ?>" placeholder="屋主/管委會/總機"></div>
                        <?php if ($canManage): ?>
                        <div class="form-group" style="align-self:flex-end;flex:0 0 auto"><button type="button" class="btn btn-danger btn-sm" onclick="this.closest('.cust-contact-row').remove()">刪除</button></div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <input type="hidden" name="contact_person" value="<?= e($customer['contact_person'] ?? '') ?>">
        <div class="form-row" style="margin-top:12px">
            <div class="form-group">
                <label>傳真</label>
                <input type="text" name="fax" class="form-control" value="<?= e($customer['fax'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" class="form-control" value="<?= e($customer['email'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>LINE ID</label>
                <input type="text" name="line_id" class="form-control" value="<?= e($customer['line_id'] ?? '') ?>">
            </div>
        </div>
        <div class="form-row" style="margin-top:12px">
            <div class="form-group" style="flex:0 0 160px">
                <label>完工日期</label>
                <input type="date" name="completion_date" id="completion_date" class="form-control" value="<?= e($customer['completion_date'] ?? '') ?>">
            </div>
            <div class="form-group" style="flex:0 0 120px">
                <label>保固月數</label>
                <?php
                    $wm = 12;
                    if (!empty($customer['completion_date']) && !empty($customer['warranty_date'])) {
                        $cd = new DateTime($customer['completion_date']);
                        $wd = new DateTime($customer['warranty_date']);
                        $diff = $cd->diff($wd);
                        $wm = $diff->y * 12 + $diff->m;
                        if ($wm < 1) $wm = 12;
                    }
                    $isCustom = !in_array($wm, array(12, 24, 36));
                ?>
                <select id="warranty_months_select" class="form-control" style="text-align:center">
                    <option value="12" <?= $wm == 12 ? 'selected' : '' ?>>12 個月</option>
                    <option value="24" <?= $wm == 24 ? 'selected' : '' ?>>24 個月</option>
                    <option value="36" <?= $wm == 36 ? 'selected' : '' ?>>36 個月</option>
                    <option value="custom" <?= $isCustom ? 'selected' : '' ?>>自訂</option>
                </select>
                <input type="number" id="warranty_months" class="form-control" min="1" max="120" value="<?= $wm ?>" style="text-align:center;margin-top:4px;<?= $isCustom ? '' : 'display:none' ?>">
            </div>
            <div class="form-group" style="flex:0 0 160px">
                <label>保固日期</label>
                <input type="date" name="warranty_date" id="warranty_date" class="form-control" value="<?= e($customer['warranty_date'] ?? '') ?>" readonly style="background:#f5f5f5">
            </div>
            <div class="form-group">
                <label>業務人員</label>
                <select name="sales_id" class="form-control">
                    <option value="">未指定</option>
                    <?php foreach ($salespeople as $sp): ?>
                    <option value="<?= $sp['id'] ?>" <?= ($customer['sales_id'] ?? '') == $sp['id'] ? 'selected' : '' ?>><?= e($sp['real_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>

    <!-- sec-invoice: 發票資訊 -->
    <div class="card <?= $canManage ? '' : 'section-readonly' ?>" id="sec-invoice">
        <div class="card-header">發票資訊</div>
        <div class="form-row">
            <div class="form-group">
                <label>發票抬頭</label>
                <input type="text" name="invoice_title" class="form-control" value="<?= e($customer['invoice_title'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>統一編號</label>
                <input type="text" name="tax_id" class="form-control" value="<?= e($customer['tax_id'] ?? '') ?>" maxlength="8">
            </div>
        </div>
        <div class="form-group">
            <label>發票寄送 Email</label>
            <input type="email" name="invoice_email" class="form-control" value="<?= e($customer['invoice_email'] ?? '') ?>">
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>付款方式</label>
                <input type="text" name="payment_method" class="form-control" value="<?= e($customer['payment_method'] ?? '') ?>" placeholder="例：匯款/支票/現金">
            </div>
            <div class="form-group">
                <label>付款條件</label>
                <input type="text" name="payment_terms" class="form-control" value="<?= e($customer['payment_terms'] ?? '') ?>" placeholder="例：月結30天">
            </div>
        </div>
    </div>

    <!-- sec-addresses: 地址資訊 -->
    <div class="card <?= $canManage ? '' : 'section-readonly' ?>" id="sec-addresses">
        <div class="card-header">帳單地址</div>
        <div class="form-row">
            <div class="form-group" style="flex:0 0 120px">
                <label>縣市</label>
                <input type="text" name="billing_city" id="billing_city" class="form-control" value="<?= e($customer['billing_city'] ?? '') ?>" placeholder="例：台中市">
            </div>
            <div class="form-group" style="flex:0 0 120px">
                <label>區域</label>
                <input type="text" name="billing_district" id="billing_district" class="form-control" value="<?= e($customer['billing_district'] ?? '') ?>" placeholder="例：西屯區">
            </div>
            <div class="form-group">
                <label>地址</label>
                <input type="text" name="billing_address" id="billing_address" class="form-control" value="<?= e($customer['billing_address'] ?? '') ?>">
            </div>
        </div>
        <div style="border-top:1px solid var(--gray-200);padding-top:12px;margin-top:12px">
            <div class="d-flex justify-between align-center mb-1">
                <strong>施工地址</strong>
                <?php if ($canManage): ?>
                <button type="button" class="btn btn-outline btn-sm" onclick="copyBillingToSite()">同帳單地址</button>
                <?php endif; ?>
            </div>
            <div class="form-row">
                <div class="form-group" style="flex:0 0 120px">
                    <label>縣市</label>
                    <input type="text" name="site_city" id="site_city" class="form-control" value="<?= e($customer['site_city'] ?? '') ?>" placeholder="例：台中市">
                </div>
                <div class="form-group" style="flex:0 0 120px">
                    <label>區域</label>
                    <input type="text" name="site_district" id="site_district" class="form-control" value="<?= e($customer['site_district'] ?? '') ?>" placeholder="例：西屯區">
                </div>
                <div class="form-group">
                    <label>地址</label>
                    <textarea name="site_address" id="site_address" class="form-control" rows="2" style="resize:vertical"><?= e($customer['site_address'] ?? '') ?></textarea>
                </div>
            </div>
        </div>
        <?php
        $siteFullAddr = trim(($customer['site_city'] ?? '') . ($customer['site_district'] ?? '') . ($customer['site_address'] ?? ''));
        if ($isEdit && $siteFullAddr):
        ?>
        <div class="mt-1">
            <iframe src="https://maps.google.com/maps?q=<?= urlencode($siteFullAddr) ?>&output=embed&hl=zh-TW" style="width:100%;max-width:480px;height:200px;border:1px solid var(--gray-200);border-radius:6px" allowfullscreen loading="lazy"></iframe>
        </div>
        <?php endif; ?>
    </div>

    <!-- sec-notes: 備註 -->
    <div class="card <?= $canManage ? '' : 'section-readonly' ?>" id="sec-notes">
        <div class="card-header">其他</div>
        <div class="form-group">
            <label>備註</label>
            <textarea name="note" class="form-control" rows="3"><?= e($customer['note'] ?? '') ?></textarea>
        </div>
        <div style="border-top:1px solid var(--gray-200);padding-top:12px;margin-top:8px">
            <label class="checkbox-label" style="display:inline-flex;align-items:center;gap:6px;cursor:pointer">
                <input type="hidden" name="is_blacklisted" value="0">
                <input type="checkbox" name="is_blacklisted" value="1" id="chk_blacklist"
                    <?= !empty($customer['is_blacklisted']) ? 'checked' : '' ?>
                    onchange="document.getElementById('blacklist_reason_wrap').style.display = this.checked ? 'block' : 'none'">
                <span style="color:var(--danger);font-weight:600">⚠ 黑名單</span>
            </label>
            <div id="blacklist_reason_wrap" style="margin-top:8px;<?= empty($customer['is_blacklisted']) ? 'display:none' : '' ?>">
                <label>黑名單原因</label>
                <textarea name="blacklist_reason" class="form-control" rows="2" placeholder="請填寫列入黑名單的原因"><?= e($customer['blacklist_reason'] ?? '') ?></textarea>
            </div>
        </div>
    </div>

    <!-- 儲存按鈕（固定底部）-->
    <?php if ($canManage): ?>
    <div class="form-actions">
        <button type="submit" class="btn btn-primary"><?= $isEdit ? '儲存變更' : '建立客戶' ?></button>
        <a href="/customers.php" class="btn btn-outline">取消</a>
    </div>
    <?php endif; ?>
</form>

<?php if ($isEdit): ?>
<!-- ====== 以下為 AJAX 區域（不在 form 內）====== -->

<!-- sec-cases: 關聯案件 -->
<div class="card" id="sec-cases">
    <div class="card-header">關聯案件</div>
    <?php if (empty($cases)): ?>
        <p class="text-muted text-center" style="padding:16px">目前無關聯案件</p>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table">
            <thead><tr><th>進件編號</th><th>案件名稱</th><th>類型</th><th>據點</th><th>狀態</th><th class="text-right">成交金額</th><th>建立日期</th></tr></thead>
            <tbody>
                <?php foreach ($cases as $c): ?>
                <tr>
                    <td><a href="/cases.php?action=edit&id=<?= $c['id'] ?>"><?= e($c['case_number']) ?></a></td>
                    <td><?= e($c['title']) ?></td>
                    <td><?php
                        $typeLabels = array('new_install'=>'新裝','maintenance'=>'維護','repair'=>'維修','inspection'=>'檢修','old_repair'=>'舊有維修','addition'=>'追加','other'=>'其他');
                        echo e(isset($typeLabels[$c['case_type']]) ? $typeLabels[$c['case_type']] : ($c['case_type'] ?: '-'));
                    ?></td>
                    <td><?= e($c['branch_name'] ?: '-') ?></td>
                    <td><?php
                        $statusLabels = array('pending'=>'待處理','ready'=>'可排工','scheduled'=>'已排工','in_progress'=>'施工中','completed'=>'已完工','closed'=>'已結案','cancelled'=>'已取消','tracking'=>'追蹤中','incomplete'=>'未完工','unpaid'=>'未收款');
                        echo e(isset($statusLabels[$c['status']]) ? $statusLabels[$c['status']] : ($c['status'] ?: '-'));
                    ?></td>
                    <td class="text-right"><?= $c['deal_amount'] ? '$' . number_format($c['deal_amount']) : '-' ?></td>
                    <td><?= $c['created_at'] ? date('Y-m-d', strtotime($c['created_at'])) : '-' ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- sec-deals: 成交紀錄 -->
<div class="card" id="sec-deals">
    <div class="card-header d-flex justify-between align-center">
        <span>成交紀錄</span>
        <?php if ($canManage): ?>
        <button type="button" class="btn btn-outline btn-sm" onclick="toggleDealForm()">+ 新增</button>
        <?php endif; ?>
    </div>
    <?php if ($canManage): ?>
    <div id="dealFormWrap" style="display:none;padding:12px;border-bottom:1px solid var(--gray-200);background:#fafbfc">
        <div class="form-row">
            <div class="form-group"><label>施工地址</label><input type="text" id="deal_site_address" class="form-control"></div>
            <div class="form-group"><label>成交金額</label><input type="number" id="deal_amount" class="form-control"></div>
            <div class="form-group"><label>完工日期</label><input type="date" id="deal_completion_date" class="form-control"></div>
            <div class="form-group"><label>保固到期</label><input type="date" id="deal_warranty_date" class="form-control"></div>
        </div>
        <div class="form-group"><label>備註</label><input type="text" id="deal_note" class="form-control"></div>
        <div class="d-flex gap-1 mt-1">
            <button type="button" class="btn btn-primary btn-sm" onclick="submitDeal()">儲存</button>
            <button type="button" class="btn btn-outline btn-sm" onclick="toggleDealForm()">取消</button>
        </div>
    </div>
    <?php endif; ?>
    <?php if (empty($deals)): ?>
        <p class="text-muted text-center" style="padding:16px" id="deals-empty">目前無成交紀錄</p>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table">
            <thead><tr><th>案件</th><th>施工地址</th><th class="text-right">成交金額</th><th>完工日期</th><th>保固到期</th><th>備註</th><?php if ($canManage): ?><th></th><?php endif; ?></tr></thead>
            <tbody>
                <?php foreach ($deals as $d): ?>
                <tr>
                    <td><?= !empty($d['case_number']) ? '<a href="/cases.php?action=edit&id='.$d['case_id'].'">'.e($d['case_number']).'</a>' : '-' ?></td>
                    <td><?= e($d['site_address'] ?: '-') ?></td>
                    <td class="text-right"><?= $d['deal_amount'] ? '$' . number_format($d['deal_amount']) : '-' ?></td>
                    <td><?= e($d['completion_date'] ?: '-') ?></td>
                    <td><?= e($d['warranty_date'] ?: '-') ?></td>
                    <td><?= e($d['note'] ?: '-') ?></td>
                    <?php if ($canManage): ?>
                    <td><button type="button" class="btn btn-danger btn-sm" onclick="deleteDeal(<?= $d['id'] ?>)">刪除</button></td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- sec-transactions: 帳款交易 -->
<div class="card" id="sec-transactions">
    <div class="card-header d-flex justify-between align-center">
        <span>帳款交易</span>
        <?php if ($canManage): ?>
        <button type="button" class="btn btn-outline btn-sm" onclick="toggleTransForm()">+ 新增</button>
        <?php endif; ?>
    </div>
    <?php if ($canManage): ?>
    <div id="transFormWrap" style="display:none;padding:12px;border-bottom:1px solid var(--gray-200);background:#fafbfc">
        <div class="form-row">
            <div class="form-group"><label>交易日期</label><input type="date" id="trans_date" class="form-control"></div>
            <div class="form-group"><label>說明</label><input type="text" id="trans_desc" class="form-control"></div>
            <div class="form-group"><label>金額</label><input type="number" id="trans_amount" class="form-control"></div>
            <div class="form-group"><label>備註</label><input type="text" id="trans_note" class="form-control"></div>
        </div>
        <div class="d-flex gap-1 mt-1">
            <button type="button" class="btn btn-primary btn-sm" onclick="submitTransaction()">儲存</button>
            <button type="button" class="btn btn-outline btn-sm" onclick="toggleTransForm()">取消</button>
        </div>
    </div>
    <?php endif; ?>
    <?php if (empty($transactions)): ?>
        <p class="text-muted text-center" style="padding:16px" id="trans-empty">目前無帳款交易紀錄</p>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table">
            <thead><tr><th>交易日期</th><th>說明</th><th class="text-right">金額</th><th>備註</th><?php if ($canManage): ?><th></th><?php endif; ?></tr></thead>
            <tbody>
                <?php foreach ($transactions as $t): ?>
                <tr>
                    <td><?= e($t['transaction_date'] ?: '-') ?></td>
                    <td><?= e($t['description'] ?: '-') ?></td>
                    <td class="text-right">$<?= number_format($t['amount']) ?></td>
                    <td><?= e($t['note'] ?: '-') ?></td>
                    <?php if ($canManage): ?>
                    <td><button type="button" class="btn btn-danger btn-sm" onclick="deleteTransaction(<?= $t['id'] ?>)">刪除</button></td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- sec-files: 文件管理 -->
<div class="card" id="sec-files">
    <div class="card-header d-flex justify-between align-center">
        <span>文件管理</span>
        <?php if ($canManage): ?>
        <button type="button" class="btn btn-outline btn-sm" onclick="addNewFileType()">+ 新增分類</button>
        <?php endif; ?>
    </div>
    <?php
    $groupedFiles = array();
    foreach ($fileTypeOptions as $k => $v) { $groupedFiles[$k] = array(); }
    if (!empty($files)) {
        foreach ($files as $f) {
            $ft = $f['file_type'] ?: 'other';
            if (!isset($groupedFiles[$ft])) $groupedFiles[$ft] = array();
            $groupedFiles[$ft][] = $f;
        }
    }
    // 將維修單模組照片合併到第一個「維修單」分類
    if (!empty($repairPhotos)) {
        $repairTypeKey = null;
        foreach ($fileTypeOptions as $k => $v) {
            if ($v === '維修單') { $repairTypeKey = $k; break; }
        }
        if ($repairTypeKey !== null) {
            foreach ($repairPhotos as $rp) {
                $groupedFiles[$repairTypeKey][] = array(
                    'id' => null,
                    'file_type' => $repairTypeKey,
                    'file_name' => $rp['repair_number'] . ' ' . basename($rp['file_path']),
                    'file_path' => $rp['file_path'],
                    'file_size' => 0,
                    'source' => 'repair',
                    'repair_number' => $rp['repair_number'],
                    'repair_date' => $rp['repair_date'],
                );
            }
        }
    }
    ?>
    <div class="cust-attach-grid">
        <?php foreach ($fileTypeOptions as $typeKey => $typeLabel): ?>
        <div class="cust-atc-card">
            <div class="cust-atc-header">
                <span class="cust-atc-title"><?= e($typeLabel) ?></span>
                <span class="cust-atc-count"><?= count($groupedFiles[$typeKey]) ?></span>
            </div>
            <div class="cust-atc-files">
                <?php foreach ($groupedFiles[$typeKey] as $f):
                    $ext = strtolower(pathinfo($f['file_name'], PATHINFO_EXTENSION));
                    $isImg = in_array($ext, array('jpg','jpeg','png','gif','webp','bmp'));
                ?>
                <?php $isRepairSrc = !empty($f['source']) && $f['source'] === 'repair'; ?>
                <div class="cust-atc-file <?= $isImg ? 'cust-atc-img' : '' ?>" <?php if ($isRepairSrc): ?>title="<?= e($f['repair_number']) ?> (<?= e($f['repair_date']) ?>)｜來自維修單模組"<?php endif; ?>>
                    <?php if ($isImg): ?>
                    <?php $fpath = ltrim($f['file_path'], '/'); ?>
                    <img src="/<?= e($fpath) ?>" class="cust-atc-thumb hs-photo" onclick="hsOpenImage('/<?= e($fpath) ?>')" alt="<?= e($f['file_name']) ?>"<?php if ($isRepairSrc): ?> style="border-color:var(--primary)"<?php endif; ?>>
                    <?php else: ?>
                    <?php $fpath = ltrim($f['file_path'], '/'); ?>
                    <a href="javascript:void(0)" onclick="hsOpenFile('/<?= e($fpath) ?>','<?= e($f['file_name']) ?>')" class="cust-atc-fname"><?= $isRepairSrc ? '' : '📄 ' ?><?= e($f['file_name']) ?></a>
                    <?php endif; ?>
                    <?php if ($canManage && !$isRepairSrc): ?>
                    <button type="button" class="cust-atc-del" onclick="deleteFile(<?= $f['id'] ?>)">✕</button>
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
<div class="cust-lightbox" id="custLightbox" onclick="custLbBgClick(event)">
    <span class="cust-lb-close" onclick="closeCustLightbox()">&times;</span>
    <span class="cust-lb-prev" onclick="custLbNav(-1)">&#10094;</span>
    <img id="custLbImg" src="" alt="預覽">
    <span class="cust-lb-next" onclick="custLbNav(1)">&#10095;</span>
    <span class="cust-lb-counter" id="custLbCounter"></span>
</div>
<?php endif; ?>

<!-- ====== CSS ====== -->
<style>
/* 區域導航 */
.section-nav { display:flex; gap:4px; flex-wrap:wrap; background:#fff; padding:8px 12px; border-radius:8px; box-shadow:0 1px 3px rgba(0,0,0,.08); position:sticky; top:0; z-index:50; }
.sec-link { padding:6px 14px; font-size:.82rem; color:var(--gray-600); text-decoration:none; border-radius:20px; transition:all .15s; white-space:nowrap; }
.sec-link:hover { background:var(--gray-100); color:var(--primary); }
.sec-link.active { background:var(--primary); color:#fff; }

/* 唯讀區域 */
.section-readonly { position:relative; }
.section-readonly::after { content:'唯讀'; position:absolute; top:10px; right:14px; background:#ef5350; color:#fff; font-size:.7rem; padding:2px 8px; border-radius:10px; font-weight:600; }
.section-readonly input, .section-readonly select, .section-readonly textarea { pointer-events:none; background:#f5f5f5 !important; color:#757575 !important; }
.section-readonly .checkbox-label, .section-readonly button { pointer-events:none; opacity:.5; }
.section-readonly input[type="checkbox"], .section-readonly input[type="radio"] { pointer-events:none; }

/* 固定底部儲存列 */
.form-actions { position:sticky; bottom:0; background:#fff; padding:12px 16px; border-top:1px solid var(--gray-200); display:flex; gap:8px; z-index:50; border-radius:0 0 8px 8px; box-shadow:0 -2px 6px rgba(0,0,0,.06); margin-top:16px; }

/* 表單 */
.form-row { display:flex; flex-wrap:wrap; gap:12px; }
.form-row .form-group { flex:1; min-width:150px; }

/* 文件管理 */
.cust-attach-grid { display:grid; grid-template-columns:repeat(3, 1fr); gap:12px; padding:12px; }
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

/* Lightbox */
.cust-lightbox { display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,.85); z-index:9999; align-items:center; justify-content:center; }
.cust-lightbox.active { display:flex; }
.cust-lightbox img { max-width:85%; max-height:85%; border-radius:8px; box-shadow:0 4px 20px rgba(0,0,0,.5); cursor:default; }
.cust-lb-close { position:absolute; top:16px; right:24px; color:#fff; font-size:2rem; cursor:pointer; z-index:10000; }
.cust-lb-prev, .cust-lb-next { position:absolute; top:50%; transform:translateY(-50%); color:#fff; font-size:2.5rem; cursor:pointer; padding:16px; user-select:none; z-index:10000; transition:opacity .15s; opacity:.7; }
.cust-lb-prev:hover, .cust-lb-next:hover { opacity:1; }
.cust-lb-prev { left:16px; }
.cust-lb-next { right:16px; }
.cust-lb-counter { position:absolute; bottom:20px; left:50%; transform:translateX(-50%); color:#fff; font-size:.85rem; opacity:.7; }

@media (max-width: 767px) { .cust-attach-grid { grid-template-columns:repeat(2, 1fr); } }
@media (max-width: 480px) { .cust-attach-grid { grid-template-columns:1fr; } }
</style>

<!-- ====== JavaScript ====== -->
<script>
// 區域導航滾動高亮
(function() {
    var links = document.querySelectorAll('.sec-link');
    var sections = [];
    links.forEach(function(l) {
        var id = l.getAttribute('href').substring(1);
        var el = document.getElementById(id);
        if (el) sections.push({ el: el, link: l });
    });
    if (!sections.length) return;
    var onScroll = function() {
        var scrollY = window.scrollY + 80;
        var current = sections[0];
        for (var i = 0; i < sections.length; i++) {
            if (sections[i].el.offsetTop <= scrollY) current = sections[i];
        }
        links.forEach(function(l) { l.classList.remove('active'); });
        current.link.classList.add('active');
    };
    window.addEventListener('scroll', onScroll);
    links.forEach(function(l) {
        l.addEventListener('click', function(e) {
            e.preventDefault();
            var id = this.getAttribute('href').substring(1);
            var el = document.getElementById(id);
            if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    });
})();

// 同帳單地址
function copyBillingToSite() {
    document.getElementById('site_city').value = document.getElementById('billing_city').value;
    document.getElementById('site_district').value = document.getElementById('billing_district').value;
    document.getElementById('site_address').value = document.getElementById('billing_address').value;
}

// 聯絡人動態新增
var custContactIdx = <?= count($custContacts) ?>;
function addCustContact() {
    var html = '<div class="cust-contact-row" data-index="' + custContactIdx + '">' +
        '<div class="form-row">' +
        '<div class="form-group"><label>姓名</label><input type="text" name="contacts[' + custContactIdx + '][contact_name]" class="form-control"></div>' +
        '<div class="form-group"><label>電話</label><input type="text" name="contacts[' + custContactIdx + '][phone]" class="form-control"></div>' +
        '<div class="form-group"><label>手機</label><input type="text" name="contacts[' + custContactIdx + '][mobile]" class="form-control"></div>' +
        '<div class="form-group"><label>角色</label><input type="text" name="contacts[' + custContactIdx + '][role]" class="form-control" placeholder="屋主/管委會/總機"></div>' +
        '<div class="form-group" style="align-self:flex-end;flex:0 0 auto"><button type="button" class="btn btn-danger btn-sm" onclick="this.closest(\'.cust-contact-row\').remove()">刪除</button></div>' +
        '</div></div>';
    document.getElementById('custContactsContainer').insertAdjacentHTML('beforeend', html);
    custContactIdx++;
}

// 表單送出時，用第一筆聯絡人更新 hidden contact_person
document.getElementById('customerForm').addEventListener('submit', function() {
    var firstName = document.querySelector('#custContactsContainer input[name*="contact_name"]');
    var hidden = document.querySelector('input[name="contact_person"]');
    if (firstName && hidden) hidden.value = firstName.value;
});

// 保固日期自動計算
function calcWarrantyDate() {
    var cd = document.getElementById('completion_date').value;
    var months = parseInt(document.getElementById('warranty_months').value) || 12;
    if (!cd) { document.getElementById('warranty_date').value = ''; return; }
    var d = new Date(cd);
    d.setMonth(d.getMonth() + months);
    var y = d.getFullYear();
    var m = ('0' + (d.getMonth() + 1)).slice(-2);
    var day = ('0' + d.getDate()).slice(-2);
    document.getElementById('warranty_date').value = y + '-' + m + '-' + day;
}
var wSel = document.getElementById('warranty_months_select');
var wInp = document.getElementById('warranty_months');
wSel.addEventListener('change', function() {
    if (this.value === 'custom') {
        wInp.style.display = '';
        wInp.focus();
    } else {
        wInp.style.display = 'none';
        wInp.value = this.value;
        calcWarrantyDate();
    }
});
document.getElementById('completion_date').addEventListener('change', calcWarrantyDate);
wInp.addEventListener('input', calcWarrantyDate);
// 頁面載入時：如果有完工日期但沒保固日期，自動計算
if (document.getElementById('completion_date').value && !document.getElementById('warranty_date').value) {
    calcWarrantyDate();
}

// Lightbox
var custLbImages = [];
var custLbIndex = 0;
function collectCustImages() {
    custLbImages = [];
    var thumbs = document.querySelectorAll('.cust-atc-thumb');
    for (var i = 0; i < thumbs.length; i++) {
        custLbImages.push(thumbs[i].getAttribute('onclick').replace("openCustLightbox('","").replace("')",""));
    }
}
function openCustLightbox(src) {
    collectCustImages();
    for (var i = 0; i < custLbImages.length; i++) {
        if (custLbImages[i] === src) { custLbIndex = i; break; }
    }
    custLbShow();
}
function custLbShow() {
    var o = document.getElementById('custLightbox');
    o.classList.add('active');
    document.getElementById('custLbImg').src = custLbImages[custLbIndex];
    document.getElementById('custLbCounter').textContent = (custLbIndex + 1) + ' / ' + custLbImages.length;
}
function closeCustLightbox() { var o=document.getElementById('custLightbox'); o.classList.remove('active'); document.getElementById('custLbImg').src=''; }
function custLbNav(dir) { custLbIndex = (custLbIndex + dir + custLbImages.length) % custLbImages.length; custLbShow(); }
function custLbBgClick(e) { if (e.target === document.getElementById('custLightbox')) closeCustLightbox(); }
document.addEventListener('keydown', function(e) {
    if (!document.getElementById('custLightbox').classList.contains('active')) return;
    if (e.key === 'Escape') closeCustLightbox();
    if (e.key === 'ArrowLeft') custLbNav(-1);
    if (e.key === 'ArrowRight') custLbNav(1);
});

<?php if ($isEdit): ?>
var csrfToken = '<?= e(Session::getCsrfToken()) ?>';
var customerId = <?= (int)$customer['id'] ?>;

// ---- 新增文件分類 ----
function addNewFileType() {
    var label = prompt('請輸入新的文件分類名稱：');
    if (!label || !label.trim()) return;
    var fd = new FormData();
    fd.append('csrf_token', csrfToken);
    fd.append('label', label.trim());
    var xhr = new XMLHttpRequest();
    xhr.open('POST', '/customers.php?action=add_file_type', true);
    xhr.onload = function() {
        try {
            var res = JSON.parse(xhr.responseText);
            if (res.success) { location.reload(); }
            else { alert(res.error || '新增失敗'); }
        } catch(e) { alert('新增失敗'); }
    };
    xhr.send(fd);
}

// ---- 文件上傳（AJAX）----
function uploadCustFiles(input, fileType) {
    if (!input.files.length) return;
    var files = Array.prototype.slice.call(input.files);
    compressImages(files).then(function(compressed) {
        var pending = compressed.length;
        for (var i = 0; i < compressed.length; i++) {
            var fd = new FormData();
            fd.append('csrf_token', csrfToken);
            fd.append('customer_id', customerId);
            fd.append('file_type', fileType);
            fd.append('file', compressed[i]);
            var xhr = new XMLHttpRequest();
            xhr.open('POST', '/customers.php?action=upload_file', true);
            xhr.onload = function() {
                pending--;
                if (pending <= 0) location.reload();
            };
            xhr.send(fd);
        }
    });
}

// ---- 刪除文件（AJAX）----
function deleteFile(fileId) {
    if (!confirm('確定刪除此文件？')) return;
    var fd = new FormData();
    fd.append('csrf_token', csrfToken);
    fd.append('file_id', fileId);
    var xhr = new XMLHttpRequest();
    xhr.open('POST', '/customers.php?action=delete_file', true);
    xhr.onload = function() { location.reload(); };
    xhr.send(fd);
}

// ---- 成交紀錄 ----
function toggleDealForm() {
    var w = document.getElementById('dealFormWrap');
    w.style.display = w.style.display === 'none' ? 'block' : 'none';
}
function submitDeal() {
    var fd = new FormData();
    fd.append('csrf_token', csrfToken);
    fd.append('customer_id', customerId);
    fd.append('site_address', document.getElementById('deal_site_address').value);
    fd.append('deal_amount', document.getElementById('deal_amount').value);
    fd.append('completion_date', document.getElementById('deal_completion_date').value);
    fd.append('warranty_date', document.getElementById('deal_warranty_date').value);
    fd.append('note', document.getElementById('deal_note').value);
    var xhr = new XMLHttpRequest();
    xhr.open('POST', '/customers.php?action=add_deal', true);
    xhr.onload = function() { location.reload(); };
    xhr.send(fd);
}
function deleteDeal(dealId) {
    if (!confirm('確定刪除此成交紀錄？')) return;
    var fd = new FormData();
    fd.append('csrf_token', csrfToken);
    fd.append('deal_id', dealId);
    var xhr = new XMLHttpRequest();
    xhr.open('POST', '/customers.php?action=delete_deal', true);
    xhr.onload = function() { location.reload(); };
    xhr.send(fd);
}

// ---- 帳款交易 ----
function toggleTransForm() {
    var w = document.getElementById('transFormWrap');
    w.style.display = w.style.display === 'none' ? 'block' : 'none';
}
function submitTransaction() {
    var fd = new FormData();
    fd.append('csrf_token', csrfToken);
    fd.append('customer_id', customerId);
    fd.append('transaction_date', document.getElementById('trans_date').value);
    fd.append('description', document.getElementById('trans_desc').value);
    fd.append('amount', document.getElementById('trans_amount').value);
    fd.append('note', document.getElementById('trans_note').value);
    var xhr = new XMLHttpRequest();
    xhr.open('POST', '/customers.php?action=add_transaction', true);
    xhr.onload = function() { location.reload(); };
    xhr.send(fd);
}
function deleteTransaction(transId) {
    if (!confirm('確定刪除此帳款交易？')) return;
    var fd = new FormData();
    fd.append('csrf_token', csrfToken);
    fd.append('transaction_id', transId);
    var xhr = new XMLHttpRequest();
    xhr.open('POST', '/customers.php?action=delete_transaction', true);
    xhr.onload = function() { location.reload(); };
    xhr.send(fd);
}
<?php endif; ?>
</script>
