<?php
/**
 * bafanglab - 环境自检
 * 版权所有：八方网域-无涯
 *
 * 安装前/部署后打开本页，一次性看清 PHP 版本、扩展、目录权限、伪静态与数据库状态，
 * 避免「装完才发现缺 gd，验证码关才报错」这类现场远离原因的问题。
 */
require_once 'includes/i18n.php';
$page_title = '环境检测';
require_once 'includes/header.php';

$rows = array();

/**
 * 追加一行检测结果
 * @param string $status ok|warn|fail|info
 * @param string $name   检测项名称
 * @param string $detail 当前实测值
 * @param string $fix    不通过时的修复建议
 */
function ck(&$rows, $status, $name, $detail, $fix = '')
{
    $rows[] = array('status' => $status, 'name' => $name, 'detail' => $detail, 'fix' => $fix);
}

/**
 * 扩展/类检测
 * @param string $ext  扩展名
 * @param bool   $byClass 是否按类名判断（如 dom => DOMDocument）
 */
function ck_has($ext, $byClass = false)
{
    if ($byClass) {
        $map = array('dom' => 'DOMDocument', 'simplexml' => 'SimpleXMLElement');
        return class_exists(isset($map[$ext]) ? $map[$ext] : $ext);
    }
    return extension_loaded($ext);
}

/* ==================== 1. 运行环境 ==================== */
$phpOk = version_compare(PHP_VERSION, '5.4.0', '>=');
$phpNew = version_compare(PHP_VERSION, '7.3.0', '>=');
ck(
    $rows,
    !$phpOk ? 'fail' : ($phpNew ? 'ok' : 'warn'),
    'PHP 版本',
    'PHP ' . PHP_VERSION . ' (' . (PHP_INT_SIZE * 8) . '-bit)',
    'phpStudy：其他选项菜单 → PHP版本 → 选择 7.3 或以上版本 → 重启 Apache。'
);

$sapi = php_sapi_name();
$server = isset($_SERVER['SERVER_SOFTWARE']) ? $_SERVER['SERVER_SOFTWARE'] : $sapi;
ck($rows, 'info', '运行方式', $sapi . ' / ' . $server);
ck($rows, 'info', '操作系统', PHP_OS . ' ' . php_uname('m'));

$tz = date_default_timezone_get();
ck(
    $rows,
    $tz === 'Asia/Shanghai' ? 'ok' : 'warn',
    '时区',
    $tz,
    'config.php 中 date_default_timezone_set 应为 Asia/Shanghai，否则关卡初始化时间会偏移。'
);

/* ==================== 2. PHP 扩展 ==================== */
// 每个扩展的修复建议都附带 phpStudy 的图形界面操作路径，方便初学者
$exts = array(
    // 扩展名 => array(严重级别, 用途说明中, phpStudy 操作说明)
    'mysqli'   => array('fail', '数据库连接（全部 SQL 关卡）',
                        'phpStudy：其他选项菜单 → PHP扩展 → 勾选 php_mysqli → 重启 Apache。'),
    'gd'       => array('fail', '验证码图片（模块5 有验证码场景）',
                        'phpStudy：其他选项菜单 → PHP扩展 → 勾选 php_gd2 → 重启 Apache。'),
    'dom'      => array('fail', 'XML 解析（模块8 XXE）',
                        'phpStudy：其他选项菜单 → PHP扩展 → 勾选 php_xml → 重启 Apache。'),
    'curl'     => array('warn', '服务端请求（模块12 SSRF）',
                        'phpStudy：其他选项菜单 → PHP扩展 → 勾选 php_curl → 重启 Apache。'),
    'mbstring' => array('warn', '多字节字符处理',
                        'phpStudy：其他选项菜单 → PHP扩展 → 勾选 php_mbstring → 重启 Apache。'),
    'session'  => array('warn', '会话（验证码、SSRF 演示）',
                        'phpStudy：其他选项菜单 → PHP扩展 → 勾选 php_session → 重启 Apache。'),
);
foreach ($exts as $ext => $meta) {
    $has = ck_has($ext, $ext === 'dom');
    ck(
        $rows,
        $has ? 'ok' : $meta[0],
        '扩展 ' . $ext,
        ($has ? '已加载' : '未加载') . ' — ' . $meta[1],
        $has ? '' : $meta[2]
    );
}

