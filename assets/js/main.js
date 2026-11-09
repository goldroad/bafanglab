/* bafanglab - 公共JS */
/* 版权所有：八方网域-无涯 | 版本：见 config.php 的 BAFANGLAB_VERSION */

var THEME_KEY = 'bafanglab-theme';

/* 前端文案：由 includes/header.php 按当前语言注入 window.LAB_I18N，缺省回退中文 */
function L(key, fallback) {
    var d = window.LAB_I18N || {};
    return typeof d[key] === 'string' && d[key] ? d[key] : fallback;
}

/* ---------------- 主题切换（默认浅色） ---------------- */
function applyTheme(theme) {
    if (theme !== 'dark') { theme = 'light'; }
    document.documentElement.setAttribute('data-theme', theme);
    var btn = document.getElementById('themeToggle');
    if (btn) {
        btn.innerHTML = theme === 'dark'
            ? '&#9788; ' + L('themeLight', '切换浅色主题')
            : '&#9789; ' + L('themeDark', '切换深色主题');
    }
}

function toggleTheme() {
    var next = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
    try { localStorage.setItem(THEME_KEY, next); } catch (e) {}
    applyTheme(next);
}

/* ---------------- 代码块一键复制 ---------------- */
// 把文本写入剪贴板：优先 Clipboard API，不可用时回退到隐藏 textarea + execCommand
function copyToClipboard(text, onOk, onFail) {
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(onOk, function () { legacyCopy(text, onOk, onFail); });
        return;
    }
    legacyCopy(text, onOk, onFail);
}

function legacyCopy(text, onOk, onFail) {
    var ta = document.createElement('textarea');
    ta.value = text;
    ta.setAttribute('readonly', 'readonly');
    ta.style.position = 'fixed';
    ta.style.top = '-9999px';
    document.body.appendChild(ta);
    ta.select();
    var ok = false;
    try { ok = document.execCommand('copy'); } catch (e) {}
    document.body.removeChild(ta);
    if (ok) { onOk(); } else { onFail(); }
}

// 为容器内每个 <pre> 代码块右上角追加「复制」按钮（幂等，重复调用不会重复添加）
function enhanceCodeCopy(root) {
    if (!root) { return; }
    var pres = root.querySelectorAll('pre');
    for (var i = 0; i < pres.length; i++) {
        var pre = pres[i];
        if (!pre.parentNode || pre.getAttribute('data-copy-ready')) { continue; }
        pre.setAttribute('data-copy-ready', '1');

        // 包裹容器：给按钮提供定位基准，避免按钮文字被算进代码文本
        var wrap = document.createElement('div');
        wrap.className = 'code-wrap';
        pre.parentNode.insertBefore(wrap, pre);
        wrap.appendChild(pre);

        var text = pre.textContent.replace(/^[\r\n]+/, '').replace(/[\s\u00a0]+$/, '');
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'code-copy-btn';
        btn.textContent = L('copy', '复制');
        (function (button, code) {
            button.addEventListener('click', function () {
                copyToClipboard(code, function () {
                    button.className = 'code-copy-btn copied';
                    button.textContent = L('copied', '已复制');
                    setTimeout(function () {
                        button.className = 'code-copy-btn';
                        button.textContent = L('copy', '复制');
                    }, 2500);
                }, function () {
                    button.textContent = L('copyFail', '复制失败');
                    setTimeout(function () { button.textContent = L('copy', '复制'); }, 1600);
                });
            });
        })(btn, text);
        wrap.appendChild(btn);
    }
}

/* ---------------- 弹窗 ---------------- */
function openModal(title, html, extraClass) {
    var modal = document.getElementById('hintModal');
    if (!modal) { return; }
    document.getElementById('modalTitle').innerHTML = title;
    var hintBody = document.getElementById('hintBody');
    hintBody.innerHTML = html;
    enhanceCodeCopy(hintBody);
    modal.className = extraClass ? 'modal ' + extraClass : 'modal';
    modal.style.display = 'block';
}

// 打开过关提示
function showHint(html) { openModal(L('modalHint', '过关提示'), html, ''); }

// 打开基础知识
function showBasic(html) { openModal(L('modalBasic', '基础知识'), html, 'modal-basic'); }

// 打开推荐工具
function showTools(html) { openModal(L('modalTools', '推荐工具'), html, 'modal-tools'); }

