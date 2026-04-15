<?php
/**
 * 權限設定頁面
 * 顯示使用者所有模組權限，支援自訂覆蓋
 */

// 解析現有自訂權限
$customPerms = array();
if (!empty($user['custom_permissions'])) {
    $decoded = json_decode($user['custom_permissions'], true);
    if (is_array($decoded)) {
        $customPerms = $decoded;
    }
}

// 角色預設權限（優先從 DB，fallback config）
$rolePerms = isset($appConfig['permissions'][$user['role']]) ? $appConfig['permissions'][$user['role']] : array();
$roleSectionDefaults = isset($appConfig['case_section_defaults'][$user['role']])
    ? $appConfig['case_section_defaults'][$user['role']]
    : array('basic');
$roleReportDefaults = isset($appConfig['report_defaults'][$user['role']])
    ? $appConfig['report_defaults'][$user['role']]
    : array();
try {
    $dbPerm = Database::getInstance();
    $rpStmt = $dbPerm->prepare("SELECT default_permissions, default_case_sections, default_reports FROM system_roles WHERE role_key = ? AND is_active = 1 LIMIT 1");
    $rpStmt->execute(array($user['role']));
    $rpRow = $rpStmt->fetch(PDO::FETCH_ASSOC);
    if ($rpRow && !empty($rpRow['default_permissions'])) {
        $dec = json_decode($rpRow['default_permissions'], true);
        if (is_array($dec)) {
            $rolePerms = array();
            if (!empty($dec['_all'])) $rolePerms[] = 'all';
            foreach ($dec as $k => $v) {
                if ($k === '_all') continue;
                if (strpos($k, 'delete_') === 0) {
                    if ($v) $rolePerms[] = substr($k, 7) . '.delete';
                } elseif (is_string($v)) {
                    $rolePerms[] = $v;
                }
            }
        }
    }
    if ($rpRow && !empty($rpRow['default_case_sections'])) {
        $dec = json_decode($rpRow['default_case_sections'], true);
        if (is_array($dec)) $roleSectionDefaults = $dec;
    }
    if ($rpRow && !empty($rpRow['default_reports'])) {
        $dec = json_decode($rpRow['default_reports'], true);
        if (is_array($dec)) $roleReportDefaults = $dec;
    }
} catch (Exception $e) {
    // fallback to config
}
$isAllPerm = in_array('all', $rolePerms);

// 模組定義：key => [label, available_levels]
$modules = array(
    'cases'                => array('label' => '案件管理',   'levels' => array('manage', 'view', 'own', 'assist'), 'has_delete' => true, 'has_create' => true),
    'schedule'             => array('label' => '排工行事曆', 'levels' => array('manage', 'view'), 'has_delete' => true),
    'repairs'              => array('label' => '維修單',     'levels' => array('manage', 'view', 'own'), 'has_delete' => true),
    'staff'                => array('label' => '人員管理',   'levels' => array('manage', 'view'), 'has_delete' => false),
    'staff_skills'         => array('label' => '技能與配對', 'levels' => array('manage', 'view'), 'has_delete' => false),
    'leaves'               => array('label' => '請假管理',   'levels' => array('manage', 'view', 'own'), 'has_delete' => true),
    'inter_branch'         => array('label' => '點工費',     'levels' => array('manage', 'view'), 'has_delete' => true),
    'reports'              => array('label' => '報表',       'levels' => array('view'), 'has_delete' => false),
    'products'             => array('label' => '產品目錄',   'levels' => array('manage', 'view'), 'has_delete' => true),
    'vehicles'             => array('label' => '車輛管理',   'levels' => array('manage', 'view'), 'has_delete' => false),
    'worklog'              => array('label' => '施工回報',   'levels' => array('manage', 'view'), 'has_delete' => false),
    'attendance'           => array('label' => '出勤',       'levels' => array('view'), 'has_delete' => false),
    'reviews'              => array('label' => '五星評價',   'levels' => array('manage', 'view'), 'has_delete' => false),
    'quotations'           => array('label' => '報價管理',   'levels' => array('manage', 'view', 'own'), 'has_delete' => true),
    'customers'            => array('label' => '客戶管理',   'levels' => array('manage', 'view', 'own'), 'has_delete' => true, 'has_create' => true),
    'business_calendar'    => array('label' => '業務行事曆', 'levels' => array('manage', 'view'), 'has_delete' => false),
    'business_tracking'    => array('label' => '業務追蹤',   'levels' => array('manage', 'view', 'own'), 'has_delete' => false),
    'engineering_tracking' => array('label' => '工程追蹤',   'levels' => array('manage', 'view', 'own'), 'has_delete' => false),
    'finance'              => array('label' => '財務會計',   'levels' => array('manage', 'view'), 'has_delete' => true),
    'transactions'         => array('label' => '非廠商交易', 'levels' => array('manage', 'view'), 'has_delete' => true),
    'petty_cash'           => array('label' => '零用金',     'levels' => array('manage', 'view'), 'has_delete' => false),
    'procurement'          => array('label' => '採購',       'levels' => array('manage', 'view'), 'has_delete' => false),
    'inventory'            => array('label' => '庫存',       'levels' => array('manage', 'view'), 'has_delete' => true),
    'accounting'           => array('label' => '會計管理',   'levels' => array('manage', 'view'), 'has_delete' => false),
    'settings'             => array('label' => '系統設定',   'levels' => array('manage'), 'has_delete' => false),
    'system'               => array('label' => '系統維護',   'levels' => array('manage'), 'has_delete' => false),
);

