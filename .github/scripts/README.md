# GitHub Actions Scripts

This directory contains scripts used by GitHub Actions workflows for documentation checks, dynamic version management and matrix generation.

## Scripts

### `check-doc-links.js`

Checks WooCommerce documentation links in Git-tracked `readme.txt` and `.php`, `.js`, `.jsx`, `.ts` and `.tsx` source files. Source scope covers root files and `assets/`, `client/`, `includes/` (including the multi-currency client), `src/` and `templates/`. Tests, snapshots, dependencies and generated/minified bundles are excluded; source comments are included.

The GitHub CLI can dispatch the workflow from a PR branch once GitHub has registered it through a PR test run:

```bash
gh workflow run check-doc-links.yml --repo Automattic/woocommerce-payments \
  --ref WORKFLOW_BRANCH -f ref=SOURCE_REF
```

`--ref` selects the branch containing the workflow; `-f ref` selects the branch, tag or commit to scan. To test the initial PR, use its branch for both. Omit `-f ref` to scan the selected workflow commit. After merge, use `develop` for `--ref`, or open **Actions → Check documentation links → Run workflow** and enter the source ref there.

Results appear in the job log and summary. The workflow has only `contents: read` permission and never executes the target checkout's code.

Local commands (the repository's Node.js/pnpm versions and Git):

```bash
pnpm install --frozen-lockfile --ignore-scripts
node .github/scripts/check-doc-links.js --report /tmp/documentation-links.md
node --test .github/scripts/tests/test-check-doc-links.js
```

The checker uses Node's built-in HTTP client and test runner, plus `parse5` to parse HTML and decode entities. CI installs locked dependencies from the checker checkout with lifecycle scripts disabled.

Offline tests run automatically on PRs that change the checker, its tests, workflow or dependency configuration, and on merge-queue runs. They use a local HTTP server without contacting WooCommerce.com. Live URL checks run only on manual dispatch, after the tests pass.

The checker:

- Finds `woocommerce.com/document/`, `/documentation/`, `/docs/`, their `www` equivalents, and legacy `docs.woocommerce.com` URLs.
- Follows HTTP redirects within the official WooCommerce documentation hosts, including `developer.woocommerce.com`.
- Fetches each unique URL without its fragment once, using four workers, a 20-second request timeout and up to three attempts for transient failures.
- Requires successful HTML responses and matches percent-decoded fragments against exact HTML `id` values. It does not check legacy `<a name>` attributes or execute page JavaScript.
- Logs every URL and source location, writes a Markdown report, and appends it to the Actions job summary. Missing IDs, HTTP/network errors and an empty scan fail the job. Access blocks and timeouts mean verification failed; they do not establish that a URL is broken.

It cannot tell whether a working page or anchor covers the topic promised by the link text. Topic relevance still needs manual review. Runtime expressions and server-supplied URLs are not evaluated; detected interpolations inside URL literals are reported for manual review.

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
