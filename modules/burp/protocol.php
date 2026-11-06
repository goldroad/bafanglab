<?php
/**
 * bafanglab - 模块1：Burp 抓包基本功 · 网络协议入门（纯教学页）
 * 版权所有：八方网域-无涯
 *
 * 本页不含任何练习操作，只做三张原理图的知识铺垫：
 *   图1 Burp 抓包原理（代理如何看到请求/响应）
 *   图2 动态网站请求流程（容器 → 代码 → 数据库 → 原路返回）
 *   图3 HTTP 报文结构（请求行/请求头/请求体、响应行/状态码/响应头/响应体）
 */
require_once dirname(dirname(__DIR__)) . '/includes/i18n.php';
$page_title = '网络协议入门';
$page_no_level_badge = true; // 纯知识页：标题不追加「N levels」徽标
require_once dirname(dirname(__DIR__)) . '/includes/header.php';

// 图片文件名含中文，URL 中必须编码，否则部分浏览器/代理会请求失败
$img1 = BASE_URL . '/images/' . rawurlencode('1-Burp抓包原理.png');
$img2 = BASE_URL . '/images/' . rawurlencode('2-网站请求流程.png');
$img3 = BASE_URL . '/images/' . rawurlencode('3-HTTP协议.png');
?>
<a class="back-link" href="<?php echo BASE_URL; ?>/index.php">&larr; <?php echo t('footer.back'); ?></a>

