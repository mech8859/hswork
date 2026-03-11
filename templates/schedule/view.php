<?php
$statusLabels = ['planned'=>'已規劃','confirmed'=>'已確認','in_progress'=>'施工中','completed'=>'已完工','cancelled'=>'已取消'];
$statusBadge = ['planned'=>'primary','confirmed'=>'info','in_progress'=>'warning','completed'=>'success','cancelled'=>'danger'];
?>

<div class="d-flex justify-between align-center flex-wrap gap-1 mb-2">
    <div>
        <h2>排工詳情</h2>
        <span class="badge badge-<?= $statusBadge[$schedule['status']] ?? 'primary' ?>"><?= e($statusLabels[$schedule['status']] ?? $schedule['status']) ?></span>
    </div>
    <div class="d-flex gap-1">
        <?php if (Auth::hasPermission('schedule.manage')): ?>
        <a href="/schedule.php?action=edit&id=<?= $schedule['id'] ?>" class="btn btn-primary btn-sm">編輯</a>
        <a href="/schedule.php?action=delete&id=<?= $schedule['id'] ?>&csrf_token=<?= e(Session::getCsrfToken()) ?>"
           class="btn btn-danger btn-sm" onclick="return confirm('確定刪除此排工?')">刪除</a>
        <?php endif; ?>
        <a href="/schedule.php" class="btn btn-outline btn-sm">返回行事曆</a>
    </div>
</div>

<div class="card">
    <div class="card-header">排工資料</div>
    <div class="detail-grid">
        <div class="detail-item">
            <span class="detail-label">案件</span>
            <span class="detail-value"><a href="/cases.php?action=view&id=<?= $schedule['case_id'] ?>"><?= e($schedule['case_number']) ?> - <?= e($schedule['case_title']) ?></a></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">施工日期</span>
            <span class="detail-value"><?= format_date($schedule['schedule_date']) ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">施工地址</span>
            <span class="detail-value"><?= e($schedule['address'] ?? '-') ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">第幾次施工</span>
            <span class="detail-value"><?= $schedule['visit_number'] ?> / <?= $schedule['total_visits'] ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">車輛</span>
            <span class="detail-value"><?= $schedule['plate_number'] ? e($schedule['plate_number']) . ' (' . e($schedule['vehicle_type']) . ')' : '未指派' ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">據點</span>
            <span class="detail-value"><?= e($schedule['branch_name']) ?></span>
        </div>
    </div>
    <?php if ($schedule['note']): ?>
    <div class="mt-1"><span class="detail-label">備註</span><p><?= nl2br(e($schedule['note'])) ?></p></div>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card-header">施工人員</div>
    <?php if (empty($schedule['engineers'])): ?>
        <p class="text-muted">未指派工程師</p>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table">
            <thead><tr><th>姓名</th><th>電話</th><th>主工程師</th><th>強制加入</th></tr></thead>
            <tbody>
                <?php foreach ($schedule['engineers'] as $eng): ?>
                <tr>
                    <td><a href="/staff.php?action=view&id=<?= $eng['user_id'] ?>"><?= e($eng['real_name']) ?></a></td>
                    <td><a href="tel:<?= e($eng['phone'] ?? '') ?>"><?= e($eng['phone'] ?? '-') ?></a></td>
                    <td><?= $eng['is_lead'] ? '<span class="badge badge-primary">主工程師</span>' : '-' ?></td>
                    <td>
                        <?php if ($eng['is_override']): ?>
                        <span class="badge badge-warning">強制加入</span>
                        <?php if ($eng['override_reason']): ?><br><small><?= e($eng['override_reason']) ?></small><?php endif; ?>
                        <?php else: ?>-<?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<style>
.detail-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 8px; }
.detail-item { display: flex; flex-direction: column; }
.detail-label { font-size: .8rem; color: var(--gray-500); }
@media (max-width: 767px) { .detail-grid { grid-template-columns: 1fr; } }
</style>
