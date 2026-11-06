#!/bin/bash
# bafanglab - 一键部署脚本
# 版权所有：八方网域-无涯
#
# 用法：
#   bash deploy.sh              首次部署：构建镜像并启动（需能拉取基础镜像）
#   bash deploy.sh --no-build   已用 import-images.sh 导入离线镜像：直接启动，不构建、不联网
set -e
cd "$(dirname "$0")"

BUILD_FLAG="--build"
if [ "$1" = "--no-build" ]; then
    BUILD_FLAG=""
    echo "模式：离线启动（不构建镜像，直接使用本地已有镜像）"
fi

echo "[1/4] 准备数据目录与运行时权限..."
mkdir -p data/mysql
chmod -R 777 uploads modules/sqli/files 2>/dev/null || true
chmod 666 config.php 2>/dev/null || true

echo "[2/4] 启动容器..."
docker compose up -d $BUILD_FLAG

echo "[3/4] 等待服务就绪..."
T0=$(date +%s)
CODE=000
for i in $(seq 1 60); do
    CODE=$(curl -s -o /dev/null -m 2 -w '%{http_code}' http://127.0.0.1/index.php 2>/dev/null || echo 000)
    if [ "$CODE" = "200" ]; then break; fi
    sleep 1
done
T1=$(date +%s)
if [ "$CODE" = "200" ]; then
    echo "  就绪（HTTP 200，耗时 $((T1 - T0)) 秒）"
else
    echo "  警告：60 秒内首页未返回 200（当前 code=$CODE），请查看日志：docker compose logs --tail=50 web"
fi
docker compose ps

echo "[4/4] 完成。"
IP=$(hostname -I 2>/dev/null | awk '{print $1}')
echo "  靶场地址：http://${IP}/"
echo "  安装页面：http://${IP}/install.php"
echo "  环境自检：http://${IP}/check.php"
echo "  运行测试：python3 test_all.py http://127.0.0.1"
echo
echo "首次部署后请先初始化环境：curl -X POST http://127.0.0.1/install.php"
echo "分发给学员的离线方案：bash export-images.sh（导出）→ bash import-images.sh（学员导入）"