<div class="panel">
    <h2><?php echo '网络协议入门'; ?></h2>
    <?php burp_basic_tabs('tab.burp_intro'); ?>

    <p class="desc">
        <?php echo '抓包的前提是看懂 HTTP。这一节用三张图讲清楚：<b>Burp 为什么能抓到包</b>、<b>动态网页的内容是怎么产生的</b>、
        <b>一条 HTTP 报文由哪些部分组成</b>。'; ?>
    </p>

    <!-- ==================== 图1：Burp 抓包原理 ==================== -->
    <h4><?php echo '一、Burp 抓包原理：把浏览器的连接目标改到代理上'; ?></h4>
    <figure class="doc-figure">
        <img src="<?php echo $img1; ?>" alt="<?php echo 'Burp 抓包原理图'; ?>">
        <figcaption><?php echo '图1：上排为浏览器直接访问服务器；下排为浏览器改为经 Burp 代理访问服务器'; ?></figcaption>
    </figure>
    <div class="result">
        <?php echo '<b>上排 —— 没有 Burp 时</b><br>
        浏览器直接连目标服务器（Nginx / Apache / Tomcat，监听 80 / 443 / 8080），请求和响应点对点直达，中间没有人能看到。'
            . '<br><br><b>下排 —— 开启 Burp 后</b><br>
        把浏览器的代理设置改到 <code>127.0.0.1:8080</code>，浏览器<b>以为</b>自己在访问服务器，实际上所有流量先交给 Burp，
        由 Burp 再转发给目标服务器。服务器返回的响应也先回到 Burp，再交给浏览器。'
            . '<br><br><b>于是 Burp 站在中间，能完整看到：</b><br>
        ① 浏览器发出的 <b>请求 HTTP</b>（方法、URL、请求头、请求体，含 Cookie）<br>
        ② 服务器返回的 <b>响应 HTTP</b>（状态码、响应头、响应体）<br>
        ③ 如果打开 <b>Intercept is on</b>，请求会被暂停在 Burp 里，可以改完再 Forward 放行；响应默认直接放行（也可设置为拦截）。'; ?>
    </div>
    <div class="doc-note">
        <?php echo '一句话总结：Burp 的本质是「中间人代理」——它不改服务器，只改浏览器的连接目标。这也是后面所有改包练习能成立的原因。'; ?>
    </div>

    <!-- ==================== 图2：动态网站请求流程 ==================== -->
    <h4><?php echo '二、动态网站是如何显示出内容的'; ?></h4>
    <figure class="doc-figure">
        <img src="<?php echo $img2; ?>" alt="<?php echo '动态网站请求流程图'; ?>">
        <figcaption><?php echo '图2：浏览器 → HTTP 容器 → 代码解释器 → 数据库，结果再封装成 HTTP 响应返回'; ?></figcaption>
    </figure>
    <div class="result">
        <?php echo '<ol class="doc-step">
            <li><b>访问 HTTP 服务器：</b>浏览器向 <code>' . htmlspecialchars(site_url('modules/burp/index.php?role=user')) . '</code> 发起请求（默认 80 端口）。</li>
            <li><b>容器接收 HTTP 请求：</b>Apache / Nginx 监听着端口，收到请求后判断这不是静态文件，而是需要执行脚本的请求。</li>
            <li><b>调用程序解析代码：</b>容器把脚本交给 <code>php.exe</code>（或 Java 虚拟机）执行，PHP 逐行解析、运行代码。</li>
            <li><b>程序操作数据库：</b>代码里的 SQL 发往数据服务器，查询用户、订单、留言等内容，结果回到 PHP。</li>
            <li><b>容器把结果封装为 HTTP 数据：</b>PHP 执行完输出的 HTML 交给容器，容器补上响应头（状态码、Content-Type、Content-Length 等）。</li>
            <li><b>原路返回：</b>浏览器收到 HTTP 响应，解析 HTML / CSS / JS 渲染出最终页面。</li>
        </ol>
        <b>关键结论：</b>浏览器拿到的永远只是<b>最终结果</b>，看不到 PHP 源码，也看不到 SQL 语句。<br>
        这也是为什么注入题要在「参数里」做文章——参数会一路传进第 4 步的 SQL 语句。'; ?>
    </div>

    <!-- ==================== 图3：HTTP 协议名词 ==================== -->
    <h4><?php echo '三、HTTP 报文名词：请求与响应'; ?></h4>
    <figure class="doc-figure">
        <img src="<?php echo $img3; ?>" alt="<?php echo 'HTTP 协议报文结构图'; ?>">
        <figcaption><?php echo '图3：Burp 中一条真实请求（左）与对应响应（右）的报文结构标注'; ?></figcaption>
    </figure>

    <div class="result">
        <?php echo '<b>请求报文（浏览器 → 服务器）</b><br>
        <ul class="doc-step">
            <li><b>请求行：</b><code>GET /bafanglab/modules/jsleak/index.php HTTP/1.1</code> —— 依次是「请求方法 + 请求路径 + 协议版本」。</li>
            <li><b>请求头：</b>一堆 <code>名字: 值</code>，描述这次请求的元信息。</li>
            <li><b>空行：</b>请求头结束的标志，之后就是请求体。</li>
            <li><b>请求体：</b>携带的数据，GET 通常没有，POST 才有（如登录账号密码、上传内容）。</li>
        </ul>
        <b>常见请求头（图中出现的都是）：</b><br>
        <code>Host</code> 目标域名与端口 &nbsp;|&nbsp; <code>User-Agent</code> 浏览器标识 &nbsp;|&nbsp; <code>Referer</code> 来源页面<br>
        <code>Cookie</code> 会话凭据（最重要的身份凭证） &nbsp;|&nbsp; <code>Content-Type</code> 请求体格式 &nbsp;|&nbsp; <code>Content-Length</code> 请求体长度<br>
        <code>Origin</code> 跨域来源 &nbsp;|&nbsp; <code>Accept-Encoding</code> 支持的压缩方式 &nbsp;|&nbsp; <code>Connection</code> 是否复用连接'; ?>
    </div>

    <div class="result">
        <?php echo '<b>响应报文（服务器 → 浏览器）</b><br>
        <ul class="doc-step">
            <li><b>响应行：</b><code>HTTP/1.1 200 OK</code> —— 依次是「协议版本 + 响应状态码 + 状态描述」。</li>
            <li><b>响应头：</b>描述这次响应的元信息。</li>
            <li><b>空行：</b>响应头结束的标志。</li>
            <li><b>响应体：</b>真正的 HTML / JSON / 图片等内容，浏览器拿它来渲染页面。</li>
        </ul>
        <b>常见响应头：</b><br>
        <code>Server</code> 服务器与容器标识（信息泄露点） &nbsp;|&nbsp; <code>X-Powered-By</code> 后端语言（如 PHP/7.3.4）<br>
        <code>Content-Type</code> 响应体类型，如 <code>text/html; charset=UTF-8</code> &nbsp;|&nbsp; <code>Content-Length</code> 响应体字节数<br>
        <code>Set-Cookie</code> 服务器下发 Cookie &nbsp;|&nbsp; <code>Location</code> 重定向目标 &nbsp;|&nbsp; <code>Keep-Alive</code> 连接保持时间'; ?>
    </div>

    <!-- ==================== 响应状态码扩展 ==================== -->
    <h4><?php echo '四、响应状态码速查（重点扩展）'; ?></h4>
    <div class="result">
        <?php echo '<table>
        <tr><th>状态码</th><th>含义</th><th>安全测试中的意义</th></tr>
        <tr><td>200 OK</td><td>请求成功</td><td>资源存在，重点看响应体与长度</td></tr>
        <tr><td>201 Created</td><td>创建成功</td><td>常见于注册 / 发布 / 上传接口</td></tr>
        <tr><td>204 No Content</td><td>成功但无响应体</td><td>常见于删除、更新接口</td></tr>
        <tr><td>301 Moved Permanently</td><td>永久重定向</td><td>路径存在（如目录补斜杠），顺着 Location 跟</td></tr>
        <tr><td>302 Found</td><td>临时重定向</td><td>常见于登录后跳转；注意跳转目标是否可控（开放重定向）</td></tr>
        <tr><td>304 Not Modified</td><td>内容未变化</td><td>缓存协商；扫目录时可据此识别已知资源</td></tr>
        <tr><td>400 Bad Request</td><td>请求语法错误</td><td>参数格式被拒，可能是 WAF 或校验生效</td></tr>
        <tr><td>401 Unauthorized</td><td>未认证</td><td>接口存在但需要登录，值得继续尝试</td></tr>
        <tr><td>403 Forbidden</td><td>禁止访问</td><td><b>路径存在但无权限</b>，目录扫描最有价值的信号</td></tr>
        <tr><td>404 Not Found</td><td>资源不存在</td><td>扫目录时用于过滤；要警惕「自定义 404」软 404</td></tr>
        <tr><td>405 Method Not Allowed</td><td>方法不被允许</td><td>说明路径存在，换 GET / POST 再试</td></tr>
        <tr><td>413 Payload Too Large</td><td>请求体过大</td><td>上传 / 提交大包时出现</td></tr>
        <tr><td>429 Too Many Requests</td><td>请求过于频繁</td><td>触发了限速，爆破时需降速或换 IP 头</td></tr>
        <tr><td>500 Internal Server Error</td><td>服务器内部错误</td><td>可能由畸形参数触发，是报错注入的入口</td></tr>
        <tr><td>502 Bad Gateway</td><td>网关错误</td><td>代理 / 反代后端异常，可尝试 SSRF 探测</td></tr>
        <tr><td>503 Service Unavailable</td><td>服务不可用</td><td>后端过载或维护</td></tr>
        <tr><td>504 Gateway Timeout</td><td>网关超时</td><td>时间盲注、SSRF 内网探测的典型回显</td></tr>
        </table>
        <b>按大类记更省力：</b>1xx 信息、2xx 成功、3xx 重定向、4xx 客户端错误、5xx 服务端错误。'; ?>
    </div>

    <!-- ==================== 请求方法扩展 ==================== -->
    <h4><?php echo '五、常见请求方法'; ?></h4>
    <div class="result">
        <?php echo '<table>
        <tr><th>方法</th><th>用途</th><th>测试关注点</th></tr>
        <tr><td>GET</td><td>获取资源，参数在 URL 上</td><td>改 URL 参数即可改包，最直观的练习目标</td></tr>
        <tr><td>POST</td><td>提交数据，参数在请求体</td><td>改请求体参数；注意 Content-Type 与长度</td></tr>
        <tr><td>HEAD</td><td>只要响应头，不要响应体</td><td>轻量探测资源是否存在</td></tr>
        <tr><td>PUT</td><td>上传 / 覆盖资源</td><td>若开启则可能任意文件写入</td></tr>
        <tr><td>DELETE</td><td>删除资源</td><td>越权删除风险</td></tr>
        <tr><td>OPTIONS</td><td>查询允许的方法</td><td>可暴露支持的请求方法与 CORS 配置</td></tr>
        <tr><td>PATCH</td><td>局部更新</td><td>常见于 API 参数覆盖（mass assignment）</td></tr>
        <tr><td>TRACE</td><td>回显请求内容</td><td>若开启可配合 XST 窃取 Cookie</td></tr>
        </table>'; ?>
    </div>

    <div class="doc-note">
        <?php echo '接下来进入练习1：你会看到页面发出的 GET 请求里带着 <code>role=user</code>。现在你已经知道它出现在请求行的路径里，
        所以在 Burp 中改它，就等于直接改变了服务器第 4 步执行的逻辑。'; ?>
    </div>
</div>

<?php level_nav(1, 1); ?>

<?php require_once dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>
