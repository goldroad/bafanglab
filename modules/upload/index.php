<?php
/**
 * bafanglab - 模块9：文件上传（简单后端扩展名限制）
 * 版权所有：八方网域-无涯
 */
require_once dirname(dirname(__DIR__)) . '/includes/i18n.php';
$page_title = '文件上传';
require_once dirname(dirname(__DIR__)) . '/includes/header.php';

$msg = '';

// 删除已上传文件/文件夹（方便反复练习）
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['del'])) {
    $del = basename(str_replace('\\', '/', (string)$_POST['del']));
    $target = UPLOAD_DIR . DIRECTORY_SEPARATOR . $del;
    if ($del !== '' && $del !== '.' && $del !== '..') {
        if (is_dir($target)) {
            // 递归清空目录后删除目录本身（如 xss/）
            $it = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($target, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($it as $node) {
                if ($node->isDir()) { @rmdir($node->getPathname()); } else { @unlink($node->getPathname()); }
            }
            @rmdir($target);
        } elseif (is_file($target)) {
            @unlink($target);
        }
    }
    header('Location: ' . BASE_URL . '/modules/upload/index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['upfile'])) {
    $file = $_FILES['upfile'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $msg = '<div class="msg fail">' . '上传失败，错误码：' . $file['error'] . '</div>';
    } else {
        $name = $file['name'];
        // 靶场故意设计：只取最后一个点后的扩展名做黑名单比较，存在多种绕过
        $ext = strtolower(substr(strrchr($name, '.'), 1)); // @vuln
        $deny = array('php', 'php3', 'php4', 'php5', 'phtml'); // @vuln

        if (in_array($ext, $deny)) {
            $msg = '<div class="msg fail">' . '不允许上传 .'
                . htmlspecialchars($ext) . ' 类型的文件！' . '</div>';
        } else {
            if (!is_dir(UPLOAD_DIR)) { mkdir(UPLOAD_DIR, 0777, true); }
            // 靶场故意设计：使用原始文件名保存，未重命名
            $dest = UPLOAD_DIR . DIRECTORY_SEPARATOR . $name; // @vuln
            if (move_uploaded_file($file['tmp_name'], $dest)) { // @vuln
                $url = BASE_URL . '/uploads/' . $name;
                $msg = '<div class="msg success">' . '上传成功！文件地址：'
                    . '<a href="' . htmlspecialchars($url)
                    . '" target="_blank" style="color:var(--success)">' . htmlspecialchars($url) . '</a></div>';
            } else {
                $msg = '<div class="msg fail">' . '保存文件失败。' . '</div>';
            }
        }
    }
}
?>
<a class="back-link" href="<?php echo BASE_URL; ?>/index.php">&larr; <?php echo t('footer.back'); ?></a>

<div class="panel">
    <h2><?php echo '模块9：文件上传 —— 简单后端扩展名限制'; ?></h2>
    <?php level_tools('basic', 'hint', true, true, 'tools', BASE_URL . '/modules/upload/case.php'); ?>
<?php
    level_code(__FILE__, null, <<<'FIX'
// 1. 白名单校验扩展名（只允许图片）
$allow = array('jpg', 'jpeg', 'png', 'gif');
$ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
if (!in_array($ext, $allow)) { die('仅允许上传图片文件'); }

// 2. 校验真实文件类型，防图片马
if (!getimagesize($file['tmp_name'])) { die('文件内容不是合法图片'); }

// 3. 随机重命名，禁止用户控制保存文件名与路径
$dest = UPLOAD_DIR . DIRECTORY_SEPARATOR . uniqid('img_', true) . '.' . $ext;
move_uploaded_file($file['tmp_name'], $dest);

// 4. 上传目录在 Web 服务器配置中禁用脚本解析（Apache: php_flag engine off）
FIX);
    ?>
    <p class="desc">
        <?php echo '头像上传功能，服务器只检查了文件扩展名黑名单。'; ?>
    </p>
    <?php level_goal('目标：绕过限制，上传一个 PHP WebShell（如 <code>&lt;?php phpinfo();?&gt;</code>）并成功访问它。'); ?>

    <?php echo $msg; ?>

    <form method="post" enctype="multipart/form-data" action="<?php echo BASE_URL; ?>/modules/upload/index.php">
        <div class="form-row">
            <label><?php echo '选择头像文件（jpg/png/gif，禁止 php）'; ?></label>
            <input type="file" name="upfile">
        </div>
        <button class="btn" type="submit"><?php echo '上传'; ?></button>
    </form>

    <h4><?php echo '已上传文件'; ?></h4>
    <div class="result">
        <?php
        if (is_dir(UPLOAD_DIR)) {
            $files = array_diff(scandir(UPLOAD_DIR), array('.', '..'));
            if (empty($files)) { echo '暂无上传文件。'; }
            $confirmDel = '确定删除该项？删除后不可恢复。';
            foreach ($files as $f) {
                $isDir  = is_dir(UPLOAD_DIR . DIRECTORY_SEPARATOR . $f);
                $label  = $f . ($isDir ? '/' : '');
                $href   = BASE_URL . '/uploads/' . rawurlencode($f) . ($isDir ? '/' : '');
                echo '<div class="up-file-row">'
                    . '<a href="' . htmlspecialchars($href) . '" target="_blank" style="color:var(--accent)">'
                    . htmlspecialchars($label) . '</a>'
                    . '<form method="post" class="up-del-form" onsubmit="'
                    . htmlspecialchars("return confirm('" . addslashes($confirmDel) . "');", ENT_QUOTES) . '">'
                    . '<input type="hidden" name="del" value="' . htmlspecialchars($f, ENT_QUOTES) . '">'
                    . '<button class="up-del-btn" type="submit" title="' . '删除' . '">&#10060;</button>'
                    . '</form></div>';
            }
        }
        ?>
    </div>
</div>

<?php level_nav_current(); ?>

<?php require_once dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>
