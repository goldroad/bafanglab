<?php
/**
 * bafanglab - 验证码图片生成
 * 版权所有：八方网域-无涯
 */
session_start();

// 生成4位数字验证码
$code = '';
for ($i = 0; $i < 4; $i++) {
    $code .= mt_rand(0, 9);
}
$_SESSION['captcha'] = $code;

header('Content-Type: image/png');

// 靶场故意设计：白底黑字、高对比度、低干扰、等宽字体（0 带点/斜线，与 o 易区分）=> 可被 OCR 轻易识别
$width  = 90;   // 调小宽度
$height = 32;
$img = imagecreatetruecolor($width, $height);
$bg    = imagecolorallocate($img, 255, 255, 255);  // 纯白背景，便于 OCR @vuln
$fg    = imagecolorallocate($img, 0, 0, 0);        // 纯黑字符，高对比度 @vuln
$noise = imagecolorallocate($img, 200, 200, 200);  // 浅灰干扰，几乎不影响 OCR

imagefilledrectangle($img, 0, 0, $width, $height, $bg);

// 极少量浅灰干扰线（不影响 OCR 识别）
for ($i = 0; $i < 2; $i++) { // @vuln
    imageline($img, mt_rand(0, $width), mt_rand(0, $height),
              mt_rand(0, $width), mt_rand(0, $height), $noise);
}
// 极少量浅灰干扰点
for ($i = 0; $i < 15; $i++) { // @vuln
    imagesetpixel($img, mt_rand(0, $width), mt_rand(0, $height), $noise);
}

// 选择等宽字体（Consolas 的 0 带点，DejaVu/Liberation 的 0 也与 o 有区分），便于 OCR 准确识别
$font = false;
foreach (array(
    'C:/Windows/Fonts/consola.ttf',
    'C:/Windows/Fonts/cour.ttf',
    '/usr/share/fonts/truetype/dejavu/DejaVuSansMono.ttf',
    '/usr/share/fonts/truetype/liberation/LiberationMono-Regular.ttf',
    '/usr/share/fonts/truetype/freefont/FreeMono.ttf',
) as $f) {
    if (is_file($f)) { $font = $f; break; }
}

if ($font !== false) {
    // TTF 等宽字体（Consolas 0 带点，OCR 不会把 0 误识为 o）
    imagefttext($img, 20, 0, 12, 26, $fg, $font, $code); // @vuln
} else {
    // 回退：内置字体5，手动给 0 加斜线避免被误识为 o
    $startX = 27;
    $startY = 8;
    $charW  = imagefontwidth(5);
    $charH  = imagefontheight(5);
    imagestring($img, 5, $startX, $startY, $code, $fg);
    for ($i = 0; $i < strlen($code); $i++) {
        if ($code[$i] === '0') {
            $x1 = $startX + $i * $charW + 1;
            $y1 = $startY + 1;
            $x2 = $x1 + $charW - 3;
            $y2 = $y1 + $charH - 3;
            imageline($img, $x1, $y1, $x2, $y2, $fg);
        }
    }
}

imagepng($img);
imagedestroy($img);
