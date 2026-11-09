<?php
/**
 * bafanglab - 模块5a：密码暴力猜解（无验证码）
 * 版权所有：八方网域-无涯
 */
session_start();
require_once dirname(dirname(__DIR__)) . '/includes/i18n.php';
$page_title = '暴力猜解（无验证码）';
require_once dirname(dirname(__DIR__)) . '/includes/header.php';
require_once dirname(dirname(__DIR__)) . '/includes/db.php';

$msg = '';
$failText = '用户名或密码错误。';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $u = isset($_POST['username']) ? $_POST['username'] : '';
    $p = isset($_POST['password']) ? $_POST['password'] : '';
    // 靶场故意设计：无任何频率限制、无验证码、无锁定策略
    $sql = "SELECT * FROM users WHERE username = '$u' AND password = '$p'"; // @vuln
    $res = mysqli_query($conn, $sql);
    if ($res && mysqli_num_rows($res) > 0) {
        $row = mysqli_fetch_assoc($res);
        $msg = '<div class="msg success">'
            . '登录成功！欢迎 '
            . htmlspecialchars($row['username']) . '（' . $row['role'] . '）—— 恭喜你完成本关挑战！</div>';
    } else {
        $msg = '<div class="msg fail">' . $failText . '</div>';
    }
}
?>
<a class="back-link" href="<?php echo BASE_URL; ?>/index.php">&larr; <?php echo t('footer.back'); ?></a>

<div class="panel">
    <h2><?php echo '模块5：密码暴力猜解'; ?></h2>
    <?php brute_tabs('tab.brute_nocap'); ?>
    <?php level_tools('basic', 'hint', true, true, 'tools', BASE_URL . '/modules/brute/case.php'); ?>
    <?php
    level_code(__FILE__, null, <<<'FIX'
// 修复1：登录失败计数 + 锁定（示例用 session，生产建议用 Redis/数据库按 IP+账号计数）
if (!isset($_SESSION['fail_count'])) { $_SESSION['fail_count'] = 0; }
if ($_SESSION['fail_count'] >= 5) {
    die('失败次数过多，账号已临时锁定，请 15 分钟后再试');
}

// 修复2：密码比较用哈希，查询用预处理（顺带修复 SQL 注入）
$stmt = mysqli_prepare($conn, 'SELECT id, username, password_hash FROM users WHERE username = ?');
mysqli_stmt_bind_param($stmt, 's', $u);
mysqli_stmt_execute($stmt);
$row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if ($row && password_verify($p, $row['password_hash'])) {
    $_SESSION['fail_count'] = 0;               // 登录成功清零
    // 登录成功逻辑...
} else {
    $_SESSION['fail_count']++;                 // 失败计数 +1
    echo '用户名或密码错误';                    // 统一提示，防账号枚举
}

// 修复3：叠加图形/行为验证码 + IP 限速 + 异常登录告警，形成纵深防护
FIX);
    ?>
    <p class="desc">
        <?php echo '本登录口没有任何防护：无验证码、无频率限制、无失败锁定。'; ?>
    </p>
    <?php level_goal('目标：用 Burp Intruder 或 hydra 挂上字典爆破出 admin 的真实密码，并登录拿到通关凭证。'); ?>

    <p style="margin-bottom:18px">
        <b><?php echo '练习字典：'; ?></b>
        <a class="btn btn-plain btn-sm" href="<?php echo BASE_URL; ?>/includes/dict_download.php?type=passwords"><?php echo '下载密码字典 dict_passwords.txt'; ?></a>
    </p>

    <?php echo $msg; ?>

    <form method="post" action="<?php echo BASE_URL; ?>/modules/brute/index.php">
        <div class="form-row">
            <label><?php echo '用户名'; ?></label>
            <input type="text" name="username" autocomplete="off">
        </div>
        <div class="form-row">
            <label><?php echo '密码'; ?></label>
            <input type="password" name="password">
        </div>
        <button class="btn" type="submit"><?php echo '登录'; ?></button>
    </form>
</div>

<?php level_nav_current(); ?>

<?php require_once dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>
