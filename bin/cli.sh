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

# Source .env.local if available for worktree-specific config
ENV_FILE_ARG=""
if [ -f ".env.local" ]; then
    ENV_FILE_ARG="--env-file .env.local"
fi

docker compose ${ENV_FILE_ARG} exec -u ${user} wordpress ${command}
