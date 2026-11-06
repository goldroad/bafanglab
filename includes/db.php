<?php
/**
 * bafanglab - 数据库连接
 * 版权所有：八方网域-无涯
 */
require_once dirname(__DIR__) . '/config.php';

// 注意：本文件为靶场教学代码，故意使用 mysqli 面向过程写法，便于构造SQL注入场景
// PHP 8.1+ 默认 mysqli 报错模式为「抛异常」，会导致连接失败时直接 500，
// 这里显式关闭，改由下方 !$conn 分支给出友好提示与 install.php 引导。
mysqli_report(MYSQLI_REPORT_OFF);
$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME, (int)DB_PORT);
if (!$conn) {
    die('<div style="padding:40px;font-family:sans-serif;">数据库连接失败：' . mysqli_connect_error()
        . '<br><br>请打开 <a href="' . BASE_URL . '/install.php">install.php</a> 检查数据库连接信息并提交安装。</div>');
}
mysqli_set_charset($conn, 'utf8');
