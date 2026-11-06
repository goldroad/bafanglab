#!/bin/bash
# bafanglab 离线镜像包方案计时（作者侧导出 → 学员侧导入 → 启动 → 测试）
LOG=/tmp/timed_offline.log
: > "$LOG"

now() { date +%s.%N; }
diff() { awk -v a="$1" -v b="$2" 'BEGIN{printf "%.1f", b-a}'; }
step() { printf '%-42s %8ss  %s\n' "$1" "$2" "$3" | tee -a "$LOG"; }

cd /opt/bafanglab || exit 1
T_ALL=$(now)
echo "===== 离线镜像包计时 $(date '+%F %T') =====" | tee -a "$LOG"

# ---------- A 作者侧：导出（一次性，只需做一次） ----------
T=$(now)
rm -f bafanglab-images.tar.gz
bash export-images.sh > /tmp/exp.log 2>&1
step "[A] 作者侧 export-images.sh" "$(diff "$T" "$(now)")" "$(du -h bafanglab-images.tar.gz 2>/dev/null | cut -f1)"

# ---------- B 学员侧：全新机器（清空镜像/容器/数据库） ----------
T=$(now)
docker compose down --remove-orphans > /dev/null 2>&1
docker rmi -f bafanglab:1.0.0 mariadb:10.11 php:7.4-apache > /dev/null 2>&1
docker builder prune -af > /dev/null 2>&1
if [ -d data/mysql ]; then mv data/mysql "/tmp/mysql_bak_off_$$"; fi
mkdir -p data/mysql
find uploads -type f -delete 2>/dev/null
find modules/sqli/files -type f -delete 2>/dev/null
step "[B] 模拟全新机器（清空）" "$(diff "$T" "$(now)")" "镜像数=$(docker images -q | wc -l)"

T_LEARN=$(now)

# ---------- C 导入 ----------
T=$(now)
bash import-images.sh bafanglab-images.tar.gz > /tmp/imp.log 2>&1
RC=$?
step "[C] import-images.sh 导入离线包" "$(diff "$T" "$(now)")" "rc=$RC"

# ---------- D 启动 ----------
T=$(now)
bash deploy.sh --no-build > /tmp/dep.log 2>&1
CODE=000
for i in $(seq 1 90); do
  CODE=$(curl -s -o /dev/null -m 3 -w '%{http_code}' http://127.0.0.1/index.php)
  [ "$CODE" = "200" ] && break
  sleep 2
done
step "[D] deploy.sh --no-build 启动→200" "$(diff "$T" "$(now)")" "code=$CODE"

# ---------- E 初始化 ----------
T=$(now)
INST=$(curl -s -X POST http://127.0.0.1/install.php | grep -o '安装完成[^<]*' | head -1)
step "[E] install.php 初始化" "$(diff "$T" "$(now)")" "$INST"

# ---------- F 测试 ----------
T=$(now)
python3 test_all.py http://127.0.0.1 > /tmp/test_off.log 2>&1
step "[F] test_all.py 全量测试" "$(diff "$T" "$(now)")" \
     "PASS=$(grep -c '\[PASS\]' /tmp/test_off.log) FAIL=$(grep -c '\[FAIL\]' /tmp/test_off.log)"

echo "================================================" | tee -a "$LOG"
step "学员侧总计（导入→测试通过）" "$(diff "$T_LEARN" "$(now)")" ""
echo "     镜像权限警告数: $(docker logs bafanglab-web 2>&1 | grep -c 'Permission denied')" | tee -a "$LOG"
