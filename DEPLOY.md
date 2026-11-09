# bafanglab - 部署指南

> 版权所有：八方网域-无涯
>
> 本文档覆盖全部部署与验证内容；项目介绍、漏洞模块清单、目录结构见 `README.md`。

## 一、Agent 自动部署（适合不熟悉命令行的用户）

如果不熟悉命令行，可以把下面这段提示词直接发给具备本机文件与命令执行能力的 AI Agent（如 WorkBuddy、Claude Code 等），由它代为完成「放置代码 → 初始化环境 → 启动服务 → 运行 109 项测试 → 汇报结果」的全流程。

### 使用方法

1. 把提示词模板中的占位符替换为你的真实信息：
   - Windows：`xxx\phpstudy_pro\WWW` 换成你的 phpStudy 网站根目录，`xxx` 换成靶场代码所在目录；
   - Linux Docker：`x.x.x.x` 换成虚拟机 IP，`root/123456` 换成 SSH 账号密码，`xxx` 换成代码在虚拟机上的路径。
2. 把整段提示词发给 Agent，等待其执行完毕。
3. 验收标准：Agent 汇报 `test_all.py` **109 项全部通过**；之后浏览器打开 `install.php` 重置一次环境即可开始练习。

### Windows（phpStudy）提示词模板

```text
这是我的phpStudy网站根目录 xxx\phpstudy_pro\WWW，靶场代码在：xxx，
请帮我部署这个靶场到WWW，运行测试。
要求：
1. 先阅读靶场代码目录下的 DEPLOY.md 第四章（phpStudy 环境安装说明），按文档步骤操作；
2. 确认 phpStudy 的 Apache 和 MySQL 已启动；
3. 通过浏览器或 HTTP 请求访问 install.php 完成初始化；
4. 部署完成后运行 test_all.py 自动化测试（共 109 项），把结果汇报给我。
```

### Linux Docker 提示词模板

```text
这是我的虚拟机 IP x.x.x.x  root/123456，靶场代码在：xxx，
请帮我用Docker部署这个靶场，运行测试。
要求：
1. 先阅读靶场代码目录下的 DEPLOY.md 第三章（Docker 部署），按文档步骤操作；
2. 项目必须部署到 /opt/bafanglab（路径型关卡和测试依赖此绝对路径）；
3. 使用 deploy.sh 一键部署（能联网用默认构建；离线环境先 import-images.sh 导入镜像后用 --no-build）；
4. 通过 curl -X POST http://127.0.0.1/install.php 初始化环境；
5. 部署完成后运行 python3 test_all.py http://127.0.0.1（共 109 项），把结果汇报给我。
```

### 注意事项

- Agent 执行过程中会启动服务、写数据库、修改部分文件权限，建议在**虚拟机或专用学习机**上执行，不要在生产环境使用；
- 若 Agent 执行失败，让它读取 `DEPLOY.md` 对应章节的「常见问题排查」小节后重试；Docker 部署失败时可让它执行 `docker compose logs --tail=50 web` 查看日志；
- 测试运行后会在项目目录生成 `test_result_*.txt` 报告文件，属正常现象。

***

## 二、部署方式选择

| 方式                     | 适用场景                         | 参考章节       |
| ---------------------- | ---------------------------- | ---------- |
| **Agent 自动部署**（零命令行）   | 不熟悉命令行，交给 AI Agent 代为执行      | 第一章        |
| **Docker - 离线镜像包**（推荐） | 教学分发、内网、网络不稳定；实测 35 秒完成      | 第三章 2.路径 B |
| Docker - 联网构建          | 部署机能访问 Docker Hub / Debian 源 | 第三章 2.路径 A |
| phpStudy（Windows）      | Windows 本地学习、无 Docker 环境     | 第四章        |
| Linux 手动部署             | Ubuntu / CentOS 服务器          | 第五章        |

***

## 三、Docker 部署（推荐，跨平台一致）

Docker 方式一条命令即可得到固定的 **PHP 7.4.33 + Apache 2.4 + MariaDB 10.11** 组合，与宿主机环境完全解耦。

### 1. 环境要求

| 项目             | 要求                                  |
| -------------- | ----------------------------------- |
| Docker Engine  | 20.10+                              |
| Docker Compose | v2（使用 `docker compose` 子命令）         |
| 磁盘             | ≥ 2 GB（镜像 + 数据库数据文件）                |
| 端口             | 80、3306 空闲（可改，见本章第 6 节）             |
| 网络             | 首次构建需能拉取 `mariadb:10.11` 与基础 PHP 镜像 |

