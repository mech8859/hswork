<?php
/**
 * Cron：修復卡住的簽核（overtime / leaves status='pending' 但無 approval_flows）
 * URL: https://hswork.com.tw/cron_fix_stuck_approvals.php?key=YOUR_KEY
 *
 * 每 30 分鐘由 bootstrap 觸發一次，自動補建 approval_flows + 發通知給簽核人
 */
$cronKey = 'hswork_approval_fix_2026';
if (($_GET['key'] ?? '') !== $cronKey) {
    http_response_code(403);
    die('Forbidden');
}

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../modules/approvals/ApprovalModel.php';
require_once __DIR__ . '/../modules/notifications/NotificationModel.php';

@set_time_limit(60);
header('Content-Type: text/plain; charset=utf-8');

$db = Database::getInstance();
$am = new ApprovalModel();
$nm = new NotificationModel();

$totalFixed = 0;
$logs = array();

// overtime / leaves 共用邏輯
$modules = array(
    'overtime' => array('table' => 'overtimes', 'label' => '加班單'),
    'leaves'   => array('table' => 'leaves',   'label' => '請假單'),
);

foreach ($modules as $module => $info) {
    $table = $info['table'];
    $label = $info['label'];

    // 掃 pending + 無 approval_flows 的（最近 30 天，避免歷史資料拖慢）
    $stmt = $db->prepare("
        SELECT t.id, t.user_id, u.real_name
        FROM `{$table}` t
        LEFT JOIN users u ON t.user_id = u.id
        WHERE t.status = 'pending'
          AND t.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
          AND NOT EXISTS (
              SELECT 1 FROM approval_flows af
              WHERE af.module = ? AND af.target_id = t.id
          )
        ORDER BY t.id
    ");
    $stmt->execute(array($module));
    $stuck = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($stuck as $row) {
        $tid = (int)$row['id'];
        $uid = (int)$row['user_id'];
        if (!$uid) continue;

        try {
            $result = $am->submitForApproval($module, $tid, 0, null, $uid);
            if (!is_array($result) || isset($result['auto_approved'])) {
                $logs[] = "[{$module}#{$tid}] {$row['real_name']}: 自動通過或無規則";
                continue;
            }
            // 補通知給每個 flow 的簽核人
            foreach ($result as $flow) {
                $nm->send(
                    (int)$flow['approver_id'],
                    'approval_pending',
                    "待簽核（{$label} 第" . (int)$flow['level_order'] . "關）：" . $row['real_name'],
                    '請進入待簽核確認',
                    '/approvals.php?action=pending',
                    $module, $tid, $uid
                );
            }
            $logs[] = "[{$module}#{$tid}] {$row['real_name']}: 補建 " . count($result) . " 筆 flow + 通知";
            $totalFixed++;
        } catch (Exception $e) {
            $logs[] = "[{$module}#{$tid}] ERROR: " . $e->getMessage();
        }
    }
}

if ($totalFixed > 0) {
    AuditLog::log('approvals', 'auto_fix_stuck', 0, "cron 修復 {$totalFixed} 筆卡住簽核");
}

echo "==== Cron Run @ " . date('Y-m-d H:i:s') . " ====\n";
echo "Total fixed: {$totalFixed}\n\n";
echo implode("\n", $logs) . "\n";
