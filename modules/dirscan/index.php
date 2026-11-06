<?php
/**
 * bafanglab - 模块2：目录扫描
 * 版权所有：八方网域-无涯
 */
require_once dirname(dirname(__DIR__)) . '/includes/i18n.php';
$page_title = '目录扫描';
require_once dirname(dirname(__DIR__)) . '/includes/header.php';
?>
<a class="back-link" href="<?php echo BASE_URL; ?>/index.php">&larr; <?php echo t('footer.back'); ?></a>

<div class="panel">
    <h2><?php echo '模块2：目录扫描'; ?></h2>
    <?php level_tools('basic', 'hint', false, true, 'tools', BASE_URL . '/modules/dirscan/case.php', false, true); ?>
<?php
    // 本关属于"服务器上存在不该暴露的目录/文件"，无 PHP 漏洞源码可展示；仅保留修复示例
    level_code(false, array(), <<<'FIX'
// 本关属于运维配置缺陷，修复在服务器层面而非 PHP 代码：

// 1. Apache 关闭目录浏览（httpd.conf 或 .htaccess）
Options -Indexes

// 2. Nginx 关闭目录浏览
autoindex off;

// 3. 备份/测试文件移出 Web 根目录，禁止直接 URL 访问
//    错误：WWW/backup/db_backup.sql
//    正确：放到 Web 根之外，或改名 + 加访问控制

// 4. 隐藏后台不能仅靠"路径猜不到"，必须加认证
//    location /admin_area/ { auth_basic "Restricted"; ... }

// 5. 禁止 .git/.svn 等版本库目录通过 URL 访问（部署时也不应上传 .git）
//    Apache .htaccess:  RewriteRule ^\.git(/|$) - [F,L]
//    Nginx:             location ~ /\.(git|svn) { deny all; }

// 6. robots.txt 不要暴露真实敏感路径；统一 404 响应避免长度/状态码差异
FIX);
    ?>
    <p class="desc">
        <?php echo '这个站点里藏了若干"不该被看到"的目录与文件：数据库备份、隐藏后台、敏感配置……'; ?>
    </p>

    <?php level_goal('目标：<br>
        1. 扫出 <code>/backup/</code> 目录，下载其中的 <code>db_backup.sql</code>，获得隐藏账号。<br>
        2. 扫出隐藏后台 <code>/admin_area/</code>，页面中藏有通关凭证。<br>
        3. 查看 <code>/robots.txt</code>，里面也有"惊喜"。<br>
        4.（重点）扫出 <code>/backup/.git/</code> 示例仓库：从提交记录中还原"已删除"的敏感文件，拿到通关凭证与开发库口令。<br>
        5.（敏感文件泄露）扫出 <code>/config.php.bak</code>——IDE 自动备份的残留文件，明文泄露数据库口令等配置。<br>
        6.（响应码识别）观察扫描器对 <code>/uploads/</code>（403）、<code>/uploads</code>（301）、不存在路径（404）的不同响应，理解每个状态码的含义。'); ?>

    <h4><?php echo '练习字典'; ?></h4>
    <p style="margin-bottom:18px">
        <a class="btn btn-plain btn-sm" href="<?php echo BASE_URL; ?>/includes/dict_download.php?type=dirs"><?php echo '下载目录字典 dict_dirs.txt'; ?></a>
    </p>

    <h4><?php echo '快速体验（手工猜解）'; ?></h4>
    <form method="get" action="<?php echo BASE_URL; ?>/modules/dirscan/index.php">
        <div class="form-row">
            <label><?php echo '输入你猜到的路径（例如：backup/db_backup.sql）'; ?></label>
            <input type="text" name="path" placeholder="backup/db_backup.sql">
        </div>
        <button class="btn" type="submit"><?php echo '访问'; ?></button>
    </form>

    <?php
    if (isset($_GET['path']) && $_GET['path'] !== '') {
        // 本练习仅做提示性跳转，不构成漏洞；自动拼接当前部署目录（如 /bafanglab）
        $target = site_url($_GET['path']);
        echo '<div class="result">' . '已为你拼接访问地址：' . '<a href="' . htmlspecialchars($target) . '" target="_blank" style="color:var(--accent)">' . htmlspecialchars($target) . '</a></div>';
    }
    ?>
</div>

<?php
// 双语文案统一从 content/<slug>/<variant>/<lang>/<piece>.html 加载
// site_tokens + 语言回退 + JSON 编码 全部由 level_content_js 自动处理
?>
<script>
<?php level_content_js('dirscan', 'index', 'basic', 'basic'); ?>
<?php level_content_js('dirscan', 'index', 'fix', 'fix'); ?>
<?php level_content_js('dirscan', 'index', 'bypass', 'bypass'); ?>
<?php level_content_js('dirscan', 'index', 'hint', 'hint'); ?>
<?php level_content_js('dirscan', 'index', 'tools', 'tools'); ?>
</script>

<?php level_nav_current(); ?>

<?php require_once dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>
