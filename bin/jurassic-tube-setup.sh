#!/bin/bash

# Exit if any command fails.
set -e

# Define Jurassic Tube directory using bin directory
TUBE_DIR="${PWD}/bin/jurassictube"

echo "Setting up Standalone Jurassic Tube..."

# Create Jurassic Tube directory if it doesn't exist
if [ ! -d "$TUBE_DIR" ]; then
    echo "Creating Jurassic Tube directory at $TUBE_DIR..."
    mkdir -p "$TUBE_DIR"
fi

# Download and run the installer
echo "Downloading the standalone installer..."
curl "https://jurassic.tube/installer-standalone.sh" -o "$TUBE_DIR/installer.sh" && chmod +x "$TUBE_DIR/installer.sh" && "$TUBE_DIR/installer.sh"

echo
read -p "Go to https://jurassic.tube/ in a browser, paste your public key which was printed above into the box, and click 'Add Public Key'. Press enter to continue"
echo 

read -p "Go to https://jurassic.tube/ in a browser, add a subdomain using the desired name for your subdomain, and click 'Add Subdomain'. The subdomain name is what you will use to access WC Payments in a browser. When this is done, type the subdomain name here and press enter. Please just type in the subdomain, not the full URL: " subdomain
echo 

read -p "Please enter your Automattic/WordPress.com username: " username
echo 

if [ ! -f "${TUBE_DIR}/config.env" ]; then
    touch "${TUBE_DIR}/config.env"
fi

echo "username=${username}" >> ${TUBE_DIR}/config.env
echo "subdomain=${subdomain}" >> ${TUBE_DIR}/config.env
echo "port=8082" >> ${TUBE_DIR}/config.env

echo "Setup complete!"
echo "Use the command: npm run tube:start from the root directory of your WooPayments project to start running Jurassic Tube."
echo 