// 權限等級標籤
$levelLabels = array(
    'manage' => '管理（完整操作）',
    'view'   => '檢視（唯讀）',
    'own'    => '個人（僅自己的）',
    'assist' => '協助',
);

// 取得模組的角色預設權限等級
function getRoleDefaultLevel($module, $rolePerms, $isAllPerm) {
    if ($isAllPerm) return 'manage';
    foreach (array('manage', 'view', 'own', 'assist') as $level) {
        if (in_array($module . '.' . $level, $rolePerms)) {
            return $level;
        }
    }
    return 'off';
}

// 取得模組的刪除權限狀態
function getDeleteStatus($module, $customPerms, $rolePerms, $isAllPerm) {
    $deleteKey = 'delete_' . $module;
    // 如果有自訂設定
    if (array_key_exists($deleteKey, $customPerms)) {
        return $customPerms[$deleteKey] ? true : false;
    }
    // 角色預設：檢查角色是否有 module.delete
    if ($isAllPerm) return true;
    return in_array($module . '.delete', $rolePerms);
}

// 取得模組的新增權限狀態（預設：無自訂則不允許，除非系統管理者或角色有 .create）
function getCreateStatus($module, $customPerms, $rolePerms, $isAllPerm) {
    $createKey = 'create_' . $module;
    if (array_key_exists($createKey, $customPerms)) {
        return $customPerms[$createKey] ? true : false;
    }
    if ($isAllPerm) return true;
    return in_array($module . '.create', $rolePerms);
}

// 取得模組的自訂權限等級
function getCustomLevel($module, $customPerms) {
    if (!array_key_exists($module, $customPerms)) return 'default';
    $val = $customPerms[$module];
    if ($val === false || $val === 'off') return 'off';
    if ($val === true) return 'default';
    if (is_string($val)) {
        // val 格式為 "module.level"
        $parts = explode('.', $val);
        if (count($parts) === 2) return $parts[1];
        return $val;
    }
    return 'default';
}

// 案件區域標籤
$sectionLabels = isset($appConfig['case_section_labels']) ? $appConfig['case_section_labels'] : array();

// 報表標籤
$reportLabels = isset($appConfig['report_labels']) ? $appConfig['report_labels'] : array();

// 自訂案件區域
$customSections = isset($customPerms['case_sections']) && is_array($customPerms['case_sections'])
    ? $customPerms['case_sections']
    : null;

// 自訂報表存取
$customReports = isset($customPerms['report_access']) && is_array($customPerms['report_access'])
    ? $customPerms['report_access']
    : null;
?>

<div class="d-flex justify-between align-center flex-wrap gap-1 mb-2">
    <div>
        <h2>權限設定 - <?= e($user['real_name']) ?></h2>
        <span class="badge badge-primary"><?= e(role_name($user['role'])) ?></span>
        <span class="text-muted"><?= e($user['branch_name']) ?></span>
    </div>
    <div class="d-flex gap-1">
        <a href="/staff.php?action=view&id=<?= $user['id'] ?>" class="btn btn-outline btn-sm">返回</a>
    </div>
</div>

<?php if ($isAllPerm): ?>
<div class="card">
    <div style="padding:12px;background:#fff3cd;border-radius:6px;color:#856404">
        此使用者角色為「<?= e(role_name($user['role'])) ?>」，擁有所有權限（all），無法個別調整。
    </div>
</div>
<?php else: ?>

