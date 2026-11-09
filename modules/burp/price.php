<?php
/**
 * bafanglab - 模块1：Burp 抓包基本功 · 练习2 修改POST请求（独立页面）
 * 版权所有：八方网域-无涯
 *
 * 靶场设计：练习2 是"修改请求（POST 篡改金额）"，表单用异步请求提交到返回 text/plain 的接口，
 * 接口响应会被填充回本页面（页面不跳转），
 * 学员拦截这条 POST 请求把 price=9999 改成 price=0.01 放行即可通关。
 */
require_once dirname(dirname(__DIR__)) . '/includes/i18n.php';
$page_title = 'Burp 练习2 · 修改POST请求';
require_once dirname(dirname(__DIR__)) . '/includes/header.php';
?>
<a class="back-link" href="<?php echo BASE_URL; ?>/index.php">&larr; <?php echo t('footer.back'); ?></a>

<div class="panel">
    <h2><?php echo '模块1：Burp 抓包基本功'; ?></h2>
    <?php burp_tabs('练习2 · 修改POST请求'); ?>
    <?php level_tools('basic', 'hint', false); ?>
    <?php
    level_code(false, array(), <<<'FIX'
// 练习2：金额篡改修复
// 价格必须由服务端根据商品 ID 查询，禁止信任客户端提交的 price
$price = get_price_from_db((int)$_POST['goods_id']);
if ($price <= 0) { die('非法订单'); }
// 订单金额与支付回调均需校验签名与订单状态
FIX);
    ?>
    <p class="desc">
        <?php echo '点击「立即购买」会向接口 <code>modules/burp/api/ex2.php</code> 发送一条
        <strong>POST 请求</strong>（请求体为 <code>buy=1&amp;goods_id=1&amp;price=9999</code>），
        接口返回 <code>text/plain</code> 纯文本，页面再把响应内容填充到下方结果区，页面本身不跳转。'; ?>
    </p>
    <?php level_goal('目标：在 Burp 中拦截这条 POST 请求，把 <code>price=9999</code> 改成 <code>price=0.01</code> 后放行。'); ?>

    <form onsubmit="return doBuy()">
        <input type="hidden" name="goods_id" value="1">
        <input type="hidden" name="price" id="priceField" value="9999">
        <div class="form-row">
            <label><?php echo '商品：《Web安全秘籍》 价格：¥9999'; ?></label>
        </div>
        <button class="btn" type="submit"><?php echo '立即购买'; ?></button>
    </form>
    <div class="result" id="payResult" style="display:none"></div>
</div>

<script>
function doBuy() {
    var price = document.getElementById('priceField').value;
    // 用异步请求提交，响应只填充到页面，避免浏览器跳到 api 地址只显示纯文本
    fetch('<?php echo BASE_URL; ?>/modules/burp/api/ex2.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'buy=1&goods_id=1&price=' + encodeURIComponent(price),
        cache: 'no-store'
    })
        .then(function (r) { return r.text(); })
        .then(function (t) { showApiResult('payResult', t, ['ok']); })
        .catch(function () {
            showApiResult('payResult', 'status=fail\nmsg=<?php echo '请求失败，请检查浏览器代理设置'; ?>', ['ok']);
        });
    return false;
}
</script>

<?php level_nav_current(); ?>

<?php require_once dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>
