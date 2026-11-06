<?php
/**
 * bafanglab - 模块1：Burp 抓包基本功 · 练习4 请求重放（独立页面）
 * 版权所有：八方网域-无涯
 *
 * 靶场设计：练习4 是"请求重放"，表单用异步请求提交到返回 text/plain 的接口，
 * 接口响应填充回本页面（页面不跳转），
 * 学员抓到这条 POST 请求后发送到 Repeater，原样重发两次观察重放检测。
 */
session_start();
require_once dirname(dirname(__DIR__)) . '/includes/i18n.php';
$page_title = 'Burp 练习4 · 请求重放';
require_once dirname(dirname(__DIR__)) . '/includes/header.php';

// 与接口共用同一组 session 键：已使用 token 列表 + 当前 token
if (!isset($_SESSION['used_tokens'])) { $_SESSION['used_tokens'] = array(); }
if (!isset($_SESSION['replay_token'])) { $_SESSION['replay_token'] = md5(uniqid('', true)); }
$cur_token = $_SESSION['replay_token'];
?>
<a class="back-link" href="<?php echo BASE_URL; ?>/index.php">&larr; <?php echo t('footer.back'); ?></a>

<div class="panel">
    <h2><?php echo '模块1：Burp 抓包基本功'; ?></h2>
    <?php burp_tabs('练习4 · 请求重放'); ?>
    <?php level_tools('basic', 'hint', false); ?>
    <?php
    level_code(false, array(), <<<'FIX'
// 练习4：重放修复
// token 一次性消费要用原子操作，并绑定用户与业务参数，放入数据库而非仅靠 session 数组
// UPDATE tokens SET used = 1 WHERE token = ? AND used = 0;  // 影响行数为 0 即重放
// 同时为关键操作增加幂等键与唯一约束，防止并发重复提交
FIX);
    ?>
    <p class="desc">
        <?php echo '下面是一次性 token 保护的转账表单，点击后会向接口 <code>modules/burp/api/ex4.php</code> 发送一条
        <strong>POST 请求</strong>，接口返回 <code>text/plain</code> 纯文本，页面再把响应填充到结果区，页面本身不跳转。'; ?>
    </p>
    <?php level_goal('目标：抓到这条 POST 请求后发送到 Repeater，<strong>原样重发两次</strong>，观察服务器如何识别重放。'); ?>
    <form onsubmit="return doTransfer()">
        <input type="hidden" name="token" id="tkField" value="<?php echo $cur_token; ?>">
        <div class="form-row">
            <label id="tkLabel"><?php echo '转账金额（一次性 token：' . $cur_token . '）'; ?></label>
            <input type="text" name="amount" id="amtField" value="100">
        </div>
        <button class="btn" type="submit"><?php echo '立即转账'; ?></button>
    </form>
    <div class="result" id="replayResult" style="display:none"></div>
</div>

<script>
function doTransfer() {
    var token = document.getElementById('tkField').value;
    var amount = document.getElementById('amtField').value;
    // 用异步请求提交，响应只填充到页面，避免浏览器跳到 api 地址只显示纯文本
    fetch('<?php echo BASE_URL; ?>/modules/burp/api/ex4.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'do_transfer=1&token=' + encodeURIComponent(token) + '&amount=' + encodeURIComponent(amount),
        cache: 'no-store'
    })
        .then(function (r) { return r.text(); })
        .then(function (t) {
            var d = kvParse(t);
            showApiResult('replayResult', t, ['ok']);
            // 转账成功后接口下发新 token，页面同步更新，避免"再点一次就被判重放"
            if (d.nextToken) {
                document.getElementById('tkField').value = d.nextToken;
                document.getElementById('tkLabel').textContent = '<?php echo '转账金额（一次性 token：'; ?>' + d.nextToken + '<?php echo '）'; ?>';
            }
        })
        .catch(function () {
            showApiResult('replayResult', 'status=fail\nmsg=<?php echo '请求失败，请检查浏览器代理设置'; ?>', ['ok']);
        });
    return false;
}
</script>

<?php
// 双语文案统一从 content/<slug>/<variant>/<lang>/<piece>.html 加载
// site_tokens + 语言回退 + JSON 编码 全部由 level_content_js 自动处理
?>
<script>
<?php level_content_js('burp', 'replay', 'basic', 'basic'); ?>
<?php level_content_js('burp', 'replay', 'fix', 'fix'); ?>
<?php level_content_js('burp', 'replay', 'bypass', 'bypass'); ?>
<?php level_content_js('burp', 'replay', 'hint', 'hint'); ?>
</script>

<?php level_nav_current(); ?>

<?php require_once dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>
