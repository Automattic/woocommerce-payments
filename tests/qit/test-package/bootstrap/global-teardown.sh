#!/bin/bash
# ------------------------------------------------------------------
# Global Teardown – executed INSIDE the WP container
# ------------------------------------------------------------------
# Runs once at the very end.  Clean up anything created in globalSetup.

set -euo pipefail

echo "[globalTeardown] Cleaning up ..."
# Example:
# wp option delete my_plugin_sandbox_token
echo "[globalTeardown] Done."