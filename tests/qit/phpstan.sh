#!/usr/bin/env bash

# Get the directory of the current script
DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"

# Source common.sh using the relative path
source "$DIR/common.sh"

# Check if the --local flag is provided which means the tests should run against the development build
ZIP_FILE=""
if echo "$@" | grep -q -- "--local"; then
  ZIP_FILE="$WCP_ROOT/woocommerce-payments.zip"

  # Check if the zip file exists
  if [[ ! -f "$ZIP_FILE" ]]; then
    echo "Zip file $ZIP_FILE does not exist. Please ensure the zip file is present in the main folder."
    exit 1
  fi

  echo "Running PHPStan tests with development build $ZIP_FILE..."
  $QIT_BINARY run:phpstan "$EXTENSION_NAME" --zip "$ZIP_FILE" --wait
else
  echo "Running PHPStan tests..."
  $QIT_BINARY run:phpstan "$EXTENSION_NAME" --wait
fi

if [ $? -ne 0 ]; then
  echo "Failed to run PHPStan command. Exiting with status 1."
  exit 1
fi