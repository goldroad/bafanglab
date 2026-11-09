<?php
/**
 * bafanglab - 模块14：命令/代码执行 · 代码执行（独立页面）
 * 版权所有：八方网域-无涯
 */
require_once dirname(dirname(__DIR__)) . '/includes/i18n.php';
$page_title = 'RCE 代码执行';
require_once dirname(dirname(__DIR__)) . '/includes/header.php';

$out2 = '';
if (isset($_POST['code']) && $_POST['code'] !== '') {
    $code = $_POST['code'];
    ob_start();
    // 靶场故意设计：eval 直接执行用户输入
    eval($code); // @vuln
    $out2 = ob_get_clean();
}
// 输出为 HTML 文档（如 phpinfo()）时用沙箱 iframe 解析渲染，避免只看到源代码
$is_html = (bool) preg_match('/<(?:!DOCTYPE|html|head|body|div|table|h1|h2)\b/i', $out2);
?>
<a class="back-link" href="<?php echo BASE_URL; ?>/index.php">&larr; <?php echo t('footer.back'); ?></a>

<div class="panel">
    <h2><?php echo '模块14：命令/代码执行'; ?></h2>
    <?php rce_tabs('代码执行'); ?>
    <?php level_tools('basic', 'hint', true, false, 'tools', BASE_URL . '/modules/rce/case.php'); ?>
    <?php
    level_code(__FILE__, null, <<<'FIX'
// 代码执行修复：严禁 eval 执行用户输入
// 表达式计算需求应使用安全的解析库；若必须限制场景，做严格字符白名单：
if (preg_match('/^[\d+\-*\/(). ]+$/', $code)) {
    $result = eval('return ' . $code . ';');   // 仅限纯数字表达式
}

// 辅助手段：php.ini 禁用危险函数
// disable_functions = system,exec,shell_exec,passthru,proc_open,popen
FIX);
    ?>
    <p class="desc">
        <?php echo '"在线 PHP 计算器"：输入 PHP 表达式，服务器用 eval() 计算结果。'; ?>
    </p>
    <?php level_goal('目标：执行 <code>phpinfo()</code>，进阶写入 WebShell。'); ?>

    <form method="post" action="<?php echo BASE_URL; ?>/modules/rce/code.php">
        <div class="form-row">
            <label><?php echo 'PHP 代码'; ?></label>
            <input type="text" name="code" style="max-width:640px"
                   placeholder="<?php echo '请输入PHP代码，以分号结束。'; ?>">
        </div>
        <button class="btn" type="submit"><?php echo '执行'; ?></button>
    </form>

    <?php if ($out2 !== ''): ?>
        <div class="result"><b><?php echo '执行结果：'; ?></b>
            <?php if ($is_html): ?>
                <iframe class="html-preview" sandbox=""
                        srcdoc="<?php echo htmlspecialchars($out2, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"></iframe>
            <?php else: ?>
                <pre style="color:var(--code-text)"><?php echo htmlspecialchars($out2); ?></pre>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php level_nav_current(); ?>

<?php require_once dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>
