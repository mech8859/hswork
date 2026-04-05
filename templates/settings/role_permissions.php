<?php
/**
 * 角色預設權限設定頁面
 * 讓 boss 可以設定每個角色的預設模組權限、案件區域、報表存取
 */

// 解析現有預設
$currentPerms = array();
if (!empty($role['default_permissions'])) {
    $decoded = json_decode($role['default_permissions'], true);
    if (is_array($decoded)) $currentPerms = $decoded;
}
$currentSections = array();
if (!empty($role['default_case_sections'])) {
    $decoded = json_decode($role['default_case_sections'], true);
    if (is_array($decoded)) $currentSections = $decoded;
}
$currentReports = array();
if (!empty($role['default_reports'])) {
    $decoded = json_decode($role['default_reports'], true);
    if (is_array($decoded)) $currentReports = $decoded;
}

$isAllPerm = !empty($currentPerms['_all']);

// 模組定義
$modules = array(
    'cases'                => array('label' => '案件管理',   'levels' => array('manage', 'view', 'own', 'assist'), 'has_delete' => true),
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
    'quotations'           => array('label' => '報價管理',   'levels' => array('manage', 'view', 'own'), 'has_delete' => true),
    'customers'            => array('label' => '客戶管理',   'levels' => array('manage', 'view', 'own'), 'has_delete' => true),
    'business_calendar'    => array('label' => '業務行事曆', 'levels' => array('manage', 'view'), 'has_delete' => false),
    'business_tracking'    => array('label' => '業務追蹤',   'levels' => array('manage', 'view', 'own'), 'has_delete' => false),
    'engineering_tracking' => array('label' => '工程追蹤',   'levels' => array('manage', 'view', 'own'), 'has_delete' => false),
    'finance'              => array('label' => '財務會計',   'levels' => array('manage', 'view'), 'has_delete' => true),
    'procurement'          => array('label' => '採購',       'levels' => array('manage', 'view'), 'has_delete' => false),
    'inventory'            => array('label' => '庫存',       'levels' => array('manage', 'view'), 'has_delete' => true),
    'accounting'           => array('label' => '會計管理',   'levels' => array('manage', 'view'), 'has_delete' => false),
    'approvals'            => array('label' => '簽核管理',   'levels' => array('manage', 'view'), 'has_delete' => false),
    'settings'             => array('label' => '系統設定',   'levels' => array('manage'), 'has_delete' => false),
    'system'               => array('label' => '系統維護',   'levels' => array('manage'), 'has_delete' => false),
);

$levelLabels = array(
    'manage' => '管理',
    'view'   => '檢視',
    'own'    => '個人',
    'assist' => '協助',
);

// 取得模組目前的預設等級
function getRolePermLevel($module, $currentPerms, $isAllPerm) {
    if ($isAllPerm) return 'manage';
    if (isset($currentPerms[$module])) {
        $val = $currentPerms[$module];
        $parts = explode('.', $val);
        return count($parts) === 2 ? $parts[1] : $val;
    }
    return 'off';
}

function getRoleDeleteDefault($module, $currentPerms, $isAllPerm) {
    if ($isAllPerm) return true;
    return !empty($currentPerms['delete_' . $module]);
}

$sectionLabels = isset($appConfig['case_section_labels']) ? $appConfig['case_section_labels'] : array();
$reportLabels = isset($appConfig['report_labels']) ? $appConfig['report_labels'] : array();
?>

<!-- 主分頁 -->
<div class="filter-pills mb-1">
    <div class="pill-group">
        <a href="/dropdown_options.php?tab=dropdown" class="pill">表單選項設定</a>
        <a href="/dropdown_options.php?tab=roles" class="pill">人員角色</a>
        <a href="/dropdown_options.php?tab=numbering" class="pill">自動編號設定</a>
        <a href="/dropdown_options.php?tab=quotation" class="pill">報價單設定</a>
    </div>
</div>

<div class="d-flex justify-between align-center flex-wrap gap-1 mb-2">
    <div>
        <h2>角色預設權限 - <?= e($role['role_label']) ?></h2>
        <code><?= e($role['role_key']) ?></code>
    </div>
    <?= back_button('/dropdown_options.php') ?>
