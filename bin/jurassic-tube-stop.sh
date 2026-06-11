#!/bin/bash
set -e

REPO_ROOT="$(git rev-parse --show-toplevel)"
JT_DIR="${REPO_ROOT}/bin/jurassictube"

if [ ! -f "${JT_DIR}/config.env" ]; then
    echo "Error: No jurassic tube config found. Is the tunnel running?"
    exit 1
fi

# shellcheck source=/dev/null
source "${JT_DIR}/config.env"

echo "Stopping tunnel: ${subdomain}.jurassic.tube"
jurassictube -b -s "$subdomain" || echo "Warning: could not stop tunnel (may already be stopped)"

# WordPress URLs revert automatically — wp-config.php derives WP_SITEURL/WP_HOME
# from HTTP_HOST, so requests to localhost:<port> use the correct URL without DB changes.
echo "Done. Tunnel stopped."
