<?php
/**
 * bafanglab - 关卡子页选项卡（各模块专属）
 * 版权所有：八方网域-无涯
 *
 * 公共头部 header.php 不应该承载模块专属逻辑，
 * 三个多子关卡（sqli、burp、rce）的选项卡函数集中在此。
 * 使用 if (!function_exists(...)) 保护，允许 header.php 先加载再加载本文件。
 */

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/i18n.php';

/**
 * 多子关模块的页签数据（唯一数据源）
 * 关卡数徽标（includes/modules.php 的 lab_levels）直接由这里推导，
 * 新增/删除子关只需改这一处，不再维护第二份手写数字。
 * 纯知识页（protocol / encode / dnslog）不参与计数。
 * @return array slug => array(i18n 键 => 相对地址)
 */
if (!function_exists('lab_tab_groups')) {
    function lab_tab_groups()
    {
        return array(
            'burp' => array(
                'tab.burp_1' => BASE_URL . '/modules/burp/index.php',
                'tab.burp_2' => BASE_URL . '/modules/burp/price.php',
                'tab.burp_3' => BASE_URL . '/modules/burp/response.php',
                'tab.burp_4' => BASE_URL . '/modules/burp/replay.php',
            ),
            'sqli' => array(
                'tab.sqli_get'     => BASE_URL . '/modules/sqli/get.php',
                'tab.sqli_post'    => BASE_URL . '/modules/sqli/post.php',
                'tab.sqli_error'   => BASE_URL . '/modules/sqli/error.php',
                'tab.sqli_blind'   => BASE_URL . '/modules/sqli/blind.php',
                'tab.sqli_time'    => BASE_URL . '/modules/sqli/time.php',
                'tab.sqli_outfile' => BASE_URL . '/modules/sqli/outfile.php',
            ),
            'xss' => array(
                'tab.xss_reflect'  => BASE_URL . '/modules/xss/reflect.php',
                'tab.xss_stored'   => BASE_URL . '/modules/xss/stored.php',
                'tab.xss_dom'      => BASE_URL . '/modules/xss/dom.php',
                'tab.xss_upload'   => BASE_URL . '/modules/xss/upload.php',
                'tab.xss_platform' => BASE_URL . '/modules/xss/platform.php',
            ),
            'brute' => array(
                'tab.brute_nocap' => BASE_URL . '/modules/brute/index.php',
                'tab.brute_cap'   => BASE_URL . '/modules/brute/captcha.php',
            ),
            'jsleak' => array(
                'tab.jsleak_js'     => BASE_URL . '/modules/jsleak/index.php',
                'tab.jsleak_unauth' => BASE_URL . '/modules/jsleak/unauth.php',
            ),
            'rce' => array(
                'tab.rce_cmd'  => BASE_URL . '/modules/rce/index.php',
                'tab.rce_code' => BASE_URL . '/modules/rce/code.php',
            ),
        );
    }
}

/**
 * 取某个模块的页签数组，未注册返回空数组
 * @param string $slug
 * @return array
 */
if (!function_exists('lab_tab_group')) {
    function lab_tab_group($slug)
    {
        $groups = lab_tab_groups();
        return isset($groups[$slug]) ? $groups[$slug] : array();
    }
}

/**
 * SQL 注入模块的 6 个子关选项卡（统一入口，避免各文件重复维护）
 * @param string $active 当前激活选项卡（i18n 键 / 中文标签 / 当前语言标签均可）
 */
if (!function_exists('sqli_tabs')) {
    function sqli_tabs($active)
    {
        level_tabs_links(lab_tab_group('sqli'), $active);
    }
}

/**
 * 模块1 Burp 抓包基本功的 4 个练习选项卡
 * 「网络协议入门 / 编码基础 / OOB 和 DNSLog」属于基础知识，统一由 burp_basic_tabs() 管理，
 * 不再混在练习选项卡中重复展示。
 * @param string $active 当前激活选项卡
 */
if (!function_exists('burp_tabs')) {
    function burp_tabs($active)
    {
        level_tabs_links(lab_tab_group('burp'), $active);
    }
}

/**
 * 模块1 Burp 知识页面的子页面选项卡（网络协议入门 / 编码基础 / OOB 和 DNSLog）
 * @param string $active 当前激活选项卡
 */
if (!function_exists('burp_basic_tabs')) {
    function burp_basic_tabs($active)
    {
        level_tabs_links(array(
            'tab.burp_intro'  => BASE_URL . '/modules/burp/protocol.php',
            'tab.burp_encode' => BASE_URL . '/modules/burp/encode.php',
            'tab.burp_dnslog' => BASE_URL . '/modules/burp/dnslog.php',
        ), $active);
    }
}

/**
 * 模块14 RCE 的子关卡选项卡（拆分为独立页面）
 * @param string $active 当前激活选项卡
 */
if (!function_exists('rce_tabs')) {
    function rce_tabs($active)
    {
        level_tabs_links(lab_tab_group('rce'), $active);
    }
}

/**
 * 模块6 XSS 的子关卡选项卡
 * @param string $active 当前激活选项卡
 */
if (!function_exists('xss_tabs')) {
    function xss_tabs($active)
    {
        level_tabs_links(lab_tab_group('xss'), $active);
    }
}

/**
 * 模块5 密码暴力猜解的子关卡选项卡
 * @param string $active 当前激活选项卡
 */
if (!function_exists('brute_tabs')) {
    function brute_tabs($active)
    {
        level_tabs_links(lab_tab_group('brute'), $active);
    }
}

/**
 * 模块4 JS 敏感信息泄露的子关卡选项卡
 * @param string $active 当前激活选项卡
 */
if (!function_exists('jsleak_tabs')) {
    function jsleak_tabs($active)
    {
        level_tabs_links(lab_tab_group('jsleak'), $active);
    }
}
