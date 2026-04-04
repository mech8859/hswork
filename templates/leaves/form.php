<h2>申請請假</h2>

<form method="POST" class="mt-2">
    <?= csrf_field() ?>

    <div class="card">
        <div class="card-header">請假資料</div>
        <?php if ($canManage && !empty($users)): ?>
        <div class="form-group">
            <label>申請人 *</label>
            <select name="user_id" class="form-control" required>
                <option value="">請選擇</option>
                <?php foreach ($users as $u): ?>
                <option value="<?= $u['id'] ?>"><?= e($u['real_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
        <div class="form-row">
            <div class="form-group">
                <label>假別 *</label>
                <select name="leave_type" class="form-control" required>
                    <option value="annual">特休</option>
                    <option value="personal">事假</option>
                    <option value="sick">病假</option>
                    <option value="official">公假</option>
                </select>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>開始日期 *</label>
                <input type="date" max="2099-12-31" name="start_date" class="form-control" required value="<?= date('Y-m-d') ?>">
            </div>
            <div class="form-group">
                <label>結束日期 *</label>
                <input type="date" max="2099-12-31" name="end_date" class="form-control" required value="<?= date('Y-m-d') ?>">
            </div>
        </div>
        <div class="form-group">
            <label>原因</label>
            <textarea name="reason" class="form-control" rows="3" placeholder="請輸入請假原因"></textarea>
        </div>
    </div>

    <div class="d-flex gap-1 mt-2">
        <button type="submit" class="btn btn-primary">送出申請</button>
        <a href="/leaves.php" class="btn btn-outline">取消</a>
    </div>
</form>

<style>
.form-row { display: flex; flex-wrap: wrap; gap: 12px; }
.form-row .form-group { flex: 1; min-width: 150px; }
</style>
