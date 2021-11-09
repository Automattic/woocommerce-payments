# Remove the old `vendor-dist` if present.
rm -rf vendor-dist

# Install non-dev dependencies in a separate folder (because we need phpcs, which is a dev dependency).
COMPOSER_VENDOR_DIR=vendor-dist composer install --no-dev

# Run PHPCS with the custom compatibility configuration.
./vendor/bin/phpcs --standard=phpcs-compat.xml.dist .

# Cleanup
rm -rf vendor-dist
