#!/usr/bin/env bash

set -e

cwd=$(pwd)
WCP_ROOT=$cwd
QIT_ROOT="$cwd/tests/qit"
EXTENSION_NAME="woocommerce-payments"


#Load local env variables if present.
if [[ -f "$QIT_ROOT/config/local.env" ]]; then
	. "$QIT_ROOT/config/local.env"
fi

# Check if QIT_USER and QIT_APP_PASSWORD are set and not empty
if [[ -z $QIT_USER ]] || [[ -z $QIT_PASSWORD ]] ; then
    echo "QIT_USER or QIT_APP_PASSWORD environment variables are not set or empty. Please set them before running the script."
    exit 1
fi

export QIT_DISABLE_ONBOARDING=yes

# If QIT_BINARY is not set, default to ./vendor/bin/qit
QIT_BINARY=${QIT_BINARY:-./vendor/bin/qit}

# Add the partner by validating credentials.
if ! $QIT_BINARY list | grep -q 'partner:remove'; then
    echo "Adding partner with QIT credentials..."
    $QIT_BINARY partner:add --user=$QIT_USER --application_password=$QIT_PASSWORD
    if [ $? -ne 0 ]; then
        echo "Failed to add partner. Exiting with status 1."
        exit 1
    fi
fi

# Run the security command
echo "Running security tests..."
$QIT_BINARY run:security woocommerce-payments --zip=woocommerce-payments.zip --wait
if [ $? -ne 0 ]; then
    echo "Failed to run security command. Exiting with status 1."
    exit 1
fi
