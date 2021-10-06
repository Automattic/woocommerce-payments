#!/usr/bin/env bash

set -e

XDEBUG_OVERRIDE_FILE=/usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini

if test -f "$XDEBUG_OVERRIDE_FILE"; then

	# Get the status from the override file.
	XDEBUG_STATUS_CONTENTS=$(cat "$XDEBUG_OVERRIDE_FILE")
	XDEBUG_STATUS=${XDEBUG_STATUS_CONTENTS:0:1}

	if [[ "$XDEBUG_STATUS" == '#' ]]; then
		echo 'Enabling XDebug...';
		echo 'zend_extension=xdebug' > $XDEBUG_OVERRIDE_FILE
	else
		echo 'Disabling XDebug...';
		echo '# zend_extension=xdebug' > $XDEBUG_OVERRIDE_FILE
	fi
else
	# Disable it, it's currently enabled.
	echo 'Disabling XDebug...';
	echo '# zend_extension=xdebug' > $XDEBUG_OVERRIDE_FILE
fi

# Restart the apache service.
service apache2 force-reload