> **项目目录固定为** **`/opt/bafanglab`**：`docker-compose.yml` 把项目目录整体挂载到容器内 `/opt/bafanglab`，而 `test_all.py` 的路径型 payload（`INTO OUTFILE`、反序列化写文件、SSTI 读文件）用的是脚本自身所在的绝对路径。两者一致时全部关卡与测试均正常；若改放其他目录，这 3 个依赖绝对路径的关卡自动化测试会失败。因此**请部署到** **`/opt/bafanglab`**。

### 2. 快速开始

部署分两条路径，按场景选择：

| 路径           | 适用场景                         | 实测总耗时（清空镜像 → 测试通过）                         |
| ------------ | ---------------------------- | ------------------------------------------ |
| **A. 联网构建**  | 部署机能访问 Docker Hub / Debian 源 | **5.4 \~ 14.3 分钟**，其中 80%\~95% 花在拉取镜像，且不可控 |
| **B. 离线镜像包** | 教学分发、内网、网络不稳定（**推荐**）        | **35 秒**，全程不联网、不构建                         |

两条路径在\*\*同一台机器（Ubuntu 24.04 / Docker 29.4）\*\*上从「删光镜像与数据」开始实测，逐阶段耗时：

| 阶段                          | A. 联网构建（第 1 轮）       | A. 联网构建（第 2 轮）        | B. 离线镜像包   |
| --------------------------- | -------------------- | --------------------- | ---------- |
| 清理镜像/容器/数据库                 | 4.0 s                | 2.4 s                 | 2.4 s      |
| 拉取 `mariadb:10.11` (327MB)  | 121.8 s              | **759.1 s**           | —（随包导入）    |
| 拉取 `php:7.4-apache` (453MB) | 136.3 s              | 39.4 s                | —（随包导入）    |
| 构建 `bafanglab:1.0.0`        | 47.2 s               | 43.3 s                | —（不需要）     |
| 导入离线包 `docker load`         | —                    | —                     | 14.6 s     |
| 启动 → 首页 HTTP 200            | 8.6 s                | 8.7 s                 | 16.7 s     |
| `install.php` 初始化           | <0.1 s               | <0.1 s                | <0.1 s     |
| `test_all.py` 109 项测试       | 3.7 s                | 3.7 s                 | 3.6 s      |
| **合计**                      | **321.8 s（5.4 min）** | **856.8 s（14.3 min）** | **35.0 s** |

> 说明：B 列「启动」16.7 s 测于 `deploy.sh` 改造前（脚本内部固定 `sleep 10`）；现已改为轮询首页 HTTP 200，同条件下会更早返回，因此 **35.0 s 应视为保守上限**。A 列数值不受该改动影响。

结论：**构建、启动、安装、测试这些我们能优化的环节加起来只有约 60 秒且非常稳定；真正不可控的是拉取镜像**——同一台机器、同一天、同一个加速器，`mariadb` 一次 122 秒、一次 759 秒，相差 6 倍。教学分发场景请一律走路径 B。

#### 路径 A：联网构建

```bash
# 1) 上传源码到服务器
tar -czf bafanglab.tar.gz bafanglab
scp bafanglab.tar.gz root@<服务器IP>:/tmp/
ssh root@<服务器IP>
mkdir -p /opt && tar -xzf /tmp/bafanglab.tar.gz -C /opt

# 2) 一键部署（构建镜像 + 启动 web/db 两个容器 + 等待就绪）
cd /opt/bafanglab
bash deploy.sh

# 3) 初始化靶场环境（建库建表、写入测试数据、生成练习目录）
curl -X POST http://127.0.0.1/install.php
```

`deploy.sh` 依次完成：准备 `data/mysql` 与运行时权限 → `docker compose up -d --build` → 等待服务就绪 → 打印访问地址与测试命令。

#### 路径 B：离线镜像包（教学分发推荐）

学员侧共 **5 步、约 35 秒**，只需要 Docker，不需要外网、不需要构建、不需要安装 PHP/MySQL。

**第 1 步：下载分发文件**

分发包含两个必需文件（校验文件可选）：

