# bafanglab - Web 漏洞靶场

> 版权所有：八方网域-无涯

## 一、项目简介

bafanglab 是一个基于 **PHP + MySQL + Apache** 的 Web 漏洞教学靶场，包含 16 个经典漏洞模块、31 个子关卡页面（另有 3 个纯教学页），适合 Web 安全入门与 Burp Suite 实操训练。

每个模块均提供「基础知识 / 提示 / 推荐工具 / 漏洞源码 / 修复方法 / 真实案例」学习辅助。

内置**简体中文**界面，并提供独立的**环境自检页** **`check.php`**，一眼看清 PHP 版本、扩展、目录权限、伪静态与数据库状态。

本靶场已在 **Windows（phpStudy）/ Ubuntu / CentOS / Docker** 四种环境下完成全量功能验证（105 项自动化测试全部通过），代码自动适配跨平台差异（如 ping 命令参数、文件路径分隔符、系统文件路径等）。推荐使用 Docker 方式部署，环境一致、开箱即用，见 [DEPLOY.md](DEPLOY.md)。

### 界面预览

| 首页 | 关卡页 |
| --- | --- |
| ![首页](images/p1-%E9%A6%96%E9%A1%B5.png) | ![关卡](images/p2-%E5%85%B3%E5%8D%A1.png) |

## 二、技术栈与版本要求

| 组件              | 最低版本 | 推荐版本       | 说明                                       |
| --------------- | ---- | ---------- | ---------------------------------------- |
| PHP             | 5.4  | 7.3 \~ 8.3 | 需开启扩展：mysqli、curl、gd、mbstring、xml（含 dom） |
| MySQL / MariaDB | 5.7  | 5.7 / 8.0  | root 账号需具备建库权限                           |
| Apache          | 2.4  | 2.4        | 需启用 mod\_rewrite（支持 .htaccess）           |

> 已实测通过的环境组合：
>
> - Windows：phpStudy 集成环境（PHP 7.3.4 + MySQL 5.7.26 + Apache）
> - Ubuntu 24.04：PHP 8.3.6 + MySQL 8.0.45 + Apache 2.4.58
> - CentOS 7：PHP 5.4.16 + MySQL 5.7.44 + Apache 2.4.6
> - Docker：PHP 7.4.33 + MariaDB 10.11 + Apache 2.4（见 `DEPLOY.md`）

## 三、快速开始

完整部署步骤、运维命令、常见问题排查与部署后验证，统一见 **[DEPLOY.md](DEPLOY.md)**：

| 部署方式 | 适用场景 | DEPLOY.md 章节 |
| --- | --- | --- |
| **Agent 自动部署**（零命令行） | 不熟悉命令行，交给 AI Agent 代为执行 | 第一章 |
| **Docker - 离线镜像包**（推荐） | 教学分发、内网、网络不稳定，实测约 35 秒完成 | 第三章 2.路径 B |
| Docker - 联网构建 | 可访问 Docker Hub / Debian 源 | 第三章 2.路径 A |
| phpStudy（Windows） | Windows 本地学习 | 第四章 |
| Linux 手动部署 | Ubuntu / CentOS 服务器 | 第五章 |

各部署方式完成后，均通过浏览器打开 `install.php` 初始化/重置环境，再访问 `check.php` 自检、`index.php` 开始练习。

## 四、漏洞模块清单

