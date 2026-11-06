<?php
/**
 * bafanglab - XSS 真实案例
 * 版权所有：八方网域-无涯
 */
require_once dirname(dirname(__DIR__)) . '/includes/i18n.php';
$page_title = 'XSS 真实案例';
require_once dirname(dirname(__DIR__)) . '/includes/header.php';
?>
<a class="back-link" href="<?php echo BASE_URL; ?>/index.php">&larr; <?php echo t('footer.back'); ?></a>

<div class="panel">
    <h2><?php echo '模块6：XSS 跨站脚本 - 真实案例'; ?></h2>
    <?php
    level_tabs_links(array(
        'tab.xss_reflect' => BASE_URL . '/modules/xss/reflect.php',
        'tab.xss_stored'  => BASE_URL . '/modules/xss/stored.php',
        'tab.xss_dom'     => BASE_URL . '/modules/xss/dom.php',
        'tab.xss_upload'  => BASE_URL . '/modules/xss/upload.php',
        'tab.xss_platform' => BASE_URL . '/modules/xss/platform.php',
        'tab.case'        => BASE_URL . '/modules/xss/case.php',
    ), 'tab.case');
    ?>

    <div class="case-intro">
        <p><?php echo '以下案例来源于真实渗透测试项目，已做脱敏处理。通过学习这些案例，可以了解 XSS 漏洞在实际环境中的危害和利用方式。'; ?></p>
    </div>

    <?php level_content_echo('xss', 'reflect', 'case'); ?>
</div>

<?php level_nav_current(); ?>

<?php require_once dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>