// 打开漏洞源码（异步从 piece.php 获取，页面响应不再内嵌源码面板）
function showSrc() {
    lvlFetchPiece('src', function (html) {
        openModal(L('modalSrc', '漏洞源码'), html, 'modal-src');
    });
}

// 打开修复方法（整合：修复方法 + 修复示例代码 + 绕过技巧，异步从 piece.php 获取）
function showFix() {
    lvlFetchPiece('fix', function (html) {
        openModal(L('modalFix', '修复方法'), html, 'modal-src');
    });
}

// 关闭弹窗
function closeHint() {
    var m = document.getElementById('hintModal');
    if (m) { m.style.display = 'none'; }
}

/* ---------------- 学习辅助页内直显（第 2 关起） ---------------- */
/* level_tools() 直显模式：
   - 「开始练习」为默认视图，下方直接展示关卡原有练习内容；
   - 其余按钮（基础知识/提示/工具/源码/修复/真实案例）点击后隐藏练习区，
     在按钮栏下方的固定区域直显对应内容，不弹窗、不跳页。 */

// 初始化：把 action-bar 之后的同级节点包进练习容器，并在其后插入直显容器
function lvlInlineSetup() {
    var bars = document.querySelectorAll('.action-bar[data-lvl-inline]');
    for (var i = 0; i < bars.length; i++) {
        var bar = bars[i];
        var parent = bar.parentNode;
        if (!parent || bar.getAttribute('data-lvl-ready')) { continue; }

        var aux = document.createElement('div');
        aux.className = 'lvl-aux';
        aux.style.display = 'none';

        var practice = document.createElement('div');
        practice.className = 'lvl-practice';

        // 依次插入：bar → aux → practice，再把 bar 之后的原节点全部移入 practice
        var following = [];
        var node = bar.nextSibling;
        while (node) { following.push(node); node = node.nextSibling; }
        parent.insertBefore(aux, bar.nextSibling);
        parent.insertBefore(practice, aux.nextSibling);
        for (var j = 0; j < following.length; j++) { practice.appendChild(following[j]); }

        bar.setAttribute('data-lvl-ready', '1');
    }
}

/* ---------------- 学习辅助内容按需加载 ---------------- */
/* 页面响应瘦身：基础/提示/工具/源码/修复/案例内容不再随页面输出，
   点击按钮时从 piece.php 异步获取，首取后缓存在内存中，再次点击零请求。 */

var LVL_PIECE_CACHE = {};

// 从当前 URL 推导模块 slug 与关卡 variant（/modules/<slug>/<variant>.php）
function lvlSlugVariant() {
    var path = window.location.pathname.replace(/\/+$/, '');
    var marker = '/modules/';
    var idx = path.indexOf(marker);
    if (idx === -1) { return null; }
    var rest = path.substring(idx + marker.length).split('/');
    if (rest.length !== 2) { return null; }
    return { slug: rest[0], variant: rest[1].replace(/\.php$/, '') };
}

// 异步获取学习辅助内容片段；失败或无内容返回「暂无内容」占位
function lvlFetchPiece(piece, cb) {
    var empty = '<p class="lvl-empty">' + L('lvlEmpty', '暂无内容') + '</p>';
    if (LVL_PIECE_CACHE[piece]) { cb(LVL_PIECE_CACHE[piece]); return; }
    var sv = lvlSlugVariant();
    if (!sv) { cb(empty); return; }
    var base = window.location.pathname.split('/modules/')[0];
    var url = base + '/piece.php'
        + '?slug=' + encodeURIComponent(sv.slug)
        + '&variant=' + encodeURIComponent(sv.variant)
        + '&piece=' + encodeURIComponent(piece);
    var xhr = new XMLHttpRequest();
    xhr.open('GET', url, true);
    xhr.onreadystatechange = function () {
        if (xhr.readyState !== 4) { return; }
        var html = (xhr.status === 200 && xhr.responseText && xhr.responseText.trim())
            ? xhr.responseText : empty;
        LVL_PIECE_CACHE[piece] = html;
        cb(html);
    };
    xhr.send();
}

