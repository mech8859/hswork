<div class="d-flex justify-between align-center flex-wrap gap-1 mb-2">
    <h2>產品詳情</h2>
    <div class="d-flex gap-1">
        <?php if (Auth::hasPermission('products.manage') || in_array(Auth::user()['role'], array('boss','manager'))): ?>
        <a href="/products.php?action=edit&id=<?= $product['id'] ?>" class="btn btn-primary btn-sm">編輯</a>
        <?php endif; ?>
        <a href="/products.php" class="btn btn-outline btn-sm">返回產品目錄</a>
    </div>
</div>

<div class="product-detail-layout">
    <!-- 左側圖片 -->
    <div class="product-detail-img">
        <div class="main-image">
            <?php if ($product['image']): ?>
            <img id="mainImg" src="<?= e($product['image']) ?>" alt="<?= e($product['name']) ?>" onerror="this.src='data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%2260%22 x=%2230%22 font-size=%2250%22>📦</text></svg>'">
            <?php else: ?>
            <div style="font-size:5rem;text-align:center;padding:40px;opacity:.3">📦</div>
            <?php endif; ?>
        </div>

        <?php
        $gallery = $product['gallery'] ? json_decode($product['gallery'], true) : array();
        if (count($gallery) > 1): ?>
        <div class="gallery-thumbs">
            <?php foreach ($gallery as $idx => $img): ?>
            <img src="<?= e($img) ?>" alt="圖片<?= $idx + 1 ?>"
                 class="gallery-thumb <?= $idx === 0 ? 'active' : '' ?>"
                 onclick="switchImage(this, '<?= e($img) ?>')" loading="lazy">
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- 右側資訊 -->
    <div class="product-detail-info">
        <div class="card">
            <div class="card-header">基本資訊</div>
            <div class="detail-grid">
                <div class="detail-row">
                    <span class="detail-label">產品名稱</span>
                    <span class="detail-value" style="font-weight:600;font-size:1.05rem"><?= e($product['name']) ?></span>
                </div>
                <?php if ($product['model']): ?>
                <div class="detail-row">
                    <span class="detail-label">型號</span>
                    <span class="detail-value"><?= e($product['model']) ?></span>
                </div>
                <?php endif; ?>
                <div class="detail-row">
                    <span class="detail-label">廠商型號</span>
                    <span class="detail-value"><?= e(!empty($product['vendor_model']) ? $product['vendor_model'] : '-') ?></span>
                </div>
                <?php if ($product['brand']): ?>
                <div class="detail-row">
                    <span class="detail-label">品牌</span>
                    <span class="detail-value"><?= e($product['brand']) ?></span>
                </div>
                <?php endif; ?>
                <?php if ($product['supplier']): ?>
                <div class="detail-row">
                    <span class="detail-label">供應商</span>
                    <span class="detail-value"><?= e($product['supplier']) ?></span>
                </div>
                <?php endif; ?>
                <?php if (!empty($product['category_path'])): ?>
                <div class="detail-row">
                    <span class="detail-label">分類</span>
                    <span class="detail-value"><?= e($product['category_path']) ?></span>
                </div>
                <?php endif; ?>
                <?php if ($product['unit']): ?>
                <div class="detail-row">
                    <span class="detail-label">單位</span>
                    <span class="detail-value"><?= e($product['unit']) ?></span>
                </div>
                <?php endif; ?>
                <?php if ($product['warranty_text'] && $product['warranty_text'] !== '0'): ?>
                <div class="detail-row">
                    <span class="detail-label">保固</span>
                    <span class="detail-value"><?= e($product['warranty_text']) ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card mt-1">
            <div class="card-header">庫存資訊</div>
            <div class="price-grid">
                <div class="price-item">
                    <span class="price-label">總庫存</span>
                    <span class="price-value" style="color:<?= (int)($product['total_stock'] ?? 0) > 0 ? 'var(--success)' : 'var(--gray-400)' ?>"><?= (int)($product['total_stock'] ?? 0) ?></span>
                </div>
                <div class="price-item">
                    <span class="price-label">可用數量</span>
                    <span class="price-value" style="color:<?= (int)($product['total_available'] ?? 0) > 0 ? 'var(--success)' : 'var(--gray-400)' ?>"><?= (int)($product['total_available'] ?? 0) ?></span>
                </div>
            </div>
            <div style="text-align:center;padding:0 16px 12px">
                <a href="/inventory.php?action=view&product_id=<?= $product['id'] ?>" class="btn btn-outline btn-sm" style="font-size:.8rem">查看各倉庫明細</a>
            </div>
        </div>

        <div class="card mt-1">
            <div class="card-header">價格資訊</div>
            <div class="price-grid">
                <div class="price-item">
                    <span class="price-label">售價</span>
                    <span class="price-value price-main">$<?= number_format((float)$product['price']) ?></span>
                </div>
                <div class="price-item">
                    <span class="price-label">成本</span>
                    <span class="price-value">$<?= number_format((float)$product['cost']) ?></span>
                </div>
                <div class="price-item">
                    <span class="price-label">零售價</span>
                    <span class="price-value">$<?= number_format((float)$product['retail_price']) ?></span>
                </div>
                <?php if ($product['labor_cost']): ?>
                <div class="price-item">
                    <span class="price-label">工資</span>
                    <span class="price-value">$<?= number_format((float)$product['labor_cost']) ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($product['specifications'] || $product['description']): ?>
        <div class="card mt-1">
            <div class="card-header">規格說明</div>
            <div style="padding:0 16px 16px">
                <?php if ($product['specifications']): ?>
                <div style="margin-top:8px">
                    <strong>規格：</strong><?= e($product['specifications']) ?>
                </div>
                <?php endif; ?>
                <?php if ($product['description']): ?>
                <div style="margin-top:8px">
                    <strong>說明：</strong><?= nl2br(e($product['description'])) ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($product['datasheet']): ?>
        <div class="mt-1">
            <a href="<?= e($product['datasheet']) ?>" target="_blank" rel="noopener" class="btn btn-outline btn-sm">
                📄 規格書下載
            </a>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
