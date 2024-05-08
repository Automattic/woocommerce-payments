FROM wordpress:php7.4
ARG XDEBUG_REMOTE_PORT
ARG XDEBUG_REMOTE_HOST=host.docker.internal
RUN pecl install xdebug-2.9.8 \
	&& echo 'xdebug.remote_enable=1' >> $PHP_INI_DIR/php.ini \
	&& echo "xdebug.remote_port=$XDEBUG_REMOTE_PORT" >> $PHP_INI_DIR/php.ini \
	&& echo "xdebug.remote_host=$XDEBUG_REMOTE_HOST" >> $PHP_INI_DIR/php.ini \
	&& echo 'xdebug.remote_autostart=0' >> $PHP_INI_DIR/php.ini \
	&& docker-php-ext-enable xdebug
RUN apt-get update \
	&& apt-get install --assume-yes --quiet --no-install-recommends gnupg2 subversion mariadb-client less jq
RUN apt-get install -y openssh-client
RUN curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar \
	&& chmod +x wp-cli.phar \
	&& mv wp-cli.phar /usr/local/bin/wp
