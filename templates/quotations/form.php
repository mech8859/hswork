<?php
$isEdit = !empty($quote);
$fmt = $isEdit ? $quote['format'] : 'simple';
$sections = $isEdit ? $quote['sections'] : array(array('title' => '', 'items' => array(array())));
if (!isset($canEdit)) $canEdit = true;
$readOnly = $isEdit && !$canEdit;
require __DIR__ . '/../_readonly_form_helper.php';
?>
<h2><?= $isEdit ? ($readOnly ? '檢視報價單 - ' : '編輯報價單 - ') . e($quote['quotation_number']) : '新增報價單' ?></h2>

<?php require __DIR__ . '/../layouts/editing_lock_warning.php'; ?>

<form method="POST" class="mt-2 <?= $readOnly ? 'form-readonly' : '' ?>" id="quoteForm">
    <?= csrf_field() ?>

    <!-- 客戶資訊 -->
    <div class="card">
        <div class="card-header">客戶資訊</div>
        <div class="form-row">
            <div class="form-group" style="flex:0 0 auto;min-width:200px">
                <label>報價單編號</label>
                <input type="text" class="form-control" value="<?= e($isEdit ? $quote['quotation_number'] : peek_next_doc_number('quotations')) ?>" readonly style="background:#f0f7ff;font-weight:600;color:var(--primary)">
            </div>
            <div class="form-group" style="flex:0 0 auto;min-width:160px">
                <label>報價公司</label>
                <select name="quote_company" class="form-control">
                    <option value="hershun" <?= ($isEdit && ($quote['quote_company'] ?? '') === 'lichuang') ? '' : 'selected' ?>>禾順</option>
                    <option value="lichuang" <?= ($isEdit && ($quote['quote_company'] ?? '') === 'lichuang') ? 'selected' : '' ?>>理創</option>
                </select>
            </div>
            <?php
            $custNo = '';
            $custData = null;
            $initCustId = '';
            if ($quote && !empty($quote['customer_id'])) {
                $initCustId = $quote['customer_id'];
            } elseif (!empty($_GET['customer_id'])) {
                $initCustId = $_GET['customer_id'];
            }
            if ($initCustId) {
                try {
                    $cStmt = Database::getInstance()->prepare('SELECT customer_no, name, contact_person, phone, mobile, site_address, tax_id, invoice_title FROM customers WHERE id = ?');
                    $cStmt->execute(array($initCustId));
                    $custData = $cStmt->fetch(PDO::FETCH_ASSOC);
                    if ($custData) $custNo = $custData['customer_no'];
                } catch (Exception $e) {}
            }
            ?>
            <div class="form-group" style="flex:0 0 auto;min-width:160px">
                <label>客戶編號</label>
                <input type="text" id="quoteCustomerNo" class="form-control" value="<?= e($custNo) ?>" readonly style="background:#f5f5f5;color:#888">
            </div>
            <div class="form-group" style="position:relative">
                <label>客戶名稱 *</label>
                <?php $initCustName = $quote ? ($quote['customer_name'] ?? '') : ($_GET['customer_name'] ?? ''); ?>
                <input type="text" name="customer_name" id="qCustName" class="form-control" value="<?= e($initCustName) ?>" required autocomplete="off" placeholder="輸入客戶名稱搜尋...">
                <input type="hidden" name="customer_id" id="qCustId" value="<?= e($initCustId) ?>">
                <div id="qCustDropdown" style="display:none;position:absolute;top:100%;left:0;right:0;z-index:100;background:#fff;border:1px solid var(--gray-200);border-radius:6px;max-height:200px;overflow-y:auto;box-shadow:0 4px 12px rgba(0,0,0,.15)"></div>
                <?php if ($isEdit && !empty($quote['customer_id'])): ?>
                <small class="text-muted" id="qCustInfo">已關聯客戶 #<?= e($quote['customer_id']) ?></small>
                <?php endif; ?>
            </div>
            <div class="form-group">
                <label>連絡對象</label>
                <?php $defaultContact = $quote ? ($quote['contact_person'] ?? '') : ($_GET['contact'] ?? ($custData['contact_person'] ?? '')); ?>
                <input type="text" name="contact_person" class="form-control" value="<?= e($defaultContact) ?>">
            </div>
            <div class="form-group">
                <label>連絡電話</label>
                <?php $defaultPhone = $quote ? ($quote['contact_phone'] ?? '') : ($_GET['phone'] ?? ($custData['phone'] ?: ($custData['mobile'] ?? ''))); ?>
                <input type="text" name="contact_phone" class="form-control" value="<?= e($defaultPhone) ?>">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>進件編號</label>
                <?php
                $initCaseId = $quote ? ($quote['case_id'] ?? '') : ($_GET['case_id'] ?? '');
                // 如果指定的案件不在選項中，額外查詢加入
                $extraCase = null;
                if ($initCaseId) {
                    $found = false;
                    foreach ($cases as $c) { if ($c['id'] == $initCaseId) { $found = true; break; } }
                    if (!$found) {
                        try {
                            $ecStmt = Database::getInstance()->prepare('SELECT id, case_number, title FROM cases WHERE id = ?');
                            $ecStmt->execute(array($initCaseId));
                            $extraCase = $ecStmt->fetch(PDO::FETCH_ASSOC);
                        } catch (Exception $e) {}
                    }
                }
                ?>
                <select name="case_id" id="qCaseId" class="form-control">
                    <option value="">-- 不關聯案件 --</option>
                    <?php if ($extraCase): ?>
                    <option value="<?= $extraCase['id'] ?>" selected><?= e($extraCase['case_number']) ?> <?= e($extraCase['title']) ?></option>
                    <?php endif; ?>
                    <?php foreach ($cases as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= $initCaseId == $c['id'] ? 'selected' : '' ?>><?= e($c['case_number']) ?> <?= e($c['title']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>案場名稱</label>
                <input type="text" name="site_name" class="form-control" value="<?= e($quote['site_name'] ?? '') ?>">
            </div>
            <div class="form-group" style="flex:2">
                <label>施工地址</label>
                <?php $initAddr = $quote ? ($quote['site_address'] ?? '') : ($_GET['address'] ?? ''); ?>
                <input type="text" name="site_address" class="form-control" value="<?= e($initAddr) ?>">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>發票抬頭</label>
                <input type="text" name="invoice_title" class="form-control" value="<?= e($quote['invoice_title'] ?? ($custData['invoice_title'] ?? '')) ?>">
            </div>
            <div class="form-group">
                <label>統編</label>
                <input type="text" name="invoice_tax_id" class="form-control" value="<?= e($quote['invoice_tax_id'] ?? ($custData['tax_id'] ?? '')) ?>">
            </div>
        </div>
    </div>

    <!-- 報價資訊 -->
    <div class="card">
        <div class="card-header">報價資訊</div>
        <div class="form-row">
            <div class="form-group">
                <label>報價日期 *</label>
                <input type="date" max="2099-12-31" name="quote_date" class="form-control" value="<?= e($quote['quote_date'] ?? date('Y-m-d')) ?>" required>
            </div>
            <div class="form-group">
                <label>有效日期 *</label>
                <input type="date" max="2099-12-31" name="valid_date" class="form-control" value="<?= e($quote['valid_date'] ?? date('Y-m-d', strtotime('+30 days'))) ?>" required>
            </div>
            <div class="form-group">
                <label>承辦業務</label>
                <select name="sales_id" class="form-control">
                    <option value="">請選擇</option>
                    <?php foreach ($salespeople as $sp): ?>
                    <option value="<?= $sp['id'] ?>" <?= ($quote['sales_id'] ?? Auth::id()) == $sp['id'] ? 'selected' : '' ?>><?= e($sp['real_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>據點</label>
                <select name="branch_id" class="form-control" required>
                    <?php foreach ($branches as $b): ?>
                    <option value="<?= $b['id'] ?>" <?= ($quote['branch_id'] ?? (Session::getUser()['branch_id'] ?? '')) == $b['id'] ? 'selected' : '' ?>><?= e($b['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>報價格式 *</label>
                <div class="d-flex gap-2 mt-1">
                    <label class="checkbox-label"><input type="radio" name="format" value="simple" <?= $fmt === 'simple' ? 'checked' : '' ?> onchange="toggleFormat(this.value)"> 普銷</label>
                    <label class="checkbox-label"><input type="radio" name="format" value="project" <?= $fmt === 'project' ? 'checked' : '' ?> onchange="toggleFormat(this.value)"> 專案</label>
                </div>
            </div>
            <div class="form-group" style="flex:0 0 140px">
                <label>保固月數</label>
                <?php
                $defaultWarranty = '12';
                try {
                    $wStmt = Database::getInstance()->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'quote_warranty_months'");
                    $wStmt->execute();
                    $wVal = $wStmt->fetchColumn();
                    if ($wVal) $defaultWarranty = $wVal;
                } catch (Exception $e) {}
                $curWarranty = $quote['warranty_months'] ?? $defaultWarranty;
                ?>
                <select name="warranty_months" class="form-control">
                    <option value="12" <?= $curWarranty == '12' ? 'selected' : '' ?>>12 個月</option>
                    <option value="24" <?= $curWarranty == '24' ? 'selected' : '' ?>>24 個月</option>
                    <option value="36" <?= $curWarranty == '36' ? 'selected' : '' ?>>36 個月</option>
                </select>
            </div>
            <div class="form-group" style="flex:2;min-width:250px">
                <label>列印選項</label>
                <div class="mt-1" style="display:flex;flex-wrap:wrap;gap:12px">
                    <label class="checkbox-label"><input type="checkbox" name="hide_model_on_print" value="1" <?= !empty($quote['hide_model_on_print']) ? 'checked' : '' ?>> 不顯示型號</label>
                    <label class="checkbox-label"><input type="checkbox" name="tax_free" value="1" <?= !empty($quote['tax_free']) ? 'checked' : '' ?> onchange="calcGrandTotal()"> 未稅(不開發票)</label>
                    <label class="checkbox-label"><input type="checkbox" name="has_discount" value="1" <?= !empty($quote['has_discount']) ? 'checked' : '' ?> onchange="toggleDiscount()"> 優惠價</label>
                </div>
            </div>
        </div>
    </div>

    <!-- 報價內容 -->
    <div class="card">
        <div class="card-header d-flex justify-between align-center">
            <span>報價內容</span>
            <button type="button" class="btn btn-outline btn-sm" id="btnAddSection" onclick="addSection()" style="<?= $fmt === 'simple' ? 'display:none' : '' ?>">+ 新增區段</button>
        </div>
        <div id="sectionsContainer">
            <?php foreach ($sections as $sIdx => $sec): ?>
            <div class="quote-section" data-sidx="<?= $sIdx ?>">
                <div class="quote-section-header" style="<?= $fmt === 'simple' ? 'display:none' : '' ?>">
                    <input type="text" name="sections[<?= $sIdx ?>][title]" class="form-control" placeholder="區段標題（如：電話線工程）" value="<?= e($sec['title'] ?? '') ?>">
                    <button type="button" class="btn btn-danger btn-sm" onclick="removeSection(this)" title="刪除區段">&times;</button>
                </div>
                <div class="table-responsive">
                    <table class="table quote-items-table">
                        <thead><tr>
                            <th style="width:30px">序</th>
                            <th style="min-width:160px">產品名稱</th>
                            <th style="width:120px">型號</th>
                            <th style="width:70px">數量</th>
                            <th style="width:50px">單位</th>
                            <th style="width:100px">單價</th>
                            <th style="width:90px">小計</th>
                            <?php if ($canManage): ?><th style="width:120px">成本</th><?php endif; ?>
                            <th style="width:30px"></th>
                        </tr></thead>
                        <tbody>
                            <?php
                            $items = isset($sec['items']) ? $sec['items'] : array(array());
                            foreach ($items as $iIdx => $item):
                            ?>
                            <tr>
                                <td class="row-num"><?= $iIdx + 1 ?></td>
                                <td>
                                    <input type="text" name="sections[<?= $sIdx ?>][items][<?= $iIdx ?>][item_name]" class="form-control item-name" value="<?= e($item['item_name'] ?? '') ?>" placeholder="品名（可手動輸入）" style="font-weight:600;font-size:.85rem" oninput="checkManualInput(this)">
                                    <input type="hidden" name="sections[<?= $sIdx ?>][items][<?= $iIdx ?>][product_id]" value="<?= e($item['product_id'] ?? '') ?>">
                                    <div class="manual-warn" style="display:<?= (!empty($item['item_name']) && empty($item['product_id'])) ? 'block' : 'none' ?>;color:#c62828;font-size:.7rem;margin-top:2px">⚠ 請用分類挑選或關鍵字搜尋，否則無法產生出庫單</div>
                                    <textarea name="sections[<?= $sIdx ?>][items][<?= $iIdx ?>][remark]" class="form-control" rows="1" placeholder="備註" style="font-size:.78rem;color:#666;margin-top:3px;padding:3px 6px;background:#fffbe6"><?= e($item['remark'] ?? '') ?></textarea>
                                </td>
                                <td><input type="text" name="sections[<?= $sIdx ?>][items][<?= $iIdx ?>][model_number]" class="form-control item-model" value="<?= e($item['model_number'] ?? '') ?>" placeholder="型號" style="font-size:.8rem;color:#1565c0"></td>
                                <td><input type="number" name="sections[<?= $sIdx ?>][items][<?= $iIdx ?>][quantity]" class="form-control item-qty" value="<?= e(rtrim(rtrim(number_format((float)($item['quantity'] ?? 1), 2, '.', ''), '0'), '.')) ?>" step="1" min="0" oninput="calcRow(this)"></td>
                                <td><input type="text" name="sections[<?= $sIdx ?>][items][<?= $iIdx ?>][unit]" class="form-control item-unit" value="<?= e($item['unit'] ?? '式') ?>"></td>
                                <td><input type="text" name="sections[<?= $sIdx ?>][items][<?= $iIdx ?>][unit_price]" class="form-control item-price" value="<?= e($item['unit_price'] ?? 0) ?>" oninput="calcRow(this)" inputmode="numeric"></td>
                                <td class="item-amount text-right"><?= number_format((float)($item['quantity'] ?? 1) * (float)($item['unit_price'] ?? 0)) ?></td>
                                <?php if ($canManage): ?>
                                <td><input type="text" name="sections[<?= $sIdx ?>][items][<?= $iIdx ?>][unit_cost]" class="form-control item-cost" value="<?= e($item['unit_cost'] ?? 0) ?>" readonly style="background:#f5f5f5;color:#666;min-width:100px;width:100%"></td>
                                <?php endif; ?>
                                <td style="white-space:nowrap">
                                    <button type="button" class="btn btn-sm" style="padding:2px 5px;font-size:.7rem;color:#666" onclick="moveItemUp(this)" title="上移">▲</button>
                                    <button type="button" class="btn btn-sm" style="padding:2px 5px;font-size:.7rem;color:#666" onclick="moveItemDown(this)" title="下移">▼</button>
                                    <button type="button" class="btn btn-danger btn-sm" onclick="removeItem(this)">&times;</button>
                                </td>
                            </tr>
                            <!-- 第二排：選擇工具列 -->
                            <tr class="psel-row" data-parent="<?= $iIdx ?>">
                                <td></td>
                                <td colspan="<?= $canManage ? 8 : 7 ?>" style="padding:4px 8px;background:#fafafa;border-top:none">
                                    <div class="psel-controls">
                                        <select class="form-control psel-cat1" onchange="onCat1(this)"><option value="">主分類</option></select>
                                        <select class="form-control psel-cat2" onchange="onCat2(this)"><option value="">子分類</option></select>
                                        <select class="form-control psel-cat3" onchange="onCat3(this)"><option value="">細分類</option></select>
                                        <select class="form-control psel-product" onchange="onProductSelect(this)"><option value="">產品名稱</option></select>
                                        <span class="psel-model-display" style="font-size:.8rem;color:#1565c0;min-width:80px"></span>
                                        <span class="psel-stock-display" style="font-size:.8rem;min-width:60px"></span>
                                        <input type="text" class="form-control psel-keyword" placeholder="關鍵字搜尋" autocomplete="off" oninput="keywordSearch(this)">
                                    </div>
                                    <div class="product-dropdown" style="position:relative"></div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="5" class="text-right">
                                    <strong>小計</strong>
                                    <label class="section-discount-chk checkbox-label" style="margin-left:15px;display:<?= $fmt === 'project' ? 'inline-flex' : 'none' ?>;font-weight:400;font-size:.85rem">
                                        <input type="checkbox" class="section-discount-toggle" <?= (isset($sec['discount_amount']) && $sec['discount_amount'] !== null) ? 'checked' : '' ?> onchange="toggleSectionDiscount(this)"> 優惠價
                                    </label>
                                </td>
                                <td class="section-subtotal text-right" style="white-space:nowrap">
                                    <strong class="section-subtotal-display" style="<?= (isset($sec['discount_amount']) && $sec['discount_amount'] !== null) ? 'text-decoration:line-through;color:#999;font-weight:400;font-size:.85rem' : '' ?>">0</strong>
                                    <input type="number" name="sections[<?= $sIdx ?>][discount_amount]" class="form-control section-discount-input" style="width:120px;text-align:right;color:var(--danger);font-weight:700;display:<?= (isset($sec['discount_amount']) && $sec['discount_amount'] !== null) ? 'inline-block' : 'none' ?>;margin-top:4px" value="<?= isset($sec['discount_amount']) && $sec['discount_amount'] !== null ? (int)$sec['discount_amount'] : '' ?>" min="0" placeholder="優惠價" oninput="calcGrandTotal()">
                                </td>
                                <td colspan="<?= $canManage ? 3 : 2 ?>">
                                    <button type="button" class="btn btn-outline btn-sm" onclick="addItem(this)">+ 新增項目</button>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- 總計 -->
        <div class="quote-totals">
            <div class="quote-total-row" id="rowSubtotal">
                <span>未稅合計：</span>
                <strong id="grandSubtotal">$0</strong>
            </div>
            <div class="quote-total-row" id="rowTax">
                <span>營業稅 (5%)：</span>
                <strong id="grandTax">$0</strong>
            </div>
            <div class="quote-total-row quote-total-grand">
                <span>合計新台幣：</span>
                <strong id="grandTotal">$0</strong>
            </div>
            <div class="quote-total-row" id="rowDiscount" style="display:<?= !empty($quote['has_discount']) ? 'flex' : 'none' ?>">
                <span style="color:var(--danger)">優惠價：</span>
                <input type="number" name="discount_amount" id="discountAmount" class="form-control" style="width:150px;text-align:right;font-weight:700;color:var(--danger)" value="<?= $isEdit ? (int)($quote['discount_amount'] ?? 0) : '' ?>" min="0" placeholder="輸入優惠價">
            </div>
        </div>
        <input type="hidden" name="tax_free" id="taxFreeHidden" value="<?= !empty($quote['tax_free']) ? '1' : '0' ?>">
        <input type="hidden" name="has_discount" id="hasDiscountHidden" value="<?= !empty($quote['has_discount']) ? '1' : '0' ?>">
    </div>

    <!-- 預計使用線材與配件（僅管理者，統計分析用） -->
    <?php if ($canManage):
        $qEstMaterials = array();
        if ($isEdit && !empty($quote['case_id'])) {
            $caseModelEst = new CaseModel();
            $qEstMaterials = $caseModelEst->getMaterialEstimates($quote['case_id']);
        }
    ?>
    <div class="card" id="estMaterialsCard" style="<?= ($isEdit && !empty($quote['case_id'])) ? '' : 'display:none' ?>">
        <div class="card-header" style="display:flex;justify-content:space-between;align-items:center">
            <span>預計使用線材與配件（統計分析用，不顯示在報價單）</span>
            <?php if (!$readOnly): ?>
            <button type="button" class="btn btn-outline btn-sm" onclick="addQEstMaterial()">+ 新增材料</button>
            <?php endif; ?>
        </div>
        <div class="table-responsive" style="overflow:visible">
            <table class="table" style="font-size:.9rem;margin:0;overflow:visible">
                <thead><tr>
                    <th style="min-width:220px">品名</th>
                    <th style="width:150px">型號</th>
                    <th style="width:70px">單位</th>
                    <th style="width:90px">預估數量</th>
                    <?php if (!$readOnly): ?><th style="width:40px"></th><?php endif; ?>
                </tr></thead>
                <tbody id="qEstMaterialsContainer">
                <?php
                $qEstIdx = 0;
                foreach ($qEstMaterials as $qem):
                ?>
                <tr class="q-est-row" data-idx="<?= $qEstIdx ?>">
                    <td style="position:relative">
                        <input type="text" name="est_materials[<?= $qEstIdx ?>][material_name]" class="form-control q-est-name"
                               value="<?= e($qem['material_name']) ?>" placeholder="搜尋產品..."
                               autocomplete="off" oninput="searchQEstProduct(this,<?= $qEstIdx ?>)">
                        <input type="hidden" name="est_materials[<?= $qEstIdx ?>][product_id]" value="<?= e($qem['product_id'] ?: '') ?>">
                        <div class="q-est-suggestions" id="q-est-sug-<?= $qEstIdx ?>"></div>
                    </td>
                    <td><input type="text" name="est_materials[<?= $qEstIdx ?>][model_number]" class="form-control" value="<?= e($qem['model_number'] ?: '') ?>" placeholder="型號"></td>
                    <td><input type="text" name="est_materials[<?= $qEstIdx ?>][unit]" class="form-control" value="<?= e($qem['unit'] ?: '') ?>" placeholder="單位"></td>
                    <td><input type="number" name="est_materials[<?= $qEstIdx ?>][estimated_qty]" class="form-control" value="<?= e($qem['estimated_qty'] ?: '') ?>" min="0" step="0.1"></td>
                    <?php if (!$readOnly): ?>
                    <td><button type="button" class="btn btn-sm" style="background:#e53935;color:#fff;padding:4px 8px" onclick="this.closest('tr').remove()">✕</button></td>
                    <?php endif; ?>
                </tr>
                <?php $qEstIdx++; endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- 內部成本（僅管理者可見） -->
    <?php if ($canManage): ?>
    <div class="card">
        <div class="card-header">內部成本分析（不顯示在報價單）</div>
        <div class="form-row">
            <div class="form-group">
                <label>施工天數</label>
                <input type="number" name="labor_days" id="laborDays" class="form-control" value="<?= e($quote['labor_days'] ?? '') ?>" step="0.5" min="0" oninput="autoCalcHours()">
            </div>
            <div class="form-group">
                <label>施工人數</label>
                <input type="number" name="labor_people" id="laborPeople" class="form-control" value="<?= e($quote['labor_people'] ?? '') ?>" min="0" oninput="autoCalcHours()">
            </div>
            <div class="form-group">
                <label>施工時數 <small style="color:#888;font-weight:normal">(自動=天數×人數×8)</small></label>
                <input type="number" name="labor_hours" id="laborHours" class="form-control" value="<?= e($quote['labor_hours'] ?? '') ?>" step="0.5" min="0" oninput="laborHoursManual=true">
            </div>
            <div class="form-group">
                <label>人力成本</label>
                <input type="number" name="labor_cost_total" id="laborCostTotal" class="form-control" value="<?= e($quote['labor_cost_total'] ?? '') ?>" min="0">
            </div>
            <div class="form-group">
                <label>線材成本</label>
                <input type="number" name="cable_cost" id="cableCost" class="form-control" value="<?= e($quote['cable_cost'] ?? '') ?>" min="0">
            </div>
        </div>
        <?php
        $_qfOpModeStmt = Database::getInstance()->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'operation_cost_mode' LIMIT 1");
        $_qfOpModeStmt->execute();
        $_qfOpMode = $_qfOpModeStmt->fetchColumn() ?: 'labor_ratio';
        $_qfOpRateStmt = Database::getInstance()->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'operation_cost_rate' LIMIT 1");
        $_qfOpRateStmt->execute();
        $_qfOpRate = (float)($_qfOpRateStmt->fetchColumn() ?: 128);
        ?>
        <div class="form-row" style="background:#f8f9fa;padding:8px;border-radius:6px">
            <div class="form-group">
                <label>器材總成本</label>
                <div id="materialCost" style="font-weight:600;font-size:1.1rem">$0</div>
            </div>
            <div class="form-group">
                <label>營運成本 <small style="color:#999;font-weight:normal">(人力×<?= $_qfOpRate ?>%)</small></label>
                <div id="opsCostDisplay" style="font-weight:600;font-size:1.1rem;color:#e65100">$0</div>
            </div>
            <div class="form-group">
                <label>總成本</label>
                <div id="totalCostDisplay" style="font-weight:600;font-size:1.1rem">$0</div>
            </div>
            <div class="form-group">
                <label>利潤金額</label>
                <div id="profitAmount" style="font-weight:600;font-size:1.1rem">$0</div>
            </div>
            <div class="form-group">
                <label>利潤率</label>
                <div id="profitRate" style="font-weight:600;font-size:1.1rem">0%</div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- 附加資訊 -->
    <div class="card">
        <div class="card-header">附加資訊</div>
        <div class="form-group">
            <label>收款條件</label>
            <textarea name="payment_terms" class="form-control" rows="2"><?= e($quote['payment_terms'] ?? '30%定金 70% 完工當日付現或當日匯款') ?></textarea>
        </div>
        <div class="form-group">
            <label>附註說明</label>
            <textarea name="notes" class="form-control" rows="2"><?= e($quote['notes'] ?? '') ?></textarea>
        </div>
    </div>

    <div class="d-flex gap-1 mt-2">
        <button type="submit" class="btn btn-primary"><?= $isEdit ? '儲存變更' : '建立報價單' ?></button>
        <a href="/quotations.php" class="btn btn-outline">取消</a>
    </div>
</form>

<style>
.form-row { display: flex; flex-wrap: wrap; gap: 12px; }
.form-row .form-group { flex: 1; min-width: 150px; }
.checkbox-label { display: flex; align-items: center; gap: 6px; cursor: pointer; }
/* 隱藏 number input 的上下箭頭 */
.quote-items-table input[type="number"]::-webkit-inner-spin-button,
.quote-items-table input[type="number"]::-webkit-outer-spin-button { -webkit-appearance: none; margin: 0; }
.quote-items-table input[type="number"] { -moz-appearance: textfield; }

.quote-section { border: 1px solid var(--gray-200); border-radius: 8px; margin-bottom: 12px; overflow: hidden; }
.quote-section-header { display: flex; gap: 8px; padding: 8px; background: var(--gray-100); }
.quote-section-header input { flex: 1; }
.quote-items-table { margin: 0; font-size: .85rem; }
.quote-items-table input { font-size: .85rem; padding: 4px 6px; }
.quote-items-table th { font-size: .75rem; padding: 6px 4px; white-space: nowrap; }
.quote-items-table td { padding: 4px; vertical-align: middle; }
.row-num { text-align: center; color: var(--gray-500); font-size: .8rem; }
/* 確保單價/成本欄位無 +/- 按鈕 */
.quote-items-table .item-price,
.quote-items-table .item-cost { -webkit-appearance: none; -moz-appearance: textfield; }
.quote-items-table .item-cost { min-width: 100px; cursor: default; }

.quote-totals { text-align: right; padding: 12px; border-top: 2px solid var(--gray-200); }
.quote-total-row { margin-bottom: 4px; font-size: .95rem; }
.quote-total-grand { font-size: 1.2rem; color: var(--primary); margin-top: 8px; }

.product-dropdown {
    position: absolute; top: 100%; left: 0; right: 0; z-index: 100;
    background: #fff; border: 1px solid var(--gray-300); border-radius: 6px;
    max-height: 200px; overflow-y: auto; display: none; box-shadow: var(--shadow);
}
.product-dropdown-item {
    padding: 6px 8px; cursor: pointer; font-size: .8rem; border-bottom: 1px solid var(--gray-100);
}
.product-dropdown-item:hover { background: var(--gray-100); }
.product-dropdown-item .product-meta { color: var(--gray-500); font-size: .7rem; }
.psel-controls { display:flex; gap:4px; align-items:center; flex-wrap:nowrap; }
.psel-controls select, .psel-controls input { font-size:.8rem !important; padding:3px 6px !important; height:28px; min-width:0; }
.psel-controls .psel-cat1 { flex:0 0 110px; }
.psel-controls .psel-cat2 { flex:0 0 110px; }
.psel-controls .psel-cat3 { flex:0 0 110px; }
.psel-controls .psel-product { flex:1; min-width:120px; }
.psel-controls .psel-keyword { flex:0 0 100px; }
.psel-model-display, .psel-stock-display { white-space:nowrap; }
.psel-row td { padding-top:0 !important; }

/* 預估線材 autocomplete */
.q-est-suggestions { display:none; position:absolute; top:100%; left:0; right:0; background:#fff; border:1px solid #ddd; border-radius:6px; box-shadow:0 4px 12px rgba(0,0,0,.15); max-height:250px; overflow-y:auto; z-index:200; }
.q-est-sug-item { padding:8px 12px; cursor:pointer; font-size:.85rem; border-bottom:1px solid #f5f5f5; }
.q-est-sug-item:hover { background:#e3f2fd; }
.q-est-sug-item:last-child { border-bottom:none; }

@media (max-width: 768px) {
    .quote-items-table { font-size: .75rem; }
    .quote-items-table input { font-size: .75rem; }
}
</style>

<script>
var sectionIdx = <?= count($sections) ?>;
var canManage = <?= $canManage ? 'true' : 'false' ?>;
var searchTimer = null;

function toggleDiscount() {
    var cb = document.querySelector('input[name="has_discount"][type="checkbox"]');
    var row = document.getElementById('rowDiscount');
    var hf = document.getElementById('hasDiscountHidden');
    if (cb && row) {
        row.style.display = cb.checked ? 'flex' : 'none';
        if (!cb.checked) document.getElementById('discountAmount').value = '';
    }
    if (hf) hf.value = cb.checked ? '1' : '0';
}

function toggleFormat(fmt) {
    var headers = document.querySelectorAll('.quote-section-header');
    var btn = document.getElementById('btnAddSection');
    for (var i = 0; i < headers.length; i++) {
        headers[i].style.display = fmt === 'project' ? 'flex' : 'none';
    }
    btn.style.display = fmt === 'project' ? '' : 'none';
    // 區段優惠價 checkbox 只在專案模式顯示
    var chks = document.querySelectorAll('.section-discount-chk');
    for (var j = 0; j < chks.length; j++) {
        chks[j].style.display = fmt === 'project' ? 'inline-flex' : 'none';
        if (fmt !== 'project') {
            var cb = chks[j].querySelector('.section-discount-toggle');
            if (cb && cb.checked) { cb.checked = false; toggleSectionDiscount(cb); }
        }
    }
    calcGrandTotal();
}

function toggleSectionDiscount(cb) {
    var section = cb.closest('.quote-section');
    var display = section.querySelector('.section-subtotal-display');
    var input = section.querySelector('.section-discount-input');
    if (cb.checked) {
        input.style.display = 'inline-block';
        if (!input.value) input.value = display.textContent.replace(/,/g, '') || '0';
        display.style.textDecoration = 'line-through';
        display.style.color = '#999';
        display.style.fontWeight = '400';
        display.style.fontSize = '.85rem';
    } else {
        input.style.display = 'none';
        input.value = '';
        display.style.textDecoration = '';
        display.style.color = '';
        display.style.fontWeight = '';
        display.style.fontSize = '';
    }
    calcGrandTotal();
}

function addSection() {
    var container = document.getElementById('sectionsContainer');
    var costTh = canManage ? '<th style="width:120px">成本</th>' : '';
    var costTd = canManage ? '<td><input type="text" name="sections[' + sectionIdx + '][items][0][unit_cost]" class="form-control item-cost" value="0" readonly style="background:#f5f5f5;color:#666;min-width:100px;width:100%"></td>' : '';
    var extraCols = canManage ? 3 : 2;

    var curFmt = (document.querySelector('input[name="format"]:checked') || {}).value || 'project';
    var discChkDisplay = curFmt === 'project' ? 'inline-flex' : 'none';
    var html = '<div class="quote-section" data-sidx="' + sectionIdx + '">' +
        '<div class="quote-section-header">' +
            '<input type="text" name="sections[' + sectionIdx + '][title]" class="form-control" placeholder="區段標題（如：電話線工程）">' +
            '<button type="button" class="btn btn-danger btn-sm" onclick="removeSection(this)">&times;</button>' +
        '</div>' +
        '<div class="table-responsive"><table class="table quote-items-table">' +
        '<thead><tr><th style="width:30px">序</th><th style="min-width:160px">產品名稱</th><th style="width:120px">型號</th><th style="width:70px">數量</th><th style="width:50px">單位</th><th style="width:100px">單價</th><th style="width:90px">小計</th>' + costTh + '<th style="width:30px"></th></tr></thead>' +
        '<tbody>' + buildItemRow(sectionIdx, 0) + '</tbody>' +
        '<tfoot><tr>' +
            '<td colspan="6" class="text-right">' +
                '<strong>小計</strong>' +
                '<label class="section-discount-chk checkbox-label" style="margin-left:15px;display:' + discChkDisplay + ';font-weight:400;font-size:.85rem">' +
                    '<input type="checkbox" class="section-discount-toggle" onchange="toggleSectionDiscount(this)"> 優惠價' +
                '</label>' +
            '</td>' +
            '<td class="section-subtotal text-right" style="white-space:nowrap">' +
                '<strong class="section-subtotal-display">0</strong>' +
                '<input type="number" name="sections[' + sectionIdx + '][discount_amount]" class="form-control section-discount-input" style="width:120px;text-align:right;color:var(--danger);font-weight:700;display:none;margin-top:4px" min="0" placeholder="優惠價" oninput="calcGrandTotal()">' +
            '</td>' +
            '<td colspan="' + extraCols + '"><button type="button" class="btn btn-outline btn-sm" onclick="addItem(this)">+ 新增項目</button></td>' +
        '</tr></tfoot>' +
        '</table></div></div>';

    container.insertAdjacentHTML('beforeend', html);
    sectionIdx++;
    loadCat1Options();
    reindexAll();
    calcGrandTotal();
}

function removeSection(btn) {
    var section = btn.closest('.quote-section');
    if (document.querySelectorAll('.quote-section').length <= 1) {
        alert('至少需要一個區段');
        return;
    }
    section.remove();
    reindexAll();
    calcGrandTotal();
}

function addItem(btn) {
    var section = btn.closest('.quote-section');
    var tbody = section.querySelector('tbody');
    var sidx = section.getAttribute('data-sidx');
    var rows = tbody.querySelectorAll('tr');
    var iidx = rows.length;
    tbody.insertAdjacentHTML('beforeend', buildItemRow(sidx, iidx));
    loadCat1Options();
    reindexSection(section);
    calcSectionSubtotal(section);
}

function moveItemUp(btn) {
    var dataRow = btn.closest('tr');
    var pselRow = dataRow.nextElementSibling;
    if (!pselRow || !pselRow.classList.contains('psel-row')) return;
    // 找上方的一對（data + psel）
    var prevPsel = dataRow.previousElementSibling;
    if (!prevPsel || !prevPsel.classList.contains('psel-row')) return;
    var prevData = prevPsel.previousElementSibling;
    if (!prevData) return;
    // 把當前一對移到上方一對前面
    var tbody = dataRow.closest('tbody');
    tbody.insertBefore(dataRow, prevData);
    tbody.insertBefore(pselRow, prevData);
    var section = dataRow.closest('.quote-section');
    reindexSection(section);
}

function moveItemDown(btn) {
    var dataRow = btn.closest('tr');
    var pselRow = dataRow.nextElementSibling;
    if (!pselRow || !pselRow.classList.contains('psel-row')) return;
    // 找下方的一對（data + psel）
    var nextData = pselRow.nextElementSibling;
    if (!nextData || nextData.classList.contains('psel-row')) return;
    var nextPsel = nextData.nextElementSibling;
    if (!nextPsel || !nextPsel.classList.contains('psel-row')) return;
    // 把下方一對移到當前一對前面
    var tbody = dataRow.closest('tbody');
    tbody.insertBefore(nextData, dataRow);
    tbody.insertBefore(nextPsel, dataRow);
    var section = dataRow.closest('.quote-section');
    reindexSection(section);
}

function removeItem(btn) {
    var row = btn.closest('tr');
    var section = row.closest('.quote-section');
    var tbody = section.querySelector('tbody');
    if (tbody.querySelectorAll('tr').length <= 1) {
        // 清空而不是刪除最後一行
        var inputs = row.querySelectorAll('input');
        for (var i = 0; i < inputs.length; i++) {
            if (inputs[i].type === 'number') inputs[i].value = inputs[i].name.indexOf('quantity') !== -1 ? '1' : '0';
            else if (inputs[i].type !== 'hidden') inputs[i].value = '';
            else inputs[i].value = '';
        }
        calcRow(row.querySelector('.item-qty'));
        return;
    }
    // 也刪除對應的 psel-row
    var nextRow = row.nextElementSibling;
    if (nextRow && nextRow.classList.contains('psel-row')) nextRow.remove();
    row.remove();
    reindexSection(section);
    calcSectionSubtotal(section);
    calcGrandTotal();
}

function buildItemRow(sidx, iidx) {
    var costTh = canManage ? '<td><input type="text" name="sections[' + sidx + '][items][' + iidx + '][unit_cost]" class="form-control item-cost" value="0" readonly style="background:#f5f5f5;color:#666;min-width:100px;width:100%"></td>' : '';
    var colSpan = canManage ? 8 : 7;
    return '<tr>' +
        '<td class="row-num">' + (iidx + 1) + '</td>' +
        '<td><input type="text" name="sections[' + sidx + '][items][' + iidx + '][item_name]" class="form-control item-name" value="" placeholder="\u54c1\u540d\uff08\u53ef\u624b\u52d5\u8f38\u5165\uff09" style="font-weight:600;font-size:.85rem" oninput="checkManualInput(this)"><input type="hidden" name="sections[' + sidx + '][items][' + iidx + '][product_id]" value=""><div class="manual-warn" style="display:none;color:#c62828;font-size:.7rem;margin-top:2px">\u26a0 \u8acb\u7528\u5206\u985e\u6311\u9078\u6216\u95dc\u9375\u5b57\u641c\u5c0b\uff0c\u5426\u5247\u7121\u6cd5\u7522\u751f\u51fa\u5eab\u55ae</div><textarea name="sections[' + sidx + '][items][' + iidx + '][remark]" class="form-control" rows="1" placeholder="\u5099\u8a3b" style="font-size:.78rem;color:#666;margin-top:3px;padding:3px 6px;background:#fffbe6"></textarea></td>' +
        '<td><input type="text" name="sections[' + sidx + '][items][' + iidx + '][model_number]" class="form-control item-model" value="" placeholder="\u578b\u865f" style="font-size:.8rem;color:#1565c0"></td>' +
        '<td><input type="number" name="sections[' + sidx + '][items][' + iidx + '][quantity]" class="form-control item-qty" value="1" step="1" min="0" oninput="calcRow(this)"></td>' +
        '<td><input type="text" name="sections[' + sidx + '][items][' + iidx + '][unit]" class="form-control item-unit" value="式"></td>' +
        '<td><input type="text" name="sections[' + sidx + '][items][' + iidx + '][unit_price]" class="form-control item-price" value="0" oninput="calcRow(this)" inputmode="numeric"></td>' +
        '<td class="item-amount text-right">0</td>' +
        costTh +
        '<td style="white-space:nowrap"><button type="button" class="btn btn-sm" style="padding:2px 5px;font-size:.7rem;color:#666" onclick="moveItemUp(this)" title="上移">▲</button><button type="button" class="btn btn-sm" style="padding:2px 5px;font-size:.7rem;color:#666" onclick="moveItemDown(this)" title="下移">▼</button><button type="button" class="btn btn-danger btn-sm" onclick="removeItem(this)">&times;</button></td>' +
    '</tr>' +
    '<tr class="psel-row">' +
        '<td></td>' +
        '<td colspan="' + colSpan + '" style="padding:4px 8px;background:#fafafa;border-top:none">' +
            '<div class="psel-controls">' +
                '<select class="form-control psel-cat1" onchange="onCat1(this)"><option value="">主分類</option></select>' +
                '<select class="form-control psel-cat2" onchange="onCat2(this)"><option value="">子分類</option></select>' +
                '<select class="form-control psel-cat3" onchange="onCat3(this)"><option value="">細分類</option></select>' +
                '<select class="form-control psel-product" onchange="onProductSelect(this)"><option value="">產品名稱</option></select>' +
                '<span class="psel-model-display" style="font-size:.8rem;color:#1565c0;min-width:80px"></span>' +
                '<span class="psel-stock-display" style="font-size:.8rem;min-width:60px"></span>' +
                '<input type="text" class="form-control psel-keyword" placeholder="關鍵字搜尋" autocomplete="off" oninput="keywordSearch(this)">' +
            '</div>' +
            '<div class="product-dropdown" style="position:relative"></div>' +
        '</td>' +
    '</tr>';
}

function reindexAll() {
    var sections = document.querySelectorAll('.quote-section');
    for (var s = 0; s < sections.length; s++) {
        sections[s].setAttribute('data-sidx', s);
        // 更新 section title name
        var titleInput = sections[s].querySelector('.quote-section-header input');
        if (titleInput) titleInput.name = 'sections[' + s + '][title]';
        reindexSection(sections[s]);
    }
}

function reindexSection(section) {
    var sidx = section.getAttribute('data-sidx');
    var rows = section.querySelectorAll('tbody tr:not(.psel-row)');
    for (var i = 0; i < rows.length; i++) {
        var numEl = rows[i].querySelector('.row-num');
        if (numEl) numEl.textContent = i + 1;
        var inputs = rows[i].querySelectorAll('input, textarea');
        for (var j = 0; j < inputs.length; j++) {
            var name = inputs[j].name;
            if (name) {
                inputs[j].name = name.replace(/sections\[\d+\]\[items\]\[\d+\]/, 'sections[' + sidx + '][items][' + i + ']');
            }
        }
    }
}

function checkManualInput(inp) {
    var td = inp.closest('td');
    var pid = td.querySelector('input[name*="product_id"]');
    var warn = td.querySelector('.manual-warn');
    if (!warn) return;
    warn.style.display = (inp.value.trim() !== '' && (!pid || !pid.value)) ? 'block' : 'none';
}
function calcRow(el) {
    var row = el.closest('tr');
    var qty = parseFloat(row.querySelector('.item-qty').value) || 0;
    var price = parseFloat(row.querySelector('.item-price').value) || 0;
    var amount = Math.round(qty * price);
    row.querySelector('.item-amount').textContent = amount.toLocaleString();
    calcSectionSubtotal(row.closest('.quote-section'));
    calcGrandTotal();
}

function calcSectionSubtotal(section) {
    var rows = section.querySelectorAll('tbody tr:not(.psel-row)');
    var subtotal = 0;
    for (var i = 0; i < rows.length; i++) {
        var qtyEl = rows[i].querySelector('.item-qty');
        var priceEl = rows[i].querySelector('.item-price');
        if (!qtyEl || !priceEl) continue;
        var qty = parseFloat(qtyEl.value) || 0;
        var price = parseFloat(priceEl.value) || 0;
        subtotal += Math.round(qty * price);
    }
    var el = section.querySelector('.section-subtotal strong');
    if (el) el.textContent = subtotal.toLocaleString();
}

function calcGrandTotal() {
    var sections = document.querySelectorAll('.quote-section');
    var grandSubtotal = 0;
    var grandCost = 0;
    for (var s = 0; s < sections.length; s++) {
        var rows = sections[s].querySelectorAll('tbody tr:not(.psel-row)');
        var secSubtotal = 0;
        for (var i = 0; i < rows.length; i++) {
            var qtyEl = rows[i].querySelector('.item-qty');
            var priceEl = rows[i].querySelector('.item-price');
            if (!qtyEl || !priceEl) continue;
            var qty = parseFloat(qtyEl.value) || 0;
            var price = parseFloat(priceEl.value) || 0;
            secSubtotal += Math.round(qty * price);
            var costEl = rows[i].querySelector('.item-cost');
            if (costEl) {
                grandCost += Math.round(qty * (parseFloat(costEl.value) || 0));
            }
        }
        // 區段優惠價：若啟用則以優惠價入合計，否則用小計
        var discToggle = sections[s].querySelector('.section-discount-toggle');
        var discInput = sections[s].querySelector('.section-discount-input');
        if (discToggle && discToggle.checked && discInput) {
            grandSubtotal += parseInt(discInput.value, 10) || 0;
        } else {
            grandSubtotal += secSubtotal;
        }
    }
    var isTaxFree = document.querySelector('input[name="tax_free"][type="checkbox"]');
    var taxFree = isTaxFree && isTaxFree.checked;
    var tax = taxFree ? 0 : Math.round(grandSubtotal * 0.05);
    var total = grandSubtotal + tax;
    document.getElementById('grandSubtotal').textContent = '$' + grandSubtotal.toLocaleString();
    document.getElementById('grandTax').textContent = '$' + tax.toLocaleString();
    document.getElementById('grandTotal').textContent = '$' + total.toLocaleString();
    // 未稅時隱藏稅額行
    document.getElementById('rowSubtotal').style.display = taxFree ? 'none' : 'flex';
    document.getElementById('rowTax').style.display = taxFree ? 'none' : 'flex';
    // 未稅時合計標籤改
    var totalLabel = document.querySelector('#grandTotal').parentElement.querySelector('span');
    if (totalLabel) totalLabel.textContent = taxFree ? '合計新台幣(未稅)：' : '合計新台幣：';
    // hidden field
    var hf = document.getElementById('taxFreeHidden');
    if (hf) hf.value = taxFree ? '1' : '0';

    // 內部成本計算（含營運成本）
    if (canManage) {
        var laborCost = parseFloat(document.getElementById('laborCostTotal').value) || 0;
        var cableCost = parseFloat(document.getElementById('cableCost').value) || 0;
        var opsRate = <?= isset($_qfOpRate) ? $_qfOpRate : 128 ?>;
        var opsCost = Math.round(laborCost * opsRate / 100);
        var totalCost = grandCost + laborCost + cableCost + opsCost;
        var profit = grandSubtotal - totalCost;
        var profitPct = grandSubtotal > 0 ? (profit / grandSubtotal * 100).toFixed(1) : 0;
        document.getElementById('materialCost').textContent = '$' + grandCost.toLocaleString();
        var opsEl = document.getElementById('opsCostDisplay');
        if (opsEl) opsEl.textContent = '$' + opsCost.toLocaleString();
        document.getElementById('totalCostDisplay').textContent = '$' + totalCost.toLocaleString();
        document.getElementById('profitAmount').textContent = '$' + profit.toLocaleString();
        document.getElementById('profitRate').textContent = profitPct + '%';
        document.getElementById('profitAmount').style.color = profit >= 0 ? '#137333' : '#c5221f';
        document.getElementById('profitRate').style.color = profit >= 0 ? '#137333' : '#c5221f';
    }
}

// 主分類變更
function onCat1(select) {
    var td = select.closest('td');
    var cat2 = td.querySelector('.psel-cat2');
    var cat3 = td.querySelector('.psel-cat3');
    var prodSel = td.querySelector('.psel-product');
    cat2.innerHTML = '<option value="">子分類</option>';
    cat3.innerHTML = '<option value="">細分類</option>';
    prodSel.innerHTML = '<option value="">產品名稱</option>';

    if (!select.value) return;

    fetch('/quotations.php?action=ajax_categories&parent_id=' + select.value)
    .then(function(r) { return r.json(); })
    .then(function(subs) {
        if (subs && subs.length > 0) {
            var html = '<option value="">子分類 (' + subs.length + ')</option>';
            for (var i = 0; i < subs.length; i++) html += '<option value="' + subs[i].id + '">' + escHtml(subs[i].name) + '</option>';
            cat2.innerHTML = html;
        } else {
            cat2.innerHTML = '<option value="">無子分類</option>';
        }
        loadProducts(td, select.value);
    });
}

// 子分類變更
function onCat2(select) {
    var td = select.closest('td');
    var cat3 = td.querySelector('.psel-cat3');
    var prodSel = td.querySelector('.psel-product');
    cat3.innerHTML = '<option value="">細分類</option>';
    prodSel.innerHTML = '<option value="">產品名稱</option>';

    if (!select.value) {
        var cat1 = td.querySelector('.psel-cat1');
        if (cat1.value) loadProducts(td, cat1.value);
        return;
    }

    fetch('/quotations.php?action=ajax_categories&parent_id=' + select.value)
    .then(function(r) { return r.json(); })
    .then(function(subs) {
        if (subs && subs.length > 0) {
            var html = '<option value="">細分類 (' + subs.length + ')</option>';
            for (var i = 0; i < subs.length; i++) html += '<option value="' + subs[i].id + '">' + escHtml(subs[i].name) + '</option>';
            cat3.innerHTML = html;
        } else {
            cat3.innerHTML = '<option value="">無細分類</option>';
        }
        loadProducts(td, select.value);
    });
}

// 細分類變更
function onCat3(select) {
    var td = select.closest('td');
    if (!select.value) {
        var cat2 = td.querySelector('.psel-cat2');
        if (cat2.value) loadProducts(td, cat2.value);
        return;
    }
    loadProducts(td, select.value);
}

// 載入產品到下拉選單
function loadProducts(td, categoryId) {
    var prodSel = td.querySelector('.psel-product');
    fetch('/quotations.php?action=ajax_products&category_id=' + categoryId)
    .then(function(r) { return r.json(); })
    .then(function(data) {
        var html = '<option value="">選擇產品 (' + data.length + '項)</option>';
        for (var i = 0; i < data.length; i++) {
            var stockLabel = data[i].stock_qty > 0 ? ' [庫存:' + data[i].stock_qty + ']' : ' [無庫存]';
            html += '<option value="' + i + '" ' +
                'data-id="' + data[i].id + '" ' +
                'data-name="' + escHtml(data[i].name) + '" ' +
                'data-model="' + escHtml(data[i].model || '') + '" ' +
                'data-unit="' + escHtml(data[i].unit || '式') + '" ' +
                'data-price="' + (data[i].price || 0) + '" ' +
                'data-cost="' + (data[i].cost_per_unit || (data[i].pack_qty > 0 ? Math.round(data[i].cost / data[i].pack_qty * 100) / 100 : data[i].cost) || 0) + '" ' +
                'data-stock="' + (data[i].stock_qty || 0) + '">' +
                escHtml(data[i].name) + (data[i].model ? ' ' + data[i].model : '') + stockLabel +
                '</option>';
        }
        prodSel.innerHTML = html;
    });
}

// 選擇產品
function onProductSelect(select) {
    var opt = select.options[select.selectedIndex];
    if (!opt || !opt.getAttribute('data-id')) return;
    var pselRow = select.closest('tr');
    // 找到上面的資料列（前一個 tr）
    var dataRow = pselRow.previousElementSibling;
    var name = opt.getAttribute('data-name');
    var model = opt.getAttribute('data-model') || '';
    var stock = opt.getAttribute('data-stock') || '0';

    // 填入第一排
    dataRow.querySelector('input[name*="product_id"]').value = opt.getAttribute('data-id');
    dataRow.querySelector('.item-name').value = name;
    var mw = dataRow.querySelector('.manual-warn');
    if (mw) mw.style.display = 'none';
    var modelInput = dataRow.querySelector('.item-model');
    if (modelInput) modelInput.value = model;
    dataRow.querySelector('.item-unit').value = opt.getAttribute('data-unit');
    dataRow.querySelector('.item-price').value = opt.getAttribute('data-price');
    var costInput = dataRow.querySelector('.item-cost');
    if (costInput) costInput.value = opt.getAttribute('data-cost');
    calcRow(dataRow.querySelector('.item-qty'));

    // 第二排顯示型號和庫存
    var modelDisp = pselRow.querySelector('.psel-model-display');
    if (modelDisp) modelDisp.textContent = model ? '型號: ' + model : '';
    var stockDisp = pselRow.querySelector('.psel-stock-display');
    if (stockDisp) {
        var stockColor = parseInt(stock) > 0 ? '#2e7d32' : '#c62828';
        stockDisp.innerHTML = '<span style="color:' + stockColor + '">庫存: ' + stock + '</span>';
    }
}

function clearProductSelection(pselRow) {
    var dataRow = pselRow.closest('tr').previousElementSibling || pselRow.closest('tr');
    dataRow.querySelector('input[name*="product_id"]').value = '';
    dataRow.querySelector('.item-name').value = '';
    var modelInput = dataRow.querySelector('.item-model');
    if (modelInput) modelInput.value = '';
    dataRow.querySelector('.item-price').value = '0';
    var costInput = dataRow.querySelector('.item-cost');
    if (costInput) costInput.value = '0';
    calcRow(dataRow.querySelector('.item-qty'));
}

// 關鍵字搜尋
function keywordSearch(input) {
    clearTimeout(searchTimer);
    var keyword = input.value.trim();
    var td = input.closest('td');
    var dropdown = td.querySelector('.product-dropdown');
    if (keyword.length < 2) { dropdown.style.display = 'none'; return; }
    searchTimer = setTimeout(function() {
        var url = '/quotations.php?action=ajax_products&keyword=' + encodeURIComponent(keyword);
        fetch(url)
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (!data.length) {
                dropdown.innerHTML = '<div class="product-dropdown-item" style="color:#999;cursor:default">無符合產品</div>';
                dropdown.style.display = 'block';
                return;
            }
            var html = '';
            for (var i = 0; i < data.length; i++) {
                var stockColor = data[i].stock_qty > 0 ? '#2e7d32' : '#999';
                var stockText = '庫存: ' + data[i].stock_qty;
                html += '<div class="product-dropdown-item" onclick="selectProduct(this)" ' +
                    'data-id="' + data[i].id + '" ' +
                    'data-name="' + escHtml(data[i].name) + '" ' +
                    'data-model="' + escHtml(data[i].model || '') + '" ' +
                    'data-unit="' + escHtml(data[i].unit || '式') + '" ' +
                    'data-price="' + (data[i].price || 0) + '" ' +
                    'data-cost="' + (data[i].cost_per_unit || (data[i].pack_qty > 0 ? Math.round(data[i].cost / data[i].pack_qty * 100) / 100 : data[i].cost) || 0) + '" ' +
                    'data-stock="' + (data[i].stock_qty || 0) + '">' +
                    '<div style="font-weight:600">' + escHtml(data[i].name) + '</div>' +
                    '<div class="product-meta">' +
                        (data[i].model ? '<span style="color:#1565c0">' + escHtml(data[i].model) + '</span> | ' : '') +
                        '<span style="color:' + stockColor + '">' + stockText + '</span> | ' +
                        '$' + Number(data[i].price || 0).toLocaleString() + '/' + escHtml(data[i].unit || '式') +
                        (data[i].category_name ? ' | <span style="color:#888">' + escHtml(data[i].category_name) + '</span>' : '') +
                        (data[i].brand ? ' | <span style="color:#e65100">' + escHtml(data[i].brand) + '</span>' : '') +
                    '</div>' +
                '</div>';
            }
            dropdown.innerHTML = html;
            dropdown.style.display = 'block';
        });
    }, 300);
}

function selectProduct(el) {
    var pselRow = el.closest('tr');
    var dataRow = pselRow.previousElementSibling;
    var name = el.getAttribute('data-name');
    var model = el.getAttribute('data-model') || '';
    var stock = el.getAttribute('data-stock') || '0';

    dataRow.querySelector('input[name*="product_id"]').value = el.getAttribute('data-id');
    dataRow.querySelector('.item-name').value = name;
    var mw2 = dataRow.querySelector('.manual-warn');
    if (mw2) mw2.style.display = 'none';
    var modelInput = dataRow.querySelector('.item-model');
    if (modelInput) modelInput.value = model;
    dataRow.querySelector('.item-unit').value = el.getAttribute('data-unit');
    dataRow.querySelector('.item-price').value = el.getAttribute('data-price');
    var costInput = dataRow.querySelector('.item-cost');
    if (costInput) costInput.value = el.getAttribute('data-cost');
    el.closest('.product-dropdown').style.display = 'none';
    pselRow.querySelector('.psel-keyword').value = '';
    calcRow(dataRow.querySelector('.item-qty'));

    var modelDisp = pselRow.querySelector('.psel-model-display');
    if (modelDisp) modelDisp.textContent = model ? '型號: ' + model : '';
    var stockDisp = pselRow.querySelector('.psel-stock-display');
    if (stockDisp) {
        var stockColor = parseInt(stock) > 0 ? '#2e7d32' : '#c62828';
        stockDisp.innerHTML = '<span style="color:' + stockColor + '">庫存: ' + stock + '</span>';
    }
}

function escHtml(s) {
    var div = document.createElement('div');
    div.appendChild(document.createTextNode(s));
    return div.innerHTML;
}

// 關閉下拉
document.addEventListener('click', function(e) {
    if (!e.target.classList.contains('psel-keyword')) {
        var dd = document.querySelectorAll('.product-dropdown');
        for (var i = 0; i < dd.length; i++) dd[i].style.display = 'none';
    }
});

// 人力成本、線材成本輸入時重算
if (document.getElementById('laborCostTotal')) {
    document.getElementById('laborCostTotal').addEventListener('input', calcGrandTotal);
}
if (document.getElementById('cableCost')) {
    document.getElementById('cableCost').addEventListener('input', calcGrandTotal);
}

// 施工時數自動計算（天數 × 人數 × 8）
var laborHoursManual = false;
function autoCalcHours() {
    if (laborHoursManual) return;
    var days = parseFloat(document.getElementById('laborDays').value) || 0;
    var people = parseFloat(document.getElementById('laborPeople').value) || 0;
    var hoursInput = document.getElementById('laborHours');
    if (days > 0 && people > 0) {
        hoursInput.value = (days * people * 8);
    } else if (days === 0 && people === 0) {
        hoursInput.value = '';
    }
}

// 載入主分類
var productCategories = [];
fetch('/quotations.php?action=ajax_categories')
.then(function(r) { return r.json(); })
.then(function(cats) {
    productCategories = cats;
    loadCat1Options();
});

function loadCat1Options() {
    var selects = document.querySelectorAll('.psel-cat1');
    for (var i = 0; i < selects.length; i++) {
        if (selects[i].options.length <= 1) {
            for (var j = 0; j < productCategories.length; j++) {
                var opt = document.createElement('option');
                opt.value = productCategories[j].id;
                opt.textContent = productCategories[j].name;
                selects[i].appendChild(opt);
            }
        }
    }
}

// 客戶搜尋
(function() {
    var inp = document.getElementById('qCustName');
    var dd = document.getElementById('qCustDropdown');
    var hiddenId = document.getElementById('qCustId');
    var timer = null;

    if (!inp || !dd) return;

    inp.addEventListener('input', function() {
        clearTimeout(timer);
        hiddenId.value = '';
        var info = document.getElementById('qCustInfo');
        if (info) info.textContent = '';
        var q = inp.value.trim();
        if (q.length < 1) { dd.style.display = 'none'; return; }
        timer = setTimeout(function() {
            fetch('/customers.php?action=ajax_search&q=' + encodeURIComponent(q))
            .then(function(r) { return r.json(); })
            .then(function(list) {
                dd.innerHTML = '';
                if (!list.length) {
                    dd.innerHTML = '<div style="padding:8px;color:#999;font-size:.85rem">無符合客戶</div>';
                    dd.style.display = 'block';
                    return;
                }
                for (var i = 0; i < list.length; i++) {
                    var c = list[i];
                    var div = document.createElement('div');
                    div.style.cssText = 'padding:8px 12px;cursor:pointer;font-size:.85rem;border-bottom:1px solid #eee';
                    div.textContent = c.name + (c.customer_no ? ' (' + c.customer_no + ')' : '');
                    div.dataset.id = c.id;
                    div.dataset.name = c.name;
                    div.dataset.contact = c.contact_person || '';
                    div.dataset.phone = c.phone || '';
                    div.dataset.custNo = c.customer_no || '';
                    div.dataset.invoiceTitle = c.invoice_title || '';
                    div.dataset.taxId = c.tax_id || '';
                    div.dataset.siteAddress = c.site_address || '';
                    div.addEventListener('click', function() {
                        inp.value = this.dataset.name;
                        hiddenId.value = this.dataset.id;
                        dd.style.display = 'none';
                        // 帶入其他欄位
                        var cp = document.querySelector('input[name="contact_person"]');
                        var cph = document.querySelector('input[name="contact_phone"]');
                        var cno = document.getElementById('quoteCustomerNo');
                        var it = document.querySelector('input[name="invoice_title"]');
                        var tid = document.querySelector('input[name="invoice_tax_id"]');
                        var sa = document.querySelector('input[name="site_address"]');
                        if (cp && !cp.value && this.dataset.contact) cp.value = this.dataset.contact;
                        if (cph && !cph.value && this.dataset.phone) cph.value = this.dataset.phone;
                        if (cno) cno.value = this.dataset.custNo;
                        if (it && !it.value && this.dataset.invoiceTitle) it.value = this.dataset.invoiceTitle;
                        if (tid && !tid.value && this.dataset.taxId) tid.value = this.dataset.taxId;
                        if (sa && !sa.value && this.dataset.siteAddress) sa.value = this.dataset.siteAddress;
                        // 顯示已關聯客戶提示
                        var info = document.getElementById('qCustInfo');
                        if (!info) {
                            info = document.createElement('small');
                            info.className = 'text-muted';
                            info.id = 'qCustInfo';
                            inp.parentNode.appendChild(info);
                        }
                        info.textContent = '已關聯客戶 #' + this.dataset.id;
                    });
                    div.addEventListener('mouseenter', function() { this.style.background = '#f0f7ff'; });
                    div.addEventListener('mouseleave', function() { this.style.background = ''; });
                    dd.appendChild(div);
                }
                dd.style.display = 'block';
            });
        }, 300);
    });

    document.addEventListener('click', function(e) {
        if (!dd.contains(e.target) && e.target !== inp) dd.style.display = 'none';
    });
})();

// ===== 預計使用線材與配件 =====
var qEstMatIndex = <?= isset($qEstIdx) ? $qEstIdx : 0 ?>;
var qEstSearchTimer = null;

function addQEstMaterial() {
    var idx = qEstMatIndex++;
    var html = '<tr class="q-est-row" data-idx="' + idx + '">' +
        '<td style="position:relative">' +
        '<input type="text" name="est_materials[' + idx + '][material_name]" class="form-control q-est-name" placeholder="搜尋產品..." autocomplete="off" oninput="searchQEstProduct(this,' + idx + ')">' +
        '<input type="hidden" name="est_materials[' + idx + '][product_id]" value="">' +
        '<div class="q-est-suggestions" id="q-est-sug-' + idx + '"></div>' +
        '</td>' +
        '<td><input type="text" name="est_materials[' + idx + '][model_number]" class="form-control" placeholder="型號"></td>' +
        '<td><input type="text" name="est_materials[' + idx + '][unit]" class="form-control" placeholder="單位"></td>' +
        '<td><input type="number" name="est_materials[' + idx + '][estimated_qty]" class="form-control" min="0" step="0.1"></td>' +
        '<td><button type="button" class="btn btn-sm" style="background:#e53935;color:#fff;padding:4px 8px" onclick="this.closest(\'tr\').remove()">✕</button></td>' +
        '</tr>';
    var container = document.getElementById('qEstMaterialsContainer');
    if (container) container.insertAdjacentHTML('beforeend', html);
}

function searchQEstProduct(input, idx) {
    clearTimeout(qEstSearchTimer);
    var q = input.value.trim();
    var sugDiv = document.getElementById('q-est-sug-' + idx);
    if (!sugDiv) return;
    if (q.length < 1) { sugDiv.innerHTML = ''; sugDiv.style.display = 'none'; return; }
    qEstSearchTimer = setTimeout(function() {
        fetch('/cases.php?action=search_products&q=' + encodeURIComponent(q))
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (!data || !data.length) {
                sugDiv.innerHTML = '<div class="q-est-sug-item" style="color:#999;cursor:default">無符合產品</div>';
                sugDiv.style.display = 'block';
                return;
            }
            var html = '';
            for (var i = 0; i < data.length; i++) {
                html += '<div class="q-est-sug-item" onclick="selectQEstProduct(' + idx + ',' + data[i].id + ',\'' + escHtml(data[i].name).replace(/'/g, "\\'") + '\',\'' + escHtml(data[i].model_number || '').replace(/'/g, "\\'") + '\',\'' + escHtml(data[i].unit || '').replace(/'/g, "\\'") + '\')">' +
                    '<strong>' + escHtml(data[i].name) + '</strong>' +
                    (data[i].model_number ? ' <span style="color:#1565c0">' + escHtml(data[i].model_number) + '</span>' : '') +
                    '</div>';
            }
            sugDiv.innerHTML = html;
            sugDiv.style.display = 'block';
        });
    }, 300);
}

function selectQEstProduct(idx, productId, name, model, unit) {
    var row = document.querySelector('tr.q-est-row[data-idx="' + idx + '"]');
    if (!row) return;
    row.querySelector('input[name*="[material_name]"]').value = name;
    row.querySelector('input[name*="[product_id]"]').value = productId;
    row.querySelector('input[name*="[model_number]"]').value = model;
    row.querySelector('input[name*="[unit]"]').value = unit;
    var sugDiv = document.getElementById('q-est-sug-' + idx);
    if (sugDiv) { sugDiv.innerHTML = ''; sugDiv.style.display = 'none'; }
}

function loadEstMaterials(caseId) {
    var container = document.getElementById('qEstMaterialsContainer');
    if (!container) return;
    container.innerHTML = '';
    qEstMatIndex = 0;
    fetch('/quotations.php?action=ajax_est_materials&case_id=' + encodeURIComponent(caseId))
    .then(function(r) { return r.json(); })
    .then(function(resp) {
        if (resp.success && resp.data && resp.data.length > 0) {
            for (var i = 0; i < resp.data.length; i++) {
                var d = resp.data[i];
                var idx = qEstMatIndex++;
                var html = '<tr class="q-est-row" data-idx="' + idx + '">' +
                    '<td style="position:relative">' +
                    '<input type="text" name="est_materials[' + idx + '][material_name]" class="form-control q-est-name" value="' + escHtml(d.material_name || '') + '" placeholder="搜尋產品..." autocomplete="off" oninput="searchQEstProduct(this,' + idx + ')">' +
                    '<input type="hidden" name="est_materials[' + idx + '][product_id]" value="' + (d.product_id || '') + '">' +
                    '<div class="q-est-suggestions" id="q-est-sug-' + idx + '"></div>' +
                    '</td>' +
                    '<td><input type="text" name="est_materials[' + idx + '][model_number]" class="form-control" value="' + escHtml(d.model_number || '') + '" placeholder="型號"></td>' +
                    '<td><input type="text" name="est_materials[' + idx + '][unit]" class="form-control" value="' + escHtml(d.unit || '') + '" placeholder="單位"></td>' +
                    '<td><input type="number" name="est_materials[' + idx + '][estimated_qty]" class="form-control" value="' + (d.estimated_qty || '') + '" min="0" step="0.1"></td>' +
                    '<td><button type="button" class="btn btn-sm" style="background:#e53935;color:#fff;padding:4px 8px" onclick="this.closest(\'tr\').remove()">✕</button></td>' +
                    '</tr>';
                container.insertAdjacentHTML('beforeend', html);
            }
        }
    });
}

// 案件切換時載入/隱藏預估線材
(function() {
    var caseSelect = document.getElementById('qCaseId');
    var estCard = document.getElementById('estMaterialsCard');
    if (!caseSelect || !estCard) return;
    caseSelect.addEventListener('change', function() {
        var caseId = this.value;
        if (!caseId) {
            estCard.style.display = 'none';
            return;
        }
        estCard.style.display = '';
        loadEstMaterials(caseId);
    });
    // 頁面載入時若已選案件，顯示卡片並載入資料（新增時透過 GET 預選）
    if (caseSelect.value) {
        estCard.style.display = '';
        // 編輯模式已由 PHP 預載，新增模式需 AJAX 載入
        var hasRows = document.querySelectorAll('#qEstMaterialsContainer .q-est-row').length;
        if (!hasRows) {
            loadEstMaterials(caseSelect.value);
        }
    }
})();

// 關閉預估線材搜尋下拉
document.addEventListener('click', function(e) {
    if (!e.target.classList.contains('q-est-name')) {
        var sugs = document.querySelectorAll('.q-est-suggestions');
        for (var i = 0; i < sugs.length; i++) { sugs[i].innerHTML = ''; sugs[i].style.display = 'none'; }
    }
});

// 初始計算
document.addEventListener('DOMContentLoaded', function() {
    var sections = document.querySelectorAll('.quote-section');
    for (var i = 0; i < sections.length; i++) calcSectionSubtotal(sections[i]);
    calcGrandTotal();
});
</script>
