<?php
/**
 * bafanglab - 模块7：SQL注入（JSON 注入）
 * 版权所有：八方网域-无涯
 */
require_once dirname(dirname(__DIR__)) . '/includes/i18n.php';
require_once dirname(dirname(__DIR__)) . '/includes/db.php';
require_once dirname(dirname(__DIR__)) . '/includes/levels.php';

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

// 结果区 HTML：页面内联输出与 AJAX 片段返回共用，保证两处结构永远一致
$resultHtml = '';
if ($showsql !== '') {
    ob_start();
    sql_toggle_button();
    $resultHtml .= ob_get_clean();
    $resultHtml .= '<div class="result warn" data-sql-panel style="display:none">'
        . '执行 SQL：' . '<code>' . htmlspecialchars($showsql) . '</code></div>';
    if ($err !== '') {
        // 靶场故意设计：直接把 MySQL 报错输出到页面
        $resultHtml .= '<div class="result error">' . 'MySQL 错误：' . htmlspecialchars($err) . '</div>';
    } elseif (!empty($rows)) {
        $resultHtml .= '<div class="result"><table><tr><th>ID</th><th>' . '用户名'
            . '</th><th>' . '角色' . '</th></tr>';
        foreach ($rows as $row) {
            $resultHtml .= '<tr><td>' . (int)$row['id'] . '</td><td>'
                . htmlspecialchars($row['username']) . '</td><td>'
                . htmlspecialchars($row['role']) . '</td></tr>';
        }
        $resultHtml .= '</table></div>';
    } else {
        $resultHtml .= '<div class="result">' . '查询结果：无匹配数据。' . '</div>';
    }
}
if ($resultHtml !== '') {
    // 外层容器保证「显示SQL语句」按钮与 data-sql-panel 同父，切换逻辑可用
    $resultHtml = '<div id="jsonResultData">' . $resultHtml . '</div>';
}

// AJAX 提交：只返回结果片段（不输出整页），前端直接注入结果区
$isAjax = (isset($_SERVER['HTTP_X_REQUESTED_WITH'])
        && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
    || (isset($_GET['ajax']) && $_GET['ajax'] === '1');
if ($isAjax) {
    header('Content-Type: text/html; charset=UTF-8');
    echo $resultHtml;
    exit;
}

$page_title = 'SQL注入 - JSON 注入';
require_once dirname(dirname(__DIR__)) . '/includes/header.php';
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
    <?php level_goal('目标：用 <code>UNION</code> 注入把 <code>secrets</code> 表中的通关凭证'
        . '拼到回显字段上，从查询结果表格中读出凭证。'); ?>

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

    <div id="jsonResult" style="margin-top:12px"><?php echo $resultHtml; ?></div>
</div>

<script>
function sendJson() {
    var body = document.querySelector('#jsonForm textarea').value;
    var box = document.getElementById('jsonResult');
    var xhr = new XMLHttpRequest();
    xhr.open('POST', '<?php echo BASE_URL; ?>/modules/sqli/json.php', true);
    xhr.setRequestHeader('Content-Type', 'application/json');
    // 标记为 AJAX 请求：服务端只返回结果片段，避免把整页 HTML 再注入一遍
    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
    xhr.onreadystatechange = function () {
        if (xhr.readyState !== 4) { return; }
        if (xhr.status === 200) {
            box.innerHTML = xhr.responseText;
        } else {
            box.innerHTML = '<div class="result error">请求失败（HTTP ' + xhr.status + '）</div>';
        }
    };
    xhr.send(body);
}
</script>

<?php level_nav_current(); ?>

<?php require_once dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>
