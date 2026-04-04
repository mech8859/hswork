<div class="d-flex justify-between align-center flex-wrap gap-1 mb-2">
    <h2>修改價格</h2>
    <a href="/products.php?action=view&id=<?= $product['id'] ?>" class="btn btn-outline btn-sm">取消</a>
</div>

<div class="card">
    <div class="card-header">
        <?= e($product['name']) ?>
        <?php if ($product['model']): ?>
        <span class="text-muted" style="font-weight:400"> - <?= e($product['model']) ?></span>
        <?php endif; ?>
    </div>

    <div style="display:flex;gap:16px;align-items:flex-start;padding:0 16px">
        <?php if ($product['image']): ?>
        <img src="<?= e($product['image']) ?>" alt="" style="width:80px;height:80px;object-fit:contain;border-radius:6px;background:#f8f9fa">
        <?php endif; ?>
        <div style="flex:1">
            <form method="POST" action="/products.php?action=edit_price&id=<?= $product['id'] ?>">
                <?= csrf_field() ?>

                <div class="form-group">
                    <label>售價</label>
                    <input type="number" name="price" class="form-control" value="<?= (float)$product['price'] ?>" step="1" min="0" required>
                </div>
                <div class="form-group">
                    <label>成本</label>
                    <input type="number" name="cost" class="form-control" value="<?= (float)$product['cost'] ?>" step="1" min="0" required>
                </div>
                <div class="form-group">
                    <label>零售價</label>
                    <input type="number" name="retail_price" class="form-control" value="<?= (float)$product['retail_price'] ?>" step="1" min="0" required>
                </div>
                <div class="form-group">
                    <label>工資</label>
                    <input type="number" name="labor_cost" class="form-control" value="<?= $product['labor_cost'] ? (float)$product['labor_cost'] : '' ?>" step="1" min="0" placeholder="選填">
                </div>

                <div class="d-flex gap-1 mt-2" style="padding-bottom:16px">
                    <button type="submit" class="btn btn-primary">儲存</button>
                    <a href="/products.php?action=view&id=<?= $product['id'] ?>" class="btn btn-outline">取消</a>
                </div>
            </form>
        </div>
    </div>
</div>
