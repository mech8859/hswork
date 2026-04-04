<?php
if (($_GET['token'] ?? '') !== 'hswork2026') { die('invalid token'); }
header('Content-Type: text/plain; charset=utf-8');

$svgPath = __DIR__ . '/icons/icon.svg';
if (!file_exists($svgPath)) { die("icon.svg not found\n"); }

// 嘗試用 Imagick
if (class_exists('Imagick')) {
    foreach ([192, 512] as $size) {
        $im = new Imagick();
        $im->setResolution(300, 300);
        $im->readImage($svgPath);
        $im->resizeImage($size, $size, Imagick::FILTER_LANCZOS, 1);
        $im->setImageFormat('png');
        $path = __DIR__ . '/icons/icon-' . $size . '.png';
        $im->writeImage($path);
        $im->clear();
        echo "Created icon-{$size}.png (Imagick)\n";
    }
    echo "Done!\n";
    exit;
}

// Fallback: GD（改良版）
foreach ([192, 512] as $size) {
    $img = imagecreatetruecolor($size, $size);
    // 漸層背景（深藍→藍）
    for ($y = 0; $y < $size; $y++) {
        $ratio = $y / $size;
        $r = (int)(15 + $ratio * 15);
        $g = (int)(60 + $ratio * 40);
        $b = (int)(180 + $ratio * 50);
        $c = imagecolorallocate($img, $r, $g, min($b, 240));
        imageline($img, 0, $y, $size, $y, $c);
    }
    $white = imagecolorallocate($img, 255, 255, 255);
    $lightBlue = imagecolorallocate($img, 180, 210, 255);

    // 裝飾：半透明圓圈
    $cx = (int)($size / 2);
    $cy = (int)($size / 2);
    for ($r = (int)($size * 0.38); $r <= (int)($size * 0.4); $r++) {
        $cc = imagecolorallocatealpha($img, 255, 255, 255, 90);
        imageellipse($img, $cx, $cy, $r * 2, $r * 2, $cc);
    }

    // 用 imagecopyresampled 放大文字
    $text = 'HS';
    $builtin = 5;
    $charW = imagefontwidth($builtin);
    $charH = imagefontheight($builtin);
    $srcW = strlen($text) * $charW + 2;
    $srcH = $charH + 2;

    // 畫小圖
    $small = imagecreatetruecolor($srcW, $srcH);
    $sBlack = imagecolorallocate($small, 0, 0, 0);
    $sWhite = imagecolorallocate($small, 255, 255, 255);
    imagecolortransparent($small, $sBlack);
    imagefill($small, 0, 0, $sBlack);
    imagestring($small, $builtin, 1, 1, $text, $sWhite);

    // 放大貼到主圖
    $dstW = (int)($size * 0.5);
    $dstH = (int)($dstW * $srcH / $srcW);
    $dx = ($size - $dstW) / 2;
    $dy = ($size - $dstH) / 2 - $size * 0.06;
    imagecopyresampled($img, $small, (int)$dx, (int)$dy, 0, 0, $dstW, $dstH, $srcW, $srcH);
    imagedestroy($small);

    // 底部文字 HERSHUN
    $subText = 'HERSHUN';
    $subSrcW = strlen($subText) * imagefontwidth(2) + 2;
    $subSrcH = imagefontheight(2) + 2;
    $subSmall = imagecreatetruecolor($subSrcW, $subSrcH);
    imagecolortransparent($subSmall, imagecolorallocate($subSmall, 0, 0, 0));
    imagefill($subSmall, 0, 0, imagecolorallocate($subSmall, 0, 0, 0));
    imagestring($subSmall, 2, 1, 1, $subText, imagecolorallocate($subSmall, 180, 210, 255));
    $subDstW = (int)($size * 0.35);
    $subDstH = (int)($subDstW * $subSrcH / $subSrcW);
    $subDx = ($size - $subDstW) / 2;
    $subDy = $dy + $dstH + $size * 0.03;
    imagecopyresampled($img, $subSmall, (int)$subDx, (int)$subDy, 0, 0, $subDstW, $subDstH, $subSrcW, $subSrcH);
    imagedestroy($subSmall);

    // 底部裝飾線
    $lineColor = imagecolorallocatealpha($img, 255, 255, 255, 80);
    imagesetthickness($img, max(1, (int)($size * 0.005)));
    imageline($img, (int)($size * 0.25), (int)($size * 0.83), (int)($size * 0.75), (int)($size * 0.83), $lineColor);

    $path = __DIR__ . '/icons/icon-' . $size . '.png';
    if (!is_dir(__DIR__ . '/icons')) mkdir(__DIR__ . '/icons', 0755, true);
    imagepng($img, $path, 9);
    imagedestroy($img);
    echo "Created icon-{$size}.png ({$size}x{$size})\n";
}
echo "Done!\n";
echo "Done!\n";
