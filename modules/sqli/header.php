<?php
/**
 * bafanglab - 模块7：SQL注入（请求头注入）
 * 版权所有：八方网域-无涯
 */
require_once dirname(dirname(__DIR__)) . '/includes/i18n.php';
$page_title = 'SQL注入 - 请求头注入';
require_once dirname(dirname(__DIR__)) . '/includes/header.php';
require_once dirname(dirname(__DIR__)) . '/includes/db.php';

$showsql = '';
$err = '';
$ua = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
$xff = isset($_SERVER['HTTP_X_FORWARDED_FOR']) ? $_SERVER['HTTP_X_FORWARDED_FOR'] : '';

// 靶场故意设计：把 User-Agent 请求头直接拼进 SQL，且回显 MySQL 报错
// 真实场景多见于"访问日志 / 审计记录"把 UA / X-Forwarded-For 入库的代码
$showsql = "SELECT id, username, role FROM users WHERE username = '$ua'"; // @vuln
$res = mysqli_query($conn, $showsql);
$rows = array();
if (!$res) {
    $err = mysqli_error($conn);
} else {
    while ($row = mysqli_fetch_assoc($res)) {
        $rows[] = $row;
    }
}
?>
<a class="back-link" href="<?php echo BASE_URL; ?>/index.php">&larr; <?php echo t('footer.back'); ?></a>

<div class="panel">
    <h2><?php echo '模块7：SQL 注入'; ?></h2>
    <?php sqli_tabs('请求头注入'); ?>
    <?php level_tools('basic', 'hint', true, true, 'tools', BASE_URL . '/modules/sqli/case.php'); ?>
    <?php
    level_code(__FILE__, null, <<<'FIX'
// 修复1：预处理语句——请求头作为绑定参数传入，彻底杜绝拼接
$stmt = mysqli_prepare($conn, 'SELECT id, username, role FROM users WHERE username = ?');
mysqli_stmt_bind_param($stmt, 's', $ua);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);

// 修复2：对请求头做白名单/长度/字符集校验
// User-Agent 允许可打印 ASCII，长度限制 256；X-Forwarded-For 只允许 IP 格式
if (!preg_match('/^[\x20-\x7E]{1,256}$/', $ua)) {
    $ua = '';  // 非法值置空，不进入 SQL
}

// 修复3：生产环境关闭错误回显，错误只写日志
// php.ini: display_errors = Off; log_errors = On
if (!$res) {
    error_log('SQL error: ' . mysqli_error($conn));
    echo '系统繁忙，请稍后再试';            // 不输出 mysqli_error()
}

// 修复4：不要信任任何客户端可控的请求头（X-Forwarded-For 可伪造），
//        真实客户端 IP 应由代理层通过受信头传递并校验。
FIX);
    ?>
    <p class="desc">
        <?php echo '很多系统会把 <code>User-Agent</code>、<code>X-Forwarded-For</code>、<code>Referer</code> '
            . '等请求头写进数据库做访问统计或审计，却<b>忘了对请求头做过滤</b>。<br>'
            . '本关服务端把 <code>User-Agent</code> 请求头直接拼进 SQL，你可以用 Burp 或 curl 修改 UA 来注入。'; ?>
    </p>
    <?php level_goal('目标：修改 <code>User-Agent</code> 请求头，注入报错函数，'
        . '从 MySQL 报错信息中读出 <code>secrets</code> 表的通关凭证。'); ?>

    <div class="result" style="margin-bottom:12px">
        <?php echo '当前 User-Agent：'; ?><code><?php echo htmlspecialchars($ua); ?></code><br>
        <?php echo '当前 X-Forwarded-For：'; ?><code><?php echo htmlspecialchars($xff ?: '(未设置)'); ?></code>
    </div>

    <?php sql_toggle_button(); ?>
    <div class="result warn" data-sql-panel style="display:none"><?php echo '执行 SQL：'; ?><code><?php echo htmlspecialchars($showsql); ?></code></div>
    <?php if ($err !== ''): ?>
        <!-- 靶场故意设计：直接把 MySQL 报错输出到页面 -->
        <div class="result error"><?php echo 'MySQL 错误：'; ?><?php echo htmlspecialchars($err); ?></div>
    <?php elseif (!empty($rows)): ?>
        <div class="result"><table><tr><th>ID</th><th><?php echo '用户名'; ?></th><th><?php echo '角色'; ?></th></tr>
        <?php foreach ($rows as $row): ?>
            <tr><td><?php echo (int)$row['id']; ?></td><td><?php echo htmlspecialchars($row['username']); ?></td><td><?php echo htmlspecialchars($row['role']); ?></td></tr>
        <?php endforeach; ?>
        </table></div>
    <?php else: ?>
        <div class="result"><?php echo '查询结果：无匹配数据（UA 中没有对应用户名）。'; ?></div>
    <?php endif; ?>
</div>

<?php level_nav_current(); ?>

<?php require_once dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>
