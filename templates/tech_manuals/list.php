<div class="d-flex justify-between align-center flex-wrap gap-1 mb-2">
    <h2>📖 技術手冊</h2>
    <div class="d-flex align-center gap-1">
        <span class="text-muted"><?= isset($result['total']) ? $result['total'] : count($records) ?> 筆</span>
        <?php if ($canManage): ?>
        <a href="/tech_manuals.php?action=create" class="btn btn-primary btn-sm">+ 新增</a>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <form method="GET" action="/tech_manuals.php" class="filter-form">
        <div class="filter-row">
            <div class="form-group">
                <label>設備類型</label>
                <select name="category" class="form-control">
                    <option value="">全部</option>
                    <?php foreach ($categories as $c): ?>
                    <option value="<?= e($c) ?>" <?= (!empty($filters['category']) && $filters['category'] === $c) ? 'selected' : '' ?>><?= e($c) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>品牌</label>
                <select name="brand" class="form-control">
                    <option value="">全部</option>
                    <?php foreach ($brands as $b): ?>
                    <option value="<?= e($b) ?>" <?= (!empty($filters['brand']) && $filters['brand'] === $b) ? 'selected' : '' ?>><?= e($b) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="flex:2;min-width:200px">
                <label>關鍵字</label>
                <input type="text" name="keyword" class="form-control" value="<?= e(!empty($filters['keyword']) ? $filters['keyword'] : '') ?>" placeholder="標題 / 型號 / 說明 / 標籤" autocomplete="off">
            </div>
            <div class="form-group" style="align-self:flex-end">
                <button type="submit" class="btn btn-primary btn-sm">搜尋</button>
                <a href="/tech_manuals.php" class="btn btn-outline btn-sm">清除</a>
            </div>
        </div>
    </form>
</div>

<div class="card">
    <?php if (empty($records)): ?>
        <p class="text-muted text-center mt-2">目前無資料</p>
    <?php else: ?>
    <div class="manual-grid">
        <?php foreach ($records as $r): ?>
        <?php
            $ext = strtolower($r['file_ext']);
            $isPdf = $ext === 'pdf';
            $isImg = in_array($ext, array('jpg', 'jpeg', 'png'), true);
            $icon = $isPdf ? '📄' : ($isImg ? '🖼️' : '📎');
            $sizeKB = (int)$r['file_size'] > 0 ? round($r['file_size'] / 1024) : 0;
            $sizeText = $sizeKB >= 1024 ? round($sizeKB / 1024, 1) . ' MB' : $sizeKB . ' KB';
            $previewUrl = '/tech_manuals.php?action=download&id=' . (int)$r['id'];
            $downloadUrl = $previewUrl . '&dl=1';
        ?>
        <div class="manual-card">
            <div class="manual-card-header">
                <a href="<?= e($previewUrl) ?>" target="_blank" class="manual-icon" title="點擊預覽"><?= $icon ?></a>
                <div class="manual-meta">
                    <?php if (!empty($r['category'])): ?><span class="manual-tag cat"><?= e($r['category']) ?></span><?php endif; ?>
                    <?php if (!empty($r['brand'])): ?><span class="manual-tag brand"><?= e($r['brand']) ?></span><?php endif; ?>
                </div>
            </div>
            <div class="manual-title">
                <a href="<?= e($previewUrl) ?>" target="_blank"><?= e($r['title']) ?></a>
            </div>
            <?php if (!empty($r['model'])): ?>
            <div class="manual-model">型號：<?= e($r['model']) ?></div>
            <?php endif; ?>
            <?php if (!empty($r['description'])): ?>
            <div class="manual-desc"><?= e(mb_strlen($r['description']) > 80 ? mb_substr($r['description'], 0, 78) . '…' : $r['description']) ?></div>
            <?php endif; ?>
            <?php if (!empty($r['tags'])): ?>
            <div class="manual-tags">
                <?php foreach (array_filter(array_map('trim', explode(',', $r['tags']))) as $t): ?>
                <span class="manual-tag kw">#<?= e($t) ?></span>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <div class="manual-footer">
                <span class="manual-size"><?= e($sizeText) ?></span>
                <span class="manual-time"><?= e(date('Y-m-d', strtotime($r['updated_at']))) ?></span>
                <div class="manual-actions">
                    <a href="<?= e($previewUrl) ?>" target="_blank" class="btn btn-outline btn-xs">預覽</a>
                    <a href="<?= e($downloadUrl) ?>" class="btn btn-outline btn-xs">下載</a>
                    <?php if ($canManage): ?>
                    <a href="/tech_manuals.php?action=edit&id=<?= (int)$r['id'] ?>" class="btn btn-outline btn-xs">編輯</a>
                    <form method="POST" action="/tech_manuals.php?action=delete" style="display:inline" onsubmit="return confirm('確定刪除「<?= e(addslashes($r['title'])) ?>」？')">
                        <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                        <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                        <button type="submit" class="btn btn-outline btn-xs" style="color:#c62828">刪除</button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <?php require __DIR__ . '/../layouts/pagination.php'; ?>
</div>

<style>
.filter-row { display: flex; flex-wrap: wrap; gap: 12px; align-items: flex-end; }
.filter-row .form-group { flex: 1; min-width: 130px; margin-bottom: 0; }

.manual-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 14px;
    padding: 4px;
}
.manual-card {
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    padding: 12px;
    background: #fff;
    transition: box-shadow .15s;
    display: flex;
    flex-direction: column;
    gap: 6px;
}
.manual-card:hover { box-shadow: 0 2px 10px rgba(0,0,0,.08); }
.manual-card-header { display: flex; align-items: flex-start; gap: 10px; }
.manual-icon { font-size: 28px; line-height: 1; text-decoration: none; }
.manual-meta { display: flex; flex-wrap: wrap; gap: 4px; flex: 1; }
.manual-tag {
    display: inline-block;
    padding: 2px 7px;
    border-radius: 12px;
    font-size: .72rem;
    line-height: 1.4;
}
.manual-tag.cat { background: #e3f2fd; color: #1565c0; }
.manual-tag.brand { background: #f3e5f5; color: #7b1fa2; }
.manual-tag.kw { background: #f5f5f5; color: #666; font-size: .7rem; }
.manual-title { font-weight: 600; font-size: .98rem; }
.manual-title a { color: #1a73e8; text-decoration: none; }
.manual-title a:hover { text-decoration: underline; }
.manual-model { font-size: .82rem; color: #555; }
.manual-desc { font-size: .82rem; color: #666; line-height: 1.45; }
.manual-tags { display: flex; flex-wrap: wrap; gap: 4px; }
.manual-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: .75rem;
    color: #888;
    margin-top: 4px;
    padding-top: 8px;
    border-top: 1px solid #f0f0f0;
    gap: 6px;
    flex-wrap: wrap;
}
.manual-actions { display: flex; gap: 4px; flex-wrap: wrap; margin-left: auto; }
.btn-xs { font-size: .72rem; padding: 2px 7px; }
@media (max-width: 600px) {
    .manual-grid { grid-template-columns: 1fr; }
}
</style>
