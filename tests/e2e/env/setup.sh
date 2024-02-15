#!/usr/bin/env bash

# Import helper functions.
# shellcheck disable=SC1091
source "$(pwd)/tests/e2e/env/helpers.sh"

# Load shared & local env variables.
source "$(pwd)/tests/e2e/env/shared.sh"
if [[ -z $CI ]]; then
	if [ -f "$E2E_ROOT/config/local.env" ]; then
		# shellcheck disable=SC1091
		source "$E2E_ROOT/config/local.env"
	fi
fi

### Load E2E setup dependencies
function check_setup_problems() {
	log_block "Checking for problems with setup"

	# Define array to store config errors
	local dependency_logs="\nMissing dependencies:"
	local server_logs="\nMissing server variables:"
	local client_logs="\nMissing client variables:"
	local other_logs="\nOther missing variables:"

	error_count=0

	# Helper function to add logs to the logs array.
	add_log() {
		local log_name=$1
		local log_message=$2

		# Using || as special character to split logs later.
		printf -v "$log_name" "%s|%s" "${!log_name}" "$log_message"
	}

	# Helper function to check if a variable is set.
	# If not, add it to the logs array under specified key.
	check_vars() {
		local log_name=$1
		shift
		local variables=("${@}")

		for var in "${variables[@]}"; do
			if [[ -z ${!var} ]]; then
				add_log "$log_name" "$(colorize_yellow "- $var")"
				((error_count++))
			fi
		done
	}

	# Define required server variables.
	local server_vars=(
		'E2E_USE_LOCAL_SERVER'
		'E2E_BLOG_ID'
		'E2E_WCPAY_STRIPE_ACCOUNT_ID'
	)

	# Define conditional server variables.
	if [[ "$E2E_USE_LOCAL_SERVER" = true ]]; then
		server_vars+=('WCP_SERVER_REPO')
		server_vars+=('WCP_SERVER_BRANCH')
		server_vars+=('E2E_WCPAY_STRIPE_TEST_PUBLIC_KEY')
		server_vars+=('E2E_WCPAY_STRIPE_TEST_SECRET_KEY')
	elif [[ "$E2E_USE_LOCAL_SERVER" = false ]]; then
		server_vars+=('E2E_BLOG_TOKEN')
		server_vars+=('E2E_USER_TOKEN')
	fi

	# Define required client variables.
	local client_vars=(
		'WCP_DEV_TOOLS_REPO'
		'WCP_DEV_TOOLS_BRANCH'
		'E2E_WP_VERSION'
		'E2E_WC_VERSION'
		'SKIP_WC_SUBSCRIPTIONS_TESTS'
		'SKIP_WC_ACTION_SCHEDULER_TESTS'
		'SKIP_WC_BLOCKS_TESTS'
	)

	# Define other required variables.
	local other_vars=(
		'E2E_GH_TOKEN'
		'FORCE_E2E_DEPS_SETUP'
	)

	# Check if Docker is running.
	if ! docker info >/dev/null 2>&1; then
		add_log "dependency_logs" "$(colorize_yellow '- Docker service is not running.')"
		((error_count++))
	fi

	# Check for script dependencies.
	if ! command -v jq &> /dev/null; then
		add_log "dependency_logs" "$(colorize_yellow '- jq library is missing.')"
		((error_count++))
	fi

	# Loop through server variables and check if they are set.
	check_vars "server_logs" "${server_vars[@]}"
	check_vars "client_logs" "${client_vars[@]}"
	check_vars "other_logs" "${other_vars[@]}"

	# Log to terminal if any errors are present and exit.
	if [[ $error_count -gt 0 ]]; then
		log_error "The following errors were found during setup dependencies check. Please fix them and try again."
		log_message "Note: The list below may be incomplete. To confirm, fix the ones below and re-run the setup."

		local logs_to_parse=("dependency_logs" "server_logs" "client_logs" "other_logs")
		for logs in "${logs_to_parse[@]}"; do
			local log_data="${!logs}"
			IFS='|' read -ra errors <<< "$log_data"
			if [ "${#errors[@]}" -gt 1 ]; then
				for error in "${errors[@]}"; do
					log_message "$error"
				done
			fi
		done

		log_message "\nFor more on E2E setup, refer to https://github.com/Automattic/woocommerce-payments/tree/develop/tests/e2e."
		exit 1
	fi

	log_success "No problems found.\n"
}