// 按钮点击：切换直显视图（事件委托），辅助内容按需拉取
document.addEventListener('click', function (e) {
    var btn = e.target.closest ? e.target.closest('.action-bar[data-lvl-inline] [data-lvl-view]') : null;
    if (!btn) { return; }
    var bar = btn.parentNode;
    var aux = bar.nextSibling;
    if (!aux || aux.className !== 'lvl-aux') { return; }
    var practice = aux.nextSibling;
    if (!practice || practice.className !== 'lvl-practice') { return; }

    // 按钮激活态
    var all = bar.querySelectorAll('[data-lvl-view]');
    for (var i = 0; i < all.length; i++) { all[i].classList.remove('active'); }
    btn.classList.add('active');

    var view = btn.getAttribute('data-lvl-view');
    if (view === 'practice') {
        aux.style.display = 'none';
        practice.style.display = '';
        return;
    }

    var titles = {
        basic: 'modalBasic', hint: 'modalHint', tools: 'modalTools',
        src: 'modalSrc', fix: 'modalFix', case: 'modalCase'
    };

    // 组装直显区域：标题 + 内容（标题走 textContent 防注入）
    aux.innerHTML = '';
    var head = document.createElement('div');
    head.className = 'lvl-aux-head';
    var title = document.createElement('span');
    title.className = 'lvl-aux-title';
    title.textContent = L(titles[view] || '', btn.textContent);
    head.appendChild(title);
    var body = document.createElement('div');
    body.className = 'hint-body';
    body.innerHTML = '<p class="lvl-empty">' + L('lvlLoading', '加载中…') + '</p>';
    aux.appendChild(head);
    aux.appendChild(body);

    practice.style.display = 'none';
    aux.style.display = 'block';

    // 按需拉取内容：先展示「加载中」，返回后填充（若期间用户已切换视图则丢弃）
    lvlFetchPiece(view, function (html) {
        if (!btn.classList.contains('active')) { return; }
        body.innerHTML = html;
        enhanceCodeCopy(body);
    });
});

/* ---------------- Burp 练习：纯文本接口响应解析与填充 ---------------- */
// 解析接口返回的 text/plain 响应（每行一组 key=value）
function kvParse(text) {
    var obj = {};
    var lines = String(text == null ? '' : text).split(/\r?\n/);
    for (var i = 0; i < lines.length; i++) {
        var pos = lines[i].indexOf('=');
        if (pos > 0) { obj[lines[i].substring(0, pos)] = lines[i].substring(pos + 1); }
    }
    return obj;
}

