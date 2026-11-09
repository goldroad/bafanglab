<?php
/**
 * bafanglab - 模块3：参数 Fuzz
 * 版权所有：八方网域-无涯
 */
require_once dirname(dirname(__DIR__)) . '/includes/i18n.php';
$page_title = '参数 Fuzz';
require_once dirname(dirname(__DIR__)) . '/includes/header.php';

// 隐藏参数：debug / admin / source（靶场故意设计）
// 返回规则（四种分支）：
//   1) 未传任何已知参数              → 请输入查询条件
//   2) 已知参数存在但值全部不匹配     → 找不到对应的数据
//   3) 参数名与值都正确               → 返回对应内容（保持原有提示）
//   4) 命中多个正确条件               → 各条结果叠加输出
$found    = array();   // 命中的正确条件（按顺序叠加输出）
$wrong    = false;     // 出现了隐藏参数名，但值不对
$hasKnown = false;     // 是否出现任一已知参数名（含公开参数 id）

if (isset($_GET['debug'])) {
    $hasKnown = true;
    if ($_GET['debug'] === '1') {
        $found[] = '发现隐藏参数 <b>debug</b>！服务器调试信息：PHP ' . PHP_VERSION . ' / ' . php_uname();
    } else {
        $wrong = true;
    }
}
if (isset($_GET['admin'])) {
    $hasKnown = true;
    if ($_GET['admin'] === 'true') {
        $found[] = '发现隐藏参数 <b>admin</b>！已获得管理员视图：恭喜你完成本关挑战！';
    } else {
        $wrong = true;
    }
}
if (isset($_GET['source'])) {
    $hasKnown = true;
    if ($_GET['source'] === '1') {
        $found[] = '发现隐藏参数 <b>source</b>！本页源码：'
            . '<br><pre>' . htmlspecialchars(file_get_contents(__FILE__)) . '</pre>';
    } else {
        $wrong = true;
    }
}
// 公开参数 id：默认 ?id=1 返回一条数据（原有行为保持不变）
if (isset($_GET['id'])) {
    $hasKnown = true;
    $found[] = '普通参数 id=' . htmlspecialchars($_GET['id']) . '（这是公开参数，不是隐藏参数哦）';
}
?>
<a class="back-link" href="<?php echo BASE_URL; ?>/index.php">&larr; <?php echo t('footer.back'); ?></a>

<div class="panel">
    <h2><?php echo '模块3：参数 Fuzz'; ?></h2>
    <?php level_tools('basic', 'hint', false, true); ?>
    <?php
    // 本关为参数 Fuzz 练习，不提供漏洞源码；仅展示修复示例
    level_code(false, array(), <<<'FIX'
// 修复1：生产环境彻底移除 debug/admin/source 等隐藏功能分支，
//        不要把"参数名难猜"当作安全控制点

// 修复2：接口参数白名单，未定义参数直接忽略
$allowed = array('id');
$params  = array_intersect_key($_GET, array_flip($allowed));

// 修复3：调试功能按环境开关，且需要登录 + 管理员权限
if (APP_ENV === 'dev' && is_admin()) {
    echo 'PHP ' . PHP_VERSION;
}

// 修复4：源码绝不允许通过参数输出；show_source/highlight_file 类功能从线上代码中删除
FIX);
    ?>
    <p class="desc">
        <?php echo '本页面除了可见的 <code>?id=1</code> 之外，还存在 <b style="color:var(--warn)">3 个未在页面上展示的隐藏参数</b>。'; ?>
    </p>
    <?php level_goal('目标：用 Burp Intruder 或 ffuf/wfuzz 对参数名进行爆破（GET 参数名 Fuzz），找出 3 个隐藏参数并拿到通关凭证。<br>
        页面会区分三种反馈：未带任何已知参数时提示「请输入查询条件」；参数名存在但值不对时提示「找不到对应的数据」；参数名与值都正确时返回对应内容。多个正确条件会叠加显示。'); ?>

    <p><?php echo '公开入口：'; ?><a href="<?php echo BASE_URL; ?>/modules/fuzz/index.php?id=1" style="color:var(--accent)">/modules/fuzz/index.php?id=1</a></p>

    <p style="margin-bottom:18px">
        <b><?php echo '练习字典：'; ?></b>
        <a class="btn btn-plain btn-sm" href="<?php echo BASE_URL; ?>/includes/dict_download.php?type=params"><?php echo '下载参数名字典 dict_params.txt'; ?></a>
    </p>

    <?php if (!empty($found)): ?>
        <div class="result">
            <?php foreach ($found as $f) { echo '<div style="margin-bottom:8px">' . $f . '</div>'; } ?>
        </div>
    <?php elseif ($wrong): ?>
        <div class="result warn"><?php echo '找不到对应的数据。'; ?></div>
    <?php elseif (!$hasKnown): ?>
        <div class="result warn"><?php echo '请输入查询条件。'; ?></div>
    <?php endif; ?>
</div>

<?php level_nav_current(); ?>

<?php require_once dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>
