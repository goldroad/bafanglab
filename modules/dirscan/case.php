<?php
/**
 * bafanglab - 目录扫描真实案例
 * 版权所有：八方网域-无涯
 */
require_once dirname(dirname(__DIR__)) . '/includes/i18n.php';
$page_title = '目录扫描真实案例';
require_once dirname(dirname(__DIR__)) . '/includes/header.php';
?>
<a class="back-link" href="<?php echo BASE_URL; ?>/index.php">&larr; <?php echo t('footer.back'); ?></a>

<div class="panel">
    <h2><?php echo '模块2：目录扫描 - 真实案例'; ?></h2>

    <div class="case-intro">
        <p><?php echo '以下案例来源于真实渗透测试项目，已做脱敏处理。通过学习这些案例，可以了解目录扫描在实际环境中的重要性。'; ?></p>
    </div>

    <?php level_content_echo('dirscan', 'index', 'case'); ?>
</div>

<?php level_nav_current(); ?>

<?php require_once dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>
