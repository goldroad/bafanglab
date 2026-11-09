<?php
/**
 * bafanglab - 模块6d：上传型 XSS（HTML / SVG / PDF）
 * 版权所有：八方网域-无涯
 *
 * 靶场设计说明：
 * 1. 上传只校验扩展名（html / htm / svg / pdf），完全不检查文件内容；
 * 2. 文件原样保存在 uploads/xss/，由 view.php 原样输出、不做任何转义；
 * 3. 学员上传带 JS 的 HTML / SVG / PDF 后，访问返回的文件地址即触发弹窗。
 */
require_once dirname(dirname(__DIR__)) . '/includes/i18n.php';

// 演示 cookie：XSS 成功后 alert(document.cookie) 即可看到通关凭证
if (!isset($_COOKIE['xss_upload_pass'])) {
    setcookie('xss_upload_pass', '恭喜你完成本关挑战！', time() + 86400,
        (BASE_URL === '' ? '/' : BASE_URL . '/'));
}

$page_title = '上传型 XSS';
require_once dirname(dirname(__DIR__)) . '/includes/header.php';

$up_dir  = UPLOAD_DIR . DIRECTORY_SEPARATOR . 'xss';
$allow_ext = array('html', 'htm', 'svg', 'pdf');

$msg = '';

// 删除已上传文件（方便反复练习）
if (isset($_GET['del'])) {
    $del = basename(str_replace('\\', '/', (string)$_GET['del']));
    $delPath = $up_dir . DIRECTORY_SEPARATOR . $del;
    if (is_file($delPath)) { @unlink($delPath); }
    header('Location: ' . BASE_URL . '/modules/xss/upload.php');
    exit;
}

// 靶场故意设计：只比较扩展名，不校验文件真实类型与内容
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['upfile'])) {
    $file = $_FILES['upfile'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $msg = '<div class="msg fail">' . '上传失败，错误码：' . (int)$file['error'] . '</div>';
    } else {
        // 只保留安全的文件名字符，防止目录穿越（本关漏洞点是 XSS，不是路径穿越）
        $name = basename(str_replace('\\', '/', $file['name']));
        $name = preg_replace('/[\x00-\x1f\x7f\/\\\\:*?"<>|]+/', '_', $name);
        $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION)); // @vuln

        if (!in_array($ext, $allow_ext, true)) { // @vuln
            $msg = '<div class="msg fail">' . '只允许上传 .html / .htm / .svg / .pdf 文件！' . '</div>';
        } elseif (!is_dir($up_dir) && !mkdir($up_dir, 0777, true)) {
            $msg = '<div class="msg fail">' . '创建上传目录失败。' . '</div>';
        } else {
            // 靶场故意设计：保留原始文件名，不重命名
            if (move_uploaded_file($file['tmp_name'], $up_dir . DIRECTORY_SEPARATOR . $name)) { // @vuln
                $view = BASE_URL . '/modules/xss/view.php?f=' . rawurlencode($name); // @vuln
                $msg = '<div class="msg success">' . '上传成功！在浏览器打开下面的地址即可触发弹窗：'
                    . '<a href="' . htmlspecialchars($view) . '" target="_blank" rel="noopener noreferrer" style="color:var(--success)">'
                    . htmlspecialchars($view) . '</a></div>';
            } else {
                $msg = '<div class="msg fail">' . '保存文件失败。' . '</div>';
            }
        }
    }
}

// 已上传文件列表
$files = array();
if (is_dir($up_dir)) {
    foreach ((array)scandir($up_dir) as $f) {
        if ($f === '.' || $f === '..' || !is_file($up_dir . DIRECTORY_SEPARATOR . $f)) { continue; }
        if (in_array(strtolower(pathinfo($f, PATHINFO_EXTENSION)), $allow_ext, true)) { $files[] = $f; }
    }
}
?>
<a class="back-link" href="<?php echo BASE_URL; ?>/index.php">&larr; <?php echo t('footer.back'); ?></a>

