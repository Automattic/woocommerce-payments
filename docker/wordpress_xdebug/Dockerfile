FROM wordpress
RUN pecl install xdebug \
	&& docker-php-ext-enable xdebug
RUN apt-get update \
	&& apt-get install --assume-yes --quiet --no-install-recommends gnupg2 subversion mariadb-client
COPY custom.ini $PHP_INI_DIR/conf.d/
