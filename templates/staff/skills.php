<h2><?= e($user['real_name']) ?> - 技能設定</h2>

<form method="POST" class="mt-2">
    <?= csrf_field() ?>

    <div class="card">
        <div class="card-header">技能熟練度 (1-5星，0=不具備)</div>
        <div class="skills-form">
            <?php
            $lastCat = '';
            foreach ($allSkills as $sk):
                if ($sk['category'] !== $lastCat):
                    $lastCat = $sk['category'];
            ?>
            <div class="skill-category-header"><?= e($sk['category']) ?></div>
            <?php endif; ?>
            <div class="skill-form-item">
                <label><?= e($sk['name']) ?></label>
                <div class="star-selector" data-skill="<?= $sk['id'] ?>">
                    <?php
                    $current = $userSkillMap[$sk['id']] ?? 0;
                    for ($i = 1; $i <= 5; $i++):
                    ?>
                    <span class="star-btn <?= $i <= $current ? 'active' : '' ?>"
                          data-value="<?= $i ?>"
                          onclick="setStars(this)">&#9733;</span>
                    <?php endfor; ?>
                    <span class="star-clear" onclick="clearStars(this)">&times;</span>
                    <input type="hidden" name="skills[<?= $sk['id'] ?>]" value="<?= $current ?>">
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="d-flex gap-1 mt-2">
        <button type="submit" class="btn btn-primary">儲存技能</button>
        <a href="/staff.php?action=view&id=<?= $user['id'] ?>" class="btn btn-outline">取消</a>
    </div>
</form>

<style>
.skills-form { max-width: 600px; }
.skill-category-header {
    font-weight: 600; color: var(--primary);
    margin-top: 16px; padding-bottom: 4px;
    border-bottom: 1px solid var(--gray-200);
}
.skill-form-item {
    display: flex; justify-content: space-between; align-items: center;
    padding: 8px 0; border-bottom: 1px solid var(--gray-100);
}
.skill-form-item label { font-size: .9rem; }
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
    container.querySelectorAll('.star-btn').forEach(function(s) {
        s.classList.toggle('active', parseInt(s.dataset.value) <= value);
    });
}
function clearStars(el) {
    var container = el.closest('.star-selector');
    var input = container.querySelector('input[type="hidden"]');
    input.value = '0';
    container.querySelectorAll('.star-btn').forEach(function(s) {
        s.classList.remove('active');
    });
}
</script>
