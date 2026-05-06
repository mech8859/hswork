<?php
$ext = strtolower(pathinfo($record['file_name'], PATHINFO_EXTENSION));
$isImage = in_array($ext, array('jpg', 'jpeg', 'png'), true);
$isPdf = ($ext === 'pdf');
$status = $record['status'];
$statusBadge = VendorInvoiceModel::statusBadgeClass($status);
$statusLabel = VendorInvoiceModel::statusOptions()[$status] ?? $status;
$fileUrl = '/vendor_invoices.php?action=download&id=' . (int)$record['id'];

// 取廠商清單給 select（最多 500 筆，常用廠商優先）
$db = Database::getInstance();
$vendors = $db->query("SELECT id, name, vendor_code FROM vendors WHERE is_active = 1 ORDER BY name LIMIT 1000")->fetchAll(PDO::FETCH_ASSOC);

// 取 AI 原始辨識（候選廠商等）
$aiRaw = !empty($record['recognized_data']) ? json_decode($record['recognized_data'], true) : null;
$aiVendorName = $aiRaw['vendor']['name'] ?? '';
$aiVendorTaxId = $aiRaw['vendor']['tax_id'] ?? '';
$aiVendorCands = $aiRaw['vendor']['candidates'] ?? array();
?>
<div class="d-flex justify-between align-center flex-wrap gap-1 mb-2">
    <div>
        <a href="/vendor_invoices.php?status=<?= e($status) ?>" class="btn btn-outline btn-sm">← 返回收件匣</a>
        <h2 style="display:inline-block;margin-left:.5rem">
            📥 <?= e($record['file_name']) ?>
            <span class="badge badge-<?= e($statusBadge) ?>" style="font-size:.75rem"><?= e($statusLabel) ?></span>
        </h2>
    </div>
    <div class="d-flex gap-1">
        <a href="<?= e($fileUrl) ?>" target="_blank" class="btn btn-outline btn-sm">🔍 開啟原檔</a>
        <?php if ($status === 'pending'): ?>
        <button type="button" class="btn btn-primary" onclick="viStartRecognize(<?= (int)$record['id'] ?>)">🤖 開始辨識</button>
        <?php elseif ($status === 'recognized'): ?>
        <form method="POST" action="/vendor_invoices.php?action=reset" style="display:inline" onsubmit="return confirm('退回後可重新辨識，目前的編輯會保留至 final 欄位但仍會被覆寫。確定？')">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int)$record['id'] ?>">
            <button type="submit" class="btn btn-outline btn-sm">↩ 退回重辨</button>
        </form>
        <?php endif; ?>
    </div>
</div>

