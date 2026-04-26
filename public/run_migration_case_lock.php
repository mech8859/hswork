<?php
/**
 * 一次性執行：migration_136_case_close_lock.sql
 *
 * 用途：在 cases 表加入 5 個結案鎖欄位 + 1 個索引
 * 權限：boss 限定
 * 用法：登入後開啟 https://hswork.com.tw/run_migration_case_lock.php
 *
 * 跑完之後此檔案可保留（重複執行會偵測欄位已存在跳過）
 */
// 開啟錯誤顯示，確保 fatal 不會吞掉
error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();

$user = Auth::user();
$allowedRoles = array('boss', 'vice_president');
if (!in_array($user['role'], $allowedRoles)) {
    http_response_code(403);
    echo '<h3 style="font-family:sans-serif;padding:20px">無權限執行 Migration</h3>';
    echo '<p style="font-family:sans-serif;padding:0 20px">僅 boss / vice_president 可執行。</p>';
    echo '<p style="font-family:sans-serif;padding:0 20px;color:#666">您的角色：<b>' . htmlspecialchars($user['role']) . '</b>（' . htmlspecialchars($user['real_name'] ?? '') . '）</p>';
    exit;
}

$db = Database::getInstance();

// 用 information_schema 而非 SHOW COLUMNS / SHOW INDEX，相容性較好
function colExists($db, $table, $col) {
    $stmt = $db->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
    $stmt->execute(array($table, $col));
    return (int)$stmt->fetchColumn() > 0;
}
function indexExists($db, $table, $idx) {
    $stmt = $db->prepare("SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?");
    $stmt->execute(array($table, $idx));
    return (int)$stmt->fetchColumn() > 0;
}

$pageTitle = 'Migration：案件結案鎖欄位';
$currentPage = 'cases';
require __DIR__ . '/../templates/layouts/header.php';

$results = array();
$confirmed = isset($_POST['confirm']) && $_POST['confirm'] === 'YES';

if ($confirmed) {
    if (!verify_csrf()) {
        Session::flash('error', '安全驗證失敗');
        redirect('/run_migration_case_lock.php');
    }

    $statements = array(
        'is_locked'   => "ALTER TABLE cases ADD COLUMN is_locked TINYINT(1) NOT NULL DEFAULT 0 COMMENT '結案上鎖標記' AFTER status",
        'locked_by'   => "ALTER TABLE cases ADD COLUMN locked_by INT UNSIGNED NULL DEFAULT NULL COMMENT '上鎖人 user_id' AFTER is_locked",
        'locked_at'   => "ALTER TABLE cases ADD COLUMN locked_at DATETIME NULL DEFAULT NULL COMMENT '上鎖時間' AFTER locked_by",
        'unlocked_at' => "ALTER TABLE cases ADD COLUMN unlocked_at DATETIME NULL DEFAULT NULL COMMENT '解鎖時間' AFTER locked_at",
        'unlocked_by' => "ALTER TABLE cases ADD COLUMN unlocked_by INT UNSIGNED NULL DEFAULT NULL COMMENT '解鎖人 user_id' AFTER unlocked_at",
    );

    foreach ($statements as $col => $sql) {
        if (colExists($db, 'cases', $col)) {
            $results[] = array('ok' => true, 'msg' => "欄位 {$col} 已存在，跳過");
            continue;
        }
        try {
            $db->exec($sql);
            $results[] = array('ok' => true, 'msg' => "✓ 新增欄位 {$col}");
        } catch (Exception $e) {
            $results[] = array('ok' => false, 'msg' => "✗ 新增欄位 {$col} 失敗：" . $e->getMessage());
        }
    }

    if (indexExists($db, 'cases', 'idx_cases_locked')) {
        $results[] = array('ok' => true, 'msg' => '索引 idx_cases_locked 已存在，跳過');
    } else {
        try {
            $db->exec("CREATE INDEX idx_cases_locked ON cases (is_locked, status)");
            $results[] = array('ok' => true, 'msg' => '✓ 建立索引 idx_cases_locked');
        } catch (Exception $e) {
            $results[] = array('ok' => false, 'msg' => '✗ 建立索引失敗：' . $e->getMessage());
        }
    }

    AuditLog::log('cases', 'migration', 0, 'Migration：加入案件結案鎖欄位');
}

$cols = array('is_locked', 'locked_by', 'locked_at', 'unlocked_at', 'unlocked_by');
$colStatus = array();
foreach ($cols as $c) {
    $colStatus[$c] = colExists($db, 'cases', $c);
}
$idxStatus = indexExists($db, 'cases', 'idx_cases_locked');
$allOk = !in_array(false, $colStatus, true) && $idxStatus;
?>
<style>
.box { padding: 14px; margin-bottom: 14px; border-left: 4px solid #ccc; }
.box.ok { background: #e8f5e9; border-left-color: #4caf50; }
.box.warn { background: #fff3cd; border-left-color: #ff9800; }
.box.err { background: #ffebee; border-left-color: #f44336; }
.status-table { border-collapse: collapse; }
.status-table td { padding: 6px 12px; border-bottom: 1px solid #eee; }
</style>

<h2>🛠️ Migration：案件結案鎖欄位</h2>

<div class="box <?= $allOk ? 'ok' : 'warn' ?>">
    <strong>目前 DB 狀態</strong>
    <table class="status-table" style="margin-top:8px">
        <?php foreach ($cols as $c): ?>
        <tr>
            <td>cases.<?= $c ?></td>
            <td><?= $colStatus[$c] ? '<span style="color:#4caf50">✓ 已存在</span>' : '<span style="color:#c62828">✗ 未建立</span>' ?></td>
        </tr>
        <?php endforeach; ?>
        <tr>
            <td>idx_cases_locked</td>
            <td><?= $idxStatus ? '<span style="color:#4caf50">✓ 已存在</span>' : '<span style="color:#c62828">✗ 未建立</span>' ?></td>
        </tr>
    </table>
</div>

<?php if ($confirmed && !empty($results)): ?>
<div class="box <?= count(array_filter($results, function($r){ return !$r['ok']; })) === 0 ? 'ok' : 'err' ?>">
    <strong>執行結果</strong>
    <ul>
        <?php foreach ($results as $r): ?>
        <li style="color:<?= $r['ok'] ? '#2e7d32' : '#c62828' ?>"><?= e($r['msg']) ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<?php if ($allOk): ?>
<div class="box ok">
    <strong>✓ Migration 已完成</strong>
    <p>下一步：</p>
    <ol>
        <li><a href="/case_lock_audit.php">執行盤點報告（case_lock_audit.php）</a></li>
        <li>修正異常資料</li>
        <li><a href="/case_lock_batch.php">批次鎖舊結案案件（case_lock_batch.php）</a></li>
    </ol>
</div>
<?php else: ?>
<form method="POST" onsubmit="return confirm('確定要執行 Migration 嗎？\n將在 cases 表新增 5 個欄位 + 1 個索引。\n此操作可重複執行（已存在的欄位會跳過）。');">
    <?= csrf_field() ?>
    <input type="hidden" name="confirm" value="YES">
    <button type="submit" class="btn btn-primary" style="font-size:1.1rem;padding:10px 24px">
        ▶️ 執行 Migration
    </button>
</form>
<?php endif; ?>

<?php require __DIR__ . '/../templates/layouts/footer.php'; ?>
