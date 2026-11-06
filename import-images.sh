#!/bin/bash
# bafanglab - 导入离线镜像包
# 用途：学员侧使用，导入作者导出的镜像包，全程无需联网、无需构建。
#
# 用法：bash import-images.sh [镜像包文件名]
# 默认读取：bafanglab-images.tar.gz（.tar / .tar.gz 均可，docker load 自动识别压缩）
set -e
cd "$(dirname "$0")"

FILE="${1:-bafanglab-images.tar.gz}"
if [ ! -f "$FILE" ]; then
    echo "找不到镜像包：$FILE"
    echo "用法：bash import-images.sh <镜像包路径>"
    exit 1
fi

echo "[1/2] 导入镜像包 $FILE（$(du -h "$FILE" | cut -f1)）..."
docker load -i "$FILE"

echo "[2/2] 当前可用镜像："
docker images --format '  {{.Repository}}:{{.Tag}}  {{.Size}}' | grep -E 'bafanglab|mariadb' || true

echo
echo "导入完成。下一步："
echo "  bash deploy.sh --no-build                    # 启动容器（不构建、不联网）"
echo "  curl -X POST http://127.0.0.1/install.php    # 初始化靶场"
echo "  浏览器访问 http://<服务器IP>/"