function cleanup_dependencies() {
	if [[ "$FORCE_E2E_DEPS_SETUP" == true ]]; then
		log_block "Remove existing dependencies"
		sudo rm -rf tests/e2e/deps && log_success "All dependencies removed."
	fi
}

function server_setup() {
	log_block "Setting up WooPayments server"

	if [[ "$E2E_USE_LOCAL_SERVER" = true ]]; then
		# Clone or update server repository.
		install_update_dependencies "$SERVER_CONTAINER"

		# Add server secrets
		log_step "Adding server secrets file"
		SECRETS="<?php
		define( 'WCPAY_STRIPE_TEST_PUBLIC_KEY', '$E2E_WCPAY_STRIPE_TEST_PUBLIC_KEY' );
		define( 'WCPAY_STRIPE_TEST_SECRET_KEY', '$E2E_WCPAY_STRIPE_TEST_SECRET_KEY' );
		define( 'WCPAY_STRIPE_TEST_WEBHOOK_SIGNATURE_KEY', 'wh_test_XXXXXXX' );
		define( 'WCPAY_STRIPE_LIVE_PUBLIC_KEY', 'pk_live_XXXXXXX' );
		define( 'WCPAY_STRIPE_LIVE_SECRET_KEY', 'sk_live_XXXXXXX' );
		define( 'WCPAY_ONBOARDING_ENCRYPT_KEY', str_repeat( 'a', SODIUM_CRYPTO_SECRETBOX_KEYBYTES ) );
		"
		echo -n "$SECRETS" > "$SERVER_PATH/local/secrets.php" && log_success "Server secrets file added."

		# Spin up server containers.
		log_step "Spinning up server containers"
		cd "$SERVER_PATH" || exit 1
		docker-compose -f docker-compose.yml -f docker-compose.e2e.yml up --quiet-pull --build --force-recreate -d wordpress && log_success "WordPress container spun up successfully."
		if [[ -z $CI ]]; then
			docker-compose -f "$E2E_ROOT"/env/docker-compose.yml up --quiet-pull --build --force-recreate -d phpMyAdmin && log_success "phpMyAdmin container spun up successfully."
		fi

		# Set folder permissions if running inside GitHub actions.
		if [[ -n $CI ]]; then
			log_step "Updating docker folder permissions"
			sudo chown www-data:www-data -R ./docker/wordpress && log_success "Folder permissions updated successfully."
		fi

		# Configure server containers.
		log_step "Configuring server containers"
		./local/bin/docker-setup.sh && log_success "Server containers configured successfully."

		# Configure WooPayments server with a stripe account.
		log_step "Configuring server with stripe account"
		./local/bin/link-account.sh "$E2E_BLOG_ID" "$E2E_WCPAY_STRIPE_ACCOUNT_ID" test 1 1 && log_success "Server configured with stripe account successfully."

		# Disable xDebug on GitHub actions.
		if [[ -n $CI ]]; then
			log_step "Disabling xDebug on WooPayments server container"
			server_cli mv /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini.disabled && log_success "xDebug disabled successfully."

			log_step "Restarting webserver on WooPayments sever container"
			server_cli /etc/init.d/apache2 reload && log_success "Webserver restarted successfully."
		fi
	fi

	# Change directory to project root.
	cd "$WCP_ROOT" || exit 1
}

