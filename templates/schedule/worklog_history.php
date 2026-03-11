<div class="d-flex justify-between align-center mb-2">
    <h2>施工記錄</h2>
    <a href="/worklog.php" class="btn btn-outline btn-sm">今日施工</a>
</div>

<?php if (empty($history)): ?>
<div class="card text-center" style="padding:40px 16px">
    <p class="text-muted">尚無施工記錄</p>
</div>
<?php else: ?>

<?php
$lastDate = '';
foreach ($history as $h):
    $date = format_date($h['schedule_date']);
    if ($date !== $lastDate):
        $lastDate = $date;
?>
<div class="history-date"><?= $date ?></div>
<?php endif; ?>

<div class="card history-card" onclick="location.href='/worklog.php?action=report&id=<?= $h['id'] ?>'">
    <div class="d-flex justify-between align-center">
        <strong><?= e($h['case_title']) ?></strong>
        <span class="badge badge-primary"><?= e($h['case_number']) ?></span>
    </div>
    <div class="history-meta">
        <?php if ($h['arrival_time']): ?>
        <span>到場 <?= format_datetime($h['arrival_time'], 'H:i') ?></span>
        <?php endif; ?>
        <?php if ($h['departure_time']): ?>
        <span>離場 <?= format_datetime($h['departure_time'], 'H:i') ?></span>
        <?php endif; ?>
    </div>
    <?php if ($h['work_description']): ?>
    <div class="history-desc"><?= e(mb_substr($h['work_description'], 0, 80)) ?><?= mb_strlen($h['work_description']) > 80 ? '...' : '' ?></div>
    <?php endif; ?>
</div>

<?php endforeach; ?>
<?php endif; ?>

<style>
.history-date {
    font-weight: 600; font-size: .9rem; color: var(--gray-700);
    padding: 8px 0 4px; margin-top: 8px;
    border-bottom: 1px solid var(--gray-200);
}
.history-card { cursor: pointer; padding: 12px; }
.history-card:hover { box-shadow: var(--shadow); }
.history-meta { font-size: .85rem; color: var(--gray-500); display: flex; gap: 12px; margin-top: 4px; }
.history-desc { font-size: .85rem; color: var(--gray-700); margin-top: 4px; }
</style>
