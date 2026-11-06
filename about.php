<?php
/**
 * bafanglab - 靶场说明
 * 版权所有：八方网域-无涯
 *
 * 静态介绍页：项目介绍、作者介绍、建议学习的基础知识、其他靶场。
 */
require_once 'includes/i18n.php';
$page_title = '靶场说明';
require_once 'includes/header.php';
?>

<a class="back-link" href="<?php echo BASE_URL; ?>/index.php">&larr; <?php echo t('footer.back'); ?></a>

<div class="panel">
    <h2><?php echo '项目介绍'; ?></h2>
    <ul class="doc-step">
        <li><?php echo '<strong>为什么要开发这个靶场？</strong> 市面上并不缺靶场——DVWA 和 pikachu 都很经典，但它们太老了：界面和代码风格停留在多年前，关卡设计零散，很多新漏洞类型覆盖不到，用起来也谈不上顺手。而我们的学员需要的是一个能<b>贯穿所有常见 Web 漏洞</b>的统一靶场：一套环境、一条主线、学完整套体系。bafanglab 就是为这个需求而生的。'; ?></li>
        <li><?php echo '<strong>它新在哪里？</strong> 传统靶场只给你一个漏洞入口，剩下的靠自己摸索。本靶场为每一关创新性地补齐了四块内容：<b>基础知识</b>把原理讲透、<b>修复方法</b>站在开发者视角给出可运行的修复代码、<b>挖掘技巧</b>来自 SRC 实战经验、<b>真实案例</b>全部来自作者自己的挖洞案例（已脱敏）。从「是什么」到「怎么挖」再到「怎么修」，一个关卡走完一个闭环，学习和练习效率都会高很多。'; ?></li>
        <li><?php echo '<strong>学完你能收获什么？</strong> 能独立使用 Burp Suite 完成抓包、改包、重放的全套操作；能对 OWASP Top 10 每类漏洞讲清成因、亲手写出 payload、给出修复建议；面对一个陌生站点时有一套系统化的手测思路；更重要的是建立「原理不变、工具会变」的意识——今天的漏洞明天会变种，但方法论可以复用一辈子。'; ?></li>
        <li><?php echo '<strong>为什么用 PHP 开发？</strong> 因为它部署最简单：解压就能跑，让你把时间花在学漏洞而不是配环境上。但漏洞本身从不挑语言——注入、XSS、反序列化在 Java、Python、Go 系统里本质完全相同，只是语法外壳不同。我们从 PHP 靶场里学会原理和利用方式，再迁移到任何语言构建的系统上，<b>触类旁通</b>。'; ?></li>
    </ul>
</div>

<div class="panel">
    <h2><?php echo '作者介绍'; ?></h2>
    <ul class="doc-step">
        <li><strong><?php echo '八方网域'; ?></strong>：<?php echo '码士集团旗下，专业的在线网络安全教育机构。'; ?> <a href="https://www.bafangwy.com/" target="_blank" rel="noopener"><?php echo 'https://www.bafangwy.com/'; ?></a></li>
        <li><strong><?php echo '无涯'; ?></strong>：<?php echo '2018 年起从事教育行业，八方网域 AI + 安全课程负责人。'; ?></li>
    </ul>
</div>

