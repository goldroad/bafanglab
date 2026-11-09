<?php
/**
 * bafanglab - 模块1：Burp 抓包基本功 · 练习1 修改 GET 请求（独立页面）
 * 版权所有：八方网域-无涯
 *
 * 靶场设计：练习1 是"修改请求"，按钮用异步请求访问返回 text/plain 的接口，
 * 接口响应填充回本页面（页面不跳转），
 * 学员在 Burp 中拦截到的就是这条 GET 请求，把 role=user 改成 role=admin 放行即可。
 */
require_once dirname(dirname(__DIR__)) . '/includes/i18n.php';
$page_title = 'Burp 练习1 · 修改GET请求';
require_once dirname(dirname(__DIR__)) . '/includes/header.php';
?>
<a class="back-link" href="<?php echo BASE_URL; ?>/index.php">&larr; <?php echo t('footer.back'); ?></a>

<div class="panel">
    <h2><?php echo '模块1：Burp 抓包基本功'; ?></h2>
    <?php burp_tabs('练习1 · 修改GET请求'); ?>
    <?php level_tools('basic', 'hint', false); ?>
    <?php
    level_code(false, array(), <<<'FIX'
// 练习1：role 越权修复
// 不要信任客户端提交的 role 参数，角色应从服务端会话/数据库读取
$user = db_query('SELECT role FROM users WHERE id = ?', array($_SESSION['uid']));
if ($user['role'] === 'admin') {
    echo "msg=机密内容：恭喜你完成本关挑战！\n";
} else {
    echo "msg=普通用户无权查看机密内容\n";
}
FIX);
    ?>
    <p class="desc">
        <?php echo '点击下方按钮会向接口 <code>modules/burp/api/ex1.php?role=user</code> 发送一条 <strong>GET 请求</strong>，
        接口返回 <code>text/plain</code> 纯文本，页面再把响应内容填充到下方结果区，页面本身不跳转。'; ?>
    </p>
    <?php level_goal('目标：用 Burp 拦截这条 GET 请求，把 <code>role=user</code> 改成 <code>role=admin</code> 后放行。'); ?>

    <button class="btn btn-plain" type="button" onclick="loadRole()"><?php echo '以普通用户身份查看机密'; ?></button>
    <div class="result" id="roleResult" style="display:none"></div>
</div>

<script>
function loadRole() {
    // 用异步请求访问接口，响应只填充到页面，避免浏览器跳到 api 地址只显示纯文本
    fetch('<?php echo BASE_URL; ?>/modules/burp/api/ex1.php?role=user', { cache: 'no-store' })
        .then(function (r) { return r.text(); })
        .then(function (t) { showApiResult('roleResult', t, ['ok']); })
        .catch(function () {
            showApiResult('roleResult', 'status=fail\nmsg=<?php echo '请求失败，请检查浏览器代理设置'; ?>', ['ok']);
        });
}
</script>

<?php level_nav_current(); ?>

<?php require_once dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>
