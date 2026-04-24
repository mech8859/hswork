<?php
$relTypeLabels = array('customer' => '客戶', 'vendor' => '廠商', 'other' => '其他');
$grandOriginal = 0; $grandOffset = 0; $grandRemaining = 0;
foreach ($orGrouped as $g) { $grandOriginal += $g['sum_original']; $grandOffset += $g['sum_offset']; $grandRemaining += $g['sum_remaining']; }
?>
<div class="page-sticky-head">
<div class="page-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px">
    <h1>立沖帳報表</h1>
    <div style="display:flex;gap:8px">
        <a href="/accounting.php?action=offset_ledger" class="btn btn-secondary">立沖帳查詢</a>
        <a href="/accounting.php?action=ledger" class="btn btn-secondary">總帳查詢</a>
    </div>
</div>

<!-- 篩選 -->
<div class="card" style="padding:16px;margin-bottom:16px">
    <form method="get" action="/accounting.php" style="display:flex;gap:8px;flex-wrap:wrap;align-items:end">
        <input type="hidden" name="action" value="offset_reports">
        <input type="hidden" name="tab" id="hiddenTab" value="<?= e($orTab) ?>">
        <div style="flex:1;min-width:220px">
            <label style="font-size:.85em">關鍵字</label>
            <input type="text" name="keyword" value="<?= e($orKeyword ?? '') ?>" class="form-control" placeholder="傳票號/往來對象/科目/廠商編號" style="width:100%">
        </div>
        <div>
            <label style="font-size:.85em">會計科目（從）</label>
            <select name="account_code_from" class="form-control" style="width:180px">
                <option value="">-- 起始 --</option>
                <?php foreach ($accounts as $a): ?>
                <option value="<?= e($a['code']) ?>" <?= ($orAccountCodeFrom ?? '') === (string)$a['code'] ? 'selected' : '' ?>><?= e($a['code']) ?> <?= e($a['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label style="font-size:.85em">會計科目（到）</label>
            <select name="account_code_to" class="form-control" style="width:180px">
                <option value="">-- 結束 --</option>
                <?php foreach ($accounts as $a): ?>
                <option value="<?= e($a['code']) ?>" <?= ($orAccountCodeTo ?? '') === (string)$a['code'] ? 'selected' : '' ?>><?= e($a['code']) ?> <?= e($a['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label style="font-size:.85em">往來類型</label>
            <select name="relation_type" class="form-control" style="width:90px" onchange="this.form.submit()" title="變更後自動重載，使下方編號下拉跟著過濾">
                <option value="">全部</option>
                <option value="customer" <?= $orRelType === 'customer' ? 'selected' : '' ?>>客戶</option>
                <option value="vendor" <?= $orRelType === 'vendor' ? 'selected' : '' ?>>廠商</option>
                <option value="other" <?= $orRelType === 'other' ? 'selected' : '' ?>>其他</option>
            </select>
        </div>
        <div>
            <label style="font-size:.85em">編號（從）</label>
            <select name="rel_id_from" class="form-control" style="width:150px">
                <option value="">--</option>
                <?php foreach ($orRelIds as $ri): $riCode = !empty($ri['vendor_code']) ? $ri['vendor_code'] : $ri['relation_id']; ?>
                <option value="<?= e($ri['relation_id']) ?>" <?= $orRelIdFrom === (string)$ri['relation_id'] ? 'selected' : '' ?>><?= e($riCode) ?> <?= e($ri['relation_name'] ?? '') ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label style="font-size:.85em">編號（到）</label>
            <select name="rel_id_to" class="form-control" style="width:150px">
                <option value="">--</option>
                <?php foreach ($orRelIds as $ri): $riCode = !empty($ri['vendor_code']) ? $ri['vendor_code'] : $ri['relation_id']; ?>
                <option value="<?= e($ri['relation_id']) ?>" <?= $orRelIdTo === (string)$ri['relation_id'] ? 'selected' : '' ?>><?= e($riCode) ?> <?= e($ri['relation_name'] ?? '') ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label style="font-size:.85em">日期從</label>
            <input type="date" name="date_from" value="<?= e($orDateFrom) ?>" class="form-control" style="width:130px">
        </div>
        <div>
            <label style="font-size:.85em">日期到</label>
            <input type="date" name="date_to" value="<?= e($orDateTo) ?>" class="form-control" style="width:130px">
        </div>
        <div>
            <label style="font-size:.85em">成本中心</label>
            <select name="cost_center_id" class="form-control" style="width:120px">
                <option value="">全部</option>
                <?php foreach ($costCenters as $cc): ?>
                <option value="<?= $cc['id'] ?>" <?= $orCostCenterId == $cc['id'] ? 'selected' : '' ?>><?= e($cc['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-primary">查詢</button>
        <a href="/accounting.php?action=offset_reports" class="btn btn-outline">清除</a>
    </form>
</div>

<!-- 統計 -->
<div style="display:flex;gap:12px;margin-bottom:12px;flex-wrap:wrap">
    <div class="card" style="padding:10px 16px;flex:1;min-width:120px;text-align:center">
        <div style="font-size:.75rem;color:#666">筆數</div>
        <div style="font-size:1.1rem;font-weight:bold"><?= count($orRecords) ?></div>
    </div>
    <div class="card" style="padding:10px 16px;flex:1;min-width:120px;text-align:center">
        <div style="font-size:.75rem;color:#666">原始金額</div>
        <div style="font-weight:bold">$<?= number_format($grandOriginal) ?></div>
    </div>
    <div class="card" style="padding:10px 16px;flex:1;min-width:120px;text-align:center">
        <div style="font-size:.75rem;color:#666">已沖金額</div>
        <div style="font-weight:bold;color:#4CAF50">$<?= number_format($grandOffset) ?></div>
    </div>
    <div class="card" style="padding:10px 16px;flex:1;min-width:120px;text-align:center">
        <div style="font-size:.75rem;color:#666">未沖餘額</div>
        <div style="font-weight:bold;color:#e53e3e">$<?= number_format($grandRemaining) ?></div>
    </div>
</div>

<!-- Tab 切換 -->
<div style="display:flex;gap:4px;margin-bottom:16px;border-bottom:2px solid var(--gray-200);padding-bottom:0">
    <button type="button" class="or-tab <?= $orTab === 'detail' ? 'active' : '' ?>" onclick="switchTab('detail')">立沖明細表</button>
    <button type="button" class="or-tab <?= $orTab === 'balance' ? 'active' : '' ?>" onclick="switchTab('balance')">科目餘額表</button>
    <button type="button" class="or-tab <?= $orTab === 'subledger' ? 'active' : '' ?>" onclick="switchTab('subledger')">立沖分類帳</button>
</div>
</div><!-- /.page-sticky-head -->

<!-- ========== 1. 立沖明細表 ========== -->
<div id="tab-detail" class="or-panel" style="<?= $orTab !== 'detail' ? 'display:none' : '' ?>">
<?php if (empty($orRecords)): ?>
<div class="card" style="padding:20px;text-align:center;color:#999">無資料</div>
<?php else: ?>
<div class="card" style="overflow:visible">
    <table class="data-table" style="width:100%;font-size:.82rem;white-space:nowrap">
        <thead class="sticky-thead"><tr style="background:#f5f5f5">
            <th>日期</th><th>傳票號碼</th><th>科目</th><th>成本中心</th><th>往來類型</th><th>往來編號</th><th>往來對象</th><th>摘要</th><th style="text-align:right">立帳金額</th><th style="text-align:right">沖帳金額</th><th style="text-align:right">未沖餘額</th>
        </tr></thead>
        <tbody>
        <?php
        $prevKey = '';
        foreach ($orGrouped as $key => $group):
            // 小計分隔
            if ($prevKey) {
                $pg = $orGrouped[$prevKey];
                $pgCode = !empty($pg['relation_display_code']) ? $pg['relation_display_code'] : $pg['relation_id'];
                echo '<tr style="background:#e8f5e9;font-weight:bold"><td colspan="8" style="text-align:right">小計 - ' . e($pg['account_code']) . ' / ' . e($pgCode) . ' ' . e($pg['relation_name']) . '</td>';
                echo '<td style="text-align:right">' . number_format($pg['sum_original']) . '</td>';
                echo '<td style="text-align:right">' . number_format($pg['sum_offset']) . '</td>';
                echo '<td style="text-align:right">' . number_format($pg['sum_remaining']) . '</td></tr>';
            }
            foreach ($group['records'] as $r):
                // 立帳行
        ?>
            <tr>
                <td><?= e($r['voucher_date']) ?></td>
                <td><a href="/accounting.php?action=journal_view&id=<?= $r['journal_entry_id'] ?>&ref=reconciliation"><?= e($r['voucher_number']) ?></a></td>
                <td><?= e($r['account_code']) ?> <?= e($r['account_name']) ?></td>
                <td><?= e($r['cost_center_name'] ?? '') ?></td>
                <td><?= $relTypeLabels[$r['relation_type']] ?? $r['relation_type'] ?></td>
                <td><?= e(!empty($r['relation_display_code']) ? $r['relation_display_code'] : $r['relation_id']) ?></td>
                <td><?= e($r['relation_name'] ?? '') ?></td>
                <td style="color:#666"><?= e($r['description'] ?? '') ?></td>
                <td style="text-align:right;font-weight:600"><?= number_format((float)$r['original_amount']) ?></td>
                <td style="text-align:right"></td>
                <td style="text-align:right"><?= number_format((float)$r['remaining_amount']) ?></td>
            </tr>
            <?php if (!empty($orDetails[$r['id']])): foreach ($orDetails[$r['id']] as $d): ?>
            <tr style="background:#fafafa">
                <td style="padding-left:20px"><?= e($d['offset_date'] ?? $d['voucher_date']) ?></td>
                <td><a href="/accounting.php?action=journal_view&id=<?= $d['journal_entry_id'] ?>&ref=reconciliation"><?= e($d['offset_voucher_number'] ?? $d['voucher_number']) ?></a></td>
                <td colspan="5" style="color:#888">↳ 沖帳</td>
                <td></td>
                <td style="text-align:right"></td>
                <td style="text-align:right;color:#4CAF50"><?= number_format((float)$d['offset_amount']) ?></td>
                <td style="text-align:right"></td>
            </tr>
            <?php endforeach; endif; ?>
        <?php
            endforeach;
            $prevKey = $key;
        endforeach;
        // 最後一組小計
        if ($prevKey) {
            $pg = $orGrouped[$prevKey];
            $pgCode = !empty($pg['relation_display_code']) ? $pg['relation_display_code'] : $pg['relation_id'];
            echo '<tr style="background:#e8f5e9;font-weight:bold"><td colspan="8" style="text-align:right">小計 - ' . e($pg['account_code']) . ' / ' . e($pgCode) . ' ' . e($pg['relation_name']) . '</td>';
            echo '<td style="text-align:right">' . number_format($pg['sum_original']) . '</td>';
            echo '<td style="text-align:right">' . number_format($pg['sum_offset']) . '</td>';
            echo '<td style="text-align:right">' . number_format($pg['sum_remaining']) . '</td></tr>';
        }
        ?>
        </tbody>
        <tfoot>
            <tr style="font-weight:bold;background:#f0f0f0;font-size:.9rem">
                <td colspan="8" style="text-align:right">合計</td>
                <td style="text-align:right"><?= number_format($grandOriginal) ?></td>
                <td style="text-align:right"><?= number_format($grandOffset) ?></td>
                <td style="text-align:right"><?= number_format($grandRemaining) ?></td>
            </tr>
        </tfoot>
    </table>
</div>
<?php endif; ?>
</div>

<!-- ========== 2. 科目餘額表 ========== -->
<div id="tab-balance" class="or-panel" style="<?= $orTab !== 'balance' ? 'display:none' : '' ?>">
<?php
// 科目餘額表：僅顯示未沖餘額 > 0 的項目，合計也依此重算
$orBalance = array_filter($orGrouped, function($g) { return (float)$g['sum_remaining'] != 0; });
$balOriginal = 0; $balOffset = 0; $balRemaining = 0;
foreach ($orBalance as $g) {
    $balOriginal += $g['sum_original'];
    $balOffset += $g['sum_offset'];
    $balRemaining += $g['sum_remaining'];
}
?>
<?php if (empty($orBalance)): ?>
<div class="card" style="padding:20px;text-align:center;color:#999">無未沖餘額資料</div>
<?php else: ?>
<div class="card" style="overflow:visible">
    <table class="data-table" style="width:100%;font-size:.85rem">
        <thead class="sticky-thead"><tr style="background:#f5f5f5">
            <th>科目編號</th><th>科目名稱</th><th>往來類型</th><th>往來編號</th><th>往來對象</th><th>成本中心</th><th>筆數</th>
            <th style="text-align:right">原始金額</th><th style="text-align:right">已沖金額</th><th style="text-align:right">未沖餘額</th>
        </tr></thead>
        <tbody>
        <?php
        $prevAcct = '';
        $acctOriginal = 0; $acctOffset = 0; $acctRemaining = 0;
        foreach ($orBalance as $key => $g):
            // 科目變換時輸出小計
            if ($prevAcct && $prevAcct !== $g['account_code']) {
                echo '<tr style="background:#fff3e0;font-weight:600"><td colspan="7" style="text-align:right">科目小計</td>';
                echo '<td style="text-align:right">' . number_format($acctOriginal) . '</td>';
                echo '<td style="text-align:right">' . number_format($acctOffset) . '</td>';
                echo '<td style="text-align:right">' . number_format($acctRemaining) . '</td></tr>';
                $acctOriginal = 0; $acctOffset = 0; $acctRemaining = 0;
            }
            $acctOriginal += $g['sum_original'];
            $acctOffset += $g['sum_offset'];
            $acctRemaining += $g['sum_remaining'];
            $prevAcct = $g['account_code'];
        ?>
        <tr>
            <td style="font-family:monospace"><?= e($g['account_code']) ?></td>
            <td><?= e($g['account_name']) ?></td>
            <td><?= $relTypeLabels[$g['relation_type']] ?? $g['relation_type'] ?></td>
            <td><?= e(!empty($g['relation_display_code']) ? $g['relation_display_code'] : $g['relation_id']) ?></td>
            <td><?= e($g['relation_name'] ?? '') ?></td>
            <td><?= e($g['cost_center_name'] ?? '') ?></td>
            <td><?= count($g['records']) ?></td>
            <td style="text-align:right"><?= number_format($g['sum_original']) ?></td>
            <td style="text-align:right"><?= number_format($g['sum_offset']) ?></td>
            <td style="text-align:right;font-weight:bold;color:<?= $g['sum_remaining'] > 0 ? '#e53e3e' : '#4CAF50' ?>"><?= number_format($g['sum_remaining']) ?></td>
        </tr>
        <?php endforeach;
        // 最後一科目小計
        if ($prevAcct) {
            echo '<tr style="background:#fff3e0;font-weight:600"><td colspan="7" style="text-align:right">科目小計</td>';
            echo '<td style="text-align:right">' . number_format($acctOriginal) . '</td>';
            echo '<td style="text-align:right">' . number_format($acctOffset) . '</td>';
            echo '<td style="text-align:right">' . number_format($acctRemaining) . '</td></tr>';
        }
        ?>
        </tbody>
        <tfoot>
            <tr style="font-weight:bold;background:#f0f0f0;font-size:.9rem">
                <td colspan="7" style="text-align:right">合計（已排除沖完項目）</td>
                <td style="text-align:right"><?= number_format($balOriginal) ?></td>
                <td style="text-align:right"><?= number_format($balOffset) ?></td>
                <td style="text-align:right"><?= number_format($balRemaining) ?></td>
            </tr>
        </tfoot>
    </table>
</div>
<?php endif; ?>
</div>

<!-- ========== 3. 立沖分類帳 ========== -->
<div id="tab-subledger" class="or-panel" style="<?= $orTab !== 'subledger' ? 'display:none' : '' ?>">
<?php if (empty($orGrouped)): ?>
<div class="card" style="padding:20px;text-align:center;color:#999">無資料</div>
<?php else: ?>
<?php foreach ($orGrouped as $key => $g): ?>
<div class="card" style="margin-bottom:16px;overflow:visible">
    <div style="padding:10px 16px;background:#f8f9fa;border-bottom:1px solid #eee;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px">
        <div>
            <strong style="font-family:monospace"><?= e($g['account_code']) ?></strong> <?= e($g['account_name']) ?>
            <span style="margin-left:12px;color:#666">|</span>
            <span style="margin-left:12px"><?= $relTypeLabels[$g['relation_type']] ?? $g['relation_type'] ?>：<?= e(!empty($g['relation_display_code']) ? $g['relation_display_code'] : $g['relation_id']) ?> <?= e($g['relation_name'] ?? '') ?></span>
            <?php if ($g['cost_center_name']): ?>
            <span style="margin-left:12px;color:#666">| <?= e($g['cost_center_name']) ?></span>
            <?php endif; ?>
        </div>
        <div style="font-size:.85rem">
            未沖餘額：<strong style="color:<?= $g['sum_remaining'] > 0 ? '#e53e3e' : '#4CAF50' ?>"><?= number_format($g['sum_remaining']) ?></strong>
        </div>
    </div>
    <table class="data-table" style="width:100%;font-size:.82rem">
        <thead class="sticky-thead"><tr style="background:#fafafa">
            <th style="width:90px">日期</th><th style="width:130px">傳票號碼</th><th>摘要</th><th>成本中心</th>
            <th style="width:100px;text-align:right">立帳金額</th><th style="width:100px;text-align:right">沖帳金額</th><th style="width:110px;text-align:right">餘額</th>
        </tr></thead>
        <tbody>
        <?php
        $runBal = 0;
        foreach ($g['records'] as $r):
            $runBal += (float)$r['original_amount'];
        ?>
        <tr>
            <td><?= e($r['voucher_date']) ?></td>
            <td><a href="/accounting.php?action=journal_view&id=<?= $r['journal_entry_id'] ?>&ref=reconciliation"><?= e($r['voucher_number']) ?></a></td>
            <td style="color:#666"><?= e($r['description'] ?? '立帳') ?></td>
            <td><?= e($r['cost_center_name'] ?? '') ?></td>
            <td style="text-align:right;font-weight:600"><?= number_format((float)$r['original_amount']) ?></td>
            <td style="text-align:right"></td>
            <td style="text-align:right;font-weight:bold"><?= number_format($runBal) ?></td>
        </tr>
        <?php if (!empty($orDetails[$r['id']])): foreach ($orDetails[$r['id']] as $d):
            $runBal -= (float)$d['offset_amount'];
        ?>
        <tr style="background:#fafafa">
            <td><?= e($d['offset_date'] ?? $d['voucher_date']) ?></td>
            <td><a href="/accounting.php?action=journal_view&id=<?= $d['journal_entry_id'] ?>&ref=reconciliation"><?= e($d['offset_voucher_number'] ?? $d['voucher_number']) ?></a></td>
            <td style="color:#888">↳ 沖帳</td>
            <td></td>
            <td style="text-align:right"></td>
            <td style="text-align:right;color:#4CAF50"><?= number_format((float)$d['offset_amount']) ?></td>
            <td style="text-align:right;font-weight:bold;<?= $runBal < 0 ? 'color:red' : '' ?>"><?= number_format($runBal) ?></td>
        </tr>
        <?php endforeach; endif; ?>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr style="font-weight:bold;background:#f8f9fa">
                <td colspan="4" style="text-align:right">期末餘額</td>
                <td style="text-align:right"><?= number_format($g['sum_original']) ?></td>
                <td style="text-align:right"><?= number_format($g['sum_offset']) ?></td>
                <td style="text-align:right;color:<?= $g['sum_remaining'] > 0 ? '#e53e3e' : '#4CAF50' ?>"><?= number_format($g['sum_remaining']) ?></td>
            </tr>
        </tfoot>
    </table>
</div>
<?php endforeach; ?>

<!-- 總計 -->
<div class="card" style="padding:12px 16px;background:#f0f0f0;font-weight:bold;display:flex;justify-content:space-between;flex-wrap:wrap">
    <span>全部合計（<?= count($orGrouped) ?> 組）</span>
    <span>原始：$<?= number_format($grandOriginal) ?> | 已沖：$<?= number_format($grandOffset) ?> | 未沖：<span style="color:#e53e3e">$<?= number_format($grandRemaining) ?></span></span>
</div>
<?php endif; ?>
</div>

<script>
function switchTab(tab) {
    var panels = document.querySelectorAll('.or-panel');
    var tabs = document.querySelectorAll('.or-tab');
    for (var i = 0; i < panels.length; i++) panels[i].style.display = 'none';
    for (var i = 0; i < tabs.length; i++) tabs[i].classList.remove('active');
    document.getElementById('tab-' + tab).style.display = '';
    event.target.classList.add('active');
    document.getElementById('hiddenTab').value = tab;
}
</script>

<style>
.or-tab { padding:8px 20px; border:none; background:none; font-size:.9rem; cursor:pointer; color:#666; border-bottom:2px solid transparent; margin-bottom:-2px; }
.or-tab:hover { color:var(--primary); }
.or-tab.active { color:var(--primary); border-bottom-color:var(--primary); font-weight:600; }
</style>
