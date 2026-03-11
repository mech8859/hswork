<h2><?= $case ? '編輯案件 - ' . e($case['case_number']) : '新增案件' ?></h2>

<form method="POST" class="mt-2">
    <?= csrf_field() ?>

    <!-- 基本資料 -->
    <div class="card">
        <div class="card-header">基本資料</div>
        <div class="form-row">
            <div class="form-group">
                <label>據點 *</label>
                <select name="branch_id" class="form-control" required>
                    <option value="">請選擇</option>
                    <?php foreach ($branches as $b): ?>
                    <option value="<?= $b['id'] ?>" <?= ($case['branch_id'] ?? '') == $b['id'] ? 'selected' : '' ?>><?= e($b['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>案件名稱 *</label>
                <input type="text" name="title" class="form-control" value="<?= e($case['title'] ?? '') ?>" required>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>案件類型</label>
                <select name="case_type" class="form-control">
                    <?php foreach (['new_install'=>'新裝','maintenance'=>'保養','repair'=>'維修','inspection'=>'勘查','other'=>'其他'] as $v => $l): ?>
                    <option value="<?= $v ?>" <?= ($case['case_type'] ?? '') === $v ? 'selected' : '' ?>><?= $l ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>狀態</label>
                <select name="status" class="form-control">
                    <?php foreach (['pending'=>'待處理','ready'=>'可排工','scheduled'=>'已排工','in_progress'=>'施工中','completed'=>'已完工','cancelled'=>'已取消'] as $v => $l): ?>
                    <option value="<?= $v ?>" <?= ($case['status'] ?? 'pending') === $v ? 'selected' : '' ?>><?= $l ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>難易度</label>
                <select name="difficulty" class="form-control">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                    <option value="<?= $i ?>" <?= ($case['difficulty'] ?? 3) == $i ? 'selected' : '' ?>><?= $i ?> 星</option>
                    <?php endfor; ?>
                </select>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>預估工時 (小時)</label>
                <input type="number" name="estimated_hours" class="form-control" step="0.5" min="0" value="<?= e($case['estimated_hours'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>預估施工次數</label>
                <input type="number" name="total_visits" class="form-control" min="1" value="<?= e($case['total_visits'] ?? 1) ?>">
            </div>
            <div class="form-group">
                <label>最多施工人數</label>
                <input type="number" name="max_engineers" class="form-control" min="1" max="10" value="<?= e($case['max_engineers'] ?? 4) ?>">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>施工地址</label>
                <input type="text" name="address" class="form-control" value="<?= e($case['address'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>業務負責人</label>
                <select name="sales_id" class="form-control">
                    <option value="">請選擇</option>
                    <?php foreach ($salesUsers as $u): ?>
                    <option value="<?= $u['id'] ?>" <?= ($case['sales_id'] ?? '') == $u['id'] ? 'selected' : '' ?>><?= e($u['real_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="form-group">
            <label>案件說明</label>
            <textarea name="description" class="form-control" rows="3"><?= e($case['description'] ?? '') ?></textarea>
        </div>
        <div class="form-group">
            <label>Ragic ID</label>
            <input type="text" name="ragic_id" class="form-control" value="<?= e($case['ragic_id'] ?? '') ?>" placeholder="選填，用於同步">
        </div>
    </div>

    <!-- 排工條件驗證 -->
    <div class="card">
        <div class="card-header">排工條件驗證</div>
        <p class="text-muted mb-1" style="font-size:.85rem">缺少的項目將在案件列表中顯示警示</p>
        <?php $rd = $case['readiness'] ?? []; ?>
        <div class="checkbox-row">
            <label class="checkbox-label">
                <input type="hidden" name="has_quotation" value="0">
                <input type="checkbox" name="has_quotation" value="1" <?= !empty($rd['has_quotation']) ? 'checked' : '' ?>>
                <span>已有報價單</span>
            </label>
            <label class="checkbox-label">
                <input type="hidden" name="has_site_photos" value="0">
                <input type="checkbox" name="has_site_photos" value="1" <?= !empty($rd['has_site_photos']) ? 'checked' : '' ?>>
                <span>已有現場照片</span>
            </label>
            <label class="checkbox-label">
                <input type="hidden" name="has_amount_confirmed" value="0">
                <input type="checkbox" name="has_amount_confirmed" value="1" <?= !empty($rd['has_amount_confirmed']) ? 'checked' : '' ?>>
                <span>金額已確認</span>
            </label>
            <label class="checkbox-label">
                <input type="hidden" name="has_site_info" value="0">
                <input type="checkbox" name="has_site_info" value="1" <?= !empty($rd['has_site_info']) ? 'checked' : '' ?>>
                <span>現場資料已備齊</span>
            </label>
        </div>
        <div class="form-group mt-1">
            <label>備註</label>
            <textarea name="readiness_notes" class="form-control" rows="2"><?= e($rd['notes'] ?? '') ?></textarea>
        </div>
    </div>

    <!-- 現場環境 -->
    <div class="card">
        <div class="card-header">現場環境</div>
        <?php $sc = $case['site_conditions'] ?? []; ?>
        <div class="form-group">
            <label>建築結構 (可複選)</label>
            <div class="checkbox-row">
                <?php
                $structures = ['RC'=>'RC結構', 'steel_sheet'=>'鐵皮', 'open_area'=>'空曠地', 'construction_site'=>'建築工地'];
                $currentStructures = isset($sc['structure_type']) ? explode(',', $sc['structure_type']) : [];
                foreach ($structures as $v => $l):
                ?>
                <label class="checkbox-label">
                    <input type="checkbox" name="structure_type[]" value="<?= $v ?>" <?= in_array($v, $currentStructures) ? 'checked' : '' ?>>
                    <span><?= $l ?></span>
                </label>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="form-group">
            <label>管線需求 (可複選)</label>
            <div class="checkbox-row">
                <?php
                $conduits = ['PVC'=>'PVC', 'EMT'=>'EMT', 'RSG'=>'RSG', 'molding'=>'壓條', 'wall_penetration'=>'穿牆', 'aerial'=>'架空', 'underground'=>'切地埋管'];
                $currentConduits = isset($sc['conduit_type']) ? explode(',', $sc['conduit_type']) : [];
                foreach ($conduits as $v => $l):
                ?>
                <label class="checkbox-label">
                    <input type="checkbox" name="conduit_type[]" value="<?= $v ?>" <?= in_array($v, $currentConduits) ? 'checked' : '' ?>>
                    <span><?= $l ?></span>
                </label>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>樓層數</label>
                <input type="number" name="floor_count" class="form-control" min="0" value="<?= e($sc['floor_count'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>&nbsp;</label>
                <div class="checkbox-row">
                    <label class="checkbox-label">
                        <input type="checkbox" name="has_elevator" value="1" <?= !empty($sc['has_elevator']) ? 'checked' : '' ?>>
                        <span>有電梯</span>
                    </label>
                    <label class="checkbox-label">
                        <input type="checkbox" name="has_ladder_needed" value="1" <?= !empty($sc['has_ladder_needed']) ? 'checked' : '' ?>>
                        <span>需要梯子</span>
                    </label>
                </div>
            </div>
        </div>
        <div class="form-group">
            <label>特殊需求</label>
            <textarea name="special_requirements" class="form-control" rows="2"><?= e($sc['special_requirements'] ?? '') ?></textarea>
        </div>
    </div>

    <!-- 聯絡人 -->
    <div class="card">
        <div class="card-header d-flex justify-between align-center">
            <span>聯絡人</span>
            <button type="button" class="btn btn-outline btn-sm" onclick="addContact()">+ 新增聯絡人</button>
        </div>
        <div id="contactsContainer">
            <?php
            $contacts = $case['contacts'] ?? [['contact_name'=>'','contact_phone'=>'','contact_role'=>'','contact_note'=>'']];
            foreach ($contacts as $idx => $c):
            ?>
            <div class="contact-row" data-index="<?= $idx ?>">
                <div class="form-row">
                    <div class="form-group">
                        <label>姓名</label>
                        <input type="text" name="contacts[<?= $idx ?>][contact_name]" class="form-control" value="<?= e($c['contact_name']) ?>">
                    </div>
                    <div class="form-group">
                        <label>電話</label>
                        <input type="text" name="contacts[<?= $idx ?>][contact_phone]" class="form-control" value="<?= e($c['contact_phone'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>角色</label>
                        <input type="text" name="contacts[<?= $idx ?>][contact_role]" class="form-control" value="<?= e($c['contact_role'] ?? '') ?>" placeholder="屋主/管委會/工地主任">
                    </div>
                    <div class="form-group" style="align-self:flex-end">
                        <button type="button" class="btn btn-danger btn-sm" onclick="this.closest('.contact-row').remove()">刪除</button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- 所需技能 -->
    <div class="card">
        <div class="card-header">所需技能</div>
        <p class="text-muted mb-1" style="font-size:.85rem">勾選此案件需要的技能，並設定最低熟練度</p>
        <div class="skills-grid">
            <?php
            $reqSkills = [];
            if (!empty($case['required_skills'])) {
                foreach ($case['required_skills'] as $rs) {
                    $reqSkills[$rs['skill_id']] = $rs['min_proficiency'];
                }
            }
            $lastCat = '';
            foreach ($skills as $sk):
                if ($sk['category'] !== $lastCat):
                    $lastCat = $sk['category'];
            ?>
            <div class="skill-category"><?= e($sk['category']) ?></div>
            <?php endif; ?>
            <div class="skill-item">
                <label class="checkbox-label">
                    <input type="checkbox" class="skill-check" data-skill="<?= $sk['id'] ?>"
                           <?= isset($reqSkills[$sk['id']]) ? 'checked' : '' ?>
                           onchange="toggleSkillLevel(this)">
                    <span><?= e($sk['name']) ?></span>
                </label>
                <select name="required_skills[<?= $sk['id'] ?>]" class="form-control skill-level"
                        style="width:100px;display:<?= isset($reqSkills[$sk['id']]) ? 'inline-block' : 'none' ?>">
                    <option value="0">不需要</option>
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                    <option value="<?= $i ?>" <?= ($reqSkills[$sk['id']] ?? 0) == $i ? 'selected' : '' ?>><?= $i ?> 星以上</option>
                    <?php endfor; ?>
                </select>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="d-flex gap-1 mt-2">
        <button type="submit" class="btn btn-primary"><?= $case ? '儲存變更' : '建立案件' ?></button>
        <a href="/cases.php" class="btn btn-outline">取消</a>
    </div>
</form>

<style>
.form-row { display: flex; flex-wrap: wrap; gap: 12px; }
.form-row .form-group { flex: 1; min-width: 150px; }
.checkbox-row { display: flex; flex-wrap: wrap; gap: 12px; }
.checkbox-label { display: flex; align-items: center; gap: 6px; cursor: pointer; font-size: .9rem; }
.checkbox-label input[type="checkbox"] { width: 18px; height: 18px; }
.contact-row { border-bottom: 1px solid var(--gray-200); padding-bottom: 12px; margin-bottom: 12px; }
.skills-grid { display: flex; flex-direction: column; gap: 4px; }
.skill-category { font-weight: 600; color: var(--primary); margin-top: 12px; margin-bottom: 4px; padding-bottom: 4px; border-bottom: 1px solid var(--gray-200); }
.skill-item { display: flex; align-items: center; justify-content: space-between; padding: 4px 0; }
.skill-level { font-size: .85rem; }
</style>

<script>
var contactIndex = <?= count($contacts) ?>;
function addContact() {
    var html = '<div class="contact-row" data-index="' + contactIndex + '">' +
        '<div class="form-row">' +
        '<div class="form-group"><label>姓名</label><input type="text" name="contacts[' + contactIndex + '][contact_name]" class="form-control"></div>' +
        '<div class="form-group"><label>電話</label><input type="text" name="contacts[' + contactIndex + '][contact_phone]" class="form-control"></div>' +
        '<div class="form-group"><label>角色</label><input type="text" name="contacts[' + contactIndex + '][contact_role]" class="form-control" placeholder="屋主/管委會/工地主任"></div>' +
        '<div class="form-group" style="align-self:flex-end"><button type="button" class="btn btn-danger btn-sm" onclick="this.closest(\'.contact-row\').remove()">刪除</button></div>' +
        '</div></div>';
    document.getElementById('contactsContainer').insertAdjacentHTML('beforeend', html);
    contactIndex++;
}
function toggleSkillLevel(cb) {
    var sel = cb.closest('.skill-item').querySelector('.skill-level');
    if (cb.checked) {
        sel.style.display = 'inline-block';
        if (sel.value === '0') sel.value = '1';
    } else {
        sel.style.display = 'none';
        sel.value = '0';
    }
}
</script>