| #  | 模块         | 入口                             | 子关卡 | 说明                                                                                                          |
| -- | ---------- | ------------------------------ | --- | ----------------------------------------------------------------------------------------------------------- |
| 1  | Burp 抓包基本功 | /modules/burp/index.php        | 4   | 基础知识（网络协议入门 protocol.php / 编码基础 encode.php / OOB 和 DNSLog dnslog.php）+ 修改 GET 请求、修改 POST 请求（金额篡改）、修改响应、请求重放 |
| 2  | 目录扫描       | /modules/dirscan/index.php     | 1   | backup/、admin\_area/、robots.txt、.git 泄露、config.php.bak 残留                                                   |
| 3  | 参数 Fuzz    | /modules/fuzz/index.php        | 1   | 隐藏参数 debug / admin / source                                                                                 |
| 4  | JS 敏感信息泄露  | /modules/jsleak/index.php      | 2   | JS 提取 AK/SK + 未授权访问接口                                                                                       |
| 5  | 密码暴力猜解     | /modules/brute/index.php       | 2   | 无验证码 + 有验证码（OCR 识别绕过）                                                                                       |
| 6  | XSS        | /modules/xss/reflect.php       | 5   | 反射型 + 存储型（stored.php）+ DOM 型（dom.php）+ 上传型（upload.php）+ XSS 平台（platform.php）                                |
| 7  | SQL 注入     | /modules/sqli/get.php          | 6   | GET 数字型 + POST 字符型 + 报错/布尔/时间盲注 + 文件读写（INTO OUTFILE / LOAD\_FILE）                                           |
| 8  | XXE 外部实体注入 | /modules/xxe/index.php         | 1   | 外部实体读本地文件 + php\://filter 读源码 + 内网探测                                                                        |
| 9  | 文件上传       | /modules/upload/index.php      | 1   | 后端扩展名黑名单绕过                                                                                                  |
| 10 | 文件包含       | /modules/fileinc/index.php     | 1   | 本地包含 + php\://filter 读源码                                                                                    |
| 11 | CSRF       | /modules/csrf/index.php        | 1   | 修改邮箱无 token 防护                                                                                              |
| 12 | SSRF       | /modules/ssrf/index.php        | 1   | 内网探测 + file:// 读文件                                                                                          |
| 13 | 任意文件读取     | /modules/fileread/index.php    | 1   | 目录穿越读任意文件                                                                                                   |
| 14 | RCE        | /modules/rce/index.php         | 2   | 命令拼接（index.php）+ eval 代码执行（code.php）                                                                        |
| 15 | 反序列化       | /modules/unserialize/index.php | 1   | \_\_destruct 任意文件写                                                                                          |
| 16 | SSTI 模板注入  | /modules/ssti/index.php        | 1   | 自研模板引擎 eval 表达式，从 7\*7 探测到命令执行                                                                              |

每个模块页面顶部均有「开始练习 / 基础知识 / 提示 / 推荐工具 / 漏洞源码 / 修复方法 / 真实案例」学习辅助。

## 五、测试账号

| 用户名   | 密码         | 角色   |
| ----- | ---------- | ---- |
| admin | admin123   | 管理员  |
| wuya  | wuya\@2026 | 管理员  |
| test  | 123456     | 普通用户 |

## 六、目录结构

```
bafanglab/
├── index.php            首页（模块导航，关卡清单来自 includes/modules.php）
├── about.php            靶场说明（项目介绍、作者、基础知识建议、其他靶场与工具汇总）
├── check.php            环境自检（PHP 版本 / 扩展 / 权限 / 伪静态 / 数据库）
├── install.php          安装/重置脚本（表单化填写数据库连接信息，失败给出具体原因）
├── config.php           数据库与全局配置（含版本常量 BAFANGLAB_VERSION）
├── includes/            公共头尾、数据库连接、i18n、关卡注册表、选项卡、安装辅助函数
│   ├── i18n.php         文案加载（仅简体中文，t() / t2() 取词、content 片段加载）
│   ├── modules.php      关卡注册表与子关卡数量（唯一数据源）
│   ├── tabs.php         各模块跨页选项卡函数
│   └── install_lib.php  安装辅助（config.php 回写，可脱离 HTTP 单独验证）
├── lang/                语言包（zh.php 简体中文）
├── content/             教学内容片段（content/<slug>/<variant>/zh/ 下的 basic/fix/bypass/hint/tools/case.html）
├── assets/              CSS/JS 静态资源
├── modules/             16 个漏洞模块（含真实案例 case.php 页面；模块1 另含 protocol.php / encode.php / dnslog.php 三个纯教学页）
├── uploads/             文件上传目录
├── backup/              目录扫描练习 - 假备份 + 示例.git泄露仓库
├── admin_area/          目录扫描练习 - 隐藏后台
├── robots.txt           目录扫描线索
├── docker-compose.yml   Docker 编排（web + db，整体挂载项目目录）
├── deploy.sh            Docker 一键部署脚本（支持 --no-build 离线启动）
├── export-images.sh     导出离线镜像包（作者侧）
├── import-images.sh     导入离线镜像包（学员侧）
├── .dockerignore        Docker 构建上下文排除项
├── .gitattributes       跨平台行尾规范（统一 LF，保护 shell 脚本）
├── .gitignore           git 忽略清单
├── README.md            项目介绍（本文档）
├── DEPLOY.md            部署指南（Docker / phpStudy / Linux / 自动化测试）
├── docker/              Docker 相关文件（Dockerfile / entrypoint.sh / mysql 配置 / 性能计时脚本）
├── test_all.py          自动化测试脚本（105 项检查）
└── data/mysql/          数据库数据目录（Docker 部署后自动生成）
```

## 七、安全声明

本项目所有漏洞均为**故意设计**，仅用于本地安全教学。请勿将本项目部署到公网，请勿将相关技术用于未授权目标。

<br />
