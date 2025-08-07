# GitHub Actions Scripts

This directory contains scripts used by GitHub Actions workflows for dynamic version management and matrix generation.

## Scripts

### `generate-wc-matrix.sh`

Generates the WooCommerce version matrix for E2E tests.

**Usage:**
```bash
.github/scripts/generate-wc-matrix.sh
```

**Output:**
JSON array of WooCommerce versions including:
- 7.7.0 (kept for business reasons)
- L-1 version (latest stable in previous major branch)
- Latest stable (current major)
- latest, beta, rc

**Features:**
- Fetches latest WC version from WordPress.org API
- Dynamically calculates L-1 version (latest stable in previous major branch)
- Includes only L-1 and current major versions (skipping intermediate versions)
- Includes special versions (latest, beta, rc)

## How It Works

The script:
1. Fetches the latest WooCommerce version from `https://api.wordpress.org/plugins/info/1.0/woocommerce.json`
2. Dynamically calculates the L-1 version by finding the latest stable version in the previous major branch
3. Includes only L-1 and current major versions (skipping intermediate major versions)
4. Outputs a JSON array for use in GitHub Actions matrix

## Dependencies

- `curl`: For API requests
- `jq`: For JSON parsing and array generation
- `bash`: For script execution

## Error Handling

Scripts use `set -e` to exit on any error. If the API is unavailable or returns unexpected data, the workflow will fail gracefully.
