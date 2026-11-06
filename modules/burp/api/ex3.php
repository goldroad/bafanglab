<?php
/**
 * bafanglab - 模块1 练习3 接口：VIP 状态开关
 * 版权所有：八方网域-无涯
 *
 * 靶场设计说明：
 * 本接口只返回 text/plain 纯文本，且只包含 vip 开关，不含任何敏感数据。
 * 通关凭证以 base64 形式预置在 response.php 页面 JS 中，学员把响应中的
 * vip=false 改成 vip=true 放行后，页面才解码显示通关凭证。
 * （呼应漏洞本质：权限开关交给前端判断 + 敏感数据预置在客户端，均非真正的安全边界）
 */
header('Content-Type: text/plain; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

echo "status=ok\n";
echo "vip=false\n";
