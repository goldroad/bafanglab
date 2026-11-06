<?php
/**
 * bafanglab - 首页
 * 版权所有：八方网域-无涯
 */
require_once 'includes/i18n.php';
$page_title = '首页';
require_once 'includes/header.php';

// 漏洞模块清单：统一从 includes/modules.php 的注册表生成，标题/简介按当前语言取
$modules = array();
foreach (lab_modules() as $slug => $url) {
    $modules[] = array(
        'slug'   => $slug,
        'url'    => $url,
        'tag'    => t('m.' . $slug . '.tag'),
        'title'  => lab_module_no($slug) . '. ' . t('m.' . $slug . '.title'),
        // 关卡数独立成徽标（定义见 style.css 的 .lvl-badge），不再塞进标题文本
        'levels' => lab_module_levels($slug),
        'desc'   => t('m.' . $slug . '.desc'),
    );
}

?>

<div class="banner">
    <h1><?php echo t('index.h1'); ?></h1>
    <div class="banner-tags">
        <span><?php echo t('index.tag1'); ?></span>
        <span><?php echo t('index.tag2'); ?></span>
        <span><?php echo t('index.tag3', array('n' => count($modules))); ?></span>
    </div>
</div>

<div class="grid">
    <?php foreach ($modules as $m): ?>
    <div class="card">
        <div class="card-top">
            <span class="tag"><?php echo $m['tag']; ?></span>
            <span class="card-meta">
                <span class="lvl-badge" role="img" aria-label="<?php echo t('lvl.badge_aria', array('n' => (int)$m['levels'])); ?>" title="<?php echo t('lvl.badge_aria', array('n' => (int)$m['levels'])); ?>"><?php echo (int)$m['levels']; ?></span>
                <span class="bolt" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M13.6 2 4.7 13.8h6.2L8.9 22l9.1-12.2h-6.1L13.6 2z"/></svg>
                </span>
            </span>
        </div>
        <h3><?php echo $m['title']; ?></h3>
        <p><?php echo $m['desc']; ?></p>
        <div class="card-actions">
            <?php if ($m['slug'] === 'burp'): ?>
            <a class="btn btn-plain" href="<?php echo BASE_URL; ?>/modules/burp/protocol.php"><?php echo t('tab.burp_basic'); ?></a>
            <?php endif; ?>
            <a class="btn" href="<?php echo BASE_URL . $m['url']; ?>"><?php echo t('index.enter'); ?></a>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php require_once 'includes/footer.php'; ?>
