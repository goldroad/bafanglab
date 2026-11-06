<?php
/**
 * bafanglab - 安装/重置脚本
 * 版权所有：八方网域-无涯
 * 说明：填写数据库连接信息（默认值取 config.php 当前配置）后提交，
 *       自动重建数据库并写入测试数据，同时把数据库配置写回 config.php；
 *       校验或执行失败时会给出具体原因与排查建议。
 */
require_once 'config.php';
require_once 'includes/i18n.php';
$page_title = '安装初始化';
require_once 'includes/install_lib.php';

// 表单值：优先取 POST 提交值，未提交或缺省时取 config.php 当前配置作为默认值
$f = array(
    'host' => isset($_POST['db_host']) ? trim($_POST['db_host']) : DB_HOST,
    'port' => isset($_POST['db_port']) ? trim($_POST['db_port']) : DB_PORT,
    'user' => isset($_POST['db_user']) ? trim($_POST['db_user']) : DB_USER,
    'pass' => isset($_POST['db_pass']) ? (string)$_POST['db_pass'] : DB_PASS,
    'name' => isset($_POST['db_name']) ? trim($_POST['db_name']) : DB_NAME,
);

$steps = array();   // 安装步骤日志
$errors = array();  // 校验/执行失败的具体原因（逐条展示给用户）
$installed = false; // 本次请求是否实际执行了安装

/**
 * 写入一个 git 松散对象（blob/tree/commit），返回对象 sha1
 * 对象格式：zlib( "<type> <len>\0<content>" )，文件名为 sha1(前2位目录 + 后38位)
 */
function git_write_object($gitDir, $type, $content)
{
    $store = $type . ' ' . strlen($content) . "\0" . $content;
    $sha = sha1($store);
    $sub = $gitDir . DIRECTORY_SEPARATOR . 'objects' . DIRECTORY_SEPARATOR . substr($sha, 0, 2);
    if (!is_dir($sub)) { mkdir($sub, 0777, true); }
    file_put_contents($sub . DIRECTORY_SEPARATOR . substr($sha, 2), gzcompress($store));
    return $sha;
}

/**
 * 依据 文件名=>内容 数组生成 tree 对象（仅普通文件，按文件名排序），返回 tree sha1
 */
