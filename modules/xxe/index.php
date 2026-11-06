<?php
/**
 * bafanglab - 模块8：XXE XML 外部实体注入
 * 版权所有：八方网域-无涯
 */
require_once dirname(dirname(__DIR__)) . '/includes/i18n.php';
$page_title = 'XXE XML 外部实体注入';
require_once dirname(dirname(__DIR__)) . '/includes/header.php';

// 默认示例 XML（正常业务报文）
$sample_xml = '<?xml version="1.0" encoding="UTF-8"?>
<user>
    <name>张三</name>
    <email>zhangsan@example.com</email>
</user>';

$result = '';
if (isset($_POST['xml']) && trim($_POST['xml']) !== '') {
    $xml = $_POST['xml'];
    // 靶场故意设计：允许加载并替换外部实体（PHP < 8.0 需显式开启实体加载器；
    // PHP 8.0 起该函数已移除，libxml 默认行为由 LIBXML_NOENT 标志控制）
    if (function_exists('libxml_disable_entity_loader')) {
        libxml_disable_entity_loader(false); // @vuln
    }
    $dom = new DOMDocument();
    $ok = @$dom->loadXML($xml, LIBXML_NOENT | LIBXML_DTDLOAD); // @vuln
    if (!$ok) {
        $result = '<div class="result error">' . 'XML 解析失败：请检查 XML 格式是否正确（读取包含 &lt; &amp; 等特殊字符的文件时会破坏 XML 结构导致解析失败）。' . '</div>';
    } else {
        $name  = '';
        $email = '';
        $names = $dom->getElementsByTagName('name');
        if ($names->length > 0) {
            $name = $names->item(0)->nodeValue;
        }
        $emails = $dom->getElementsByTagName('email');
        if ($emails->length > 0) {
            $email = $emails->item(0)->nodeValue;
        }
        $result = '<div class="result">'
            . '解析成功：' . '<br>'
            . '姓名：' . '<b>' . htmlspecialchars($name) . '</b><br>'
            . '邮箱：' . '<b>' . htmlspecialchars($email) . '</b></div>';
    }
}
?>
<a class="back-link" href="<?php echo BASE_URL; ?>/index.php">&larr; <?php echo t('footer.back'); ?></a>

<div class="panel">
    <h2><?php echo '模块8：XXE XML 外部实体注入'; ?></h2>
    <?php level_tools('basic', 'hint', true, false, 'tools', BASE_URL . '/modules/xxe/case.php'); ?>
    <?php
    level_code(__FILE__, null, <<<'FIX'
// 修复方案1（PHP < 8.0）：禁用外部实体加载器，且不加 LIBXML_NOENT 标志
libxml_disable_entity_loader(true);
$dom = new DOMDocument();
$dom->loadXML($xml);   // 外部实体不再被替换，<!ENTITY> 声明失效
// PHP >= 8.0 起 libxml 默认不加载外部实体，无需第一行调用

// 修复方案2（辅助手段）：解析前直接拒绝含 DTD/ENTITY 关键字的文档
if (stripos($xml, '<!DOCTYPE') !== false || stripos($xml, '<!ENTITY') !== false) {
    die('XML 中不允许包含 DTD/ENTITY');
}

// 修复方案3（推荐）：能用 JSON 就不用 XML
$data  = json_decode(file_get_contents('php://input'), true);
$name  = isset($data['name'])  ? $data['name']  : '';
$email = isset($data['email']) ? $data['email'] : '';
FIX);
    ?>
    <p class="desc">
        <?php echo '这是一个"用户资料 XML 导入"接口：提交 XML 报文，服务器解析后回显姓名与邮箱。'; ?>
    </p>
    <?php level_goal('目标1：构造外部实体，读取服务器本地文件（Windows: <code>C:/Windows/win.ini</code>，Linux: <code>/etc/passwd</code>）。<br>
        目标2：用 <code>php://filter</code> base64 编码读取 <code>config.php</code> 源码。<br>
        目标3（进阶）：通过 XXE 让服务器请求内网地址，观察回显/报错差异。'); ?>

    <form method="post" action="<?php echo BASE_URL; ?>/modules/xxe/index.php">
        <div class="form-row">
            <label><?php echo 'XML 报文'; ?></label>
            <textarea name="xml" rows="10" style="width:100%;max-width:720px;font-family:Consolas,monospace"><?php
                echo isset($_POST['xml']) ? htmlspecialchars($_POST['xml']) : htmlspecialchars($sample_xml);
            ?></textarea>
        </div>
        <button class="btn" type="submit"><?php echo '提交解析'; ?></button>
    </form>

    <?php echo $result; ?>
</div>

<?php
// 双语文案统一从 content/<slug>/<variant>/<lang>/<piece>.html 加载
// site_tokens + 语言回退 + JSON 编码 全部由 level_content_js 自动处理
?>
<script>
<?php level_content_js('xxe', 'index', 'basic', 'basic'); ?>
<?php level_content_js('xxe', 'index', 'fix', 'fix'); ?>
<?php level_content_js('xxe', 'index', 'bypass', 'bypass'); ?>
<?php level_content_js('xxe', 'index', 'hint', 'hint'); ?>
</script>

<?php level_nav_current(); ?>

<?php require_once dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>