/* ==================== 3. 目录与文件权限 ==================== */
$upWritable = is_dir(UPLOAD_DIR) && is_writable(UPLOAD_DIR);
ck(
    $rows,
    $upWritable ? 'ok' : 'fail',
    'uploads 目录可写',
    (is_dir(UPLOAD_DIR) ? '存在' : '不存在')
        . ' / ' . (is_writable(UPLOAD_DIR) ? '可写' : '不可写')
        . ' — ' . str_replace('\\', '/', UPLOAD_DIR),
    'phpStudy：在 bafanglab 目录下新建 uploads 文件夹即可（Windows 下默认可写）。Linux：mkdir -p uploads && chmod 777 uploads。'
);

$cfgWritable = is_writable(BASE_DIR . '/config.php');
ck(
    $rows,
    $cfgWritable ? 'ok' : 'warn',
    'config.php 可写',
    $cfgWritable ? '可写（安装程序可自动回写数据库配置）'
                 : '不可写（安装后需手工修改数据库配置）',
    'phpStudy：右键 config.php → 属性 → 安全 → 编辑 → 给 Users 写入权限。Linux：chmod 666 config.php。'
);

$projReadable = is_readable(BASE_DIR . '/modules/sqli/get.php');
ck(
    $rows,
    $projReadable ? 'ok' : 'warn',
    '关卡源码可读',
    $projReadable ? '可读（「漏洞源码」按钮可正常展示）'
                  : '不可读（「漏洞源码」弹窗会显示为空）',
    '确认项目目录权限：Linux 下 chmod -R 755 项目目录，属主改为 Apache 运行账号。'
);

/* ==================== 4. Web 服务器 / 伪静态 ==================== */
$apacheMods = function_exists('apache_get_modules') ? apache_get_modules() : null;
$htaccessBlocked = null; // true=已生效, false=未生效/泄露, null=无法探测
if (function_exists('curl_init')) {
    $probeUrl = site_base_url() . '/.git/HEAD';
    $ch = curl_init($probeUrl);
    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 3,
        CURLOPT_NOBODY         => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
    ));
    curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code === 403) { $htaccessBlocked = true; }
    elseif ($code === 200) { $htaccessBlocked = false; }
    elseif ($code === 404) { $htaccessBlocked = true; } // 未用 git 部署，无 .git 可泄露
}

if ($htaccessBlocked === null) {
    ck($rows, 'warn', '.htaccess 生效情况',
        '无法自动探测（缺少 curl 扩展）',
        '请手工确认 .git 不可被浏览器访问。');
} else {
    ck(
        $rows,
        $htaccessBlocked ? 'ok' : 'fail',
        '.htaccess 伪静态生效',
        $htaccessBlocked
            ? '已生效（.git 访问被拦截）'
            : '未生效：.git 可被直接下载，存在源码泄露风险',
        $htaccessBlocked ? '' : 'phpStudy：其他选项菜单 → Apache 配置 → httpd-conf → 搜索 AllowOverride，将对应目录的 None 改为 All，保存后重启 Apache。'
    );
}
ck(
    $rows,
    $apacheMods === null ? 'info' : (in_array('mod_rewrite', $apacheMods) ? 'ok' : 'warn'),
    'mod_rewrite',
    $apacheMods === null
        ? '非 Apache（或未暴露模块列表），以 .htaccess 探测结果为准'
        : (in_array('mod_rewrite', $apacheMods) ? '已启用' : '未启用'),
    'phpStudy：其他选项菜单 → Apache 配置 → httpd-conf → 搜索 LoadModule rewrite_module，去掉行首的 #，保存后重启 Apache。'
);

/* ==================== 5. 数据库 ==================== */
$dbVersion = db_server_version();
$dbOk = $dbVersion !== '';
$tablesOk = false;
$tableMissing = array();
if ($dbOk) {
    mysqli_report(MYSQLI_REPORT_OFF);
    $link = @mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME, (int)DB_PORT);
    if ($link) {
        $need = array('users', 'comments', 'secrets');
        foreach ($need as $tb) {
            $r = @mysqli_query($link, "SHOW TABLES LIKE '" . $tb . "'");
            if (!$r || mysqli_num_rows($r) === 0) { $tableMissing[] = $tb; }
        }
        $tablesOk = empty($tableMissing);
    }
}

