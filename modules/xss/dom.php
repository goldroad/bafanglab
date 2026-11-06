<?php
/**
 * bafanglab - 模块6c：DOM 型 XSS
 * 版权所有：八方网域-无涯
 */
require_once dirname(dirname(__DIR__)) . '/includes/i18n.php';
$page_title = 'DOM 型 XSS';
require_once dirname(dirname(__DIR__)) . '/includes/header.php';
?>
<a class="back-link" href="<?php echo BASE_URL; ?>/index.php">&larr; <?php echo t('footer.back'); ?></a>

<div class="panel">
    <h2><?php echo '模块6：XSS 跨站脚本'; ?></h2>
    <?php xss_tabs('tab.xss_dom'); ?>
    <?php level_tools('basic', 'hint', true, false, 'tools', BASE_URL . '/modules/xss/case.php'); ?>
    <?php
    level_code(__FILE__, null, <<<'FIX'
// 修复1：纯文本输出用 textContent，而不是 innerHTML（浏览器不会解析标签）
function renderDomHash() {
    var raw = location.hash ? location.hash.substring(1) : '';
    var box = document.getElementById('domOut');
    box.textContent = raw;            // 安全：任何 <script>/<img> 都按纯文本显示
}

// 修复2：确需渲染 HTML 时先用 DOMPurify 白名单净化
// box.innerHTML = DOMPurify.sanitize(raw);

// 修复3：危险 sink 禁用清单——eval / new Function / document.write /
//        字符串形式的 setTimeout 一律不碰用户可控数据

// 修复4：辅助手段——CSP 禁止内联脚本：default-src 'self'; script-src 'self'
FIX);
    ?>
    <p class="desc">
        <?php echo '本页面把 URL 中 <code>#</code> 后面的内容直接写入 <code>innerHTML</code>，整个过程都在浏览器里完成，不经过服务器——这就是 DOM 型 XSS。'; ?>
    </p>
    <?php level_goal('目标：在 URL 的 <code>#</code> 后面构造 payload，让页面执行你的 JS，并把隐藏的通关凭证显示出来。'); ?>

    <form onsubmit="return applyPayload();">
        <div class="form-row">
            <label><?php echo '要写入 URL # 后面的 payload'; ?></label>
            <input type="text" id="payloadInput" style="max-width:640px"
                   placeholder="<?php echo '例如 &lt;svg onload=alert(1)&gt;'; ?>">
        </div>
        <button class="btn" type="submit"><?php echo '写入 Hash 并执行'; ?></button>
    </form>

    <h4><?php echo '渲染结果（innerHTML 输出点）'; ?></h4>
    <div class="result" id="domOut"></div>

    <!-- Lab by design: the 通关凭证 hides in a node that is easiest to reach through DOM XSS -->
    <span id="secretZone" style="display:none">恭喜你完成本关挑战！</span>

    <p style="margin-top:12px;color:var(--text-muted);font-size:12px">
        <?php echo '提示：payload 会保留在 URL Hash 中，可直接把带 payload 的完整链接发给别人。'; ?>
    </p>
</div>

<script>
// 靶场故意设计：直接取 location.hash 并写入 innerHTML，无任何过滤（DOM-XSS 注入点）
function renderDomHash() {
    var raw = location.hash ? location.hash.substring(1) : ''; // @vuln
    var box = document.getElementById('domOut');
    if (!raw) {
        box.innerHTML = '<span style="color:var(--text-muted)"><?php echo '等待输入 payload……试试把 &lt;svg onload=alert(1)&gt; 加到 URL 的 # 后面'; ?></span>';
        return;
    }
    var payload = raw;
    try { payload = decodeURIComponent(raw); } catch (e) {}
    box.innerHTML = payload; // @vuln
}
function applyPayload() {
    var v = document.getElementById('payloadInput').value;
    location.hash = encodeURIComponent(v);
    return false;
}
window.addEventListener('hashchange', renderDomHash);
window.addEventListener('load', renderDomHash);
</script>

<?php
// 双语文案统一从 content/<slug>/<variant>/<lang>/<piece>.html 加载
// site_tokens + 语言回退 + JSON 编码 全部由 level_content_js 自动处理
?>
<script>
<?php level_content_js('xss', 'dom', 'basic', 'basic'); ?>
<?php level_content_js('xss', 'dom', 'fix', 'fix'); ?>
<?php level_content_js('xss', 'dom', 'bypass', 'bypass'); ?>
<?php level_content_js('xss', 'dom', 'hint', 'hint'); ?>
</script>

<?php level_nav_current(); ?>

<?php require_once dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>
