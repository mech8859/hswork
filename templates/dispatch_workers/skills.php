<div class="d-flex justify-between align-center flex-wrap gap-1 mb-2">
    <h2><?= e($worker['name']) ?> - 技能設定</h2>
    <div class="d-flex gap-1">
        <a href="/dispatch_workers.php?action=edit&id=<?= $worker['id'] ?>" class="btn btn-outline btn-sm">返回人員資料</a>
        <?= back_button('/dispatch_workers.php') ?>
    </div>
</div>

<form method="POST">
    <?= csrf_field() ?>
    <div class="card">
        <div class="card-header">技能熟練度 (1-5星，0=不具備)</div>
        <div class="skills-form">
            <?php
            $lastGroup = '';
            $lastCat = '';
            foreach ($allSkills as $sk):
                $group = $sk['skill_group'] ?: '其他';
                if ($group !== $lastGroup):
                    $lastGroup = $group;
                    $lastCat = '';
            ?>
            <div class="skill-group-header"><?= e($group) ?></div>
            <?php endif; ?>
            <?php
                if ($sk['category'] !== $lastCat):
                    $lastCat = $sk['category'];
            ?>
            <div class="skill-category-header"><?= e($sk['category']) ?></div>
            <?php endif; ?>
            <div class="skill-form-item">
                <label><?= e($sk['name']) ?></label>
                <div class="star-selector" data-skill="<?= $sk['id'] ?>">
                    <?php
                    $current = isset($workerSkillMap[$sk['id']]) ? (int)$workerSkillMap[$sk['id']] : 0;
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
        <a href="/dispatch_workers.php?action=edit&id=<?= $worker['id'] ?>" class="btn btn-outline">取消</a>
    </div>
</form>

<style>
.skills-form { max-width: 100%; }
.skill-group-header {
    font-weight: 700; font-size: 1rem; color: var(--gray-900);
    margin-top: 20px; padding: 8px 0 4px;
    border-bottom: 2px solid var(--primary);
}
.skill-group-header:first-child { margin-top: 0; }
.skill-category-header {
    font-weight: 600; color: var(--primary);
    margin-top: 12px; padding-bottom: 4px;
    border-bottom: 1px solid var(--gray-200);
    font-size: .9rem;
}
.skill-form-item {
    display: flex; justify-content: space-between; align-items: center;
    padding: 6px 0; border-bottom: 1px solid var(--gray-100);
}
.skill-form-item label { font-size: .85rem; flex: 1; }
.star-selector { display: flex; align-items: center; gap: 2px; flex-shrink: 0; }
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
