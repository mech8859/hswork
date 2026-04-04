<div class="d-flex justify-between align-center mb-2">
    <h2><?= e($targetUser['real_name']) ?> - 配對表</h2>
    <div class="d-flex gap-1">
        <a href="/staff.php?action=skills&id=<?= $targetUser['id'] ?>" class="btn btn-outline">技能設定</a>
        <a href="/staff.php?action=view&id=<?= $targetUser['id'] ?>" class="btn btn-outline">返回人員資料</a>
    </div>
</div>

<form method="POST">
    <?= csrf_field() ?>
    <div class="card">
        <div class="card-header">
            與其他工程師的配對度設定
            <span class="text-muted" style="font-size:.8rem;margin-left:8px">1★=不佳 ~ 5★=絕佳，0=未設定</span>
        </div>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th style="width:160px">工程師</th>
                        <th style="width:100px">據點</th>
                        <th style="width:200px">配對度</th>
                        <th>備註</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($engineers as $eng):
                        if ((int)$eng['id'] === $userId) continue;
                        $pair = isset($existingPairs[(int)$eng['id']]) ? $existingPairs[(int)$eng['id']] : null;
                        $currentCompat = $pair ? (int)$pair['compatibility'] : 0;
                        $currentNote = $pair ? ($pair['note'] ?? '') : '';
                    ?>
                    <tr>
                        <td>
                            <strong><?= e($eng['real_name']) ?></strong>
                            <?php if (!empty($eng['caution_notes'])): ?>
                            <br><small style="color:#e65100">⚠ <?= e($eng['caution_notes']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td><span class="text-muted" style="font-size:.85rem"><?= e($eng['branch_name'] ?? '') ?></span></td>
                        <td>
                            <div class="star-rating" data-for="<?= $eng['id'] ?>">
                                <?php for ($s = 1; $s <= 5; $s++): ?>
                                <span class="star <?= $s <= $currentCompat ? 'active' : '' ?>" data-val="<?= $s ?>">★</span>
                                <?php endfor; ?>
                                <input type="hidden" name="pairs[<?= $eng['id'] ?>][compatibility]" value="<?= $currentCompat ?>" id="compat_<?= $eng['id'] ?>">
                            </div>
                        </td>
                        <td>
                            <input type="text" name="pairs[<?= $eng['id'] ?>][note]" class="form-control" style="font-size:.85rem" placeholder="選填" value="<?= e($currentNote) ?>">
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="d-flex gap-1">
        <button type="submit" class="btn btn-primary">儲存所有配對</button>
        <a href="/staff.php?action=view&id=<?= $targetUser['id'] ?>" class="btn btn-outline">取消</a>
    </div>
</form>

<style>
.star-rating { display:inline-flex; gap:2px; }
.star { font-size:1.3rem; color:#ddd; cursor:pointer; transition:color .15s; user-select:none; }
.star.active { color:#f9a825; }
.star:hover { color:#f57f17; }
</style>

<script>
document.querySelectorAll('.star-rating').forEach(function(container) {
    var forId = container.getAttribute('data-for');
    container.querySelectorAll('.star').forEach(function(star) {
        star.addEventListener('click', function() {
            var val = parseInt(this.getAttribute('data-val'));
            var input = document.getElementById('compat_' + forId);
            // 如果點同一個星星，取消設定
            if (parseInt(input.value) === val) {
                input.value = 0;
                container.querySelectorAll('.star').forEach(function(s) { s.classList.remove('active'); });
            } else {
                input.value = val;
                container.querySelectorAll('.star').forEach(function(s) {
                    s.classList.toggle('active', parseInt(s.getAttribute('data-val')) <= val);
                });
            }
        });
        star.addEventListener('mouseenter', function() {
            var val = parseInt(this.getAttribute('data-val'));
            container.querySelectorAll('.star').forEach(function(s) {
                if (parseInt(s.getAttribute('data-val')) <= val) s.style.color = '#f57f17';
            });
        });
        star.addEventListener('mouseleave', function() {
            container.querySelectorAll('.star').forEach(function(s) {
                s.style.color = '';
            });
        });
    });
});
</script>
