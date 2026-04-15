<?php $isEdit = !empty($record); ?>

<h2><?= $isEdit ? '編輯廠商 - ' . e($record['name']) : '新增廠商' ?></h2>

<form method="POST" action="/vendors.php?action=<?= $isEdit ? 'edit&id=' . $record['id'] : 'create' ?>" class="mt-2">
    <?= csrf_field() ?>

    <!-- 基本資訊 -->
    <div class="card">
        <div class="card-header">基本資訊</div>
        <div class="form-row">
            <div class="form-group">
                <label>廠商編號</label>
                <input type="text" name="vendor_code" class="form-control"
                       value="<?= e(!empty($record['vendor_code']) ? $record['vendor_code'] : '') ?>"
                       placeholder="<?= $isEdit ? '' : '儲存後自動產生（B-XXXX）' ?>"
                       readonly
                       style="background:#f5f5f5;color:#555">
                <?php if (!$isEdit): ?>
                <small class="text-muted">系統自動產生，格式 B-XXXX</small>
                <?php endif; ?>
            </div>
            <div class="form-group">
                <label>廠商名稱 *</label>
                <input type="text" name="name" class="form-control"
                       value="<?= e(!empty($record['name']) ? $record['name'] : '') ?>" required>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>簡稱</label>
                <input type="text" name="short_name" class="form-control"
                       value="<?= e(!empty($record['short_name']) ? $record['short_name'] : '') ?>">
            </div>
            <div class="form-group">
                <label>統一編號</label>
                <input type="text" name="tax_id" class="form-control" maxlength="8"
                       value="<?= e(!empty($record['tax_id']) ? $record['tax_id'] : '') ?>">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>類別</label>
                <input type="text" name="category" class="form-control" placeholder="例：水電、消防、監控"
                       value="<?= e(!empty($record['category']) ? $record['category'] : '') ?>">
            </div>
            <div class="form-group">
                <label>服務項目</label>
                <input type="text" name="service_items" class="form-control" placeholder="例：配線、安裝、維修"
                       value="<?= e(!empty($record['service_items']) ? $record['service_items'] : '') ?>">
            </div>
        </div>
    </div>

    <!-- 聯絡資訊 -->
    <div class="card">
        <div class="card-header">聯絡資訊</div>
        <div class="form-row">
            <div class="form-group">
                <label>聯絡窗口</label>
                <input type="text" name="contact_person" class="form-control"
                       value="<?= e(!empty($record['contact_person']) ? $record['contact_person'] : '') ?>">
            </div>
            <div class="form-group">
                <label>電話</label>
                <input type="text" name="phone" class="form-control"
                       value="<?= e(!empty($record['phone']) ? $record['phone'] : '') ?>">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>傳真</label>
                <input type="text" name="fax" class="form-control"
                       value="<?= e(!empty($record['fax']) ? $record['fax'] : '') ?>">
            </div>
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" class="form-control"
                       value="<?= e(!empty($record['email']) ? $record['email'] : '') ?>">
            </div>
        </div>
    </div>

    <!-- 地址 -->
    <div class="card">
        <div class="card-header">地址</div>
        <div class="form-row">
            <div class="form-group" style="flex:0 0 100px;">
                <label>郵遞區號</label>
                <input type="text" name="postal_code" class="form-control" maxlength="6"
                       value="<?= e(!empty($record['postal_code']) ? $record['postal_code'] : '') ?>">
            </div>
            <div class="form-group">
                <label>縣市區域</label>
                <input type="text" name="city_district" class="form-control" placeholder="例：台中市西屯區"
                       value="<?= e(!empty($record['city_district']) ? $record['city_district'] : '') ?>">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>街道地址</label>
                <input type="text" name="street_address" class="form-control"
                       value="<?= e(!empty($record['street_address']) ? $record['street_address'] : '') ?>">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>完整地址</label>
                <input type="text" name="address" class="form-control" placeholder="系統自動組合或手動輸入"
                       value="<?= e(!empty($record['address']) ? $record['address'] : '') ?>">
            </div>
        </div>
    </div>

    <!-- 付款資訊 -->
    <div class="card">
        <div class="card-header">付款資訊</div>
        <div class="form-row">
            <div class="form-group">
                <label>付款方式</label>
                <select name="payment_method" class="form-control">
                    <option value="">請選擇</option>
                    <?php
                    $paymentMethods = array('現金' => '現金', '匯款' => '匯款', '支票' => '支票', '月結' => '月結');
                    foreach ($paymentMethods as $k => $v): ?>
                    <option value="<?= e($k) ?>" <?= (!empty($record['payment_method']) && $record['payment_method'] === $k) ? 'selected' : '' ?>><?= e($v) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>付款條件</label>
                <input type="text" name="payment_terms" class="form-control" placeholder="例：月結30天"
                       value="<?= e(!empty($record['payment_terms']) ? $record['payment_terms'] : '') ?>">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>結帳日</label>
                <input type="text" name="settlement_day" class="form-control" placeholder="例：每月25日"
                       value="<?= e(!empty($record['settlement_day']) ? $record['settlement_day'] : '') ?>">
            </div>
            <div class="form-group">
                <label>請款方式</label>
                <input type="text" name="invoice_method" class="form-control" placeholder="例：郵寄、電子"
                       value="<?= e(!empty($record['invoice_method']) ? $record['invoice_method'] : '') ?>">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>發票類型</label>
                <select name="invoice_type" class="form-control">
                    <option value="">請選擇</option>
                    <?php
                    $invoiceTypes = array('三聯式' => '三聯式', '二聯式' => '二聯式', '電子發票' => '電子發票');
                    // 既有資料相容：若已存舊值「免用統一發票」，仍顯示
                    if (!empty($record['invoice_type']) && !isset($invoiceTypes[$record['invoice_type']])) {
                        $invoiceTypes[$record['invoice_type']] = $record['invoice_type'];
                    }
                    foreach ($invoiceTypes as $k => $v): ?>
                    <option value="<?= e($k) ?>" <?= (!empty($record['invoice_type']) && $record['invoice_type'] === $k) ? 'selected' : '' ?>><?= e($v) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>

    <!-- 發票資訊 -->
    <div class="card">
        <div class="card-header">發票資訊</div>
        <div class="form-row">
            <div class="form-group">
                <label>發票抬頭</label>
                <input type="text" name="header1" class="form-control"
                       value="<?= e(!empty($record['header1']) ? $record['header1'] : '') ?>">
            </div>
            <div class="form-group">
                <label>統一編號</label>
                <input type="text" name="tax_id1" class="form-control" maxlength="8"
                       value="<?= e(!empty($record['tax_id1']) ? $record['tax_id1'] : '') ?>">
            </div>
        </div>
    </div>

    <!-- 備註 -->
    <div class="card">
        <div class="card-header">備註</div>
        <div class="form-group">
            <textarea name="note" class="form-control" rows="3"><?= e(!empty($record['note']) ? $record['note'] : '') ?></textarea>
        </div>
    </div>

    <div class="d-flex gap-1 mt-2">
        <button type="submit" class="btn btn-primary"><?= $isEdit ? '儲存變更' : '建立廠商' ?></button>
        <a href="/vendors.php" class="btn btn-outline">取消</a>
    </div>
</form>

<style>
.form-row { display: flex; flex-wrap: wrap; gap: 12px; }
.form-row .form-group { flex: 1; min-width: 150px; }
</style>
