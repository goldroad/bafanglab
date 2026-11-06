<?php
/**
 * bafanglab - 公共头部
 * 版权所有：八方网域-无涯 | 版本：见 config.php 的 BAFANGLAB_VERSION
 */
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/i18n.php';
require_once dirname(__DIR__) . '/includes/modules.php';
require_once dirname(__DIR__) . '/includes/tabs.php';

/**
 * 关卡学习辅助工具栏（统一置于页面内容上方）
 *
 * 所有关卡统一使用「页内直显」模式：
 *   - 首个按钮为「开始练习」，默认激活，下方直接展示关卡练习内容（即页面原有内容）；
 *   - 基础知识/提示/推荐工具/漏洞源码/修复方法/真实案例 均不再弹窗或跳新页，
 *     点击后在按钮栏下方的固定区域直显对应内容，再次点击「开始练习」回到练习。
 *   - 真实案例内容按当前子页变体从 content/<slug>/<variant>/<lang>/case.html 内嵌，
 *     无需额外请求，每个子关卡展示自己的案例。
 *
 * @param string $basicVar 基础知识内容所在的 JS 变量名
 * @param string $hintVar  提示内容所在的 JS 变量名
 * @param bool   $showSrc  是否显示「漏洞源码」按钮（Burp/目录扫描/参数Fuzz 等无源码关卡传 false）
 * @param bool   $showTools 是否显示「推荐工具」按钮
 * @param string $toolsVar 推荐工具内容所在的 JS 变量名
 * @param string $caseUrl  真实案例页面链接（为空则不显示；直显模式下仅作开关，内容自动内嵌）
 */
if (!function_exists('level_tools')) {
    function level_tools($basicVar = 'basic', $hintVar = 'hint', $showSrc = true, $showTools = false, $toolsVar = 'tools', $caseUrl = '')
    {
        // 所有关卡统一使用页内直显模式
        echo '<div class="action-bar" data-lvl-inline="1">'
                . '<span class="action-label">' . t('tools.label') . '</span>'
                . '<button class="btn btn-practice active" type="button" data-lvl-view="practice">'
                . t('tools.practice') . '</button>'
                . '<button class="btn btn-basic" type="button" data-lvl-view="basic" data-var="' . htmlspecialchars($basicVar) . '">'
                . t('tools.basic') . '</button>'
                . '<button class="btn btn-hint" type="button" data-lvl-view="hint" data-var="' . htmlspecialchars($hintVar) . '">'
                . t('tools.hint') . '</button>';
            if ($showTools) {
                echo '<button class="btn btn-tools" type="button" data-lvl-view="tools" data-var="' . htmlspecialchars($toolsVar) . '">' . t('tools.tools') . '</button>';
            }
            if ($showSrc) {
                echo '<button class="btn btn-code" type="button" data-lvl-view="src">' . t('tools.src') . '</button>';
            }
            echo '<button class="btn btn-fix" type="button" data-lvl-view="fix">' . t('tools.fix') . '</button>';
            if ($caseUrl) {
                echo '<button class="btn btn-case" type="button" data-lvl-view="case">' . t('tools.case') . '</button>';
            }
            echo '</div>';
            // 真实案例内容按当前子页变体内嵌（隐藏容器，点击按钮后直显）
            if ($caseUrl) {
                $slug = level_slug_current();
                $variant = basename(isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '', '.php');
                if ($slug !== '' && $variant !== '' && function_exists('level_content_load')) {
                    $caseHtml = level_content_load($slug, $variant, 'case');
                    echo '<div id="lvlCase" style="display:none">' . $caseHtml . '</div>';
                }
            }
    }
}

/**
 * 关卡「开始练习」页的目标行：红色小旗 + 强调色块（全站统一样式，定义见 style.css 的 .level-goal）
 * @param string $html 目标内容（可含 HTML，如 <code>），建议以「目标：」开头
 */
if (!function_exists('level_goal')) {
    function level_goal($html)
    {
        echo '<div class="level-goal">'
            . '<span class="goal-flag" aria-hidden="true">'
            . '<svg viewBox="0 0 24 24" focusable="false" role="presentation">'
            . '<path d="M4.5 3v18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>'
            . '<path d="M5.5 4h13.2l-2.4 4 2.4 4H5.5z" fill="currentColor"/>'
            . '</svg></span>'
            . '<div class="goal-body">' . $html . '</div>'
            . '</div>';
    }
}

/**
 * 输出"漏洞源码 / 修复示例"弹窗内容（隐藏 div，由 main.js 的 showSrc/showFix 取内容弹窗）
 * @param string|false $srcFile   要展示的 PHP 源文件绝对路径（传 __FILE__）；传 false 则不展示漏洞源码
 * @param array|null   $vulnLines 漏洞行号数组；传 null 则按源码中的 @vuln 标记自动定位
 * @param string $fixCode   修复示例代码片段（原样展示，可用 nowdoc 书写）
 */