| 文件                        | 内容                                       | 体积       |
| ------------------------- | ---------------------------------------- | -------- |
| `bafanglab.tar.gz`        | 靶场源码（不含数据库数据；含目录扫描关卡用的 `.git` 假仓库）       | 约 6.6 MB |
| `bafanglab-images.tar.gz` | `bafanglab:1.0.0` + `mariadb:10.11` 两个镜像 | 265 MB   |
| `MD5SUMS`                 | 上述两个文件的校验值（可选）                           | <1 KB    |

按分发渠道选择下载方式：

```bash
# 渠道一：内网文件服务器 / Nginx 目录（推荐，一条命令）
curl -O http://<内网文件服务器>/bafanglab.tar.gz
curl -O http://<内网文件服务器>/bafanglab-images.tar.gz
curl -O http://<内网文件服务器>/MD5SUMS

# 渠道二：网盘（百度网盘 / 微云 / 企业微信文件等）
#   浏览器下载后放到同一目录即可，例如 ~/bafanglab-dist/

# 校验完整性（可选但建议，尤其是网盘传输）
md5sum -c MD5SUMS
```

**第 2 步：解压源码到固定路径**

```bash
sudo mkdir -p /opt
sudo tar -xzf bafanglab.tar.gz -C /opt
cd /opt/bafanglab
```

> 路径必须是 `/opt/bafanglab`，原因见本章第 1 节末尾的说明（`test_all.py` 的路径型 payload 依赖它）。

**第 3 步：导入镜像（实测 15 秒，不联网）**

```bash
sudo bash import-images.sh ~/bafanglab-dist/bafanglab-images.tar.gz
# 脚本内部执行 docker load，并在结束时列出 bafanglab / mariadb 镜像用于确认
```

**第 4 步：启动（不构建、不联网）**

```bash
sudo bash deploy.sh --no-build
# 依次：准备 data/mysql 与运行时权限 → docker compose up -d（不 build）
#       → 轮询首页直到返回 HTTP 200（最多 60 秒，健康时数秒内返回）→ 打印访问地址
```

> `deploy.sh` 早期版本用固定 `sleep 10` 等待，现已改为**轮询首页 HTTP 状态**：服务健康时提前返回，异常时也不会静默通过（超时会打印 `docker compose logs` 排查命令）。

**第 5 步：初始化靶场环境**

```bash
curl -X POST http://127.0.0.1/install.php
# 建库建表、写入测试数据、生成练习目录（backup/.git、admin_area、fileinc 页面等）
```

五步完成后即可访问。若需验证部署质量，可再跑一次全量测试（实测 3.6 秒），见**第六章**。

**作者侧：如何准备上述分发文件**

```bash
cd /opt/bafanglab

# 1) 导出镜像包（实测 52 秒 → 265MB）
sudo bash export-images.sh

# 2) 另存一份源码包（务必排除 data/，否则会把 149MB 数据库一起分发出去）
cd /opt
sudo tar --exclude='bafanglab/data' --exclude='bafanglab/.screenshots' \
         --exclude='bafanglab/__pycache__' --exclude='*.tar.gz' \
         -czf /tmp/bafanglab.tar.gz bafanglab

# 3) 生成校验文件，与两个包放在一起分发
cd /tmp
sudo md5sum bafanglab.tar.gz bafanglab-images.tar.gz > MD5SUMS
```

把 `bafanglab.tar.gz`、`bafanglab-images.tar.gz`、`MD5SUMS` 三个文件放到内网文件服务器或网盘即可。

**可选：改用私有镜像仓库分发**

镜像包适合课堂与内网；若学员机器可直接访问内网 Registry，可省掉 265MB 传输：

```bash
# 作者侧：推送
docker tag bafanglab:1.0.0 <registry>/bafanglab:1.0.0
docker tag mariadb:10.11   <registry>/mariadb:10.11
docker push <registry>/bafanglab:1.0.0
docker push <registry>/mariadb:10.11

# 学员侧：把 docker-compose.yml 里的 image 改成 <registry>/... ，然后
docker compose pull && sudo bash deploy.sh --no-build
```

代价是学员侧仍需从仓库拉取 800MB（含 php 基础层），在弱网环境下不如离线包稳定 —— **教学场景建议仍用离线包**。

部署完成后访问（**注意是根路径，没有** **`/bafanglab`** **前缀**）：

```
http://<服务器IP>/              # 靶场首页
http://<服务器IP>/install.php    # 安装 / 一键重置
http://<服务器IP>/check.php      # 环境自检
```