// 数据库未连接 / 表不存在均属"尚未安装"的正常状态，仅提示去 install.php，不算故障
ck(
    $rows,
    'info',
    'MySQL 连接',
    $dbOk ? 'MySQL ' . $dbVersion . ' @ ' . DB_HOST . ':' . DB_PORT . ' / ' . DB_NAME
          : '未连接（首次部署属正常，请先运行 install.php）',
    $dbOk ? '' : 'phpStudy：点击「启动」开启 MySQL；确认 config.php 中账号密码与 phpStudy 一致；或打开 install.php 填写连接信息并安装。'
);
if ($dbOk) {
    ck(
        $rows,
        $tablesOk ? 'ok' : 'info',
        '数据表状态',
        $tablesOk ? 'users / comments / secrets 均存在'
                  : '缺少表：' . implode(', ', $tableMissing)
                  . '（尚未安装属正常）',
        $tablesOk ? '' : '打开 install.php 点击「开始安装 / 重置」即可一键建库建表。'
    );
}

// 文件读写关（模块7 · sqli/outfile）依赖：MySQL 版本 + FILE 权限 + secure_file_priv 指向本关目录
if ($dbOk && $link) {
    $verOk = version_compare($dbVersion, '5.7.0', '>=');
    $verMid = version_compare($dbVersion, '5.6.0', '>=');
    ck(
        $rows,
        $verOk ? 'ok' : ($verMid ? 'warn' : 'fail'),
        'MySQL 版本',
        'MySQL ' . $dbVersion . ($verOk ? '（满足 5.7+ 要求）' : ''),
        $verOk ? '' : '建议升级到 MySQL 5.7 及以上：5.6 及以下缺少部分 JSON / 窗口函数能力，'
            . '且 INTO OUTFILE 与 LOAD_FILE 的行为差异较大，文件读写关可能出现与教程不一致的结果。'
            . 'phpStudy：其他选项菜单 → MySQL 版本 → 切换版本后重启 MySQL。'
    );

    // 本关允许 MySQL 读写的目录（绝对路径，由安装位置推导，不写死）
    $filesDir = BASE_DIR . DIRECTORY_SEPARATOR . 'modules' . DIRECTORY_SEPARATOR . 'sqli'
        . DIRECTORY_SEPARATOR . 'files';
    $filesDirSlash = str_replace('\\', '/', $filesDir);

    // secure_file_priv：NULL（禁用）/ 空字符串（不限制）/ 指定目录（仅该目录及子目录）
    $sfpRaw = null;
    $sfpKnown = false;
    $r = @mysqli_query($link, 'SELECT @@secure_file_priv');
    if ($r) {
        $row = mysqli_fetch_row($r);
        $sfpRaw = isset($row[0]) ? $row[0] : null; // SQL NULL 与空字符串要用 === 区分
        $sfpKnown = true;
    }

    $iniHint = 'phpStudy：打开 MySQL 的 my.ini（其他选项菜单 → MySQL 配置），在 [mysqld] 段加 '
        . 'secure-file-priv="' . $filesDir . '"，保存后从面板重启 MySQL。'
        . 'MySQL 5.7 不能用 SET GLOBAL 改这一项，必须改配置文件并重启。';

    if (!$sfpKnown) {
        ck(
            $rows,
            'warn',
            'secure_file_priv',
            '查询失败，无法判断（不影响其它关卡）',
            '确认数据库账号可执行 SHOW VARIABLES；文件读写关（模块7）需要该项允许写入本关 files 目录。' . $iniHint
        );
    } elseif ($sfpRaw === null) {
        ck(
            $rows,
            'fail',
            'secure_file_priv = NULL（禁用文件读写）',
            'INTO OUTFILE / LOAD_FILE 均被拒绝（错误 1290），文件读写关无法完成',
            '本关只能写入 modules/sqli/files 目录。' . $iniHint
        );
    } elseif ($sfpRaw === '') {
        ck(
            $rows,
            'warn',
            'secure_file_priv = 空字符串（不限制目录）',
            'MySQL 可读写服务器任意路径，文件读写关可用，但权限过大、风险最高',
            '教学环境可用；建议收敛为只允许本关目录：' . $iniHint
        );
    } else {
        $sfpSlash = rtrim(str_replace('\\', '/', $sfpRaw), '/');
        $inScope = stripos($filesDirSlash . '/', $sfpSlash . '/') === 0;
        ck(
            $rows,
            $inScope ? 'ok' : 'fail',
            'secure_file_priv 允许本关目录',
            $inScope
                ? '已指向 ' . $sfpRaw . '（覆盖本关 files 目录）'
                : '当前为 ' . $sfpRaw . '，不包含本关目录 ' . $filesDir,
            $inScope ? '' : '文件读写关（模块7）写入会被拒绝（错误 1290）。' . $iniHint
        );
    }

    // 当前数据库账号的 FILE 权限（INTO OUTFILE / LOAD_FILE 必需）
    $grants = '';
    $r = @mysqli_query($link, 'SHOW GRANTS');
    if ($r) {
        while ($g = mysqli_fetch_row($r)) { $grants .= ' ' . $g[0]; }
    }
    $hasFile = (stripos($grants, 'ALL PRIVILEGES') !== false)
        || preg_match('/\bFILE\b/', $grants) === 1;
    ck(
        $rows,
        $grants === '' ? 'warn' : ($hasFile ? 'ok' : 'fail'),
        'MySQL FILE 权限',
        $grants === ''
            ? '无法读取授权信息（不影响其它关卡）'
            : ($hasFile ? '当前账号具备 FILE 权限' : '当前账号缺少 FILE 权限'),
        $hasFile ? '' : '文件读写关（模块7）会报权限错误。授权命令：'
            . 'GRANT FILE ON *.* TO \'' . DB_USER . '\'@\'localhost\'; FLUSH PRIVILEGES;'
            . '（root 账号通常已具备；若使用受限账号需单独授权。）'
    );

    mysqli_close($link);
}