if (!function_exists('level_code_vuln_lines')) {
    /**
     * 扫描源码文件里的 @vuln 标记，返回需要红色高亮的行号（从 1 开始）
     * 标记写在漏洞代码行尾（如 `// @vuln`），代码增删后行号自动跟随，不会漂移
     * @param string|false $srcFile
     * @return array
     */
    function level_code_vuln_lines($srcFile)
    {
        if ($srcFile === false || !is_file($srcFile)) { return array(); }
        $out = array();
        foreach (file($srcFile) as $i => $line) {
            if (strpos($line, '@vuln') !== false) { $out[] = $i + 1; }
        }
        return $out;
    }
}

if (!function_exists('level_code')) {
    function level_code($srcFile, $vulnLines = array(), $fixCode = '')
    {
        if ($vulnLines === null) {
            $vulnLines = level_code_vuln_lines($srcFile);
        }
        if ($srcFile !== false) {
            $rel = str_replace(BASE_DIR . DIRECTORY_SEPARATOR, '', $srcFile);
            // 统一成正斜杠，源码面板里的路径在 Windows 下也能正常显示
            $rel = str_replace('\\', '/', $rel);
            $src = '<div class="src-meta">' . htmlspecialchars($rel)
                . '（红色高亮行为漏洞点）' . '</div>';
            if (is_file($srcFile)) {
                $src .= '<pre class="src-pre">';
                $lines = file($srcFile);
                foreach ($lines as $i => $line) {
                    $n = $i + 1;
                    $cls = in_array($n, $vulnLines) ? 'src-line src-vuln' : 'src-line';
                    $src .= '<span class="' . $cls . '"><span class="src-ln">' . $n . '</span>'
                        . htmlspecialchars(rtrim($line, "\r\n")) . '</span>';
                }
                $src .= '</pre>';
            } else {
                $src .= '<p>' . '源码文件不存在：' . htmlspecialchars($rel) . '</p>';
            }
            echo '<div id="lvlSrc" style="display:none">' . $src . '</div>';
        }
        echo '<div id="lvlFix" style="display:none"><pre class="src-pre fix-pre">'
            . htmlspecialchars($fixCode) . '</pre></div>';
    }
}

/**
 * 关卡顺序表（由 includes/modules.php 的注册表生成，标题按当前语言）
 * @return array 序号 => array(标题, 相对地址)
 */
if (!function_exists('level_map')) {
    function level_map()
    {
        static $map = null;
        if ($map !== null) { return $map; }
        $map = array();
        $no = 0;
        foreach (lab_modules() as $slug => $url) {
            $no++;
            $map[$no] = array(lab_module_title($slug), $url);
        }
        return $map;
    }
}

/**
 * 页面底部的关卡切换导航（左"上一关" + 右"下一关"）
 * @param int      $current 当前关卡序号
 * @param int|null $nextNo  下一关序号覆盖（默认按 $current + 1 自动计算）
 *                          知识页需要把「下一关」指回本模块练习首页时使用
 */
if (!function_exists('level_nav')) {
    function level_nav($current, $nextNo = null)
    {
        $levels = level_map();
        $prev = $current - 1;
        $next = ($nextNo === null) ? $current + 1 : $nextNo;

        echo '<div class="level-nav">';

        // 左侧：上一关
        if (isset($levels[$prev])) {
            echo '<a class="btn btn-plain" href="' . BASE_URL . $levels[$prev][1] . '">'
                . t('nav.prev_tpl', array('no' => $prev, 'title' => $levels[$prev][0])) . '</a>';
        } else {
            echo '<a class="btn btn-plain" href="' . BASE_URL . '/index.php">' . t('nav.back_home') . '</a>';
        }

        // 中间：切换关卡（点击下拉列出全部关卡，可任意跳转）
        echo '<div class="level-switch">';
        echo '<button class="btn btn-plain" type="button" data-level-switch>' . t('nav.switch') . '</button>';
        echo '<div class="level-switch-menu" style="display:none">';
        foreach ($levels as $no => $info) {
            $cls = ($no == $current) ? 'level-switch-item current' : 'level-switch-item';
            echo '<a class="' . $cls . '" href="' . BASE_URL . $info[1] . '">'
                . '<span class="ls-no">' . (int)$no . '.</span> '
                . htmlspecialchars($info[0], ENT_QUOTES) . '</a>';
        }
        echo '</div>';
        echo '</div>';

        // 右侧：下一关
        if (isset($levels[$next])) {
            echo '<a class="btn" href="' . BASE_URL . $levels[$next][1] . '">'
                . t('nav.next_tpl', array('no' => $next, 'title' => $levels[$next][0])) . '</a>';
        } else {
            echo '<a class="btn btn-plain" href="' . BASE_URL . '/index.php">' . t('nav.finished') . '</a>';
        }

        echo '</div>';
    }
}

