<?php
/**
 * bafanglab - 模块12：SSRF
 * 版权所有：八方网域-无涯
 */
require_once dirname(dirname(__DIR__)) . '/includes/i18n.php';
$page_title = 'SSRF';
require_once dirname(dirname(__DIR__)) . '/includes/header.php';

$result = '';
$elapsed = 0;
if (isset($_GET['url']) && $_GET['url'] !== '') {
    $url = $_GET['url'];
    // 靶场故意设计：服务器端直接请求用户给的 URL，无任何校验
    $start = microtime(true);
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url); // @vuln
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // @vuln
    $body = curl_exec($ch);
    $info = curl_getinfo($ch);
    $err  = curl_error($ch);
    curl_close($ch);
    $elapsed = round(microtime(true) - $start, 2);

    if ($body === false) {
        $result = '<div class="result error">' . '请求失败：' . htmlspecialchars($err)
            . '（耗时 ' . $elapsed . 's）</div>';
    } else {
        $result = '<div class="result">'
            . 'HTTP 状态码：' . '<b>' . $info['http_code'] . '</b> | '
            . '耗时：' . $elapsed . 's | '
            . '响应大小：' . strlen($body) . ' 字节'
            . '<pre style="margin-top:10px;max-height:320px;overflow:auto;color:var(--code-text)">'
            . htmlspecialchars(substr($body, 0, 3000)) . '</pre></div>';
    }
}
?>
<a class="back-link" href="<?php echo BASE_URL; ?>/index.php">&larr; <?php echo t('footer.back'); ?></a>

<div class="panel">
    <h2><?php echo '模块12：SSRF 服务器端请求伪造'; ?></h2>
    <?php level_tools('basic', 'hint', true, false, 'tools', BASE_URL . '/modules/ssrf/case.php'); ?>
<?php
    level_code(__FILE__, null, <<<'FIX'
// 1. 协议白名单：只允许 http/https
$parts = parse_url($url);
if (!in_array(isset($parts['scheme']) ? $parts['scheme'] : '', array('http', 'https'))) {
    die('协议不允许');
}

// 2. 解析域名得到最终 IP，禁止内网/回环/保留地址（防 file:// 与内网探测）
$ip = gethostbyname($parts['host']);
if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
    die('禁止访问内网地址');
}

// 3. 禁止自动跳转，防止 302 重定向绕过上面的校验
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
FIX);
    ?>
    <p class="desc">
        <?php echo '这是一个"网页快照/信息采集"功能：输入 URL，服务器帮你抓取内容并展示。'; ?>
    </p>
    <?php level_goal('目标1：让服务器去访问它自己的内网服务（本机探测）。<br>
        目标2：用 <code>file://</code> 协议读取服务器本地文件。<br>
        目标3（进阶）：结合响应时间做内网端口扫描。'); ?>

    <form method="get" action="<?php echo BASE_URL; ?>/modules/ssrf/index.php">
        <div class="form-row">
            <label><?php echo '目标 URL'; ?></label>
            <input type="text" name="url" placeholder="http://example.com" style="max-width:640px"
                   value="<?php echo isset($_GET['url']) ? htmlspecialchars($_GET['url']) : ''; ?>">
        </div>
        <button class="btn" type="submit"><?php echo '抓取'; ?></button>
    </form>

    <?php echo $result; ?>
</div>

<?php level_nav_current(); ?>

<?php require_once dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>
