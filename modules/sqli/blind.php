<?php
/**
 * bafanglab - 模块7d：SQL注入（布尔盲注）
 * 版权所有：八方网域-无涯
 */
require_once dirname(dirname(__DIR__)) . '/includes/i18n.php';
$page_title = 'SQL注入 - 布尔盲注';
require_once dirname(dirname(__DIR__)) . '/includes/header.php';
require_once dirname(dirname(__DIR__)) . '/includes/db.php';

$sql_shown = '';
$state = '';
$err = '';
if (isset($_GET['name'])) {
    $name = $_GET['name'];
    // 靶场故意设计：字符型拼接；页面只返回"有/无"，不回显字段内容
    $sql_shown = "SELECT id FROM users WHERE username = '$name'"; // @vuln
    $res = mysqli_query($conn, $sql_shown);
    if (!$res) {
        // 靶场设计：报错信息此处被"吃掉"，只给出统一提示，逼你用布尔差异判断
        $state = 'error';
        $err = mysqli_error($conn);
    } else {
        $state = mysqli_num_rows($res) > 0 ? 'yes' : 'no';
    }
}
?>
<a class="back-link" href="<?php echo BASE_URL; ?>/index.php">&larr; <?php echo t('footer.back'); ?></a>

<div class="panel">
    <h2><?php echo '模块7：SQL 注入'; ?></h2>
    <?php sqli_tabs('布尔盲注'); ?>
    <?php level_tools('basic', 'hint', true, true, 'tools', BASE_URL . '/modules/sqli/case.php'); ?>
    <?php
    level_code(__FILE__, null, <<<'FIX'
// 修复1：预处理语句——盲注的拼接入口被根除
$stmt = mysqli_prepare($conn, 'SELECT id FROM users WHERE username = ?');
mysqli_stmt_bind_param($stmt, 's', $name);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);

// 修复2：统一响应——无论存在/不存在/异常，返回相同结构与相近长度
//        （辅助手段，可降低布尔差异信号，但不能替代参数化）

// 修复3：对同一来源高频请求限速/封禁，显著提高盲注时间成本
//        （如 Nginx limit_req、应用层按 IP+账号计数）

// 修复4：关闭错误回显 + 最小权限数据库账号 + WAF 纵深防御
FIX);
    ?>
    <p class="desc">
        <?php echo '同一个"用户是否存在"接口，但这次<b>连报错都不给你看</b>了：页面只会告诉你两种结果——"存在"或"不存在"。<br>
        建议配合 Burp Intruder 或 Python 脚本自动化，手工猜一个字符就能体会原理。'; ?>
    </p>
    <?php level_goal('目标：构造 <code>and/or</code> 条件，让条件的真假决定页面返回哪一种结果，逐位猜出 <code>secrets</code> 表中的通关凭证。'); ?>

    <form method="get" action="<?php echo BASE_URL; ?>/modules/sqli/blind.php">
        <div class="form-row">
            <label><?php echo '用户名 name'; ?></label>
            <input type="text" name="name" style="max-width:640px"
                   value="<?php echo isset($_GET['name']) ? htmlspecialchars($_GET['name']) : 'admin'; ?>">
        </div>
        <button class="btn" type="submit"><?php echo '查询'; ?></button>
    </form>

    <?php if ($sql_shown !== ''): ?>
        <?php sql_toggle_button(); ?>
        <div class="result warn" data-sql-panel style="display:none"><?php echo '执行 SQL：'; ?><code><?php echo htmlspecialchars($sql_shown); ?></code></div>
        <?php if ($state === 'yes'): ?>
            <div class="result" style="border-color:var(--success)"><?php echo '查询结果：用户<b style="color:var(--success)">存在</b>。'; ?></div>
        <?php elseif ($state === 'no'): ?>
            <div class="result" style="border-color:var(--danger)"><?php echo '查询结果：用户<b style="color:var(--danger)">不存在</b>。'; ?></div>
        <?php else: ?>
            <div class="result error"><?php echo '查询异常。'; ?></div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php
// 双语文案统一从 content/<slug>/<variant>/<lang>/<piece>.html 加载
// site_tokens + 语言回退 + JSON 编码 全部由 level_content_js 自动处理
?>
<script>
<?php level_content_js('sqli', 'blind', 'basic', 'basic'); ?>
<?php level_content_js('sqli', 'blind', 'fix', 'fix'); ?>
<?php level_content_js('sqli', 'blind', 'bypass', 'bypass'); ?>
<?php level_content_js('sqli', 'blind', 'hint', 'hint'); ?>
<?php level_content_js('sqli', 'blind', 'tools', 'tools'); ?>
</script>

<?php level_nav_current(); ?>

<?php require_once dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>
