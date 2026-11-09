<?php
/**
 * bafanglab - 模块7：SQL注入（JSON 注入）
 * 版权所有：八方网域-无涯
 */
require_once dirname(dirname(__DIR__)) . '/includes/i18n.php';
$page_title = 'SQL注入 - JSON 注入';
require_once dirname(dirname(__DIR__)) . '/includes/header.php';
require_once dirname(dirname(__DIR__)) . '/includes/db.php';

$raw = file_get_contents('php://input');
$showsql = '';
$err = '';
$rows = array();
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $raw !== '') {
    // 靶场故意设计：直接 json_decode 后把字段拼进 SQL，无任何过滤
    $data = json_decode($raw, true);
    $name = is_array($data) && isset($data['username']) ? $data['username'] : '';

    // 靶场故意设计：字符型参数直接拼接，且回显 MySQL 报错信息
    $showsql = "SELECT id, username, role FROM users WHERE username = '$name'"; // @vuln
    $res = mysqli_query($conn, $showsql);
    if (!$res) {
        $err = mysqli_error($conn);
    } else {
        while ($row = mysqli_fetch_assoc($res)) {
            $rows[] = $row;
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $msg = '<div class="msg fail">' . '请提交 JSON 格式的请求体（Content-Type: application/json）。' . '</div>';
}
?>
<a class="back-link" href="<?php echo BASE_URL; ?>/index.php">&larr; <?php echo t('footer.back'); ?></a>

<div class="panel">
    <h2><?php echo '模块7：SQL 注入'; ?></h2>
    <?php sqli_tabs('JSON 注入'); ?>
    <?php level_tools('basic', 'hint', true, true, 'tools', BASE_URL . '/modules/sqli/case.php'); ?>
    <?php
    level_code(__FILE__, null, <<<'FIX'
// 修复1：预处理语句——JSON 字段作为参数绑定，彻底杜绝拼接
$stmt = mysqli_prepare($conn, 'SELECT id, username, role FROM users WHERE username = ?');
mysqli_stmt_bind_param($stmt, 's', $name);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);

// 修复2：json_decode 失败时统一返回 400，不进入 SQL
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo '请求体 JSON 格式错误';
    exit;
}

// 修复3：生产环境关闭错误回显，错误只写日志
// php.ini: display_errors = Off; log_errors = On
// ini_set('display_errors', '0');
if (!$res) {
    error_log('SQL error: ' . mysqli_error($conn));
    echo '系统繁忙，请稍后再试';            // 不输出 mysqli_error()
}

// 修复4：对 JSON 字段做类型/白名单校验，配合 WAF 纵深防御
FIX);
    ?>
    <p class="desc">
        <?php echo '这是一个接收 <b>JSON 请求体</b> 的查询接口（<code>Content-Type: application/json</code>），'
            . '服务端把 JSON 里的 <code>username</code> 字段直接拼进 SQL。<br>'
            . '很多前后端分离项目只校验了表单参数，却忽略了 JSON body 里的字段同样可以注入。'; ?>
    </p>
    <?php level_goal('目标：向 JSON body 中的 <code>username</code> 字段注入报错函数，'
        . '从 MySQL 报错信息中读出 <code>secrets</code> 表的通关凭证。'); ?>

    <?php echo $msg; ?>

    <form id="jsonForm" onsubmit="return false;">
        <div class="form-row">
            <label><?php echo 'JSON 请求体'; ?></label>
            <textarea name="body" rows="6" style="max-width:640px;font-family:monospace">{
  "username": "admin"
}</textarea>
        </div>
        <button class="btn" type="button" onclick="sendJson()"><?php echo '提交查询'; ?></button>
    </form>

    <div id="jsonResult" style="margin-top:12px"></div>

    <?php if ($showsql !== ''): ?>
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
            <div class="result"><?php echo '查询结果：无匹配数据。'; ?></div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<script>
function sendJson() {
    var body = document.querySelector('#jsonForm textarea').value;
    var xhr = new XMLHttpRequest();
    xhr.open('POST', '<?php echo BASE_URL; ?>/modules/sqli/json.php', true);
    xhr.setRequestHeader('Content-Type', 'application/json');
    xhr.onreadystatechange = function () {
        if (xhr.readyState === 4) {
            var box = document.getElementById('jsonResult');
            // 只取响应里的结果片段，避免整页嵌套
            var doc = new DOMParser().parseFromString(xhr.responseText, 'text/html');
            var panel = doc.querySelector('.panel');
            box.innerHTML = panel ? panel.innerHTML : xhr.responseText;
        }
    };
    xhr.send(body);
}
</script>

<?php level_nav_current(); ?>

<?php require_once dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>
