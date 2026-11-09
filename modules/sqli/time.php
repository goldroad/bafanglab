<?php
/**
 * bafanglab - 模块7e：SQL注入（时间盲注）
 * 版权所有：八方网域-无涯
 */
require_once dirname(dirname(__DIR__)) . '/includes/i18n.php';
$page_title = 'SQL注入 - 时间盲注';
require_once dirname(dirname(__DIR__)) . '/includes/header.php';
require_once dirname(dirname(__DIR__)) . '/includes/db.php';

$sql_shown = '';
$elapsed = null;
if (isset($_GET['name'])) {
    $name = $_GET['name'];
    // 靶场故意设计：字符型拼接；无论真假都返回同一句话，只能靠响应时间判断
    $sql_shown = "SELECT id FROM users WHERE username = '$name'"; // @vuln
    $start = microtime(true);
    @mysqli_query($conn, $sql_shown);
    $elapsed = round(microtime(true) - $start, 3);
}
?>
<a class="back-link" href="<?php echo BASE_URL; ?>/index.php">&larr; <?php echo t('footer.back'); ?></a>

<div class="panel">
    <h2><?php echo '模块7：SQL 注入'; ?></h2>
    <?php sqli_tabs('时间盲注'); ?>
    <?php level_tools('basic', 'hint', true, true, 'tools', BASE_URL . '/modules/sqli/case.php'); ?>
    <?php
    level_code(__FILE__, null, <<<'FIX'
// 修复1：预处理语句——sleep()/benchmark() 再无注入入口
$stmt = mysqli_prepare($conn, 'SELECT id FROM users WHERE username = ?');
mysqli_stmt_bind_param($stmt, 's', $name);
mysqli_stmt_execute($stmt);

// 修复2：设置查询超时，避免长查询拖垮连接（MySQL 5.7+）
// mysqli_query($conn, "SET SESSION max_execution_time = 1000");  // 毫秒

// 修复3：限制请求频率 + 慢查询监控告警，及时发现 time-based 探测
//        （页面不要向用户展示精确查询耗时）

// 修复4：统一响应文案与长度，最小权限数据库账号
FIX);
    ?>
    <?php level_goal('目标：不依赖报错、不比较页面长度，<b>只用响应时间</b>完成一次完整的盲注：<br>
        ① 先建立延时通道：让 <code>sleep(3)</code> 由注入条件控制，条件为真时响应明显变慢；<br>
        ② 再逐位提取：用 <code>ascii(substr((SELECT secret FROM secrets WHERE id=1),N,1))</code> 配合 <code>if()</code> 逐位比较，二分或枚举出每一位字符；<br>
        ③ 拼出完整字符串并核对结果：本关目标数据是 <code>secrets.secret</code>，最终应得到「恭喜你完成本关挑战！」。<br>
        <b>判定方式</b>：单次请求耗时 ≥ 3 秒视为条件成立，&lt; 1 秒视为不成立；页面会按秒显示本次耗时，可直接作为布尔信号。提示里是具体 payload 与二分脚本，建议先自己设计步骤再看。'); ?>

    <form method="get" action="<?php echo BASE_URL; ?>/modules/sqli/time.php">
        <div class="form-row">
            <label><?php echo '用户名 name'; ?></label>
            <input type="text" name="name" style="max-width:640px"
                   value="<?php echo isset($_GET['name']) ? htmlspecialchars($_GET['name']) : 'admin'; ?>">
        </div>
        <button class="btn" type="submit"><?php echo '查询'; ?></button>
    </form>

    <?php if ($elapsed !== null): ?>
        <?php sql_toggle_button(); ?>
        <div class="result warn" data-sql-panel style="display:none"><?php echo '执行 SQL：'; ?><code><?php echo htmlspecialchars($sql_shown); ?></code></div>
        <div class="result"><?php echo '本次查询耗时：'; ?><b style="color:var(--accent)"><?php echo $elapsed; ?> <?php echo '秒'; ?></b>
            <span style="color:var(--text-muted);font-size:12px"><?php echo '（正常查询通常 &lt; 0.01 秒）'; ?></span></div>
        <div class="result"><?php echo '查询结果：已处理。'; ?></div>
    <?php endif; ?>
</div>

<?php level_nav_current(); ?>

<?php require_once dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>
