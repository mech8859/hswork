<?php
$limitedEdit = isset($limitedEdit) ? $limitedEdit : false;
$emergencyContacts = array();
if ($user) {
    $emergencyContacts = $model->getEmergencyContacts($user['id']);
}
$employmentStatusOptions = array(
    'active' => '在職',
    'probation' => '試用',
    'suspended' => '留職停薪',
    'resigned' => '離職',
    'contract_expired' => '合約到期',
    'retired' => '退休',
    'deceased' => '已歿',
);
$maritalOptions = array(
    '' => '請選擇',
    'single' => '未婚',
    'married' => '已婚',
    'divorced' => '離婚',
);
$educationOptions = array(
    '' => '請選擇',
    'phd' => '博士',
    'master' => '碩士',
    'graduate' => '研究所',
    'university' => '大學',
    'tech_4yr' => '四技',
    'tech_2yr' => '二專',
    'tech_3yr' => '三專',
    'tech_5yr' => '五專',
    'senior_high' => '高中',
    'vocational' => '高職',
    'junior_high' => '國中',
    'elementary' => '國小',
);
$genderOptions = array('' => '請選擇', 'male' => '男', 'female' => '女');
$bloodOptions = array('' => '請選擇', 'A' => 'A', 'B' => 'B', 'O' => 'O', 'AB' => 'AB');
?>
<h2><?= $user ? '編輯人員 - ' . e($user['real_name']) : '新增人員' ?></h2>

