<?php
/**
 * bafanglab - 模块14：命令/代码执行 · 命令执行（独立页面）
 * 版权所有：八方网域-无涯
 */
require_once dirname(dirname(__DIR__)) . '/includes/i18n.php';
$page_title = 'RCE 命令执行';
require_once dirname(dirname(__DIR__)) . '/includes/header.php';

$out1 = '';
if (isset($_GET['ip']) && $_GET['ip'] !== '') {
    $ip = $_GET['ip'];
    // 靶场故意设计：直接拼接系统命令（跨平台自适应 ping 参数与输出编码，保留命令注入漏洞）
    if (DIRECTORY_SEPARATOR === '\\') {
        // Windows：ping -n 指定次数，中文系统输出为 GBK 需转码
        $cmd = "ping -n 2 " . $ip; // @vuln
        $out1 = shell_exec($cmd); // @vuln
        $out1 = mb_convert_encoding($out1, 'UTF-8', 'GBK');
    } else {
        // Linux/Unix：ping -c 指定次数，输出默认为 UTF-8
        $cmd = "ping -c 2 " . $ip; // @vuln
        $out1 = shell_exec($cmd); // @vuln
    }
}
?>
<a class="back-link" href="<?php echo BASE_URL; ?>/index.php">&larr; <?php echo t('footer.back'); ?></a>

<div class="panel">
    <h2><?php echo '模块14：命令/代码执行'; ?></h2>
    <?php rce_tabs('命令执行'); ?>
    <?php level_tools('basic', 'hint', true, false, 'tools', BASE_URL . '/modules/rce/case.php'); ?>
    <?php
    level_code(__FILE__, null, <<<'FIX'
// 命令执行修复：白名单校验输入 + 参数转义，绝不直接拼接
if (!filter_var($ip, FILTER_VALIDATE_IP)) { die('非法 IP 地址'); }
// 跨平台选择 ping 参数（Windows 用 -n，Linux 用 -c）
$flag = (DIRECTORY_SEPARATOR === '\\') ? '-n' : '-c';
$out = shell_exec('ping ' . $flag . ' 2 ' . escapeshellarg($ip));

// 辅助手段：php.ini 禁用危险函数
// disable_functions = system,exec,shell_exec,passthru,proc_open,popen
FIX);
    ?>
    <p class="desc">
        <?php echo '网络诊断工具：输入 IP 地址，服务器执行 ping 命令。'; ?>
    </p>
    <?php level_goal('目标：通过命令拼接，让服务器执行你自己的系统命令（如查看目录、写 WebShell），最终读取 <code>secret.txt</code> 中的通关凭证。'); ?>

    <form method="get" action="<?php echo BASE_URL; ?>/modules/rce/index.php">
        <div class="form-row">
            <label><?php echo 'IP 地址'; ?></label>
            <input type="text" name="ip" style="max-width:640px"
                   value="<?php echo isset($_GET['ip']) ? htmlspecialchars($_GET['ip']) : '127.0.0.1'; ?>">
        </div>
        <button class="btn" type="submit">Ping</button>
    </form>

    <?php if ($out1 !== ''): ?>
        <div class="result"><pre style="color:var(--code-text)"><?php echo htmlspecialchars($out1); ?></pre></div>
    <?php endif; ?>
</div>

<?php
// 双语文案统一从 content/<slug>/<variant>/<lang>/<piece>.html 加载
// site_tokens + 语言回退 + JSON 编码 全部由 level_content_js 自动处理
?>
<script>
<?php level_content_js('rce', 'index', 'basic', 'basic'); ?>
<?php level_content_js('rce', 'index', 'fix', 'fix'); ?>
<?php level_content_js('rce', 'index', 'bypass', 'bypass'); ?>
<?php level_content_js('rce', 'index', 'hint', 'hint'); ?>
</script>

<?php level_nav_current(); ?>

<?php require_once dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>