<div class="d-flex gap-2 flex-wrap" style="align-items:flex-start">
    <!-- 左：原檔預覽 -->
    <div style="flex:1;min-width:320px;max-width:600px;position:sticky;top:8px">
        <div class="card" style="padding:0;overflow:hidden">
            <?php if ($isImage): ?>
            <img src="<?= e($fileUrl) ?>" alt="<?= e($record['file_name']) ?>" style="width:100%;display:block;cursor:zoom-in" onclick="window.open(this.src)">
            <?php elseif ($isPdf): ?>
            <iframe src="<?= e($fileUrl) ?>" style="width:100%;height:80vh;border:0"></iframe>
            <?php else: ?>
            <div class="text-center" style="padding:40px">
                <p>此檔案格式無法預覽。</p>
                <a href="<?= e($fileUrl) ?>" class="btn btn-primary">下載檔案</a>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- 右：辨識結果 / 編輯表單 / 確認區 -->
    <div style="flex:1;min-width:320px">
        <?php if ($status === 'recognized'): ?>
        <!-- 編輯表單（待確認狀態）-->
        <form method="POST" action="/vendor_invoices.php?action=confirm">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int)$record['id'] ?>">

            <div class="card">
                <h3>請款單資訊（請核對 AI 辨識結果）</h3>
                <?php if ($aiVendorName): ?>
                <p class="text-muted" style="font-size:.85rem">
                    AI 辨識廠商：<strong><?= e($aiVendorName) ?></strong>
                    <?php if ($aiVendorTaxId): ?> ／ 統編 <?= e($aiVendorTaxId) ?><?php endif; ?>
                </p>
                <?php endif; ?>
                <div class="form-group">
                    <label>廠商 <span style="color:#c62828">*</span></label>
                    <select name="vendor_id" class="form-control" required>
                        <option value="">— 請選擇 —</option>
                        <?php foreach ($vendors as $v): ?>
                        <option value="<?= (int)$v['id'] ?>" <?= ((int)$record['vendor_id'] === (int)$v['id']) ? 'selected' : '' ?>>
                            <?= e($v['name']) ?><?= !empty($v['vendor_code']) ? ' (' . e($v['vendor_code']) . ')' : '' ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (!empty($aiVendorCands)): ?>
                    <small class="text-muted">AI 候選：
                        <?php foreach ($aiVendorCands as $c): ?>
                        <a href="javascript:void(0)" onclick="this.closest('.form-group').querySelector('select').value='<?= (int)$c['id'] ?>'" style="margin-right:.5rem"><?= e($c['name']) ?></a>
                        <?php endforeach; ?>
                    </small>
                    <?php endif; ?>
                </div>
                <div class="d-flex gap-1 flex-wrap">
                    <div class="form-group" style="flex:1;min-width:140px">
                        <label>請款日期</label>
                        <input type="date" name="invoice_date" value="<?= e($record['invoice_date']) ?>" class="form-control">
                    </div>
                    <div class="form-group" style="flex:1;min-width:140px">
                        <label>發票號碼</label>
                        <input type="text" name="invoice_number" value="<?= e($record['invoice_number']) ?>" class="form-control">
                    </div>
                    <div class="form-group" style="flex:1;min-width:140px">
                        <label>總金額（含稅）</label>
                        <input type="number" step="0.01" name="total_amount" value="<?= e($record['total_amount']) ?>" class="form-control">
                    </div>
                </div>
                <div class="form-group">
                    <label>備註</label>
                    <textarea name="note" rows="2" class="form-control"><?= e($record['note']) ?></textarea>
                </div>
            </div>

            <div class="card mt-2">
                <h3>明細（共 <?= count($items) ?> 項）</h3>
                <?php if (empty($items)): ?>
                <p class="text-muted">無明細</p>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th style="width:30px">#</th>
                                <th style="width:120px">型號</th>
                                <th style="min-width:200px">品名</th>
                                <th style="width:110px" class="text-right">數量</th>
                                <th style="width:70px">單位</th>
                                <th style="width:130px" class="text-right">單價</th>
                                <th style="width:130px" class="text-right">金額</th>
                                <th style="width:390px">對應產品</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $i => $it):
                                $finalModel = $it['final_model'] !== null && $it['final_model'] !== '' ? $it['final_model'] : ($it['ai_model'] ?? '');
                                $finalName = $it['final_name'] !== null && $it['final_name'] !== '' ? $it['final_name'] : ($it['ai_name'] ?? '');
                                $finalQty = $it['final_qty'] !== null ? $it['final_qty'] : ($it['ai_qty'] ?? '');
                                $finalUnit = $it['final_unit'] !== null && $it['final_unit'] !== '' ? $it['final_unit'] : ($it['ai_unit'] ?? '');
                                $finalUp = $it['final_unit_price'] !== null ? $it['final_unit_price'] : ($it['ai_unit_price'] ?? '');
                                $finalAmt = $it['final_amount'] !== null ? $it['final_amount'] : ($it['ai_amount'] ?? '');
                            ?>
                            <tr>
                                <td><?= (int)$it['line_no'] ?></td>
                                <input type="hidden" name="items[<?= $i ?>][id]" value="<?= (int)$it['id'] ?>">
                                <td><input type="text" name="items[<?= $i ?>][final_model]" value="<?= e($finalModel) ?>" class="form-control" style="font-size:.85rem"></td>
                                <td><input type="text" name="items[<?= $i ?>][final_name]" value="<?= e($finalName) ?>" class="form-control" style="font-size:.85rem"></td>
                                <td><input type="number" step="0.001" name="items[<?= $i ?>][final_qty]" value="<?= e($finalQty) ?>" class="form-control text-right" style="font-size:.85rem"></td>
                                <td><input type="text" name="items[<?= $i ?>][final_unit]" value="<?= e($finalUnit) ?>" class="form-control" style="font-size:.85rem"></td>
                                <td><input type="number" step="0.01" name="items[<?= $i ?>][final_unit_price]" value="<?= e($finalUp) ?>" class="form-control text-right" style="font-size:.85rem"></td>
                                <td><input type="number" step="0.01" name="items[<?= $i ?>][final_amount]" value="<?= e($finalAmt) ?>" class="form-control text-right" style="font-size:.85rem"></td>
                                <td>
                                    <input type="text" name="items[<?= $i ?>][matched_product_id]" value="<?= !empty($it['matched_product_id']) ? (int)$it['matched_product_id'] : '' ?>" class="form-control" style="font-size:.8rem" placeholder="產品 ID">
                                    <?php if (!empty($it['product_name'])): ?>
                                    <small class="text-muted"><?= e($it['product_name']) ?><?php if (!empty($it['product_model'])): ?> (<?= e($it['product_model']) ?>)<?php endif; ?></small>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <small class="text-muted">提示：已對應產品的列會在確認時把單價寫入「產品價格變動史」與「廠商產品對照表」。產品 ID 留空則該列不影響價格。</small>
                <?php endif; ?>
            </div>

            <div class="card mt-2">
                <div class="d-flex gap-1 justify-end">
                    <button type="submit" class="btn btn-primary">✓ 確認並寫入價格紀錄</button>
                </div>
            </div>
        </form>
        <?php else: ?>
        <!-- 唯讀檢視（pending / confirmed）-->
        <div class="card">
            <h3>請款單資訊</h3>
            <table class="table table-sm">
                <tr><th style="width:120px">檔名</th><td><?= e($record['file_name']) ?></td></tr>
                <tr><th>檔案大小</th><td><?= !empty($record['file_size']) ? number_format($record['file_size'] / 1024, 0) . ' KB' : '-' ?></td></tr>
                <tr><th>上傳時間</th><td><?= e(date('Y-m-d H:i', strtotime($record['uploaded_at']))) ?></td></tr>
                <tr><th>狀態</th><td><span class="badge badge-<?= e($statusBadge) ?>"><?= e($statusLabel) ?></span></td></tr>
                <?php if ($status !== 'pending'): ?>
                <tr><th>廠商</th><td><?= e($record['vendor_name'] ?: '-') ?></td></tr>
                <tr><th>請款日期</th><td><?= e($record['invoice_date'] ?: '-') ?></td></tr>
                <tr><th>發票號碼</th><td><?= e($record['invoice_number'] ?: '-') ?></td></tr>
                <tr><th>總金額</th><td><?= !empty($record['total_amount']) ? '$' . number_format((float)$record['total_amount']) : '-' ?></td></tr>
                <?php endif; ?>
                <?php if ($status === 'confirmed'): ?>
                <tr><th>確認時間</th><td><?= e(!empty($record['confirmed_at']) ? date('Y-m-d H:i', strtotime($record['confirmed_at'])) : '-') ?></td></tr>
                <?php endif; ?>
            </table>

            <?php if ($status === 'pending'): ?>
            <div class="alert alert-info" style="margin-top:1rem">
                <strong>📋 流程說明</strong><br>
                1. 點上方「🤖 開始辨識」呼叫 AI 辨識（耗時約 10-30 秒）<br>
                2. 辨識完成後檔案會進入「待確認」分頁可編輯<br>
                3. 確認辨識結果後寫入產品價格變動史
            </div>
            <?php else: ?>
            <div class="alert alert-success" style="margin-top:1rem">
                ✓ 已確認，相關價格已寫入產品價格變動史與廠商產品對照表。
            </div>
            <?php endif; ?>
        </div>

        <?php if (!empty($items)): ?>
        <div class="card mt-2">
            <h3>明細</h3>
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>型號</th>
                            <th>品名</th>
                            <th class="text-right">數量</th>
                            <th>單位</th>
                            <th class="text-right">單價</th>
                            <th class="text-right">金額</th>
                            <th>對應產品</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $it):
                            $showModel = $it['final_model'] !== null && $it['final_model'] !== '' ? $it['final_model'] : ($it['ai_model'] ?? '');
                            $showName = $it['final_name'] !== null && $it['final_name'] !== '' ? $it['final_name'] : ($it['ai_name'] ?? '');
                            $showQty = $it['final_qty'] !== null ? $it['final_qty'] : ($it['ai_qty'] ?? null);
                            $showUnit = $it['final_unit'] !== null && $it['final_unit'] !== '' ? $it['final_unit'] : ($it['ai_unit'] ?? '');
                            $showUp = $it['final_unit_price'] !== null ? $it['final_unit_price'] : ($it['ai_unit_price'] ?? null);
                            $showAmt = $it['final_amount'] !== null ? $it['final_amount'] : ($it['ai_amount'] ?? null);
                        ?>
                        <tr>
                            <td><?= (int)$it['line_no'] ?></td>
                            <td><?= e($showModel ?: '-') ?></td>
                            <td><?= e($showName ?: '-') ?></td>
                            <td class="text-right"><?= $showQty !== null && $showQty !== '' ? number_format((float)$showQty, 0) : '-' ?></td>
                            <td><?= e($showUnit ?: '-') ?></td>
                            <td class="text-right"><?= $showUp !== null && $showUp !== '' ? '$' . number_format((float)$showUp) : '-' ?></td>
                            <td class="text-right"><?= $showAmt !== null && $showAmt !== '' ? '$' . number_format((float)$showAmt) : '-' ?></td>
                            <td><?= !empty($it['product_name']) ? e($it['product_name']) : '<span class="text-muted">未對應</span>' ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <div class="d-flex gap-1 mt-2">
            <?php if ($status !== 'confirmed' || Auth::hasPermission('all')): ?>
            <form method="POST" action="/vendor_invoices.php?action=delete" style="display:inline" onsubmit="return confirm('確定刪除此請款單？檔案會一併移除。')">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int)$record['id'] ?>">
                <button type="submit" class="btn btn-outline btn-sm" style="color:#c62828">🗑 刪除</button>
            </form>
            <?php endif; ?>
            <a href="<?= e($fileUrl) ?>" download class="btn btn-outline btn-sm">⬇ 下載原檔</a>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- 辨識中 overlay -->
