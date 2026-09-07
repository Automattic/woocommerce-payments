#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")"
container=wcpay_wp_default
plugin=/var/www/html/wp-content/plugins/woopayments-agent-demo
docker exec "$container" mkdir -p "$plugin"
# Explicit source allowlist: private .runtime files must never enter the web root.
for source in woopayments-agent-demo.php class-demo-quote.php approval.php demo.css; do
  docker cp "$source" "$container:$plugin/$source"
done
docker cp setup.php "$container:/tmp/wcpay-agent-demo-setup.php"
docker exec "$container" wp --allow-root eval-file /tmp/wcpay-agent-demo-setup.php
mkdir -p .runtime
chmod 700 .runtime
docker cp "$container:/tmp/wcpay-agent-demo-agent.json" .runtime/agent.json
docker cp "$container:/tmp/wcpay-agent-demo-shopper.json" .runtime/shopper.json
chmod 600 .runtime/*.json
docker exec "$container" rm /tmp/wcpay-agent-demo-agent.json /tmp/wcpay-agent-demo-shopper.json
docker exec "$container" wp --allow-root plugin activate woopayments-agent-demo