<form method="POST" action="/staff.php?action=permissions&id=<?= $user['id'] ?>">
    <?= csrf_field() ?>

    <!-- 角色預設提示 -->
    <div class="card mb-1">
        <div class="card-header d-flex justify-between align-center">
            <span>角色預設權限參考</span>
            <button type="button" class="btn btn-outline btn-sm" onclick="resetToDefaults()">使用角色預設</button>
        </div>
        <div style="padding:8px 0;font-size:.85rem;color:var(--gray-500)">
            角色「<?= e(role_name($user['role'])) ?>」的預設權限：
            <?php
            $defaultList = array();
            foreach ($modules as $modKey => $modInfo) {
                $defLevel = getRoleDefaultLevel($modKey, $rolePerms, $isAllPerm);
                if ($defLevel !== 'off') {
                    $defaultList[] = $modInfo['label'] . '（' . (isset($levelLabels[$defLevel]) ? explode('（', $levelLabels[$defLevel])[0] : $defLevel) . '）';
                }
            }
            echo e(implode('、', $defaultList) ?: '無');
            ?>
        </div>
    </div>

    <!-- 模組權限 -->
    <div class="card mb-1">
        <div class="card-header">模組權限</div>
        <div class="perm-grid">
            <?php foreach ($modules as $modKey => $modInfo):
                $roleDefault = getRoleDefaultLevel($modKey, $rolePerms, $isAllPerm);
                $current = getCustomLevel($modKey, $customPerms);
                $defaultLabel = $roleDefault === 'off' ? '關閉' : (isset($levelLabels[$roleDefault]) ? explode('（', $levelLabels[$roleDefault])[0] : $roleDefault);
            ?>
            <div class="perm-module">
                <div class="perm-module-label">
                    <?= e($modInfo['label']) ?>
                    <span class="perm-default-hint">預設: <?= e($defaultLabel) ?></span>
                </div>
                <div class="perm-radios">
                    <label class="perm-radio">
                        <input type="radio" name="perm_<?= $modKey ?>" value="default"
                            <?= $current === 'default' ? 'checked' : '' ?>>
                        <span>預設</span>
                    </label>
                    <?php foreach ($modInfo['levels'] as $level): ?>
                    <label class="perm-radio">
                        <input type="radio" name="perm_<?= $modKey ?>" value="<?= $modKey . '.' . $level ?>"
                            <?= $current === $level ? 'checked' : '' ?>>
                        <span><?= e(isset($levelLabels[$level]) ? explode('（', $levelLabels[$level])[0] : $level) ?></span>
                    </label>
                    <?php endforeach; ?>
                    <label class="perm-radio">
                        <input type="radio" name="perm_<?= $modKey ?>" value="off"
                            <?= $current === 'off' ? 'checked' : '' ?>>
                        <span>關閉</span>
                    </label>
                    <?php if (!empty($modInfo['has_create'])): ?>
                    <label class="perm-delete-check" title="允許新增" style="background:#e8f5e9;border-color:#4caf50">
                        <input type="checkbox" name="create_<?= $modKey ?>" value="1"
                            <?= getCreateStatus($modKey, $customPerms, $rolePerms, $isAllPerm) ? 'checked' : '' ?>>
                        <span>新增</span>
                    </label>
                    <?php endif; ?>
                    <?php if (!empty($modInfo['has_delete'])): ?>
                    <label class="perm-delete-check" title="允許刪除">
                        <input type="checkbox" name="delete_<?= $modKey ?>" value="1"
                            <?= getDeleteStatus($modKey, $customPerms, $rolePerms, $isAllPerm) ? 'checked' : '' ?>>
                        <span>刪除</span>
                    </label>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- 案件可編輯區塊 -->
    <div class="card mb-1">
        <div class="card-header">案件可編輯區塊</div>
        <div class="perm-hint">
            角色預設：<?php
            $defSectionList = array();
            foreach ($roleSectionDefaults as $sk) {
                if (isset($sectionLabels[$sk])) $defSectionList[] = $sectionLabels[$sk];
            }
            echo e(implode('、', $defSectionList) ?: '無');
            ?>
        </div>
        <div class="perm-check-group">
            <label class="perm-check-item">
                <input type="checkbox" name="case_section_use_default" value="1"
                    <?= $customSections === null ? 'checked' : '' ?>
                    onchange="toggleSectionDefaults(this)">
                <span>使用角色預設</span>
            </label>
        </div>
        <div class="perm-check-group" id="caseSectionChecks" <?= $customSections === null ? 'style="opacity:.4;pointer-events:none"' : '' ?>>
            <?php foreach ($sectionLabels as $sk => $slabel):
                $checked = $customSections !== null
                    ? in_array($sk, $customSections)
                    : in_array($sk, $roleSectionDefaults);
            ?>
            <label class="perm-check-item">
                <input type="checkbox" name="case_section_<?= $sk ?>" value="1"
                    <?= $checked ? 'checked' : '' ?>>
                <span><?= e($slabel) ?></span>
            </label>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- 報表存取 -->
    <div class="card mb-1">
        <div class="card-header">報表存取</div>
        <div class="perm-hint">
            角色預設：<?php
            $defReportList = array();
            foreach ($roleReportDefaults as $rk) {
                if (isset($reportLabels[$rk])) $defReportList[] = $reportLabels[$rk];
            }
            echo e(implode('、', $defReportList) ?: '無');
            ?>
        </div>
        <div class="perm-check-group">
            <label class="perm-check-item">
                <input type="checkbox" name="report_use_default" value="1"
                    <?= $customReports === null ? 'checked' : '' ?>
                    onchange="toggleReportDefaults(this)">
                <span>使用角色預設</span>
            </label>
        </div>
        <div class="perm-check-group" id="reportChecks" <?= $customReports === null ? 'style="opacity:.4;pointer-events:none"' : '' ?>>
            <?php foreach ($reportLabels as $rk => $rlabel):
                $checked = $customReports !== null
                    ? in_array($rk, $customReports)
                    : in_array($rk, $roleReportDefaults);
            ?>
            <label class="perm-check-item">
                <input type="checkbox" name="report_access_<?= $rk ?>" value="1"
                    <?= $checked ? 'checked' : '' ?>>
                <span><?= e($rlabel) ?></span>
            </label>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- 儲存 -->
    <div class="d-flex gap-1 mt-2">
        <button type="submit" class="btn btn-primary">儲存權限設定</button>
        <a href="/staff.php?action=view&id=<?= $user['id'] ?>" class="btn btn-outline">取消</a>
    </div>
