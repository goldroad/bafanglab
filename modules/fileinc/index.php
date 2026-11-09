<?php
/**
 * bafanglab - 模块10：文件包含
 * 版权所有：八方网域-无涯
 */
require_once dirname(dirname(__DIR__)) . '/includes/i18n.php';
$page_title = '文件包含';
require_once dirname(dirname(__DIR__)) . '/includes/header.php';

$content = '';
if (isset($_GET['page'])) {
    $page = $_GET['page'];
    // 靶场故意设计：用户输入直接进入 include，无任何过滤
    $incfile = $page; // @vuln
    ob_start();
    // 用 @ 抑制 warning 以保持页面美观，错误仍会通过 PHP 配置显示
    @include($incfile); // @vuln
    $content = ob_get_clean();
    if ($content === '' || $content === false) {
        $content = '<span style="color:var(--danger)">' . '包含失败或文件为空：' . htmlspecialchars($incfile) . '</span>';
    }
}
?>
<a class="back-link" href="<?php echo BASE_URL; ?>/index.php">&larr; <?php echo t('footer.back'); ?></a>

<div class="panel">
    <h2><?php echo '模块10：文件包含'; ?></h2>
    <?php level_tools('basic', 'hint', true, false, 'tools', BASE_URL . '/modules/fileinc/case.php'); ?>
<?php
    level_code(__FILE__, null, <<<'FIX'
// 白名单映射：用户只能选择 key，真实路径由服务端决定
$pages = array(
    'about' => 'pages/about.php',
    'news'  => 'pages/news.php',
);
$key = isset($_GET['page']) ? $_GET['page'] : 'about';
if (!isset($pages[$key])) { die('页面不存在'); }
include($pages[$key]);   // 用户输入永远不会直接进入 include

// 辅助手段：php.ini 设置 allow_url_include = Off，杜绝远程包含
FIX);
    ?>
    <p class="desc">
        <?php echo '站点通过 <code>?page=</code> 参数动态包含页面文件。'; ?>
    </p>
    <?php level_goal('目标1：包含 <code>/etc/hosts</code> 或 <code>php.ini</code> 等系统文件。<br>
        目标2：配合模块9 上传的图片马，包含它执行 PHP 代码。<br>
        目标3（进阶）：用 <code>php://filter</code> 读取 index.php 源码。'); ?>

    <p>
        <?php echo '正常页面：'; ?>
        <a href="<?php echo BASE_URL; ?>/modules/fileinc/index.php?page=pages/about.php" style="color:var(--accent)"><?php echo '关于我们'; ?></a> |
        <a href="<?php echo BASE_URL; ?>/modules/fileinc/index.php?page=pages/news.php" style="color:var(--accent)"><?php echo '新闻中心'; ?></a>
    </p>

    <form method="get" action="<?php echo BASE_URL; ?>/modules/fileinc/index.php">
        <div class="form-row">
            <label><?php echo 'page 参数'; ?></label>
            <input type="text" name="page" value="<?php echo isset($_GET['page']) ? htmlspecialchars($_GET['page']) : 'pages/about.php'; ?>">
        </div>
        <button class="btn" type="submit"><?php echo '加载'; ?></button>
    </form>

    <?php if (isset($_GET['page'])): ?>
        <h4><?php echo '包含结果（'; ?><?php echo htmlspecialchars($_GET['page']); ?>）</h4>
        <div class="result"><?php echo $content; ?></div>
    <?php endif; ?>
</div>

<?php level_nav_current(); ?>

<?php require_once dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>
