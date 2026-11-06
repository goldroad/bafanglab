<?php
/**
 * bafanglab - 模块7f：SQL注入（文件读写 INTO OUTFILE / LOAD_FILE）
 * 版权所有：八方网域-无涯
 *
 * 靶场设计说明：
 * 1. 「写入」「读取」两个输入框的内容都会作为参数 id 的完整 payload 直接拼进 SQL，不做任何过滤；
 * 2. 读取结果必须由注入的 LOAD_FILE() 返回，页面不做 readfile / file_get_contents 直接读文件；
 * 3. 写入是否成功由文件是否落地判定，通关由 LOAD_FILE 读回内容是否命中通关凭证判定。
 */
require_once dirname(dirname(__DIR__)) . '/includes/i18n.php';
$page_title = 'SQL注入 - 文件读写';
require_once dirname(dirname(__DIR__)) . '/includes/header.php';
require_once dirname(dirname(__DIR__)) . '/includes/db.php';

// 本关只允许读写模块目录下的 files/（已由 MySQL 的 secure-file-priv 限定）
$filesDir = str_replace('\\', '/', __DIR__ . '/files');
$flagPath = $filesDir . '/flag.txt';
$shellPath = $filesDir . '/shell.php';

// 清理本关写入的文件，方便重复练习
if (isset($_GET['reset'])) {
    foreach (array($flagPath, $shellPath) as $f) {
        if (is_file($f)) { @unlink($f); }
    }
}

$expected = '恭喜你完成本关挑战！';
$step     = isset($_GET['step']) ? (string)$_GET['step'] : '';
$wVal     = isset($_GET['w']) ? (string)$_GET['w'] : '';
$rVal     = isset($_GET['r']) ? (string)$_GET['r'] : '';
$inputVal = ($step === 'read') ? $rVal : $wVal;
?>
<a class="back-link" href="<?php echo BASE_URL; ?>/index.php">&larr; <?php echo t('footer.back'); ?></a>

<div class="panel">
    <h2><?php echo '模块7：SQL 注入'; ?></h2>
    <?php sqli_tabs('文件读写'); ?>
    <?php level_tools('basic', 'hint', true, true, 'tools', BASE_URL . '/modules/sqli/case.php'); ?>
    <?php
    level_code(__FILE__, null, <<<'FIX'
// 修复1：参数化查询——注入点被消除，攻击者无法再拼接 INTO OUTFILE / UNION
$stmt = mysqli_prepare($conn, 'SELECT id, username, email, role FROM users WHERE id = ?');
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);

// 修复2：数据库账号最小权限——业务账号不授予 FILE 权限
// 执行一次即可：REVOKE FILE ON *.* FROM 'bafang'@'localhost';

// 修复3：固定 secure_file_priv（MySQL 5.7+ 无法用 SET GLOBAL 修改，必须在 my.ini 配置）
// my.ini:
//   secure-file-priv=""                          ← 完全禁用文件导入导出
//   或 secure-file-priv="D:/mysql-files"         ← 只允许指定目录，且该目录不可被 Web 访问

// 修复4：Web 目录禁止脚本执行（Apache: php_admin_flag engine off；Nginx: location ~ \.php$ { deny all; }）

// 修复5：关闭详细错误回显，避免路径/权限信息泄露


