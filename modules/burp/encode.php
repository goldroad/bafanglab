<?php
/**
 * bafanglab - 模块1：Burp 抓包基本功 · 编码基础（纯教学页）
 * 版权所有：八方网域-无涯
 *
 * 本页不含任何练习操作，只做编码原理的知识铺垫，与「网络协议入门」并列：
 *   一、HTML 实体编码（含 XSS 防御中的局限）
 *   二、URL 编码（百分号编码、%20 与 +、双重编码、与 SQL 注入的关系）
 *   三、Base64（原理、在 XXE / SSRF 中的用途）
 *   四、ASCII（可打印范围、在 SQL 盲注中的用法）
 *   五、常见符号、字母速查表（字符 / 十进制 / 十六进制 / URL 编码 / HTML 实体）
 *   六、编码选择对照
 *
 * 速查表数据全部由 ord() 与 dechex() 实时计算，不手写码值，避免抄错。
 */
require_once dirname(dirname(__DIR__)) . '/includes/i18n.php';
$page_title = '编码基础';
$page_no_level_badge = true; // 纯知识页：标题不追加「N levels」徽标
require_once dirname(dirname(__DIR__)) . '/includes/header.php';

/* ---------------- 速查表数据准备（全部由程序计算，保证准确） ---------------- */

// 常用字符的命名实体（未列出的字符使用十进制数字实体 &#NN;）
$entityMap = array(
    ' ' => '&#32;',
    '"' => '&quot;',
    '&' => '&amp;',
    "'" => '&#39;',
    '<' => '&lt;',
    '>' => '&gt;',
);

/**
 * 输出一行速查表数据
 * @param string $ch    原始字符（单字节 ASCII）
 * @param string $label 显示用文本（如空格显示为「空格」）
 */
$encRow = function ($ch, $label = '') use ($entityMap) {
    $dec = ord($ch);
    $hex = '0x' . strtoupper(dechex($dec));
    $url = '%' . strtoupper(str_pad(dechex($dec), 2, '0', STR_PAD_LEFT));
    $ent = isset($entityMap[$ch]) ? $entityMap[$ch] : '&#' . $dec . ';';
    $show = ($label !== '') ? $label : htmlspecialchars($ch, ENT_QUOTES, 'UTF-8');
    echo '<tr><td><code>' . $show . '</code></td>'
        . '<td>' . $dec . '</td>'
        . '<td><code>' . $hex . '</code></td>'
        . '<td><code>' . $url . '</code></td>'
        // 实体必须再次转义，否则浏览器会把 &#65; 直接渲染成字符 A，看不到实体写法本身
        . '<td><code>' . htmlspecialchars($ent, ENT_QUOTES, 'UTF-8') . '</code></td></tr>';
};

/**
 * 输出一组速查表（标题 + 表头 + 逐行数据）
 */
$encGroup = function ($title, $chars, $isSpaceLabel) use ($encRow) {
    echo '<h4>' . $title . '</h4><div class="result"><div class="table-scroll"><table>'
        . '<tr><th>字符</th><th>ASCII 十进制</th><th>ASCII 十六进制</th><th>URL 编码</th><th>HTML 实体</th></tr>';
    foreach ($chars as $c) {
        $encRow($c, ($isSpaceLabel && $c === ' ') ? '空格（SP）' : '');
    }
    echo '</table></div></div>';
};
?>
<a class="back-link" href="<?php echo BASE_URL; ?>/index.php">&larr; <?php echo t('footer.back'); ?></a>

