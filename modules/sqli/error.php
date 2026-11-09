<?php
/**
 * bafanglab - 模块7c：SQL注入（报错注入）
 * 版权所有：八方网域-无涯
 */
require_once dirname(dirname(__DIR__)) . '/includes/i18n.php';
$page_title = 'SQL注入 - 报错注入';
require_once dirname(dirname(__DIR__)) . '/includes/header.php';
require_once dirname(dirname(__DIR__)) . '/includes/db.php';

$sql_shown = '';
$err = '';
$exists = null;
if (isset($_GET['name'])) {
    $name = $_GET['name'];
    // 靶场故意设计：字符型参数直接拼接，且回显 MySQL 报错信息
    $sql_shown = "SELECT id, username, role FROM users WHERE username = '$name'"; // @vuln
    $res = mysqli_query($conn, $sql_shown);
    if (!$res) {
        $err = mysqli_error($conn);
    } else {
        $exists = mysqli_num_rows($res) > 0;
    }
}
?>
<a class="back-link" href="<?php echo BASE_URL; ?>/index.php">&larr; <?php echo t('footer.back'); ?></a>

<div class="panel">
    <h2><?php echo '模块7：SQL 注入'; ?></h2>
    <?php sqli_tabs('报错注入'); ?>
    <?php level_tools('basic', 'hint', true, true, 'tools', BASE_URL . '/modules/sqli/case.php'); ?>
    <?php
    level_code(__FILE__, null, <<<'FIX'
// 修复1：预处理语句——报错注入的拼接入口被根除
$stmt = mysqli_prepare($conn, 'SELECT id, username, role FROM users WHERE username = ?');
mysqli_stmt_bind_param($stmt, 's', $name);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);

// 修复2：生产环境关闭错误回显，错误只写日志
// php.ini: display_errors = Off; log_errors = On
// ini_set('display_errors', '0');

// 修复3：统一异常处理，对外只返回通用提示
if (!$res) {
    error_log('SQL error: ' . mysqli_error($conn));
    echo '系统繁忙，请稍后再试';            // 不输出 mysqli_error()
}

// 修复4：数据库账号最小权限，配合 WAF 纵深防御
FIX);
    ?>
    <p class="desc">
        <?php echo '这是一个"用户是否存在"的查询接口，正常只会告诉你结果真假，<b>不会把数据回显到表格里</b>。<br>
        但服务端把 MySQL 报错信息原样输出了，于是可以借助报错函数把 <code>secrets</code> 表的数据"夹带"出来。'; ?>
    </p>
    <?php level_goal('目标：用 <code>updatexml</code> / <code>extractvalue</code> / <code>floor</code> 等报错函数读取 <code>secrets</code> 表中的通关凭证。'); ?>

    <p>
        <?php echo '试试这些链接：'; ?>
        <a href="<?php echo BASE_URL; ?>/modules/sqli/error.php?name=admin" style="color:var(--accent)">name=admin</a> |
        <a href="<?php echo BASE_URL; ?>/modules/sqli/error.php?name=wuya" style="color:var(--accent)">name=wuya</a>
    </p>

    <form method="get" action="<?php echo BASE_URL; ?>/modules/sqli/error.php">
        <div class="form-row">
            <label><?php echo '用户名 name'; ?></label>
            <input type="text" name="name" style="max-width:640px"
                   value="<?php echo isset($_GET['name']) ? htmlspecialchars($_GET['name']) : ''; ?>">
        </div>
        <button class="btn" type="submit"><?php echo '查询'; ?></button>
    </form>

    <?php if ($sql_shown !== ''): ?>
        <?php sql_toggle_button(); ?>
        <div class="result warn" data-sql-panel style="display:none"><?php echo '执行 SQL：'; ?><code><?php echo htmlspecialchars($sql_shown); ?></code></div>
        <?php if ($err !== ''): ?>
            <!-- 靶场故意设计：直接把 MySQL 报错输出到页面 -->
            <div class="result error"><?php echo 'MySQL 错误：'; ?><?php echo htmlspecialchars($err); ?></div>
        <?php elseif ($exists): ?>
            <div class="result"><?php echo '查询结果：该用户<b>存在</b>。'; ?></div>
        <?php else: ?>
            <div class="result"><?php echo '查询结果：该用户<b>不存在</b>。'; ?></div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php level_nav_current(); ?>

<?php require_once dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>
