#!/bin/bash
# ------------------------------------------------------------------
# Global Setup – executed INSIDE the WP container
# ------------------------------------------------------------------
# Put your plugin/extension into a _minimal ready state_ here.
#   – Creates sandbox credentials
#   – Disables onboarding banners
#   – Turns off tracking, etc.
# This runs **once** per test run (even if your package is only in
# `global_setup`) and should finish fast.

set -euo pipefail

echo "[globalSetup] Starting global configuration..."
# Example:
# wp option update my_plugin_onboarding_complete yes
echo "[globalSetup] Done."