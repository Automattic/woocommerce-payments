FROM wordpress
RUN pecl install xdebug \
	&& docker-php-ext-enable xdebug
COPY custom.ini $PHP_INI_DIR/conf.d/