<div class="panel">
    <h2><?php echo '建议学习的基础知识'; ?></h2>
    <p class="desc">
        <?php echo '漏洞挖掘是综合能力的体现，具备以下基础知识，能帮助你更快理解关卡内容。'; ?>
    </p>
    <table class="chk-table">
        <tr>
            <th style="width:150px"><?php echo '知识领域'; ?></th>
            <th><?php echo '为什么重要'; ?></th>
            <th style="width:200px"><?php echo '学习目标'; ?></th>
        </tr>
        <tr>
            <td><strong><?php echo 'Linux 操作系统'; ?></strong></td>
            <td><?php echo '绝大部分企业的网站服务器都运行 Linux，学习阶段也有大量程序需要在 Linux 系统上部署。'; ?></td>
            <td><?php echo '掌握常用命令。'; ?></td>
        </tr>
        <tr>
            <td><strong><?php echo '计算机网络'; ?></strong></td>
            <td><?php echo '理解计算机之间通信的完整流程，弄清一次请求是如何从浏览器到达服务器的。'; ?></td>
            <td><?php echo '掌握常见的网络协议。'; ?></td>
        </tr>
        <tr>
            <td><strong>HTML + JS</strong></td>
            <td><?php echo '浏览器中看到的页面，本质上都是由 HTML 和 JS 构成的。'; ?></td>
            <td><?php echo '能看懂 HTML 和 JS 代码。'; ?></td>
        </tr>
        <tr>
            <td><strong><?php echo 'MySQL 数据库'; ?></strong></td>
            <td><?php echo '目前最流行的开源数据库，绝大多数 Web 系统都使用它存储数据。'; ?></td>
            <td><?php echo '掌握常用 SQL 语法。'; ?></td>
        </tr>
        <tr>
            <td><strong><?php echo 'PHP 程序'; ?></strong></td>
            <td><?php echo '大量开源 CMS 和企业系统使用 PHP 开发，也是本靶场的主要开发语言。'; ?></td>
            <td><?php echo '能看懂 PHP 代码。'; ?></td>
        </tr>
        <tr>
            <td><strong><?php echo 'AI 智能体'; ?></strong></td>
            <td><?php echo 'AI 正在改变安全攻防的工作方式，善用工具能大幅提升学习与工作效率。'; ?></td>
            <td><?php echo '至少掌握使用 Trae 开发、使用 WorkBuddy 办公。'; ?></td>
        </tr>
    </table>
    <p class="desc" style="margin-top:12px">
        <?php echo '以上是学习各关卡前建议掌握的最小知识集。想按完整的路线图系统学习安全知识，请查看'; ?>
        <a href="https://www.processon.com/view/link/6a1d0ead9efe202a33b62c82" target="_blank" rel="noopener noreferrer"><?php echo '更系统的学习路线（思维导图）'; ?></a>。
    </p>
</div>

<div class="panel">
    <h2><?php echo '其他靶场'; ?></h2>
    <p class="desc">
        <?php echo '本项目聚焦于常见 Web 技术漏洞。业务逻辑漏洞以及 AI 项目漏洞，请下载无涯老师开发/修改的其他靶场：'; ?>
    </p>
    <ul class="doc-step">
        <li><?php echo '业务逻辑漏洞靶场'; ?>：
            <a href="https://github.com/goldroad/edu_logic_vul_public" target="_blank" rel="noopener noreferrer">github.com/goldroad/edu_logic_vul_public</a></li>
        <li><?php echo 'AI 安全靶场'; ?>：
            <a href="https://github.com/goldroad/airt_wuya" target="_blank" rel="noopener noreferrer">github.com/goldroad/airt_wuya</a></li>
    </ul>
</div>

<div class="panel">
    <h2><?php echo '本项目提到的其他靶场'; ?></h2>
    <p class="desc">
        <?php echo '以下靶场在本项目的各关卡基础知识中被推荐，适合学完本靶场后继续深入练习。'; ?>
    </p>
    <table class="chk-table">
        <tr>
            <th style="width:150px"><?php echo '靶场'; ?></th>
            <th style="width:150px"><?php echo '类型'; ?></th>
            <th style="width:380px"><?php echo '仓库地址'; ?></th>
            <th><?php echo '教程'; ?></th>
        </tr>
        <tr>
            <td><strong>xss-labs</strong></td>
            <td><?php echo 'XSS 专项'; ?></td>
            <td><a href="https://github.com/do0dl3/xss-labs" target="_blank" rel="noopener noreferrer">https://github.com/do0dl3/xss-labs</a></td>
            <td><a href="https://wiki.bafangwy.com/doc/630/" target="_blank" rel="noopener noreferrer">https://wiki.bafangwy.com/doc/630/</a></td>
        </tr>
        <tr>
            <td><strong>sqli-labs</strong></td>
            <td><?php echo 'SQL 注入专项'; ?></td>
            <td><a href="https://github.com/skyblueee/sqli-labs-php7" target="_blank" rel="noopener noreferrer">https://github.com/skyblueee/sqli-labs-php7</a></td>
            <td><a href="https://wiki.bafangwy.com/doc/626/" target="_blank" rel="noopener noreferrer">https://wiki.bafangwy.com/doc/626/</a></td>
        </tr>
        <tr>
            <td><strong>upload-labs</strong></td>
            <td><?php echo '文件上传专项'; ?></td>
            <td><a href="https://github.com/c0ny1/upload-labs" target="_blank" rel="noopener noreferrer">https://github.com/c0ny1/upload-labs</a></td>
            <td><a href="https://wiki.bafangwy.com/doc/629/" target="_blank" rel="noopener noreferrer">https://wiki.bafangwy.com/doc/629/</a></td>
        </tr>
        <tr>
            <td><strong>DVWA</strong></td>
            <td><?php echo '综合漏洞平台'; ?></td>
            <td><a href="https://github.com/digininja/DVWA" target="_blank" rel="noopener noreferrer">https://github.com/digininja/DVWA</a></td>
            <td><a href="https://wiki.bafangwy.com/doc/627/" target="_blank" rel="noopener noreferrer">https://wiki.bafangwy.com/doc/627/</a></td>
        </tr>
        <tr>
            <td><strong>pikachu</strong></td>
            <td><?php echo '生活化场景靶场'; ?></td>
            <td><a href="https://github.com/zhuifengshaonianhanlu/pikachu" target="_blank" rel="noopener noreferrer">https://github.com/zhuifengshaonianhanlu/pikachu</a></td>
            <td><a href="https://wiki.bafangwy.com/doc/628/" target="_blank" rel="noopener noreferrer">https://wiki.bafangwy.com/doc/628/</a></td>
        </tr>
    </table>
