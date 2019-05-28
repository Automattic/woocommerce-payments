FROM wordpress
RUN pecl install xdebug-2.6.1 \
	&& docker-php-ext-enable xdebug
COPY custom.ini $PHP_INI_DIR/conf.d/