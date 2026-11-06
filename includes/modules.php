<?php
/**
 * bafanglab - 关卡注册表（唯一数据源）
 * 版权所有：八方网域-无涯
 *
 * 首页卡片、上一关/下一关导航、关卡序号全部由此生成，
 * 新增关卡只需在下方数组追加一行，并在 lang/zh.php 补 m.<slug>.tag/title/desc。
 * 数组顺序即关卡顺序（1 开始）。
 */

/**
 * slug => 相对地址（不含 BASE_URL）
 * @return array
 */
function lab_modules()
{
    static $m = null;
    if ($m !== null) { return $m; }
    $m = array(
        'burp'        => '/modules/burp/index.php',
        'dirscan'      => '/modules/dirscan/index.php',
        'fuzz'         => '/modules/fuzz/index.php',
        'jsleak'       => '/modules/jsleak/index.php',
        'brute'        => '/modules/brute/index.php',
        'xss'          => '/modules/xss/reflect.php',
        'sqli'         => '/modules/sqli/get.php',
        'xxe'          => '/modules/xxe/index.php',
        'upload'       => '/modules/upload/index.php',
        'fileinc'      => '/modules/fileinc/index.php',
        'csrf'         => '/modules/csrf/index.php',
        'ssrf'         => '/modules/ssrf/index.php',
        'fileread'     => '/modules/fileread/index.php',
        'rce'          => '/modules/rce/index.php',
        'unserialize'  => '/modules/unserialize/index.php',
        'ssti'         => '/modules/ssti/index.php',
    );
    return $m;
}

/**
 * 关卡 slug 数组（按顺序，索引 0 对应第 1 关）
 * @return array
 */
function lab_slugs()
{
    return array_keys(lab_modules());
}

/**
 * 取关卡序号（1 开始），未找到返回 0
 * @param string $slug
 * @return int
 */
function lab_module_no($slug)
{
    $i = array_search($slug, lab_slugs(), true);
    return $i === false ? 0 : $i + 1;
}

/**
 * 取关卡 slug，未找到返回空字符串
 * @param int $no 1 开始的序号
 * @return string
 */
function lab_module_slug($no)
{
    $slugs = lab_slugs();
    $i = (int)$no - 1;
    return isset($slugs[$i]) ? $slugs[$i] : '';
}

/**
 * 取关卡链接（已带部署子目录）
 * @param string $slug
 * @return string
 */
function lab_module_url($slug)
{
    $m = lab_modules();
    return BASE_URL . (isset($m[$slug]) ? $m[$slug] : '/index.php');
}

/**
 * 关卡标题（按当前语言）
 * @param string $slug
 * @return string
 */
function lab_module_title($slug)
{
    return t('m.' . $slug . '.title');
}

/**
 * 关卡子页面数量（用于标题后显示 "N levels"）
 * 唯一数据源是 includes/tabs.php 的 lab_tab_groups()：有页签取页签数，无页签为 1。
 * @return array slug => 子关卡数
 */
function lab_levels()
{
    static $levels = null;
    if ($levels !== null) { return $levels; }
    // tabs.php 依赖 config.php / i18n.php，require_once 可保证独立加载时也可用
    require_once dirname(__FILE__) . '/tabs.php';
    $levels = array();
    foreach (lab_modules() as $slug => $entry) {
        $tabs = lab_tab_group($slug);
        $levels[$slug] = $tabs ? count($tabs) : 1;
    }
    return $levels;
}

/**
 * 取某关卡的子关卡数量（未注册返回 1）
 * @param string $slug
 * @return int
 */
function lab_module_levels($slug)
{
    $levels = lab_levels();
    return isset($levels[$slug]) ? (int)$levels[$slug] : 1;
}

/**
 * 从 lab_modules() 提取 slug => 目录前缀 的映射
 * 用于 $_SERVER['SCRIPT_NAME'] 反查 slug——同一模块的多个子页面
 * （如 sqli 的 get.php / post.php / error.php）共享同一目录前缀。
 * @return array
 */
function lab_dirs()
{
    static $dirs = null;
    if ($dirs !== null) { return $dirs; }
    $dirs = array();
    foreach (lab_modules() as $slug => $entry) {
        $dirs[$slug] = rtrim(dirname($entry), '/\\');
    }
    return $dirs;
}

/**
 * 从脚本路径反查所属关卡 slug
 *
 * @param string $script 一般传 $_SERVER['SCRIPT_NAME']
 * @return string 未找到返回空字符串
 */
function lab_slug_from_script($script)
{
    $script = str_replace('\\', '/', (string)$script);
    // 去掉部署子目录前缀（BASE_URL 如 /bafanglab）
    $base = rtrim(str_replace('\\', '/', BASE_URL), '/');
    if ($base !== '' && strpos($script, $base) === 0) {
        $script = substr($script, strlen($base));
    }
    $script = '/' . ltrim($script, '/');

    foreach (lab_dirs() as $slug => $dir) {
        if (strpos($script, $dir . '/') === 0 || $script === $dir) {
            return $slug;
        }
    }
    return '';
}

/**
 * 当前页面所属关卡序号（自动从 SCRIPT_NAME 反推）
 * @return int 未找到返回 0
 */
function level_no_current()
{
    static $no = null;
    if ($no !== null) { return $no; }
    $slug = lab_slug_from_script(isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '');
    return $no = lab_module_no($slug);
}

/**
 * 当前页面所属关卡 slug（自动从 SCRIPT_NAME 反推）
 * @return string 未找到返回空字符串
 */
function level_slug_current()
{
    static $slug = null;
    if ($slug !== null) { return $slug; }
    return $slug = lab_slug_from_script(isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '');
}

/**
 * 自动推导当前关卡的底部导航（上一关/下一关）
 * 替换所有手写 level_nav(N)，注册表变顺序时零成本跟随。
 */
if (!function_exists('level_nav_current')) {
    function level_nav_current()
    {
        $no = level_no_current();
        if ($no > 0) {
            level_nav($no);
        }
    }
}
