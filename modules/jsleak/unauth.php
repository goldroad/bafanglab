<?php
/**
 * bafanglab - 第4关：未授权访问内部接口
 * 版权所有：八方网域-无涯
 */
require_once dirname(dirname(__DIR__)) . '/includes/i18n.php';
$page_title = '未授权访问接口';
require_once dirname(dirname(__DIR__)) . '/includes/header.php';

$msg = '';
if (isset($_POST['do_login'])) {
    // 靶场设计：前台登录永远失败，用于突出"后端接口未做鉴权"的问题
    $msg = '<div class="msg fail">' . '账号不存在或密码错误。提示：前端登录失败，并不代表后端接口也做了校验。' . '</div>';
}
?>
<a class="back-link" href="<?php echo BASE_URL; ?>/index.php">&larr; <?php echo t('footer.back'); ?></a>

<div class="panel">
    <h2><?php echo '模块4：未授权访问接口'; ?></h2>
    <?php jsleak_tabs('tab.jsleak_unauth'); ?>
    <?php level_tools('basic2', 'hint2', true, false, 'tools', BASE_URL . '/modules/jsleak/case.php'); ?>
    <?php
    // 本关漏洞在 api/users.php：全程没有任何鉴权就直接输出数据
    level_code(dirname(__FILE__) . '/api/users.php', null, <<<'FIX'
// 修复：接口入口处统一鉴权，未登录直接 401，再按角色/数据归属过滤字段
session_start();
header('Content-Type: application/json; charset=utf-8');

// 1. 登录校验（所有接口默认要求登录）
if (empty($_SESSION['uid'])) {
    http_response_code(401);
    exit(json_encode(array('code' => 401, 'msg' => '未登录')));
}

// 2. 角色校验（员工列表属敏感数据，仅管理员可见）
if ($_SESSION['role'] !== 'admin') {
    http_response_code(403);
    exit(json_encode(array('code' => 403, 'msg' => '无权访问')));
}

// 3. 最小化返回字段：手机号/邮箱/内部备注按需脱敏或裁剪
FIX);
    ?>
    <p class="desc">
        <?php echo '这是内部员工中心的登录页，页面本身要求登录后才能查看员工数据。<br>
        但后端的数据接口没有做登录与权限校验——只要知道接口地址，就能直接拿到全部员工信息。'; ?>
    </p>
    <?php level_goal('目标：找到员工数据接口并直接访问，从 admin 的记录中获取通关凭证。'); ?>

    <?php echo $msg; ?>
    <form method="post" action="<?php echo BASE_URL; ?>/modules/jsleak/unauth.php">
        <div class="form-row">
            <label><?php echo '用户名'; ?></label>
            <input type="text" name="username" value="employee" style="max-width:360px">
        </div>
        <div class="form-row">
            <label><?php echo '密码'; ?></label>
            <input type="password" name="password" value="" style="max-width:360px">
        </div>
        <button class="btn" type="submit" name="do_login" value="1"><?php echo '登录员工中心'; ?></button>
    </form>

</div>

<?php level_nav_current(); ?>

<?php require_once dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>