FIX);
    ?>
    <?php level_goal('目标：利用同一个数字型注入点，先把数据库中的通关凭证写入指定文件（写入），
        再用注入的 <code>LOAD_FILE()</code> 把文件内容读回页面（读取）；<b>文件成功落地，且读回内容与写入内容一致</b>即通关。
        '); ?>

    <p class="desc">
        <?php echo '前五关都在“把数据读出来”，这一关反向利用：<b>把查询结果写进服务器文件，再通过数据库把它读回来</b>。<br>
        注入点与 GET 关相同（数字型 <code>id</code>，4 列结果）。下面「写入」「读取」两个输入框都只接收完整的 <code>id</code> payload，
        提交后统一由这条注入语句执行——写入走 <code>INTO OUTFILE</code>，读取走 <code>LOAD_FILE()</code>，页面本身不会直接读取文件。<br>
        可写目录：<code>' . htmlspecialchars($filesDir) . '</code>（已由 MySQL <code>secure_file_priv</code> 限定，仅此目录允许读写）'; ?>
    </p>

    <p>
        <?php echo '试试这些链接确认注入点：'; ?>
        <a href="<?php echo BASE_URL; ?>/modules/sqli/outfile.php?id=1" style="color:var(--accent)">id=1</a> |
        <a href="<?php echo BASE_URL; ?>/modules/sqli/outfile.php?id=2" style="color:var(--accent)">id=2</a> |
        <a href="<?php echo BASE_URL; ?>/modules/sqli/outfile.php?id=3" style="color:var(--accent)">id=3</a>
    </p>

    <form method="get" action="<?php echo BASE_URL; ?>/modules/sqli/outfile.php">
        <input type="hidden" name="step" value="write">
        <div class="form-row">
            <label><?php echo '写入 · 参数 id（自行构造 INTO OUTFILE payload）'; ?></label>
            <input type="text" name="w" style="max-width:900px" placeholder="在此粘贴完整的 id 参数值"
                   value="<?php echo htmlspecialchars($wVal); ?>">
        </div>
        <button class="btn" type="submit"><?php echo '执行写入'; ?></button>
    </form>

    <form method="get" action="<?php echo BASE_URL; ?>/modules/sqli/outfile.php">
        <input type="hidden" name="step" value="read">
        <div class="form-row">
            <label><?php echo '读取 · 参数 id（自行构造 LOAD_FILE payload）'; ?></label>
            <input type="text" name="r" style="max-width:900px" placeholder="在此粘贴完整的 id 参数值"
                   value="<?php echo htmlspecialchars($rVal); ?>">
        </div>
        <button class="btn" type="submit"><?php echo '执行读取'; ?></button>
        <a class="btn btn-plain btn-sm" href="<?php echo BASE_URL; ?>/modules/sqli/outfile.php?reset=1"><?php echo '清理本关写入的文件'; ?></a>
    </form>

    <?php
    $readBuf = '';
    if ($step !== '' && $inputVal !== '') {
        // 靶场故意设计：参数直接拼接进 SQL，无任何过滤（这是本关的漏洞点）
        $sql = "SELECT id, username, email, role FROM users WHERE id = $inputVal"; // @vuln
        sql_toggle_button();
        echo '<div class="result warn" data-sql-panel style="display:none">' . '执行 SQL：' . '<code>' . htmlspecialchars($sql) . '</code></div>';

        $res = mysqli_query($conn, $sql);
        if (!$res) {
            // 靶场故意设计：回显 MySQL 报错，便于判断文件读写失败原因
            $errno = mysqli_errno($conn);
            echo '<div class="result error">' . 'MySQL 错误：' . htmlspecialchars(mysqli_error($conn)) . '</div>';
            if ($errno === 1086) {
                echo '<div class="result warn">' . '目标文件已存在（错误码 1086）：<code>INTO OUTFILE</code> 不能覆盖已有文件。请先点「清理本关写入的文件」，或换一个文件名。' . '</div>';
            } elseif ($errno === 1290) {
                echo '<div class="result warn">' . 'MySQL 禁止文件导入导出（错误码 1290）：<code>secure_file_priv</code> 为 NULL、空字符串或目录不在允许范围。' . '</div>';
            } elseif ($errno === 1044 || $errno === 1142) {
                echo '<div class="result warn">' . '当前数据库账号缺少 FILE 权限（错误码 ' . (int)$errno . '），无法读写文件。' . '</div>';
            }
        } elseif (mysqli_num_rows($res) > 0) {
            echo '<div class="result"><table><tr><th>ID</th><th>' . '用户名' . '</th><th>'
                . '邮箱' . '</th><th>' . '角色' . '</th></tr>';
            while ($row = mysqli_fetch_assoc($res)) {
                // 读取结果全部来自 SQL（LOAD_FILE 的内容会出现在其中某一列），页面不再直接读文件
                $readBuf .= implode('', array_map('strval', $row));
                echo '<tr><td>' . htmlspecialchars($row['id']) . '</td><td>' . htmlspecialchars($row['username']) . '</td><td>'
                    . htmlspecialchars($row['email']) . '</td><td>' . htmlspecialchars($row['role']) . '</td></tr>';
            }
            echo '</table></div>';
        } else {
            echo '<div class="result">' . '查询已执行，本次没有返回数据行。' . '<br>'
                . ($step === 'write'
                    ? '如果 payload 使用了 <code>INTO OUTFILE</code>，说明写入语句已经执行，请查看下面的文件状态。'
                    : '如果 payload 使用了 <code>LOAD_FILE()</code> 却没有数据，请检查文件路径与转义是否正确。')
                . '</div>';
        }
    }

    // 读回内容判定：直接命中通关凭证，或命中其十六进制形式（配合 HEX(LOAD_FILE(...)) 的写法）
    $readOk = false;
    $trimmed = trim($readBuf);
    if ($readBuf !== '') {
        if (strpos($readBuf, $expected) !== false || strpos($readBuf, bin2hex($expected)) !== false) {
            $readOk = true;
        } else {
            $decoded = @hex2bin($trimmed);
            if ($decoded !== false && strpos($decoded, $expected) !== false) {
                $readOk = true;
            }
        }
    }
    $fileExists = is_file($flagPath);

    if ($readBuf !== '') {
        echo '<div class="result"><b>' . 'LOAD_FILE 注入读回的内容：' . '</b><code>'
            . htmlspecialchars($trimmed) . '</code></div>';
    }
    ?>

    <?php if ($fileExists && $readOk): ?>
        <div class="result" style="border-color:var(--success)">
            <div><b style="color:var(--success)">通关成功：写入 + LOAD_FILE 注入读取均已完成！</b></div>
            <div><?php echo '目标文件：'; ?><code><?php echo htmlspecialchars($flagPath); ?></code></div>
            <div><?php echo '读回内容已命中通关凭证（见上方「LOAD_FILE 注入读回的内容」）。'; ?></div>
        </div>
    <?php elseif ($fileExists): ?>
        <div class="result warn"><?php echo '文件已落地，下一步在「读取」框构造 <code>LOAD_FILE()</code> payload，把文件内容通过注入读回来。'; ?></div>
    <?php else: ?>
        <div class="result warn"><?php echo '尚未检测到目标文件：请先在「写入」框构造 <code>INTO OUTFILE</code> payload 并执行。'; ?></div>
    <?php endif; ?>
</div>

<?php
// 双语文案统一从 content/<slug>/<variant>/<lang>/<piece>.html 加载
// site_tokens + 语言回退 + JSON 编码 全部由 level_content_js 自动处理
?>
<script>
<?php level_content_js('sqli', 'outfile', 'basic', 'basic'); ?>
<?php level_content_js('sqli', 'outfile', 'fix', 'fix'); ?>
<?php level_content_js('sqli', 'outfile', 'bypass', 'bypass'); ?>
<?php level_content_js('sqli', 'outfile', 'hint', 'hint'); ?>
<?php level_content_js('sqli', 'outfile', 'tools', 'tools'); ?>
</script>

<?php level_nav_current(); ?>

<?php require_once dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>