### 3. 手动部署（等价步骤）

```bash
cd /opt/bafanglab
mkdir -p data/mysql
chmod -R 777 uploads modules/sqli/files
chmod 666 config.php
docker compose up -d --build
docker compose ps          # 等 bafanglab-db 变为 healthy、bafanglab-web 为 Up
```

#### 3.1 镜像构成与构建参数

镜像基于**官方** **`php:7.4-apache`**，不引入 WordPress 等任何无关内容：

| 项目                     | 数值 / 说明                                           |
| ---------------------- | ------------------------------------------------- |
| 最终镜像 `bafanglab:1.0.0` | **472 MB**                                        |
| 基础镜像 `php:7.4-apache`  | 453 MB                                            |
| 数据库镜像 `mariadb:10.11`  | 327 MB                                            |
| 无缓存完整重建耗时              | **43\~47 秒**（基础镜像已在本机时，少量波动来自 apt 与扩展编译）          |
| 首次启动 → 页面可用            | **9 秒**（其中数据库 `healthy` 约 5 秒、Apache 就绪约 3 秒）     |
| 离线包体积                  | **265 MB**（gzip；未压缩 783 MB，`docker load` 可直接读压缩包） |

- **已内置扩展**（官方镜像自带，无需处理）：`dom` / `libxml` / `SimpleXML` / `curl` / `mbstring` / `openssl` / `json` / `session`
- **构建时编译的扩展**：`mysqli`、`pdo_mysql`（全部数据库关卡）、`gd`（验证码图片）
- `gd` 未编译 freetype：容器内没有任何 TTF 字体文件，`captcha_code.php` 本来就会走 `imagestring` 回退路径，编译 freetype 属无效开销
- Debian 源默认使用清华镜像，海外可覆盖：`docker compose build --build-arg DEBIAN_MIRROR=deb.debian.org`

```bash
docker pull php:7.4-apache      # 部署前可选：预拉基础镜像，避免构建时等待
docker pull mariadb:10.11
```

国内拉取 Docker Hub 镜像慢时，可先配置加速器（`/etc/docker/daemon.json` 的 `registry-mirrors`）再重试；或者直接用**路径 B 的离线镜像包**，彻底绕开拉取。

### 4. 常用运维命令

```bash
cd /opt/bafanglab

docker compose ps                      # 查看容器状态
docker compose logs -f web             # 跟随 web 日志（Apache / PHP）
docker compose restart web             # 只重启 web
docker compose down                    # 停止并删除容器（数据库数据保留在 ./data/mysql）
docker compose down && rm -rf data/mysql   # 彻底重置数据库，下次启动重新初始化
docker compose up -d --build           # 修改代码后重建并启动

docker exec -it bafanglab-web bash     # 进入 web 容器
docker exec -it bafanglab-db mysql -uroot -proot bafanglab -e "show tables;"   # 查库
```

练习混乱后重置环境：浏览器打开 `install.php` 点击「开始安装 / 重置」即可，无需重启容器。

### 5. 运行自动化测试

测试脚本在**宿主机**项目目录执行，目标地址用根路径：

```bash
cd /opt/bafanglab
pip3 install -r requirements.txt      # 或 apt-get install -y python3-requests
python3 test_all.py http://127.0.0.1
```

退出码 `0` 表示全部通过。测试开始前会自动调用 `install.php` 重置环境，结束后自动清理留言、写入文件并还原测试账号数据。

### 6. 端口与配置调整

| 需求         | 修改方式                                                                                              |
| ---------- | ------------------------------------------------------------------------------------------------- |
| 80 端口被占用   | 改 `docker-compose.yml` 中 web 的 `ports: "8080:80"`，改用 `http://IP:8080/`                            |
| 3306 端口被占用 | 改 db 的 `ports: "3307:3306"`                                                                       |
| 不对外暴露数据库   | 直接删除 db 的 `ports` 段（web 容器通过内部网络 `db:3306` 连接，不受影响）                                               |
| 修改数据库密码    | 同时改 `MARIADB_ROOT_PASSWORD` 与 web 的 `DB_PASS`；**旧密码已写入数据卷，须** **`rm -rf data/mysql`** **后重启才会生效** |
| 数据库主机名     | 默认 `DB_HOST=db`，由入口脚本在运行期写入 `config.php`；改服务名时同步改该环境变量                                            |

### 7. 关键设计说明

