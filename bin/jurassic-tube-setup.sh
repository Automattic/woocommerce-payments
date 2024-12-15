#!/bin/bash

# Exit if any command fails.
set -e

# Define Jurassic Tube directory using bin directory
JT_DIR="${PWD}/bin/jurassictube"

echo "Checking if ${JT_DIR} directory exists..."

if [ -d "${JT_DIR}" ]; then
    echo "${JT_DIR} already exists."
else
    echo "Creating ${JT_DIR} directory..."
    mkdir -p "${JT_DIR}"
fi

echo "Checking if the installer is present and downloading it if not..."
echo 

# Download the installer (if it's not already present):
if [ ! -f "${JT_DIR}/installer.sh" ]; then
    echo "Downloading the standalone installer..."
    curl "https://jurassic.tube/installer-standalone.sh" -o "${JT_DIR}/installer.sh" && chmod +x "${JT_DIR}/installer.sh"
fi

echo "Running the installation script..."
echo 

# Run the installer script
"${JT_DIR}/installer.sh"

echo
read -p "Go to https://jurassic.tube/ in a browser, paste your public key which was printed above into the box, and click 'Add Public Key'. Press enter to continue"
echo 

read -p "Go to https://jurassic.tube/ in a browser, add a subdomain using the desired name for your subdomain, and click 'Add Subdomain'. The subdomain name is what you will use to access WC Payments in a browser. When this is done, type the subdomain name here and press enter. Please just type in the subdomain, not the full URL: " subdomain
echo 

# npm run wp option update home https://${subdomain}.jurassic.tube/
# npm run wp option update siteurl https://${subdomain}.jurassic.tube/

read -p "Please enter your Automattic/WordPress.com username: " username
echo 

if [ ! -f "${JT_DIR}/config.env" ]; then
    touch "${JT_DIR}/config.env"
else
    > "${JT_DIR}/config.env"
fi

# Find the WordPress container section and get its port
PORT=$(docker ps | grep woocommerce_payments_wordpress | sed -En "s/.*0:([0-9]+).*/\1/p")

# Use default if extraction failed
if [ -z "$PORT" ]; then
    PORT=8082  # Default fallback
    echo "Could not extract WordPress container port, using default: ${PORT}"
fi

echo "username=${username}" >> "${JT_DIR}/config.env"
echo "subdomain=${subdomain}" >> "${JT_DIR}/config.env"
echo "localhost=localhost:${PORT}" >> "${JT_DIR}/config.env"

echo "Setup complete!"
echo "Use the command: npm run tube:start from the root directory of your WC Payments project to start running Jurassic Tube."
echo 
