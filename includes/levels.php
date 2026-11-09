<?php
/**
 * bafanglab - 关卡辅助函数库
 * 版权所有：八方网域-无涯
 *
 * 从 includes/header.php 拆出：header.php 只保留页面模板，
 * 本文件集中存放 level_* 函数，供页面与 piece.php（辅助内容按需加载端点）共用。
 *
 * 响应瘦身设计（2026-11）：
 *   「开始练习」页面的 HTTP 响应只包含关卡练习内容与学习辅助按钮，
 *   基础知识/提示/工具/源码/修复/案例等辅助内容不再内嵌，
 *   由前端点击按钮时异步请求 piece.php 按需获取。
 *   - level_tools()  只输出按钮栏；
 *   - level_code()   改为空操作（no-op），各关卡页保留调用：
 *     piece.php 通过 level_code_parse() 静态解析页面源码中的
 *     level_code(源文件, ..., <<<'FIX' ... FIX) 调用，
 *     提取「漏洞源码文件路径」与「修复示例代码」。
 */

require_once dirname(__DIR__) . '/includes/i18n.php';
require_once dirname(__DIR__) . '/includes/modules.php';
require_once dirname(__DIR__) . '/includes/tabs.php';

/**
 * 关卡学习辅助工具栏（统一置于页面内容上方）
 *
 * 页内直显模式：按钮栏下方的固定区域由 main.js 在点击时
 * 异步从 piece.php 拉取对应内容并填充（首取后内存缓存）。
 *
 * @param string $basicVar  兼容旧签名保留（内容改由端点按 slug/variant 提供）
 * @param string $hintVar   兼容旧签名保留
 * @param bool   $showSrc   是否显示「漏洞源码」按钮（Burp/目录扫描/参数Fuzz 等无源码关卡传 false）
 * @param bool   $showTools 是否显示「推荐工具」按钮
 * @param string $toolsVar  兼容旧签名保留
 * @param string $caseUrl   真实案例页面链接（为空则不显示案例按钮）
 */