1. **路径统一**：容器 `DocumentRoot` 直接指向 `/opt/bafanglab`，与宿主机部署目录同名。这样测试脚本与页面 payload 里的绝对路径在容器内外完全一致，路径型关卡（SQL 文件读写、反序列化、SSTI）无需任何改动。
2. **SQL 注入文件读写免配置**：db 容器把同一宿主目录挂到 `/opt/bafanglab/modules/sqli/files`，并由 `docker/mysql/conf.d/bafanglab.cnf` 将 `secure_file_priv` 指向该路径 —— 对比 phpStudy 需手工编辑 `my.ini`，容器方式开箱即用。
3. **输出缓冲对齐**：启用 `php.ini-production`（`output_buffering=4096`），避免 `xss/upload.php`、`platform.php` 的 `header()` 操作产生 Warning。
4. **保留** **`.git`**：`.dockerignore` 未排除 `.git`，保证目录扫描关卡的 `/.git` 被 `.htaccess` 403 拦截的教学效果。
5. **入口脚本**：`docker/entrypoint.sh` 仅在运行期改写 `config.php` 的 `DB_HOST`、修正挂载目录权限、等待数据库就绪，随后前台启动 Apache。
6. **写权限清单（重要）**：项目目录是 bind mount，文件属主为宿主机用户（通常 root），容器内 `www-data` 默认无写权限。`entrypoint.sh` 启动时用 `chmod -R a+rwX` 放开以下位置，否则 `install.php` 重置环境会静默失败（Apache 日志出现 `file_put_contents(...): failed to open stream: Permission denied`）：
   | 目录/文件                                         | 用途                                  |
   | --------------------------------------------- | ----------------------------------- |
   | `uploads/`                                    | 文件上传关卡、XSS 平台日志与 payload            |
   | `modules/sqli/files/`                         | SQL 注入 `INTO OUTFILE` / `LOAD_FILE` |
   | `backup/`（含假 `.git`）                          | 目录扫描关卡重建 `db_backup.sql`、`www.zip`  |
   | `admin_area/`                                 | 隐藏后台首页                              |
   | `modules/fileinc/pages/`                      | 文件包含关卡的被包含页面                        |
   | `modules/fileread/files/`                     | 任意文件读取关卡的示例文件                       |
   | `modules/ssti/`                               | SSTI 关卡的 `secret.txt`               |
   | `modules/unserialize/`                        | 反序列化关卡日志                            |
   | `config.php`、`robots.txt`                     | 运行期改写数据库主机、重置扫描线索                   |
   | 用 `a+rwX` 而非 `777`：目录保留 `x` 位以便遍历，普通文件不误加执行位。 | <br />                              |

### 8. Docker 部署常见问题

| 现象                                | 原因                                                       | 解决                                                                                                     |
| --------------------------------- | -------------------------------------------------------- | ------------------------------------------------------------------------------------------------------ |
| web 容器反复重启，日志 `exec format error` | `entrypoint.sh` 被 Windows 行尾（CRLF）污染或缺失 `#!/bin/bash` 首行 | `sed -i 's/\r$//' docker/entrypoint.sh deploy.sh`，确认首行为 `#!/bin/bash` 后 `docker compose up -d --build` |
| 构建长时间卡住 / 拉取超时                    | 国内网络拉取基础镜像慢                                              | 改用**路径 B 离线镜像包**（最快）；或配置加速器、先 `docker pull php:7.4-apache` 与 `mariadb:10.11` 再构建                       |
| `bind: address already in use`    | 宿主机已有 nginx / apache / 其他容器占用 80                         | `ss -tlnp \| grep :80` 定位后停止，或改 compose 端口映射（本章第 6 节）                                                  |
| 数据库连接失败 1045                      | 旧数据卷中残留旧密码                                               | `docker compose down && rm -rf data/mysql` 后重新启动                                                       |
| SQL 文件读写关卡返回 1290                 | `secure_file_priv` 配置未生效                                 | 确认 `docker/mysql/conf.d/bafanglab.cnf` 已挂载并 `docker compose restart db`                                |
| 页面 500 / 验证码不显示                   | 基础镜像缺少 gd 或 mysqli                                       | 使用默认 `php:7.4-apache` 基础镜像（已含编译步骤），重建前不要跳过扩展安装层                                                        |
| 自动化测试仅路径类 3 项失败                   | 项目未放在 `/opt/bafanglab`                                   | 移动到 `/opt/bafanglab` 后重跑（见本章第 1 节说明）                                                                   |
| 磁盘空间不足                            | 构建缓存堆积                                                   | `docker builder prune`、`docker image prune`                                                            |

