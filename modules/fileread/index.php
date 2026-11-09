<?php
/**
 * bafanglab - 模块13：任意文件读取（目录穿越）
 * 版权所有：八方网域-无涯
 */
require_once dirname(dirname(__DIR__)) . '/includes/i18n.php';
$page_title = '任意文件读取';
require_once dirname(dirname(__DIR__)) . '/includes/header.php';

$content = '';
if (isset($_GET['file']) && $_GET['file'] !== '') {
    $file = $_GET['file'];
    // 靶场故意设计：未过滤 ../，且未禁止绝对路径（真实业务中常见的两种缺陷）
    // 跨平台：Windows 盘符绝对路径（C:/）、Windows UNC 路径（\\）、Linux 绝对路径（/）均直接使用
    if (preg_match('/^[a-zA-Z]:[\\\\\/]/', $file) || strpos($file, '\\\\') === 0 || strpos($file, '/') === 0) { // @vuln
        $path = $file; // @vuln 绝对路径直接使用
    } else {
        $path = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'files' . DIRECTORY_SEPARATOR . $file; // @vuln
    }

    if (is_file($path)) {
        $data = @file_get_contents($path);
        $content = '<div class="result"><b>' . '文件：' . '</b>' . htmlspecialchars($path)
            . '<pre style="margin-top:10px;color:var(--code-text);max-height:400px;overflow:auto">'
            . htmlspecialchars($data) . '</pre></div>';
    } else {
        $content = '<div class="result error">' . '文件不存在或不可读：' . htmlspecialchars($path) . '</div>';
    }
}
?>
<a class="back-link" href="<?php echo BASE_URL; ?>/index.php">&larr; <?php echo t('footer.back'); ?></a>

<div class="panel">
    <h2><?php echo '模块13：任意文件读取（目录穿越）'; ?></h2>
    <?php level_tools('basic', 'hint', true, false, 'tools', BASE_URL . '/modules/fileread/case.php'); ?>
<?php
    level_code(__FILE__, null, <<<'FIX'
// 1. 固定根目录 + basename 去掉用户输入中的路径
$base = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'files' . DIRECTORY_SEPARATOR;
$path = $base . basename($_GET['file']);

// 2. realpath 规范化后，校验最终路径仍在允许目录内
$real = realpath($path);
if ($real === false || strpos($real, realpath($base)) !== 0) {
    die('非法文件路径');
}
echo file_get_contents($real);

// 更优方案：ID -> 文件名白名单映射，用户输入永远不进入路径
FIX);
    ?>
    <p class="desc">
        <?php echo '资料下载功能，只能读取 <code>files/</code> 目录下的文件。'; ?>
    </p>
    <?php level_goal('目标1：跳出 files 目录，读取站点配置文件 <code>config.php</code>。<br>
        目标2：读取系统文件（如 Windows 的 <code>win.ini</code> 或 <code>hosts</code>）。'); ?>

    <p>
        <?php echo '公开文件：'; ?>
        <a href="<?php echo BASE_URL; ?>/modules/fileread/index.php?file=readme.txt" style="color:var(--accent)">readme.txt</a> |
        <a href="<?php echo BASE_URL; ?>/modules/fileread/index.php?file=notice.txt" style="color:var(--accent)">notice.txt</a>
    </p>

    <form method="get" action="<?php echo BASE_URL; ?>/modules/fileread/index.php">
        <div class="form-row">
            <label><?php echo '文件名'; ?></label>
            <input type="text" name="file" style="max-width:640px"
                   value="<?php echo isset($_GET['file']) ? htmlspecialchars($_GET['file']) : 'readme.txt'; ?>">
        </div>
        <button class="btn" type="submit"><?php echo '读取'; ?></button>
    </form>

    <?php echo $content; ?>
</div>

<?php level_nav_current(); ?>

<?php require_once dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>
