<div class="d-flex justify-between align-center flex-wrap gap-1 mb-2">
    <h2><?= e($worker['name']) ?> - 工程師配對</h2>
    <div class="d-flex gap-1">
        <a href="/dispatch_workers.php?action=skills&id=<?= $worker['id'] ?>" class="btn btn-outline btn-sm">技能設定</a>
        <a href="/dispatch_workers.php?action=edit&id=<?= $worker['id'] ?>" class="btn btn-outline btn-sm">返回人員資料</a>
        <?= back_button('/dispatch_workers.php') ?>
    </div>
</div>

<form method="POST">
    <?= csrf_field() ?>
    <div class="card">
        <div class="card-header">與工程師的配對評分 (1-5星，0=尚未評)</div>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>工程師</th>
                        <th>分公司</th>
                        <th style="width:200px">配對評分</th>
                        <th>備註</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($engineers as $eng):
                        $pairData = isset($existingPairs[$eng['id']]) ? $existingPairs[$eng['id']] : null;
                        $currentScore = $pairData ? (int)$pairData['compatibility'] : 0;
                        $currentNote = $pairData ? $pairData['note'] : '';
                    ?>
                    <tr>
                        <td><strong><?= e($eng['real_name']) ?></strong></td>
                        <td><?= e($eng['branch_name'] ?? '') ?></td>
                        <td>
                            <div class="star-selector">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                <span class="star-btn <?= $i <= $currentScore ? 'active' : '' ?>"
                                      data-value="<?= $i ?>"
                                      onclick="setStars(this)">&#9733;</span>
                                <?php endfor; ?>
                                <span class="star-clear" onclick="clearStars(this)">&times;</span>
                                <input type="hidden" name="pairs[<?= $eng['id'] ?>][compatibility]" value="<?= $currentScore ?>">
                            </div>
                        </td>
                        <td>
                            <input type="text" name="pairs[<?= $eng['id'] ?>][note]" class="form-control form-control-sm" value="<?= e($currentNote) ?>" placeholder="選填" style="font-size:.8rem">
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="d-flex gap-1 mt-2">
        <button type="submit" class="btn btn-primary">儲存配對</button>
        <a href="/dispatch_workers.php?action=edit&id=<?= $worker['id'] ?>" class="btn btn-outline">取消</a>
    </div>
</form>

<style>
.star-selector { display: flex; align-items: center; gap: 2px; }
.star-btn {
    font-size: 1.3rem; cursor: pointer; color: var(--gray-300);
    transition: color .1s; user-select: none;
}
.star-btn.active { color: var(--warning); }
.star-btn:hover { color: var(--warning); }
.star-clear {
    font-size: 1.1rem; cursor: pointer; color: var(--gray-500);
    margin-left: 6px; padding: 0 4px;
}
.star-clear:hover { color: var(--danger); }
</style>

<script>
function setStars(el) {
    var value = parseInt(el.dataset.value);
    var container = el.closest('.star-selector');
    var input = container.querySelector('input[type="hidden"]');
    input.value = value;
    var stars = container.querySelectorAll('.star-btn');
    for (var i = 0; i < stars.length; i++) {
        if (parseInt(stars[i].dataset.value) <= value) {
            stars[i].classList.add('active');
        } else {
            stars[i].classList.remove('active');
        }
    }
}
function clearStars(el) {
    var container = el.closest('.star-selector');
    var input = container.querySelector('input[type="hidden"]');
    input.value = '0';
    var stars = container.querySelectorAll('.star-btn');
    for (var i = 0; i < stars.length; i++) {
        stars[i].classList.remove('active');
    }
}
</script>
