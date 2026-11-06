<?php
/**
 * bafanglab - 练习字典统一下载接口
 * 版权所有：八方网域-无涯
 *
 * 为「目录扫描 / 参数 Fuzz / 密码爆破」三个模块提供字典下载。
 * 通过 PHP 输出 Content-Disposition: attachment，强制浏览器下载，
 * 避免直接链接 .txt 文件在 Burp 代理等场景下出现响应异常或被当作页面展示。
 *
 * 用法：includes/dict_download.php?type=dirs|params|passwords
 */
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/i18n.php';

$type = isset($_GET['type']) ? $_GET['type'] : '';

// 字典清单：type => array(物理路径, 下载文件名)
$files = array(
    'dirs'      => array(BASE_DIR . '/modules/dirscan/dict_dirs.txt',    'dict_dirs.txt'),
    'params'    => array(BASE_DIR . '/modules/fuzz/dict_params.txt',     'dict_params.txt'),
    'passwords' => array(BASE_DIR . '/modules/brute/dict_passwords.txt', 'dict_passwords.txt'),
);

if (!isset($files[$type]) || !is_file($files[$type][0])) {
    http_response_code(404);
    exit('字典文件不存在：' . htmlspecialchars($type));
}

$path = $files[$type][0];
$name = $files[$type][1];

// 关闭缓冲区，确保内容立即输出，降低代理超时概率
while (ob_get_level()) { ob_end_clean(); }

header('Content-Type: text/plain; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $name . '"');
header('Content-Length: ' . filesize($path));
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');

readfile($path);
exit;
