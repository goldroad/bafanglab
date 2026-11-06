<?php
/**
 * bafanglab - 反序列化真实案例
 * 版权所有：八方网域-无涯
 */
require_once dirname(dirname(__DIR__)) . '/includes/i18n.php';
$page_title = '反序列化真实案例';
require_once dirname(dirname(__DIR__)) . '/includes/header.php';
?>
<a class="back-link" href="<?php echo BASE_URL; ?>/index.php">&larr; <?php echo t('footer.back'); ?></a>

<div class="panel">
    <h2><?php echo '模块15：反序列化漏洞 - 真实案例'; ?></h2>

    <div class="case-intro">
        <p><?php echo '以下案例均来自公开披露的 PHP 反序列化漏洞（CVE 编号 / 厂商安全通告 / 公开复现记录），保留原始接口路径与参数，便于对照真实攻击面。案例仅用于教学，请勿对未授权目标验证。'; ?></p>
    </div>

    <?php level_content_echo('unserialize', 'index', 'case'); ?>
</div>

<?php level_nav_current(); ?>

<?php require_once dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>
