<?php
/**
 * bafanglab - 国际化（仅简体中文）
 * 版权所有：八方网域-无涯
 *
 * 说明：本项目已移除繁体中文支持，全站仅使用简体中文。
 *       t() 从 lang/zh.php 读取文案；t2() 直接返回简体中文参数（第二参数被忽略，仅为兼容旧调用）。
 *
 * 使用方式：
 *   t('nav.home')                     取共享文案（lang/zh.php 中的键）
 *   t('index.tag3', array('n' => 16))  取文案并替换 {n} 占位符
 *   t2('简体中文', '台湾繁体')          直接返回简体中文（第二参数忽略，仅兼容历史代码）
 */

require_once dirname(__DIR__) . '/config.php';

/**
 * 支持的语言：仅简体中文
 */
function lang_supported()
{
    return array(
        'zh' => '中文',
    );
}

/**
 * 当前语言代码：固定简体中文
 */
function lang_current()
{
    return 'zh';
}

/**
 * 当前语言的文案字典（仅简体中文）
 */
function lang_dict()
{
    static $dict = null;
    if ($dict !== null) { return $dict; }

    $dict = array();
    $zhFile = BASE_DIR . '/lang/zh.php';
    if (is_file($zhFile)) { $dict = (array) require $zhFile; }

    return $dict;
}

/**
 * 取翻译文案
 * @param string $key  文案键，如 nav.home
 * @param array  $args 占位符替换表，如 array('n' => 16) 会替换文案中的 {n}
 * @return string
 */
function t($key, $args = array())
{
    $dict = lang_dict();
    $s = isset($dict[$key]) ? $dict[$key] : $key;
    if (!empty($args) && is_string($s)) {
        foreach ($args as $k => $v) {
            $s = str_replace('{' . $k . '}', (string)$v, $s);
        }
    }
    return $s;
}

/**
 * 直接输出翻译文案
 */
function te($key, $args = array())
{
    echo t($key, $args);
}

/**
 * 一次性文案（不进入字典）——适合与代码逻辑强耦合的局部字符串
 * 注：本项目仅简体中文，第二参数 $tw 被忽略，保留仅为兼容历史调用。
 * @param string $zh 简体中文
 * @param string $tw （已废弃，忽略）
 * @return string
 */
function t2($zh, $tw = '')
{
    return $zh;
}

/**
 * 把多行 HTML 文案安全内联为 JS 字符串字面量（JSON 编码）
 *
 * 关卡页的「基础知识 / 提示」是一大段 HTML，直接写进 JS 模板字符串时，
 * 一旦文案里出现反引号或 ${ 就会截断脚本；用 JSON 编码则完全免疫，
 * 同时把 < > & 转成 \u003C 形式，避免文案里出现 </script> 提前闭合标签。
 *
 * @param string $s 待内联的文案
 * @return string JSON 字面量（含双引号）
 */
function js_lit($s)
{
    return json_encode((string)$s, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP);
}

/**
 * 把文案里的地址占位符替换成当前部署环境的真实地址
 *
 * 关卡页的「基础知识 / 提示」用 nowdoc（<<<'EN'）存放，nowdoc 内不解析 PHP，
 * 所以不能直接写 <?php echo BASE_URL; ?>，否则会把源码原样打印给用户。
 * 统一约定以下占位符，由本函数在输出前回填：
 *   {{BASE}}       = 站内路径前缀（如 /<部署目录>）
 *   {{SITE}}       = 协议 + 主机 + 前缀（如 http://<主机>/<部署目录>）
 *   {{HOST}}       = 主机（含端口）
 *   {{BASE_DIR}}   = 站点根目录（正斜杠，按部署位置自动推导）
 *   {{UPLOAD_DIR}} = 上传目录（正斜杠，按部署位置自动推导）
 *
 * @param string $s 含占位符的文案
 * @return string 已回填真实地址的文案
 */
function site_tokens($s)
{
    static $map = null;
    if ($map === null) {
        $map = array(
            '{{SITE}}'       => site_base_url(),
            '{{BASE}}'       => BASE_URL,
            '{{HOST}}'       => site_host(),
            '{{BASE_DIR}}'   => str_replace('\\', '/', BASE_DIR),
            '{{UPLOAD_DIR}}' => str_replace('\\', '/', UPLOAD_DIR),
        );
    }
    return strtr((string)$s, $map);
}

