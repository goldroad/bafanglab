<?php
/**
 * bafanglab - 模块1：Burp 抓包基本功 · 练习3 修改响应（独立页面）
 * 版权所有：八方网域-无涯
 *
 * 靶场设计：练习3 是"修改响应"。接口 api/ex3.php 只返回短小的 text/plain（status + vip 开关），
 * 不含任何敏感数据；通关凭证以 base64 预置在本页面 JS 中。学员在 Burp 中拦截接口响应，
 * 把 vip=false 改成 vip=true 放行后，页面才解码显示通关凭证。
 */
require_once dirname(dirname(__DIR__)) . '/includes/i18n.php';
$page_title = 'Burp 练习3 · 修改响应';
require_once dirname(dirname(__DIR__)) . '/includes/header.php';
?>
<a class="back-link" href="<?php echo BASE_URL; ?>/index.php">&larr; <?php echo t('footer.back'); ?></a>

<div class="panel">
    <h2><?php echo '模块1：Burp 抓包基本功'; ?></h2>
    <?php burp_tabs('练习3 · 修改响应'); ?>
    <?php level_tools('basic', 'hint', false); ?>
    <?php
    level_code(false, array(), <<<'FIX'
// 练习3：响应篡改修复
// 权限判断必须完全在服务端完成，接口不下发开关，页面不下发敏感数据
// if (!$isVip) { exit("status=fail\nmsg=你不是 VIP\n"); }
// 页面 JS 中预置敏感内容（哪怕 base64 编码）同样不安全，编码不是加密
FIX);
    ?>
    <p class="desc">
        <?php echo '点击「加载 VIP 区域」会向接口 <code>modules/burp/api/ex3.php</code> 发送一条 GET 请求，
        接口返回<strong>短小的 text/plain 纯文本</strong>（仅 <code>status=ok</code> 与 <code>vip=false</code> 两行，不含通关凭证）。'; ?>
    </p>
    <?php level_goal('目标：在 Burp 中拦截这条接口响应，把 <code>vip=false</code> 改成 <code>vip=true</code> 后放行，
        页面即显示隐藏的通关凭证。'); ?>

    <button class="btn" type="button" onclick="loadVip()"><?php echo '加载 VIP 区域'; ?></button>
    <div class="result warn" id="vipBox"><?php echo 'VIP 区域尚未加载，请点击上方按钮。'; ?></div>
</div>

<script>
// 通关凭证以 base64 预置在页面中，仅在响应的 vip 开关被改为 true 时才解码显示
// （base64 只是编码不是加密，呼应"客户端藏数据不是安全边界"这一课）
var vipFlagB64 = '5oGt5Zac5L2g5a6M5oiQ5pys5YWz5oyR5oiY77yB';

function loadVip() {
    // 用异步请求访问接口，响应只填充到页面，避免浏览器跳到 api 地址只显示纯文本
    fetch('<?php echo BASE_URL; ?>/modules/burp/api/ex3.php', { cache: 'no-store' })
        .then(function (r) { return r.text(); })
        .then(function (t) {
            var d = kvParse(t);
            var box = document.getElementById('vipBox');
            if (d.vip === 'true') {
                box.className = 'result';
                box.innerHTML = '<b style="color:var(--success)"><?php echo 'VIP 内容：'; ?></b>' + kvEscape(atob(vipFlagB64));
            } else {
                box.className = 'result warn';
                box.innerHTML = '<?php echo '你不是 VIP，无法查看隐藏内容。'; ?>';
            }
        })
        .catch(function () {
            var box = document.getElementById('vipBox');
            box.className = 'result warn';
            box.innerHTML = '<?php echo '请求失败，请检查浏览器代理设置'; ?>';
        });
}
</script>

<?php level_nav_current(); ?>

<?php require_once dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>
