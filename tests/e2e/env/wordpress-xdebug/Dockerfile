FROM wordpress

RUN pecl install xdebug \
	&& echo 'xdebug.remote_enable=1' >> $PHP_INI_DIR/php.ini \
	&& echo 'xdebug.remote_port=9000' >> $PHP_INI_DIR/php.ini \
	&& echo 'xdebug.remote_host=host.docker.internal' >> $PHP_INI_DIR/php.ini \
	&& echo 'xdebug.remote_autostart=1' >> $PHP_INI_DIR/php.ini \
	&& docker-php-ext-enable xdebug > /dev/null
