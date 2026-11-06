<?php
/**
 * bafanglab - 密码暴力猜解真实案例
 * 版权所有：八方网域-无涯
 */
require_once dirname(dirname(__DIR__)) . '/includes/i18n.php';
$page_title = '密码暴力猜解真实案例';
require_once dirname(dirname(__DIR__)) . '/includes/header.php';
?>
<a class="back-link" href="<?php echo BASE_URL; ?>/index.php">&larr; <?php echo t('footer.back'); ?></a>

<div class="panel">
    <h2><?php echo '模块5：密码暴力猜解 - 真实案例'; ?></h2>
    <?php brute_tabs('tab.case'); ?>

    <div class="case-intro">
        <p><?php echo '以下案例来源于真实渗透测试项目，已做脱敏处理。通过学习这些案例，可以了解弱口令漏洞在实际环境中的危害。'; ?></p>
    </div>

    <?php level_content_echo('brute', 'index', 'case'); ?>
</div>

<?php level_nav_current(); ?>

<?php require_once dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>
