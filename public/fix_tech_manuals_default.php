<?php
/**
 * 為所有角色補上「技術手冊」預設檢視權限
 * 存在 system_roles.default_permissions (JSON)
 */
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (!Auth::hasPermission('system.manage') && !Auth::hasPermission('all')) {
    die('No permission');
}

$db = Database::getInstance();
header('Content-Type: text/html; charset=utf-8');

$dryRun = !isset($_GET['go']) || $_GET['go'] !== '1';

echo "<h3>角色預設權限：補上「技術手冊」= 檢視</h3>";
echo "<p>模式：" . ($dryRun ? '<b style="color:#c62828">Dry-run</b>' : '<b style="color:#2e7d32">實際執行</b>') . "</p>";

// 檢查表是否存在
try {
    $chk = $db->query("SHOW TABLES LIKE 'system_roles'");
    $hasTable = $chk && $chk->fetch();
    echo "<p>system_roles 表：" . ($hasTable ? '<b style="color:#2e7d32">存在 ✓</b>' : '<b style="color:#c62828">不存在 ✗</b>') . "</p>";
    if (!$hasTable) {
        echo "<p style='color:#c62828'>此環境角色預設權限不是從 DB 讀取，而是從 config/app.php。我剛剛已經改了 config，請重新整理人員編輯頁看看。</p>";
        echo "<p>如果仍顯示預設: 關閉，請把人員編輯頁的網址給我，我再查實際邏輯。</p>";
        exit;
    }
} catch (Exception $e) {
    echo "<p style='color:#c62828'>檢查表失敗: " . htmlspecialchars($e->getMessage()) . "</p>";
    exit;
}

try {
    // 動態抓欄位（不同環境可能欄位命名不同）
    $colStmt = $db->query("SHOW COLUMNS FROM system_roles");
    $cols = array();
    while ($row = $colStmt->fetch(PDO::FETCH_ASSOC)) $cols[] = $row['Field'];
    $nameCol = in_array('role_name', $cols) ? 'role_name'
             : (in_array('label', $cols) ? 'label'
             : (in_array('name', $cols) ? 'name' : 'role_key'));
    $stmt = $db->query("SELECT id, role_key, `{$nameCol}` AS display_name, default_permissions, is_active FROM system_roles ORDER BY id");
    $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "<p>找到 <b>" . count($roles) . "</b> 筆角色設定</p>";
} catch (Exception $e) {
    echo "<p style='color:#c62828'>查詢失敗: " . htmlspecialchars($e->getMessage()) . "</p>";
    exit;
}

if (empty($roles)) {
    echo "<p style='color:#c62828'>system_roles 表無資料。可能此環境沒用這個機制，而是直接看 config/app.php。</p>";
    echo "<p>請告訴我你看到「預設: 關閉」的人員編輯頁連結（或人員帳號），我再查實際邏輯。</p>";
    exit;
}

echo "<table border='1' cellpadding='6' cellspacing='0' style='border-collapse:collapse;font-size:.85rem'>";
echo "<thead><tr><th>id</th><th>role_key</th><th>名稱</th><th>啟用</th><th>tech_manuals 現值</th><th>動作</th></tr></thead><tbody>";

$upd = $db->prepare("UPDATE system_roles SET default_permissions = ? WHERE id = ?");
$changed = 0;
$skipped = 0;

foreach ($roles as $r) {
    $perms = !empty($r['default_permissions']) ? json_decode($r['default_permissions'], true) : array();
    if (!is_array($perms)) $perms = array();

    $current = isset($perms['tech_manuals']) ? (string)$perms['tech_manuals'] : '(未設)';
    $hasAll = !empty($perms['_all']);

    $action = '';
    $willUpdate = false;

    if ($hasAll) {
        $action = "有 _all，略過";
        $skipped++;
    } elseif (isset($perms['tech_manuals']) && $perms['tech_manuals'] === 'tech_manuals.view') {
        $action = "已是檢視，略過";
        $skipped++;
    } else {
        $perms['tech_manuals'] = 'tech_manuals.view';
        $willUpdate = true;
        $action = "<b style='color:#2e7d32'>" . ($dryRun ? '將更新為' : '已更新為') . " tech_manuals.view</b>";
        if (!$dryRun) {
            $upd->execute(array(json_encode($perms, JSON_UNESCAPED_UNICODE), $r['id']));
            AuditLog::log('system_roles', 'update', $r['id'], '新增 tech_manuals.view 預設');
        }
        $changed++;
    }

    echo "<tr>"
       . "<td>{$r['id']}</td>"
       . "<td>" . htmlspecialchars($r['role_key']) . "</td>"
       . "<td>" . htmlspecialchars((string)$r['display_name']) . "</td>"
       . "<td>" . ($r['is_active'] ? '✓' : '✗') . "</td>"
       . "<td>" . htmlspecialchars($current) . ($hasAll ? ' (有_all)' : '') . "</td>"
       . "<td>{$action}</td>"
       . "</tr>";
}
echo "</tbody></table>";

echo "<hr><p>將更新：<b>{$changed}</b>，略過：<b>{$skipped}</b></p>";

if ($dryRun && $changed > 0) {
    echo "<p><a href='?go=1' onclick='return confirm(\"確定更新 {$changed} 個角色？\")' "
       . "style='display:inline-block;padding:8px 20px;background:#c62828;color:#fff;text-decoration:none;border-radius:4px'>"
       . "執行更新</a></p>";
} elseif (!$dryRun) {
    echo "<p style='color:#2e7d32'>✓ 完成。請重新整理人員編輯頁確認。</p>";
}
