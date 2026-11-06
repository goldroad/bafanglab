<?php
/**
 * bafanglab - 模块1 练习1 接口：GET 参数越权
 * 版权所有：八方网域-无涯
 *
 * 靶场设计说明：
 * 本接口只返回 text/plain 纯文本（每行 key=value），不输出任何 HTML，
 * 便于在 Burp Suite 中直接拦截、修改请求参数与响应内容后观察结果。
 */
require_once dirname(dirname(dirname(__DIR__))) . '/includes/i18n.php';

header('Content-Type: text/plain; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

// 靶场故意设计：直接信任客户端提交的 role 参数，未与会话/数据库中的真实角色比对
$role = isset($_GET['role']) ? $_GET['role'] : 'guest';

if ($role === 'admin') {
    echo "status=ok\n";
    echo "role=admin\n";
    echo "msg=" . '管理员身份已确认，机密内容：恭喜你完成本关挑战！' . "\n";
} else {
    echo "status=fail\n";
    echo "role=" . $role . "\n";
    echo "msg=" . '普通用户无权查看机密内容' . "\n";
}
