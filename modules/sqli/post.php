<?php
/**
 * bafanglab - 模块7b：SQL注入（POST 字符型）
 * 版权所有：八方网域-无涯
 */
session_start();
require_once dirname(dirname(__DIR__)) . '/includes/i18n.php';
$page_title = 'SQL注入 - POST';
require_once dirname(dirname(__DIR__)) . '/includes/header.php';
require_once dirname(dirname(__DIR__)) . '/includes/db.php';

$msg = '';
$showsql = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $u = isset($_POST['username']) ? $_POST['username'] : '';
    $p = isset($_POST['password']) ? $_POST['password'] : '';
    // 靶场故意设计：登录 SQL 直接拼接，可万能密码绕过
    $showsql = "SELECT * FROM users WHERE username = '$u' AND password = '$p'"; // @vuln
    $res = mysqli_query($conn, $showsql);
    if (!$res) {
        $msg = '<div class="msg fail">' . 'MySQL 错误：' . mysqli_error($conn) . '</div>';
    } elseif (mysqli_num_rows($res) > 0) {
        $row = mysqli_fetch_assoc($res);
        $_SESSION['sqli_user'] = $row['username'];
        $msg = '<div class="msg success">' . '登录成功！当前身份：'
            . htmlspecialchars($row['username'])
            . '（' . htmlspecialchars($row['role']) . '）—— 恭喜你完成本关挑战！</div>';
    } else {
        $msg = '<div class="msg fail">' . '用户名或密码错误。' . '</div>';
    }
}
?>
<a class="back-link" href="<?php echo BASE_URL; ?>/index.php">&larr; <?php echo t('footer.back'); ?></a>

<div class="panel">
    <h2><?php echo '模块7：SQL 注入'; ?></h2>
    <?php sqli_tabs('POST 字符型'); ?>
    <?php level_tools('basic', 'hint', true, true, 'tools', BASE_URL . '/modules/sqli/case.php'); ?>
    <?php
    level_code(__FILE__, null, <<<'FIX'
// 修复1：预处理语句 + 哈希校验密码（万能密码彻底失效）
$stmt = mysqli_prepare($conn, 'SELECT id, username, role, password_hash FROM users WHERE username = ?');
mysqli_stmt_bind_param($stmt, 's', $u);
mysqli_stmt_execute($stmt);
$row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if ($row && password_verify($p, $row['password_hash'])) {
    $_SESSION['sqli_user'] = $row['username'];   // 登录成功
} else {
    echo '用户名或密码错误';                      // 统一提示，不泄露 SQL 结构
}

// 修复2：PDO 写法
// $stmt = $pdo->prepare('SELECT * FROM users WHERE username = ?');
// $stmt->execute(array($u));

// 修复3：不要在页面上回显执行的 SQL（教学用途除外）
FIX);
    ?>
    <p class="desc">
        <?php echo '一个登录框，账号密码用 POST 提交。'; ?>
    </p>
    <?php level_goal('目标1：在不知道密码的情况下登录成功（万能密码）。<br>
        目标2（进阶）：通过 Burp 抓包用 sqlmap 跑出 <code>secrets</code> 表中的通关凭证。'); ?>

    <?php echo $msg; ?>

    <form method="post" action="<?php echo BASE_URL; ?>/modules/sqli/post.php">
        <div class="form-row">
            <label><?php echo '用户名'; ?></label>
            <input type="text" name="username" autocomplete="off">
        </div>
        <div class="form-row">
            <label><?php echo '密码'; ?></label>
            <input type="text" name="password" autocomplete="off">
        </div>
        <button class="btn" type="submit"><?php echo '登录'; ?></button>
    </form>

    <?php if ($showsql): ?>
        <?php sql_toggle_button(); ?>
        <div class="result warn" data-sql-panel style="display:none"><?php echo '执行 SQL：'; ?><code><?php echo htmlspecialchars($showsql); ?></code></div>
    <?php endif; ?>
</div>

<?php
// 双语文案统一从 content/<slug>/<variant>/<lang>/<piece>.html 加载
// site_tokens + 语言回退 + JSON 编码 全部由 level_content_js 自动处理
?>
<script>
<?php level_content_js('sqli', 'post', 'basic', 'basic'); ?>
<?php level_content_js('sqli', 'post', 'fix', 'fix'); ?>
<?php level_content_js('sqli', 'post', 'bypass', 'bypass'); ?>
<?php level_content_js('sqli', 'post', 'hint', 'hint'); ?>
<?php level_content_js('sqli', 'post', 'tools', 'tools'); ?>
</script>

<?php level_nav_current(); ?>

<?php require_once dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>
