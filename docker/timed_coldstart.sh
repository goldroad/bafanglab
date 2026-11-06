#!/bin/bash
# bafanglab 冷启动全流程计时（仅用于性能测量，不参与部署）
# 依次：清理 → 拉取基础镜像 → 构建 → 启动就绪 → 安装 → 全量测试
LOG=/tmp/timed.log
: > "$LOG"

now() { date +%s.%N; }
diff() { awk -v a="$1" -v b="$2" 'BEGIN{printf "%.1f", b-a}'; }
step() { printf '%-44s %8ss  %s\n' "$1" "$2" "$3" | tee -a "$LOG"; }

cd /opt/bafanglab || exit 1
T_ALL=$(now)
echo "===== 冷启动计时 $(date '+%F %T') =====" | tee -a "$LOG"

# ---------- 0 清理 ----------
T=$(now)
docker compose down --remove-orphans    > /tmp/c1.log 2>&1
docker rmi -f bafanglab:1.0.0 mariadb:10.11 php:7.4-apache >> /tmp/c1.log 2>&1
docker volume prune -f                  >> /tmp/c1.log 2>&1
docker builder prune -af                >> /tmp/c1.log 2>&1
if [ -d data/mysql ]; then mv data/mysql "/tmp/mysql_bak_$$"; fi
mkdir -p data/mysql
find uploads -type f -delete 2>/dev/null
find modules/sqli/files -type f -delete 2>/dev/null
step "[0] 清理镜像/容器/构建缓存/数据库" "$(diff "$T" "$(now)")"
echo "     剩余镜像数: $(docker images -q | wc -l)" | tee -a "$LOG"

# ---------- 1 拉取 mariadb ----------
T=$(now)
docker pull mariadb:10.11 > /tmp/pull_db.log 2>&1
RC=$?
step "[1] 拉取 mariadb:10.11 (327MB)" "$(diff "$T" "$(now)")" "rc=$RC"

# ---------- 2 拉取 php ----------
T=$(now)
docker pull php:7.4-apache > /tmp/pull_php.log 2>&1
RC=$?
step "[2] 拉取 php:7.4-apache (453MB)" "$(diff "$T" "$(now)")" "rc=$RC"

# ---------- 3 构建 ----------
T=$(now)
docker compose build web > /tmp/build.log 2>&1
RC=$?
step "[3] 构建 bafanglab 镜像" "$(diff "$T" "$(now)")" "rc=$RC"
docker images --format '     {{.Repository}}:{{.Tag}} {{.Size}}' | grep bafanglab | tee -a "$LOG"

# ---------- 4 启动并就绪 ----------
T=$(now)
docker compose up -d > /tmp/up.log 2>&1
CODE=000
for i in $(seq 1 90); do
  CODE=$(curl -s -o /dev/null -m 3 -w '%{http_code}' http://127.0.0.1/index.php)
  [ "$CODE" = "200" ] && break
  sleep 2
done
step "[4] 启动容器 → 首页 HTTP 200" "$(diff "$T" "$(now)")" "code=$CODE"

# ---------- 5 初始化 ----------
T=$(now)
INST=$(curl -s -X POST http://127.0.0.1/install.php | grep -o '安装完成[^<]*' | head -1)
step "[5] install.php 初始化环境" "$(diff "$T" "$(now)")" "$INST"
echo "     check.php 失败项: $(curl -s http://127.0.0.1/check.php | grep -c chk-fail)" | tee -a "$LOG"

# ---------- 6 全量测试 ----------
T=$(now)
python3 test_all.py http://127.0.0.1 > /tmp/test_timed.log 2>&1
step "[6] test_all.py 全量测试" "$(diff "$T" "$(now)")" ""
echo "     通过: $(grep -c '\[PASS\]' /tmp/test_timed.log) 项 / 失败: $(grep -c '\[FAIL\]' /tmp/test_timed.log) 项" | tee -a "$LOG"

TOTAL=$(diff "$T_ALL" "$(now)")
echo "================================================" | tee -a "$LOG"
step "总计（清理→测试完成）" "$TOTAL" ""