// HTML 转义，防止接口返回内容中的特殊字符被当作标签渲染
function kvEscape(s) {
    return String(s == null ? '' : s)
        .replace(/&/g, '&amp;').replace(/</g, '&lt;')
        .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

// 把接口响应填充到指定结果区域：status 命中成功值用正常样式，否则用告警样式
function showApiResult(elId, text, okStatuses) {
    var data = kvParse(text);
    var ok = false;
    var list = okStatuses || [];
    for (var i = 0; i < list.length; i++) {
        if (data.status === list[i]) { ok = true; }
    }
    var el = document.getElementById(elId);
    if (!el) { return; }
    el.className = 'result' + (ok ? '' : ' warn');
    var html = '';
    for (var k in data) {
        if (!Object.prototype.hasOwnProperty.call(data, k) || k === 'status') { continue; }
        html += '<div><code>' + kvEscape(k) + '</code>　' + kvEscape(data[k]) + '</div>';
    }
    el.innerHTML = html;
    el.style.display = 'block';
}

/* ---------------- 输入框点击（获焦）清空 ---------------- */
// 全局统一交互：input / textarea 首次获焦时清空当前值，原值记录在 data-fc-value，
// 未输入就失焦时恢复原值。点击后输入框应为真正的空白（不显示灰色占位文字）。
// 以下情况跳过，避免破坏原有功能：
//   - 搜索框（type=search）、隐藏域（type=hidden）、密码框（type=password）
//   - 按钮/复选/单选/文件/范围/颜色等其他非文本输入
//   - 只读、禁用、带 data-no-clear 标记的元素
//   - 已被用户手动输入过（data-user-edited=1）的元素，避免误删用户内容

var FC_TYPE_SKIP = {
    search: 1, hidden: 1, password: 1, submit: 1, button: 1, reset: 1,
    checkbox: 1, radio: 1, file: 1, range: 1, color: 1, image: 1
};

function isFocusClearTarget(el) {
    if (!el || (el.tagName !== 'INPUT' && el.tagName !== 'TEXTAREA')) { return false; }
    if (el.disabled || el.readOnly) { return false; }
    if (el.tagName === 'INPUT' && FC_TYPE_SKIP[String(el.type || 'text').toLowerCase()]) { return false; }
    if (el.hasAttribute('data-no-clear')) { return false; }
    return true;
}

document.addEventListener('focus', function (e) {
    var el = e.target;
    if (!isFocusClearTarget(el)) { return; }
    if (el.getAttribute('data-user-edited') === '1') { return; }

    // 首次获焦：记录原值，并临时移除 placeholder，使输入框呈现真正的空白
    if (el.getAttribute('data-fc-ready') !== '1') {
        el.setAttribute('data-fc-ready', '1');
        el.setAttribute('data-fc-value', el.value);
        if (el.hasAttribute('placeholder')) {
            el.setAttribute('data-fc-ph', el.getAttribute('placeholder'));
            el.removeAttribute('placeholder');
        }
    }
    if (el.value !== '') { el.value = ''; }
}, true);

// 记录用户真实输入：之后不再自动清空，避免覆盖用户内容
document.addEventListener('input', function (e) {
    var el = e.target;
    if (!el || (el.tagName !== 'INPUT' && el.tagName !== 'TEXTAREA')) { return; }
    el.setAttribute('data-user-edited', '1');
}, true);

// 失焦：未输入内容时恢复首次获焦前的原值与 placeholder
document.addEventListener('blur', function (e) {
    var el = e.target;
    if (!isFocusClearTarget(el)) { return; }
    if (el.getAttribute('data-user-edited') === '1') {
        // 用户已编辑：恢复 placeholder（不恢复值，保留用户输入）
        var ph = el.getAttribute('data-fc-ph');
        if (ph !== null && !el.hasAttribute('placeholder')) {
            el.setAttribute('placeholder', ph);
        }
        return;
    }
    var orig = el.getAttribute('data-fc-value');
    if (el.value === '' && orig !== null) {
        el.value = orig;
    }
    // 恢复 placeholder
    var savedPh = el.getAttribute('data-fc-ph');
    if (savedPh !== null && !el.hasAttribute('placeholder')) {
        el.setAttribute('placeholder', savedPh);
    }
}, true);

/* ---------------- 初始化 ---------------- */
document.addEventListener('DOMContentLoaded', function () {
    var saved = null;
    try { saved = localStorage.getItem(THEME_KEY); } catch (e) {}
    applyTheme(saved === 'dark' ? 'dark' : 'light');

    // 学习辅助页内直显：包装练习区与直显区（第 2 关起）
    lvlInlineSetup();
});

// 点击遮罩关闭
window.onclick = function (e) {
    var m = document.getElementById('hintModal');
    if (m && e.target === m) { m.style.display = 'none'; }
};

// ESC 关闭弹窗
document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') { closeHint(); }
});

/* ---------------- 「显示SQL语句」开关（SQL 注入模块） ---------------- */
// 按钮 data-sql-toggle 与同父元素内 data-sql-panel 配对，默认隐藏，点击切换
document.addEventListener('click', function (e) {
    var btn = e.target.closest ? e.target.closest('[data-sql-toggle]') : null;
    if (!btn) { return; }
    var panel = btn.parentElement.querySelector('[data-sql-panel]');
    if (!panel) { return; }
    var show = panel.style.display === 'none';
    panel.style.display = show ? 'block' : 'none';
    btn.textContent = show ? btn.getAttribute('data-hide') : btn.getAttribute('data-show');
});

/* ---------------- 「切换关卡」下拉菜单 ---------------- */
// 点击「切换关卡」按钮展开/收起全部关卡列表；点击外部或按 ESC 关闭
document.addEventListener('click', function (e) {
    var btn = e.target.closest ? e.target.closest('[data-level-switch]') : null;
    var menu = null;
    if (btn) {
        menu = btn.parentElement.querySelector('.level-switch-menu');
    } else {
        // 点击在菜单内部则不处理
        var inside = e.target.closest ? e.target.closest('.level-switch-menu') : null;
        if (inside) { return; }
        // 点击在其它「切换关卡」容器内则不处理
        var otherSwitch = e.target.closest ? e.target.closest('.level-switch') : null;
        if (otherSwitch) { return; }
    }
    // 关闭所有已展开的切换关卡菜单
    var all = document.querySelectorAll('.level-switch-menu');
    for (var i = 0; i < all.length; i++) { all[i].style.display = 'none'; }
    // 若点击的是按钮，则展开对应菜单（display 用 grid 以匹配 4×4 网格布局）
    if (btn && menu) { menu.style.display = 'grid'; }
});

// ESC 关闭切换关卡菜单
document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
        var all = document.querySelectorAll('.level-switch-menu');
        for (var i = 0; i < all.length; i++) { all[i].style.display = 'none'; }
    }
});
