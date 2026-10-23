# Keep the default PHP and Xdebug in step with transact-local-dev's local/Dockerfile,
# so the plugin and the server it talks to run the same PHP locally. CI sets
# PHP_VERSION per job, so the job's PHP label is the PHP WordPress runs on.
ARG PHP_VERSION=8.4
FROM wordpress:php${PHP_VERSION}
ARG PHP_VERSION
# pecl.php.net is deprecated upstream (PIE is the successor) and unreachable
# since early June 2026, so build Xdebug from a pinned GitHub release tarball
# instead of `pecl install xdebug`. Xdebug 3.1 is the last line for PHP 7.4.
RUN XDEBUG_VERSION=$([ "$PHP_VERSION" = "7.4" ] && echo 3.1.6 || echo 3.4.7) \
	&& curl -fsSL "https://github.com/xdebug/xdebug/archive/refs/tags/${XDEBUG_VERSION}.tar.gz" -o /tmp/xdebug.tar.gz \
	&& tar -xzf /tmp/xdebug.tar.gz -C /tmp \
	&& cd "/tmp/xdebug-${XDEBUG_VERSION}" \
	&& phpize \
	&& ./configure --quiet \
	&& make --quiet -j"$(nproc)" \
	&& make --quiet install \
	&& rm -rf /tmp/xdebug.tar.gz "/tmp/xdebug-${XDEBUG_VERSION}" \
	&& echo 'xdebug.remote_enable=1' >> $PHP_INI_DIR/php.ini \
	&& echo 'xdebug.remote_port=9000' >> $PHP_INI_DIR/php.ini \
	&& echo 'xdebug.remote_host=host.docker.internal' >> $PHP_INI_DIR/php.ini \
	&& echo 'xdebug.remote_autostart=0' >> $PHP_INI_DIR/php.ini \
	&& docker-php-ext-enable xdebug > /dev/null
# wordpress:php7.4 is frozen on Debian 11, whose packages moved to
# archive.debian.org once its support ended.
RUN if grep -q bullseye /etc/os-release; then \
		sed -i 's|deb.debian.org|archive.debian.org|g' /etc/apt/sources.list; \
	fi
RUN apt-get update \
	&& apt-get install --assume-yes --quiet --no-install-recommends gnupg2 subversion mariadb-client less jq
RUN apt-get install -y openssh-client
RUN curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar \
	&& chmod +x wp-cli.phar \
	&& mv wp-cli.phar /usr/local/bin/wp