/**
 * 关卡选项卡（跨页面链接形式，适用于子关卡拆分在不同文件中）
 * @param array  $tabs   array(i18n 键 => 链接地址)
 * @param string $active 当前激活选项卡（可传 i18n 键、中文标签或当前语言标签，自动归一化）
 */
if (!function_exists('level_tabs_links')) {
    function level_tabs_links($tabs, $active)
    {
        $activeKey = tab_key($active);
        echo '<div class="tabs">';
        foreach ($tabs as $key => $url) {
            $cls = ($key === $activeKey) ? 'tab active' : 'tab';
            // 真实案例选项卡使用特殊样式
            if ($key === 'tab.case') { $cls .= ' tab-case'; }
            echo '<a class="' . $cls . '" href="' . $url . '">' . t($key) . '</a>';
        }
        echo '</div>';
    }
}

/**
 * 「显示SQL语句」开关按钮：默认隐藏 data-sql-panel 容器，点击后切换显示
 * 依赖 assets/js/main.js 中的 data-sql-toggle 委托事件
 */
if (!function_exists('sql_toggle_button')) {
    function sql_toggle_button()
    {
        echo '<button type="button" class="btn btn-plain btn-sm" data-sql-toggle'
            . ' data-show="' . '显示SQL语句' . '"'
            . ' data-hide="' . '隐藏SQL语句' . '"'
            . ' style="margin:8px 0">' . '显示SQL语句' . '</button>';
    }
}

?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? htmlspecialchars($page_title) . ' - ' : ''; ?>bafanglab</title>
    <script>
        // 提前应用主题，避免页面渲染时的颜色闪烁（默认浅色）
        (function () {
            try {
                if (localStorage.getItem('bafanglab-theme') === 'dark') {
                    document.documentElement.setAttribute('data-theme', 'dark');
                }
            } catch (e) {}
        })();
        // 前端文案（主题切换按钮、弹窗标题），由 PHP 注入
        window.LAB_I18N = <?php echo lang_js_strings(); ?>;
    </script>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css?v=<?php echo @filemtime(dirname(__DIR__) . '/assets/css/style.css') ?: BAFANGLAB_VERSION; ?>">
</head>
<body>
<header class="topbar">
    <div class="logo">
        <span class="logo-icon">&#9876;</span>
        <a href="<?php echo BASE_URL; ?>/index.php">bafanglab</a>
        <span class="logo-sub"><?php echo t('site.sub'); ?></span>
    </div>
    <nav class="topnav">
        <a href="<?php echo BASE_URL; ?>/index.php"><?php echo t('nav.home'); ?></a>
        <a href="<?php echo BASE_URL; ?>/check.php"><?php echo t('nav.check'); ?></a>
        <a href="<?php echo BASE_URL; ?>/install.php"><?php echo t('nav.install'); ?></a>
        <a href="<?php echo BASE_URL; ?>/about.php"><?php echo t('nav.about'); ?></a>
    </nav>
</header>
<main class="container">
<?php
// 关卡页：在页面首个 .panel h2 标题后追加子关卡数量角标（首页与非关卡页不处理）
// 角标与首页卡片共用同一套 .lvl-badge 设计语言（正圆、仅数字，可访问名称由 aria-label 提供）
// 纯知识页（protocol / encode / dnslog）由页面设置 $page_no_level_badge = true，标题只保留本页名称
$__curSlug = function_exists('level_slug_current') ? level_slug_current() : '';
if ($__curSlug !== '' && empty($page_no_level_badge)) {
    $__curN = lab_module_levels($__curSlug);
    $__curAria = t('lvl.badge_aria', array('n' => (int)$__curN));
    echo '<script>'
        . 'window.addEventListener("DOMContentLoaded",function(){'
        . 'var h=document.querySelector(".panel h2");'
        . 'if(h&&!h.querySelector(".lvl-badge")){'
        . 'var s=document.createElement("span");s.className="lvl-badge";'
        . 's.setAttribute("role","img");'
        . 's.setAttribute("aria-label","' . htmlspecialchars($__curAria, ENT_QUOTES) . '");'
        . 's.setAttribute("title","' . htmlspecialchars($__curAria, ENT_QUOTES) . '");'
        . 's.textContent="' . (int)$__curN . '";'
        . 'h.appendChild(s);}});'
        . '</script>';
}
?>
