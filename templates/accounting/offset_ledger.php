<div class="page-sticky-head">
<div class="page-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px">
    <h1>立沖帳查詢</h1>
    <div style="display:flex;gap:8px">
        <a href="/accounting.php?action=ledger" class="btn btn-secondary">總帳查詢</a>
        <a href="/accounting.php?action=journals" class="btn btn-secondary">傳票管理</a>
    </div>
</div>

<!-- Query Form -->
<div class="card" style="padding:16px;margin-bottom:16px">
    <form method="get" action="/accounting.php" style="display:flex;gap:8px;flex-wrap:wrap;align-items:end">
        <input type="hidden" name="action" value="offset_ledger">
        <div style="flex:1;min-width:220px">
            <label style="font-size:0.85em">關鍵字</label>
            <input type="text" name="keyword" value="<?= e($olKeyword ?? '') ?>" class="form-control" placeholder="傳票號/往來對象/科目/廠商編號" style="width:100%">
        </div>
        <div>
            <label style="font-size:0.85em">會計科目（從）</label>
            <select name="account_code_from" class="form-control" style="width:200px">
                <option value="">-- 起始 --</option>
                <?php foreach ($accounts as $a): ?>
                <option value="<?= e($a['code']) ?>" <?= ($olAccountCodeFrom ?? '') === (string)$a['code'] ? 'selected' : '' ?>><?= e($a['code']) ?> <?= e($a['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label style="font-size:0.85em">會計科目（到）</label>
            <select name="account_code_to" class="form-control" style="width:200px">
                <option value="">-- 結束 --</option>
                <?php foreach ($accounts as $a): ?>
                <option value="<?= e($a['code']) ?>" <?= ($olAccountCodeTo ?? '') === (string)$a['code'] ? 'selected' : '' ?>><?= e($a['code']) ?> <?= e($a['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label style="font-size:0.85em">往來類型</label>
            <select name="relation_type" class="form-control" style="width:100px" onchange="this.form.submit()" title="變更後自動重載，使下方編號下拉跟著過濾">
                <option value="">全部</option>
                <option value="customer" <?= $olRelType === 'customer' ? 'selected' : '' ?>>客戶</option>
                <option value="vendor" <?= $olRelType === 'vendor' ? 'selected' : '' ?>>廠商</option>
                <option value="other" <?= $olRelType === 'other' ? 'selected' : '' ?>>其他</option>
            </select>
        </div>
        <div>
            <label style="font-size:0.85em">往來編號（從）</label>
            <select name="rel_id_from" class="form-control" style="width:160px">
                <option value="">-- 起始 --</option>
                <?php foreach ($olRelIds as $ri): $riCode = !empty($ri['vendor_code']) ? $ri['vendor_code'] : $ri['relation_id']; ?>
                <option value="<?= e($ri['relation_id']) ?>" <?= ($olRelIdFrom ?? '') === (string)$ri['relation_id'] ? 'selected' : '' ?>><?= e($riCode) ?> <?= e($ri['relation_name'] ?? '') ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label style="font-size:0.85em">往來編號（到）</label>
            <select name="rel_id_to" class="form-control" style="width:160px">
                <option value="">-- 結束 --</option>
                <?php foreach ($olRelIds as $ri): $riCode = !empty($ri['vendor_code']) ? $ri['vendor_code'] : $ri['relation_id']; ?>
                <option value="<?= e($ri['relation_id']) ?>" <?= ($olRelIdTo ?? '') === (string)$ri['relation_id'] ? 'selected' : '' ?>><?= e($riCode) ?> <?= e($ri['relation_name'] ?? '') ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label style="font-size:0.85em">狀態</label>
            <select name="status" class="form-control" style="width:100px">
                <option value="">全部</option>
                <option value="open" <?= $olStatus === 'open' ? 'selected' : '' ?>>未沖</option>
                <option value="partial" <?= $olStatus === 'partial' ? 'selected' : '' ?>>部分沖</option>
                <option value="closed" <?= $olStatus === 'closed' ? 'selected' : '' ?>>已沖完</option>
            </select>
        </div>
        <div>
            <label style="font-size:0.85em">日期從</label>
            <input type="date" name="date_from" value="<?= e($olDateFrom) ?>" class="form-control" style="width:140px">
        </div>
        <div>
            <label style="font-size:0.85em">日期到</label>
            <input type="date" name="date_to" value="<?= e($olDateTo) ?>" class="form-control" style="width:140px">
        </div>
        <div>
            <label style="font-size:0.85em">成本中心</label>
            <select name="cost_center_id" class="form-control" style="width:130px">
                <option value="">全部</option>
                <?php foreach ($costCenters as $cc): ?>
                <option value="<?= $cc['id'] ?>" <?= $olCostCenterId == $cc['id'] ? 'selected' : '' ?>><?= e($cc['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-primary">查詢</button>
        <a href="/accounting.php?action=offset_ledger" class="btn btn-outline">清除</a>
    </form>
</div>

<!-- Summary -->
<?php
$sumOriginal = 0; $sumOffset = 0; $sumRemaining = 0;
$countOpen = 0; $countPartial = 0; $countClosed = 0;
foreach ($offsetRecords as $r) {
    $sumOriginal += (float)$r['original_amount'];
    $sumOffset += (float)$r['offset_total'];
    $sumRemaining += (float)$r['remaining_amount'];
    if ($r['status'] === 'open') $countOpen++;
    elseif ($r['status'] === 'partial') $countPartial++;
    else $countClosed++;
}
?>
<div style="display:flex;gap:12px;margin-bottom:16px;flex-wrap:wrap">
    <div class="card" style="padding:12px;flex:1;min-width:150px;text-align:center">
        <div style="font-size:.8rem;color:#666">共</div>
        <div style="font-size:1.3rem;font-weight:bold"><?= count($offsetRecords) ?> 筆</div>
    </div>
    <div class="card" style="padding:12px;flex:1;min-width:150px;text-align:center">
        <div style="font-size:.8rem;color:#666">原始金額</div>
        <div style="font-size:1.1rem;font-weight:bold">$<?= number_format($sumOriginal) ?></div>
    </div>
    <div class="card" style="padding:12px;flex:1;min-width:150px;text-align:center">
        <div style="font-size:.8rem;color:#666">已沖金額</div>
        <div style="font-size:1.1rem;font-weight:bold;color:#4CAF50">$<?= number_format($sumOffset) ?></div>
    </div>
    <div class="card" style="padding:12px;flex:1;min-width:150px;text-align:center">
        <div style="font-size:.8rem;color:#666">未沖餘額</div>
        <div style="font-size:1.1rem;font-weight:bold;color:#e53e3e">$<?= number_format($sumRemaining) ?></div>
    </div>
    <div class="card" style="padding:12px;flex:1;min-width:150px;text-align:center">
        <div style="font-size:.8rem;color:#666">未沖/部分/已沖</div>
        <div style="font-size:1.1rem;font-weight:bold"><?= $countOpen ?> / <?= $countPartial ?> / <?= $countClosed ?></div>
    </div>
</div>
</div><!-- /.page-sticky-head -->

<!-- Table -->
<div class="card" style="overflow:visible">
    <table class="data-table" style="width:100%;font-size:.85rem">
        <thead class="sticky-thead">
            <tr>
                <th style="width:32px"></th>
                <th style="width:100px">傳票日期</th>
                <th style="width:140px">傳票號碼</th>
                <th>科目</th>
                <th>成本中心</th>
                <th>往來類型</th>
                <th>往來編號</th>
                <th>往來對象</th>
                <th>借/貸</th>
                <th style="text-align:right">原始金額</th>
                <th style="text-align:right">已沖金額</th>
                <th style="text-align:right">未沖餘額</th>
                <th style="width:70px">狀態</th>
                <th style="width:40px"></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($offsetRecords)): ?>
            <tr><td colspan="14" style="text-align:center;padding:20px;color:#999">無符合的立帳記錄</td></tr>
            <?php endif; ?>
            <?php
            $relTypeLabels = array('customer' => '客戶', 'vendor' => '廠商', 'other' => '其他');
            $statusLabels = array('open' => '未沖', 'partial' => '部分沖', 'closed' => '已沖完');
            $statusColors = array('open' => '#e53e3e', 'partial' => '#f0ad4e', 'closed' => '#5cb85c');
            foreach ($offsetRecords as $r): $isStar = !empty($r['is_starred']);
            ?>
            <tr>
                <td class="text-center"><span class="star-toggle <?= $isStar ? 'is-on' : '' ?>" data-id="<?= (int)$r['id'] ?>" onclick="toggleStarOffset(this)" title="標記">&#9733;</span></td>
                <td><?= e($r['voucher_date']) ?></td>
                <td><a href="/accounting.php?action=journal_view&id=<?= $r['journal_entry_id'] ?>&ref=offset_ledger"><?= e($r['voucher_number']) ?></a></td>
                <td><?= e($r['account_code']) ?> <?= e($r['account_name']) ?></td>
                <td><?= e($r['cost_center_name'] ?? '') ?></td>
                <td><?= isset($relTypeLabels[$r['relation_type']]) ? $relTypeLabels[$r['relation_type']] : e($r['relation_type']) ?></td>
                <td><?= e(!empty($r['relation_display_code']) ? $r['relation_display_code'] : ($r['relation_id'] ?? '')) ?></td>
                <td><?= e($r['relation_name'] ?? '') ?></td>
                <td><?= $r['direction'] === 'debit' ? '借' : '貸' ?></td>
                <td style="text-align:right"><?= number_format((float)$r['original_amount']) ?></td>
                <td style="text-align:right"><?= (float)$r['offset_total'] > 0 ? number_format((float)$r['offset_total']) : '' ?></td>
                <td style="text-align:right;font-weight:bold;color:<?= $statusColors[$r['status']] ?? '#666' ?>"><?= number_format((float)$r['remaining_amount']) ?></td>
                <td><span style="color:<?= $statusColors[$r['status']] ?? '#666' ?>;font-weight:600;font-size:.8rem"><?= $statusLabels[$r['status']] ?? $r['status'] ?></span></td>
                <td>
                    <?php if (!empty($offsetDetails[$r['id']])): ?>
                    <button type="button" class="btn btn-sm btn-outline" onclick="toggleDetail(<?= $r['id'] ?>)" title="沖帳明細" style="padding:2px 6px;font-size:.75rem">▼</button>
                    <?php endif; ?>
                </td>
            </tr>
            <?php if (!empty($offsetDetails[$r['id']])): ?>
            <tr id="detail-<?= $r['id'] ?>" style="display:none">
                <td colspan="14" style="padding:0 16px 8px 40px;background:#fafafa">
                    <table style="width:100%;font-size:.8rem;border-collapse:collapse;margin-top:4px">
                        <thead><tr style="color:#666"><th style="padding:4px 8px">沖帳日期</th><th style="padding:4px 8px">沖帳傳票</th><th style="padding:4px 8px;text-align:right">沖帳金額</th></tr></thead>
                        <tbody>
                        <?php foreach ($offsetDetails[$r['id']] as $d): ?>
                        <tr style="border-top:1px solid #eee">
                            <td style="padding:4px 8px"><?= e($d['voucher_date']) ?></td>
                            <td style="padding:4px 8px"><a href="/accounting.php?action=journal_view&id=<?= $d['journal_entry_id'] ?>&ref=offset_ledger"><?= e($d['voucher_number']) ?></a></td>
                            <td style="padding:4px 8px;text-align:right"><?= number_format((float)$d['offset_amount']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </td>
            </tr>
            <?php endif; ?>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<style>
.star-toggle { display:inline-block; cursor:pointer; font-size:1.2rem; color:#d0d0d0; transition:color .15s,transform .15s; user-select:none; line-height:1; }
.star-toggle:hover { color:#f1c40f; transform:scale(1.15); }
.star-toggle.is-on { color:#f1c40f; }
.star-toggle.saving { opacity:.5; pointer-events:none; }
</style>
<script>
function toggleDetail(id) {
    var row = document.getElementById('detail-' + id);
    if (row) row.style.display = row.style.display === 'none' ? '' : 'none';
}
function toggleStarOffset(el) {
    if (el.classList.contains('saving')) return;
    var id = el.getAttribute('data-id'); if (!id) return;
    el.classList.add('saving');
    var fd = new FormData(); fd.append('id', id);
    var xhr = new XMLHttpRequest();
    xhr.open('POST', '/accounting.php?action=toggle_star_offset');
    xhr.onload = function() { el.classList.remove('saving'); try { var res = JSON.parse(xhr.responseText); if (res.error) { alert(res.error); return; } el.classList.toggle('is-on', !!res.starred); } catch (e) { alert('回應錯誤'); } };
    xhr.onerror = function() { el.classList.remove('saving'); alert('網路錯誤'); };
    xhr.send(fd);
}
</script>
