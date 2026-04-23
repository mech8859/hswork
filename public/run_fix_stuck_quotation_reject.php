<?php
/**
 * 修復卡住的報價單：approval_flows 已 rejected 但 quotations.status 還是 pending_approval
 *
 * 原因：approvals.php reject action 的 quotations 分支曾因 $db 變數未定義 Fatal error，
 *       導致 approval_flows 記錄退回，但 quotations.status 未同步更新。
 * 本腳本掃描並批次修正，將這類報價單改為 rejected_internal（可編輯狀態）。
 *
 * 執行完後請刪除此檔。
 */
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();

// 僅 boss 可執行
if (Auth::user()['role'] !== 'boss') {
    http_response_code(403);
    echo '僅系統管理者可執行';
    exit;
}

$db = Database::getInstance();

echo '<!DOCTYPE html><html lang="zh-Hant"><head><meta charset="utf-8"><title>修復卡住的報價單退回</title>';
echo '<style>body{font-family:sans-serif;padding:24px;max-width:920px;margin:auto} .ok{color:#137333} .warn{color:#c5221f} table{border-collapse:collapse;margin:10px 0;width:100%} td,th{border:1px solid #ccc;padding:6px 10px;text-align:left;font-size:.9rem} th{background:#f1f3f5} .btn{display:inline-block;padding:8px 14px;background:#1967d2;color:#fff;text-decoration:none;border-radius:4px;margin-right:8px} .btn-danger{background:#c5221f}</style>';
echo '</head><body>';
echo '<h2>修復卡住的報價單退回</h2>';

$confirmed = isset($_GET['confirm']) && $_GET['confirm'] === '1';

// 找出所有有 rejected flow、但 quotation 還卡在 pending_approval 的報價單
$sql = "
    SELECT q.id, q.quotation_number, q.status AS quote_status,
           q.customer_name, q.total_amount,
           MAX(af.decided_at) AS latest_reject_at,
           COUNT(af.id) AS reject_flow_count
    FROM quotations q
    JOIN approval_flows af ON af.module = 'quotations' AND af.target_id = q.id AND af.status = 'rejected'
    WHERE q.status = 'pending_approval'
    GROUP BY q.id, q.quotation_number, q.status, q.customer_name, q.total_amount
    ORDER BY latest_reject_at DESC
";

try {
    $rows = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    echo '<p class="warn">查詢失敗：' . htmlspecialchars($e->getMessage()) . '</p>';
    echo '</body></html>';
    exit;
}

if (empty($rows)) {
    echo '<p class="ok">✓ 沒有需要修復的報價單。</p>';
    echo '<p><a href="/approvals.php">回待簽核</a></p>';
    echo '</body></html>';
    exit;
}

echo '<p>共找到 <strong>' . count($rows) . '</strong> 筆卡住的報價單（approval_flows 已退回但 quotations.status 仍為 pending_approval）：</p>';

echo '<table><thead><tr><th>#</th><th>報價單號</th><th>客戶</th><th>金額</th><th>目前狀態</th><th>退回時間</th></tr></thead><tbody>';
foreach ($rows as $i => $r) {
    echo '<tr>';
    echo '<td>' . ($i + 1) . '</td>';
    echo '<td>' . htmlspecialchars($r['quotation_number']) . '</td>';
    echo '<td>' . htmlspecialchars($r['customer_name']) . '</td>';
    echo '<td>$' . number_format((float)$r['total_amount']) . '</td>';
    echo '<td>' . htmlspecialchars($r['quote_status']) . '</td>';
    echo '<td>' . htmlspecialchars(substr($r['latest_reject_at'] ?? '', 0, 16)) . '</td>';
    echo '</tr>';
}
echo '</tbody></table>';

if (!$confirmed) {
    echo '<p>點下方按鈕將把這些報價單的狀態改為 <strong>rejected_internal</strong>（可重新編輯後送簽核）：</p>';
    echo '<p><a class="btn" href="?confirm=1">確認修復 ' . count($rows) . ' 筆</a>';
    echo '<a class="btn" style="background:#999" href="/approvals.php">取消</a></p>';
} else {
    $ids = array_column($rows, 'id');
    $ph  = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $db->prepare("UPDATE quotations SET status = 'rejected_internal' WHERE id IN ($ph) AND status = 'pending_approval'");
    $stmt->execute($ids);
    $affected = $stmt->rowCount();

    AuditLog::log('quotations', 'bulk_fix_stuck_reject', 0, '批次修復 ' . $affected . ' 筆卡住的退回報價單');

    echo '<p class="ok">✓ 已更新 <strong>' . $affected . '</strong> 筆報價單為 rejected_internal，現在可編輯後重新送簽核。</p>';
    echo '<p><a class="btn" href="/approvals.php">回待簽核</a> <a class="btn" href="/quotations.php">看報價單列表</a></p>';
    echo '<p class="warn">⚠ 修復完成後，請從伺服器刪除此腳本檔案：<code>/www/run_fix_stuck_quotation_reject.php</code></p>';
}

echo '</body></html>';
