#!/bin/bash
# bafanglab - 导出离线镜像包
# 用途：在已构建完成的机器上打包镜像，分发给学员后无需联网、无需构建，docker load 即可运行。
#
# 用法：bash export-images.sh [输出文件名]
# 默认输出：bafanglab-images.tar.gz（gzip 压缩，约 300~400MB；docker load 可直接读取压缩包）
set -e
cd "$(dirname "$0")"

OUT="${1:-bafanglab-images.tar.gz}"
IMAGES="bafanglab:1.0.0 mariadb:10.11"

echo "[1/3] 检查镜像是否存在..."
for img in $IMAGES; do
    if ! docker image inspect "$img" >/dev/null 2>&1; then
        echo "  缺少镜像：$img"
        echo "  请先执行：bash deploy.sh"
        exit 1
    fi
    printf "  %-24s %s\n" "$img" "$(docker image inspect "$img" --format '{{.Size}}' | awk '{printf "%.0fMB", $1/1024/1024}')"
done

echo "[2/3] 导出到 $OUT ..."
if [ "${OUT%.gz}" = "$OUT" ]; then
    docker save -o "$OUT" $IMAGES           # 文件名不带 .gz：保持未压缩
else
    docker save $IMAGES | gzip -c > "$OUT"  # 压缩后体积约为未压缩的 1/3
fi

echo "[3/3] 校验可读性..."
docker load -i "$OUT" >/dev/null 2>&1 && echo "  镜像包可正常读取"
SIZE=$(du -h "$OUT" | cut -f1)

echo
echo "导出完成：$OUT（$SIZE）"
echo
echo "分发给学员时，连同项目目录一起打包，学员侧执行："
echo "  bash import-images.sh $OUT                   # 导入镜像（离线，无需构建）"
echo "  bash deploy.sh --no-build                    # 直接启动"
echo "  curl -X POST http://127.0.0.1/install.php    # 初始化靶场"
