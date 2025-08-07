# GitHub Actions Scripts

This directory contains scripts used by GitHub Actions workflows for dynamic version management and matrix generation.

## Scripts

### `generate-wc-matrix.sh`

Generates the WooCommerce version matrix for E2E tests with dynamic version resolution and optimized PHP version strategy.

**Usage:**

```bash
.github/scripts/generate-wc-matrix.sh
```

**Output:**
JSON array of WooCommerce versions including:

- 7.7.0 (kept for business reasons - significant TPV)
- L-1 version (latest stable in previous major branch)
- Latest stable (current major)
- latest, beta (when available), rc

**Features:**

- Fetches latest WC version from WordPress.org API
- Dynamically calculates L-1 version (latest stable in previous major branch)
- Includes only L-1 and current major versions (skipping intermediate versions)
- Dynamically resolves beta and RC versions from current major branch
- Outputs debug information to stderr for version extraction
- Skips beta versions when not available

**Debug Output (stderr):**

```
Fetching latest WooCommerce version...
Latest WC version: 10.0.4
L-1 version: 9.9.5
Major versions latest stable: 9.9.5 10.0.4
Fetching latest RC and beta versions...
Latest RC version: 10.1.0-rc.2
Latest beta version: null
No beta version available, skipping beta tests
```

## Matrix Generation Strategy

### PHP Version Strategy

The workflow uses an optimized PHP version strategy to reduce job count while maintaining comprehensive coverage:

- **WC 7.7.0**: PHP 7.4 (legacy support)
- **WC 9.9.5 (L-1)**: PHP 8.3 (stable)
- **WC latest**: PHP 8.3 (stable)
- **WC beta**: PHP 8.3 (stable) - only when available
- **WC rc**: PHP 8.4 (latest)

### Version Resolution

- **L-1 Version**: Dynamically extracted from script stderr output
- **Beta Version**: Extracted from script stderr, only included when available
- **RC Version**: Dynamically resolved to actual version number
- **Fallback**: No fallback to string versions (prevents WP-CLI errors)

## How It Works

### Script Execution

1. Fetches the latest WooCommerce version from `https://api.wordpress.org/plugins/info/1.0/woocommerce.json`
2. Dynamically calculates the L-1 version by finding the latest stable version in the previous major branch
3. Fetches beta and RC versions from the current major branch only
4. Outputs debug information to stderr for version extraction
5. Outputs JSON array to stdout for matrix generation

### Workflow Integration

1. Script runs and outputs both JSON array and debug info
2. Workflow extracts specific versions from stderr output
3. Workflow builds optimized matrix with selective PHP version testing
4. Matrix includes only necessary combinations to reduce job count

### Version Extraction

```bash
# Extract L-1 version from script output
L1_VERSION=$(echo "$SCRIPT_OUTPUT" | grep "L-1 version:" | cut -d' ' -f3)

# Extract beta version from script output
BETA_VERSION=$(echo "$SCRIPT_OUTPUT" | grep "Latest beta version:" | cut -d' ' -f4)

# Extract RC version from JSON array
RC_VERSION=$(echo "$WC_VERSIONS" | jq -r '.[-1]')
```

## Dependencies

- `curl`: For API requests
- `jq`: For JSON parsing and array generation
- `bash`: For script execution

## Error Handling

- Scripts use `set -e` to exit on any error
- Version extraction includes validation checks
- Graceful handling of missing beta versions
- If the API is unavailable or returns unexpected data, the workflow will fail gracefully

## Future Considerations

- Automatically adapts to new WooCommerce releases
- Will include beta versions when they become available
- Supports L-2 policy implementation if needed
- Maintains business continuity with WC 7.7.0 support
