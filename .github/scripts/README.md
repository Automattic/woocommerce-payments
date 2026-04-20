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
Single JSON object containing versions array and metadata:

```json
{
  "versions": [
    "7.7.0",
    "9.9.5",
    "latest",
    "10.1.0-rc.2"
  ],
  "metadata": {
    "l1_version": "9.9.5",
    "rc_version": "10.1.0-rc.2",
    "beta_version": null
  }
}
```

**Features:**

- Fetches latest WC version from WordPress.org API
- Dynamically calculates L-1 version (latest stable in previous major branch)
- Includes only L-1 and current major versions (skipping intermediate versions)
- Dynamically resolves beta and RC versions from current major branch
- Outputs structured JSON for easy parsing
- Skips beta versions when not available
- Provides debug output to stderr for troubleshooting

## Matrix Generation Strategy

### PHP Version Strategy

The workflow uses an optimized PHP version strategy to reduce job count while maintaining comprehensive coverage:

- **WC 7.7.0**: PHP 7.3 (legacy support - minimum required version)
- **WC L-1**: PHP 8.3 (stable)
- **WC latest**: PHP 8.3 (stable)
- **WC beta**: PHP 8.3 (stable) - only when available
- **WC rc**: PHP 8.4 (latest)

### Version Resolution

- **L-1 Version**: Extracted from JSON metadata
- **Beta Version**: Extracted from JSON metadata, only included when available
- **RC Version**: Always included - extracted from JSON metadata or falls back to string "rc"

## How It Works

### Script Execution

1. Fetches the latest WooCommerce version from `https://api.wordpress.org/plugins/info/1.0/woocommerce.json`
2. Dynamically calculates the L-1 version by finding the latest stable version in the previous major branch
3. Fetches beta and RC versions from the current major branch only
4. Outputs JSON object to stdout for matrix generation

### Workflow Integration

1. Script runs and outputs structured JSON with versions and metadata
2. Workflow extracts specific versions using standard JSON parsing
3. Workflow builds optimized matrix with selective PHP version testing
4. Matrix includes only necessary combinations to reduce job count

### Version Extraction

```bash
# Get script result
SCRIPT_RESULT=$( .github/scripts/generate-wc-matrix.sh )

# Extract versions and metadata using jq
WC_VERSIONS=$(echo "$SCRIPT_RESULT" | jq -r '.versions')
L1_VERSION=$(echo "$SCRIPT_RESULT" | jq -r '.metadata.l1_version')
RC_VERSION=$(echo "$SCRIPT_RESULT" | jq -r '.metadata.rc_version')
BETA_VERSION=$(echo "$SCRIPT_RESULT" | jq -r '.metadata.beta_version')
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