</div>

<div class="panel">
    <h2><?php echo '本项目提到的工具汇总'; ?></h2>
    <p class="desc">
        <?php echo '以下工具来自各关卡的"推荐工具"内容，按用途分类整理，点击关卡中的"推荐工具"按钮可查看完整使用命令。'; ?>
    </p>
    <table class="chk-table">
        <tr>
            <th style="width:180px"><?php echo '用途分类'; ?></th>
            <th style="width:200px"><?php echo '工具名称'; ?></th>
            <th><?php echo '适用关卡 / 说明'; ?></th>
        </tr>
        <tr>
            <td rowspan="2"><strong><?php echo '抓包与测试'; ?></strong></td>
            <td>Burp Suite</td>
            <td><?php echo '全靶场核心工具，模块 1-5 专门训练 Burp 实操。'; ?></td>
        </tr>
        <tr>
            <td>Burp HAE / LinkFinder / captcha-killer-modified</td>
            <td><?php echo 'Burp 扩展商店免费装，各关卡 tools.html 里有具体用法。'; ?></td>
        </tr>
        <tr>
            <td><strong><?php echo '暴力破解'; ?></strong></td>
            <td>Hydra / Medusa / Burp Intruder</td>
            <td><?php echo '模块 5 暴力猜解，Hydra 速度快协议多，Burp Intruder 最直观。'; ?></td>
        </tr>
        <tr>
            <td rowspan="2"><strong><?php echo '目录扫描 / Fuzz'; ?></strong></td>
            <td>dirsearch / dirb / ffuf</td>
            <td><?php echo '模块 2 目录扫描，dirsearch 速度最快推荐。'; ?></td>
        </tr>
        <tr>
            <td>wfuzz / ffuf / GitHack / git-dumper</td>
            <td><?php echo '模块 3 参数 Fuzz；扫到 .git 后用 git-dumper 还原仓库。'; ?></td>
        </tr>
        <tr>
            <td><strong><?php echo 'SQL 注入自动化'; ?></strong></td>
            <td>sqlmap</td>
            <td><?php echo '模块 7 全系列注入，支持 UNION/报错/布尔盲注/时间盲注/POST，3 秒自动识别导出。'; ?></td>
        </tr>
        <tr>
            <td><strong><?php echo '敏感信息扫描'; ?></strong></td>
            <td>Trufflehog（<?php echo '浏览器插件'; ?>）</td>
            <td><?php echo '模块 4 JS 信息泄露，Chrome/Edge 内直接扫描 500+ 种密钥。'; ?></td>
        </tr>
        <tr>
            <td><strong><?php echo 'OCR 验证码'; ?></strong></td>
            <td>ddddocr + Flask</td>
            <td><?php echo '模块 5 带验证码猜解，本地部署 HTTP 服务对接 Burp OCR 插件。'; ?></td>
        </tr>
    </table>
</div>

<?php require_once 'includes/footer.php'; ?>
