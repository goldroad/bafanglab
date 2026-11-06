#!/bin/bash
# bafanglab 容器入口脚本
# 版权所有：八方网域-无涯
set -e

DOCROOT=/opt/bafanglab
DB_HOST_ENV="${DB_HOST:-db}"
DB_USER_ENV="${DB_USER:-root}"
DB_PASS_ENV="${DB_PASS:-root}"

# 1) 仅运行时改写 config.php 的数据库主机（宿主机部署副本与容器共享同一文件）
sed -ri "s/define\('DB_HOST',[[:space:]]*'[^']*'\);/define('DB_HOST', '${DB_HOST_ENV}');/" "$DOCROOT/config.php"

# 2) 挂载目录权限
#    bind mount 的文件属主是宿主机用户（通常 root），容器内 www-data 无写权限，
#    install.php 重置环境时会 file_put_contents 写回下列位置，必须放开：
#      uploads/                文件上传关卡
#      modules/sqli/files/     SQL 注入 INTO OUTFILE / LOAD_FILE
#      backup/                 目录扫描关卡的 db_backup.sql、www.zip 与假 .git 仓库
#      admin_area/             隐藏后台首页
#      modules/fileinc/pages/  文件包含关卡的被包含页面
#      modules/fileread/files/ 任意文件读取关卡的示例文件
#      modules/ssti/           SSTI 关卡的 secret.txt
#    用 a+rwX 而非 777：目录保留 x 位、普通文件不误加执行位
for _d in uploads backup admin_area modules/sqli/files modules/unserialize \
          modules/fileinc/pages modules/fileread/files modules/ssti; do
    [ -d "$DOCROOT/$_d" ] && chmod -R a+rwX "$DOCROOT/$_d" 2>/dev/null || true
done
chmod 666 "$DOCROOT/config.php" "$DOCROOT/robots.txt" 2>/dev/null || true

# 3) 兜底等待数据库就绪（compose healthcheck 已保证，此处再确认一次）
echo "[bafanglab] waiting for MySQL at ${DB_HOST_ENV}:3306 ..."
for i in $(seq 1 60); do
    if php -r "exit(@mysqli_connect('${DB_HOST_ENV}','${DB_USER_ENV}','${DB_PASS_ENV}') ? 0 : 1);" 2>/dev/null; then
        echo "[bafanglab] MySQL is ready."
        break
    fi
    sleep 2
done

# 4) 前台启动 Apache
exec apache2-foreground