<div id="viReconOverlay" style="display:none;position:fixed;top:0;left:0;width:100vw;height:100vh;background:rgba(0,0,0,.6);z-index:2000;align-items:center;justify-content:center">
    <div style="background:#fff;border-radius:8px;padding:32px 40px;max-width:480px;width:90%;text-align:center">
        <div style="font-size:3rem;margin-bottom:.5rem">🤖</div>
        <h3 style="margin:.5rem 0">AI 辨識中…</h3>
        <p id="viReconMsg" class="text-muted">正在呼叫 Claude Vision，約需 30-60 秒，請勿關閉視窗。</p>
        <div style="margin-top:1rem;background:#f0f0f0;border-radius:4px;height:6px;overflow:hidden">
            <div id="viReconBar" style="background:#1a56db;height:100%;width:5%;transition:width .3s"></div>
        </div>
        <p style="margin-top:.5rem;font-size:.85rem;color:#666"><span id="viReconElapsed">0</span> 秒</p>
    </div>
</div>

<script>
function viStartRecognize(id) {
    var overlay = document.getElementById('viReconOverlay');
    var msg = document.getElementById('viReconMsg');
    var bar = document.getElementById('viReconBar');
    var elapsedEl = document.getElementById('viReconElapsed');
    overlay.style.display = 'flex';

    var startedAt = Date.now();
    var elapsed = 0;
    var pollTimer = null;
    var done = false;

    // 進度條（最多 90% 等回應）
    var progressTimer = setInterval(function() {
        elapsed = Math.floor((Date.now() - startedAt) / 1000);
        elapsedEl.textContent = elapsed;
        var pct = Math.min(90, 5 + elapsed * 1.5);
        bar.style.width = pct + '%';
    }, 500);

    // 呼叫 recognize（後端可能會被 Apache timeout，但 PHP 仍跑完）
    var ctrl = (typeof AbortController !== 'undefined') ? new AbortController() : null;
    var fetchOpts = { method: 'GET', credentials: 'same-origin' };
    if (ctrl) fetchOpts.signal = ctrl.signal;

    fetch('/vendor_invoices.php?action=recognize&id=' + id, fetchOpts)
        .then(function(r) {
            // 不論結果都進入 polling 確認 status
            if (!done) startPolling();
        })
        .catch(function() {
            // Apache 500 / 網路斷線：開始輪詢 status
            if (!done) {
                msg.innerHTML = '辨識耗時較久，正在等候伺服器寫入結果…';
                startPolling();
            }
        });

    function startPolling() {
        if (pollTimer) return;
        pollTimer = setInterval(checkStatus, 3000);
        // 立刻檢查一次
        setTimeout(checkStatus, 500);
    }

    function checkStatus() {
        fetch('/vendor_invoices.php?action=status&id=' + id, { credentials: 'same-origin' })
            .then(function(r) { return r.json(); })
            .then(function(j) {
                if (j && j.ok && j.status && j.status !== 'pending') {
                    finish('辨識完成！');
                }
                // 超過 3 分鐘還沒結果就放棄
                if (elapsed > 180 && !done) {
                    finish('辨識逾時，請手動重試或查看狀態。', true);
                }
            })
            .catch(function() {});
    }

    function finish(text, isError) {
        if (done) return;
        done = true;
        clearInterval(progressTimer);
        if (pollTimer) clearInterval(pollTimer);
        bar.style.width = '100%';
        msg.textContent = text;
        setTimeout(function() {
            location.href = '/vendor_invoices.php?action=view&id=' + id;
        }, isError ? 1500 : 600);
    }
}
</script>
