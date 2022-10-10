#!/usr/bin/env bash

# Init variables and functions used in other scripts
cwd=$(pwd)
export WCP_ROOT=$cwd
export E2E_ROOT="$cwd/tests/e2e"
export WP_URL="localhost:8084"
export SERVER_PATH="$E2E_ROOT/deps/wcp-server"
export SERVER_CONTAINER="woocommerce_payments_server_wordpress_e2e"
export DEV_TOOLS_DIR="wcp-dev-tools"
export DEV_TOOLS_PATH="$E2E_ROOT/deps/$DEV_TOOLS_DIR"
export CLIENT_CONTAINER="wcp_e2e_wordpress"

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
	redirect_output docker run -i --rm --user 33:33 --env-file ${E2E_ROOT}/env/default.env --volumes-from "$CLIENT_CONTAINER" --network container:"$CLIENT_CONTAINER" wordpress:cli "$@"
}

# Function to log WP-CLI output without redirecting the output to /dev/null.
# Works even when the DEBUG flag is unset or set to false
cli_debug()
{
	docker run -i --rm --user 33:33 --env-file ${E2E_ROOT}/env/default.env --volumes-from "$CLIENT_CONTAINER" --network container:"$CLIENT_CONTAINER" wordpress:cli "$@"
}
