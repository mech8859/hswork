<div class="d-flex justify-between align-center mb-2">
    <h2>施工記錄</h2>
    <a href="/worklog.php" class="btn btn-outline btn-sm">今日施工</a>
</div>

<?php if (empty($history)): ?>
<div class="card text-center" style="padding:40px 16px">
    <p class="text-muted">尚無施工記錄</p>
</div>
<?php else: ?>

<div class="timeline">
<?php
$lastDate = '';
foreach ($history as $h):
    $date = $h['schedule_date'];
    $dayNames = array('日','一','二','三','四','五','六');
    $dateLabel = format_date($date, 'n月j日') . ' (' . $dayNames[(int)date('w', strtotime($date))] . ')';
    if ($date !== $lastDate):
        $lastDate = $date;
?>
<div class="timeline-date"><?= $dateLabel ?></div>
<?php endif; ?>

<div class="timeline-item" onclick="location.href='/worklog.php?action=report&id=<?= $h['id'] ?>'">
    <div class="timeline-dot <?= $h['departure_time'] ? 'dot-done' : 'dot-pending' ?>"></div>
    <div class="timeline-content">
        <div class="d-flex justify-between align-center">
            <strong><?= e($h['case_title']) ?></strong>
            <a href="/schedule.php?action=view&id=<?= (int)$h['schedule_id'] ?>" class="badge badge-primary" style="font-size:.7rem;text-decoration:none" onclick="event.stopPropagation()"><?= e($h['case_number']) ?></a>
        </div>

        <div class="timeline-meta">
            <?php if ($h['arrival_time']): ?>
            <span>&#x1F552; <?= format_datetime($h['arrival_time'], 'H:i') ?></span>
            <?php endif; ?>
            <?php if ($h['departure_time']): ?>
            <span>&#x2192; <?= format_datetime($h['departure_time'], 'H:i') ?></span>
            <?php endif; ?>
            <?php if ($h['total_visits'] > 1): ?>
            <span>第<?= $h['visit_number'] ?>/<?= $h['total_visits'] ?>次</span>
            <?php endif; ?>
            <?php if ($h['photo_count'] > 0): ?>
            <span>&#x1F4F7; <?= $h['photo_count'] ?></span>
            <?php endif; ?>
        </div>

        <?php if ($h['work_description']): ?>
        <div class="timeline-desc"><?= e(mb_substr($h['work_description'], 0, 100)) ?><?= mb_strlen($h['work_description']) > 100 ? '...' : '' ?></div>
        <?php endif; ?>

        <?php if (!$h['work_description'] || !$h['departure_time']): ?>
        <div class="timeline-warning">
            <?php if (!$h['work_description']): ?><span class="text-danger" style="font-size:.8rem">未填回報</span><?php endif; ?>
            <?php if (!$h['departure_time']): ?><span class="text-danger" style="font-size:.8rem">未打卡離場</span><?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($h['payment_collected'])): ?>
        <div class="timeline-payment">&#x1F4B0; 已收款 $<?= number_format($h['payment_amount'] ?? 0) ?></div>
        <?php endif; ?>
    </div>
</div>

<?php endforeach; ?>
</div>

<?php endif; ?>

<style>
.timeline { position: relative; padding-left: 20px; }
.timeline::before {
    content: ''; position: absolute; left: 8px; top: 40px; bottom: 0;
    width: 2px; background: var(--gray-200);
}
.timeline-date {
    font-weight: 700; font-size: 1rem; color: var(--gray-800);
    padding: 12px 0 6px; position: relative;
}
.timeline-item {
    position: relative; padding: 8px 0 8px 16px; cursor: pointer;
    transition: background .15s; border-radius: var(--radius);
}
.timeline-item:hover { background: var(--gray-50); }
.timeline-dot {
    position: absolute; left: -16px; top: 14px;
    width: 12px; height: 12px; border-radius: 50%;
    border: 2px solid #fff; z-index: 1;
}
.dot-done { background: var(--success); }
.dot-pending { background: var(--warning); }
.timeline-content {
    background: #fff; border: 1px solid var(--gray-200); border-radius: var(--radius);
    padding: 10px 12px;
}
.timeline-meta {
    display: flex; gap: 10px; flex-wrap: wrap;
    font-size: .8rem; color: var(--gray-500); margin-top: 4px;
}
.timeline-desc {
    font-size: .85rem; color: var(--gray-700); margin-top: 6px;
    line-height: 1.4;
}
.timeline-warning { margin-top: 4px; display: flex; gap: 8px; }
.timeline-payment {
    margin-top: 4px; font-size: .8rem; color: var(--success); font-weight: 500;
}
</style>
