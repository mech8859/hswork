<?php
/**
 * 案件結案鎖：上線前盤點腳本
 *
 * 用途：列出所有「status='closed' 但 balance_amount != 0」的舊資料
 * 這些案件在批次鎖之前需先人工確認、修正錯誤資料，否則錯資料會被鎖入
 *
 * 權限：boss / vice_president
 * 用法：登入後開啟 https://hswork.com.tw/case_lock_audit.php
 */
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();

$user = Auth::user();
if (!in_array($user['role'], array('boss', 'vice_president'))) {
    http_response_code(403);
    echo '無權限';
    exit;
}

$db = Database::getInstance();

// 1) 結案案件總數
$totalClosed = (int)$db->query("SELECT COUNT(*) FROM cases WHERE status='closed'")->fetchColumn();

// 2) 結案但帳款未平
$problemStmt = $db->query("
    SELECT id, case_number, title, customer_name, branch_id,
           total_amount, deal_amount, balance_amount, total_collected,
           settlement_confirmed, settlement_date, completion_date
    FROM cases
    WHERE status='closed' AND balance_amount IS NOT NULL AND balance_amount != 0
    ORDER BY id DESC
");
$problems = $problemStmt->fetchAll(PDO::FETCH_ASSOC);

// 3) 結案但未標結清
$noSettleStmt = $db->query("
    SELECT id, case_number, title, customer_name, balance_amount, settlement_confirmed
    FROM cases
    WHERE status='closed' AND (settlement_confirmed IS NULL OR settlement_confirmed = 0)
    ORDER BY id DESC
");
$noSettle = $noSettleStmt->fetchAll(PDO::FETCH_ASSOC);

// 4) 結案但缺完工日
$noCompletionStmt = $db->query("
    SELECT id, case_number, title, customer_name, completion_date
    FROM cases
    WHERE status='closed' AND completion_date IS NULL
    ORDER BY id DESC
");
$noCompletion = $noCompletionStmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = '案件結案鎖：上線前盤點';
$currentPage = 'cases';
require __DIR__ . '/../templates/layouts/header.php';
?>
<style>
.audit-section { margin-bottom: 24px; }
.audit-table { width: 100%; border-collapse: collapse; font-size: 14px; }
.audit-table th, .audit-table td { padding: 6px 10px; border: 1px solid #ddd; text-align: left; }
.audit-table th { background: #f5f5f5; }
.audit-table .num { text-align: right; }
.summary-card { padding: 12px; background: #fff3cd; border-left: 4px solid #ff9800; margin-bottom: 16px; }
.summary-card.ok { background: #e8f5e9; border-left-color: #4caf50; }
</style>

<h2>🔍 案件結案鎖：上線前盤點報告</h2>
<p style="color:#666">執行時間：<?= date('Y-m-d H:i:s') ?> ｜ 操作人：<?= e($user['real_name']) ?></p>

<div class="summary-card <?= empty($problems) && empty($noSettle) && empty($noCompletion) ? 'ok' : '' ?>">
    <strong>總結</strong><br>
    結案案件總數：<b><?= number_format($totalClosed) ?></b> 筆<br>
    異常 1（帳款未平）：<b><?= count($problems) ?></b> 筆<br>
    異常 2（未標結清）：<b><?= count($noSettle) ?></b> 筆<br>
    異常 3（缺完工日）：<b><?= count($noCompletion) ?></b> 筆
</div>

<div class="audit-section">
    <h3>異常 1：結案但 balance_amount ≠ 0（最重要）</h3>
    <p style="color:#888;font-size:13px">
        理論上：結案 = 帳款結清 → balance 應為 0。出現非零的可能原因：歷史資料錯誤、收款作廢未回滾、四捨五入誤差。
    </p>
    <?php if (empty($problems)): ?>
    <p style="color:#4caf50">✓ 無異常</p>
    <?php else: ?>
    <table class="audit-table">
        <thead><tr>
            <th>ID</th><th>案件編號</th><th>標題</th><th>客戶</th>
            <th class="num">含稅金額</th><th class="num">成交金額</th>
            <th class="num">已收</th><th class="num">尾款</th>
            <th>結清</th><th>結清日</th><th>完工日</th>
            <th>動作</th>
        </tr></thead>
        <tbody>
            <?php foreach ($problems as $p): ?>
            <tr>
                <td><?= $p['id'] ?></td>
                <td><?= e($p['case_number']) ?></td>
                <td><?= e($p['title']) ?></td>
                <td><?= e($p['customer_name']) ?></td>
                <td class="num"><?= number_format((int)$p['total_amount']) ?></td>
                <td class="num"><?= number_format((int)$p['deal_amount']) ?></td>
                <td class="num"><?= number_format((int)$p['total_collected']) ?></td>
                <td class="num" style="color:red;font-weight:bold"><?= number_format((int)$p['balance_amount']) ?></td>
                <td><?= $p['settlement_confirmed'] ? '✓' : '✗' ?></td>
                <td><?= e($p['settlement_date'] ?? '') ?></td>
                <td><?= e($p['completion_date'] ?? '') ?></td>
                <td><a href="/cases.php?action=edit&id=<?= $p['id'] ?>" target="_blank">編輯</a></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<div class="audit-section">
    <h3>異常 2：結案但未標結清（settlement_confirmed = 0）</h3>
    <?php if (empty($noSettle)): ?>
    <p style="color:#4caf50">✓ 無異常</p>
    <?php else: ?>
    <table class="audit-table">
        <thead><tr>
            <th>ID</th><th>案件編號</th><th>標題</th><th>客戶</th>
            <th class="num">尾款</th><th>動作</th>
        </tr></thead>
        <tbody>
            <?php foreach ($noSettle as $p): ?>
            <tr>
                <td><?= $p['id'] ?></td>
                <td><?= e($p['case_number']) ?></td>
                <td><?= e($p['title']) ?></td>
                <td><?= e($p['customer_name']) ?></td>
                <td class="num"><?= number_format((int)$p['balance_amount']) ?></td>
                <td><a href="/cases.php?action=edit&id=<?= $p['id'] ?>" target="_blank">編輯</a></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<div class="audit-section">
    <h3>異常 3：結案但缺完工日</h3>
    <?php if (empty($noCompletion)): ?>
    <p style="color:#4caf50">✓ 無異常</p>
    <?php else: ?>
    <table class="audit-table">
        <thead><tr><th>ID</th><th>案件編號</th><th>標題</th><th>客戶</th><th>動作</th></tr></thead>
        <tbody>
            <?php foreach ($noCompletion as $p): ?>
            <tr>
                <td><?= $p['id'] ?></td>
                <td><?= e($p['case_number']) ?></td>
                <td><?= e($p['title']) ?></td>
                <td><?= e($p['customer_name']) ?></td>
                <td><a href="/cases.php?action=edit&id=<?= $p['id'] ?>" target="_blank">編輯</a></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<div class="summary-card">
    <strong>下一步</strong><br>
    1. 異常 1 必須先修正（balance_amount 應為 0）才能批次鎖<br>
    2. 異常 2、3 可選擇修正後再鎖，或允許帶錯入鎖（管理員後續手動解鎖修正）<br>
    3. 確認可以批次鎖之後，請開啟 <a href="/case_lock_batch.php">case_lock_batch.php</a> 執行批次鎖
</div>

<?php require __DIR__ . '/../templates/layouts/footer.php'; ?>
