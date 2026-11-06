<?php
/**
 * bafanglab - 第4关：未授权访问的内部员工数据接口（演示用）
 * 版权所有：八方网域-无涯
 *
 * 说明：靶场故意不做任何鉴权，用于练习「未授权访问」。
 */
require_once dirname(dirname(dirname(__DIR__))) . '/includes/i18n.php';

header('Content-Type: application/json; charset=utf-8');

$users = array( // @vuln
    array(
        'uid'    => 1,
        'name'   => 'admin',
        'role'   => '系统管理员',
        'phone'  => '13800008888',
        'email'  => 'admin@bafanglab.local',
        'remark' => '超级管理员账号，内部备注：恭喜你完成本关挑战！'
    ),
    array(
        'uid'    => 2,
        'name'   => 'operator',
        'role'   => '运营专员',
        'phone'  => '13900001234',
        'email'  => 'operator@bafanglab.local',
        'remark' => '负责活动配置'
    ),
    array(
        'uid'    => 3,
        'name'   => 'finance',
        'role'   => '财务',
        'phone'  => '13700005678',
        'email'  => 'finance@bafanglab.local',
        'remark' => '负责对账与结算'
    )
);

echo json_encode(array( // @vuln
    'code' => 0,
    'msg'  => 'success',
    'data' => $users
), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
