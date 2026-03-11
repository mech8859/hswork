<div class="d-flex justify-between align-center mb-2">
    <h2>今日施工</h2>
    <a href="/worklog.php?action=history" class="btn btn-outline btn-sm">歷史記錄</a>
</div>

<?php if (empty($todaySchedules)): ?>
<div class="card text-center" style="padding:40px 16px">
    <p class="text-muted" style="font-size:1.1rem">今日無排工</p>
    <p class="text-muted">請聯繫工程主管確認排程</p>
</div>
<?php else: ?>

<?php foreach ($todaySchedules as $ts): ?>
<div class="card worklog-card">
    <div class="d-flex justify-between align-center mb-1">
        <strong style="font-size:1.1rem"><?= e($ts['case_title']) ?></strong>
        <span class="badge badge-primary"><?= e($ts['case_number']) ?></span>
    </div>

    <?php if ($ts['address']): ?>
    <div class="worklog-address">
        <a href="https://maps.google.com/?q=<?= urlencode($ts['address']) ?>" target="_blank" rel="noopener">
            <?= e($ts['address']) ?>
        </a>
    </div>
    <?php endif; ?>

    <div class="worklog-info">
        <?php if ($ts['plate_number']): ?><span>車輛: <?= e($ts['plate_number']) ?></span><?php endif; ?>
        <?php if ($ts['total_visits'] > 1): ?><span>第<?= $ts['visit_number'] ?>/<?= $ts['total_visits'] ?>次</span><?php endif; ?>
    </div>

    <div class="worklog-actions">
        <?php if (!$ts['worklog_id']): ?>
            <!-- 尚未打卡 -->
            <form method="POST" action="/worklog.php?action=checkin" style="flex:1">
                <?= csrf_field() ?>
                <input type="hidden" name="schedule_id" value="<?= $ts['id'] ?>">
                <button type="submit" class="btn btn-success btn-block" onclick="return confirm('確定打卡到場?')">
                    打卡到場
                </button>
            </form>
        <?php elseif (!$ts['departure_time']): ?>
            <!-- 已到場，尚未離場 -->
            <div class="worklog-time-info">
                <span>到場: <?= format_datetime($ts['arrival_time'], 'H:i') ?></span>
            </div>
            <div class="d-flex gap-1" style="flex:1">
                <a href="/worklog.php?action=report&id=<?= $ts['worklog_id'] ?>" class="btn btn-primary" style="flex:1">
                    填寫回報
                </a>
                <form method="POST" action="/worklog.php?action=checkout" style="flex:1">
                    <?= csrf_field() ?>
                    <input type="hidden" name="worklog_id" value="<?= $ts['worklog_id'] ?>">
                    <button type="submit" class="btn btn-danger btn-block" onclick="return confirm('確定打卡離場?')">
                        打卡離場
                    </button>
                </form>
            </div>
        <?php else: ?>
            <!-- 已完成 -->
            <div class="worklog-time-info">
                <span>到場: <?= format_datetime($ts['arrival_time'], 'H:i') ?></span>
                <span>離場: <?= format_datetime($ts['departure_time'], 'H:i') ?></span>
            </div>
            <a href="/worklog.php?action=report&id=<?= $ts['worklog_id'] ?>" class="btn btn-outline btn-block">
                <?= $ts['work_description'] ? '查看/編輯回報' : '填寫回報' ?>
            </a>
        <?php endif; ?>
    </div>
</div>
<?php endforeach; ?>

<?php endif; ?>

<style>
.worklog-card { padding: 16px; }
.worklog-address {
    font-size: .9rem; margin-bottom: 8px;
    padding: 6px 10px; background: var(--gray-50); border-radius: var(--radius);
}
.worklog-address a { color: var(--primary); }
.worklog-info {
    display: flex; gap: 12px; font-size: .85rem; color: var(--gray-500);
    margin-bottom: 12px;
}
.worklog-actions { display: flex; flex-direction: column; gap: 8px; }
.worklog-time-info {
    display: flex; gap: 16px; font-size: .9rem; font-weight: 500;
    padding: 6px 0;
}
</style>