<div class="panel">
    <h2><?php echo '模块6：XSS 跨站脚本'; ?></h2>
    <?php xss_tabs('tab.xss_upload'); ?>
    <?php level_tools('basic', 'hint', true, false, 'tools', BASE_URL . '/modules/xss/case.php'); ?>
    <?php
    level_code(__FILE__, null, <<<'FIX'
// 修复1：只允许"不能被执行"的文件类型（图片白名单），禁止 html/svg/pdf/xml 等可解析类型
$allow = array('jpg', 'jpeg', 'png', 'gif', 'webp');
$ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
if (!in_array($ext, $allow, true)) { die('仅允许上传图片'); }

// 修复2：校验真实类型，防止把 HTML 内容伪装成图片扩展名
if (!getimagesize($file['tmp_name'])) { die('文件内容不是有效图片'); }

// 修复3：随机重命名 + 固定扩展名，禁止用户控制访问路径
$save = 'img_' . bin2hex(random_bytes(8)) . '.' . $ext;

// 修复4：上传文件放到独立域名 / 对象存储（不同源），并强制下载
// header('Content-Disposition: attachment; filename="download"');
// header('X-Content-Type-Options: nosniff');

// 修复5：响应头加 CSP，即使文件被浏览器解析也执行不了脚本
// header("Content-Security-Policy: default-src 'none'; sandbox");
FIX);
    ?>
    <p class="desc">
        <?php echo '头像 / 附件上传功能：服务器只检查扩展名，允许 <code>.html</code>、<code>.svg</code>、<code>.pdf</code>，并且上传后可以在<b>同源</b>直接打开。'; ?>
    </p>
    <?php level_goal('目标：上传一个带 JS 的 HTML 或 SVG 文件（PDF 亦可），打开返回的链接后弹出 <code>alert(document.cookie)</code>，拿到 cookie 中的通关凭证。'); ?>

    <?php echo $msg; ?>

    <form method="post" enctype="multipart/form-data" action="<?php echo BASE_URL; ?>/modules/xss/upload.php">
        <div class="form-row">
            <label><?php echo '选择文件（仅允许 html / htm / svg / pdf）'; ?></label>
            <input type="file" name="upfile">
        </div>
        <button class="btn" type="submit"><?php echo '上传文件'; ?></button>
    </form>

    <h4><?php echo '示例 payload 下载（下载后直接上传即可）'; ?></h4>
    <p class="desc">
        <?php echo '三个示例文件都可以用文本编辑器（记事本、Sublime 等）直接打开编辑：修改其中的弹窗内容或 payload 后再上传，
        不需要专门的开发工具。'; ?>
    </p>
    <div class="result">
        <?php
        $samples = array(
            'html' => 'HTML 文件（&lt;script&gt; 直接执行）',
            'svg'  => 'SVG 图片（&lt;script&gt; / onload 执行）',
            'pdf'  => 'PDF 文件（/OpenAction 或 HTML 混排）',
        );
        foreach ($samples as $type => $label) {
            $u = BASE_URL . '/modules/xss/sample.php?type=' . $type;
            echo '<div><a href="' . htmlspecialchars($u) . '" style="color:var(--accent)">'
                . $label . '</a></div>';
        }
        ?>
    </div>

    <h4><?php echo '已上传文件'; ?></h4>
    <div class="result">
        <?php if (empty($files)): ?>
            <?php echo '暂无上传文件。'; ?>
        <?php else: ?>
            <table>
                <tr>
                    <th><?php echo '文件'; ?></th>
                    <th><?php echo '大小'; ?></th>
                    <th><?php echo '访问链接（触发点）'; ?></th>
                    <th><?php echo '操作'; ?></th>
                </tr>
                <?php foreach ($files as $f): ?>
                    <?php $view = BASE_URL . '/modules/xss/view.php?f=' . rawurlencode($f); ?>
                    <tr>
                        <td><?php echo htmlspecialchars($f); ?></td>
                        <td><?php echo number_format(filesize($up_dir . DIRECTORY_SEPARATOR . $f)); ?> B</td>
                        <td><a href="<?php echo htmlspecialchars($view); ?>" target="_blank" rel="noopener noreferrer" style="color:var(--accent)"><?php echo htmlspecialchars($view); ?></a></td>
                        <td><a href="<?php echo BASE_URL; ?>/modules/xss/upload.php?del=<?php echo rawurlencode($f); ?>" style="color:var(--danger)"><?php echo '删除'; ?></a></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>
    </div>
</div>

<?php level_nav_current(); ?>

<?php require_once dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>