</form>

<script>
function resetToDefaults() {
    if (!confirm('確定要清除所有自訂權限，恢復為角色預設？')) return;
    // 所有模組 radio 選「預設」
    var radios = document.querySelectorAll('input[type=radio][value=default]');
    for (var i = 0; i < radios.length; i++) {
        radios[i].checked = true;
    }
    // 案件區域勾「使用角色預設」
    var sectionDefault = document.querySelector('input[name=case_section_use_default]');
    if (sectionDefault) { sectionDefault.checked = true; toggleSectionDefaults(sectionDefault); }
    // 報表勾「使用角色預設」
    var reportDefault = document.querySelector('input[name=report_use_default]');
    if (reportDefault) { reportDefault.checked = true; toggleReportDefaults(reportDefault); }
}

function toggleSectionDefaults(cb) {
    var container = document.getElementById('caseSectionChecks');
    if (cb.checked) {
        container.style.opacity = '.4';
        container.style.pointerEvents = 'none';
    } else {
        container.style.opacity = '1';
        container.style.pointerEvents = 'auto';
    }
}

function toggleReportDefaults(cb) {
    var container = document.getElementById('reportChecks');
    if (cb.checked) {
        container.style.opacity = '.4';
        container.style.pointerEvents = 'none';
    } else {
        container.style.opacity = '1';
        container.style.pointerEvents = 'auto';
    }
}
</script>

<?php endif; ?>

<style>
.perm-grid { display: flex; flex-direction: column; gap: 0; }
.perm-module { display: flex; align-items: center; gap: 12px; padding: 10px 0; border-bottom: 1px solid var(--gray-200); }
.perm-module:last-child { border-bottom: none; }
.perm-module-label { min-width: 120px; font-weight: 600; font-size: .9rem; }
.perm-default-hint { display: block; font-size: .75rem; color: var(--gray-400); font-weight: 400; }
.perm-radios { display: flex; flex-wrap: wrap; gap: 8px; }
.perm-radio { display: flex; align-items: center; gap: 4px; cursor: pointer; font-size: .85rem; padding: 4px 10px; border-radius: 4px; border: 1px solid var(--gray-200); background: var(--gray-50); transition: all .15s; }
.perm-radio:hover { border-color: var(--primary); }
.perm-radio input[type=radio] { margin: 0; }
.perm-radio input[type=radio]:checked + span { color: var(--primary); font-weight: 600; }
.perm-radio:has(input:checked) { border-color: var(--primary); background: #e8f0fe; }
.perm-check-group { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 8px; }
.perm-check-item { display: flex; align-items: center; gap: 4px; cursor: pointer; font-size: .85rem; padding: 4px 10px; border-radius: 4px; border: 1px solid var(--gray-200); background: var(--gray-50); }
.perm-check-item:hover { border-color: var(--primary); }
.perm-check-item input[type=checkbox]:checked + span { color: var(--primary); font-weight: 600; }
.perm-delete-check { display: flex; align-items: center; gap: 4px; cursor: pointer; font-size: .85rem; padding: 4px 10px; border-radius: 4px; border: 1px solid #f5c6cb; background: #fff5f5; transition: all .15s; margin-left: 4px; }
.perm-delete-check:hover { border-color: #dc3545; }
.perm-delete-check input[type=checkbox]:checked + span { color: #dc3545; font-weight: 600; }
.perm-delete-check:has(input:checked) { border-color: #dc3545; background: #fde8ea; }
.perm-hint { font-size: .8rem; color: var(--gray-400); padding: 4px 0; }
.mb-1 { margin-bottom: 12px; }

@media (max-width: 767px) {
    .perm-module { flex-direction: column; align-items: flex-start; gap: 6px; }
    .perm-module-label { min-width: auto; }
}
</style>
