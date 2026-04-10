<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=10.0, user-scalable=yes">
<title>照片檢視</title>
<style>
* { margin:0; padding:0; }
body { background:#000; display:flex; align-items:center; justify-content:center; min-height:100vh; }
img { max-width:100%; max-height:100vh; display:block; }
.back-btn { position:fixed; top:12px; left:12px; background:rgba(255,255,255,.85); color:#333; border:none; border-radius:20px; padding:8px 16px; font-size:.9rem; cursor:pointer; z-index:10; box-shadow:0 2px 8px rgba(0,0,0,.3); }
</style>
</head>
<body>
<button class="back-btn" onclick="history.back()">← 返回</button>
<img src="<?= htmlspecialchars($_GET['src'] ?? '', ENT_QUOTES, 'UTF-8') ?>" alt="照片">
</body>
</html>
