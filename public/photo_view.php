<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>照片檢視</title>
<script src="/js/panzoom.min.js"></script>
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body { background:#000; overflow:hidden; height:100vh; width:100vw; }
#wrap { width:100%; height:100%; display:flex; align-items:center; justify-content:center; overflow:hidden; }
#wrap img { max-width:100%; max-height:100%; }
.btn { position:fixed; z-index:10; background:rgba(0,0,0,.5); color:#fff; border:none; cursor:pointer; border-radius:50%; display:flex; align-items:center; justify-content:center; }
.btn-close { top:12px; right:12px; width:44px; height:44px; font-size:1.8rem; line-height:1; }
.btn-prev, .btn-next { top:50%; transform:translateY(-50%); width:44px; height:44px; font-size:1.8rem; }
.btn-prev { left:8px; }
.btn-next { right:8px; }
.counter { position:fixed; bottom:16px; left:50%; transform:translateX(-50%); color:#fff; background:rgba(0,0,0,.5); padding:4px 14px; border-radius:12px; font-size:.9rem; z-index:10; }
.hint { position:fixed; bottom:48px; left:50%; transform:translateX(-50%); color:rgba(255,255,255,.4); font-size:.75rem; z-index:10; }
</style>
</head>
<body>
<?php
$src = isset($_GET['src']) ? $_GET['src'] : '';
$imagesJson = isset($_GET['images']) ? $_GET['images'] : '';
$idx = isset($_GET['idx']) ? (int)$_GET['idx'] : 0;
$back = isset($_GET['back']) ? $_GET['back'] : '';
$images = $imagesJson ? json_decode($imagesJson, true) : array($src);
if (!is_array($images) || empty($images)) $images = array($src);
if ($idx < 0 || $idx >= count($images)) $idx = 0;
$currentSrc = $images[$idx];
$total = count($images);
?>

<button class="btn btn-close" onclick="goBack()">&times;</button>
<?php if ($total > 1): ?>
<button class="btn btn-prev" onclick="nav(-1)">&lsaquo;</button>
<button class="btn btn-next" onclick="nav(1)">&rsaquo;</button>
<div class="counter"><?= $idx + 1 ?> / <?= $total ?></div>
<?php endif; ?>
<div class="hint">雙指縮放 · 單指拖曳</div>

<div id="wrap">
    <img id="photo" src="<?= htmlspecialchars($currentSrc, ENT_QUOTES, 'UTF-8') ?>" alt="照片">
</div>

<script>
var images = <?= json_encode($images) ?>;
var idx = <?= $idx ?>;
var backUrl = <?= json_encode($back) ?>;

// Panzoom
var img = document.getElementById('photo');
var pz;
function initPz() {
    if (pz) { try { pz.destroy(); } catch(e) {} }
    pz = Panzoom(img, { maxScale: 8, minScale: 1, contain: 'outside' });
    document.getElementById('wrap').addEventListener('wheel', pz.zoomWithWheel);
}
if (img.complete) initPz();
else img.onload = initPz;

function nav(dir) {
    idx += dir;
    if (idx < 0) idx = images.length - 1;
    if (idx >= images.length) idx = 0;
    var params = 'idx=' + idx + '&images=' + encodeURIComponent(JSON.stringify(images));
    if (backUrl) params += '&back=' + encodeURIComponent(backUrl);
    location.replace('/photo_view.php?' + params);
}

function goBack() {
    if (backUrl) location.href = backUrl;
    else history.back();
}
</script>
</body>
</html>