function git_tree_object($gitDir, $files)
{
    ksort($files);
    $tree = '';
    foreach ($files as $name => $content) {
        $blobSha = git_write_object($gitDir, 'blob', $content);
        $tree .= '100644 ' . $name . "\0" . hex2bin($blobSha);
    }
    return git_write_object($gitDir, 'tree', $tree);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $installed = true;

    do {
        // 1. 输入校验（逐项给出具体原因）
        if ($f['host'] === '') {
            $errors[] = '数据库 IP/主机 不能为空（如 127.0.0.1 或 localhost）';
        }
        if (!preg_match('/^\d+$/', $f['port']) || (int)$f['port'] < 1 || (int)$f['port'] > 65535) {
            $errors[] = '端口必须是 1-65535 的整数，当前填写：「'
                . htmlspecialchars($f['port']) . '」';
        }
        if ($f['user'] === '') {
            $errors[] = '数据库账号不能为空';
        }
        if (!preg_match('/^[A-Za-z0-9_]+$/', $f['name'])) {
            $errors[] = '数据库名称只允许字母、数字、下划线，当前填写：「'
                . htmlspecialchars($f['name']) . '」';
        }
        if ($errors) { break; }

        // 2. 连接 MySQL（不选库）；关闭异常模式，改用错误码给出具体原因
        mysqli_report(MYSQLI_REPORT_OFF);
        $link = @mysqli_connect($f['host'], $f['user'], $f['pass'], '', (int)$f['port']);
        if (!$link) {
            $en = mysqli_connect_errno();
            $hintMap = array(
                2002 => '无法建立连接：请确认 MySQL 服务已启动、IP 与端口填写正确、防火墙未拦截',
                2003 => '无法连接到目标主机：请检查 IP 是否正确、目标机器网络是否可达',
                2005 => '无法解析主机名：请检查填写的 IP/主机名是否正确',
                1045 => '认证失败：数据库账号或密码错误',
                1044 => '该账号无数据库访问权限，请换用有建库权限的账号（如 root）',
            );
            $errors[] = 'MySQL 连接失败 [错误码 ' . $en . ']：' . htmlspecialchars(mysqli_connect_error())
                . (isset($hintMap[$en]) ? '<br>排查建议：' . $hintMap[$en] : '');
            break;
        }
        mysqli_set_charset($link, 'utf8');
        $steps[] = 'MySQL 连接成功（' . htmlspecialchars($f['host']) . ':' . htmlspecialchars($f['port']) . '）';

        // 3. 重建数据库
        mysqli_query($link, "DROP DATABASE IF EXISTS `" . $f['name'] . "`");
        if (!mysqli_query($link, "CREATE DATABASE `" . $f['name'] . "` DEFAULT CHARACTER SET utf8")) {
            $errors[] = '创建数据库「' . htmlspecialchars($f['name']) . '」' . '失败：' . htmlspecialchars(mysqli_error($link))
                . '<br>排查建议：确认账号 ' . htmlspecialchars($f['user']) . ' 具有 CREATE/DROP 数据库权限';
            break;
        }
        $steps[] = '数据库 ' . htmlspecialchars($f['name']) . ' 重建成功（旧数据已清理）';
        mysqli_select_db($link, $f['name']);

        // 4. 用户表
        $sql = "CREATE TABLE `users` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `username` VARCHAR(32) NOT NULL,
            `password` VARCHAR(64) NOT NULL,
            `email` VARCHAR(64) DEFAULT '',
            `role` VARCHAR(16) DEFAULT 'user',
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8";
        if (!mysqli_query($link, $sql)) {
            $errors[] = 'users 表创建失败：' . htmlspecialchars(mysqli_error($link));
            break;
        }
        // 注意：明文存储密码是靶场故意设计，用于暴力猜解/注入教学
        $sql = "INSERT INTO `users` (`username`,`password`,`email`,`role`) VALUES
            ('admin','admin123','admin@bafanglab.local','admin'),
            ('wuya','wuya@2026','wuya@bafanglab.local','admin'),
            ('test','123456','test@bafanglab.local','user'),
            ('guest','guest','guest@bafanglab.local','user'),
            ('zhangsan','zs@8888','zhangsan@bafanglab.local','user')";
        if (!mysqli_query($link, $sql)) {
            $errors[] = 'users 测试数据写入失败：' . htmlspecialchars(mysqli_error($link));
            break;
        }
        $steps[] = 'users 表创建成功，测试数据写入成功（5个账号）';

        // 5. 留言表（存储型XSS）
        $sql = "CREATE TABLE `comments` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `nickname` VARCHAR(64) NOT NULL,
            `content` TEXT NOT NULL,
            `create_time` DATETIME NOT NULL,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8";
        if (!mysqli_query($link, $sql)
            || !mysqli_query($link, "INSERT INTO `comments` (`nickname`,`content`,`create_time`) VALUES
                ('admin','Welcome to bafanglab - this is the first demo comment.', NOW())")) {
            $errors[] = 'comments 表创建/初始留言写入失败：' . htmlspecialchars(mysqli_error($link));
            break;
        }
        $steps[] = 'comments 表创建成功，初始留言写入成功';

        // 6. 机密表（SQL注入目标数据）
        $sql = "CREATE TABLE `secrets` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `secret` VARCHAR(128) NOT NULL,
            `note` VARCHAR(128) DEFAULT '',
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8";
        if (!mysqli_query($link, $sql)
            || !mysqli_query($link, "INSERT INTO `secrets` (`secret`,`note`) VALUES
                ('恭喜你完成本关挑战！','SQL注入关卡通关凭证'),
                ('恭喜你完成本关挑战！','目录扫描关卡通关凭证')")) {
            $errors[] = 'secrets 表创建/通关凭证写入失败：' . htmlspecialchars(mysqli_error($link));
            break;
        }
        $steps[] = 'secrets 表创建成功（通关凭证已埋入）';

        // 7. 敏感文件（目录扫描用）
        $dirs = array('backup', 'admin_area', 'phpmyadmin_bak', '.git_hidden');
        foreach ($dirs as $d) {
            $p = BASE_DIR . DIRECTORY_SEPARATOR . $d;
            if (!is_dir($p)) { mkdir($p, 0777, true); }
        }
        file_put_contents(BASE_DIR . DIRECTORY_SEPARATOR . 'backup' . DIRECTORY_SEPARATOR . 'db_backup.sql',
            "-- bafanglab 数据库备份 " . date('Y-m-d') . "\nINSERT INTO users VALUES (99,'sysadmin','Sy5@dm1n#" . date('Y') . "');\n");
        file_put_contents(BASE_DIR . DIRECTORY_SEPARATOR . 'backup' . DIRECTORY_SEPARATOR . 'www.zip', 'fake zip placeholder for dirscan practice');
        file_put_contents(BASE_DIR . DIRECTORY_SEPARATOR . 'admin_area' . DIRECTORY_SEPARATOR . 'index.php',
            "<?php echo '<h1 style=\"color:#0b6fd6\">后台管理区 - 目录扫描关卡发现成功！恭喜你完成本关挑战！</h1>';");
        file_put_contents(BASE_DIR . DIRECTORY_SEPARATOR . 'robots.txt',
            "User-agent: *\nDisallow: /backup/\nDisallow: /admin_area/\n");
        $steps[] = '目录扫描敏感目录/文件已生成（backup/ admin_area/ robots.txt 等）';

        // 8. 示例 .git 仓库（目录扫描 - .git 信息泄露练习）：纯 PHP 生成松散对象，含 3 次提交记录
        $gitDir = BASE_DIR . DIRECTORY_SEPARATOR . 'backup' . DIRECTORY_SEPARATOR . '.git';
        // 先清理旧的示例仓库，保证每次重置后提交记录一致
        if (is_dir($gitDir)) {
            $it = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($gitDir, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($it as $g) {
                if ($g->isDir()) { rmdir($g->getPathname()); } else { unlink($g->getPathname()); }
            }
        }
        mkdir($gitDir . DIRECTORY_SEPARATOR . 'objects', 0777, true);
        mkdir($gitDir . DIRECTORY_SEPARATOR . 'refs' . DIRECTORY_SEPARATOR . 'heads', 0777, true);
        mkdir($gitDir . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'refs' . DIRECTORY_SEPARATOR . 'heads', 0777, true);

        // 仓库故事线：备份源码 → 加入通关凭证文件 → "删除敏感文件"（但历史记录仍可还原，教学点）
        $author = 'wuya <wuya@bafanglab.local>';
        $delDate = date('Y-m-d', strtotime('-1 day'));
        $file_db = "<?php\n// 开发库配置（备份版本，已于 " . $delDate . " 从线上删除）\ndefine('DB_HOST', '127.0.0.1');\ndefine('DB_USER', 'bafang_dev');\ndefine('DB_PASS', 'Dev@bafang#" . date('Y') . "');\ndefine('DB_NAME', 'bafanglab_dev');\n";
        $file_flag = "恭喜你完成本关挑战！\n说明：本文件在第三次提交中被删除，但历史记录仍然可以还原它。\n";
        $file_idx = "<?php\n// 备份站点占位页\necho 'bafanglab dev backup';\n";

        // 提交时间按"当前时间"往前推，避免示例仓库出现写死的过期年份
        $gitNow = time();
        $commits = array(
            array('ts' => $gitNow - 3 * 86400, 'msg' => 'init: 备份站点源码与数据库配置',
                  'files' => array('db_config.php' => $file_db, 'index.php' => $file_idx)),
            array('ts' => $gitNow - 2 * 86400, 'msg' => 'chore: 添加内部通关校验文件',
                  'files' => array('db_config.php' => $file_db, 'secret.txt' => $file_flag, 'index.php' => $file_idx)),
            array('ts' => $gitNow - 1 * 86400, 'msg' => 'security: 删除敏感文件，防止泄露',
                  'files' => array('index.php' => $file_idx)),
        );

        $parent = null;
        $zero = str_repeat('0', 40);
        $logLines = '';
        foreach ($commits as $c) {
            $treeSha = git_tree_object($gitDir, $c['files']);
            $content = "tree $treeSha\n"
                . ($parent ? "parent $parent\n" : '')
                . "author $author {$c['ts']} +0800\n"
                . "committer $author {$c['ts']} +0800\n\n{$c['msg']}\n";
            $sha = git_write_object($gitDir, 'commit', $content);
            $kind = $parent ? 'commit' : 'commit (initial)';
            $logLines .= "$zero $sha $author {$c['ts']} +0800\t$kind: {$c['msg']}\n";
            $parent = $sha;
            $zero = $sha;
        }

        file_put_contents($gitDir . DIRECTORY_SEPARATOR . 'HEAD', "ref: refs/heads/master\n");
        file_put_contents($gitDir . DIRECTORY_SEPARATOR . 'refs' . DIRECTORY_SEPARATOR . 'heads' . DIRECTORY_SEPARATOR . 'master', $parent . "\n");
        file_put_contents($gitDir . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'HEAD', $logLines);
        file_put_contents($gitDir . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'refs' . DIRECTORY_SEPARATOR . 'heads' . DIRECTORY_SEPARATOR . 'master', $logLines);
        file_put_contents($gitDir . DIRECTORY_SEPARATOR . 'description', "bafanglab dev backup repository\n");
        file_put_contents($gitDir . DIRECTORY_SEPARATOR . 'config', "[core]\n\trepositoryformatversion = 0\n\tfilemode = false\n\tbare = false\n\tlogallrefupdates = true\n");
        $steps[] = '示例 .git 仓库已生成（backup/.git，3 次提交记录，通关凭证藏在被删除的历史文件中）';

        // 9. 上传目录
        if (!is_dir(UPLOAD_DIR)) { mkdir(UPLOAD_DIR, 0777, true); }
        $steps[] = 'uploads 上传目录就绪';

        // 10. 文件包含演示文件
        $incdir = BASE_DIR . DIRECTORY_SEPARATOR . 'modules' . DIRECTORY_SEPARATOR . 'fileinc' . DIRECTORY_SEPARATOR . 'pages';
        if (!is_dir($incdir)) { mkdir($incdir, 0777, true); }
        file_put_contents($incdir . DIRECTORY_SEPARATOR . 'about.php', '<h3>关于我们</h3><p>八方网域 - 专注网络安全人才培养。</p>');
        file_put_contents($incdir . DIRECTORY_SEPARATOR . 'news.php', '<h3>新闻中心</h3><p>' . date('Y') . ' 网络安全宣传周活动开启。</p>');
        $steps[] = '文件包含演示页面已生成';

        // 11. 任意文件读取演示文件
        $frdir = BASE_DIR . DIRECTORY_SEPARATOR . 'modules' . DIRECTORY_SEPARATOR . 'fileread' . DIRECTORY_SEPARATOR . 'files';
        if (!is_dir($frdir)) { mkdir($frdir, 0777, true); }
        file_put_contents($frdir . DIRECTORY_SEPARATOR . 'readme.txt', "==========================================\n 教学资料共享平台 - 公共文件目录说明\n==========================================\n\n【目录用途】\n/files 目录用于存放面向全站用户公开发布的文档资料，\n由教务处在每学期开学前统一更新，无需登录即可下载。\n\n【当前文件清单】\n1. readme.txt   本说明文件\n2. notice.txt   系统维护公告\n\n【更新记录】\n2026-09-01  资料下载功能上线，发布首批公开文档\n2026-10-15  清理过期课表文件，新增系统维护公告\n\n【注意事项】\n1. 本目录文件仅供校内学习交流使用，请勿用于商业用途；\n2. 转载课件时请注明出处并保留原作者署名；\n3. 如需获取受权限保护的内部资料，请携带工牌到信息中心办理。\n\n【联系我们】\n系统故障或资料缺失，请联系信息中心（内线 8021），\n或到行政楼 305 室现场登记，工作日 9:00-17:00 受理。\n");
        file_put_contents($frdir . DIRECTORY_SEPARATOR . 'notice.txt', "【系统维护公告】\n\n全体师生：\n\n    教学资料共享平台将于本周六 22:00 至次日 06:00 进行服务器例行维护，\n期间资料下载功能暂停使用，请提前下载所需课件与讲义。\n\n    本次维护内容如下：\n    1. 数据库例行备份与索引优化；\n    2. 升级下载模块，提升大文件下载的稳定性；\n    3. 修复部分浏览器下载时文件名乱码的问题。\n\n    维护完成后无需重新登录，原有账号、下载记录与收藏均不受影响。\n由此带来的不便，敬请谅解。\n\n                                    信息中心\n                                    2026 年 10 月 28 日\n");
        $steps[] = '任意文件读取演示文件已生成';

        // 12. SSTI 关卡（模块16）通关凭证文件：要求通过模板注入执行命令读取
        $sstidir = BASE_DIR . DIRECTORY_SEPARATOR . 'modules' . DIRECTORY_SEPARATOR . 'ssti';
        if (!is_dir($sstidir)) { mkdir($sstidir, 0777, true); }
        file_put_contents($sstidir . DIRECTORY_SEPARATOR . 'secret.txt',
            "恭喜你完成本关挑战！\n");
        $steps[] = 'SSTI 关卡通关凭证文件已生成（modules/ssti/secret.txt）';

        // 13. SQL 注入文件读写关卡目录：secure_file_priv 需指向该目录，重置时清空旧写入文件
        $sqliFiles = BASE_DIR . DIRECTORY_SEPARATOR . 'modules' . DIRECTORY_SEPARATOR . 'sqli' . DIRECTORY_SEPARATOR . 'files';
        if (!is_dir($sqliFiles)) { mkdir($sqliFiles, 0777, true); }
        foreach (array('flag.txt', 'shell.php') as $sf) {
            if (is_file($sqliFiles . DIRECTORY_SEPARATOR . $sf)) { @unlink($sqliFiles . DIRECTORY_SEPARATOR . $sf); }
        }
        $steps[] = 'SQL 注入文件读写目录就绪（modules/sqli/files，旧写入文件已清理）';
        $sfpRes = mysqli_query($link, 'SELECT @@secure_file_priv AS p');
        if ($sfpRes && ($sfpRow = mysqli_fetch_assoc($sfpRes))) {
            $steps[] = 'MySQL secure_file_priv 当前值：' . htmlspecialchars((string)$sfpRow['p'])
                . '（本关要求指向 modules/sqli/files 目录）';
        }

        // 14. 把数据库配置写回 config.php，使整个靶场切换到本次填写的连接信息
        if (write_config_db($f)) {
            $steps[] = '数据库配置已写回 config.php（DB_HOST/DB_PORT/DB_USER/DB_PASS/DB_NAME）';
        } else {
            $errors[] = '数据库安装完成，但 config.php 写入失败：请检查该文件可写权限，'
                . '并手工把其中 DB_HOST/DB_PORT/DB_USER/DB_PASS/DB_NAME 改为本次填写的值，否则其他关卡仍会连接旧数据库';
            break;
        }

        mysqli_close($link);
    } while (false);
}

require_once 'includes/header.php';
?>
<div class="panel">
    <h2><?php echo 'bafanglab 安装 / 重置'; ?></h2>
    <p class="doc-note"><?php echo '强烈建议先进行'; ?><a href="<?php echo BASE_URL; ?>/check.php"><?php echo '环境检测'; ?></a><?php echo '。'; ?></p>
    <p class="desc">
        <?php echo '填写数据库连接信息（默认值取自当前 config.php），提交后将'; ?><strong style="color:var(--danger)"><?php echo '删除并重建'; ?></strong><?php echo '指定数据库、'; ?>
        <?php echo '写入测试数据，并把数据库配置写回 config.php。'; ?><br>
        <?php echo '练习混乱后重新提交本表单即可一键重置环境。'; ?>
    </p>

    <?php if ($installed && $errors): ?>
        <div class="msg fail">
            <b><?php echo '安装未通过，具体原因：'; ?></b><br>
            <?php foreach ($errors as $e): ?>
                &bull; <?php echo $e; ?><br>
            <?php endforeach; ?>
        </div>
    <?php elseif ($installed): ?>
        <div class="msg success"><?php echo '安装完成！所有组件初始化成功。'; ?></div>
    <?php endif; ?>

    <?php if ($installed && $steps): ?>
        <div class="result">
            <?php foreach ($steps as $i => $s): ?>
                <div>[<?php echo $i + 1; ?>] <?php echo $s; ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <h4><?php echo '数据库连接配置'; ?></h4>
    <form method="post" action="<?php echo BASE_URL; ?>/install.php">
        <div class="form-row">
            <label><?php echo '数据库 IP/主机'; ?></label>
            <input type="text" name="db_host" value="<?php echo htmlspecialchars($f['host'], ENT_QUOTES); ?>" autocomplete="off">
        </div>
        <div class="form-row">
            <label><?php echo '端口'; ?></label>
            <input type="text" name="db_port" value="<?php echo htmlspecialchars($f['port'], ENT_QUOTES); ?>" autocomplete="off">
        </div>
        <div class="form-row">
            <label><?php echo '账号'; ?></label>
            <input type="text" name="db_user" value="<?php echo htmlspecialchars($f['user'], ENT_QUOTES); ?>" autocomplete="off">
        </div>
        <div class="form-row">
            <label><?php echo '密码'; ?></label>
            <input type="text" name="db_pass" value="<?php echo htmlspecialchars($f['pass'], ENT_QUOTES); ?>" autocomplete="off">
        </div>
        <div class="form-row">
            <label><?php echo '数据库名称'; ?></label>
            <input type="text" name="db_name" value="<?php echo htmlspecialchars($f['name'], ENT_QUOTES); ?>" autocomplete="off">
        </div>
        <button class="btn" type="submit"><?php echo '开始安装 / 重置'; ?></button>
    </form>

    <br><a class="btn" href="<?php echo BASE_URL; ?>/index.php"><?php echo '返回首页开始练习'; ?></a>
</div>
<?php require_once 'includes/footer.php'; ?>