/**
 * 取中文基准文案（不受当前语言影响）
 * 用于把「模块里写死的中文标签」反查成 i18n 键，保证切换语言后选项卡激活态仍然正确
 * @param string $key
 * @return string
 */
function t_zh($key)
{
    static $zh = null;
    if ($zh === null) {
        $f = BASE_DIR . '/lang/zh.php';
        $zh = is_file($f) ? (array) require $f : array();
    }
    return isset($zh[$key]) ? $zh[$key] : $key;
}

/**
 * 把「键 / 中文标签 / 当前语言标签」统一归一化为 i18n 键
 * @param string $key
 * @return string
 */
function tab_key($key)
{
    static $keys = null;
    if ($keys === null) {
        $keys = array();
        foreach (lang_dict() as $k => $v) {
            if (strpos($k, 'tab.') === 0) { $keys[] = $k; }
        }
    }
    foreach ($keys as $k) {
        if ($key === $k || $key === t_zh($k) || $key === t($k)) { return $k; }
    }
    return $key;
}

/**
 * 输出给前端 JS 使用的文案表（主题切换、弹窗标题等）
 * @return string 已转义的 JSON
 */
function lang_js_strings()
{
    $data = array(
        'themeDark'   => t('js.theme_dark'),
        'themeLight'  => t('js.theme_light'),
        'modalHint'   => t('js.modal_hint'),
        'modalBasic'  => t('js.modal_basic'),
        'modalSrc'    => t('js.modal_src'),
        'modalFix'    => t('js.modal_fix'),
        'modalTools'  => t('js.modal_tools'),
        'modalCase'   => t('js.modal_case'),
        'lvlEmpty'    => t('js.lvl_empty'),
    );
    return json_encode($data, JSON_UNESCAPED_UNICODE);
}

/**
 * 关卡内容文件路径
 *
 * content 目录组织方式：
 *   content/<slug>/<variant>/zh/<piece>.html
 *   例：content/sqli/get/zh/basic.html
 *
 * @param string $slug    关卡 slug，如 sqli
 * @param string $variant 子页标识（文件名不含后缀），如 get / index / intro
 * @param string $piece   内容片段：basic / hint / tools / case / fix / bypass
 * @param string $lang    语言代码（已废弃，固定 zh）
 * @return string 绝对路径
 */
function level_content_path($slug, $variant, $piece, $lang = null)
{
    return BASE_DIR . '/content/' . $slug . '/' . $variant . '/zh/' . $piece . '.html';
}

/**
 * 加载关卡内容文件（自动 site_tokens 替换）
 *
 * 文件内统一使用占位符 {{SITE}} {{BASE}} {{HOST}}，本函数自动替换。
 *
 * @param string $slug
 * @param string $variant
 * @param string $piece
 * @return string
 */
function level_content_load($slug, $variant, $piece)
{
    $path = level_content_path($slug, $variant, $piece);
    if (is_file($path)) {
        return site_tokens(file_get_contents($path));
    }
    return '';
}

/**
 * 输出关卡内容为前端 JS 变量声明
 *
 * 例：level_content_js('sqli', 'get', 'basic', 'basic');
 *   输出：var basic = "<h4>...</h4>...";
 *
 * 同时处理了文案里出现反引号 / ${ / </script> 等需要 JSON 编码的问题。
 *
 * @param string $slug
 * @param string $variant
 * @param string $piece
 * @param string $varName 要声明的 JS 变量名
 */
function level_content_js($slug, $variant, $piece, $varName)
{
    $content = level_content_load($slug, $variant, $piece);
    echo 'var ' . $varName . ' = ' . js_lit($content) . ';';
}

/**
 * 输出关卡内容为 HTML 片段（直接 echo，不走 JS）
 * @param string $slug
 * @param string $variant
 * @param string $piece
 */
function level_content_echo($slug, $variant, $piece)
{
    echo level_content_load($slug, $variant, $piece);
}
