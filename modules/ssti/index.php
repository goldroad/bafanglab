<?php
/**
 * bafanglab - 模块16：SSTI 服务端模板注入
 * 版权所有：八方网域-无涯
 *
 * 场景：官网「欢迎语模板」功能，运营可自定义问候语模板。
 * 靶场故意实现了一个直接 eval 表达式的自研模板引擎，
 * 导致 {{7*7}} 可求值、{{system()}} 可直接执行系统命令。
 */
require_once dirname(dirname(__DIR__)) . '/includes/i18n.php';
$page_title = 'SSTI 模板注入';
require_once dirname(dirname(__DIR__)) . '/includes/header.php';

/**
 * 靶场自研模板引擎（故意不安全，仅用于教学）
 *
 * 渲染规则：
 *   1. {{变量名}} 替换为模板变量值
 *   2. 其它 {{表达式}} 一律交给 eval 求值 —— 漏洞点
 *
 * @param string $tpl  模板内容
 * @param array  $vars 模板变量表
 * @return string 渲染结果
 */
function bafang_render($tpl, $vars = array())
{
    return preg_replace_callback('/\{\{(.*?)\}\}/s', function ($m) use ($vars) { // @vuln
        $expr = trim($m[1]);
        if ($expr === '') { return ''; }

        // 命中模板变量：按变量替换
        if (isset($vars[$expr])) { return $vars[$expr]; }

        // 靶场故意设计：未定义的表达式直接当 PHP 代码执行，无任何白名单
        $expr = rtrim($expr, ';');
        ob_start();
        try {
            $ret = @eval('return ' . $expr . ';'); // @vuln
        } catch (Exception $e) {
            $ret = false;
        } catch (Throwable $e) {
            $ret = false;
        }
        $out = ob_get_clean();

        if ($out !== '' && $out !== false) { return $out; }
        if (is_scalar($ret) && $ret !== false) { return (string)$ret; }
        return '';
    }, $tpl);
}

// 模板变量：正常业务给运营用的占位符
$tplVars = array(
    'site' => '八方网域',
    'user' => '访客',
    'year' => date('Y'),
);

$defaultTpl = "欢迎 {{user}} 访问 {{site}} 官网！\n本网站创建于 {{year}} 年。";

$tpl = isset($_POST['tpl']) ? (string)$_POST['tpl'] : '';
$rendered = null;
$emptyInput = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (trim($tpl) === '') {
        // 空内容不进入渲染，避免前端校验被绕过时仍执行模板
        $emptyInput = true;
    } else {
        if (strlen($tpl) > 2000) {
            $tpl = substr($tpl, 0, 2000);
        }
        $rendered = bafang_render($tpl, $tplVars);
    }
}
?>
<a class="back-link" href="<?php echo BASE_URL; ?>/index.php">&larr; <?php echo t('footer.back'); ?></a>

<div class="panel">
    <h2><?php echo '模块16：SSTI 服务端模板注入'; ?></h2>
    <?php level_tools('basic', 'hint', true, false, 'tools', BASE_URL . '/modules/ssti/case.php'); ?>
    <?php
    level_code(__FILE__, null, <<<'FIX'
// 修复原则：模板只做「取值」，绝不把用户输入当作代码执行

// 1. 只允许白名单变量，禁止任何表达式求值
$allow = array('site', 'user', 'year');
$html = preg_replace_callback('/\{\{(\w+)\}\}/', function ($m) use ($tplVars, $allow) {
    $k = $m[1];
    if (!in_array($k, $allow, true)) { return ''; }   // 未在白名单内直接丢弃
    return htmlspecialchars($tplVars[$k], ENT_QUOTES, 'UTF-8');
}, $tpl);

// 2. 语法层面就禁止执行：用 \w+ 而非 .*? ，只匹配变量名，不匹配表达式
//    正则 /\{\{(\w+)\}\}/ 无法匹配 {{7*7}}、{{system("id")}}，从根上杜绝注入

// 3. 确实需要表达式能力时，使用成熟模板引擎的沙箱模式，而不是 eval
//    Twig:  $twig->render($tpl, $vars)          （默认不执行任意 PHP 函数）
//    Twig:  new Twig\Environment($loader, ['sandbox' => $sandboxPolicy])
//    Smarty / Blade 同理，均不允许调用 system/exec/file_get_contents

// 4. 模板内容由运营维护时，做版本评审 + 变更审计，不允许前端用户提交模板
FIX
    );
    ?>
    <p class="desc">
        <?php echo '官网「欢迎语模板」支持自定义 <code>{{变量}}</code> 占位符，服务端渲染后输出。<br>
             方向：可以先尝试简单的数学运算或函数调用确认是否存在模板注入，再逐步尝试执行系统命令或读取文件。'; ?>
    </p>
    <?php level_goal('目标：让模板引擎执行你构造的表达式，最终读取本模块目录下的 <code>secret.txt</code>。'); ?>

    <form method="post" action="<?php echo BASE_URL; ?>/modules/ssti/index.php"
          onsubmit="if(!this.tpl.value.trim()){alert('<?php echo '请输入内容！'; ?>');this.tpl.focus();return false;}">
        <div class="form-row">
            <label><?php echo '模板内容'; ?></label>
            <textarea name="tpl" rows="6" placeholder="<?php echo htmlspecialchars($defaultTpl, ENT_QUOTES); ?>"><?php
                echo htmlspecialchars($tpl !== '' ? $tpl : $defaultTpl, ENT_QUOTES);
            ?></textarea>
        </div>
        <button class="btn" type="submit"><?php echo '渲染模板'; ?></button>
    </form>

    <?php if ($emptyInput): ?>
        <div class="result warn"><?php echo '请输入内容！'; ?></div>
    <?php elseif ($rendered !== null): ?>
        <h4><?php echo '渲染结果'; ?></h4>
        <div class="result warn">
            <div style="white-space:pre-wrap"><?php
                echo $rendered === ''
                    ? htmlspecialchars('（无输出）表达式已执行，但没有回显内容。')
                    : htmlspecialchars($rendered);
            ?></div>
        </div>
    <?php endif; ?>
</div>

<?php level_nav_current(); ?>

<?php require_once dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>
