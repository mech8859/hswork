<?php
// $totalClosed, $lockedCount, $anomBalance, $anomSettle, $anomCompletion 由 controller 傳入
$totalAnomBalance = count($anomBalance);
$totalAnomSettle = count($anomSettle);
$totalAnomCompletion = count($anomCompletion);
$lockedRate = $totalClosed > 0 ? round($lockedCount / $totalClosed * 100, 1) : 0;
?>
<style>
.ca-summary { padding: 14px 16px; background: #fff3cd; border-left: 4px solid #ff9800; margin-bottom: 14px; border-radius: 4px; }
.ca-summary.ok { background: #e8f5e9; border-left-color: #4caf50; }
.ca-stat-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 10px; margin-top: 8px; }
.ca-stat { padding: 10px; background: #fff; border-radius: 4px; text-align: center; }
.ca-stat .num { font-size: 1.8rem; font-weight: 700; }
.ca-stat .lbl { font-size: .8rem; color: #666; margin-top: 2px; }
.ca-section { margin-bottom: 20px; }
.ca-section-header {
    display: flex; justify-content: space-between; align-items: center;
    padding: 10px 14px; background: #f5f5f5;
    border-left: 4px solid #2196f3; border-radius: 4px 4px 0 0;
    margin-bottom: 0;
}
.ca-section-header h3 { margin: 0; font-size: 1rem; }
.ca-section-header .ca-count { color: #c62828; font-weight: 600; }
.ca-table { width: 100%; border-collapse: collapse; font-size: 13px; background: #fff; }
.ca-table th, .ca-table td { padding: 6px 10px; border: 1px solid #e0e0e0; text-align: left; }
.ca-table th { background: #fafafa; font-weight: 600; position: sticky; top: 0; }
.ca-table td.num { text-align: right; }
.ca-table .col-warn { color: #c62828; font-weight: 600; }
.ca-edit-btn {
    display: inline-block; padding: 3px 10px; background: #2196f3; color: #fff !important;
    border-radius: 3px; text-decoration: none; font-size: 12px;
}
.ca-edit-btn:hover { background: #1976d2; text-decoration: none; }
.ca-locked-badge {
    display: inline-block; padding: 1px 6px; background: #ffebee; color: #c62828;
    border-radius: 3px; font-size: 11px; margin-left: 4px;
}
.ca-toolbar { margin-bottom: 14px; display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
.ca-fresh { padding: 6px 14px; background: #4caf50; color: #fff; border: none; border-radius: 4px; cursor: pointer; font-size: 13px; }
.ca-fresh:hover { background: #388e3c; }
.ca-empty { padding: 14px; text-align: center; color: #4caf50; background: #e8f5e9; border-radius: 0 0 4px 4px; }
</style>

<div class="d-flex justify-between align-center flex-wrap gap-1 mb-2">
    <h2>⚠️ 結案資料異常</h2>
    <?= back_button('/reports.php') ?>
</div>

<div class="ca-toolbar">
    <button type="button" class="ca-fresh" onclick="location.reload()">🔄 重新整理</button>
    <span style="color:#666;font-size:.85rem">最後更新：<?= date('Y-m-d H:i:s') ?></span>
    <span style="color:#888;font-size:.8rem;margin-left:auto">點「編輯」會在新分頁打開案件，修完存檔後回到本頁按「重新整理」即可看到最新狀態</span>
</div>

<div class="ca-summary <?= ($totalAnomBalance + $totalAnomSettle + $totalAnomCompletion) === 0 ? 'ok' : '' ?>">
    <strong>📊 總結</strong>
    <div class="ca-stat-grid">
        <div class="ca-stat"><div class="num"><?= number_format($totalClosed) ?></div><div class="lbl">結案案件總數</div></div>
        <div class="ca-stat"><div class="num" style="color:#4caf50"><?= number_format($lockedCount) ?></div><div class="lbl">已上鎖（<?= $lockedRate ?>%）</div></div>
        <div class="ca-stat"><div class="num" style="color:#c62828"><?= number_format($totalAnomBalance) ?></div><div class="lbl">帳款未平</div></div>
        <div class="ca-stat"><div class="num" style="color:#ff9800"><?= number_format($totalAnomSettle) ?></div><div class="lbl">未標結清</div></div>
        <div class="ca-stat"><div class="num" style="color:#9c27b0"><?= number_format($totalAnomCompletion) ?></div><div class="lbl">缺完工日</div></div>
    </div>
</div>

<!-- 異常 1：帳款未平 -->
<div class="ca-section">
    <div class="ca-section-header" style="border-left-color:#f44336">
        <h3>異常 1：結案但 balance_amount ≠ 0 <span class="ca-count">（<?= number_format($totalAnomBalance) ?> 筆）</span></h3>
        <small style="color:#888">理論上：結案＝帳款結清，balance 應為 0</small>
    </div>
    <?php if (empty($anomBalance)): ?>
    <div class="ca-empty">✓ 無異常</div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="ca-table">
            <thead><tr>
                <th>ID</th><th>案件編號</th><th>標題</th><th>客戶</th>
                <th class="num">含稅金額</th><th class="num">成交金額</th>
                <th class="num">已收</th><th class="num">尾款</th>
                <th>結清</th><th>結清日</th><th>完工日</th><th>動作</th>
            </tr></thead>
            <tbody>
                <?php foreach ($anomBalance as $p): ?>
                <tr>
                    <td><?= $p['id'] ?></td>
                    <td><?= e($p['case_number']) ?><?= !empty($p['is_locked']) ? '<span class="ca-locked-badge">🔒</span>' : '' ?></td>
                    <td><?= e($p['title']) ?></td>
                    <td><?= e($p['customer_name']) ?></td>
                    <td class="num"><?= number_format((int)$p['total_amount']) ?></td>
                    <td class="num"><?= number_format((int)$p['deal_amount']) ?></td>
                    <td class="num"><?= number_format((int)$p['total_collected']) ?></td>
                    <td class="num col-warn"><?= number_format((int)$p['balance_amount']) ?></td>
                    <td><?= $p['settlement_confirmed'] ? '✓' : '✗' ?></td>
                    <td><?= e($p['settlement_date'] ?? '') ?></td>
                    <td><?= e($p['completion_date'] ?? '') ?></td>
                    <td><a href="/cases.php?action=edit&id=<?= $p['id'] ?>" target="_blank" class="ca-edit-btn">編輯</a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- 異常 2：未標結清 -->
<div class="ca-section">
    <div class="ca-section-header" style="border-left-color:#ff9800">
        <h3>異常 2：結案但未標結清（settlement_confirmed = 0 / NULL）<span class="ca-count">（<?= number_format($totalAnomSettle) ?> 筆）</span></h3>
    </div>
    <?php if (empty($anomSettle)): ?>
    <div class="ca-empty">✓ 無異常</div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="ca-table">
            <thead><tr>
                <th>ID</th><th>案件編號</th><th>標題</th><th>客戶</th>
                <th class="num">尾款</th><th>完工日</th><th>動作</th>
            </tr></thead>
            <tbody>
                <?php foreach ($anomSettle as $p): ?>
                <tr>
                    <td><?= $p['id'] ?></td>
                    <td><?= e($p['case_number']) ?><?= !empty($p['is_locked']) ? '<span class="ca-locked-badge">🔒</span>' : '' ?></td>
                    <td><?= e($p['title']) ?></td>
                    <td><?= e($p['customer_name']) ?></td>
                    <td class="num"><?= number_format((int)$p['balance_amount']) ?></td>
                    <td><?= e($p['completion_date'] ?? '') ?></td>
                    <td><a href="/cases.php?action=edit&id=<?= $p['id'] ?>" target="_blank" class="ca-edit-btn">編輯</a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- 異常 3：缺完工日 -->
<div class="ca-section">
    <div class="ca-section-header" style="border-left-color:#9c27b0">
        <h3>異常 3：結案但缺完工日 <span class="ca-count">（<?= number_format($totalAnomCompletion) ?> 筆）</span></h3>
    </div>
    <?php if (empty($anomCompletion)): ?>
    <div class="ca-empty">✓ 無異常</div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="ca-table">
            <thead><tr>
                <th>ID</th><th>案件編號</th><th>標題</th><th>客戶</th>
                <th class="num">尾款</th><th>結清</th><th>動作</th>
            </tr></thead>
            <tbody>
                <?php foreach ($anomCompletion as $p): ?>
                <tr>
                    <td><?= $p['id'] ?></td>
                    <td><?= e($p['case_number']) ?><?= !empty($p['is_locked']) ? '<span class="ca-locked-badge">🔒</span>' : '' ?></td>
                    <td><?= e($p['title']) ?></td>
                    <td><?= e($p['customer_name']) ?></td>
                    <td class="num"><?= number_format((int)$p['balance_amount']) ?></td>
                    <td><?= $p['settlement_confirmed'] ? '✓' : '✗' ?></td>
                    <td><a href="/cases.php?action=edit&id=<?= $p['id'] ?>" target="_blank" class="ca-edit-btn">編輯</a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<div class="ca-summary">
    <strong>💡 提示</strong>
    <ul style="margin:8px 0 0 0;padding-left:24px;font-size:.9rem">
        <li>修正後存檔，系統會自動偵測：若案件變成「乾淨」狀態（balance=0、結清=✓、完工日齊），會自動上鎖</li>
        <li>已上鎖的案件（顯示 🔒）若要修正，需 boss / 副總到案件編輯頁解鎖後才能修改</li>
        <li>建議優先處理「異常 1：帳款未平」，這影響財務報表準確度</li>
    </ul>
</div>

<script>
// 視窗重新 focus 時自動 reload（例如從另一分頁編輯回來）
let lastFocusTime = Date.now();
window.addEventListener('focus', function() {
    // 距上次至少 5 秒才 reload，避免快速切換頻繁刷新
    if (Date.now() - lastFocusTime > 5000) {
        location.reload();
    }
    lastFocusTime = Date.now();
});
</script>