### 9. Docker 相关文件

```
bafanglab/
├── docker-compose.yml              编排：web(80) + db(3306)，整体挂载项目目录
├── deploy.sh                       一键部署（默认构建；--no-build 走离线镜像）
├── export-images.sh                导出离线镜像包（作者侧，约 265MB）
├── import-images.sh                导入离线镜像包（学员侧，无需联网）
├── .dockerignore                   构建上下文排除项（data/、缓存、日志、镜像包）
└── docker/
    ├── Dockerfile                  官方 php:7.4-apache + mysqli/pdo_mysql/gd
    ├── entrypoint.sh               容器入口（改写 DB_HOST、修权限、等数据库、启 Apache）
    └── mysql/conf.d/bafanglab.cnf  MariaDB 配置（secure_file_priv 指向 files 目录）
```

***

## 四、phpStudy 环境安装说明（Windows）

1. 将本项目整个 `bafanglab` 目录放到 phpStudy 的 WWW 根目录（如 `WWW\bafanglab`，盘符按你的安装位置替换）。
2. 打开 phpStudy，启动 **Apache** 和 **MySQL**。
3. 在 phpStudy「网站」中确认站点指向你的安装路径（如 `D:\phpStudy\WWW\bafanglab`），域名或端口按实际配置（默认 `http://localhost`）。
4. 浏览器打开安装页，页面会自动读取 `config.php` 当前配置作为默认值（默认 `127.0.0.1:3306`，账号密码 `root / root`，库名 `bafanglab`）：
   ```
   http://localhost/<部署目录>/install.php
   ```
   确认或修改数据库 **IP / 端口 / 账号 / 密码 / 库名** 后，点击「开始安装 / 重置」提交。安装程序会逐项校验并执行建库、建表、写入测试数据、生成练习目录，任一环节失败都会在页面上给出具体原因与排查建议（如服务未启动、账号密码错误、权限不足等）。
   安装成功后，本次填写的数据库连接信息会**自动写回** **`config.php`**，整个靶场随即切换到新配置，无需手工改文件。
   > **SQL 注入「文件读写」关卡环境要求**：MySQL 的 `secure_file_priv` 需指向本项目的 `modules/sqli/files` 目录。phpStudy 下编辑 MySQL 的 `my.ini`（版本目录按实际，位于 MySQL 安装目录下），在 `[mysqld]` 段添加 `secure-file-priv="<安装路径>/modules/sqli/files"`（路径用正斜杠，替换为你的真实安装路径）后重启 MySQL 生效（MySQL 5.7+ 该变量只读，不能用 `SET GLOBAL` 修改）。保持默认值（禁止文件导入导出）时，`INTO OUTFILE` / `LOAD_FILE` 会被拒绝并返回错误码 1290，该子关卡无法通关，其余关卡不受影响。`check.php` 的「数据库」节会自动检测该项并给出对应的修改建议。
5. 安装完成后先做一次环境自检，再访问首页开始练习：
   ```
   http://localhost/<部署目录>/check.php     # 环境自检，逐项确认扩展/权限/伪静态/数据库
   http://localhost/<部署目录>/index.php     # 首页，开始练习
   ```
6. 练习混乱后想重置环境：打开 `install.php` 直接点击「开始安装 / 重置」按钮即可一键重建数据库（默认值就是当前配置，直接提交即可）。

***

## 五、Linux 环境安装说明（Ubuntu / CentOS）

### 1. 环境要求

| 组件              | 版本要求       | 必需扩展/模块                            |
| --------------- | ---------- | ---------------------------------- |
| PHP             | 5.4 \~ 8.3 | mysqli、curl、gd、mbstring、xml（含 DOM） |
| MySQL / MariaDB | 5.7+       | root 账号具备建库权限                      |
| Apache          | 2.4+       | mod\_rewrite（用于 .htaccess）         |

### 2. Ubuntu / Debian 部署步骤

