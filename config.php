<?php
/**
 * bafanglab - Web漏洞靶场
 * 版权所有：八方网域-无涯
 * 全局配置文件
 */

// 靶场版本号（唯一来源：页面、页尾、首页横幅统一引用此常量，避免多处硬编码）
define('BAFANGLAB_VERSION', '1.0.1');

// 数据库配置
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_USER', 'root');
define('DB_PASS', 'root');
define('DB_NAME', 'bafanglab');

// 项目根路径（用于文件包含/读取模块）
define('BASE_DIR', dirname(__FILE__));

// 自动计算 Web 根路径（兼容站点根指向 bafanglab 目录或其上级 WWW 目录两种部署方式）
$__docroot = isset($_SERVER['DOCUMENT_ROOT']) ? str_replace('\\', '/', rtrim($_SERVER['DOCUMENT_ROOT'], '/')) : '';
$__basedir = str_replace('\\', '/', BASE_DIR);
$__baseurl = '';
if ($__docroot !== '' && strpos($__basedir, $__docroot) === 0) {
    $__baseurl = substr($__basedir, strlen($__docroot));
}
define('BASE_URL', $__baseurl);
unset($__docroot, $__basedir, $__baseurl);

/**
 * 当前访问协议（自动识别 http/https，兼容反向代理场景）
 */
function site_scheme()
{
    if ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
        || (!empty($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)) {
        return 'https';
    }
    return 'http';
}

/**
 * 当前访问主机（含端口），如 localhost、127.0.0.1:8080
 */
function site_host()
{
    return (isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST'] !== '')
        ? $_SERVER['HTTP_HOST'] : 'localhost';
}

/**
 * 站点根地址（含协议、主机与部署子目录）
 * 部署在 /<部署目录>/ 时返回 http://<主机>/<部署目录>
 */
function site_base_url()
{
    return site_scheme() . '://' . site_host() . BASE_URL;
}

/**
 * 拼接站点内绝对 URL（自动带上部署子目录）
 * @param string $path 相对路径，如 modules/sqli/get.php
 */
function site_url($path = '')
{
    return site_base_url() . '/' . ltrim($path, '/');
}

/**
 * 探测 MySQL 服务端版本号（用于首页横幅展示，避免把版本写死）
 * 探测失败时返回空字符串，调用方负责降级显示
 *
 * 说明：PHP 8.1+ 默认 mysqli 报错模式为异常，这里显式关闭并自行捕获，
 *       保证「数据库连不上」不会让首页直接 500，只是不显示版本号。
 */
function db_server_version()
{
    static $ver = null;
    if ($ver !== null) { return $ver; }
    $ver = '';
    if (!function_exists('mysqli_connect')) { return $ver; }
    $prev = mysqli_report(MYSQLI_REPORT_OFF);
    try {
        $link = @mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME, (int)DB_PORT);
        if ($link) {
            $v = mysqli_get_server_info($link);
            // MariaDB 兼容层返回 "5.5.5-10.11.19-MariaDB-..." 形式，剥掉前缀取真实版本号
            if (is_string($v) && stripos($v, 'MariaDB') !== false
                && preg_match('/^5\.5\.5-(\d+\.\d+\.\d+)/', $v, $m)) {
                $v = $m[1];
            }
            if (is_string($v) && $v !== '') { $ver = $v; }
            mysqli_close($link);
        }
    } catch (Exception $e) {
        $ver = '';
    }
    unset($prev);
    return $ver;
}

// 上传目录
define('UPLOAD_DIR', BASE_DIR . DIRECTORY_SEPARATOR . 'uploads');

// 时区
date_default_timezone_set('Asia/Shanghai');

// 靶场环境：显示PHP错误，方便学员观察注入报错等信息
error_reporting(E_ALL);
ini_set('display_errors', 1);
