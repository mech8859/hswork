<?php
$isEdit = !empty($record);
// 解碼已選人員
$selectedGroupPhotoIds = array();
$selectedEngineerIds = array();
if ($isEdit) {
    if (!empty($record['group_photo_engineer_ids'])) {
        $tmp = json_decode($record['group_photo_engineer_ids'], true);
        if (is_array($tmp)) $selectedGroupPhotoIds = array_map('intval', $tmp);
    }
    if (!empty($record['engineer_ids'])) {
        $tmp = json_decode($record['engineer_ids'], true);
        if (is_array($tmp)) $selectedEngineerIds = array_map('intval', $tmp);
    }
}
?>

<div class="d-flex justify-between align-center mb-2">
    <h2><?= $isEdit ? '編輯五星評價' : '新增五星評價' ?></h2>
    <a href="/reviews.php" class="btn btn-outline btn-sm">返回列表</a>
</div>

<form method="POST" action="/reviews.php?action=store" id="reviewForm">
    <?= csrf_field() ?>
    <?php if ($isEdit): ?>
    <input type="hidden" name="id" value="<?= (int)$record['id'] ?>">
    <?php endif; ?>

    <div class="card">
        <div class="card-header">基本資料</div>
        <div class="form-row">
            <div class="form-group">
                <label>五星評價編號</label>
                <input type="text" class="form-control" disabled style="background:#f5f5f5;color:#1a73e8;font-weight:600"
                       value="<?= e($isEdit && !empty($record['review_number']) ? $record['review_number'] : (isset($nextNumber) ? $nextNumber : '')) ?>">
            </div>
            <div class="form-group">
                <label>日期 *</label>
                <input type="date" max="2099-12-31" name="review_date" class="form-control" required
                       value="<?= e($isEdit && !empty($record['review_date']) ? $record['review_date'] : date('Y-m-d')) ?>">
            </div>
            <div class="form-group">
                <label>所屬分公司</label>
                <select name="branch_id" class="form-control">
                    <option value="">請選擇</option>
                    <?php foreach ($branches as $b): ?>
                    <option value="<?= e($b['id']) ?>"
                            <?= ($isEdit && (int)$record['branch_id'] === (int)$b['id']) ? 'selected' : '' ?>>
                        <?= e($b['name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>客戶名稱</label>
                <input type="text" name="customer_name" class="form-control"
                       value="<?= e($isEdit && !empty($record['customer_name']) ? $record['customer_name'] : '') ?>">
            </div>
            <div class="form-group">
                <label>原客戶名稱（後刪）</label>
                <input type="text" name="original_customer_name" class="form-control"
                       value="<?= e($isEdit && !empty($record['original_customer_name']) ? $record['original_customer_name'] : '') ?>">
            </div>
            <div class="form-group">
                <label>Google 評價人名稱</label>
                <input type="text" name="google_reviewer_name" class="form-control"
                       value="<?= e($isEdit && !empty($record['google_reviewer_name']) ? $record['google_reviewer_name'] : '') ?>">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group" style="flex:1">
                <label>照片</label>
                <input type="text" name="photo_path" class="form-control" placeholder="照片說明/連結（人工輸入）"
                       value="<?= e($isEdit && !empty($record['photo_path']) ? $record['photo_path'] : '') ?>">
            </div>
            <div class="form-group" style="flex:1">
                <label>獎金發放日期</label>
                <input type="date" max="2099-12-31" name="bonus_payment_date" class="form-control"
                       value="<?= e($isEdit && !empty($record['bonus_payment_date']) ? $record['bonus_payment_date'] : '') ?>">
            </div>
        </div>

        <div class="form-group">
            <label>不符獎金原因</label>
            <textarea name="reason" class="form-control" rows="3"><?= e($isEdit && !empty($record['reason']) ? $record['reason'] : '') ?></textarea>
        </div>
    </div>

    <!-- 施工人員合影（多選） -->
    <div class="card">
        <div class="card-header d-flex justify-between align-center">
            <span>施工人員合影（可多選）</span>
            <span class="text-muted" style="font-size:.8rem" id="groupPhotoCount">已選 <?= count($selectedGroupPhotoIds) ?> 人</span>
        </div>
        <input type="text" class="form-control mb-1" id="groupPhotoSearch" placeholder="搜尋人員..." onkeyup="filterList('groupPhotoList', this.value)">
        <div id="groupPhotoList" class="engineer-checklist">
            <?php if (!empty($engineers)): foreach ($engineers as $eng): ?>
            <label class="eng-chk">
                <input type="checkbox" name="group_photo_engineer_ids[]" value="<?= (int)$eng['id'] ?>"
                       onchange="updateCount('groupPhotoList','groupPhotoCount')"
                       <?= in_array((int)$eng['id'], $selectedGroupPhotoIds, true) ? 'checked' : '' ?>>
                <span><?= e($eng['real_name']) ?></span>
                <?php if (!empty($eng['branch_name'])): ?>
                <span class="text-muted" style="font-size:.75rem">(<?= e($eng['branch_name']) ?>)</span>
                <?php endif; ?>
            </label>
            <?php endforeach; else: ?>
            <p class="text-muted">尚無可選工程人員</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- 施工人員（多選） -->
    <div class="card">
        <div class="card-header d-flex justify-between align-center">
            <span>施工人員（可多選）</span>
            <span class="text-muted" style="font-size:.8rem" id="engineerCount">已選 <?= count($selectedEngineerIds) ?> 人</span>
        </div>
        <input type="text" class="form-control mb-1" id="engineerSearch" placeholder="搜尋人員..." onkeyup="filterList('engineerList', this.value)">
        <div id="engineerList" class="engineer-checklist">
            <?php if (!empty($engineers)): foreach ($engineers as $eng): ?>
            <label class="eng-chk">
                <input type="checkbox" name="engineer_ids[]" value="<?= (int)$eng['id'] ?>"
                       onchange="updateCount('engineerList','engineerCount')"
                       <?= in_array((int)$eng['id'], $selectedEngineerIds, true) ? 'checked' : '' ?>>
                <span><?= e($eng['real_name']) ?></span>
                <?php if (!empty($eng['branch_name'])): ?>
                <span class="text-muted" style="font-size:.75rem">(<?= e($eng['branch_name']) ?>)</span>
                <?php endif; ?>
            </label>
            <?php endforeach; else: ?>
            <p class="text-muted">尚無可選工程人員</p>
            <?php endif; ?>
        </div>
        <div class="form-group mt-1">
            <label>原施工人員（後刪）</label>
            <input type="text" name="original_engineer_names" class="form-control" placeholder="自由輸入已離職/誤刪人員名字"
                   value="<?= e($isEdit && !empty($record['original_engineer_names']) ? $record['original_engineer_names'] : '') ?>">
        </div>
    </div>

    <div class="d-flex justify-between mt-2">
        <a href="/reviews.php" class="btn btn-outline">取消</a>
        <button type="submit" class="btn btn-primary"><?= $isEdit ? '更新' : '新增' ?></button>
    </div>
</form>

<?php if ($isEdit && Auth::hasPermission('all')): ?>
<form method="POST" action="/reviews.php?action=delete" class="mt-2" onsubmit="return confirm('確定要刪除此筆五星評價嗎？')">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int)$record['id'] ?>">
    <button type="submit" class="btn btn-sm" style="color:var(--danger)">刪除此筆</button>
</form>
<?php endif; ?>

<style>
.form-row { display: flex; flex-wrap: wrap; gap: 12px; }
.form-row .form-group { flex: 1; min-width: 150px; }
.engineer-checklist {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
    gap: 4px;
    max-height: 320px;
    overflow-y: auto;
    border: 1px solid var(--gray-200);
    border-radius: var(--radius);
    padding: 8px;
}
.eng-chk {
    display: flex; align-items: center; gap: 6px;
    padding: 6px 8px;
    border-radius: 4px;
    cursor: pointer;
    font-size: .9rem;
}
.eng-chk:hover { background: #f0f7ff; }
.eng-chk input[type="checkbox"] { width: 16px; height: 16px; }
.eng-chk.hidden { display: none; }
</style>

<script>
function updateCount(listId, countId) {
    var checked = document.querySelectorAll('#' + listId + ' input[type="checkbox"]:checked').length;
    document.getElementById(countId).textContent = '已選 ' + checked + ' 人';
}
function filterList(listId, keyword) {
    keyword = keyword.trim().toLowerCase();
    var labels = document.querySelectorAll('#' + listId + ' label.eng-chk');
    for (var i = 0; i < labels.length; i++) {
        var txt = labels[i].textContent.toLowerCase();
        if (!keyword || txt.indexOf(keyword) !== -1) {
            labels[i].classList.remove('hidden');
        } else {
            labels[i].classList.add('hidden');
        }
    }
}
</script>
