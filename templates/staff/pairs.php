<div class="d-flex justify-between align-center flex-wrap gap-1 mb-2">
    <h2>人員配對表</h2>
    <?= back_button('/staff.php') ?>
</div>

<!-- 新增/編輯配對 -->
<div class="card">
    <div class="card-header">設定配對</div>
    <form method="POST" class="mt-1">
        <?= csrf_field() ?>
        <div class="form-row">
            <div class="form-group">
                <label>工程師 A</label>
                <select name="user_a_id" class="form-control" required>
                    <option value="">請選擇</option>
                    <?php foreach ($engineers as $eng): ?>
                    <option value="<?= $eng['id'] ?>"><?= e($eng['real_name']) ?> (<?= e($eng['branch_name']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>工程師 B</label>
                <select name="user_b_id" class="form-control" required>
                    <option value="">請選擇</option>
                    <?php foreach ($engineers as $eng): ?>
                    <option value="<?= $eng['id'] ?>"><?= e($eng['real_name']) ?> (<?= e($eng['branch_name']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>配對度</label>
                <select name="compatibility" class="form-control" required>
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                    <option value="<?= $i ?>" <?= $i === 3 ? 'selected' : '' ?>><?= $i ?> 星</option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="form-group">
                <label>備註</label>
                <input type="text" name="note" class="form-control" placeholder="選填">
            </div>
            <div class="form-group" style="align-self:flex-end">
                <button type="submit" class="btn btn-primary btn-sm">儲存配對</button>
            </div>
        </div>
    </form>
</div>

<!-- 現有配對 -->
<div class="card">
    <div class="card-header">現有配對</div>
    <?php if (empty($pairs)): ?>
        <p class="text-muted">尚未設定配對</p>
    <?php else: ?>

    <!-- 手機版 -->
    <div class="pair-cards show-mobile">
        <?php foreach ($pairs as $p): ?>
        <div class="pair-card">
            <div class="d-flex justify-between align-center">
                <span><?= e($p['user_a_name']) ?> + <?= e($p['user_b_name']) ?></span>
                <span class="stars"><?= str_repeat('&#9733;', $p['compatibility']) ?><?= str_repeat('&#9734;', 5 - $p['compatibility']) ?></span>
            </div>
            <?php if ($p['note']): ?>
            <div class="text-muted" style="font-size:.8rem"><?= e($p['note']) ?></div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- 桌面版 -->
    <div class="table-responsive hide-mobile">
        <table class="table">
            <thead><tr><th>工程師 A</th><th>工程師 B</th><th>配對度</th><th>備註</th></tr></thead>
            <tbody>
                <?php foreach ($pairs as $p): ?>
                <tr>
                    <td><?= e($p['user_a_name']) ?></td>
                    <td><?= e($p['user_b_name']) ?></td>
                    <td><span class="stars"><?= str_repeat('&#9733;', $p['compatibility']) ?><?= str_repeat('&#9734;', 5 - $p['compatibility']) ?></span></td>
                    <td><?= e($p['note'] ?? '-') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<style>
.form-row { display: flex; flex-wrap: wrap; gap: 12px; }
.form-row .form-group { flex: 1; min-width: 140px; }
.pair-cards { display: flex; flex-direction: column; gap: 8px; }
.pair-card { border: 1px solid var(--gray-200); border-radius: var(--radius); padding: 10px; }
.show-mobile { display: flex; }
.hide-mobile { display: none; }
@media (min-width: 768px) {
    .show-mobile { display: none !important; }
    .hide-mobile { display: block !important; }
}
</style>