```bash
# 1. 安装 Apache、PHP、MySQL 及所需扩展
sudo apt-get update
sudo apt-get install -y apache2 php php-mysql php-curl php-gd php-mbstring php-xml mysql-server

# 2. 启动服务并设置开机自启
sudo systemctl start apache2 mysql
sudo systemctl enable apache2 mysql

# 3. 设置 MySQL root 密码（MySQL 8.0 默认使用 auth_socket，需改为密码登录）
sudo mysql -uroot -e "ALTER USER 'root'@'localhost' IDENTIFIED WITH mysql_native_password BY 'root'; FLUSH PRIVILEGES;"

# 4. 启用 mod_rewrite 并允许 .htaccess 覆盖
sudo a2enmod rewrite
sudo sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf
sudo systemctl restart apache2

# 5. 将项目放到 Web 根目录
sudo cp -r bafanglab /var/www/html/
sudo chown -R www-data:www-data /var/www/html/bafanglab
sudo chmod -R 755 /var/www/html/bafanglab
sudo mkdir -p /var/www/html/bafanglab/uploads
sudo chmod 777 /var/www/html/bafanglab/uploads
sudo chmod 666 /var/www/html/bafanglab/config.php

# 6. 浏览器访问安装页（默认 127.0.0.1:3306，root/root，库名 bafanglab）
#    http://<服务器IP>/bafanglab/install.php
```

### 3. CentOS 7 部署步骤

```bash
# 1. 安装 Apache、PHP、MySQL 及所需扩展
sudo yum install -y httpd php php-mysqlnd php-curl php-gd php-mbstring php-xml mariadb-server mariadb

# 2. 启动服务并设置开机自启
sudo systemctl start httpd mariadb
sudo systemctl enable httpd mariadb

# 3. 设置 MySQL root 密码（首次启动后查看临时密码，或用 skip-grant-tables 重置）
sudo grep 'temporary password' /var/log/mysqld.log 2>/dev/null
sudo mysql_secure_installation   # 按提示设置 root 密码为 root

# 4. 允许 .htaccess 覆盖（Apache 2.4）
sudo sed -i 's/AllowOverride None/AllowOverride All/g' /etc/httpd/conf/httpd.conf
sudo systemctl restart httpd

# 5. 关闭防火墙（学习靶场，方便访问）或放行 80 端口
sudo systemctl stop firewalld
sudo systemctl disable firewalld

# 6. 将项目放到 Web 根目录
sudo cp -r bafanglab /var/www/html/
sudo chown -R apache:apache /var/www/html/bafanglab
sudo chmod -R 755 /var/www/html/bafanglab
sudo mkdir -p /var/www/html/bafanglab/uploads
sudo chmod 777 /var/www/html/bafanglab/uploads
sudo chmod 666 /var/www/html/bafanglab/config.php

# 7. 浏览器访问安装页
#    http://<服务器IP>/bafanglab/install.php
```

### 4. 常见问题排查

| 现象                              | 原因                                     | 解决                                                   |
| ------------------------------- | -------------------------------------- | ---------------------------------------------------- |
| 页面空白或 500 错误                    | PHP 扩展缺失                               | `php -m` 确认 mysqli/curl/gd/mbstring/xml 已加载          |
| `Class 'DOMDocument' not found` | 缺少 php-xml                             | 安装 `php-xml`（CentOS）或 `php-dom`（部分发行版）               |
| 验证码图片不显示                        | 缺少 GD 扩展                               | 安装 `php-gd`                                          |
| 数据库连接失败 1045                    | MySQL root 密码不对                        | 确认 `config.php` 中 DB\_PASS 与实际一致，或重新运行 install.php   |
| `.htaccess` 不生效（.git 可访问）       | mod\_rewrite 未启用或 AllowOverride 为 None | `a2enmod rewrite` + 设 `AllowOverride All` 后重启 Apache |
| 文件上传失败                          | uploads 目录无写权限                         | `chmod 777 uploads` 并确认属主为 Apache 运行用户               |

***

## 六、部署后验证：一键自动化测试

修改代码或迁移环境后，运行自动化测试验证全部关卡功能是否正常（共 109 项检查；运行前自动调用 install.php 重置环境，测试结束后自动清理测试留言、写入文件，并还原测试账号邮箱等数据，环境恢复为干净初始状态）。

执行（phpStudy / Linux 通用，目标地址含部署子目录）：

```bash
pip install -r requirements.txt
python test_all.py http://localhost/<部署目录>
```

Docker 部署时目标地址为根路径（详见第三章第 5 节）：

```bash
python3 test_all.py http://127.0.0.1
```

退出码 `0` = 全部通过，`1` = 存在失败项。
