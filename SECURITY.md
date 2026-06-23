# Security Policy

Full details of the Automattic Security Policy can be found on [automattic.com](https://automattic.com/security/).

## Supported Versions

Generally, only the latest version of the extension has continued support.  In some cases, we may opt to backport critical vulnerabilities fixes to previous versions.

## Reporting a Vulnerability

[WooPayments](https://woocommerce.com/payments/) is an open-source plugin for WooCommerce. Our HackerOne program covers the plugin software.

**For responsible disclosure of security issues and to be eligible for our bug bounty program, please submit your report via the [HackerOne](https://hackerone.com/automattic) portal.**

_Please note that the **WordPress software is a separate entity** from Automattic. Please report vulnerabilities for WordPress through [the WordPress Foundation's HackerOne page](https://hackerone.com/wordpress)._

## Guidelines

We're committed to working with security researchers to resolve the vulnerabilities they discover. You can help us by following these guidelines:

*   Follow [HackerOne's disclosure guidelines](https://www.hackerone.com/disclosure-guidelines).
*   Pen-testing Production:
    *   Please **setup a local environment** instead whenever possible. Most of our code is open source (see above).
    *   If that's not possible, **limit any data access/modification** to the bare minimum necessary to reproduce a PoC.
    *   **_Don't_ automate form submissions!** That's very annoying for us, because it adds extra work for the volunteers who manage those systems, and reduces the signal/noise ratio in our communication channels.
    *   To be eligible for a bounty, all of these guidelines must be followed.
*   Be Patient - Give us a reasonable time to correct the issue before you disclose the vulnerability.

We also expect you to comply with all applicable laws. You're responsible to pay any taxes associated with your bounties.

## Accepted advisories

This section documents security advisories that surface in `npm audit` or GitHub Dependabot but are **not exploitable in WooPayments' usage**. Each entry should explain why the vulnerable code path is unreachable, so reviewers can dismiss the corresponding Dependabot alert with confidence and revisit it if the situation changes.

### `locutus` (`@woocommerce/number` → `locutus@2.x`)

| Advisory | Severity | Affected | Vulnerable function |
|---|---|---|---|
| [GHSA-vh9h-29pq-r5m8](https://github.com/advisories/GHSA-vh9h-29pq-r5m8) | critical | `<=3.0.24` | `create_function()` |
| [GHSA-fp25-p6mj-qqg6](https://github.com/advisories/GHSA-fp25-p6mj-qqg6) | high | `<=3.0.24` | `call_user_func_array()` |
| [GHSA-vc8f-x9pp-wf5p](https://github.com/advisories/GHSA-vc8f-x9pp-wf5p) | moderate | `<=3.0.24` | prototype pollution (incomplete fix for CVE-2026-25521) |
| [GHSA-4mph-v827-f877](https://github.com/advisories/GHSA-4mph-v827-f877) | moderate | `<=3.0.24` | `unserialize()` prototype pollution |

**Why it's not exploitable here.** `locutus` reaches our bundle only via `@woocommerce/number@2.5.0`, which imports a single function — `locutus/php/strings/number_format` — and re-exports it as `numberFormat`. None of the vulnerable functions (`create_function`, `call_user_func_array`, `unserialize`, prototype-pollution paths) are imported, instantiated, or transitively called from `@woocommerce/number`'s public API. Our codebase calls `numberFormat` from one site (`client/utils/index.js`'s `applyThousandSeparator`) on integer transaction counts; the input never flows into the vulnerable surface.

**Why we don't override.** `locutus@>=3.0.25` is not vulnerable, but it changes its module shape from CommonJS default-export to a named ESM export, which breaks `@woocommerce/number`'s `import numberFormatter from 'locutus/...'` line. No published `@woocommerce/number` or `@woocommerce/currency` version exists that uses the patched locutus, so the chain currently has no clean upstream fix.

**Re-evaluate when:** a new `@woocommerce/number` or `@woocommerce/currency` is published that bumps the `locutus` dependency to `>=3.0.25`, or when `@woocommerce/number` switches to a non-locutus implementation. Until then, plugin maintainers should manually re-check this exception during regular dependency and security review.

The transitive `@woocommerce/components`, `@woocommerce/currency`, and `@woocommerce/number` advisories that GitHub flags are downstream of this same locutus chain — they resolve automatically once locutus is fixed upstream.
