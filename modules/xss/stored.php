<?php
/**
 * bafanglab - 模块6b：存储型 XSS（留言板）
 * 版权所有：八方网域-无涯
 */
session_start();
require_once dirname(dirname(__DIR__)) . '/includes/i18n.php';
$page_title = '存储型 XSS';
require_once dirname(dirname(__DIR__)) . '/includes/header.php';
require_once dirname(dirname(__DIR__)) . '/includes/db.php';

// 提交留言（靶场故意设计：不过滤任何内容）
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nickname'], $_POST['content'])) {
    $n = $_POST['nickname'];
    $c = $_POST['content'];
    mysqli_query($conn, "INSERT INTO comments (nickname, content, create_time) VALUES ('$n', '$c', NOW())"); // @vuln
}

// 清空留言（方便练习）
if (isset($_GET['clear'])) {
    mysqli_query($conn, "TRUNCATE TABLE comments");
    header('Location: ' . BASE_URL . '/modules/xss/stored.php');
    exit;
}

// 删除单条留言（方便练习时清理某条测试留言）
if (isset($_GET['del'])) {
    $id = (int)$_GET['del'];
    if ($id > 0) {
        mysqli_query($conn, "DELETE FROM comments WHERE id = $id");
    }
    header('Location: ' . BASE_URL . '/modules/xss/stored.php');
    exit;
}
?>
<a class="back-link" href="<?php echo BASE_URL; ?>/index.php">&larr; <?php echo t('footer.back'); ?></a>

<div class="panel">
    <h2><?php echo '模块6：XSS 跨站脚本'; ?></h2>
    <?php xss_tabs('tab.xss_stored'); ?>
    <?php level_tools('basic', 'hint', true, false, 'tools', BASE_URL . '/modules/xss/case.php'); ?>
    <?php
    level_code(__FILE__, null, <<<'FIX'
// 修复1：入库用预处理语句（顺带修复 SQL 注入）
$stmt = mysqli_prepare($conn, 'INSERT INTO comments (nickname, content, create_time) VALUES (?, ?, NOW())');
mysqli_stmt_bind_param($stmt, 'ss', $n, $c);
mysqli_stmt_execute($stmt);

// 修复2：输出时按上下文转义——这是防存储型 XSS 的根本手段
echo '<b>' . htmlspecialchars($row['nickname'], ENT_QUOTES, 'UTF-8') . '</b>';
echo '<div>' . htmlspecialchars($row['content'], ENT_QUOTES, 'UTF-8') . '</div>';

// 修复3：确需富文本时用白名单库（如 HTMLPurifier），严禁 str_replace 黑名单过滤
// $clean = $purifier->purify($row['content']);

// 修复4：辅助手段——Cookie 设置 HttpOnly + SameSite，响应头加 CSP
FIX);
    ?>
    <p class="desc">
        <?php echo '留言内容会被存入数据库并展示给<b>所有访问者</b>。'; ?>
        <a href="<?php echo BASE_URL; ?>/modules/xss/stored.php?clear=1" style="color:var(--danger)"><?php echo '清空留言板'; ?></a>
    </p>
    <?php level_goal('目标：提交一条恶意留言，使得任何打开本页的人都会执行你的 JS（如弹出 cookie）。'); ?>

    <form method="post" action="<?php echo BASE_URL; ?>/modules/xss/stored.php">
        <div class="form-row">
            <label><?php echo '昵称'; ?></label>
            <input type="text" name="nickname" autocomplete="off">
        </div>
        <div class="form-row">
            <label><?php echo '留言内容'; ?></label>
            <textarea name="content"></textarea>
        </div>
        <button class="btn" type="submit"><?php echo '提交留言'; ?></button>
    </form>

    <h4><?php echo '留言列表'; ?></h4>
    <?php
    $res = mysqli_query($conn, "SELECT * FROM comments ORDER BY id DESC LIMIT 50");
    while ($row = mysqli_fetch_assoc($res)) {
        $delUrl = BASE_URL . '/modules/xss/stored.php?del=' . (int)$row['id'];
        echo '<div class="result" style="margin-top:10px;position:relative">';
        echo '<a href="' . htmlspecialchars($delUrl) . '" class="btn btn-sm btn-plain" style="position:absolute;right:8px;top:8px;color:var(--danger)" onclick="return confirm(\'确定删除这条留言？\')">'
            . '删除' . '</a>';
        echo '<b style="color:var(--accent)">' . $row['nickname'] . '</b> '; // @vuln
        echo '<span style="color:var(--text-muted);font-size:12px">' . $row['create_time'] . '</span>';
        // 靶场故意设计：原样输出，无 htmlspecialchars
        echo '<div style="margin-top:6px">' . $row['content'] . '</div>'; // @vuln
        echo '</div>';
    }
    ?>
</div>

<?php level_nav_current(); ?>

<?php require_once dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>