</div>

<?php if ($role['role_key'] === 'boss'): ?>
<div class="card">
    <div style="padding:12px;background:#fff3cd;border-radius:6px;color:#856404">
        系統管理者（boss）擁有所有權限，無需設定預設。
    </div>
</div>
<?php else: ?>

<form method="POST" action="/dropdown_options.php?action=save_role_permissions&role_id=<?= $role['id'] ?>">
    <?= csrf_field() ?>

    <!-- 全部權限開關 -->
    <div class="card mb-1">
        <div class="card-header">權限模式</div>
        <div style="padding:12px">
            <label class="perm-check-item" style="display:inline-flex">
                <input type="checkbox" name="all_permissions" value="1" <?= $isAllPerm ? 'checked' : '' ?> onchange="toggleAllPerms(this)">
                <span>擁有所有權限（all）</span>
            </label>
            <div style="font-size:.8rem;color:var(--gray-500);margin-top:4px">勾選後此角色將擁有完整操作權限，下方模組設定會被忽略。</div>
        </div>
    </div>

    <!-- 模組權限 -->
    <div class="card mb-1" id="modulePermsCard">
        <div class="card-header d-flex justify-between align-center">
            <span>模組權限</span>
            <div class="d-flex gap-1">
                <button type="button" class="btn btn-outline btn-sm" onclick="setAllModules('manage')">全部管理</button>
                <button type="button" class="btn btn-outline btn-sm" onclick="setAllModules('view')">全部檢視</button>
                <button type="button" class="btn btn-outline btn-sm" onclick="setAllModules('off')">全部關閉</button>
            </div>
        </div>
        <div class="perm-grid">
            <?php foreach ($modules as $modKey => $modInfo):
                $current = getRolePermLevel($modKey, $currentPerms, $isAllPerm);
                $deleteChecked = getRoleDeleteDefault($modKey, $currentPerms, $isAllPerm);
            ?>
            <div class="perm-module">
                <div class="perm-module-label"><?= e($modInfo['label']) ?></div>
                <div class="perm-radios">
                    <?php foreach ($modInfo['levels'] as $level): ?>
                    <label class="perm-radio">
                        <input type="radio" name="perm_<?= $modKey ?>" value="<?= $modKey . '.' . $level ?>"
                            <?= $current === $level ? 'checked' : '' ?>>
                        <span><?= e($levelLabels[$level]) ?></span>
                    </label>
                    <?php endforeach; ?>
                    <label class="perm-radio">
                        <input type="radio" name="perm_<?= $modKey ?>" value="off"
                            <?= $current === 'off' ? 'checked' : '' ?>>
                        <span>關閉</span>
                    </label>
                    <?php if (!empty($modInfo['has_delete'])): ?>
                    <label class="perm-delete-check" title="允許刪除">
                        <input type="checkbox" name="delete_<?= $modKey ?>" value="1"
                            <?= $deleteChecked ? 'checked' : '' ?>>
                        <span>刪除</span>
                    </label>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- 案件可編輯區塊 -->
    <div class="card mb-1" id="caseSectionsCard">
        <div class="card-header">案件可編輯區塊</div>
        <div class="perm-check-group" style="padding:12px">
            <?php foreach ($sectionLabels as $sk => $slabel): ?>
            <label class="perm-check-item">
                <input type="checkbox" name="section_<?= $sk ?>" value="1"
                    <?= in_array($sk, $currentSections) ? 'checked' : '' ?>>
                <span><?= e($slabel) ?></span>
            </label>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- 報表存取 -->
    <div class="card mb-1" id="reportsCard">
        <div class="card-header">報表存取</div>
        <div class="perm-check-group" style="padding:12px">
            <?php foreach ($reportLabels as $rk => $rlabel): ?>
            <label class="perm-check-item">
                <input type="checkbox" name="report_<?= $rk ?>" value="1"
                    <?= in_array($rk, $currentReports) ? 'checked' : '' ?>>
                <span><?= e($rlabel) ?></span>
            </label>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="d-flex gap-1 mt-2">
        <button type="submit" class="btn btn-primary">儲存預設權限</button>
        <a href="/dropdown_options.php?tab=roles" class="btn btn-outline">取消</a>
    </div>
