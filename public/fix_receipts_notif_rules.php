<?php
/**
 * 診斷 + 修復：收款單已收款通知規則
 *
 * 預期狀態（兩條都啟用）：
 *   1. field + sales_id        → 只通知該筆收款單的業務本人
 *   2. role + sales_assistant  → 通知同分公司業務助理
 *
 * 若規則 6 被改成 field 型（導致業務助理收不到通知），此工具可一鍵還原。
 */
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (!Auth::hasPermission('system.manage') && !Auth::hasPermission('all')) {
    die('No permission');
}

$db = Database::getInstance();
header('Content-Type: text/html; charset=utf-8');

echo "<h3>收款單已收款通知規則 診斷 / 修復</h3>";

$action = isset($_GET['go']) ? $_GET['go'] : '';

// 列出目前所有相關規則
$stmt = $db->query("
    SELECT * FROM notification_settings
    WHERE module = 'receipts'
      AND event = 'status_changed'
      AND condition_field = 'status'
      AND condition_value = '已收款'
    ORDER BY sort_order
");
$rules = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<h4>目前規則</h4>";
echo "<table border='1' cellpadding='6' cellspacing='0' style='border-collapse:collapse;font-size:.9rem'>";
echo "<thead><tr><th>id</th><th>排序</th><th>啟用</th><th>通知類型</th><th>目標</th><th>分公司範圍</th><th>標題</th></tr></thead><tbody>";
foreach ($rules as $r) {
    $isCorrectField = ($r['notify_type'] === 'field' && $r['notify_target'] === 'sales_id');
    $isCorrectRole = ($r['notify_type'] === 'role' && $r['notify_target'] === 'sales_assistant');
    $color = ($isCorrectField || $isCorrectRole) ? '#2e7d32' : '#c62828';
    echo "<tr style='color:{$color}'>"
       . "<td>{$r['id']}</td>"
       . "<td>{$r['sort_order']}</td>"
       . "<td>" . ($r['is_active'] ? '✓' : '✗') . "</td>"
       . "<td>" . htmlspecialchars($r['notify_type']) . "</td>"
       . "<td>" . htmlspecialchars($r['notify_target']) . "</td>"
       . "<td>" . htmlspecialchars($r['branch_scope']) . "</td>"
       . "<td>" . htmlspecialchars($r['title_template']) . "</td>"
       . "</tr>";
}
echo "</tbody></table>";

// 檢查是否已符合預期
$hasField = false;
$hasRole = false;
foreach ($rules as $r) {
    if (!$r['is_active']) continue;
    if ($r['notify_type'] === 'field' && $r['notify_target'] === 'sales_id') $hasField = true;
    if ($r['notify_type'] === 'role' && $r['notify_target'] === 'sales_assistant') $hasRole = true;
}

echo "<h4>檢查結果</h4><ul>";
echo "<li>規則「欄位=sales_id」(通知該業務)：" . ($hasField ? '<b style="color:#2e7d32">✓ 存在且啟用</b>' : '<b style="color:#c62828">✗ 缺少</b>') . "</li>";
echo "<li>規則「角色=sales_assistant」(通知業務助理)：" . ($hasRole ? '<b style="color:#2e7d32">✓ 存在且啟用</b>' : '<b style="color:#c62828">✗ 缺少</b>') . "</li>";
echo "</ul>";

// 執行修復
if ($action === 'fix') {
    echo "<hr><h4>執行修復</h4>";

    // 1. 確保有 field + sales_id 規則
    $fieldStmt = $db->prepare("
        SELECT id FROM notification_settings
        WHERE module='receipts' AND event='status_changed'
          AND condition_field='status' AND condition_value='已收款'
          AND notify_type='field' AND notify_target='sales_id'
        LIMIT 1
    ");
    $fieldStmt->execute();
    $fieldId = $fieldStmt->fetchColumn();
    if ($fieldId) {
        $db->prepare("UPDATE notification_settings SET is_active=1 WHERE id=?")->execute(array($fieldId));
        echo "<p style='color:#2e7d32'>✓ 已確保規則 id={$fieldId}（field sales_id）為啟用</p>";
    } else {
        $db->prepare("
            INSERT INTO notification_settings
            (module, event, condition_field, condition_value, notify_type, notify_target, branch_scope,
             title_template, message_template, link_template, is_active, sort_order)
            VALUES ('receipts','status_changed','status','已收款','field','sales_id','same',
                    '收款單已收款通知',
                    '客戶：{customer_name}，收款金額：NT\${total_amount}，狀態已更新為：已收款',
                    '/receipts.php?action=edit&id={id}',1,5)
        ")->execute();
        echo "<p style='color:#2e7d32'>✓ 新增規則：field sales_id</p>";
    }

    // 2. 確保有 role + sales_assistant 規則
    $roleStmt = $db->prepare("
        SELECT id FROM notification_settings
        WHERE module='receipts' AND event='status_changed'
          AND condition_field='status' AND condition_value='已收款'
          AND notify_type='role' AND notify_target='sales_assistant'
        LIMIT 1
    ");
    $roleStmt->execute();
    $roleId = $roleStmt->fetchColumn();
    if ($roleId) {
        $db->prepare("UPDATE notification_settings SET is_active=1, branch_scope='same' WHERE id=?")->execute(array($roleId));
        echo "<p style='color:#2e7d32'>✓ 已確保規則 id={$roleId}（role sales_assistant）為啟用+同分公司</p>";
    } else {
        $db->prepare("
            INSERT INTO notification_settings
            (module, event, condition_field, condition_value, notify_type, notify_target, branch_scope,
             title_template, message_template, link_template, is_active, sort_order)
            VALUES ('receipts','status_changed','status','已收款','role','sales_assistant','same',
                    '收款單已收款通知',
                    '客戶：{customer_name}，收款金額：NT\${total_amount}，狀態已更新為：已收款',
                    '/receipts.php?action=edit&id={id}',1,6)
        ")->execute();
        echo "<p style='color:#2e7d32'>✓ 新增規則：role sales_assistant（同分公司）</p>";
    }

    echo "<p><a href='/fix_receipts_notif_rules.php'>重新檢查</a> | <a href='/notifications.php?action=settings'>回通知設定</a></p>";
} else {
    if ($hasField && $hasRole) {
        echo "<p style='color:#2e7d32'><b>✓ 規則已正確，不需修復。</b></p>";
    } else {
        echo "<p><a href='?go=fix' onclick='return confirm(\"確定要還原為標準兩條規則？\")' "
           . "style='display:inline-block;padding:8px 20px;background:#1a73e8;color:#fff;text-decoration:none;border-radius:4px'>"
           . "一鍵還原預設兩條規則</a></p>";
    }
}

echo "<hr><p><small>還原後：</small><br>"
   . "<small>• 排序 5：記錄欄位 sales_id → 通知該收款單的業務本人</small><br>"
   . "<small>• 排序 6：角色 sales_assistant + 同分公司 → 通知同分公司業務助理</small></p>";
