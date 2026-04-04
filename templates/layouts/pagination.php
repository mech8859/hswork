<?php if (isset($result) && $result['lastPage'] > 1): ?>
<div class="pagination">
    <?php
    $lastPage = $result['lastPage'];
    $curPage = $result['page'];
    // 顯示頁碼範圍：前後各 4 頁
    $start = max(1, $curPage - 4);
    $end = min($lastPage, $curPage + 4);
    ?>
    <?php if ($curPage > 1): ?>
        <?php $qs = $_GET; $qs['page'] = $curPage - 1; ?>
        <a href="?<?= http_build_query($qs) ?>" class="btn btn-sm btn-outline">&laquo;</a>
    <?php endif; ?>
    <?php if ($start > 1): ?>
        <?php $qs = $_GET; $qs['page'] = 1; ?>
        <a href="?<?= http_build_query($qs) ?>" class="btn btn-sm btn-outline">1</a>
        <?php if ($start > 2): ?><span class="pagination-dots">...</span><?php endif; ?>
    <?php endif; ?>
    <?php for ($i = $start; $i <= $end; $i++): ?>
        <?php $qs = $_GET; $qs['page'] = $i; ?>
        <a href="?<?= http_build_query($qs) ?>" class="btn btn-sm <?= $i === $curPage ? 'btn-primary' : 'btn-outline' ?>"><?= $i ?></a>
    <?php endfor; ?>
    <?php if ($end < $lastPage): ?>
        <?php if ($end < $lastPage - 1): ?><span class="pagination-dots">...</span><?php endif; ?>
        <?php $qs = $_GET; $qs['page'] = $lastPage; ?>
        <a href="?<?= http_build_query($qs) ?>" class="btn btn-sm btn-outline"><?= $lastPage ?></a>
    <?php endif; ?>
    <?php if ($curPage < $lastPage): ?>
        <?php $qs = $_GET; $qs['page'] = $curPage + 1; ?>
        <a href="?<?= http_build_query($qs) ?>" class="btn btn-sm btn-outline">&raquo;</a>
    <?php endif; ?>
</div>
<style>
.pagination { display: flex; gap: 4px; justify-content: center; margin-top: 16px; flex-wrap: wrap; }
.pagination-dots { display: flex; align-items: center; padding: 0 4px; color: var(--gray-400); }
</style>
<?php endif; ?>
