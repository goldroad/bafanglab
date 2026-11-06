<?php
/**
 * bafanglab - 第4关：JS 提取敏感信息（前端硬编码云存储 AK/SK）
 * 版权所有：八方网域-无涯
 */
require_once dirname(dirname(__DIR__)) . '/includes/i18n.php';
$page_title = 'JS 敏感信息泄露';
require_once dirname(dirname(__DIR__)) . '/includes/header.php';

// 靶场演示用的虚构阿里云 AK/SK（与 assets/app.js 中的值保持一致）
$FAKE_AK = 'LTAI5tB8fAnGLaBFakeKey01';
$FAKE_SK = 'FakeSecretBafangLab2026AbCdEfGh';

$msg = '';
if (isset($_POST['check_oss'])) {
    $ak = isset($_POST['ak']) ? trim($_POST['ak']) : '';
    $sk = isset($_POST['sk']) ? trim($_POST['sk']) : '';
    if ($ak === '' || $sk === '') {
        $msg = '<div class="msg fail">' . '请填写 AccessKeyId 与 AccessKeySecret。' . '</div>';
    } elseif ($ak === $FAKE_AK && $sk === $FAKE_SK) {
        $msg = '<div class="msg success">' . '凭证校验通过！已成功调用 OSS 配置接口：恭喜你完成本关挑战！' . '</div>';
    } else {
        $msg = '<div class="msg fail">' . '凭证无效，请到前端 JS 文件中寻找真实的 AccessKey 信息。' . '</div>';
    }
}
?>
<a class="back-link" href="<?php echo BASE_URL; ?>/index.php">&larr; <?php echo t('footer.back'); ?></a>

<div class="panel">
    <h2><?php echo '模块4：JS 敏感信息泄露'; ?></h2>
    <?php jsleak_tabs('tab.jsleak_js'); ?>
    <?php level_tools('basic', 'hint', true, true, 'tools', BASE_URL . '/modules/jsleak/case.php'); ?>
    <?php
    // 本关漏洞在前端 JS：展示 assets/app.js 并标红硬编码 AK/SK 行
    level_code(dirname(__FILE__) . '/assets/app.js', null, <<<'FIX'
// 修复1：AK/SK 绝不写进前端代码。前端通过后端接口换取 STS 临时凭证：
fetch('/api/sts_token.php')          // 后端登录后生成，有效期短、权限最小
  .then(r => r.json())
  .then(sts => { /* 用 sts 临时凭证访问 OSS */ });

// 修复2：后端 sts_token.php 中保管真实密钥（只允许服务端读取）
// $ak = getenv('OSS_ACCESS_KEY_ID');       // 从环境变量读取，不进代码库
// $sk = getenv('OSS_ACCESS_KEY_SECRET');

// 修复3：构建时用 CI 注入配置；生产环境删除 .js.map；
//        密钥一旦泄露立即到云平台轮换，并配置最小权限 + 访问审计
FIX);
    ?>
    <p class="desc">
        <?php echo '这是一个"员工门户"前端页面，页面加载了打包后的静态 JS 文件。<br>
        开发把云存储配置写死在前端代码里，导致 AccessKeyId / AccessKeySecret 泄露。'; ?>
    </p>
    <?php level_goal('目标：从 JS 中提取 AK/SK 并提交验证，拿到通关凭证。'); ?>

    <div class="result" id="portalStatus"><?php echo '正在加载前端资源……'; ?></div>

    <?php echo $msg; ?>
    <form method="post" action="<?php echo BASE_URL; ?>/modules/jsleak/index.php">
        <div class="form-row">
            <label>AccessKeyId</label>
            <input type="text" name="ak" style="max-width:640px" placeholder="<?php echo '例如 LTAI...'; ?>">
        </div>
        <div class="form-row">
            <label>AccessKeySecret</label>
            <input type="text" name="sk" style="max-width:640px" placeholder="<?php echo '例如 FakeSecret...'; ?>">
        </div>
        <button class="btn" type="submit" name="check_oss" value="1"><?php echo '验证凭证'; ?></button>
    </form>
</div>

<script src="<?php echo BASE_URL; ?>/modules/jsleak/assets/app.js"></script>

<?php
// 双语文案统一从 content/<slug>/<variant>/<lang>/<piece>.html 加载
// site_tokens + 语言回退 + JSON 编码 全部由 level_content_js 自动处理
?>
<script>
<?php level_content_js('jsleak', 'index', 'basic', 'basic'); ?>
<?php level_content_js('jsleak', 'index', 'fix', 'fix'); ?>
<?php level_content_js('jsleak', 'index', 'bypass', 'bypass'); ?>
<?php level_content_js('jsleak', 'index', 'hint', 'hint'); ?>
<?php level_content_js('jsleak', 'index', 'tools', 'tools'); ?>
</script>

<?php level_nav_current(); ?>

<?php require_once dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>