function switchImage(thumb, src) {
    document.getElementById('mainImg').src = src;
    var thumbs = document.querySelectorAll('.gallery-thumb');
    for (var i = 0; i < thumbs.length; i++) thumbs[i].classList.remove('active');
    thumb.classList.add('active');
}
</script>

<style>
.product-detail-layout {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
    align-items: start;
}
.main-image {
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0,0,0,.1);
    overflow: hidden;
    aspect-ratio: 1;
    display: flex;
    align-items: center;
    justify-content: center;
}
.main-image img {
    max-width: 100%;
    max-height: 100%;
    object-fit: contain;
    padding: 16px;
}
.gallery-thumbs {
    display: flex;
    gap: 8px;
    margin-top: 8px;
    overflow-x: auto;
}
.gallery-thumb {
    width: 60px;
    height: 60px;
    object-fit: cover;
    border-radius: 6px;
    border: 2px solid transparent;
    cursor: pointer;
    opacity: .6;
    transition: all .2s;
}
.gallery-thumb.active,
.gallery-thumb:hover {
    border-color: var(--primary);
    opacity: 1;
}
.detail-grid { padding: 0 16px 12px; }
.detail-row {
    display: flex;
    padding: 8px 0;
    border-bottom: 1px solid var(--gray-100);
}
.detail-row:last-child { border-bottom: none; }
.detail-label {
    width: 80px;
    flex-shrink: 0;
    color: var(--gray-500);
    font-size: .85rem;
}
.detail-value { flex: 1; }
.price-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 12px;
    padding: 12px 16px 16px;
}
.price-item { text-align: center; }
.price-label {
    display: block;
    font-size: .75rem;
    color: var(--gray-500);
    margin-bottom: 2px;
}
.price-value {
    font-size: 1.1rem;
    font-weight: 600;
}
.price-main { color: var(--primary); font-size: 1.3rem; }

@media (max-width: 767px) {
    .product-detail-layout {
        grid-template-columns: 1fr;
    }
}
</style>
