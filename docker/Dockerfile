# bafanglab - Web 漏洞靶场镜像（Apache 2.4 + PHP 7.4）
# 版权所有：八方网域-无涯
#
# 基础镜像：官方 php:7.4-apache，体积小、无冗余层（不引入 WordPress / CMS 等无关内容）。
#   官方镜像已内置：dom / libxml / SimpleXML / curl / mbstring / openssl / json / session / zlib
#   需额外编译：mysqli、pdo_mysql（全部数据库关卡）、gd（验证码图片，不带 freetype，见下）
#
# 路径约定：容器内项目路径固定 /opt/bafanglab（与宿主机部署目录同名），
# Apache DocumentRoot 直接指向它——保证测试脚本的绝对路径 payload 在容器内外一致。
FROM php:7.4-apache

# Debian 源（默认清华；海外可覆盖：--build-arg DEBIAN_MIRROR=deb.debian.org）
# 注：bullseye-security 仓库已于 2026-08 结束常规支持，索引与软件池不同步（索引要 deb11u4，
#     池里已下架），因此构建时屏蔽 security 源，仅使用 bullseye 主仓库的基础版本。
ARG DEBIAN_MIRROR=mirrors.tuna.tsinghua.edu.cn

# 1) 编译扩展：mysqli / pdo_mysql / gd(png+jpeg)
#    官方镜像未内置这三个扩展；dom/curl/mbstring/xml 等已内置，无需处理。
#    gd 不编译 freetype：容器内无 TTF 字体，验证码本来就走 imagestring 回退路径。
RUN set -eux; \
    if [ -f /etc/apt/sources.list ]; then \
        sed -i "s|deb.debian.org|${DEBIAN_MIRROR}|g" /etc/apt/sources.list; \
        sed -i "/debian-security/d" /etc/apt/sources.list; \
    fi; \
    apt-get update || apt-get update; \
    apt-get install -y --no-install-recommends libpng-dev libjpeg62-turbo-dev; \
    docker-php-ext-configure gd --with-jpeg; \
    docker-php-ext-install -j"$(nproc)" gd mysqli pdo_mysql; \
    rm -rf /var/lib/apt/lists/*; \
    php -r 'foreach (["mysqli","pdo_mysql","gd","dom","curl","mbstring"] as $e) { echo $e, "=", extension_loaded($e) ? "ok" : "MISSING", PHP_EOL; }'

# 2) Apache：启用 rewrite/headers，放开 .htaccess，DocumentRoot 指向 /opt/bafanglab
RUN a2enmod rewrite headers \
    && sed -ri 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf \
    && sed -ri 's#DocumentRoot /var/www/html#DocumentRoot /opt/bafanglab#g' \
        /etc/apache2/sites-available/000-default.conf \
    && printf '<Directory /opt/bafanglab>\n    Options -Indexes +FollowSymLinks\n    AllowOverride All\n    Require all granted\n</Directory>\n' \
        > /etc/apache2/conf-available/bafanglab.conf \
    && a2enconf bafanglab

# 3) 启用 php.ini-production（output_buffering=4096），与 phpstudy 行为一致，
#    避免 xss/upload.php、platform.php 的 header 操作报 Warning
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

# 4) 靶场源码（运行时会被 bind mount 覆盖，此处仅作镜像自包含兜底，约 15MB）
COPY . /opt/bafanglab/

RUN chown -R www-data:www-data /opt/bafanglab \
    && find /opt/bafanglab -type d -exec chmod 755 {} + \
    && find /opt/bafanglab -type f -exec chmod 644 {} + \
    && chmod 666 /opt/bafanglab/config.php

# 5) 入口脚本：改写数据库主机、修正挂载目录权限、等待数据库就绪
COPY docker/entrypoint.sh /usr/local/bin/bafanglab-entrypoint.sh
RUN chmod +x /usr/local/bin/bafanglab-entrypoint.sh

ENTRYPOINT ["bafanglab-entrypoint.sh"]
EXPOSE 80