if (!function_exists('level_tools')) {
    function level_tools($basicVar = 'basic', $hintVar = 'hint', $showSrc = true, $showTools = false, $toolsVar = 'tools', $caseUrl = '')
    {
        echo '<div class="action-bar" data-lvl-inline="1">'
            . '<span class="action-label">' . t('tools.label') . '</span>'
            . '<button class="btn btn-practice active" type="button" data-lvl-view="practice">'
            . t('tools.practice') . '</button>'
            . '<button class="btn btn-basic" type="button" data-lvl-view="basic">'
            . t('tools.basic') . '</button>'
            . '<button class="btn btn-hint" type="button" data-lvl-view="hint">'
            . t('tools.hint') . '</button>';
        if ($showTools) {
            echo '<button class="btn btn-tools" type="button" data-lvl-view="tools">' . t('tools.tools') . '</button>';
        }
        if ($showSrc) {
            echo '<button class="btn btn-code" type="button" data-lvl-view="src">' . t('tools.src') . '</button>';
        }
        echo '<button class="btn btn-fix" type="button" data-lvl-view="fix">' . t('tools.fix') . '</button>';
        if ($caseUrl) {
            echo '<button class="btn btn-case" type="button" data-lvl-view="case">' . t('tools.case') . '</button>';
        }
        echo '</div>';
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

if (!function_exists('level_code_render')) {
    /**
     * 生成「漏洞源码」面板 HTML（相对路径 + 逐行高亮），供 piece.php 按需返回
     * @param string|false $srcFile
     * @param array|null   $vulnLines null 则按 @vuln 标记自动定位
     * @return string 源码文件不存在时返回空字符串
     */
    function level_code_render($srcFile, $vulnLines = null)
    {
        if ($srcFile === false || !is_file($srcFile)) { return ''; }
        if ($vulnLines === null) {
            $vulnLines = level_code_vuln_lines($srcFile);
        }
        // 统一成正斜杠后剥离站点根前缀，源码面板里的路径在 Windows 下也能正常显示
        $rel = str_replace('\\', '/', $srcFile);
        $rel = preg_replace('#^' . preg_quote(str_replace('\\', '/', BASE_DIR), '#') . '/#', '', $rel);
        $src = '<div class="src-meta">' . htmlspecialchars($rel)
            . '（红色高亮行为漏洞点）' . '</div>';
        $src .= '<pre class="src-pre">';
        $lines = file($srcFile);
        foreach ($lines as $i => $line) {
            $n = $i + 1;
            $cls = in_array($n, $vulnLines) ? 'src-line src-vuln' : 'src-line';
            $src .= '<span class="' . $cls . '"><span class="src-ln">' . $n . '</span>'
                . htmlspecialchars(rtrim($line, "\r\n")) . '</span>';
        }
        $src .= '</pre>';
        return $src;
    }
}

if (!function_exists('level_code_parse')) {
    /**
     * 静态解析关卡页面源码中的 level_code(...) 调用，提取源码文件路径与修复示例代码
     *
     * 关卡页保留 level_code(源文件, null, <<<'FIX' ... FIX); 调用作为数据源，
     * 页面渲染时该函数为空操作；本函数按需读取页面文件文本做提取。
     *
     * @param string $slug    模块 slug（如 sqli）
     * @param string $variant 关卡文件名（不含 .php，如 get）
     * @return array array(源码文件绝对路径|false, 修复示例代码字符串)
     */
    function level_code_parse($slug, $variant)
    {
        $file = BASE_DIR . '/modules/' . $slug . '/' . $variant . '.php';
        $fix = '';
        if (!is_file($file)) { return array(false, $fix); }
        $src = file_get_contents($file);
        if (!preg_match("/level_code\(\s*(.+?),\s*(?:null|array\(\))\s*,\s*<<<'FIX'\r?\n(.*?)\r?\n\s*FIX\s*\)\s*;/s", $src, $m)) {
            return array(false, $fix);
        }
        $fix = $m[2];
        $expr = trim($m[1]);
        if (strcasecmp($expr, 'false') === 0) { return array(false, $fix); }
        // 把 __FILE__ / dirname(__FILE__) / __DIR__ 替换为字面量后求值
        $dir = str_replace('\\', '/', dirname($file));
        $expr = str_replace(
            array('dirname(__FILE__)', '__DIR__', '__FILE__'),
            array("'" . $dir . "'", "'" . $dir . "'", "'" . str_replace('\\', '/', $file) . "'"),
            $expr
        );
        $path = false;
        try {
            $path = eval("return {$expr};");
        } catch (\Throwable $e) {
            $path = false;
        }
        if (!is_string($path) || $path === '') { $path = false; }
        return array($path, $fix);
    }
}

if (!function_exists('level_piece_html')) {
    /**
     * 按需渲染某关卡的学习辅助内容片段（piece.php 端点调用）
     *
     * piece 取值：
     *   basic / hint / tools / bypass / case  → content/<slug>/<variant>/zh/<piece>.html
     *   fix  → 修复方法(fix.html) + 修复示例代码(页面内联 heredoc) + 绕过技巧(bypass.html)，
     *          组合顺序与原前端 fixCombinedHtml() 一致
     *   src  → 漏洞源码面板（level_code 第一个参数指向的源文件逐行高亮）
     *
     * @param string $slug
     * @param string $variant
     * @param string $piece
     * @return string HTML 片段（无内容时为空字符串）
     */
    function level_piece_html($slug, $variant, $piece)
    {
        switch ($piece) {
            case 'basic':
            case 'hint':
            case 'tools':
            case 'bypass':
            case 'case':
                return level_content_load($slug, $variant, $piece);
            case 'fix':
                $out = level_content_load($slug, $variant, 'fix');
                list(, $fixCode) = level_code_parse($slug, $variant);
                if (trim($fixCode) !== '') {
                    $out .= '<h4>修复示例代码</h4><pre class="src-pre fix-pre">'
                        . htmlspecialchars($fixCode) . '</pre>';
                }
                $out .= level_content_load($slug, $variant, 'bypass');
                return $out;
            case 'src':
                list($srcFile,) = level_code_parse($slug, $variant);
                return level_code_render($srcFile);
        }
        return '';
    }
}

if (!function_exists('level_code')) {
    /**
     * 空操作（no-op）。
     *
     * 历史上本函数在页面内联输出 #lvlSrc / #lvlFix 隐藏面板；
     * 响应瘦身改造后辅助内容全部改由 piece.php 按需返回，
     * 但各关卡页保留本调用——piece.php 通过 level_code_parse()
     * 静态解析该调用的参数（源码文件路径 + 修复示例代码 heredoc）。
     *
     * @param string|false $srcFile   要展示的 PHP 源文件绝对路径（传 __FILE__）；传 false 则不展示漏洞源码
     * @param array|null   $vulnLines 兼容旧签名保留（高亮行始终按 @vuln 标记自动定位）
     * @param string $fixCode   修复示例代码片段（原样展示，可用 nowdoc 书写）
     */
    function level_code($srcFile, $vulnLines = array(), $fixCode = '')
    {
        // no-op：辅助内容改由 piece.php 按需提供，页面响应不再内嵌源码/修复面板
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
