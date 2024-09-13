FROM wordpress:php8.1
ARG XDEBUG_MODE=coverage,debug
ARG XDEBUG_REMOTE_PORT
ARG XDEBUG_REMOTE_HOST=host.docker.internal
ARG XDEBUG_START_WITH_REQUEST=trigger
ARG XDEBUG_SHOW_ERROR_TRACE=0
ARG XDEBUG_SHOW_EXCEPTION_TRACE=0
RUN pecl install xdebug \
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
