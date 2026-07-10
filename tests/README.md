# WooPayments Tests

WooPayments has several test suites, each documented alongside the tests it covers. Use this page to find the right one.

| Suite | Location | What it covers | Guide |
|-------|----------|----------------|-------|
| **PHP unit** | `tests/unit/` | PHPUnit tests for backend PHP code (`includes/`, `src/`) | [tests/unit/README.md](unit/README.md) |
| **JavaScript unit** | `tests/js/` | Jest + React Testing Library tests for the `client/` frontend | [tests/js/README.md](js/README.md) |
| **End-to-end (E2E)** | `tests/e2e/` | Playwright tests exercising full flows in a real WordPress environment | [tests/e2e/README.md](e2e/README.md) |
| **QIT** | `tests/qit/` | WooCommerce Quality Insights Toolkit suites (E2E, security, malware, PHPStan) for marketplace certification | [tests/qit/README.md](qit/README.md) |

## Quick start

Once your [Docker environment](../docker/README.md) is running:

```bash
npm test          # Run JS and PHP unit tests
npm run test:js   # JavaScript unit tests only
npm run test:php  # PHP unit tests only
```

See the per-suite guides above for setup, watch mode, coverage, and how to run a single test.

## Deep reference

[`docs/test-matrix.md`](../docs/test-matrix.md) is the authoritative inventory of every suite — including which run in CI, which gate a release, and their reliability notes.
