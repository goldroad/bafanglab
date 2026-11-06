<?php
/**
 * bafanglab - 模块6d：上传型 XSS —— 上传文件访问入口
 * 版权所有：八方网域-无涯
 *
 * 靶场设计说明：
 * - 原样输出上传文件的内容，不转义、不消毒，这就是上传型 XSS 的触发点；
 * - 按扩展名决定 Content-Type：
 *     .html / .htm → text/html      浏览器直接解析并执行 JS
 *     .svg         → image/svg+xml  SVG 内的 <script> / onload 会执行
 *     .pdf         → text/html      靶场故意模拟 MIME 配置错误（内容嗅探）场景，
 *                                   让 PDF 里夹带的 HTML+JS 在浏览器执行；
 *                                   同一份 PDF 自带 /OpenAction，用 Adobe Reader 打开也会弹窗
 */
require_once dirname(dirname(__DIR__)) . '/config.php';

$allow_ext = array('html', 'htm', 'svg', 'pdf');
$types = array(
    'html' => 'text/html; charset=UTF-8',
    'htm'  => 'text/html; charset=UTF-8',
    'svg'  => 'image/svg+xml',
    'pdf'  => 'text/html; charset=UTF-8',
);

$name = isset($_GET['f']) ? basename(str_replace('\\', '/', (string)$_GET['f'])) : '';
$ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));
$dir  = realpath(UPLOAD_DIR . DIRECTORY_SEPARATOR . 'xss');

$path = ($name === '' || !in_array($ext, $allow_ext, true) || $dir === false)
    ? '' : $dir . DIRECTORY_SEPARATOR . $name;
$real = $path !== '' ? realpath($path) : false;

// 只允许访问 uploads/xss 目录内的白名单文件，杜绝目录穿越
if ($real === false || strpos($real, $dir . DIRECTORY_SEPARATOR) !== 0 || !is_file($real)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    echo '404 - file not found';
    exit;
}

header('Content-Type: ' . $types[$ext]);
header('X-Content-Type-Options: nosniff');
header('Content-Length: ' . filesize($real));
header('Cache-Control: no-store, no-cache, must-revalidate');
readfile($real);
