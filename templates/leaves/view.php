<?php
$typeLabels = array(
    'annual' => '特休', 'personal' => '事假', 'sick' => '病假',
    'official' => '公假', 'day_off' => '補休', 'menstrual' => '生理假',
    'bereavement' => '喪假', 'marriage' => '婚假', 'maternity' => '產假',
    'paternity' => '陪產假', 'funeral' => '喪假', 'other' => '其他',
);
$typeLabel = isset($typeLabels[$leave['leave_type']]) ? $typeLabels[$leave['leave_type']] : $leave['leave_type'];
$statusLabel = $leave['status'] === 'approved' ? '已核准'
             : ($leave['status'] === 'rejected' ? '已駁回' : '待核准');
$statusClass = $leave['status'] === 'approved' ? 'badge-success'
             : ($leave['status'] === 'rejected' ? 'badge-danger' : 'badge-warning');
?>
<div class="d-flex justify-between align-center flex-wrap mb-2">
    <h2>請假單檢視 <span class="badge <?= $statusClass ?>"><?= $statusLabel ?></span></h2>
    <div class="d-flex gap-1 flex-wrap">
        <?php if ($leave['status'] === 'pending' && ($leave['user_id'] == Auth::id() || $canManage)): ?>
        <a href="/leaves.php?action=list#leave-<?= (int)$leave['id'] ?>" class="btn btn-outline btn-sm">編輯</a>
        <?php endif; ?>
        <a href="/leaves.php?action=list" class="btn btn-outline btn-sm">返回列表</a>
    </div>
</div>

<div class="card">
    <div class="card-header">基本資訊</div>
    <table class="table detail-table">
        <tr>
            <th style="width:120px">請假人員</th>
            <td><?= e($leave['real_name']) ?> <small style="color:#888">(<?= e($leave['branch_name']) ?>)</small></td>
            <th style="width:120px">假別</th>
            <td><?= e($typeLabel) ?></td>
        </tr>
        <tr>
            <th>開始日期</th>
            <td><?= e($leave['start_date']) ?><?= !empty($leave['start_time']) ? ' ' . substr($leave['start_time'], 0, 5) : '' ?></td>
            <th>結束日期</th>
            <td><?= e($leave['end_date']) ?><?= !empty($leave['end_time']) ? ' ' . substr($leave['end_time'], 0, 5) : '' ?></td>
        </tr>
        <tr>
            <th>天數</th>
            <td><strong style="color:#1565c0"><?= (int)$leave['days'] ?></strong> 天</td>
            <th>申請時間</th>
            <td><?= e($leave['created_at'] ?? '-') ?></td>
        </tr>
        <?php if (!empty($leave['reason'])): ?>
        <tr>
            <th>請假事由</th>
            <td colspan="3" style="white-space:pre-line"><?= e($leave['reason']) ?></td>
        </tr>
        <?php endif; ?>
    </table>
</div>

<div class="card">
    <div class="card-header">簽核資訊</div>
    <table class="table detail-table">
        <tr>
            <th style="width:120px">核准/駁回人</th>
            <td><?= e(!empty($leave['approved_by_name']) ? $leave['approved_by_name'] : '-') ?></td>
            <th style="width:120px">處理時間</th>
            <td><?= e(!empty($leave['approved_at']) ? $leave['approved_at'] : '-') ?></td>
        </tr>
        <?php if ($leave['status'] === 'rejected' && !empty($leave['reject_reason'])): ?>
        <tr>
            <th>駁回原因</th>
            <td colspan="3" style="color:#c62828;white-space:pre-line"><?= e($leave['reject_reason']) ?></td>
        </tr>
        <?php endif; ?>
    </table>
</div>

<style>
.detail-table th { background: #f9f9f9; text-align: left; padding: 8px 12px; font-weight: 600; }
.detail-table td { padding: 8px 12px; }
.badge { display: inline-block; padding: 2px 10px; border-radius: 10px; font-size: .8rem; font-weight: 500; vertical-align: middle; }
.badge-warning { background: #fff3e0; color: #e65100; }
.badge-success { background: #e8f5e9; color: #2e7d32; }
.badge-danger { background: #ffebee; color: #c62828; }
</style>
