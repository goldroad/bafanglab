<?php
/**
 * bafanglab - 模块6a：反射型 XSS
 * 版权所有：八方网域-无涯
 */
require_once dirname(dirname(__DIR__)) . '/includes/i18n.php';
$page_title = '反射型 XSS';
require_once dirname(dirname(__DIR__)) . '/includes/header.php';
?>
<a class="back-link" href="<?php echo BASE_URL; ?>/index.php">&larr; <?php echo t('footer.back'); ?></a>

<div class="panel">
    <h2><?php echo '模块6：XSS 跨站脚本'; ?></h2>
    <?php xss_tabs('tab.xss_reflect'); ?>
    <?php level_tools('basic', 'hint', true, false, 'tools', BASE_URL . '/modules/xss/case.php'); ?>
    <?php
    level_code(__FILE__, null, <<<'FIX'
// 修复1：HTML 标签之间输出——htmlspecialchars 转义（ENT_QUOTES 连单引号一起转）
echo '搜索结果：没有找到与 "' . htmlspecialchars($_GET['q'], ENT_QUOTES, 'UTF-8') . '" 相关的内容。';

// 修复2：HTML 属性内输出——同样转义，并始终用引号包裹属性值
echo '<input type="text" name="q" value="' . htmlspecialchars($q, ENT_QUOTES, 'UTF-8') . '">';

// 修复3：输出到 JS 字符串上下文时用 json_encode，而不是 htmlspecialchars
echo '<script>var q = ' . json_encode($q, JSON_UNESCAPED_UNICODE) . ';</script>';

// 修复4：辅助手段——CSP 响应头限制脚本来源 + Cookie 加 HttpOnly
// header("Content-Security-Policy: default-src 'self'");
FIX);
    ?>
    <p class="desc">
        <?php echo '这是一个"搜索"功能，你提交的关键词会被原样回显到页面上。'; ?>
    </p>
    <?php level_goal('目标：构造 payload 弹出 <code>alert(document.cookie)</code> 或 <code>alert(1)</code>，拿到 cookie 中的通关凭证。'); ?>

    <form method="get" action="<?php echo BASE_URL; ?>/modules/xss/reflect.php">
        <div class="form-row">
            <label><?php echo '搜索关键词'; ?></label>
            <input type="text" name="q" value="<?php echo isset($_GET['q']) ? $_GET['q'] : ''; // @vuln ?>">
        </div>
        <button class="btn" type="submit"><?php echo '搜索'; ?></button>
    </form>

    <?php if (isset($_GET['q']) && $_GET['q'] !== ''): ?>
        <div class="result">
            <!-- 靶场故意设计：无任何过滤的输出 -->
            <?php echo '搜索结果：没有找到与 "'; ?><?php echo $_GET['q']; // @vuln ?><?php echo '" 相关的内容。'; ?>
        </div>
    <?php endif; ?>
</div>

<?php
// 双语文案统一从 content/<slug>/<variant>/<lang>/<piece>.html 加载
// site_tokens + 语言回退 + JSON 编码 全部由 level_content_js 自动处理
?>
<script>
<?php level_content_js('xss', 'reflect', 'basic', 'basic'); ?>
<?php level_content_js('xss', 'reflect', 'fix', 'fix'); ?>
<?php level_content_js('xss', 'reflect', 'bypass', 'bypass'); ?>
<?php level_content_js('xss', 'reflect', 'hint', 'hint'); ?>
</script>

<?php level_nav_current(); ?>

<?php require_once dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>
