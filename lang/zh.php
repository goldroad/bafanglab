<?php
/**
 * bafanglab - 中文语言包（基准语言，其它语种缺失的键自动回退到此文件）
 * 版权所有：八方网域-无涯
 */
return array(

    /* ---------- 站点外壳 ---------- */
    'site.sub'          => 'Web 漏洞靶场',
    'nav.home'          => '首页',
    'nav.check'         => '环境检测',
    'nav.install'       => '安装/重置',
    'nav.github'        => 'GitHub',
    'nav.about'         => '靶场说明',
    'lang.label'        => '语言',

    'footer.back'       => '返回首页',
    'footer.copy'       => '版权所有 &copy; {year}',
    'footer.author'     => '八方网域-无涯',
    'footer.version'    => 'v{ver}',

    /* ---------- 前端 JS 文案 ---------- */
    'js.theme_dark'     => '切换深色主题',
    'js.theme_light'    => '切换浅色主题',
    'js.modal_hint'     => '过关提示',
    'js.modal_basic'    => '基础知识',
    'js.modal_src'      => '漏洞源码',
    'js.modal_fix'      => '修复方法',
    'js.modal_tools'    => '推荐工具',
    'js.modal_case'     => '真实案例',
    'js.lvl_empty'      => '暂无内容',

    /* ---------- 学习辅助按钮 ---------- */
    'tools.label'       => '学习辅助',
    'tools.practice'    => '开始练习',
    'tools.basic'       => '基础知识',
    'tools.hint'        => '提示',
    'tools.tools'       => '推荐工具',
    'tools.src'         => '漏洞源码',
    'tools.fix'         => '修复方法',
    'tools.case'        => '真实案例',

    /* ---------- 关卡切换导航 ---------- */
    'nav.prev_tpl'      => '&larr; 上一关：{no}. {title}',
    'nav.next_tpl'      => '下一关：{no}. {title} &rarr;',
    'nav.back_home'     => '&larr; 返回首页',
    'nav.finished'      => '已完成全部关卡，返回首页 &rarr;',
    'nav.switch'        => '&#9776; 切换关卡',

    /* ---------- 首页 ---------- */
    'index.h1'          => 'bafanglab Web 漏洞靶场',
    'index.tag1'        => 'Burp Suite 实战',
    'index.tag2'        => 'OWASP Top 10',
    'index.tag3'        => '{n} 类漏洞场景',
    'index.enter'       => '进入关卡',

    /* ---------- 子关卡数量徽标 ---------- */
    'lvl.levels'        => 'levels',
    'lvl.badge_aria'    => '{n} 个关卡',

    /* ---------- 选项卡 ---------- */
    'tab.sqli_get'      => 'GET 数字型',
    'tab.sqli_post'     => 'POST 字符型',
    'tab.sqli_json'     => 'JSON 注入',
    'tab.sqli_header'   => '请求头注入',
    'tab.sqli_error'    => '报错注入',
    'tab.sqli_blind'    => '布尔盲注',
    'tab.sqli_time'     => '时间盲注',
    'tab.sqli_outfile'  => '文件读写',
    'tab.case'          => '真实案例',
    'tab.burp_basic'    => '基础知识',
    'tab.burp_intro'    => '网络协议入门',
    'tab.burp_encode'   => '编码基础',
    'tab.burp_dnslog'   => 'OOB 和 DNSLog',
    'tab.burp_1'        => '练习1 · 修改GET请求',
    'tab.burp_2'        => '练习2 · 修改POST请求',
    'tab.burp_3'        => '练习3 · 修改响应',
    'tab.burp_4'        => '练习4 · 请求重放',
    'tab.rce_cmd'       => '命令执行',
    'tab.rce_code'      => '代码执行',
    'tab.jsleak_js'     => 'JS 敏感信息泄露',
    'tab.jsleak_unauth' => '未授权访问接口',
    'tab.brute_nocap'   => '无验证码版',
    'tab.brute_cap'     => '有验证码版',
    'tab.xss_reflect'   => '反射型 XSS',
    'tab.xss_stored'    => '存储型 XSS',
    'tab.xss_dom'       => 'DOM 型 XSS',
    'tab.xss_upload'    => '上传型 XSS',
    'tab.xss_platform'  => 'XSS 平台',

    /* ---------- 模块 1-16 标题与简介 ---------- */
    'm.burp.tag'        => 'Burp',
    'm.burp.title'      => 'Burp 抓包基本功',
    'm.burp.desc'       => '练习抓请求、修改请求（GET / POST 参数与金额）、抓响应、修改响应、请求重放等 Burp Suite 基础操作。',

    'm.dirscan.tag'     => '信息收集',
    'm.dirscan.title'   => '目录扫描',
    'm.dirscan.desc'    => '站点中隐藏了若干敏感目录与备份文件，用字典把它们扫出来。',

    'm.fuzz.tag'        => 'FUZZ',
    'm.fuzz.title'      => '参数 Fuzz',
    'm.fuzz.desc'       => '页面存在未公开显示的隐藏参数，通过参数爆破发现它们。',

    'm.jsleak.tag'      => '信息泄露',
    'm.jsleak.title'    => 'JS 敏感信息泄露',
    'm.jsleak.desc'     => '前端 JS 中残留了云存储 AK/SK 与内部接口，练习信息提取与未授权接口访问。',

    'm.brute.tag'       => '弱口令',
    'm.brute.title'     => '密码暴力猜解',
    'm.brute.desc'      => '包含无验证码与有验证码两种场景，练习字典爆破与绕过技巧。',

    'm.xss.tag'         => 'XSS',
    'm.xss.title'       => 'XSS 跨站脚本',
    'm.xss.desc'        => '包含反射型、存储型、DOM 型、上传型与 XSS 平台五个子关卡；XSS 平台以 webhook.site 为例演示收信与回连流程，并保留靶内自建收信方案。',

    'm.sqli.tag'        => 'SQLi',
    'm.sqli.title'      => 'SQL 注入',
    'm.sqli.desc'       => '包含 GET、POST、JSON、请求头、报错、布尔盲注、时间盲注、文件读写八个子关，练习手工注入、sqlmap 与 INTO OUTFILE 写文件。',

    'm.xxe.tag'         => 'XXE',
    'm.xxe.title'       => 'XXE 外部实体注入',
    'm.xxe.desc'        => 'XML 解析未禁用外部实体，通过实体声明读取本地文件与内网探测。',

    'm.upload.tag'      => 'Upload',
    'm.upload.title'    => '文件上传',
    'm.upload.desc'     => '后端仅简单限制扩展名，尝试绕过限制上传 WebShell。',

    'm.fileinc.tag'     => 'LFI/RFI',
    'm.fileinc.title'   => '文件包含',
    'm.fileinc.desc'    => '通过文件包含读取源码或包含上传的图片马。',

    'm.csrf.tag'        => 'CSRF',
    'm.csrf.title'      => 'CSRF',
    'm.csrf.desc'       => '修改邮箱接口无 CSRF 防护，构造页面让管理员中招。',

    'm.ssrf.tag'        => 'SSRF',
    'm.ssrf.title'      => 'SSRF',
    'm.ssrf.desc'       => '服务器端请求伪造，探测内网端口与读取内网资源。',

    'm.fileread.tag'    => '目录穿越',
    'm.fileread.title'  => '任意文件读取',
    'm.fileread.desc'   => '通过目录穿越读取服务器上的任意文件。',

    'm.rce.tag'         => 'RCE',
    'm.rce.title'       => '命令/代码执行',
    'm.rce.desc'        => '包含命令注入与代码执行两个关卡，直接 Getshell。',

    'm.unserialize.tag'   => 'deserialize',
    'm.unserialize.title' => '反序列化',
    'm.unserialize.desc'  => 'PHP 反序列化漏洞，构造 POP 链触发危险操作。',

    'm.ssti.tag'        => 'SSTI',
    'm.ssti.title'      => 'SSTI 模板注入',
    'm.ssti.desc'       => '自研模板引擎直接 eval 表达式，从 7*7 探测一路打到系统命令执行。',
);
