<div class="d-flex justify-between align-center flex-wrap gap-1 mb-2">
    <h2>請假管理</h2>
    <a href="/leaves.php?action=create" class="btn btn-primary btn-sm">+ 申請請假</a>
</div>

<!-- 篩選 -->
<div class="card">
    <form method="GET" action="/leaves.php" class="filter-form">
        <input type="hidden" name="action" value="list">
        <div class="filter-row">
            <div class="form-group">
                <label>月份</label>
                <input type="month" name="month" class="form-control" value="<?= e($filters['month']) ?>">
            </div>
            <?php if ($canManage && !empty($users)): ?>
            <div class="form-group">
                <label>人員</label>
                <select name="user_id" class="form-control">
                    <option value="">全部</option>
                    <?php foreach ($users as $u): ?>
                    <option value="<?= $u['id'] ?>" <?= $filters['user_id'] == $u['id'] ? 'selected' : '' ?>><?= e($u['real_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <div class="form-group">
                <label>狀態</label>
                <select name="status" class="form-control">
                    <option value="">全部</option>
                    <option value="pending" <?= $filters['status'] === 'pending' ? 'selected' : '' ?>>待審核</option>
                    <option value="approved" <?= $filters['status'] === 'approved' ? 'selected' : '' ?>>已核准</option>
                    <option value="rejected" <?= $filters['status'] === 'rejected' ? 'selected' : '' ?>>已駁回</option>
                </select>
            </div>
            <div class="form-group" style="align-self:flex-end">
                <button type="submit" class="btn btn-primary btn-sm">搜尋</button>
                <a href="/leaves.php?action=list" class="btn btn-outline btn-sm">清除</a>
            </div>
        </div>
    </form>
</div>

<div class="card">
    <?php if (empty($leaves)): ?>
        <p class="text-muted text-center mt-2">目前無請假記錄</p>
    <?php else: ?>

    <!-- 手機版 -->
    <div class="leave-cards show-mobile">
        <?php foreach ($leaves as $lv): ?>
        <div class="staff-card">
            <div class="d-flex justify-between align-center">
                <strong><?= e($lv['real_name']) ?></strong>
                <?php if ($lv['status'] === 'pending'): ?>
                    <span class="badge badge-warning">待審核</span>
                <?php elseif ($lv['status'] === 'approved'): ?>
                    <span class="badge badge-success">已核准</span>
                <?php else: ?>
                    <span class="badge badge-danger">已駁回</span>
                <?php endif; ?>
            </div>
            <div class="staff-card-meta">
                <span><?= e(LeaveModel::leaveTypeLabel($lv['leave_type'])) ?></span>
                <span><?= e($lv['start_date']) ?> ~ <?= e($lv['end_date']) ?></span>
                <span><?= (int)$lv['days'] ?>天</span>
            </div>
            <?php if ($canManage && $lv['status'] === 'pending'): ?>
            <div class="d-flex gap-1 mt-1">
                <a href="/leaves.php?action=approve&id=<?= $lv['id'] ?>&csrf_token=<?= e(Session::getCsrfToken()) ?>"
                   class="btn btn-success btn-sm" onclick="return confirm('確定核准?')">核准</a>
                <button type="button" class="btn btn-danger btn-sm" onclick="showRejectModal(<?= $lv['id'] ?>)">駁回</button>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- 桌面版 -->
    <div class="table-responsive hide-mobile">
        <table class="table">
            <thead>
                <tr>
                    <th>申請人</th>
                    <th>據點</th>
                    <th>假別</th>
                    <th>開始日</th>
                    <th>結束日</th>
                    <th>天數</th>
                    <th>原因</th>
                    <th>狀態</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($leaves as $lv): ?>
                <tr>
                    <td><?= e($lv['real_name']) ?></td>
                    <td><?= e($lv['branch_name']) ?></td>
                    <td><?= e(LeaveModel::leaveTypeLabel($lv['leave_type'])) ?></td>
                    <td><?= e($lv['start_date']) ?><?= !empty($lv['start_time']) ? ' <small>' . e(substr($lv['start_time'], 0, 5)) . '</small>' : '' ?></td>
                    <td><?= e($lv['end_date']) ?><?= !empty($lv['end_time']) ? ' <small>' . e(substr($lv['end_time'], 0, 5)) . '</small>' : '' ?></td>
                    <td class="text-center"><?= (int)$lv['days'] ?></td>
                    <td><?= e(mb_substr($lv['reason'] ?: '-', 0, 30)) ?></td>
                    <td>
                        <?php if ($lv['status'] === 'pending'): ?>
                            <span class="badge badge-warning">待審核</span>
                        <?php elseif ($lv['status'] === 'approved'): ?>
                            <span class="badge badge-success">已核准</span>
                        <?php else: ?>
                            <span class="badge badge-danger">已駁回</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="d-flex gap-1">
                            <?php if ($canManage && $lv['status'] === 'pending'): ?>
                            <a href="/leaves.php?action=approve&id=<?= $lv['id'] ?>&csrf_token=<?= e(Session::getCsrfToken()) ?>"
                               class="btn btn-success btn-sm" onclick="return confirm('確定核准?')">核准</a>
                            <button type="button" class="btn btn-danger btn-sm" onclick="showRejectModal(<?= $lv['id'] ?>)">駁回</button>
                            <?php endif; ?>
                            <?php if ($lv['user_id'] == Auth::id() && $lv['status'] === 'pending'): ?>
                            <a href="/leaves.php?action=delete&id=<?= $lv['id'] ?>&csrf_token=<?= e(Session::getCsrfToken()) ?>"
                               class="btn btn-outline btn-sm" onclick="return confirm('確定刪除?')">刪除</a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- 駁回對話框 -->
<div id="rejectModal" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,.5); z-index:9999; display:none; align-items:center; justify-content:center;">
    <div class="card" style="max-width:400px; width:90%; margin:auto; margin-top:20vh;">
        <div class="card-header">駁回原因</div>
        <form method="POST" action="/leaves.php?action=reject">
            <?= csrf_field() ?>
            <input type="hidden" name="id" id="rejectId">
            <div class="form-group">
                <textarea name="reject_reason" class="form-control" rows="3" placeholder="請輸入駁回原因"></textarea>
            </div>
            <div class="d-flex gap-1">
                <button type="submit" class="btn btn-danger">確定駁回</button>
                <button type="button" class="btn btn-outline" onclick="hideRejectModal()">取消</button>
            </div>
        </form>
    </div>
</div>

<script>
function showRejectModal(id) {
    document.getElementById('rejectId').value = id;
    document.getElementById('rejectModal').style.display = 'flex';
}
function hideRejectModal() {
    document.getElementById('rejectModal').style.display = 'none';
}
</script>

<style>
.filter-row { display: flex; flex-wrap: wrap; gap: 12px; align-items: flex-end; }
.filter-row .form-group { flex: 1; min-width: 130px; margin-bottom: 0; }
.leave-cards { display: flex; flex-direction: column; gap: 8px; }
.show-mobile { display: flex; }
.hide-mobile { display: none; }
@media (min-width: 768px) {
    .show-mobile { display: none !important; }
    .hide-mobile { display: block !important; }
}
</style>
