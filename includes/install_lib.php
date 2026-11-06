<?php
/**
 * bafanglab - 安装辅助函数
 * 版权所有：八方网域-无涯
 *
 * 抽成独立文件的目的：安装器的「回写 config.php」逻辑可以脱离 HTTP 与数据库单独验证，
 * 避免再次出现「密码含特殊字符被静默写坏、页面却显示安装成功」这类缺陷。
 */

require_once dirname(__DIR__) . '/config.php';

/**
 * 把数据库配置写回 config.php（按 define 行精确替换）
 *
 * 关键点：必须用 preg_replace_callback，回调返回值按字面量处理。
 * 若直接把密码拼进 preg_replace 的替换串，其中的 $1 / \1 会被当成反向引用而被吃掉
 * （例如密码 pa$1ss 会被静默写成 pass），属于难以察觉的缺陷。
 *
 * @param array       $f    配置数组：host / port / user / pass / name
 * @param string|null $path 目标文件路径，默认 BASE_DIR/config.php（便于单元测试注入临时文件）
 * @return bool 写入是否成功
 */
function write_config_db($f, $path = null)
{
    if ($path === null) {
        $path = BASE_DIR . DIRECTORY_SEPARATOR . 'config.php';
    }
    if (!is_file($path) || !is_writable($path)) {
        return false;
    }
    $cfg = file_get_contents($path);
    if ($cfg === false) {
        return false;
    }

    $map = array(
        'DB_HOST' => $f['host'],
        'DB_PORT' => $f['port'],
        'DB_USER' => $f['user'],
        'DB_PASS' => $f['pass'],
        'DB_NAME' => $f['name'],
    );

    foreach ($map as $k => $v) {
        // 规范化为 PHP 单引号字符串内的安全字面量：
        // 先去换行（避免撑断 define 行），再转义 反斜杠 与 单引号
        $lit = str_replace(array("\r", "\n"), '', (string)$v);
        $lit = str_replace(array('\\', "'"), array('\\\\', "\\'"), $lit);
        $cfg = preg_replace_callback(
            "/define\('" . $k . "',\s*'[^']*'\);/",
            function () use ($k, $lit) {
                return "define('" . $k . "', '" . $lit . "');";
            },
            $cfg
        );
    }

    return file_put_contents($path, $cfg) !== false;
}
