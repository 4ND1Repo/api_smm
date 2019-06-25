FROM bitnami/minideb-runtimes:stretch-php
LABEL maintainer "Bitnami <containers@bitnami.com>"

ENV ACCEPT_EULA=Y

# Install required system packages and dependencies
RUN install_packages ca-certificates libbz2-1.0 libc6 libcomerr2 libcurl3 libffi6 libfreetype6 libgcc1 libgcrypt20 libgmp10 libgnutls30 libgpg-error0 libgssapi-krb5-2 libhogweed4 libicu57 libidn11 libidn2-0 libjpeg62-turbo libk5crypto3 libkeyutils1 libkrb5-3 libkrb5support0 libldap-2.4-2 liblzma5 libmcrypt4 libmemcached11 libmemcachedutil2 libncurses5 libnettle6 libnghttp2-14 libp11-kit0 libpng16-16 libpq5 libpsl5 libreadline7 librtmp1 libsasl2-2 libssh2-1 libssl1.0.2 libssl1.1 libstdc++6 libsybdb5 libtasn1-6 libtidy5 libtinfo5 libunistring0 libxml2 libxslt1.1 wget zlib1g curl gnupg apt-transport-https 
RUN wget -nc -P /tmp/bitnami/pkg/cache/ https://downloads.bitnami.com/files/stacksmith/php-7.1.25-21-linux-amd64-debian-9.tar.gz && \
    echo "9c0ca4fc5ddafad98d27c94d459a921c1077139d5ece08198a6a48d18485fe13  /tmp/bitnami/pkg/cache/php-7.1.25-21-linux-amd64-debian-9.tar.gz" | sha256sum -c - && \
    tar -zxf /tmp/bitnami/pkg/cache/php-7.1.25-21-linux-amd64-debian-9.tar.gz -P --transform 's|^[^/]*/files|/opt/bitnami|' --wildcards '*/files' && \
    rm -rf /tmp/bitnami/pkg/cache/php-7.1.25-21-linux-amd64-debian-9.tar.gz
RUN mkdir -p /bitnami && ln -sf /bitnami/php /bitnami/php-fpm
RUN mkdir /opt/bitnami/php/logs && touch /opt/bitnami/php/logs/php-fpm.log

ENV BITNAMI_APP_NAME="php-fpm" \
    BITNAMI_IMAGE_VERSION="7.1.25-debian-9-r23" \
    PATH="/opt/bitnami/php/bin:/opt/bitnami/php/sbin:/opt/bitnami/php/sbin:$PATH"
    
# Microsoft SQL Server Prerequisites
RUN apt-get update -y --no-install-recommends
RUN curl https://packages.microsoft.com/keys/microsoft.asc | apt-key add - && curl https://packages.microsoft.com/config/debian/9/prod.list > /etc/apt/sources.list.d/mssql-release.list
RUN apt-get update -y --no-install-recommends
RUN apt-get -y --no-install-recommends install msodbcsql17 nginx autoconf make gcc g++ unixodbc unixodbc-dev sed nodejs git \
    && pecl install sqlsrv pdo_sqlsrv

# Install composer
RUN curl -sS https://getcomposer.org/installer | php

RUN printf "\nextension=sqlsrv.so" >> /opt/bitnami/php/etc/php.ini && \
    printf "\nextension=pdo_sqlsrv.so" >> /opt/bitnami/php/etc/php.ini && \
    sed -i s/^upload_max_filesize.*/upload_max_filesize\ =\ 32M/g /opt/bitnami/php/etc/php.ini && \
    sed -i s/^post_max_size.*/post_max_size\ =\ 200M/g /opt/bitnami/php/etc/php.ini && \
    sed -i s/^memory_limit.*/memory_limit\ =\ 1024M/g /opt/bitnami/php/etc/php.ini && \
    printf "\nclient_buffer_max_kb_size = '65000240'" >> /opt/bitnami/php/etc/php.ini && \
    printf "\nsqlsrv.ClientBufferMaxKBSize = 65000240" >> /opt/bitnami/php/etc/php.ini && \
    sed -i s/^pm.max_children.*/pm.max_children\ =\ 200/g /opt/bitnami/php/etc/php-fpm.d/www.conf && \
    printf "\nphp_admin_value[upload_max_filesize] = 200M" >> /opt/bitnami/php/etc/php-fpm.d/www.conf && \
    printf "\nphp_admin_value[post_max_size] = 200M" >> /opt/bitnami/php/etc/php-fpm.d/www.conf && \
    printf "\nphp_flag[display_errors] = off" >> /opt/bitnami/php/etc/php-fpm.d/www.conf && \
    printf "\nphp_flag[expose_php] = Off" >> /opt/bitnami/php/etc/php-fpm.d/www.conf && \
    printf "\npm.process_idle_timeout = 10s" >> /opt/bitnami/php/etc/php-fpm.d/www.conf
    
# Remove Cache
RUN apt-get remove --purge -y --allow-remove-essential autoconf make gcc g++ sed gnupg apt-transport-https wget curl git && \
    apt-get clean -y && \
    apt-get autoremove --purge -y && \
    apt-get autoclean --dry-run -y && \
    rm -rf /tmp/*

COPY conf/default /etc/nginx/sites-enabled/default
COPY conf/www.conf /opt/bitnami/php/etc/php-fpm.d/www.conf
RUN mkdir /app 

WORKDIR /app

EXPOSE 8080
EXPOSE 8443
