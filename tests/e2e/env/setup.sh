#!/usr/bin/env bash

set -e

. ./tests/e2e/env/shared.sh

# ─── Output helpers ───────────────────────────────────────────────────────────

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
BOLD='\033[1m'
DIM='\033[2m'
NC='\033[0m'

info()    { echo -e "  ${BLUE}${BOLD}info${NC}  $1"; }
success() { echo -e "  ${GREEN}${BOLD}  ok${NC}  $1"; }
warn()    { echo -e "  ${YELLOW}${BOLD}warn${NC}  $1"; }
fail()    { echo -e "  ${RED}${BOLD}fail${NC}  $1"; }

section() {
	echo ""
	echo -e "${BOLD}── $1 ──${NC}"
	echo ""
}

is_remote_git_repo() {
	local repo=$1

	[[ "$repo" =~ ^git@ ]] || [[ "$repo" =~ ^ssh:// ]] || [[ "$repo" =~ ^https?:// ]]
}

is_github_ssh_repo() {
	local repo=$1

	[[ "$repo" =~ ^git@github\.com: ]] || [[ "$repo" =~ ^ssh://git@github\.com/ ]]
}

is_local_git_repo() {
	local repo=$1

	git -C "$repo" rev-parse --is-inside-work-tree > /dev/null 2>&1
}

has_server_code() {
	local repo=$1

	local plugins_dir="$repo/server/wp-content/rest-api-plugins"

	[[ -d "$plugins_dir" ]] && find "$plugins_dir" -mindepth 1 -print -quit | grep -q .
}

get_sandbox_host() {
	local repo=$1
	local sandbox_host=""

	if [[ -f "$repo/.env" ]]; then
		sandbox_host=$(grep '^WCPAY_SANDBOX_HOST=' "$repo/.env" | tail -n 1 | cut -d= -f2-)
		sandbox_host=${sandbox_host#\'}
		sandbox_host=${sandbox_host%\'}
		sandbox_host=${sandbox_host#\"}
		sandbox_host=${sandbox_host%\"}
	fi

	echo "${sandbox_host:-wpcomsandbox}"
}

print_pull_failure_help() {
	local repo=$1
	local pull_output=$2
	local sandbox_host=""

	sandbox_host=$(get_sandbox_host "$repo")

	info "Bootstrap pull needs SSH access to the sandbox host '${sandbox_host}' and a working rsync binary."

	if grep -Eqi "rsync could not be found|Detected an rsync-compatible implementation" <<< "$pull_output"; then
		info "Fix: install the standard rsync binary and re-run setup. On macOS: brew install rsync"
	elif grep -Eqi "Could not resolve hostname|Name or service not known|No address associated with hostname" <<< "$pull_output"; then
		info "Fix: configure an SSH host named '${sandbox_host}' in ~/.ssh/config, or set WCPAY_SANDBOX_HOST in ${repo}/.env to a working sandbox alias."
	elif grep -Eqi "Permission denied|publickey" <<< "$pull_output"; then
		info "Fix: make sure your SSH key is loaded and that 'ssh ${sandbox_host} \"echo ok\"' succeeds before re-running setup."
	elif grep -Eqi "Connection timed out|Operation timed out|Connection refused" <<< "$pull_output"; then
		info "Fix: verify the sandbox host '${sandbox_host}' is reachable over SSH and that you are connected to the correct network or VPN."
	else
		info "Fix: run 'cd ${repo} && npm run pull -- -s' directly to verify your sandbox configuration, then re-run E2E setup."
	fi

	info "You can also prepare your local checkout first with 'cd $TRANSACT_PLATFORM_SERVER_REPO && npm run pull -- -s', then run E2E setup again."
}

sync_server_code_from_local_repo() {
	local source_repo=$1
	local destination_repo=$2

	for dir in server missioncontrol; do
		if [[ -d "$source_repo/$dir" ]]; then
			info "Syncing $dir/ from source repo..."
			rsync -a --delete "$source_repo/$dir/" "$destination_repo/$dir/"
			success "Synced $dir/"
		fi
	done
}

pull_server_code_into_repo() {
	local repo=$1
	local pull_output=""

	info "Pulling sandbox server code into E2E clone..."

	set +e
	pull_output=$(cd "$repo" && npm run pull -- -s 2>&1)
	local exit_code=$?
	set -e

	if [[ $exit_code -eq 0 ]] && has_server_code "$repo"; then
		success "Pulled sandbox server code into E2E clone"
		return 0
	fi

	fail "Failed to pull sandbox server code into E2E clone"
	echo ""
	echo "Pull output (last 20 lines):"
	printf '%s\n' "$pull_output" | tail -n 20
	print_pull_failure_help "$repo" "$pull_output"
	return 1
}

ensure_server_code_in_clone() {
	local source_repo=$1
	local destination_repo=$2

	if [[ -d "$source_repo" ]] && has_server_code "$source_repo"; then
		sync_server_code_from_local_repo "$source_repo" "$destination_repo"
	fi

	if has_server_code "$destination_repo"; then
		success "Server clone has sandbox-backed code"
		return 0
	fi

	pull_server_code_into_repo "$destination_repo"
}

check_remote_repo_access() {
	local repo=$1
	local branch=$2
	local label=$3

	if git ls-remote --exit-code --heads "$repo" "$branch" > /dev/null 2>&1; then
		success "$label is reachable"
		return 0
	fi

	fail "$label is not reachable"
	info "Repo: $repo"
	info "Branch: $branch"

	if is_github_ssh_repo "$repo"; then
		info "This URL uses GitHub SSH. Ensure your SSH key is loaded, or switch local.env to a local path or HTTPS URL."
	else
		info "Confirm the repo URL, branch name, and that your Git credentials allow access."
	fi

	return 1
}

clone_repo() {
	local repo=$1
	local branch=$2
	local destination=$3
	local label=$4

	if git clone --depth=1 --branch "$branch" "$repo" "$destination"; then
		success "$label cloned"
		return 0
	fi

	fail "Failed to clone $label"
	info "Repo: $repo"
	info "Branch: $branch"

	if is_github_ssh_repo "$repo"; then
		info "This URL uses GitHub SSH. Ensure your SSH key is loaded, or switch local.env to a local path or HTTPS URL."
	fi

	exit 1
}

link_server_account() {
	local blog_id=$1
	local stripe_account_id=$2
	local link_output=""

	set +e
	link_output=$("$SERVER_PATH"/local/bin/link-account.sh "$blog_id" "$stripe_account_id" test 1 1 2>&1)
	local exit_code=$?
	set -e

	if [[ $exit_code -eq 0 ]]; then
		echo "$link_output"
		return 0
	fi

	if [[ "$link_output" == *"the Stripe account ID is already linked to the blog"* ]]; then
		warn "Stripe account is already linked to the blog; skipping relink"
		return 0
	fi

	echo "$link_output"
	return $exit_code
}

# ─── Preflight checks ────────────────────────────────────────────────────────
# Catch common problems before spending minutes on Docker setup.

section "Preflight checks"

PREFLIGHT_OK=true

# jq
if command -v jq &> /dev/null; then
	success "jq is installed"
else
	fail "jq is not installed"
	info "Install it: brew install jq (macOS) or sudo apt install jq (Linux)"
	PREFLIGHT_OK=false
fi

# Docker
if command -v docker &> /dev/null; then
	if docker info &> /dev/null; then
		success "Docker is installed and running"
	else
		fail "Docker is installed but the daemon is not running"
		info "Start Docker Desktop or run: sudo systemctl start docker"
		PREFLIGHT_OK=false
	fi
else
	fail "Docker is not installed"
	info "Install Docker Desktop from https://docs.docker.com/get-docker/"
	PREFLIGHT_OK=false
fi

# docker compose
if docker compose version &> /dev/null; then
	success "docker compose is available"
else
	fail "docker compose is not available"
	info "Docker Compose V2 is required. Update Docker Desktop or install the compose plugin."
	PREFLIGHT_OK=false
fi

# Node.js
if command -v node &> /dev/null; then
	NODE_CURRENT=$(node -v | sed 's/v//')
	NODE_EXPECTED=$(cat .nvmrc 2>/dev/null || echo "unknown")
	NODE_MAJOR_CURRENT=$(echo "$NODE_CURRENT" | cut -d. -f1)
	NODE_MAJOR_EXPECTED=$(echo "$NODE_EXPECTED" | cut -d. -f1)
	if [[ "$NODE_MAJOR_CURRENT" == "$NODE_MAJOR_EXPECTED" ]]; then
		success "Node.js v${NODE_CURRENT} (expected ${NODE_EXPECTED})"
	else
		warn "Node.js v${NODE_CURRENT} (expected ${NODE_EXPECTED} from .nvmrc)"
		info "Run: nvm use"
	fi
else
	fail "Node.js is not installed"
	info "Install via nvm: nvm install"
	PREFLIGHT_OK=false
fi

# composer
if command -v composer &> /dev/null; then
	success "Composer is installed"
else
	fail "Composer is not installed"
	info "Install from https://getcomposer.org/download/"
	PREFLIGHT_OK=false
fi

# node_modules
if [[ -d "node_modules" ]]; then
	success "node_modules exists"
else
	fail "node_modules is missing"
	info "Run: npm install"
	PREFLIGHT_OK=false
fi

# vendor
if [[ -f "vendor/autoload.php" ]]; then
	success "vendor dependencies installed"
else
	fail "vendor dependencies are missing"
	info "Run: composer install"
	PREFLIGHT_OK=false
fi

# local.env (not required in CI — env vars come from GitHub secrets)
if [[ -n "$CI" ]]; then
	success "Running in CI (local.env not required)"
elif [[ -f "$E2E_ROOT/config/local.env" ]]; then
	success "local.env exists"
else
	fail "local.env is missing"
	info "Run: bin/setup-e2e-local.sh"
	info "Or copy tests/e2e/config/.env.example and fill in your values."
	PREFLIGHT_OK=false
fi

# Load local.env early so we can validate server config below.
if [[ -f "$E2E_ROOT/config/local.env" ]]; then
	. "$E2E_ROOT/config/local.env"
fi

# Dev tools repo
if [[ -z "$WCP_DEV_TOOLS_REPO" ]]; then
	fail "WCP_DEV_TOOLS_REPO is not set in local.env"
	PREFLIGHT_OK=false
elif [[ -d "$WCP_DEV_TOOLS_REPO" ]]; then
	if is_local_git_repo "$WCP_DEV_TOOLS_REPO"; then
		success "Dev tools repo exists"
	else
		fail "Dev tools repo path is not a Git checkout"
		info "Path: $WCP_DEV_TOOLS_REPO"
		info "Use a local checkout or a reachable Git URL."
		PREFLIGHT_OK=false
	fi
elif check_remote_repo_access "$WCP_DEV_TOOLS_REPO" "${WCP_DEV_TOOLS_BRANCH-trunk}" "Dev tools repo"; then
	:
else
	PREFLIGHT_OK=false
fi

# Transact Platform Server (local mode only)
if [[ "$E2E_USE_LOCAL_SERVER" != false && -z "$CI" ]]; then
	if [[ -z "$TRANSACT_PLATFORM_SERVER_REPO" ]]; then
		fail "TRANSACT_PLATFORM_SERVER_REPO is not set in local.env"
		PREFLIGHT_OK=false
	else
		# Resolve the repo path (could be a local path or git URL)
		if [[ -d "$TRANSACT_PLATFORM_SERVER_REPO" ]]; then
			if ! is_local_git_repo "$TRANSACT_PLATFORM_SERVER_REPO"; then
				fail "Transact server repo path is not a Git checkout"
				info "Path: $TRANSACT_PLATFORM_SERVER_REPO"
				info "Use a local checkout or a reachable Git URL."
				PREFLIGHT_OK=false
			elif has_server_code "$TRANSACT_PLATFORM_SERVER_REPO"; then
				success "Transact server repo has server code"
			else
				warn "Transact server repo is missing sandbox-backed server code"
				info "Setup will try a one-shot 'npm run pull -- -s' in the E2E clone."
			fi
		elif is_remote_git_repo "$TRANSACT_PLATFORM_SERVER_REPO"; then
			if ! check_remote_repo_access "$TRANSACT_PLATFORM_SERVER_REPO" "${TRANSACT_PLATFORM_SERVER_BRANCH-trunk}" "Transact server repo"; then
				PREFLIGHT_OK=false
			else
				info "Setup will pull sandbox-backed server code into the E2E clone after cloning."
			fi
		else
			fail "TRANSACT_PLATFORM_SERVER_REPO does not exist locally"
			info "Use a local transact-platform-server checkout or a reachable Git URL."
			PREFLIGHT_OK=false
		fi
	fi
fi

if [[ "$PREFLIGHT_OK" != true ]]; then
	echo ""
	fail "Preflight checks failed. Fix the issues above and re-run."
	exit 1
fi

# ─── Build client if needed ──────────────────────────────────────────────────
# Skip in CI where builds are handled separately or via artifact.

if [[ -z "$CI" && "$WCPAY_USE_BUILD_ARTIFACT" != true ]]; then
	BUILD_NEEDED=false

	if [[ ! -d "dist" || -z "$(ls -A dist/ 2>/dev/null)" ]]; then
		BUILD_NEEDED=true
		BUILD_REASON="dist/ is empty or missing"
	else
		# Rebuild if any client source file is newer than the oldest dist output.
		DIST_TIME=$(find dist -type f -print0 2>/dev/null | xargs -0 stat -f '%m' 2>/dev/null | sort -n | head -1)
		CLIENT_TIME=$(find client -type f -newer dist/checkout.js -print -quit 2>/dev/null)
		if [[ -n "$CLIENT_TIME" ]]; then
			BUILD_NEEDED=true
			BUILD_REASON="client/ has changes newer than dist/"
		fi
	fi

	if [[ "$BUILD_NEEDED" == true ]]; then
		section "Building client"
		info "$BUILD_REASON — running npm run build:client"
		npm run build:client
		success "Client built"
	else
		success "Client build is up to date"
	fi
fi

# Function to handle permissions in a cross-platform way
handle_permissions() {
    local path=$1
    if [[ "$(uname)" == "Darwin" ]]; then
        chmod -R 755 "$path"
    else
        if ! sudo chown www-data:www-data -R "$path"; then
            fail "Failed to set permissions on $path"
            exit 1
        fi
    fi
}

# Variables
BLOG_ID=${E2E_JP_SITE_ID-111}
WC_GUEST_EMAIL=$(<"$USERS_CONFIG_JSON_PATH" jq -r '.users.guest.email')
WC_CUSTOMER_EMAIL=$(<"$USERS_CONFIG_JSON_PATH" jq -r '.users.customer.email')
WC_CUSTOMER_USERNAME=$(<"$USERS_CONFIG_JSON_PATH" jq -r '.users.customer.username')
WC_CUSTOMER_PASSWORD=$(<"$USERS_CONFIG_JSON_PATH" jq -r '.users.customer.password')
WP_ADMIN=$(<"$USERS_CONFIG_JSON_PATH" jq -r '.users.admin.username')
WP_ADMIN_PASSWORD=$(<"$USERS_CONFIG_JSON_PATH" jq -r '.users.admin.password')
WP_ADMIN_EMAIL=$(<"$USERS_CONFIG_JSON_PATH" jq -r '.users.admin.email')
WP_EDITOR=$(<"$USERS_CONFIG_JSON_PATH" jq -r '.users.editor.username')
WP_EDITOR_PASSWORD=$(<"$USERS_CONFIG_JSON_PATH" jq -r '.users.editor.password')
WP_EDITOR_EMAIL=$(<"$USERS_CONFIG_JSON_PATH" jq -r '.users.editor.email')
SITE_TITLE="WooPayments E2E site"
SITE_URL=$WP_URL

if [[ $FORCE_E2E_DEPS_SETUP ]]; then
	sudo rm -rf tests/e2e/deps
fi

# ─── Transact Platform Server ────────────────────────────────────────────────
# Only if E2E_USE_LOCAL_SERVER is present & equals to true.

if [[ "$E2E_USE_LOCAL_SERVER" != false ]]; then
	section "Transact Platform Server"

	if [[ ! -d "$SERVER_PATH" ]]; then
		info "Cloning server (branch ${TRANSACT_PLATFORM_SERVER_BRANCH-trunk})..."

		if [[ -z $TRANSACT_PLATFORM_SERVER_REPO ]]; then
			fail "TRANSACT_PLATFORM_SERVER_REPO is not set in local.env"
			exit 1;
		fi

		rm -rf "$SERVER_PATH"
		clone_repo "$TRANSACT_PLATFORM_SERVER_REPO" "${TRANSACT_PLATFORM_SERVER_BRANCH-trunk}" "$SERVER_PATH" "Server"
	else
		success "Using cached server at ${SERVER_PATH}"
	fi

	ensure_server_code_in_clone "$TRANSACT_PLATFORM_SERVER_REPO" "$SERVER_PATH"

	cd "$SERVER_PATH"

	info "Creating server secrets..."
	SECRETS="<?php
	define( 'WCPAY_STRIPE_TEST_PUBLIC_KEY', '$E2E_WCPAY_STRIPE_TEST_PUBLIC_KEY' );
	define( 'WCPAY_STRIPE_TEST_SECRET_KEY', '$E2E_WCPAY_STRIPE_TEST_SECRET_KEY' );
	define( 'WCPAY_STRIPE_TEST_WEBHOOK_SIGNATURE_KEY', '$E2E_WCPAY_STRIPE_TEST_WEBHOOK_SIGNATURE_KEY' );
	define( 'WCPAY_STRIPE_LIVE_PUBLIC_KEY', 'pk_live_XXXXXXX' );
	define( 'WCPAY_STRIPE_LIVE_SECRET_KEY', 'sk_live_XXXXXXX' );
	define( 'WCPAY_ONBOARDING_ENCRYPT_KEY', str_repeat( 'a', SODIUM_CRYPTO_SECRETBOX_KEYBYTES ) );
	define( 'WOOPAY_URL', 'https://pay.woo.com' );
	define( 'WOOPAY_BLOG_ID', '$E2E_WOOPAY_BLOG_ID' );
	"
	printf "$SECRETS" > "local/secrets.php"
	success "Secrets created"

	info "Starting server containers..."
	redirect_output docker compose -f docker-compose.yml -f docker-compose.e2e.yml up --build --force-recreate -d

	WP_LISTEN_PORT=$(docker ps | grep "$SERVER_CONTAINER" | sed -En "s/.*0:([0-9]+).*/\1/p")
	success "Server listening on port ${WP_LISTEN_PORT}"

	if [[ -n $CI ]]; then
		handle_permissions "$SERVER_PATH/docker/wordpress"
		touch "$SERVER_PATH/logstash.log"
		handle_permissions "$SERVER_PATH/logstash.log"
	fi

	info "Running server setup..."
	"$SERVER_PATH"/local/bin/docker-setup.sh
	success "Server setup complete"

	info "Linking Stripe account..."
	link_server_account "$BLOG_ID" "$E2E_WCPAY_STRIPE_ACCOUNT_ID"
	success "Stripe account is ready"

	info "Configuring account flags..."
	"$SERVER_PATH"/local/bin/setup-account-metas.sh "$BLOG_ID"
	success "Account flags configured"

	if [[ -n $CI ]]; then
		info "Disabling Xdebug on server container..."
		docker exec "$SERVER_CONTAINER" \
		sh -c 'echo "#zend_extension=xdebug" > /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini && echo "Xdebug disabled."'
	fi
fi

cd "$cwd"

# ─── Dev Tools ────────────────────────────────────────────────────────────────

section "Dev Tools"

if [[ ! -d "$DEV_TOOLS_PATH" ]]; then
	if [[ -z $WCP_DEV_TOOLS_REPO ]]; then
		fail "WCP_DEV_TOOLS_REPO is not set in local.env"
		exit 1;
	fi

	info "Cloning dev tools..."
	rm -rf "$DEV_TOOLS_PATH"
	clone_repo "$WCP_DEV_TOOLS_REPO" "${WCP_DEV_TOOLS_BRANCH-trunk}" "$DEV_TOOLS_PATH" "Dev tools"
else
	success "Dev tools already present"
fi

if [[ -d "$DEV_TOOLS_PATH" && ! -f "$DEV_TOOLS_PATH/vendor/autoload.php" ]]; then
	info "Installing dev tools dependencies..."
	composer install --no-dev --no-interaction --working-dir="$DEV_TOOLS_PATH"
	success "Dev tools dependencies installed"
fi

# ─── Client containers ───────────────────────────────────────────────────────

section "WordPress client"

info "Starting containers..."
redirect_output docker compose -f "$E2E_ROOT"/env/docker-compose.yml up --build --force-recreate -d wordpress
if [[ -z $CI ]]; then
	docker compose -f "$E2E_ROOT"/env/docker-compose.yml up --build --force-recreate -d phpMyAdmin
fi
success "Containers started"

if [[ -n $CI ]]; then
	info "Disabling Xdebug on client container..."
	docker exec "$CLIENT_CONTAINER" \
	sh -c 'echo "#zend_extension=xdebug" > /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini && echo "Xdebug disabled."'
fi

# Wait for database
info "Waiting for database..."
set +e
cli wp db check --skip_ssl --path=/var/www/html --quiet > /dev/null
while [[ $? -ne 0 ]]; do
	sleep 5
	cli wp db check --skip_ssl --path=/var/www/html --quiet > /dev/null
done
set -e
success "Database is ready"

if [[ -n $CI ]]; then
	handle_permissions "$E2E_ROOT/docker/wordpress/wp-content"
	redirect_output ls -al "$E2E_ROOT"/docker/wordpress
fi

# ─── WordPress setup ─────────────────────────────────────────────────────────

section "WordPress setup"

info "Pulling WordPress CLI image..."
docker pull wordpress:cli > /dev/null

info "Installing WordPress core..."
cli wp core install \
	--path=/var/www/html \
	--url="$SITE_URL" \
	--title="$SITE_TITLE" \
	--admin_name="${WP_ADMIN-admin}" \
	--admin_password="${WP_ADMIN_PASSWORD-password}" \
	--admin_email="${WP_ADMIN_EMAIL-admin@example.com}" \
	--skip-email

if [[ -n "$E2E_WP_VERSION" && "$E2E_WP_VERSION" != "latest" ]]; then
	info "Installing WordPress ${E2E_WP_VERSION}..."
	cli wp core update --version="$E2E_WP_VERSION" --force --quiet
else
	info "Updating WordPress to latest..."
	cli wp core update --quiet
fi

cli wp core update-db --quiet

# Keep notices out of rendered pages even when verbose setup output is enabled.
cli wp config set WP_DEBUG_DISPLAY false --raw
cli wp config set WP_DEBUG_LOG true --raw

cli wp config set DISABLE_JETPACK_ACCOUNT_PROTECTION true --raw

info "Configuring permalinks..."
cli wp rewrite structure '/%postname%/'
cli wp rewrite flush --hard

success "WordPress installed"

# ─── WooCommerce ──────────────────────────────────────────────────────────────

section "WooCommerce"

info "Installing WordPress Importer..."
cli wp plugin install wordpress-importer --activate

if [[ -n "$E2E_WC_VERSION" && $E2E_WC_VERSION != 'latest' ]]; then
	info "Installing WooCommerce ${E2E_WC_VERSION}..."
	cli wp plugin install woocommerce --version="$E2E_WC_VERSION" --activate
else
	info "Installing latest WooCommerce..."
	cli wp plugin install woocommerce --activate
fi

info "Installing REST API auth plugin..."
cli wp plugin install https://github.com/WP-API/Basic-Auth/archive/master.zip --activate --force

info "Installing themes..."
cli wp theme install storefront --activate
cli wp theme install twentytwentyfour

info "Configuring WooCommerce settings..."
cli wp option set woocommerce_store_address "60 29th Street"
cli wp option set woocommerce_store_address_2 "#343"
cli wp option set woocommerce_store_city "San Francisco"
cli wp option set woocommerce_default_country "US:CA"
cli wp option set woocommerce_store_postcode "94110"
cli wp option set woocommerce_currency "USD"
cli wp option set woocommerce_product_type "both"
cli wp option set woocommerce_allow_tracking "no"
cli wp option set woocommerce_enable_signup_and_login_from_checkout "yes"
cli wp option set woocommerce_onboarding_profile --format=json '{"skipped":true}'
cli wp option set woocommerce_coming_soon "no"
cli wp option set woocommerce_checkout_company_field "optional"

info "Importing shop pages..."
cli wp wc --user=admin tool run install_pages

INSTALLED_WC_VERSION=$(cli_debug wp plugin get woocommerce --field=version)

# Workaround for WC > 8.3: use shortcode-based cart & checkout pages.
IS_WORKAROUND_REQUIRED=$(cli_debug wp eval "echo version_compare(\"$INSTALLED_WC_VERSION\", \"8.3\", \">=\");")

if [[ "$IS_WORKAROUND_REQUIRED" = "1" ]]; then
	info "Setting up shortcode checkout pages (WC > 8.3)..."
	CART_PAGE_ID=$(cli_debug wp option get woocommerce_cart_page_id)
	CHECKOUT_PAGE_ID=$(cli_debug wp option get woocommerce_checkout_page_id)

	CART_SHORTCODE="<!-- wp:shortcode -->[woocommerce_cart]<!-- /wp:shortcode -->"
	CHECKOUT_SHORTCODE="<!-- wp:shortcode -->[woocommerce_checkout]<!-- /wp:shortcode -->"

	cli wp post create --from-post="$CHECKOUT_PAGE_ID" --post_type="page" --post_title="Checkout WCB" --post_status="publish" --post_name="checkout-wcb"
	CHECKOUT_WCB_PAGE_ID=$(cli_debug wp post url-to-id checkout-wcb)

	cli wp post update "$CART_PAGE_ID" --post_content="$CART_SHORTCODE"
	cli wp post update "$CHECKOUT_PAGE_ID" --post_content="$CHECKOUT_SHORTCODE"
	cli wp post meta update "$CHECKOUT_PAGE_ID" _wp_page_template "template-fullwidth.php"
	cli wp post meta update "$CHECKOUT_WCB_PAGE_ID" _wp_page_template "template-fullwidth.php"
fi

info "Importing sample data..."
cli wp import wp-content/plugins/woocommerce/sample-data/sample_products.xml --authors=skip

success "WooCommerce configured (v${INSTALLED_WC_VERSION})"

# ─── User accounts ───────────────────────────────────────────────────────────

section "User accounts"

info "Setting up test accounts..."
cli wp user delete "$WC_CUSTOMER_USERNAME" --yes 2>/dev/null || true
cli wp user delete "$WC_CUSTOMER_EMAIL" --yes 2>/dev/null || true
cli wp user delete "$WC_GUEST_EMAIL" --yes 2>/dev/null || true
cli wp user delete "$WP_EDITOR" --yes 2>/dev/null || true
cli wp user delete "$WP_EDITOR_EMAIL" --yes 2>/dev/null || true
cli wp user create "$WC_CUSTOMER_USERNAME" "$WC_CUSTOMER_EMAIL" --role=customer --user_pass="$WC_CUSTOMER_PASSWORD"
cli wp user create "$WP_EDITOR" "$WP_EDITOR_EMAIL" --role=editor --user_pass="$WP_EDITOR_PASSWORD"

success "Test accounts created (admin, customer, editor)"

# ─── WooPayments ──────────────────────────────────────────────────────────────

section "WooPayments"

if [[ "$WCPAY_USE_BUILD_ARTIFACT" = true ]]; then
	info "Installing from build artifact..."
	mv "$WCPAY_ARTIFACT_DIRECTORY"/woocommerce-payments "$WCPAY_ARTIFACT_DIRECTORY"/woocommerce-payments-build
    cd "$WCPAY_ARTIFACT_DIRECTORY" && zip -r "$cwd"/woocommerce-payments-build.zip . && cd "$cwd"
	cli wp plugin install wp-content/plugins/woocommerce-payments/woocommerce-payments-build.zip --activate
else
	info "Activating WooPayments plugin..."
	cli wp plugin activate woocommerce-payments
fi

cli wp option set woocommerce_woocommerce_payments_settings --format=json '{"enabled":"yes"}'

info "Activating dev tools..."
cli wp plugin activate "$DEV_TOOLS_DIR"
cli wp option set wcpaydev_proxy 0

if [[ "$E2E_USE_LOCAL_SERVER" != false ]]; then
	info "Connecting to local server..."
	if [[ -n $CI ]]; then
		DOCKER_HOST=$(ip -4 addr show docker0 | grep -Po 'inet \K[\d.]+')
	fi
	cli wp wcpay_dev redirect_to "http://${DOCKER_HOST-host.docker.internal}:${WP_LISTEN_PORT}/wp-json/"
	cli wp wcpay_dev set_blog_id "$BLOG_ID"
	cli wp wcpay_dev refresh_account_data
	success "Connected to local Transact server"
else
	info "Connecting to live server..."
	cli wp wcpay_dev set_blog_id "$BLOG_ID" --blog_token="$E2E_JP_BLOG_TOKEN" --user_token="$E2E_JP_USER_TOKEN"
	success "Connected to live server"
fi

# ─── Optional plugins ────────────────────────────────────────────────────────

if [[ ! ${SKIP_WC_SUBSCRIPTIONS_TESTS} ]]; then
	section "WooCommerce Subscriptions"

	info "Installing latest release..."
	cd "$E2E_ROOT"/deps

	LATEST_RELEASE_ASSET_ID=$(curl -H "Authorization: token $E2E_GH_TOKEN" https://api.github.com/repos/"$WC_SUBSCRIPTIONS_REPO"/releases/latest | jq -r '.assets[0].id')

	curl -LJ \
		-H "Authorization: token $E2E_GH_TOKEN" \
		-H "Accept: application/octet-stream" \
		--output woocommerce-subscriptions.zip \
		https://api.github.com/repos/"$WC_SUBSCRIPTIONS_REPO"/releases/assets/"$LATEST_RELEASE_ASSET_ID"

	unzip -qq woocommerce-subscriptions.zip -d woocommerce-subscriptions-source

	sudo mv woocommerce-subscriptions-source/woocommerce-subscriptions/* woocommerce-subscriptions
	cli wp plugin activate woocommerce-subscriptions
	rm -rf woocommerce-subscriptions-source

	info "Importing subscription products..."
	cli wp import wp-content/plugins/woocommerce-payments/tests/e2e/env/wc-subscription-products.xml --authors=skip

	success "WooCommerce Subscriptions installed"
fi

if [[ ! ${SKIP_WC_ACTION_SCHEDULER_TESTS} ]]; then
	info "Installing Action Scheduler..."
	cli wp plugin install action-scheduler --activate
	success "Action Scheduler installed"
fi

# ─── Final configuration ─────────────────────────────────────────────────────

section "Final configuration"

info "Configuring test settings..."
cli wp option set woocommerce_orders_report_date_tour_shown yes
mkdir -p $WCP_ROOT/screenshots
handle_permissions $WCP_ROOT/screenshots
cli wp option set wcpay_session_rate_limiter_disabled_wcpay_card_declined_registry yes
cli wp option set wcpay_fraud_protection_welcome_tour_dismissed 1

info "Setting up test coupon..."
cli wp db query "DELETE p, m FROM wp_posts p LEFT JOIN wp_postmeta m ON p.ID = m.post_id WHERE p.post_type = 'shop_coupon'"
cli wp wc --user=admin shop_coupon create --code=free --amount=100 --discount_type=percent --individual_use=true --free_shipping=true

IS_HPOS_AVAILABLE=$(cli_debug wp eval "echo version_compare(\"$INSTALLED_WC_VERSION\", \"8.2\", \">=\");")
if [[ ${IS_HPOS_AVAILABLE} ]]; then
	info "Syncing HPOS data..."
	cli wp wc hpos sync
else
	info "Syncing COT data..."
	cli wp wc cot sync
fi

success "Configuration complete"

# ─── Summary ─────────────────────────────────────────────────────────────────

section "Setup complete"

echo -e "  ${DIM}WordPress${NC}      $(cli_debug wp core version)"
echo -e "  ${DIM}WooCommerce${NC}    $(cli_debug wp plugin get woocommerce --field=version)"
if [[ ! ${SKIP_WC_SUBSCRIPTIONS_TESTS} ]]; then
	echo -e "  ${DIM}Subscriptions${NC}  $(cli_debug wp plugin get woocommerce-subscriptions --field=version)"
fi
echo ""
echo -e "  ${GREEN}${BOLD}Site ready${NC}  http://${WP_URL}/wp-admin/"
if [[ -z $CI ]]; then
	echo -e "  ${DIM}phpMyAdmin${NC}  http://localhost:8085"
fi
echo ""
echo -e "  Run tests:  ${BOLD}npm run test:e2e${NC}"
echo -e "  UI mode:    ${BOLD}npm run test:e2e-ui${NC}"
echo ""
