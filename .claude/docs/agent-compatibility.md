# Compatibility and migrations

**Last updated:** 2026-09-05

Read the sections relevant to the current task. Paths and commands in this reference are relative to the repository root unless stated otherwise.

## Version Support

- **WordPress:** Strict L-2 (current + 2 previous major versions)
- **WooCommerce:** Loose L-2
- Details: `docs/version-support-policy.md`


## Backward Compatibility

WooPayments has backward-compatibility obligations in **both directions**. Any change to a **public or externally exposed** class, interface, function, or method signature is **high-risk** and **must state its backward-compatibility impact in the PR description** — regardless of whether the symbol lives under the `WCPay\Internal` namespace.

Treat a symbol as **externally exposed** when it is implemented or consumed outside this plugin — by extensions, themes, WooPay, mobile apps, or other plugins — even if it lives under `Internal`. `Internal` is **not** a stability guarantee. When in doubt, assume it is exposed and state the BC impact.

**As a producer of public API.** WooPayments exposes a large surface that third parties consume:
- `do_action`/`apply_filters` hooks — `wcpay_*` and the `woocommerce_*` hooks this plugin fires (renaming a hook, changing its args, or dropping a filter's passthrough value is breaking).
- The Request/Response class layer (`includes/core/server/request/`) — deliberately hook-extensible (`wcpay_*_request` filters), so its constructors, setters, and validation are consumed externally.
- The public gateway class `WC_Payment_Gateway_WCPay` and payment-method classes.
- REST controllers (`includes/admin/`, `includes/reports/`, `includes/multi-currency/`) — route paths, params, response shapes — and registered Abilities.
- `wcpay_*` option keys and stored meta.

Adding a **required** method to an interface that external code can implement is backward-incompatible — existing implementers fatal on load. Prefer a non-breaking alternative: add the method to a concrete class, introduce a separate new interface, or provide a default via an abstract base class.

**Deprecate, don't rename.** Never rename or remove an existing public symbol (class, interface, method, constant, hook, option key) in place. Mark the old one `@deprecated`, add the replacement alongside it, and keep both working through a deprecation window so consumers can migrate.

**As a consumer of upstream WooCommerce contracts.** WooPayments extends and implements upstream WooCommerce classes and interfaces — e.g. `WC_Payment_Gateway_CC`, `Blocks\Payments\Integrations\AbstractPaymentMethodType`, and `Blocks\Integrations\IntegrationInterface`. The `Internal` namespace is not a stability guarantee upstream either: WooCommerce can change these contracts, and doing so is exactly the class of break this guardrail exists to prevent (a WC 10.9.0 change to an `Internal` `FeedInterface` fataled older WooCommerce Stripe Gateway versions on load). When implementing an upstream contract, keep the implementation compatible across the supported WC range (L, L-1, L-2) and guard against contract changes rather than assuming the interface is frozen.

### The compatibility surface is wider than PHP signatures

Class and function signatures are not the only contracts. The following are equally binding: a change to any of them is **high-risk** and requires the same backward-compatibility impact statement in the PR description.

**Hooks and filters are public contracts.** Every `do_action` and `apply_filters` call — the `wcpay_*` hooks and the `woocommerce_*` hooks this plugin fires — is an interface third-party callbacks depend on. Removing a hook, renaming it, or removing/reordering its arguments breaks every attached callback. Changing *when* or *whether* a hook fires can break consumers that depend on its timing. Additive is the safe path: append new arguments at the end, never remove or reorder existing ones. To retire a hook, fire it through `do_action_deprecated()` / `apply_filters_deprecated()` for a deprecation window instead of deleting it.

**Never trust data that flows through hooks.** Keep hook callback parameters untyped and validate or coerce the value before passing it to strictly typed code, since any callback can receive a value another one produced. And when firing a filter, validate the final return value before using it, since any callback in the chain can return the wrong thing.

**Overridable classes are contracts too, including which internal methods get called.** Third-party code subclasses `WC_Payment_Gateway_WCPay` and the payment-method classes and overrides individual public and protected methods, so those methods are contracts: changing their signatures or removing them breaks subclasses even when no caller inside this plugin remains. Adding a fast path or skip that avoids calling an overridable method silently disables those overrides even though no signature changed: the subclass's code simply stops running. When optimizing such a class, ensure overridable methods are still invoked on every code path, or treat the change as breaking.

**Registered script and style handles are public contracts.** Third-party code enqueues this plugin's handles and lists them as dependencies — the handles this plugin registers for admin, checkout, and express-checkout assets (both the `WCPAY_*` and `wcpay-*` forms) — including handles that were only ever registered incidentally. Renaming or removing a handle breaks those consumers. To rename with a compatibility window, register the legacy handle as an alias that depends on the new handle (the same pattern WordPress core uses for `jquery` → `jquery-core`); do not register the same file under both handles, or pages with mixed consumers will load it twice.

**Do not assume global state.** WooPayments code runs in admin, REST, CLI, cron, webhook, and front-end contexts, and not all of them set the globals a front-end request does (`$post`, `$wp_query`, an initialized session or cart). Webhook and cron handlers in particular run with no cart and no logged-in customer. A newly introduced read of a global, or of `WC()->…` state, in a path reachable outside a standard request is a fatal or a silent misbehavior in the contexts that do not set it. Guard the exact dependency explicitly: use `function_exists`/`class_exists` for symbols, `isset` for variables, `did_action` for lifecycle state, and verify that `WC()` and the required component are initialized before dereferencing `WC()->…`.

**Do not assume single-site.** Multisite changes where data lives: site-scoped vs network-scoped options (`get_option` vs `get_site_option`), per-site tables, user roles and capabilities, and upload paths all differ. A change that reads or writes site state must state in its PR whether it behaves correctly under multisite — and if it was not tested there, say so explicitly.

**Do not assume install layout.** WordPress could be configured to run in a subdirectory, with relocated `wp-content`, and behind reverse proxies. Never build paths or URLs by concatenation from the domain root; derive them (`plugins_url()`, `plugin_dir_path()`, `wp_upload_dir()`, and mind the `home_url()` vs `site_url()` distinction). A path that works on a root install and breaks elsewhere is a compatibility bug, not an edge case.

### Database migrations

Migration classes live in `includes/migrations/` and run on `woocommerce_woocommerce_payments_updated`, gated by `version_compare` against the version the store last had installed.

- **The threshold is the release that runs it; only convergent migrations may bump it.** A new migration's threshold names the release that ships it; `@since` records when the class landed and never changes afterward. Bumping the threshold re-runs the migration on every store below the new value - the repo does this deliberately when a second run lands in the same end state (see the registration comment above the `Payment_Method_Deprecation_Settings_Update` hooks in `class-wc-payments.php`, which carries the live thresholds). Convergence is the test, not delete-only: that sweep rewrites `upe_enabled_payment_method_ids` and disables the deprecated gateway, and is still safe to bump because a second run filters an already-filtered list. If a second run would land somewhere different, write a new class.
- **A missing version option runs everything.** `get_option( 'woocommerce_woocommerce_payments_version' )` returns `false` on a fresh install, which makes the `'>'` threshold gate true and the `'<='` early-return guard false - both styles in use, so every migration also fires on brand-new stores, and again if the option is ever lost. Guard `empty( $previous_version )` when running on a fresh store would be wrong, as `Multi_Currency_Cache_Autodetect_Existing_Install` does.
- **A downgrade must not fatal or corrupt data.** Deleting a dead option is fine - old code reads the default. Dropping a key old code still reads costs the merchant that setting: `Migrate_Express_Checkout_Locations` and `Migrate_Payment_Request_To_Express_Checkout_Enabled` do exactly that, a deliberate trade, not a pattern to copy. Reshaping a value in place fatals or silently misbehaves, since old code still parses that key: put the new shape under a new key, prefer leaving the old key readable for a release, and if you cut over, state what a downgrade costs in the PR description.

### Before changing any public or externally exposed surface (agent checklist)

1. Identify the contract you are touching: signature, hook, global/scope expectation, site topology, or install layout.
2. Assume unseen consumers. You cannot enumerate third-party code; if the surface is reachable from outside this plugin, someone consumes it.
3. Prefer the additive path (new optional method, appended hook argument, new symbol + deprecation) over changing what exists.
4. State the impact in the PR description: what changed, who could consume it, and why it is safe or what the deprecation path is.
5. If you cannot establish the impact, stop and flag it to the user as needing review.

> Core's [AGENTS.md Backward Compatibility](https://github.com/woocommerce/woocommerce/blob/trunk/AGENTS.md#backward-compatibility) section carries the same guardrail.
