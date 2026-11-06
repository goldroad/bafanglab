<?php
/**
 * bafanglab - 模块5b：密码暴力猜解（有验证码）
 * 版权所有：八方网域-无涯
 */
session_start();
require_once dirname(dirname(__DIR__)) . '/includes/i18n.php';
$page_title = '暴力猜解（有验证码）';
require_once dirname(dirname(__DIR__)) . '/includes/header.php';
require_once dirname(dirname(__DIR__)) . '/includes/db.php';

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $u = isset($_POST['username']) ? $_POST['username'] : '';
    $p = isset($_POST['password']) ? $_POST['password'] : '';
    $c = isset($_POST['captcha']) ? $_POST['captcha'] : '';

    // 靶场故意设计：验证码已一次性使用（防复用），但验证码本身为高对比度低干扰样式 => 可被 OCR 自动识别
    $ok = isset($_SESSION['captcha']) && strtolower($c) === strtolower($_SESSION['captcha']);
    unset($_SESSION['captcha']);   // 用后即销毁，不可复用，逼迫每次重新识别
    if (!$ok) {
        $msg = '<div class="msg fail">' . '验证码错误。' . '</div>';
    } else {
        $sql = "SELECT * FROM users WHERE username = '$u' AND password = '$p'";
        $res = mysqli_query($conn, $sql);
        if ($res && mysqli_num_rows($res) > 0) {
            $row = mysqli_fetch_assoc($res);
            $msg = '<div class="msg success">' . '登录成功！欢迎 '
                . htmlspecialchars($row['username']) . ' —— 恭喜你完成本关挑战！</div>';
        } else {
            $msg = '<div class="msg fail">' . '用户名或密码错误。' . '</div>';
        }
    }
}
?>
<a class="back-link" href="<?php echo BASE_URL; ?>/index.php">&larr; <?php echo t('footer.back'); ?></a>

<div class="panel">
    <h2><?php echo '模块5：密码暴力猜解'; ?></h2>
    <?php brute_tabs('tab.brute_cap'); ?>
    <?php level_tools('basic', 'hint', true, true, 'tools', BASE_URL . '/modules/brute/case.php'); ?>
    <?php
    level_code(__DIR__ . '/captcha_code.php', null, <<<'FIX'
// 修复方向：验证码本身要"难识别"，而不仅仅是"一次性"

// 1. 用后即销毁（防复用）—— 已做
$ok = isset($_SESSION['captcha'])
   && strtolower($c) === strtolower($_SESSION['captcha']);
unset($_SESSION['captcha']);
if (!$ok) { die('验证码错误'); }

// 2. 验证码短有效期 + 会话绑定
if (time() - $_SESSION['captcha_time'] > 120) {
    unset($_SESSION['captcha']);
    die('验证码已过期，请刷新');
}

// 3. 核心：让验证码"不易被 OCR 识别"
//    - 字符旋转/扭曲、字体大小与颜色随机
//    - 增加干扰线/干扰点密度并使用与字符相近的颜色
//    - 字符间距随机、加噪点、背景渐变/噪点
$img = imagecreatetruecolor(120, 40);
$bg  = imagecolorallocate($img, 230, 230, 240);
imagefilledrectangle($img, 0, 0, 120, 40, $bg);
for ($i = 0; $i < 4; $i++) {
    $ch = substr($code, $i, 1);
    $angle = mt_rand(-25, 25);                       // 随机旋转
    $color = imagecolorallocate($img, mt_rand(0,80), mt_rand(0,80), mt_rand(120,200));
    imagefttext($img, 22, $angle, 12 + $i*26, 30, $color, 'font.ttf', $ch);
}
// 高密度干扰线 / 干扰点
for ($i = 0; $i < 8; $i++) {
    $lc = imagecolorallocate($img, mt_rand(100,200), mt_rand(100,200), mt_rand(100,200));
    imageline($img, mt_rand(0,120), mt_rand(0,40), mt_rand(0,120), mt_rand(0,40), $lc);
}

// 4. 密码哈希校验 + 预处理语句（同时修复 SQL 注入）
$stmt = mysqli_prepare($conn, 'SELECT password_hash FROM users WHERE username = ?');
mysqli_stmt_bind_param($stmt, 's', $u);
mysqli_stmt_execute($stmt);
$row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
if (!$row || !password_verify($p, $row['password_hash'])) {
    die('用户名或密码错误');
}

// 5. 再叠加失败次数锁定 + IP 限速
FIX);
    ?>
    <p class="desc">
        <?php echo '这个登录口的验证码<b>用后即销毁</b>、不可复用——但验证码样式过于简单（白底黑字、低干扰、固定字体）。'; ?>
    </p>
    <?php level_goal('目标：用 OCR 自动识别每次请求的验证码，再配合字典完成爆破并登录拿到通关凭证。'); ?>

    <p style="margin-bottom:18px">
        <b><?php echo '练习字典：'; ?></b>
        <a class="btn btn-plain btn-sm" href="<?php echo BASE_URL; ?>/modules/brute/dict_passwords.txt" download="dict_passwords.txt"><?php echo '下载密码字典 dict_passwords.txt'; ?></a>
    </p>

    <?php echo $msg; ?>

    <form method="post" action="<?php echo BASE_URL; ?>/modules/brute/captcha.php">
        <div class="form-row">
            <label><?php echo '用户名'; ?></label>
            <input type="text" name="username" autocomplete="off">
        </div>
        <div class="form-row">
            <label><?php echo '密码'; ?></label>
            <input type="password" name="password">
        </div>
        <div class="form-row">
            <label><?php echo '验证码（点击图片可刷新）'; ?></label>
            <input type="text" name="captcha" autocomplete="off" maxlength="4"><br>
            <img class="captcha-img" id="capimg" src="<?php echo BASE_URL; ?>/modules/brute/captcha_code.php" alt="captcha"
                 onclick="this.src='captcha_code.php?t='+Math.random()">
        </div>
        <button class="btn" type="submit"><?php echo '登录'; ?></button>
    </form>
</div>

<?php
// 双语文案统一从 content/<slug>/<variant>/<lang>/<piece>.html 加载
// site_tokens + 语言回退 + JSON 编码 全部由 level_content_js 自动处理
?>
<script>
<?php level_content_js('brute', 'captcha', 'basic', 'basic'); ?>
<?php level_content_js('brute', 'captcha', 'fix', 'fix'); ?>
<?php level_content_js('brute', 'captcha', 'bypass', 'bypass'); ?>
<?php level_content_js('brute', 'captcha', 'hint', 'hint'); ?>
<?php level_content_js('brute', 'captcha', 'tools', 'tools'); ?>
</script>

<?php level_nav_current(); ?>

<?php require_once dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>
