#!/usr/bin/env sh

set -e

cwd=$(pwd)
export WCP_ROOT=$cwd
export E2E_ROOT="$cwd/tests/e2e"
export WP_URL="localhost:8084"

step() {
	echo
	echo "===> $1"
}

if [[ -f "local.env" ]]; then
	echo "Loading local env variables"
	. ./local.env
fi

SERVER_PATH="$E2E_ROOT/deps/wcp_server"
if [[ $FORCE_E2E_SERVER_SETUP || ! -d $SERVER_PATH ]]; then
	step "Fetching server"
	echo $FORCE_E2E_SERVER_SETUP

	if [[ -z $WCP_SERVER_REPO ]]; then
		echo "WCP_SERVER_REPO env variable is not defined"
		exit 1;
	fi

	rm -rf $SERVER_PATH
	git clone --depth=1 $WCP_SERVER_REPO $SERVER_PATH
else
	echo "Using cached server at ${SERVER_PATH}"
fi

step "Starting server containers"
cd $SERVER_PATH
local/bin/start.sh
cd $cwd

step "Starting client containers"
docker-compose -f "$E2E_ROOT/env/docker-compose.yml" up -d

echo
step "Setting up client site"
# Need to use those credentials to comply with @woocommerce/e2e-environment
export WP_ADMIN=admin
export WP_ADMIN_PASSWORD=password
. bin/docker-setup.sh wcp_e2e_wordpress

# TODO: Map client and server to workaround jetpack setup

# TODO: Connect WCP Account

step "Creating ready page"
cli wp post create --post_type=page --post_status=publish --post_title='Ready' --post_content='E2E-tests.'

echo
step "Client site is up and running at http://${WP_URL}/wp-admin/"
