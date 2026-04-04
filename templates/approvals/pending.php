<div class="d-flex justify-between align-center mb-2">
    <h2>待簽核</h2>
    <?php if ($canManageRules): ?>
    <a href="/approvals.php?action=settings" class="btn btn-outline btn-sm">簽核設定</a>
    <?php endif; ?>
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
                    </td>
                    <td class="text-right"><?= !empty($info['amount']) ? '$' . number_format($info['amount']) : '-' ?></td>
                    <td><?= e($item['submitter_name'] ?? '-') ?></td>
                    <td><?= e(substr($item['submitted_at'], 0, 16)) ?></td>
                    <td>
                        <?php if (!empty($info['url'])): ?>
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
