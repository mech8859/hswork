<h2><?= $record ? '編輯點工記錄' : '新增點工記錄' ?></h2>

<form method="POST" class="mt-2">
    <?= csrf_field() ?>

    <div class="card">
        <div class="card-header">點工資料</div>
        <div class="form-row">
            <div class="form-group">
                <label>支援人員 *</label>
                <select name="user_id" class="form-control" required>
                    <option value="">請選擇</option>
                    <?php foreach ($engineers as $eng): ?>
                    <option value="<?= $eng['id'] ?>" <?= ($record['user_id'] ?? '') == $eng['id'] ? 'selected' : '' ?>><?= e($eng['real_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>支援日期 *</label>
                <input type="date" max="2099-12-31" name="support_date" class="form-control" value="<?= e($record['support_date'] ?? date('Y-m-d')) ?>" required>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>原據點 *</label>
                <select name="from_branch_id" class="form-control" required>
                    <?php foreach ($branches as $b): ?>
                    <option value="<?= $b['id'] ?>" <?= ($record['from_branch_id'] ?? '') == $b['id'] ? 'selected' : '' ?>><?= e($b['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>支援據點 *</label>
                <select name="to_branch_id" class="form-control" required>
                    <?php foreach ($branches as $b): ?>
                    <option value="<?= $b['id'] ?>" <?= ($record['to_branch_id'] ?? '') == $b['id'] ? 'selected' : '' ?>><?= e($b['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>計費方式 *</label>
                <select name="charge_type" class="form-control" required id="chargeType" onchange="toggleHours()">
                    <option value="full_day" <?= ($record['charge_type'] ?? '') === 'full_day' ? 'selected' : '' ?>>整日</option>
                    <option value="half_day" <?= ($record['charge_type'] ?? '') === 'half_day' ? 'selected' : '' ?>>半日</option>
                    <option value="hourly" <?= ($record['charge_type'] ?? '') === 'hourly' ? 'selected' : '' ?>>時數</option>
                </select>
            </div>
            <div class="form-group" id="hoursGroup" style="<?= ($record['charge_type'] ?? '') === 'hourly' ? '' : 'display:none' ?>">
                <label>時數</label>
                <input type="number" name="hours" class="form-control" value="<?= e($record['hours'] ?? '') ?>" step="0.5" min="0.5">
            </div>
        </div>
        <div class="form-group">
            <label>備註</label>
            <textarea name="note" class="form-control" rows="2"><?= e($record['note'] ?? '') ?></textarea>
        </div>
    </div>

    <div class="d-flex gap-1 mt-2">
        <button type="submit" class="btn btn-primary"><?= $record ? '儲存變更' : '新增記錄' ?></button>
        <a href="/inter_branch.php" class="btn btn-outline">取消</a>
    </div>
</form>

<script>
function toggleHours() {
    var el = document.getElementById('hoursGroup');
    el.style.display = document.getElementById('chargeType').value === 'hourly' ? '' : 'none';
}
</script>

<style>
.form-row { display: flex; flex-wrap: wrap; gap: 12px; }
.form-row .form-group { flex: 1; min-width: 150px; }
</style>