<form method="POST" class="mt-2">
    <?= csrf_field() ?>

    <div class="card">
        <div class="card-header">基本資料</div>
        <div class="form-row">
            <div class="form-group">
                <label>真實姓名 *</label>
                <input type="text" name="real_name" class="form-control" value="<?= e($user['real_name'] ?? '') ?>" required <?= $limitedEdit ? 'readonly' : '' ?>>
            </div>
            <div class="form-group">
                <label>據點 *</label>
                <?php if ($limitedEdit): ?>
                <input type="text" class="form-control" value="<?= e($user['branch_name'] ?? '') ?>" readonly>
                <?php else: ?>
                <select name="branch_id" class="form-control" required>
                    <option value="">請選擇</option>
                    <?php foreach ($branches as $b): ?>
                    <option value="<?= $b['id'] ?>" <?= ($user['branch_id'] ?? '') == $b['id'] ? 'selected' : '' ?>><?= e($b['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php endif; ?>
            </div>
        </div>
        <?php if (!$limitedEdit): ?>
        <div class="form-row">
            <div class="form-group">
                <label>帳號 *</label>
                <?php if ($user): ?>
                    <?php if (Auth::user()['role'] === 'boss'): ?>
                        <input type="text" name="username" class="form-control" value="<?= e($user['username']) ?>" required>
                    <?php else: ?>
                        <input type="text" class="form-control" value="<?= e($user['username']) ?>" disabled>
                    <?php endif; ?>
                <?php else: ?>
                    <input type="text" name="username" class="form-control" value="<?= e($_POST['username'] ?? '') ?>" required>
                <?php endif; ?>
            </div>
            <div class="form-group">
                <label><?= $user ? '新密碼 (留空不修改)' : '密碼 *' ?></label>
                <div style="position:relative">
                    <input type="password" name="password" id="pwField" class="form-control" style="padding-right:40px" <?= $user ? '' : 'required' ?>>
                    <span onclick="togglePw()" id="pwEye" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);cursor:pointer;font-size:1.1rem;color:var(--gray-500);user-select:none">&#128065;</span>
                </div>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>角色 *</label>
                <select name="role" class="form-control" required>
                    <?php
                    $dynamicRoles = get_dynamic_roles();
                    foreach ($dynamicRoles as $v => $l):
                    ?>
                    <option value="<?= $v ?>" <?= ($user['role'] ?? '') === $v ? 'selected' : '' ?>><?= e($l) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>職稱</label>
                <input type="text" name="job_title" class="form-control" value="<?= e($user['job_title'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>電話</label>
                <input type="text" name="phone" class="form-control" value="<?= e($user['phone'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" class="form-control" value="<?= e($user['email'] ?? '') ?>">
            </div>
        </div>
        <div class="checkbox-row mt-1">
            <label class="checkbox-label">
                <input type="hidden" name="is_engineer" value="0">
                <input type="checkbox" name="is_engineer" value="1" <?= !empty($user['is_engineer']) ? 'checked' : '' ?>>
                <span>工程師 (可排工)</span>
            </label>
            <label class="checkbox-label">
                <input type="hidden" name="is_sales" value="0">
                <input type="checkbox" name="is_sales" value="1" <?= !empty($user['is_sales']) ? 'checked' : '' ?>>
                <span>業務 (可承接業務)</span>
            </label>
            <label class="checkbox-label">
                <input type="hidden" name="is_mobile" value="0">
                <input type="checkbox" name="is_mobile" value="1" <?= ($user['is_mobile'] ?? 1) ? 'checked' : '' ?>>
                <span>手機介面</span>
            </label>
        </div>

        <!-- 可查看分公司 -->
        <div class="form-group mt-1">
            <label>可查看分公司</label>
            <div class="checkbox-row">
                <label class="checkbox-label">
                    <input type="checkbox" id="viewAllBranches" onchange="toggleAllBranches(this)" <?= !empty($user['can_view_all_branches']) ? 'checked' : '' ?>>
                    <span style="font-weight:600">全部分公司</span>
                </label>
                <input type="hidden" name="can_view_all_branches" value="0" id="viewAllHidden">
            </div>
            <div class="checkbox-row mt-1" id="branchCheckboxes" style="<?= !empty($user['can_view_all_branches']) ? 'opacity:.4;pointer-events:none' : '' ?>">
                <?php
                $viewableBranches = array();
                if (!empty($user['viewable_branches'])) {
                    $viewableBranches = is_array($user['viewable_branches']) ? $user['viewable_branches'] : json_decode($user['viewable_branches'], true);
                    if (!is_array($viewableBranches)) $viewableBranches = array();
                }
                foreach ($branches as $b):
                    $isOwn = ($user['branch_id'] ?? 0) == $b['id'];
                ?>
                <label class="checkbox-label">
                    <input type="checkbox" name="viewable_branch_ids[]" value="<?= $b['id'] ?>"
                           <?= $isOwn ? 'checked disabled' : '' ?>
                           <?= in_array($b['id'], $viewableBranches) ? 'checked' : '' ?>>
                    <span><?= e($b['name']) ?><?= $isOwn ? ' (所屬)' : '' ?></span>
                </label>
                <?php if ($isOwn): ?>
                <input type="hidden" name="viewable_branch_ids[]" value="<?= $b['id'] ?>">
                <?php endif; ?>
                <?php endforeach; ?>
            </div>
            <small class="text-muted">所屬分公司預設可查看，勾選其他分公司開放查看權限</small>
        </div>
        <?php endif; ?>
    </div>

    <?php if (!$limitedEdit): ?>
    <!-- 人事基本資料 -->
    <div class="card">
        <div class="card-header">人事資料</div>
        <div class="form-row">
            <div class="form-group">
                <label>員工編號</label>
                <input type="text" name="employee_id" class="form-control" value="<?= e($user['employee_id'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>部門</label>
                <select name="department" class="form-control">
                    <option value="">請選擇</option>
                    <?php
                    require_once __DIR__ . '/../../modules/settings/DropdownModel.php';
                    $deptModel = new DropdownModel();
                    $deptOptions = $deptModel->getOptions('department');
                    foreach ($deptOptions as $dept):
                    ?>
                    <option value="<?= e($dept['label']) ?>" <?= ($user['department'] ?? '') === $dept['label'] ? 'selected' : '' ?>><?= e($dept['label']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>身分證字號</label>
                <input type="text" name="id_number" class="form-control" value="<?= e($user['id_number'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>出生日期</label>
                <input type="date" name="birth_date" id="birth_date" class="form-control" value="<?= e($user['birth_date'] ?? '') ?>" onchange="calcAgeSeniority()">
            </div>
            <div class="form-group">
                <label>性別</label>
                <select name="gender" class="form-control">
                    <?php foreach ($genderOptions as $v => $l): ?>
                    <option value="<?= $v ?>" <?= ($user['gender'] ?? '') === $v ? 'selected' : '' ?>><?= $l ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>婚姻狀態</label>
                <select name="marital_status" class="form-control">
                    <?php foreach ($maritalOptions as $v => $l): ?>
                    <option value="<?= $v ?>" <?= ($user['marital_status'] ?? '') === $v ? 'selected' : '' ?>><?= $l ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>血型</label>
                <select name="blood_type" class="form-control">
                    <?php foreach ($bloodOptions as $v => $l): ?>
                    <option value="<?= $v ?>" <?= ($user['blood_type'] ?? '') === $v ? 'selected' : '' ?>><?= $l ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>最高教育程度</label>
                <select name="education_level" class="form-control">
                    <?php foreach ($educationOptions as $v => $l): ?>
                    <option value="<?= $v ?>" <?= ($user['education_level'] ?? '') === $v ? 'selected' : '' ?>><?= $l ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>在職狀態</label>
                <select name="employment_status" class="form-control">
                    <?php foreach ($employmentStatusOptions as $v => $l): ?>
                    <option value="<?= $v ?>" <?= ($user['employment_status'] ?? 'active') === $v ? 'selected' : '' ?>><?= $l ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>到職日期</label>
                <input type="date" name="hire_date" id="hire_date" class="form-control" value="<?= e($user['hire_date'] ?? '') ?>" onchange="calcAgeSeniority()">
            </div>
            <div class="form-group">
                <label>離職日期</label>
                <input type="date" name="resignation_date" class="form-control" value="<?= e($user['resignation_date'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>享有特休 (天)</label>
                <input type="number" name="annual_leave_days" class="form-control" value="<?= (int)($user['annual_leave_days'] ?? 0) ?>" min="0">
            </div>
        </div>
        <?php
        $staffAge = '';
        $staffSeniority = '';
        if (!empty($user['birth_date'])) {
            $bd = new DateTime($user['birth_date']);
            $now = new DateTime();
            $staffAge = $bd->diff($now)->y . ' 歲';
        }
        if (!empty($user['hire_date'])) {
            $hd = new DateTime($user['hire_date']);
            $now = new DateTime();
            $diff = $hd->diff($now);
            $staffSeniority = $diff->y > 0 ? $diff->y . ' 年 ' . $diff->m . ' 個月' : $diff->m . ' 個月';
        }
        ?>
        <div class="form-row">
            <div class="form-group">
                <label>年齡</label>
                <input type="text" class="form-control" id="calc_age" value="<?= e($staffAge) ?>" readonly style="background:#f0f7ff;color:var(--primary);font-weight:600">
            </div>
            <div class="form-group">
                <label>年資</label>
                <input type="text" class="form-control" id="calc_seniority" value="<?= e($staffSeniority) ?>" readonly style="background:#f0f7ff;color:var(--primary);font-weight:600">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group" style="flex:1">
                <label>通訊地址</label>
                <input type="text" name="address" class="form-control" value="<?= e($user['address'] ?? '') ?>">
            </div>
            <div class="form-group" style="flex:1">
                <label>戶籍地址 <label style="font-weight:normal;font-size:.8rem"><input type="checkbox" id="sameAddress" onchange="if(this.checked){document.querySelector('[name=registered_address]').value=document.querySelector('[name=address]').value}"> 同通訊地址</label></label>
                <input type="text" name="registered_address" class="form-control" value="<?= e($user['registered_address'] ?? '') ?>">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>銀行名稱</label>
                <input type="text" name="bank_name" class="form-control" value="<?= e($user['bank_name'] ?? '') ?>" placeholder="如：中國信託(822)">
            </div>
            <div class="form-group">
                <label>銀行帳號</label>
                <input type="text" name="bank_account" class="form-control" value="<?= e($user['bank_account'] ?? '') ?>">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>勞保投保公司</label>
                <input type="text" name="labor_insurance_company" class="form-control" value="<?= e($user['labor_insurance_company'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>勞保投保日期</label>
                <input type="date" name="labor_insurance_date" class="form-control" value="<?= e($user['labor_insurance_date'] ?? '') ?>">
            </div>
            <div class="form-group" style="flex:2">
                <label>眷屬加保</label>
                <input type="text" name="dependent_insurance" class="form-control" value="<?= e($user['dependent_insurance'] ?? '') ?>" placeholder="如：配偶王OO、子女王OO">
            </div>
        </div>
    </div>

    <!-- 緊急聯絡人 -->
    <div class="card">
        <div class="card-header d-flex" style="justify-content:space-between;align-items:center">
            <span>緊急聯絡人</span>
            <?php if ($user): ?>
            <button type="button" class="btn btn-sm btn-primary" onclick="addEmergencyRow()">+ 新增</button>
            <?php endif; ?>
        </div>
        <?php if ($user): ?>
        <table class="table" id="emergencyTable">
            <thead>
                <tr>
                    <th>姓名</th>
                    <th>關係</th>
                    <th>住家電話</th>
                    <th>公司電話</th>
                    <th>手機</th>
                    <th style="width:80px">操作</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($emergencyContacts)): ?>
                <tr id="emptyRow"><td colspan="6" class="text-center text-muted">尚無緊急聯絡人</td></tr>
                <?php endif; ?>
                <?php foreach ($emergencyContacts as $ec): ?>
                <tr id="ecRow_<?= $ec['id'] ?>">
                    <td><input type="text" name="ec[<?= $ec['id'] ?>][contact_name]" class="form-control form-control-sm" value="<?= e($ec['contact_name']) ?>"></td>
                    <td><input type="text" name="ec[<?= $ec['id'] ?>][relationship]" class="form-control form-control-sm" value="<?= e($ec['relationship'] ?? '') ?>"></td>
                    <td><input type="text" name="ec[<?= $ec['id'] ?>][home_phone]" class="form-control form-control-sm" value="<?= e($ec['home_phone'] ?? '') ?>"></td>
                    <td><input type="text" name="ec[<?= $ec['id'] ?>][work_phone]" class="form-control form-control-sm" value="<?= e($ec['work_phone'] ?? '') ?>"></td>
                    <td><input type="text" name="ec[<?= $ec['id'] ?>][mobile]" class="form-control form-control-sm" value="<?= e($ec['mobile'] ?? '') ?>"></td>
                    <td><button type="button" class="btn btn-sm btn-danger" onclick="removeEmergency(<?= $ec['id'] ?>, this)">刪除</button></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <p class="text-muted p-1">請先建立人員後再新增緊急聯絡人</p>
        <?php endif; ?>
    </div>

    <!-- 工程師設定 -->
    <div class="card" id="engineerSettingsCard" style="<?= empty($user['is_engineer']) ? 'display:none' : '' ?>">
        <div class="card-header">工程師設定</div>
        <div class="form-row">
            <div class="form-group">
                <label>工程師等級</label>
                <select name="engineer_level" class="form-control">
                    <option value="">請選擇</option>
                    <?php foreach (array('leader' => '組長', 'senior' => '資深', 'regular' => '一般', 'probation' => '試用') as $v => $l): ?>
                    <option value="<?= $v ?>" <?= ($user['engineer_level'] ?? '') === $v ? 'selected' : '' ?>><?= $l ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="align-self:flex-end">
                <label class="checkbox-label" style="margin-bottom:8px">
                    <input type="hidden" name="can_lead" value="0">
                    <input type="checkbox" name="can_lead" value="1" <?= !empty($user['can_lead']) ? 'checked' : '' ?>>
                    <span>可帶隊（可任主工程師）</span>
                </label>
            </div>
            <div class="form-group" style="align-self:flex-end">
                <label class="checkbox-label" style="margin-bottom:8px">
                    <input type="hidden" name="repair_priority" value="0">
                    <input type="checkbox" name="repair_priority" value="1" <?= !empty($user['repair_priority']) ? 'checked' : '' ?>>
                    <span>查修優先人員</span>
                </label>
            </div>
        </div>
        <div class="form-row" style="margin-top:8px;padding-top:8px;border-top:1px solid var(--gray-100)">
            <div class="form-group">
                <label>師傅（師徒制）</label>
                <select name="mentor_id" class="form-control">
                    <option value="">無</option>
                    <?php if (isset($mentorCandidates)): foreach ($mentorCandidates as $mc): ?>
                    <option value="<?= $mc['id'] ?>" <?= ($user['mentor_id'] ?? '') == $mc['id'] ? 'selected' : '' ?>><?= e($mc['real_name']) ?><?= $mc['engineer_level'] ? ' (' . array('leader'=>'組長','senior'=>'資深','regular'=>'一般')[$mc['engineer_level']] . ')' : '' ?></option>
                    <?php endforeach; endif; ?>
                </select>
            </div>
            <div class="form-group">
                <label>師徒開始日期</label>
                <input type="date" name="mentor_start_date" class="form-control" value="<?= e($user['mentor_start_date'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>師徒已持續</label>
                <?php
                $mentorDuration = '-';
                if (!empty($user['mentor_start_date'])) {
                    $msDays = (int)((time() - strtotime($user['mentor_start_date'])) / 86400);
                    $msMonths = (int)($msDays / 30);
                    $msRemainDays = $msDays % 30;
                    $mentorDuration = $msMonths . ' 個月 ' . $msRemainDays . ' 天';
                    if ($msDays >= 90) {
                        $mentorDuration .= ' ⚠ 已超過3個月';
                    }
                }
                ?>
                <input type="text" class="form-control" value="<?= e($mentorDuration) ?>" readonly style="background:#f0f7ff;font-weight:600;color:<?= (!empty($user['mentor_start_date']) && $msDays >= 90) ? 'var(--danger)' : 'var(--primary)' ?>">
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">施工配合度</div>
        <div class="form-row">
            <div class="form-group">
                <label>假日施工配合度</label>
                <select name="holiday_availability" class="form-control">
                    <option value="high" <?= ($user['holiday_availability'] ?? 'medium') === 'high' ? 'selected' : '' ?>>高</option>
                    <option value="medium" <?= ($user['holiday_availability'] ?? 'medium') === 'medium' ? 'selected' : '' ?>>中</option>
                    <option value="low" <?= ($user['holiday_availability'] ?? 'medium') === 'low' ? 'selected' : '' ?>>低</option>
                </select>
            </div>
            <div class="form-group">
                <label>夜間施工配合度</label>
                <select name="night_availability" class="form-control">
                    <option value="high" <?= ($user['night_availability'] ?? 'medium') === 'high' ? 'selected' : '' ?>>高</option>
                    <option value="medium" <?= ($user['night_availability'] ?? 'medium') === 'medium' ? 'selected' : '' ?>>中</option>
                    <option value="low" <?= ($user['night_availability'] ?? 'medium') === 'low' ? 'selected' : '' ?>>低</option>
                </select>
            </div>
        </div>
        <div class="form-group">
            <label>注意事項</label>
            <textarea name="caution_notes" class="form-control" rows="2" placeholder="如：懼高症、色弱、不可夜間施工等"><?= e($user['caution_notes'] ?? '') ?></textarea>
        </div>
    </div>

    <?php endif; // !$limitedEdit ?>

    <div class="d-flex gap-1 mt-2">
        <?php if ($limitedEdit): ?>
        <a href="/staff.php?action=view&id=<?= $user['id'] ?>" class="btn btn-outline">返回</a>
        <?php else: ?>
        <button type="submit" class="btn btn-primary"><?= $user ? '儲存變更' : '建立人員' ?></button>
        <a href="/staff.php" class="btn btn-outline">取消</a>
        <?php endif; ?>
    </div>
</form>

<style>
.form-row { display: flex; flex-wrap: wrap; gap: 12px; }
.form-row .form-group { flex: 1; min-width: 150px; }
.checkbox-row { display: flex; flex-wrap: wrap; gap: 16px; }
.checkbox-label { display: flex; align-items: center; gap: 6px; cursor: pointer; }
.checkbox-label input[type="checkbox"] { width: 18px; height: 18px; }
.form-control-sm { padding: 4px 8px; font-size: .9rem; }
#emergencyTable th { font-size: .85rem; padding: 6px 8px; }
#emergencyTable td { padding: 4px 6px; }
</style>

<script>
function calcAgeSeniority() {
    var bd = document.getElementById('birth_date').value;
    var hd = document.getElementById('hire_date').value;
    var now = new Date();
    var ageEl = document.getElementById('calc_age');
    var senEl = document.getElementById('calc_seniority');
    if (bd) {
        var b = new Date(bd);
        var age = now.getFullYear() - b.getFullYear();
        var m = now.getMonth() - b.getMonth();
        if (m < 0 || (m === 0 && now.getDate() < b.getDate())) age--;
        ageEl.value = age + ' 歲';
    } else { ageEl.value = ''; }
    if (hd) {
        var h = new Date(hd);
        var totalMonths = (now.getFullYear() - h.getFullYear()) * 12 + (now.getMonth() - h.getMonth());
        if (now.getDate() < h.getDate()) totalMonths--;
        var yrs = Math.floor(totalMonths / 12);
        var mos = totalMonths % 12;
        senEl.value = yrs > 0 ? yrs + ' 年 ' + mos + ' 個月' : mos + ' 個月';
    } else { senEl.value = ''; }
}

function togglePw() {
    var f = document.getElementById('pwField');
    var e = document.getElementById('pwEye');
    if (f.type === 'password') { f.type = 'text'; e.style.opacity = '1'; }
    else { f.type = 'password'; e.style.opacity = '0.5'; }
}

function toggleAllBranches(cb) {
    var container = document.getElementById('branchCheckboxes');
    var hidden = document.getElementById('viewAllHidden');
    if (cb.checked) {
        container.style.opacity = '.4';
        container.style.pointerEvents = 'none';
        hidden.value = '1';
    } else {
        container.style.opacity = '1';
        container.style.pointerEvents = 'auto';
        hidden.value = '0';
    }
}

<?php if ($user): ?>
var newEcIdx = 0;
function addEmergencyRow() {
    var emptyRow = document.getElementById('emptyRow');
    if (emptyRow) emptyRow.remove();
    newEcIdx++;
    var tbody = document.querySelector('#emergencyTable tbody');
    var tr = document.createElement('tr');
    tr.id = 'ecNewRow_' + newEcIdx;
    tr.innerHTML = '<td><input type="text" name="ec_new[' + newEcIdx + '][contact_name]" class="form-control form-control-sm" placeholder="姓名" required></td>'
        + '<td><input type="text" name="ec_new[' + newEcIdx + '][relationship]" class="form-control form-control-sm" placeholder="關係"></td>'
        + '<td><input type="text" name="ec_new[' + newEcIdx + '][home_phone]" class="form-control form-control-sm" placeholder="住家電話"></td>'
        + '<td><input type="text" name="ec_new[' + newEcIdx + '][work_phone]" class="form-control form-control-sm" placeholder="公司電話"></td>'
        + '<td><input type="text" name="ec_new[' + newEcIdx + '][mobile]" class="form-control form-control-sm" placeholder="手機"></td>'
        + '<td><button type="button" class="btn btn-sm btn-outline" onclick="this.closest(\'tr\').remove()">取消</button></td>';
    tbody.appendChild(tr);
}

function removeEmergency(ecId, btn) {
    if (!confirm('確定刪除此聯絡人？')) return;
    var csrf = document.querySelector('input[name="csrf_token"]').value;
    fetch('/staff.php?action=remove_emergency_contact&ec_id=' + ecId + '&user_id=<?= $user['id'] ?>&csrf_token=' + csrf)
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (d.success) {
                btn.closest('tr').remove();
            } else {
                alert(d.message || '刪除失敗');
            }
        });
}
<?php endif; ?>
// 切換工程師設定卡片顯示
var engCb = document.querySelector('input[name="is_engineer"][type="checkbox"]');
if (engCb) {
    engCb.addEventListener('change', function() {
        var card = document.getElementById('engineerSettingsCard');
        if (card) card.style.display = this.checked ? '' : 'none';
    });
}
</script>
