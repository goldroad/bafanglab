<?php
/**
 * bafanglab - 模块7a：SQL注入（GET 数字型）
 * 版权所有：八方网域-无涯
 */
require_once dirname(dirname(__DIR__)) . '/includes/i18n.php';
$page_title = 'SQL注入 - GET';
require_once dirname(dirname(__DIR__)) . '/includes/header.php';
require_once dirname(dirname(__DIR__)) . '/includes/db.php';
?>
<a class="back-link" href="<?php echo BASE_URL; ?>/index.php">&larr; <?php echo t('footer.back'); ?></a>

<div class="panel">
    <h2><?php echo '模块7：SQL 注入'; ?></h2>
    <?php sqli_tabs('GET 数字型'); ?>
    <?php level_tools('basic', 'hint', true, true, 'tools', BASE_URL . '/modules/sqli/case.php'); ?>
    <?php
    level_code(__FILE__, null, <<<'FIX'
// 修复1：MySQLi 预处理语句——参数与 SQL 结构分离，从根上杜绝注入
$stmt = mysqli_prepare($conn, 'SELECT id, username, email, role FROM users WHERE id = ?');
mysqli_stmt_bind_param($stmt, 'i', $id);       // 'i' 强制整型
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);

// 修复2：数字型参数兜底——强制类型转换 + 白名单
$id = intval($_GET['id']);

// 修复3：PDO 写法（推荐）
// $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
// $stmt->execute(array($id));

// 修复4：生产环境关闭错误回显 display_errors=Off；数据库账号最小权限
FIX);
    ?>
    <p class="desc">
        <?php echo '用户详情页通过 URL 参数 <code>id</code> 查询数据库。<br>
        本关以 <b>Burp Suite</b> 手工注入为主，进阶可用 sqlmap 自动化验证。'; ?>
    </p>
    <?php level_goal('目标：利用注入漏洞，查询出 <code>secrets</code> 表中藏着的通关凭证。'); ?>

    <p>
        <?php echo '试试这些链接：'; ?>
        <a href="<?php echo BASE_URL; ?>/modules/sqli/get.php?id=1" style="color:var(--accent)">id=1</a> |
        <a href="<?php echo BASE_URL; ?>/modules/sqli/get.php?id=2" style="color:var(--accent)">id=2</a> |
        <a href="<?php echo BASE_URL; ?>/modules/sqli/get.php?id=3" style="color:var(--accent)">id=3</a>
    </p>

    <?php
    if (isset($_GET['id'])) {
        $id = $_GET['id']; // @vuln
        // 靶场故意设计：参数直接拼接进 SQL，无任何过滤
        $sql = "SELECT id, username, email, role FROM users WHERE id = $id"; // @vuln
        sql_toggle_button();
        echo '<div class="result warn" data-sql-panel style="display:none">' . '执行 SQL：' . '<code>' . htmlspecialchars($sql) . '</code></div>';

        $res = mysqli_query($conn, $sql);
        if (!$res) {
            // 靶场故意设计：回显 MySQL 报错，便于报错注入教学
            echo '<div class="result error">' . 'MySQL 错误：' . mysqli_error($conn) . '</div>';
        } else {
            echo '<div class="result"><table><tr><th>ID</th><th>' . '用户名' . '</th><th>'
                . '邮箱' . '</th><th>' . '角色' . '</th></tr>';
            while ($row = mysqli_fetch_assoc($res)) {
                echo '<tr><td>' . $row['id'] . '</td><td>' . $row['username'] . '</td><td>'
                    . $row['email'] . '</td><td>' . $row['role'] . '</td></tr>';
            }
            echo '</table></div>';
        }
    }
    ?>

</div>
<?php level_nav_current(); ?>

<?php require_once dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>
