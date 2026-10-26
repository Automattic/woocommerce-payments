FROM wordpress:php8.1
ARG XDEBUG_MODE=coverage,debug
ARG XDEBUG_REMOTE_PORT
ARG XDEBUG_REMOTE_HOST=host.docker.internal
ARG XDEBUG_START_WITH_REQUEST=trigger
ARG XDEBUG_SHOW_ERROR_TRACE=0
ARG XDEBUG_SHOW_EXCEPTION_TRACE=0
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
    && echo "xdebug.mode=${XDEBUG_MODE}" >> $PHP_INI_DIR/php.ini \
    && echo "xdebug.client_port=${XDEBUG_REMOTE_PORT}" >> $PHP_INI_DIR/php.ini \
    && echo "xdebug.client_host=${XDEBUG_REMOTE_HOST}" >> $PHP_INI_DIR/php.ini \
    && echo "xdebug.start_with_request=${XDEBUG_START_WITH_REQUEST}" >> $PHP_INI_DIR/php.ini \
    && echo "xdebug.show_error_trace=${XDEBUG_SHOW_ERROR_TRACE}" >> $PHP_INI_DIR/php.ini \
    && echo "xdebug.show_exception_trace=${XDEBUG_SHOW_EXCEPTION_TRACE}" >> $PHP_INI_DIR/php.ini \
    && docker-php-ext-enable xdebug
RUN apt-get update \
	&& apt-get install --assume-yes --quiet --no-install-recommends gnupg2 subversion mariadb-client less jq openssh-client
RUN curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar \
	&& chmod +x wp-cli.phar \
	&& mv wp-cli.phar /usr/local/bin/wp
