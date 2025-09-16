#!/usr/bin/env bash

set -euo pipefail
IFS=$'\n\t'

cwd=$(pwd)
WCP_ROOT="$cwd"
QIT_ROOT="$cwd/tests/qit"
EXTENSION_NAME="woocommerce-payments"

#Load local env variables if present.
if [[ -f "$QIT_ROOT/config/local.env" ]]; then
	# shellcheck disable=SC1090
	. "$QIT_ROOT/config/local.env"
fi

# Check if QIT credentials are set and not empty
if [[ -z "${QIT_CI_USER:-}" ]] || [[ -z "${QIT_CI_SECRET:-}" ]]; then
	echo "QIT_CI_USER or QIT_CI_SECRET environment variables are not set or empty. Please set them in the local env file before running the script."
	exit 1
fi

# Set legacy variable names for compatibility with existing scripts
QIT_USER="$QIT_CI_USER"
QIT_PASSWORD="$QIT_CI_SECRET"

export QIT_DISABLE_ONBOARDING=yes

# If QIT_BINARY is not set, default to ./vendor/bin/qit
QIT_BINARY=${QIT_BINARY:-./vendor/bin/qit}

# Add the partner by validating credentials.
if ! "$QIT_BINARY" list | grep -q 'partner:remove'; then
	echo "Adding partner with QIT credentials..."
	if ! "$QIT_BINARY" partner:add --user="$QIT_USER" --application_password="$QIT_PASSWORD"; then
		echo "Failed to add partner. Exiting with status 1."
		exit 1
	fi
fi
