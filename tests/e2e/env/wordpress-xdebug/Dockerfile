FROM wordpress:php8.1
# pecl.php.net is deprecated upstream (PIE is the successor) and unreachable
# since early June 2026, so build Xdebug from a pinned GitHub release tarball
# instead of `pecl install xdebug`. Xdebug 3.4.x is the last line supporting PHP 8.1.
RUN curl -fsSL https://github.com/xdebug/xdebug/archive/refs/tags/3.4.7.tar.gz -o /tmp/xdebug.tar.gz \
	&& tar -xzf /tmp/xdebug.tar.gz -C /tmp \
	&& cd /tmp/xdebug-3.4.7 \
	&& phpize \
	&& ./configure --quiet \
	&& make --quiet -j"$(nproc)" \
	&& make --quiet install \
	&& rm -rf /tmp/xdebug.tar.gz /tmp/xdebug-3.4.7 \
	&& echo 'xdebug.remote_enable=1' >> $PHP_INI_DIR/php.ini \
	&& echo 'xdebug.remote_port=9000' >> $PHP_INI_DIR/php.ini \
	&& echo 'xdebug.remote_host=host.docker.internal' >> $PHP_INI_DIR/php.ini \
	&& echo 'xdebug.remote_autostart=0' >> $PHP_INI_DIR/php.ini \
	&& docker-php-ext-enable xdebug > /dev/null
RUN apt-get update \
	&& apt-get install --assume-yes --quiet --no-install-recommends gnupg2 subversion mariadb-client less jq
RUN apt-get install -y openssh-client
RUN curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar \
	&& chmod +x wp-cli.phar \
	&& mv wp-cli.phar /usr/local/bin/wp
