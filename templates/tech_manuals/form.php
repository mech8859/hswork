<?php $isEdit = !empty($record); ?>
<div class="d-flex justify-between align-center flex-wrap gap-1 mb-2">
    <h2><?= $isEdit ? '編輯技術手冊' : '新增技術手冊' ?></h2>
    <a href="/tech_manuals.php" class="btn btn-outline btn-sm">← 返回列表</a>
</div>

<div class="card">
    <form method="POST" action="/tech_manuals.php?action=store" enctype="multipart/form-data">
        <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
        <?php if ($isEdit): ?>
        <input type="hidden" name="id" value="<?= (int)$record['id'] ?>">
        <?php endif; ?>

        <div class="form-group">
            <label>標題 <span style="color:#c62828">*</span></label>
            <input type="text" name="title" class="form-control" required maxlength="200"
                   value="<?= e($isEdit ? $record['title'] : '') ?>"
                   placeholder="例：Aiphone JF-2MED 對講機安裝手冊">
        </div>

        <div class="d-flex gap-1 flex-wrap">
            <div class="form-group" style="flex:1;min-width:200px">
                <label>設備類型</label>
                <input type="text" name="category" class="form-control" list="cat-list" maxlength="50"
                       value="<?= e($isEdit ? $record['category'] : '') ?>"
                       placeholder="對講機 / 監視器 / 電子鎖 / 火警 / 網路...">
                <datalist id="cat-list">
                    <?php foreach ($categories as $c): ?>
                    <option value="<?= e($c) ?>">
                    <?php endforeach; ?>
                </datalist>
            </div>
            <div class="form-group" style="flex:1;min-width:200px">
                <label>品牌</label>
                <input type="text" name="brand" class="form-control" list="brand-list" maxlength="50"
                       value="<?= e($isEdit ? $record['brand'] : '') ?>"
                       placeholder="Aiphone / Panasonic / Hikvision...">
                <datalist id="brand-list">
                    <?php foreach ($brands as $b): ?>
                    <option value="<?= e($b) ?>">
                    <?php endforeach; ?>
                </datalist>
            </div>
            <div class="form-group" style="flex:1;min-width:200px">
                <label>型號</label>
                <input type="text" name="model" class="form-control" maxlength="100"
                       value="<?= e($isEdit ? $record['model'] : '') ?>"
                       placeholder="例：JF-2MED">
            </div>
        </div>

        <div class="form-group">
            <label>說明</label>
            <textarea name="description" class="form-control" rows="3" placeholder="備註：施工重點、常見問題、接線注意事項..."><?= e($isEdit ? $record['description'] : '') ?></textarea>
        </div>

        <div class="form-group">
            <label>關鍵字標籤</label>
            <input type="text" name="tags" class="form-control" maxlength="255"
                   value="<?= e($isEdit ? $record['tags'] : '') ?>"
                   placeholder="逗號分隔，例：接線圖,RS485,遠端監控">
            <small class="text-muted">會出現在搜尋結果裡</small>
        </div>

        <div class="form-group">
            <label>檔案 <?= $isEdit ? '（如不換檔可留空）' : '<span style="color:#c62828">*</span>' ?></label>
            <input type="file" name="file" class="form-control" accept=".pdf,.jpg,.jpeg,.png" <?= $isEdit ? '' : 'required' ?>>
            <small class="text-muted">支援 PDF / JPG / PNG，上限 20MB</small>
            <?php if ($isEdit && !empty($record['file_name'])): ?>
            <div class="mt-1" style="font-size:.9rem">
                目前檔案：
                <a href="/tech_manuals.php?action=download&id=<?= (int)$record['id'] ?>" target="_blank"><?= e($record['file_name']) ?></a>
                <span class="text-muted">(<?= e(round(($record['file_size'] ?? 0) / 1024) . ' KB') ?>)</span>
            </div>
            <?php endif; ?>
        </div>

        <div class="mt-2">
            <button type="submit" class="btn btn-primary">儲存</button>
            <a href="/tech_manuals.php" class="btn btn-outline">取消</a>
        </div>
    </form>
</div>
