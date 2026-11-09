<?php
/**
 * bafanglab - 模块11：CSRF
 * 版权所有：八方网域-无涯
 */
session_start();
require_once dirname(dirname(__DIR__)) . '/includes/i18n.php';
$page_title = 'CSRF';
require_once dirname(dirname(__DIR__)) . '/includes/header.php';
require_once dirname(dirname(__DIR__)) . '/includes/db.php';

$msg = '';

// 登录
if (isset($_POST['login'])) {
    $u = $_POST['username'];
    $p = $_POST['password'];
    $res = mysqli_query($conn, "SELECT * FROM users WHERE username='$u' AND password='$p'");
    if ($res && mysqli_num_rows($res) > 0) {
        $_SESSION['csrf_user'] = $u;
    } else {
        $msg = '<div class="msg fail">' . '登录失败。' . '</div>';
    }
}
if (isset($_GET['logout'])) {
    unset($_SESSION['csrf_user']);
    header('Location: ' . BASE_URL . '/modules/csrf/index.php');
    exit;
}

// 修改邮箱（靶场故意设计：GET 请求改数据 + 无 CSRF token + 不校验 Referer）
if (isset($_GET['newemail']) && isset($_SESSION['csrf_user'])) { // @vuln
    $em = $_GET['newemail'];
    $u  = $_SESSION['csrf_user'];
    mysqli_query($conn, "UPDATE users SET email='$em' WHERE username='$u'"); // @vuln
    $msg = '<div class="msg success">' . '邮箱已修改为：' . htmlspecialchars($em)
        . '（注意：这次请求可能是你自己发的，也可能是"别人"替你发的！）' . '</div>';
}
?>
<a class="back-link" href="<?php echo BASE_URL; ?>/index.php">&larr; <?php echo t('footer.back'); ?></a>

<div class="panel">
    <h2><?php echo '模块11：CSRF 跨站请求伪造'; ?></h2>
    <?php level_tools('basic', 'hint', true, false, 'tools', BASE_URL . '/modules/csrf/case.php'); ?>
<?php
    level_code(__FILE__, null, <<<'FIX'
// 1. 登录后生成一次性 CSRF Token 存入会话
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// 2. 敏感操作只接受 POST，并用 hash_equals 校验 Token
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['newemail'])
    && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    // 通过校验后才执行修改邮箱
}

// 3. 表单中输出隐藏域：
// <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

// 4. 会话 Cookie 设置 SameSite（PHP >= 7.3 写法）：
// setcookie('PHPSESSID', session_id(), array('samesite' => 'Lax'));
FIX);
    ?>
    <p class="desc">
        <?php echo '用户中心的"修改邮箱"功能没有 CSRF 防护。'; ?>
    </p>
    <?php level_goal('目标：构造一个恶意页面，只要已登录用户<b>点开它</b>，他的邮箱就会被悄悄改掉。'); ?>

    <?php echo $msg; ?>

    <?php if (!isset($_SESSION['csrf_user'])): ?>
        <h4><?php echo '第一步：登录受害者账号（test / 123456）'; ?></h4>
        <form method="post" action="<?php echo BASE_URL; ?>/modules/csrf/index.php">
            <div class="form-row"><label><?php echo '用户名'; ?></label><input type="text" name="username" value="test"></div>
            <div class="form-row"><label><?php echo '密码'; ?></label><input type="password" name="password" value="123456"></div>
            <button class="btn" type="submit" name="login" value="1"><?php echo '登录'; ?></button>
        </form>
    <?php else:
        $u = $_SESSION['csrf_user'];
        $res = mysqli_query($conn, "SELECT * FROM users WHERE username='$u'");
        $row = mysqli_fetch_assoc($res);
    ?>
        <div class="result">
            <?php echo '当前登录：'; ?><b style="color:var(--accent)"><?php echo htmlspecialchars($row['username']); ?></b><br>
            <?php echo '当前邮箱：'; ?><b style="color:var(--warn)"><?php echo htmlspecialchars($row['email']); ?></b>
            <a href="<?php echo BASE_URL; ?>/modules/csrf/index.php?logout=1" style="color:var(--danger);margin-left:15px"><?php echo '退出登录'; ?></a>
        </div>

        <h4><?php echo '修改我的邮箱（正常功能）'; ?></h4>
        <form method="get" action="<?php echo BASE_URL; ?>/modules/csrf/index.php">
            <div class="form-row"><label><?php echo '新邮箱'; ?></label><input type="text" name="newemail" placeholder="new@example.com"></div>
            <button class="btn" type="submit"><?php echo '修改'; ?></button>
        </form>

        <h4><?php echo '第二步：模拟攻击者 —— 恶意链接'; ?></h4>
        <div class="result warn">
            <?php echo '攻击者只需要把下面这个链接发给已登录的你：<br>'; ?>
            <code style="color:var(--danger)"><?php echo BASE_URL; ?>/modules/csrf/index.php?newemail=hacker@evil.com</code><br>
            <a href="<?php echo BASE_URL; ?>/modules/csrf/index.php?newemail=hacker@evil.com"
               style="color:var(--accent)"><?php echo '【模拟受害者点击】"免费领取网络安全资料大礼包！"'; ?></a><br><br>
            <?php echo '攻击者还可以做成自动提交的 HTML 页面（img 标签/自动 form 提交），受害者打开即中招。'; ?>
        </div>
    <?php endif; ?>
</div>

<?php level_nav_current(); ?>

<?php require_once dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>
