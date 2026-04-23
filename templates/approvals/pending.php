<div class="d-flex justify-between align-center mb-2">
    <h2>待簽核</h2>
    <div class="d-flex gap-1">
        <a href="/approvals.php?action=history" class="btn btn-outline btn-sm">簽核紀錄</a>
        <?php if ($canManageRules): ?>
        <a href="/approvals.php?action=settings" class="btn btn-outline btn-sm">簽核設定</a>
        <?php endif; ?>
    </div>
</div>

<?php if (empty($pendingList)): ?>
<div class="card">
    <p class="text-muted text-center" style="padding:40px">目前沒有待簽核的項目 ✅</p>
</div>
<?php else: ?>
<div class="card">
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>模組</th>
                    <th>單據</th>
                    <th>金額</th>
                    <th>送簽人</th>
                    <th>送簽時間</th>
                    <th style="width:100px">操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pendingList as $item):
                    $info = $item['target_info'];
                ?>
                <tr>
                    <td>
                        <span class="badge badge-primary"><?= e(ApprovalModel::moduleLabel($item['module'])) ?></span>
                        <?php if ($item['module'] === 'case_completion'): ?>
                        <br><small class="text-muted">第<?= (int)$item['level_order'] ?>關<?= $item['level_order'] == 1 ? '(工程主管)' : '(會計確認)' ?></small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (!empty($info['url'])): ?>
                        <a href="<?= e($info['url']) ?>"><?= e($info['label'] ?? '-') ?></a>
                        <?php else: ?>
                        <?= e($info['label'] ?? $item['module'] . ' #' . $item['target_id']) ?>
                        <?php endif; ?>
                        <?php if (!empty($item['comment'])): ?>
                        <div style="font-size:.8rem;color:#c5221f;margin-top:4px;padding:4px 8px;background:#fff3e0;border-left:3px solid #ff9800;border-radius:3px;white-space:pre-wrap"><?= e($item['comment']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="text-right"><?= !empty($info['amount']) ? '$' . number_format($info['amount']) : '-' ?></td>
                    <td><?= e($item['submitter_name'] ?? '-') ?></td>
                    <td><?= e(substr($item['submitted_at'], 0, 16)) ?></td>
                    <td>
                        <?php if (in_array($item['module'], array('leaves', 'overtime'))): ?>
                        <button type="button" class="btn btn-success btn-sm" onclick="approveFlow(<?= (int)$item['id'] ?>, '<?= e($item['module']) ?>', <?= (int)$item['target_id'] ?>)">✓ 核准</button>
                        <button type="button" class="btn btn-danger btn-sm" onclick="rejectFlow(<?= (int)$item['id'] ?>)">✗ 駁回</button>
                        <?php elseif (!empty($info['url'])): ?>
                        <a href="<?= e($info['url']) ?>" class="btn btn-primary btn-sm">進入審核</a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- 駁回 Modal -->
<div id="rejectModal" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,.5);z-index:9999;align-items:center;justify-content:center">
    <div class="card" style="width:400px;margin:0">
        <div class="card-header">駁回原因</div>
        <form method="POST" action="/approvals.php?action=reject">
            <?= csrf_field() ?>
            <input type="hidden" name="flow_id" id="rejectFlowId">
            <div class="form-group" style="padding:12px 16px">
                <textarea name="comment" class="form-control" rows="3" placeholder="請輸入駁回原因（選填）"></textarea>
            </div>
            <div style="padding:0 16px 12px;display:flex;gap:8px;justify-content:flex-end">
                <button type="button" class="btn btn-outline btn-sm" onclick="document.getElementById('rejectModal').style.display='none'">取消</button>
                <button type="submit" class="btn btn-danger btn-sm">確定駁回</button>
            </div>
        </form>
    </div>
</div>

<script>
function approveFlow(flowId, module, targetId) {
    if (!confirm('確定核准此請假單？')) return;
    var f = document.createElement('form');
    f.method = 'POST';
    f.action = '/approvals.php?action=approve';
    f.style.display = 'none';
    var fields = { csrf_token: '<?= e(Session::getCsrfToken()) ?>', flow_id: flowId, module: module, target_id: targetId };
    for (var k in fields) {
        var i = document.createElement('input'); i.type = 'hidden'; i.name = k; i.value = fields[k]; f.appendChild(i);
    }
    document.body.appendChild(f); f.submit();
}
function rejectFlow(flowId) {
    document.getElementById('rejectFlowId').value = flowId;
    document.getElementById('rejectModal').style.display = 'flex';
}
</script>
