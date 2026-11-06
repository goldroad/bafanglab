<?php
/**
 * bafanglab - 模块1 练习4 接口：一次性 token 转账
 * 版权所有：八方网域-无涯
 *
 * 靶场设计说明：
 * 接口返回 text/plain 纯文本，便于在 Burp Repeater 中原样重放同一个请求，
 * 观察第二次响应变成"检测到重放攻击"。
 */
session_start();
require_once dirname(dirname(dirname(__DIR__))) . '/includes/i18n.php';
header('Content-Type: text/plain; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

if (!isset($_SESSION['used_tokens'])) { $_SESSION['used_tokens'] = array(); }
if (!isset($_SESSION['replay_token'])) { $_SESSION['replay_token'] = md5(uniqid('', true)); }

$token  = isset($_POST['token']) ? $_POST['token'] : '';
$amount = isset($_POST['amount']) ? $_POST['amount'] : '';

if ($token === '') {
    echo "status=fail\n";
    echo "msg=" . '缺少一次性 token，请求被拒绝。' . "\n";
    exit;
}

// 靶场故意设计：仅依靠 session 中"已使用 token 列表"判断，无签名、无幂等键
if (in_array($token, $_SESSION['used_tokens'], true)) {
    echo "status=replay\n";
    echo "msg=" . '检测到重放攻击！该 token 已使用过，服务器拒绝了本次转账。' . "\n";
} else {
    $_SESSION['used_tokens'][] = $token;
    $_SESSION['replay_token'] = md5(uniqid('', true));
    echo "status=ok\n";
    echo "token=" . $token . "\n";
    echo "amount=" . $amount . "\n";
    echo "msg=" . '转账成功！' . "\n";
    echo "nextToken=" . $_SESSION['replay_token'] . "\n";
}
