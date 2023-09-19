#!/usr/bin/env bash

first_arg=${1}
if [ "${first_arg}" = "--as-root" ]; then
	user=0
	command=${@:2}
else
	user=www-data
	command=${@:1}
fi

command=${command:-bash}

docker-compose exec -u ${user} wordpress ${command}