function client_setup() {
	log_block "Setting up WooPayments client"

	# Load required variables from E2E config file.
	log_step "Fetching required variables from E2E config file..."
	local e2e_config_file=$(<"$DEFAULT_CONFIG_JSON_PATH") && log_success "Required variables fetched successfully."

	local wp_admin_username=$(echo "$e2e_config_file" | jq -r '.users.admin.username')
	local wp_admin_password=$(echo "$e2e_config_file" | jq -r '.users.admin.password')
	local wp_admin_email=$(echo "$e2e_config_file" | jq -r '.users.admin.email')
	local wc_customer_username=$(echo "$e2e_config_file" | jq -r '.users.customer.username')
	local wc_customer_password=$(echo "$e2e_config_file" | jq -r '.users.customer.password')
	local wc_customer_email=$(echo "$e2e_config_file" | jq -r '.users.customer.email')
	local wc_guest_email=$(echo "$e2e_config_file" | jq -r '.users.guest.email')

	log_step "Spinning up client containers..."
	docker-compose -f "$E2E_ROOT"/env/docker-compose.yml up --quiet-pull --build --force-recreate -d wordpress && log_success "WordPress container spun up successfully."
	if [[ -z $CI ]]; then
		docker-compose -f "$E2E_ROOT"/env/docker-compose.yml up --quiet-pull --build --force-recreate -d phpMyAdmin && log_success "phpMyAdmin container spun up successfully."
	fi

	# Disable xDebug on GitHub actions.
	if [[ -n $CI ]]; then
		log_step "Disabling xDebug on WooPayments server container..."
		client_cli mv /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini.disabled && log_success "xDebug disabled successfully."

		log_step "Restarting webserver on WooPayments sever container..."
		client_cli "$CLIENT_CONTAINER" /etc/init.d/apache2 reload && log_success "Webserver restarted successfully."
	fi

	# Setup client
	log_step "Waiting for containers to be ready for setup..."
	set +e
	while ! client_cli wp db check --quiet > /dev/null
	do
		log_message "Waiting until the service is ready..."
		sleep 5
	done && log_success "Containers are ready."
	set -e

	if [[ -n $CI ]]; then
		log_step "Setting docker folder permissions..."
		sudo chown www-data:www-data -R "$E2E_ROOT"/docker/wordpress/wp-content && log_success "Folder permissions updated successfully."
	fi

	log_step "Setting up WordPress"
	client_cli wp core install \
		--url="$WP_URL" \
		--title="WooCommerce Payments E2E" \
		--admin_name="$wp_admin_username" \
		--admin_password="$wp_admin_password" \
		--admin_email="$wp_admin_email" \
		--skip-email

	# Disable displaying errors & log to file.
	log_step "Updating WordPress debug settings.."
	client_cli wp config set WP_DEBUG_DISPLAY false --raw
	client_cli wp config set WP_DEBUG_LOG true --raw

	# Update permalink structure.
	log_step "Updating permalink structure..."
	client_cli wp rewrite structure '/%postname%/'

	# Install required plugins & themes.
	install_update_dependencies "$CLIENT_CONTAINER"

	# Add WooCommerce store settings.
	log_step "Adding basic WooCommerce settings..."
	client_cli wp option set woocommerce_store_address "60 29th Street"
	client_cli wp option set woocommerce_store_address_2 "#343"
	client_cli wp option set woocommerce_store_city "San Francisco"
	client_cli wp option set woocommerce_default_country "US:CA"
	client_cli wp option set woocommerce_store_postcode "94110"
	client_cli wp option set woocommerce_currency "USD"
	client_cli wp option set woocommerce_product_type "both"
	client_cli wp option set woocommerce_allow_tracking "no"
	client_cli wp option set woocommerce_enable_signup_and_login_from_checkout "yes"

	# Add default WooCommerce pages.
	log_step "Importing WooCommerce shop pages..."
	client_cli wp wc --user=admin tool run install_pages

	# Importing sample data.
	log_step "Importing some sample data..."
	client_cli wp import wp-content/plugins/woocommerce/sample-data/sample_products.xml --authors=skip

	# # Start - Workaround for > WC 8.3 compatibility by updating cart & checkout pages to use shortcode.
	# To be removed when WooPayments L-2 support is >= WC 8.3
	installed_wc_version=$(client_cli wp plugin get woocommerce --field=version)
	is_workaround_required=$(client_cli wp eval "echo version_compare(\"$installed_wc_version\", \"8.3\", \">=\");")

	if [[ "$is_workaround_required" = "1" ]]; then
		log_step "Updating cart & checkout pages for WC > 8.3 compatibility..."
		# Get cart & checkout page IDs.
		cart_page_id=$(client_cli wp option get woocommerce_cart_page_id)
		checkout_page_id=$(client_cli wp option get woocommerce_checkout_page_id)

		cart_shortcode="<!-- wp:shortcode -->[woocommerce_cart]<!-- /wp:shortcode -->"
		checkout_shortcode="<!-- wp:shortcode -->[woocommerce_checkout]<!-- /wp:shortcode -->"

		# Update cart & checkout pages to use shortcode.
		client_cli wp post update "$cart_page_id" --post_content="$cart_shortcode"
		client_cli wp post update "$checkout_page_id" --post_content="$checkout_shortcode"
	fi
	# End - Workaround for > WC 8.3 compatibility by updating cart & checkout pages to use shortcode.

	log_step "Removing customer account if present..."
	client_cli wp user delete "$wc_customer_email" --yes

	log_step "Removing guest account if present..."
	client_cli wp user delete "$wc_guest_email" --yes

	log_step "Adding customer account..."
	client_cli wp user create "$wc_customer_username" "$wc_customer_email" --role=customer --user_pass="$wc_customer_password"

	log_step "Removing all coupons..."
	client_cli wp db query "DELETE p, m FROM wp_posts p LEFT JOIN wp_postmeta m ON p.ID = m.post_id WHERE p.post_type = 'shop_coupon'"

	log_step "Setting up a coupon for E2E tests..."
	client_cli wp wc --user=admin shop_coupon create --code=free --amount=100 --discount_type=percent --individual_use=true --free_shipping=true

	log_step "Creating screenshots directory..."
	mkdir -p "$WCP_ROOT"/screenshots
}

