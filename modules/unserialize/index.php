<?php
/**
 * bafanglab - 模块15：PHP 反序列化
 * 版权所有：八方网域-无涯
 */
require_once dirname(dirname(__DIR__)) . '/includes/i18n.php';
$page_title = '反序列化';

// 说明：PHP 在请求关闭阶段执行 __destruct 前会将 CWD 重置为 PHP 安装目录，
// 因此本模块中 __destruct 的相对路径写入会落到 PHP 目录，利用时应使用绝对路径（教学点）

// ================= 靶场故意设计：存在危险 __destruct 的类 =================
class Logger {
    public $logfile = 'access_log.txt';
    public $msg = 'page view';

    // 对象销毁时写日志 —— 属性可控 = 任意文件写
    public function __destruct() {
        @file_put_contents($this->logfile, $this->msg . "\n", FILE_APPEND); // @vuln
    }
}

class UserPrefs {
    public $nickname = 'guest';
    public $theme = 'dark';
}

// ================= 处理逻辑 =================
$msg = '';
$prefs = null;

// 保存偏好：序列化后放入 Cookie（教学演示常见错误写法）
if (isset($_POST['save'])) {
    $p = new UserPrefs();
    $p->nickname = $_POST['nickname'];
    $p->theme = $_POST['theme'];
    $ser = base64_encode(serialize($p));
    setcookie('prefs', $ser, time() + 3600, '/');
    $msg = '<div class="msg success">' . '偏好已保存！Cookie: prefs='
        . htmlspecialchars($ser) . '（刷新页面生效）' . '</div>';
}

// 读取偏好：反序列化 Cookie（靶场故意设计：直接 unserialize 用户可控输入）
if (isset($_COOKIE['prefs'])) {
    $data = base64_decode($_COOKIE['prefs']);
    $prefs = @unserialize($data); // @vuln
}

require_once dirname(dirname(__DIR__)) . '/includes/header.php';
?>
<a class="back-link" href="<?php echo BASE_URL; ?>/index.php">&larr; <?php echo t('footer.back'); ?></a>

<div class="panel">
    <h2><?php echo '模块15：PHP 反序列化'; ?></h2>
    <?php level_tools('basic', 'hint', true, false, 'tools', BASE_URL . '/modules/unserialize/case.php'); ?>
<?php
    level_code(__FILE__, null, <<<'FIX'
// 1. 首选方案：改用 JSON，彻底告别对象注入
setcookie('prefs', json_encode(array('nickname' => $n, 'theme' => $t)), time() + 3600, '/');
$prefs = json_decode($_COOKIE['prefs'], true);

// 2. 必须反序列化时：禁止实例化任何类（PHP >= 7.0）
$prefs = unserialize($data, array('allowed_classes' => false));

// 3. 数据完整性签名（HMAC），篡改即失效
$sig = hash_hmac('sha256', $data, $secret_key);
// 校验：hash_equals(hash_hmac('sha256', $data, $secret_key), $_COOKIE['prefs_sig'])
FIX);
    ?>
    <p class="desc">
        <?php echo '站点把你的"昵称/主题偏好"序列化后存在 Cookie 里，下次访问时 <code>unserialize()</code> 还原。'; ?>
    </p>
    <?php level_goal('目标：篡改 Cookie 中的序列化数据，利用 <code>Logger</code> 类的 <code>__destruct</code> 向服务器写入任意文件（WebShell）。'); ?>

    <?php echo $msg; ?>

    <?php if ($prefs instanceof UserPrefs): ?>
        <div class="result"><?php echo '欢迎回来，'; ?><b style="color:var(--accent)"><?php echo htmlspecialchars($prefs->nickname); ?></b>！
        <?php echo '当前主题：'; ?><?php echo htmlspecialchars($prefs->theme); ?></div>
    <?php elseif (isset($_COOKIE['prefs'])): ?>
        <div class="result warn"><?php echo '偏好对象已反序列化（非 UserPrefs 类型）。如果 Logger 被构造出来，它的 __destruct 已在页面结束时执行——去看看文件写入没有？'; ?></div>
    <?php endif; ?>

    <h4><?php echo '设置偏好'; ?></h4>
    <form method="post" action="<?php echo BASE_URL; ?>/modules/unserialize/index.php">
        <div class="form-row"><label><?php echo '昵称'; ?></label><input type="text" name="nickname" value="wuya"></div>
        <div class="form-row"><label><?php echo '主题'; ?></label>
            <select name="theme"><option>dark</option><option>light</option></select>
        </div>
        <button class="btn" type="submit" name="save" value="1"><?php echo '保存偏好'; ?></button>
    </form>
</div>

<?php level_nav_current(); ?>

<?php require_once dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>