<div class="panel">
    <h2><?php echo '编码基础'; ?></h2>
    <?php burp_basic_tabs('tab.burp_encode'); ?>

    <p class="desc">
        <?php echo '改包改的是<b>数据</b>，而数据在传输、入库、回显的每一步都可能被编码。
        看不懂编码，payload 就会「明明改对了却不生效」。这一节讲清四种最常用的编码：
        <b>HTML 实体</b>、<b>URL 编码</b>、<b>Base64</b>、<b>ASCII</b>，
        并给出可直接查的字符速查表。'; ?>
    </p>

    <!-- ==================== 一、HTML 实体编码 ==================== -->
    <h4><?php echo '一、HTML 实体编码：让特殊字符变成纯文本'; ?></h4>
    <div class="result">
        <?php echo '<b>为什么需要？</b><br>
        在 HTML 里，<code>&lt;</code> 是标签开始、<code>&amp;</code> 是实体开始、引号是属性边界。
        如果页面上要<b>显示</b>这些字符本身，就必须先把它们换成「实体」，
        浏览器解析时再把实体还原成字符显示——这样字符就不会被当成语法。'; ?>
        <table>
            <tr><th>字符</th><th>命名实体</th><th>十进制实体</th><th>十六进制实体</th><th>说明</th></tr>
            <tr><td><code>&lt;</code></td><td><code>&amp;lt;</code></td><td><code>&amp;#60;</code></td><td><code>&amp;#x3C;</code></td><td>标签开始，XSS 最关键的字符</td></tr>
            <tr><td><code>&gt;</code></td><td><code>&amp;gt;</code></td><td><code>&amp;#62;</code></td><td><code>&amp;#x3E;</code></td><td>标签结束</td></tr>
            <tr><td><code>&amp;</code></td><td><code>&amp;amp;</code></td><td><code>&amp;#38;</code></td><td><code>&amp;#x26;</code></td><td>实体开始，必须最先转义</td></tr>
            <tr><td><code>&quot;</code></td><td><code>&amp;quot;</code></td><td><code>&amp;#34;</code></td><td><code>&amp;#x22;</code></td><td>双引号，属性值边界</td></tr>
            <tr><td><code>&#39;</code></td><td><code>&amp;apos;</code>（仅 HTML5）</td><td><code>&amp;#39;</code></td><td><code>&amp;#x27;</code></td><td>单引号，属性值边界；HTML4 无 &amp;apos;</td></tr>
            <tr><td>空格</td><td><code>&amp;nbsp;</code>（不换行空格）</td><td><code>&amp;#32;</code></td><td><code>&amp;#x20;</code></td><td>普通空格是 U+0020；<code>&amp;nbsp;</code> 是 U+00A0，二者不等价</td></tr>
        </table>
        <?php echo '<b>十进制 / 十六进制实体写法：</b><br>
        格式分别是 <code>&amp;#十进制;</code> 与 <code>&amp;#x十六进制;</code>（x 大小写均可），<b>必须带结尾分号</b>。
        例如 <code>&amp;#60;</code>、<code>&amp;#x3C;</code>、<code>&amp;#X3C;</code> 都表示 <code>&lt;</code>。
        数字实体不依赖命名表，任何字符都能用（如 <code>&amp;#65;</code> 就是 <code>A</code>）。'; ?>
        <br><br>
        <?php echo '<b>在 XSS 防御中的作用：</b><br>
        PHP 的 <code>htmlspecialchars($s, ENT_QUOTES, \'UTF-8\')</code> 会把
        <code>&amp; &lt; &gt; &quot; \'</code> 转成实体。当用户输入被输出到 HTML 正文或属性值里时，
        这一步能让 <code>&lt;script&gt;</code> 变成普通文字而不是标签。'; ?>
        <div class="doc-note" style="margin-bottom:0">
            <?php echo '<b>为什么单靠 HTML 实体不够：</b><br>
            实体只对「HTML 解析器」有意义。一旦数据落在<b>别的上下文</b>，实体不会被执行成对应字符，防护就失效：<br>
            <ul class="doc-step">
                <li><b>URL 上下文：</b><code>&lt;a href="用户输入"&gt;</code> 里 <code>javascript:</code> 不含被转义的字符，实体转义挡不住。</li>
                <li><b>JS 上下文：</b><code>&lt;script&gt;var a = "用户输入";&lt;/script&gt;</code> 中，HTML 实体在 JS 字符串里只会保持字面量，
                    正确做法是 <code>json_encode()</code> 或 <code>\u003c</code> 形式。</li>
                <li><b>属性无引号 / 事件属性：</b><code>onclick=用户输入</code> 时，空格与括号才是危险字符，实体表反而没覆盖。</li>
                <li><b>二次解码：</b>数据先被实体化、又被前端用 <code>innerHTML</code> 或 <code>decodeURIComponent</code> 还原一次，仍可能重新生效。</li>
            </ul>
            结论：<b>防护要按输出位置选择编码</b>（HTML 实体、URL 编码、JS 转义、SQL 预处理），而不是"统一转一遍实体"。'; ?>
        </div>
    </div>

    <!-- ==================== 二、URL 编码 ==================== -->
    <h4><?php echo '二、URL 编码（百分号编码）'; ?></h4>
    <div class="result">
        <?php echo '<b>原理：</b>把一个字节写成 <code>%</code> + 两位十六进制。例如 <code>A</code>(0x41) → <code>%41</code>，
        <code>&lt;</code>(0x3C) → <code>%3C</code>，<code>/</code>(0x2F) → <code>%2F</code>，
        空格(0x20) → <code>%20</code>。<br>
        <b>非 ASCII 字符</b>按 UTF-8 多字节逐个编码：<code>中</code>(U+4E2D) → UTF-8 三字节 E4 B8 AD → <code>%E4%B8%AD</code>。'; ?>
        <table>
            <tr><th>类别</th><th>字符</th><th>说明</th></tr>
            <tr><td>非保留字符（可原样传输）</td><td><code>A-Z a-z 0-9 - . _ ~</code></td><td>RFC 3986 规定的 unreserved，编不编码都由服务端还原成同一字符</td></tr>
            <tr><td>保留字符（有语法含义）</td><td><code>: / ? # [ ] @ ! $ &amp; ' ( ) * + , ; =</code></td><td>出现在参数值里时必须编码，否则会被解析成路径 / 分隔符</td></tr>
            <tr><td>不安全字符</td><td>空格、<code>" &lt; &gt; % { } | \ ^ `</code>、非 ASCII</td><td>必须编码，否则 URL 结构被破坏或被代理改写</td></tr>
        </table>
        <?php echo '<b><code>%20</code> 与 <code>+</code> 的区别：</b><br>
        <ul class="doc-step">
            <li><b>路径部分</b>（<code>/a b/</code>）：<code>+</code> 就是普通加号，空格要写 <code>%20</code>。</li>
            <li><b>查询字符串 / 表单体</b>（<code>application/x-www-form-urlencoded</code>）：<code>+</code> 被约定为空格，
                所以 <code>name=a+b</code> 与 <code>name=a%20b</code> 等价。</li>
            <li>这意味着改包时把空格写成 <code>+</code>，在 GET 参数里通常有效，在路径里则无效。</li>
        </ul>
        <b>双重编码：</b>把 <code>%</code> 自己再编码成 <code>%25</code>。例如 <code>&lt;</code> 一次编码是 <code>%3C</code>，
        双重编码后是 <code>%253C</code>。服务端如果只解码一次，拿到的是 <code>%3C</code> 这个「字符串」，
        若它随后又进入第二次解码流程（或交给另一个组件再解），就会还原成 <code>&lt;</code>——这是绕过只解码一次类 WAF 的常见思路。'; ?>
        <div class="doc-note" style="margin-bottom:0">
            <?php echo '<b>与 SQL 注入的关系：</b><br>
            URL 编码解决的是「怎么把字符送进去」，注入要解决的是「送进去后怎么拼成 SQL」。
            很多 WAF / 黑名单会过滤空格与关键字，常用替代手法：<br>
            <ul class="doc-step">
                <li><b>用注释代替空格：</b><code>UNION/**/SELECT/**/1,2,3</code>（MySQL），注释本身相当于空白分隔。</li>
                <li><b>用其他空白字符：</b><code>%09</code>(Tab)、<code>%0A</code>(换行)、<code>%0B</code>、<code>%0C</code>、<code>%0D</code>(回车)，
                    部分解析器把它们视作空白。</li>
                <li><b>关键字拆分或编码：</b>大小写混写 <code>SeLeCt</code>、关键字中间插注释 <code>SEL/**/ECT</code>，
                    或把字符串值改成十六进制 <code>0x414243</code> / <code>CHAR()</code>。</li>
                <li><b>注意解码层数：</b>先 URL 解码再进 SQL 的执行链很常见，所以「编码一次还是两次」要按目标实际行为试，不要盲猜。</li>
            </ul>
            Burp 快捷操作：Repeater 中选中文本按 <code>Ctrl+U</code> 做 URL 编码，<code>Ctrl+Shift+U</code> 解码。'; ?>
        </div>
    </div>

    <!-- ==================== 三、Base64 ==================== -->
    <h4><?php echo '三、Base64 编码：把任意字节压进 64 个安全字符'; ?></h4>
    <div class="result">
        <?php echo '<b>原理：</b>字符表共 64 个：<code>A-Z</code>(0-25)、<code>a-z</code>(26-51)、<code>0-9</code>(52-61)、
        <code>+</code>(62)、<code>/</code>(63)。每 <b>3 个字节</b>（24 位）切成 <b>4 个 6 位</b>，各查表得到一个字符；<br>
        末尾不足 3 字节时补 <code>=</code>：剩 1 字节 → 补 <code>==</code>（2 个字符有效），剩 2 字节 → 补 <code>=</code>（3 个字符有效）。'; ?>
        <table>
            <tr><th>原文</th><th>字节数</th><th>Base64</th><th>说明</th></tr>
            <tr><td><code>abc</code></td><td>3</td><td><code>YWJj</code></td><td>正好 3 字节，无补齐</td></tr>
            <tr><td><code>admin</code></td><td>5</td><td><code>YWRtaW4=</code></td><td>5 = 3+2，末尾补 1 个 <code>=</code></td></tr>
            <tr><td><code>flag</code></td><td>4</td><td><code>ZmxhZw==</code></td><td>4 = 3+1，末尾补 2 个 <code>=</code></td></tr>
            <tr><td><code>中国</code></td><td>6（UTF-8）</td><td><code>5Lit5Zu9</code></td><td>中文按 UTF-8 字节编码后再 Base64</td></tr>
        </table>
        <?php echo '<b>注意：Base64 是编码不是加密</b>——没有密钥，任何人都能解码，它只负责「让字节可安全传输」。
        判断依据：出现 <code>+ / =</code>、长度常为 4 的倍数、解码后是明文或可读结构，基本就是 Base64。'; ?>
        <br><br>
        <?php echo '<b>在 XXE 中的用途：</b><br>
        <ul class="doc-step">
            <li><b>把整段 XML 以 Base64 传入：</b>某些入口不允许出现 <code>&lt;</code>、引号或换行，就先 Base64，
                再配合 <code>data://text/plain;base64,</code> 这类伪协议让解析器解码后当作 XML 解析。示例 XML：<br>
                <code>&lt;?xml version="1.0"?&gt;&lt;!DOCTYPE r [&lt;!ENTITY x SYSTEM "file:///c:/windows/win.ini"&gt;]&gt;&lt;r&gt;&amp;x;&lt;/r&gt;</code><br>
                其 Base64 为 <code>PD94bWwgdmVyc2lvbj0iMS4wIj8+PCFET0NUWVBFIHIgWzwhRU5USVRZIHggU1lTVEVNICJmaWxlOi8vL2M6L3dpbmRvd3Mvd2luLmluaSI+XT48cj4meDs8L3I+</code></li>
            <li><b>外部 DTD / 参数实体：</b>把 DTD 内容或外带数据先 Base64 再外发，避免特殊字符在传输中被破坏，
                也常用来规避只匹配明文关键字的检测。</li>
            <li><b>Blind XXE 外带：</b>把读取到的文件内容 Base64 后拼进外带 URL，接收端解码即可还原（避免换行、特殊字符丢失）。</li>
        </ul>
        <b>在 SSRF / 文件读取中的用途：</b><br>
        <ul class="doc-step">
            <li><b>读源码：</b><code>php://filter/convert.base64-encode/resource=index.php</code>，
                把源码 Base64 后回显，避免 PHP 代码被直接执行而看不到内容。</li>
            <li><b>gopher 载荷：</b>gopher 需要严格的 CRLF 与 URL 编码，构造复杂报文时常先写好原始 HTTP 报文，
                再做 URL 编码（部分场景配合 Base64 中转），用于攻击内网 Redis / MySQL / FastCGI 等。</li>
            <li><b>参数传递与绕过：</b>把内网 URL、JSON、XML 先 Base64 再放进参数，既能避开特殊字符限制，
                也能绕过只检测明文域名 / 关键字的过滤。</li>
        </ul>
        Burp 快捷操作：选中文本 → 右键 <b>Send to Decoder</b>，可连续做 Base64 解码 / 编码并对比结果。'; ?>
    </div>

    <!-- ==================== 四、ASCII 编码 ==================== -->
    <h4><?php echo '四、ASCII 编码：字符与数字的一一对应'; ?></h4>
    <div class="result">
        <?php echo '<b>可打印范围：</b><code>32</code>（空格，0x20）到 <code>126</code>（<code>~</code>，0x7E），共 95 个字符。
        <code>0-31</code> 与 <code>127</code> 是不可打印的控制字符（如 Tab 9、换行 10、回车 13）。<br>
        <b>常用锚点：</b><code>空格=32</code>、<code>0=48</code>、<code>A=65</code>、<code>a=97</code>、<code>DEL=127</code>。
        有了 <code>A=65</code> 与 <code>a=97</code> 两个锚点，其余字母可直接推算。'; ?>
        <table>
            <tr><th>用途</th><th>写法</th><th>结果</th><th>适用</th></tr>
            <tr><td>字符转数字</td><td><code>ASCII('A')</code> / <code>ORD('A')</code></td><td>65</td><td>SQL Server / MySQL</td></tr>
            <tr><td>数字转字符</td><td><code>CHAR(65)</code></td><td><code>A</code></td><td>MySQL / SQL Server（Oracle 用 <code>CHR(65)</code>）</td></tr>
            <tr><td>十六进制字面量</td><td><code>0x41</code></td><td><code>A</code></td><td>MySQL 可用 <code>0x414243</code> 表示 <code>ABC</code></td></tr>
            <tr><td>取子串再转码</td><td><code>ASCII(SUBSTR('admin',1,1))</code> / <code>ORD(MID('admin',1,1))</code></td><td>97</td><td>盲注逐字符判断</td></tr>
        </table>
        <?php echo '<b>SQL 盲注中的典型用法：</b><br>
        <code>AND ASCII(SUBSTR((SELECT password FROM users LIMIT 1),1,1)) &gt; 100</code><br>
        逐位比较字符码，配合二分法即可在没有回显的情况下逐字猜出数据；时间盲注则把比较结果接到
        <code>SLEEP()</code> 或 <code>BENCHMARK()</code> 上，用响应耗时判断真假。'; ?>
        <div class="doc-note" style="margin-bottom:0">
            <?php echo '<b>为什么能绕过部分关键字过滤：</b><br>
            <ul class="doc-step">
                <li><b>字符串不再以明文出现：</b>黑名单匹配的是 <code>admin</code>、<code>select</code> 等字面量，
                    用 <code>0x61646D696E</code> 或 <code>CHAR(97,100,109,105,110)</code> 构造后，报文里根本没有这些词。</li>
                <li><b>用函数替代运算符 / 关键字：</b>如用 <code>ORD()</code>、<code>SUBSTR()</code> 组合完成判断，
                    避开被过滤的 <code>LIKE</code>、<code>REGEXP</code> 等。</li>
                <li><b>数字与逗号通常不在黑名单里：</b>编码后的载荷以数字为主，更容易穿过只针对英文关键字的规则。</li>
                <li><b>局限：</b>目标数据库的函数名与十六进制语法不同（Oracle 用 <code>CHR</code>、SQL Server 用 <code>CHAR</code>、
                    PostgreSQL 用 <code>CHR</code>），需要先判断数据库类型再选写法。</li>
            </ul>
            强调：这类技巧只用于<b>有授权的靶场 / 渗透测试</b>，目的是理解过滤规则为何脆弱，而不是教人绕过生产防护。'; ?>
        </div>
    </div>

    <!-- ==================== 五、速查表 ==================== -->
    <h4><?php echo '五、常见符号、字母速查表'; ?></h4>
    <div class="doc-note">
        <?php echo '表中「字符」列就是实际字符本身；URL 编码列给出百分号编码形式
        （<code>A-Z a-z 0-9 - . _ ~</code> 属于 RFC 3986 非保留字符，可原样传输，这里列出编码形式便于对照）；
        HTML 实体列对 <code>&amp; &lt; &gt; &quot; &#39;</code> 给出命名实体，其余为十进制数字实体。
        所有十进制、十六进制、URL 编码值均由 <code>ord()</code> / <code>dechex()</code> 实时计算生成，不存在手抄误差。'; ?>
    </div>
    <?php
    // 数字 0-9
    $encGroup('数字（0-9）', range('0', '9'), false);
    // 大写字母 A-Z
    $encGroup('大写字母（A-Z）', range('A', 'Z'), false);
    // 小写字母 a-z
    $encGroup('小写字母（a-z）', range('a', 'z'), false);
    // 常用符号（ASCII 32-126 中的符号部分）
    $symbols = array(
        ' ', '!', '"', '#', '$', '%', '&', "'", '(', ')', '*', '+', ',', '-', '.', '/',
        ':', ';', '<', '=', '>', '?', '@', '[', '\\', ']', '^', '_', '`',
        '{', '|', '}', '~',
    );
    $encGroup('常用符号（按码值顺序）', $symbols, true);
    ?>

    <!-- ==================== 六、编码选择对照 ==================== -->
    <h4><?php echo '六、什么时候用哪种编码'; ?></h4>
    <div class="result">
        <table>
            <tr><th>场景</th><th>该用哪种</th><th>示例</th></tr>
            <tr><td>用户输入要显示在 HTML 正文 / 属性值</td><td>HTML 实体</td><td><code>htmlspecialchars()</code> → <code>&amp;lt;script&amp;gt;</code></td></tr>
            <tr><td>数据要放进 URL 参数或路径</td><td>URL 编码</td><td>空格 <code>%20</code>，<code>&amp;</code> 用 <code>%26</code></td></tr>
            <tr><td>二进制 / 结构化文本要跨协议传递</td><td>Base64</td><td><code>data://text/plain;base64,PD94...</code></td></tr>
            <tr><td>要在 SQL 里表达字符或绕过关键字匹配</td><td>ASCII / 十六进制 / CHAR()</td><td><code>0x61646D696E</code>、<code>CHAR(97,100,109,105,110)</code></td></tr>
            <tr><td>数据要嵌入 JS 字符串</td><td>JS 转义（非 HTML 实体）</td><td><code>json_encode()</code> 输出 <code>\u003c</code></td></tr>
        </table>
        <?php echo '<b>实战判断顺序：</b>先看数据落在哪个上下文（HTML / URL / JS / SQL），再决定编码方式；
        然后用 Burp 的 Decoder 或 Repeater 的 <code>Ctrl+U</code> / <code>Ctrl+Shift+U</code> 快速验证编码层数。
        改包时「编码一次没生效」很常见，多半是目标又解码了一层，或编码上下文选错了。'; ?>
    </div>

    <div class="doc-note">
        <?php echo '接下来进入练习1：你会看到请求里带着 <code>role=user</code>。等你遇到参数被过滤、
        payload 不生效的情况，再回到本页查编码表，比死记硬背有效得多。'; ?>
    </div>
</div>

<?php level_nav(1, 1); ?>

<?php require_once dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>
