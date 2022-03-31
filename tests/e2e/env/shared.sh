#!/usr/bin/env bash

# Init variables and functions used in other scripts
cwd=$(pwd)
export WCP_ROOT=$cwd
export E2E_ROOT="$cwd/tests/e2e"
export WP_URL="localhost:8084"
export SERVER_PATH="$E2E_ROOT/deps/wcp-server"
export DEV_TOOLS_DIR="wcp-dev-tools"
export DEV_TOOLS_PATH="$E2E_ROOT/deps/$DEV_TOOLS_DIR"
export WP_CONTAINER="wcp_e2e_wordpress"

step() {
	echo
	echo "===> $1"
}

redirect_output() {
	if [[ "$DEBUG" = true ]]; then
        "$@"
    else
        "$@" > /dev/null
    fi
}

# --user xfs forces the wordpress:cli container to use a user with the same ID as the main wordpress container. See:
# https://hub.docker.com/_/wordpress#running-as-an-arbitrary-user
cli()
{
	redirect_output docker run -i --rm --user xfs --volumes-from "$WP_CONTAINER" --network container:"$WP_CONTAINER" wordpress:cli "$@"
}

# Function to log WP-CLI output without redirecting the output to /dev/null.
# Works even when the DEBUG flag is unset or set to false
cli_debug()
{
	docker run -i --rm --user xfs --volumes-from "$WP_CONTAINER" --network container:"$WP_CONTAINER" wordpress:cli "$@"
}