function install_update_dependencies() {
	local container=$1

	# Dependencies for WooPayments server.
	if [[ $container == "$SERVER_CONTAINER" ]]; then
		# Clone server repository & configure server.
		if [[ ! -d "$SERVER_PATH" ]]; then
			log_step "Cloning server with branch $WCP_SERVER_BRANCH"
			git clone --depth=1 --branch="$WCP_SERVER_BRANCH" "$WCP_SERVER_REPO" "$SERVER_PATH" && log_success "Server cloned successfully."
		else
			log_info "Using cached server at ${SERVER_PATH}"
			cd "$SERVER_PATH" || exit 1
			log_step "Pulling latest changes from server repo..."
			git checkout "$WCP_SERVER_BRANCH"
			git reset --hard HEAD && git clean -fd && git pull && log_success "Latest changes updated successfully."
			cd "$WCP_ROOT" || exit 1
		fi
	fi

	# Dependencies for WooPayments client.
	if [[ $container == "$CLIENT_CONTAINER" ]]; then
		if [[ "$E2E_WP_VERSION" != "latest" ]]; then
			log_step "Updating to WordPress $E2E_WP_VERSION..."
			client_cli wp core update --version="$E2E_WP_VERSION" --force
		else
			log_step "Updating WordPress to latest..."
			client_cli wp core update
		fi

		log_step "Updating the WordPress database..."
		client_cli wp core update-db

		log_step "Installing and activating WordPress Importer..."
		client_cli wp plugin install wordpress-importer --force --activate

		# # Install WooCommerce
		if [[ -n "$E2E_WC_VERSION" && $E2E_WC_VERSION != 'latest' ]]; then
			# If specified version is 'beta', fetch the latest beta version from WordPress.org API
			if [[ $E2E_WC_VERSION == 'beta' ]]; then
				E2E_WC_VERSION=$(curl https://api.wordpress.org/plugins/info/1.0/woocommerce.json | jq -r '.versions | with_entries(select(.key|match("beta";"i"))) | keys[-1]' --sort-keys)
			fi

			log_step "Installing and activating WooCommerce $E2E_WC_VERSION..."
			client_cli wp plugin install woocommerce --version="$E2E_WC_VERSION" --force --activate
		else
			log_step "Installing and activating latest WooCommerce version..."
			client_cli wp plugin install woocommerce --force --activate
		fi

		# Install basic auth plugin.
		log_step "Installing and activating basic auth plugin..."
		client_cli wp plugin install https://github.com/WP-API/Basic-Auth/archive/master.zip --force --activate

		# Install storefront theme.
		log_step "Installing and activating storefront theme..."
		client_cli wp theme install storefront --force --activate

		if [ ! "$(ls -A "$DEV_TOOLS_PATH")" ]; then
			log_step "Cloning WooPayments dev tools with branch $WCP_DEV_TOOLS_BRANCH..."
			git clone --depth=1 --branch "$WCP_DEV_TOOLS_BRANCH" "$WCP_DEV_TOOLS_REPO" "$DEV_TOOLS_PATH" && log_success "Dev tools cloned successfully."
		else
			cd "$DEV_TOOLS_PATH" || exit 1
			log_step "Pulling latest changes from dev tools repo..."
			git checkout "$WCP_DEV_TOOLS_BRANCH"
			git reset --hard HEAD && git clean -fd && git pull && log_success "Latest changes updated successfully."
			cd "$WCP_ROOT" || exit 1
		fi

		log_step "Activating WooPayments dev tools plugin..."
		client_cli wp plugin activate "$DEV_TOOLS_DIR"

		# WooPayments plugin - Install from zip or activate.
		# If CI is true and WCPAY_USE_BUILD_ARTIFACT is true, create a zip file from the GitHub artifact and install it.
		# Else, activate the plugin.
		if [[ -n "$CI" && "$WCPAY_USE_BUILD_ARTIFACT" = true ]]; then
			log_step "Creating WooCommerce Payments zip file from GitHub artifact..."
			mv "$WCPAY_ARTIFACT_DIRECTORY"/woocommerce-payments "$WCPAY_ARTIFACT_DIRECTORY"/woocommerce-payments-build
			cd "$WCPAY_ARTIFACT_DIRECTORY" && zip -r "$WCP_ROOT"/woocommerce-payments-build.zip . && cd "$WCP_ROOT"

			log_step "Installing & activating the WooCommerce Payments plugin using the zip file created..."
			client_cli wp plugin install wp-content/plugins/woocommerce-payments/woocommerce-payments-build.zip --force --activate
		else
			log_step "Activating the WooCommerce Payments plugin..."
			client_cli wp plugin activate woocommerce-payments
		fi

		# Install & activate WC Subscriptions if not skipped.
		if [[ "$SKIP_WC_SUBSCRIPTIONS_TESTS" == false ]]; then
			log_step "Install and activate the latest release of WooCommerce Subscriptions..."

			cd "$E2E_ROOT"/deps || exit

			# Get the latest release asset id & download the release package.
			LATEST_RELEASE_ASSET_ID=$(curl -H "Authorization: token $E2E_GH_TOKEN" https://api.github.com/repos/"$WC_SUBSCRIPTIONS_REPO"/releases/latest | jq -r '.assets[0].id')
			curl -LJ \
				-H "Authorization: token $E2E_GH_TOKEN" \
				-H "Accept: application/octet-stream" \
				--output woocommerce-subscriptions.zip \
				https://api.github.com/repos/"$WC_SUBSCRIPTIONS_REPO"/releases/assets/"$LATEST_RELEASE_ASSET_ID"

			# Unzip the latest release package & move contents to woocommerce-subscriptions folder mapped to docker.
			# This may require your admin password.
			unzip -qq woocommerce-subscriptions.zip -d woocommerce-subscriptions-source
			sudo mv woocommerce-subscriptions-source/woocommerce-subscriptions/* woocommerce-subscriptions

			# Activate the plugin.
			client_cli wp plugin activate woocommerce-subscriptions

			# Remove unwanted files.
			sudo rm -rf woocommerce-subscriptions.zip woocommerce-subscriptions-source

			# Import subscription products.
			log_step "Import WooCommerce Subscription products"
			if [[ "0" == "$(client_cli wp option list --search=subscriptions_products_imported --format=count --quiet)" ]]; then
				log_step "Importing subscriptions products..."
				client_cli wp import wp-content/plugins/woocommerce-payments/tests/e2e/env/wc-subscription-products.xml --authors=skip
				client_cli wp option add subscriptions_products_imported true --quiet
			fi

			cd "$WCP_ROOT" || exit
		else
			log_step "Skipping install of WooCommerce Subscriptions & deactivating plugin if present..."
			client_cli wp plugin deactivate woocommerce-subscriptions || true
		fi

		# Install & activate Action Scheduler if not skipped.
		if [[ "$SKIP_WC_ACTION_SCHEDULER_TESTS" == false ]]; then
			log_step "Install and activate the latest release of Action Scheduler..."
			client_cli wp plugin install action-scheduler --force --activate
		else
			log_step "Skipping install of Action Scheduler & deactivating plugin if present..."
			client_cli wp plugin deactivate action-scheduler || true
		fi

		# Install & activate WC Blocks if not skipped.
		if [[ "$SKIP_WC_BLOCKS_TESTS" == false ]]; then
			log_step "Install and activate the latest release of WooCommerce Blocks"
			client_cli wp plugin install woo-gutenberg-products-block --activate
		else
			echo "Skipping install of WooCommerce Blocks & deactivating plugin if present..."
			client_cli wp plugin deactivate woo-gutenberg-products-block || true
		fi
	fi
}

function setup_woopayments() {
	log_block "Setting up WooCommerce Payments..."

	if [[ "0" == "$(client_cli wp option list --search=woocommerce_woocommerce_payments_settings --format=count --quiet)" ]]; then
		log_step "Adding WooCommerce Payments settings..."
		client_cli wp option add woocommerce_woocommerce_payments_settings --format=json '{"enabled":"yes"}'
	fi

	log_step "Disabling WPCOM requests proxy..."
	client_cli wp option set wcpaydev_proxy 0

	# If local server is installed, set up redirects to local server & set blog id.
	# Else, set up redirects to production server & set blog id, blog token & user token..
	if [[ "$E2E_USE_LOCAL_SERVER" == true ]]; then
		log_step "Setting redirection to local server..."

		WP_LISTEN_PORT=$(docker port "$SERVER_CONTAINER" | sed -En "s/.*0:([0-9]+).*/\1/p")

		if [[ -n $CI ]]; then
			# host.docker.internal is not available in linux. Use ip address for docker0 interface to redirect requests from container.
			DOCKER_HOST=$(ip -4 addr show docker0 | grep -Po 'inet \K[\d.]+')
		fi
		client_cli wp wcpay_dev redirect_to "http://${DOCKER_HOST-host.docker.internal}:${WP_LISTEN_PORT}/wp-json/"

		log_step "Setting Jetpack blog id..."
		client_cli wp wcpay_dev set_blog_id "$E2E_BLOG_ID"

		log_step "Refresh WooPayments account data..."
		client_cli wp wcpay_dev refresh_account_data
	else
		log_step "Setting Jetpack blog id..."
		client_cli wp wcpay_dev set_blog_id "$E2E_BLOG_ID" --blog_token="$E2E_BLOG_TOKEN" --user_token="$E2E_USER_TOKEN"
	fi

	# Disable WooPayments rate limiter.
	log_step "Disabling rate limiter for E2E tests..."
	client_cli wp option set wcpay_session_rate_limiter_disabled_wcpay_card_declined_registry yes
}

function get_client_info() {
	# Log test configuration for visibility
	log_block "WooPayments client configuration"

	log_step "Docker environment:"
	client_cli wp cli info

	log_step "WordPress version:"
	client_cli wp core version

	log_step "Active plugins:"
	client_cli wp plugin list --active --fields=name,version


	step "Client site is up and running at http://$WP_URL/"
}

check_setup_problems
cleanup_dependencies
server_setup
client_setup
setup_woopayments
get_client_info
