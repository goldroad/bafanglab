<?php
/**
 * bafanglab - 学习辅助内容按需加载端点
 * 版权所有：八方网域-无涯
 *
 * 页面响应瘦身配套端点：「开始练习」页面不再内嵌辅助内容，
 * 前端点击 基础知识/提示/工具/源码/修复/案例 按钮时异步请求本端点获取对应 HTML 片段。
 *
 * 用法：piece.php?slug=<模块>&variant=<关卡>&piece=<片段>
 *   piece 取值：basic | hint | tools | bypass | fix | src | case
 *
 * 仅限本地靶场环境使用（与站点其余部分同等信任级别）。
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/levels.php';

header('Content-Type: text/html; charset=UTF-8');

$slug    = isset($_GET['slug'])    ? trim($_GET['slug'])    : '';
$variant = isset($_GET['variant']) ? trim($_GET['variant']) : '';
$piece   = isset($_GET['piece'])   ? trim($_GET['piece'])   : '';

// 防御性规范化：仅允许目录名/文件名字符，避免拼接出意外路径（本地环境常规防御）
foreach (array($slug, $variant, $piece) as $v) {
    if (preg_match('/[^A-Za-z0-9_\-]/', $v)) {
        exit('');
    }
}

echo level_piece_html($slug, $variant, $piece);
