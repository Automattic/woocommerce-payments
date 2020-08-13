#!/usr/bin/env sh

# Exit if any command fails.
set -e

WP_CONTAINER=${1-woocommerce_payments_wordpress}
SITE_URL=${WP_URL-"localhost:8082"}

redirect_output() {
	if [[ -z "$DEBUG" ]]; then
        "$@" > /dev/null
    else
        "$@"
    fi
}

# --user xfs forces the wordpress:cli container to use a user with the same ID as the main wordpress container. See:
# https://hub.docker.com/_/wordpress#running-as-an-arbitrary-user
cli()
{
	redirect_output docker run -it --rm --user xfs --volumes-from $WP_CONTAINER --network container:$WP_CONTAINER wordpress:cli "$@"
}

set +e
# Wait for containers to be started up before the setup.
# The db being accessible means that the db container started and the WP has been downloaded and the plugin linked
cli wp db check --path=/var/www/html --quiet > /dev/null
while [[ $? -ne 0 ]]; do
	echo "Waiting until the service is ready..."
	sleep 5s
	cli wp db check --path=/var/www/html --quiet > /dev/null
done

set -e

echo
echo "Setting up environment..."
echo

echo "Pulling the WordPress CLI docker image..."
docker pull wordpress:cli > /dev/null

echo "Setting up WordPress..."
cli wp core install \
	--path=/var/www/html \
	--url=$SITE_URL \
	--title=${SITE_TITLE-"WooCommerce Payments Dev"} \
	--admin_name=${WP_ADMIN-admin} \
	--admin_password=${WP_ADMIN_PASSWORD-admin} \
	--admin_email=${WP_ADMIN_EMAIL-admin@example.com} \
	--skip-email

echo "Updating WordPress to the latest version..."
cli wp core update --quiet

echo "Updating the WordPress database..."
cli wp core update-db --quiet

echo "Configuring paths to work with ngrok...";
cli config set DOCKER_REQUEST_URL "( ! empty( \$_SERVER['HTTPS'] ) ? 'https://' : 'http://' ) . ( ! empty( \$_SERVER['HTTP_HOST'] ) ? \$_SERVER['HTTP_HOST'] : 'localhost' )" --raw
cli config set WP_SITEURL DOCKER_REQUEST_URL --raw
cli config set WP_HOME DOCKER_REQUEST_URL --raw

echo "Updating permalink structure"
cli wp rewrite structure '/%postname%/'

echo "Installing and activating WooCommerce..."
cli wp plugin install woocommerce --activate

echo "Installing and activating Storefront theme..."
cli wp theme install storefront --activate

echo "Adding basic WooCommerce settings..."
cli wp option set woocommerce_store_address "60 29th Street"
cli wp option set woocommerce_store_address_2 "#343"
cli wp option set woocommerce_store_city "San Francisco"
cli wp option set woocommerce_default_country "US:CA"
cli wp option set woocommerce_store_postcode "94110"
cli wp option set woocommerce_currency "USD"
cli wp option set woocommerce_product_type "both"
cli wp option set woocommerce_allow_tracking "no"

echo "Importing WooCommerce shop pages..."
cli wp wc --user=admin tool run install_pages

echo "Installing and activating the WordPress Importer plugin..."
cli wp plugin install wordpress-importer --activate

echo "Importing some sample data..."
cli wp import wp-content/plugins/woocommerce/sample-data/sample_products.xml --authors=skip

echo "Activating the WooCommerce Payments plugin..."
cli wp plugin activate woocommerce-payments

echo "Setting up WooCommerce Payments..."
if [[ "0" == "$(cli wp option list --search=woocommerce_woocommerce_payments_settings --format=count)" ]]; then
	echo "Creating WooCommerce Payments settings"
	cli wp option add woocommerce_woocommerce_payments_settings --format=json '{"enabled":"yes"}'
else
	echo "Updating WooCommerce Payments settings"
	cli wp option update woocommerce_woocommerce_payments_settings --format=json '{"enabled":"yes"}'
fi

echo
echo "SUCCESS! You should now be able to access http://${SITE_URL}/wp-admin/"
echo "You can login by using the username and password both as 'admin'"
