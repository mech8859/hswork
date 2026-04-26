<?php
/**
 * 案件結案鎖：批次鎖（僅鎖乾淨案件）
 *
 * 用途：把所有「乾淨已結案」案件設 is_locked=1
 * 條件：status='closed' AND balance=0 AND settlement_confirmed=1 AND completion_date 不空 AND is_locked=0
 * 異常案件（尾款>0、未結清、缺完工日）不會鎖，留待人工修正
 *
 * 此腳本可重複執行（每次都重掃，新乾淨的會被鎖）
 *
 * 權限：boss / vice_president
 * 用法：登入後開啟 https://hswork.com.tw/case_lock_batch.php
 */
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();

$user = Auth::user();
if (!in_array($user['role'], array('boss', 'vice_president'))) {
    http_response_code(403);
    echo '僅 boss / vice_president 可執行批次鎖';
    exit;
}

$db = Database::getInstance();

$pageTitle = '案件結案鎖：批次自動鎖（僅鎖乾淨）';
$currentPage = 'cases';
require __DIR__ . '/../templates/layouts/header.php';

// 條件清楚：乾淨案件
$cleanCondition = "
    status = 'closed'
    AND is_locked = 0
    AND (balance_amount IS NULL OR balance_amount = 0)
    AND settlement_confirmed = 1
    AND completion_date IS NOT NULL
";

$cleanCount = (int)$db->query("SELECT COUNT(*) FROM cases WHERE {$cleanCondition}")->fetchColumn();
$totalClosed = (int)$db->query("SELECT COUNT(*) FROM cases WHERE status='closed'")->fetchColumn();
$lockedCount = (int)$db->query("SELECT COUNT(*) FROM cases WHERE status='closed' AND is_locked=1")->fetchColumn();
$dirtyCount = (int)$db->query("
    SELECT COUNT(*) FROM cases
    WHERE status='closed' AND is_locked=0
      AND ((balance_amount IS NOT NULL AND balance_amount != 0)
           OR settlement_confirmed != 1
           OR completion_date IS NULL)
")->fetchColumn();

$confirmed = isset($_POST['confirm']) && $_POST['confirm'] === 'YES';
$executed = false;
$affectedRows = 0;

if ($confirmed) {
    if (!verify_csrf()) {
        Session::flash('error', '安全驗證失敗');
        redirect('/case_lock_batch.php');
    }
    try {
        $stmt = $db->prepare("
            UPDATE cases
            SET is_locked = 1,
                locked_by = ?,
                locked_at = NOW()
            WHERE {$cleanCondition}
        ");
        $stmt->execute(array($user['id']));
        $affectedRows = $stmt->rowCount();
        $executed = true;
        AuditLog::log('cases', 'batch_lock_clean', 0, "批次鎖乾淨結案案件 {$affectedRows} 筆");
        // 重新計數
        $cleanCount = (int)$db->query("SELECT COUNT(*) FROM cases WHERE {$cleanCondition}")->fetchColumn();
        $lockedCount = (int)$db->query("SELECT COUNT(*) FROM cases WHERE status='closed' AND is_locked=1")->fetchColumn();
    } catch (Exception $e) {
        Session::flash('error', '批次鎖失敗：' . $e->getMessage());
    }
}
?>
<style>
.warn-box { padding: 16px; background: #fff3cd; border-left: 4px solid #ff9800; margin-bottom: 16px; }
.danger-box { padding: 16px; background: #ffebee; border-left: 4px solid #f44336; margin-bottom: 16px; }
.success-box { padding: 16px; background: #e8f5e9; border-left: 4px solid #4caf50; margin-bottom: 16px; }
.info-box { padding: 16px; background: #e3f2fd; border-left: 4px solid #2196f3; margin-bottom: 16px; }
.summary-table { border-collapse: collapse; margin-top: 8px; }
.summary-table td { padding: 6px 14px; border-bottom: 1px solid #eee; }
.summary-table td:nth-child(2) { font-weight: bold; }
</style>

<h2>🔒 案件結案鎖：批次自動鎖（僅鎖乾淨案件）</h2>

<?php if ($executed): ?>
<div class="success-box">
    <h3>✓ 批次鎖已執行</h3>
    <p>本次新鎖：<b><?= number_format($affectedRows) ?></b> 筆</p>
    <p>目前已鎖：<b><?= number_format($lockedCount) ?></b> / <?= number_format($totalClosed) ?> 筆已結案案件</p>
    <p>剩餘待鎖（乾淨）：<b><?= number_format($cleanCount) ?></b> 筆</p>
</div>
<?php endif; ?>

<div class="info-box">
    <strong>📊 目前狀態</strong>
    <table class="summary-table">
        <tr><td>結案案件總數</td><td><?= number_format($totalClosed) ?> 筆</td></tr>
        <tr><td>已上鎖</td><td style="color:#4caf50"><?= number_format($lockedCount) ?> 筆</td></tr>
        <tr><td>未上鎖且乾淨（將被本次鎖定）</td><td style="color:#2196f3"><?= number_format($cleanCount) ?> 筆</td></tr>
        <tr><td>未上鎖但異常（不鎖，需人工修正）</td><td style="color:#ff9800"><?= number_format($dirtyCount) ?> 筆</td></tr>
    </table>
</div>

<div class="warn-box">
    <strong>📝 鎖定條件</strong>
    <ul style="margin:8px 0">
        <li>案件狀態 = 已完工結案（status='closed'）</li>
        <li>尾款 = 0（balance_amount = 0 或 NULL）</li>
        <li>已標結清（settlement_confirmed = 1）</li>
        <li>有完工日（completion_date 不空）</li>
        <li>尚未上鎖（is_locked = 0）</li>
    </ul>
    <small style="color:#666">不符合條件的異常案件不會被鎖。等資料修正後重新跑此腳本即可鎖入。</small>
</div>

<?php if ($cleanCount > 0): ?>
<form method="POST" onsubmit="return confirm('確定要鎖定 <?= number_format($cleanCount) ?> 筆乾淨已結案案件嗎？\n\n異常案件不會被鎖。\n此操作可重複執行（每次都重掃乾淨案件）。');">
    <?= csrf_field() ?>
    <input type="hidden" name="confirm" value="YES">
    <button type="submit" class="btn btn-primary" style="font-size:1.1rem;padding:10px 24px">
        🔒 執行批次鎖（<?= number_format($cleanCount) ?> 筆乾淨案件）
    </button>
    <a href="/case_lock_audit.php" class="btn" style="background:#ff9800;color:#fff">查看異常清單</a>
    <a href="/cases.php" class="btn" style="background:#9e9e9e;color:#fff">返回</a>
</form>
<?php else: ?>
<div class="success-box">
    <strong>✓ 沒有待鎖的乾淨案件</strong>
    <p>所有乾淨案件都已上鎖。
    <?php if ($dirtyCount > 0): ?>
    <br>還有 <?= number_format($dirtyCount) ?> 筆異常案件等待人工修正，修完後可<a href="/case_lock_batch.php">重新跑此腳本</a>把它們鎖入。
    <?php endif; ?>
    </p>
    <a href="/cases.php" class="btn">返回案件清單</a>
    <?php if ($dirtyCount > 0): ?>
    <a href="/case_lock_audit.php" class="btn" style="background:#ff9800;color:#fff">查看異常清單</a>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php require __DIR__ . '/../templates/layouts/footer.php'; ?>