/* ==================== 6. 安装状态提醒 ==================== */
// 已初始化成功（库 + 表都在）的用户：提醒 install.php 对外开放的风险
if ($dbOk && $tablesOk) {
    ck(
        $rows,
        'warn',
        '已安装提醒',
        '检测到数据库与数据表均已就绪，靶场已完成初始化。',
        'install.php 仍对外开放，任何人可重置数据库。本机/内网教学可保留；若需更安全，可删除 install.php 或在 .htaccess 中限制来源 IP。'
    );
} else {
    ck(
        $rows,
        'info',
        '安装状态',
        '尚未安装：数据库或数据表不存在，打开 install.php 即可一键初始化。',
        ''
    );
}
ck($rows, 'info', '靶场版本', 'bafanglab v' . BAFANGLAB_VERSION);

$failCount = 0;
$warnCount = 0;
foreach ($rows as $r) {
    if ($r['status'] === 'fail') { $failCount++; }
    if ($r['status'] === 'warn') { $warnCount++; }
}
$badge = array(
    'ok'   => '通过',
    'warn' => '建议处理',
    'fail' => '不通过',
    'info' => '信息',
);
?>

<a class="back-link" href="<?php echo BASE_URL; ?>/index.php">&larr; <?php echo t('footer.back'); ?></a>

<div class="panel">
    <h2><?php echo '环境自检'; ?></h2>
    <p class="desc">
        <?php echo '本页逐项检查靶场运行所需的 PHP 版本、扩展、目录权限、伪静态与数据库状态，部署后建议第一时间打开。'; ?>
    </p>

    <?php if ($failCount === 0 && $warnCount === 0): ?>
        <div class="msg success"><?php echo '全部检查通过，可以开始练习。'; ?></div>
    <?php elseif ($failCount === 0): ?>
        <div class="msg success"><?php echo '必需项全部通过，有 ' . $warnCount . ' 项建议关注（不影响主要功能）。'; ?></div>
    <?php else: ?>
        <div class="msg fail"><?php echo '有 ' . $failCount . ' 项必需条件不满足，相关关卡会报错。请按下方「处理方式」修复后刷新本页。'; ?></div>
    <?php endif; ?>

    <table class="chk-table">
        <tr>
            <th style="width:86px"><?php echo '状态'; ?></th>
            <th style="width:190px"><?php echo '检测项'; ?></th>
            <th><?php echo '实测结果'; ?></th>
        </tr>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td><span class="chk-badge chk-<?php echo $r['status']; ?>"><?php echo $badge[$r['status']]; ?></span></td>
                <td><?php echo $r['name']; ?></td>
                <td>
                    <?php echo $r['detail']; ?>
                    <?php if (in_array($r['status'], array('warn', 'fail')) && $r['fix'] !== ''): ?>
                        <div class="chk-fix"><?php echo '处理方式：'; ?><?php echo $r['fix']; ?></div>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>

    <h4><?php echo '下一步'; ?></h4>
    <p>
        <a class="btn" href="<?php echo BASE_URL; ?>/install.php"><?php echo '前往安装 / 重置'; ?></a>
        <a class="btn btn-plain" href="<?php echo BASE_URL; ?>/index.php"><?php echo t('footer.back'); ?></a>
    </p>
</div>

<?php require_once 'includes/footer.php'; ?>
