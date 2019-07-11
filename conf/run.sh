#!/bin/bash

if [ -d "storage" ]; then
	chmod -Rf 777 storage/
fi

composer_file="composer.json"
if [ -f "$composer_file" ]; then
	if [ ! -d "vendor" ]; then
		composer update
	fi
fi

npm_file="package.json"
if [ -f "$npm_file" ]; then
	if [ ! -d "node_modules" ]; then
		npm install
	fi
fi

php-fpm -F --pid /opt/bitnami/php/tmp/php-fpm.pid -y /opt/bitnami/php/etc/php-fpm.conf &
nginx -c /etc/nginx/nginx.conf -g "daemon off;"
