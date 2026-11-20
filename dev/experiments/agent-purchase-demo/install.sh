#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")"
container=wcpay_wp_default
plugin=/var/www/html/wp-content/plugins/woopayments-agent-demo
docker exec "$container" mkdir -p "$plugin"
# Explicit source allowlist: private .runtime files must never enter the web root.
# Migrate an earlier companion installation before loading its fixture-only replacement.
if docker exec "$container" wp --allow-root plugin is-active woopayments-agent-demo; then
  docker exec "$container" wp --allow-root plugin deactivate woopayments-agent-demo
fi
docker exec "$container" rm -f "$plugin/woopayments-agent-demo.php" "$plugin/class-demo-quote.php" "$plugin/approval.php" "$plugin/demo.css"
for source in demo-support.php; do
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
docker exec "$container" wp --allow-root plugin activate woopayments-agent-demo/demo-support.php
