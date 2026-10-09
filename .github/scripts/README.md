# GitHub Actions Scripts

This directory contains scripts used by GitHub Actions workflows for documentation checks, dynamic version management and matrix generation.

## Scripts

### `check-doc-links.py`

Checks complete WooCommerce documentation URL literals in Git-tracked `.php`, `.js`, `.jsx`, `.ts` and `.tsx` source files. The scan covers root source files and `assets/`, `client/`, `includes/` (including the multi-currency client), `src/` and `templates/`. Tests, snapshots, dependencies and generated/minified bundles are excluded. URLs in source comments are included.

```bash
python3 -B .github/scripts/check-doc-links.py --report /tmp/documentation-links.md
python3 -B -m unittest discover -s .github/scripts/tests -p 'test_check_doc_links.py' -v
```

Requires Python 3.10+ and Git, with no third-party Python packages. The tests use a local HTTP server and do not contact WooCommerce.com. The checker:

- Finds `woocommerce.com/document/`, `/documentation/`, `/docs/`, their `www` equivalents, and legacy `docs.woocommerce.com` URLs.
- Follows HTTP redirects within the official WooCommerce documentation hosts, including `developer.woocommerce.com`.
- Fetches each unique URL without its fragment once, using four workers, a 20-second request timeout and up to three attempts for transient failures.
- Requires successful HTML responses and matches percent-decoded fragments against exact HTML `id` values. It does not check legacy `<a name>` attributes or execute page JavaScript.
- Logs every URL and source location, writes a Markdown report, and appends that report to the Actions job summary. Missing IDs, HTTP/network errors and an empty scan return a failing exit status. Access blocks and timeouts mean verification failed; they do not establish that a URL is broken.
- Checks complete literals only. Runtime-assembled links, including the country-specific fee anchors in `client/utils/account-fees.tsx`, are outside this first version's scope. Detected interpolations inside URL literals are reported for manual review.

The **Check documentation links** workflow is manual-only. After the workflow reaches the default branch, select **Actions → Check documentation links → Run workflow**. Run the workflow from `develop` and either:

- Set `ref` to a release branch, tag or commit (for example, `release/11.2.0`). Leave both inputs empty to scan the selected workflow commit. These runs report in the logs and job summary.
- Set `pr_number` to scan that PR's current head commit and create or update one report comment. Leave `ref` empty. The PR number is resolved through GitHub so the comment always describes the revision that was scanned.

The source checkout is read as data; its code and dependencies are never executed. No automatic release-PR trigger is enabled yet. When adding one, filter on the PR's **head** branch (`release/`), since `pull_request.branches` filters the base branch, and use a trusted checker revision with appropriate comment permissions. The existing report step already accepts a PR event number.

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