</form>

<script>
function toggleAllPerms(cb) {
    var cards = ['modulePermsCard', 'caseSectionsCard', 'reportsCard'];
    for (var i = 0; i < cards.length; i++) {
        var el = document.getElementById(cards[i]);
        if (el) {
            el.style.opacity = cb.checked ? '.4' : '1';
            el.style.pointerEvents = cb.checked ? 'none' : 'auto';
        }
    }
}
function setAllModules(level) {
    var radios = document.querySelectorAll('.perm-radios input[type=radio]');
    for (var i = 0; i < radios.length; i++) {
        if (level === 'off' && radios[i].value === 'off') {
            radios[i].checked = true;
        } else if (level !== 'off') {
            var parts = radios[i].value.split('.');
            if (parts.length === 2 && parts[1] === level) {
                radios[i].checked = true;
            }
        }
    }
    // 如果是關閉，也取消刪除
    if (level === 'off') {
        var deletes = document.querySelectorAll('.perm-delete-check input');
        for (var j = 0; j < deletes.length; j++) deletes[j].checked = false;
    }
}
// 初始化
(function() {
    var cb = document.querySelector('input[name=all_permissions]');
    if (cb && cb.checked) toggleAllPerms(cb);
})();
</script>
<?php endif; ?>

<style>
.filter-pills { display: flex; flex-direction: column; gap: 8px; }
.pill-group { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
.pill { display: inline-block; padding: 6px 16px; border-radius: 16px; font-size: .85rem; background: var(--gray-100); color: var(--gray-700); text-decoration: none; transition: all .15s; }
.pill:hover { background: var(--gray-200); }
.pill-active { background: var(--primary); color: #fff; }
.perm-grid { display: flex; flex-direction: column; gap: 0; }
.perm-module { display: flex; align-items: center; gap: 12px; padding: 10px 12px; border-bottom: 1px solid var(--gray-200); }
.perm-module:last-child { border-bottom: none; }
.perm-module-label { min-width: 120px; font-weight: 600; font-size: .9rem; }
.perm-radios { display: flex; flex-wrap: wrap; gap: 8px; }
.perm-radio { display: flex; align-items: center; gap: 4px; cursor: pointer; font-size: .85rem; padding: 4px 10px; border-radius: 4px; border: 1px solid var(--gray-200); background: var(--gray-50); transition: all .15s; }
.perm-radio:hover { border-color: var(--primary); }
.perm-radio input[type=radio] { margin: 0; }
.perm-radio input[type=radio]:checked + span { color: var(--primary); font-weight: 600; }
.perm-radio:has(input:checked) { border-color: var(--primary); background: #e8f0fe; }
.perm-check-group { display: flex; flex-wrap: wrap; gap: 8px; }
.perm-check-item { display: flex; align-items: center; gap: 4px; cursor: pointer; font-size: .85rem; padding: 4px 10px; border-radius: 4px; border: 1px solid var(--gray-200); background: var(--gray-50); }
.perm-check-item:hover { border-color: var(--primary); }
.perm-check-item input[type=checkbox]:checked + span { color: var(--primary); font-weight: 600; }
.perm-delete-check { display: flex; align-items: center; gap: 4px; cursor: pointer; font-size: .85rem; padding: 4px 10px; border-radius: 4px; border: 1px solid #f5c6cb; background: #fff5f5; transition: all .15s; margin-left: 4px; }
.perm-delete-check:hover { border-color: #dc3545; }
.perm-delete-check input[type=checkbox]:checked + span { color: #dc3545; font-weight: 600; }
.perm-delete-check:has(input:checked) { border-color: #dc3545; background: #fde8ea; }
.mb-1 { margin-bottom: 12px; }
code { background: var(--gray-100); padding: 2px 6px; border-radius: 3px; font-size: .85rem; }
@media (max-width: 767px) {
    .perm-module { flex-direction: column; align-items: flex-start; gap: 6px; }
    .perm-module-label { min-width: auto; }
}
</style>
