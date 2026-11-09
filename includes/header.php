<?php
/**
 * bafanglab - 公共头部
 * 版权所有：八方网域-无涯 | 版本：见 config.php 的 BAFANGLAB_VERSION
 *
 * level_* 关卡辅助函数已拆分至 includes/levels.php（同时供 piece.php 端点共用）。
 * 页面响应只包含关卡练习内容与学习辅助按钮；辅助内容由 main.js 点击时异步从 piece.php 获取。
 */
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/i18n.php';
require_once dirname(__DIR__) . '/includes/modules.php';
require_once dirname(__DIR__) . '/includes/tabs.php';
require_once dirname(__DIR__) . '/includes/levels.php';

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
