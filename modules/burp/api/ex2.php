<?php
/**
 * bafanglab - 模块1 练习2 接口：商品下单（修改POST请求 / 金额篡改）
 * 版权所有：八方网域-无涯
 *
 * 靶场设计说明：
 * 接口返回 text/plain 纯文本，拦截下单请求把 price=9999 改成 price=0.01 即可通关。
 */
require_once dirname(dirname(dirname(__DIR__))) . '/includes/i18n.php';

header('Content-Type: text/plain; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

$price = isset($_POST['price']) ? (float)$_POST['price'] : 9999;

// 靶场故意设计：价格直接取客户端提交的值，未从数据库按商品 ID 查询
if ($price <= 0.01) {
    echo "status=ok\n";
    echo "price=" . $price . "\n";
    echo "msg=" . '恭喜！你以 ¥' . $price . ' 买到了价值 ¥9999 的《Web安全秘籍》！恭喜你完成本关挑战！' . "\n";
} else {
    echo "status=fail\n";
    echo "price=" . $price . "\n";
    echo "msg=" . '支付 ¥' . $price . ' 成功……但什么都还没学会（试试把价格改成 0.01）。' . "\n";
}
