# Feature rules and documentation

**Last updated:** 2026-09-05

Read the sections relevant to the current task. Paths and commands in this reference are relative to the repository root unless stated otherwise.

## `.claude/` Documentation Structure

AI-generated docs live in `.claude/`. Permanent developer docs live in `docs/`.

| Directory | Purpose | Naming | Git |
|-----------|---------|--------|-----|
| `.claude/docs/` | Living reference guides | No date prefix; `**Last updated:** YYYY-MM-DD` after title | Tracked |
| `.claude/docs/analysis/` | Research, investigations | `YYYY-MM-DD-description.md` | Gitignored |
| `.claude/docs/plans/` | Implementation plans | `YYYY-MM-DD-description.md` | Gitignored |
| `.claude/tmp/` | Transitory files | Any | Gitignored |
| `.claude/tmp/reviews/` | Code review outputs | `YYYY-MM-DD-description.md` | Gitignored |
| `.claude/tmp/screenshots/` | UI screenshots | `YYYY-MM-DD-description.png` | Gitignored |
| `.claude/local/` | Developer-local drafts | Any; `-outdated` suffix for archives | Gitignored |

**Living docs** must include `**Last updated:** YYYY-MM-DD` after the title. Update on every modification.

**When to persist:**

| Content | Where |
|---------|-------|
| Reference guides, patterns | `.claude/docs/` |
| Research, analysis | `.claude/docs/analysis/` |
| Implementation plans | `.claude/docs/plans/` |
| Code reviews | `.claude/tmp/reviews/` |
| Screenshots | `.claude/tmp/screenshots/` |

Skip persisting trivial lookups, single-file reads, simple Q&A.


## Agent Rules

- Prefer editing existing files over creating new ones
- Check both `src/` and `includes/` when searching for PHP code
- New PHP code in `src/` must follow PSR-4 class/file naming and existing folder conventions. Prefer `WCPay\Internal\Service\PascalCaseService` in `src/Internal/Service/`, register services in the appropriate `src/Internal/DependencyManagement/ServiceProvider/*ServiceProvider.php`, resolve them through `wcpay_get_container()` from legacy `includes/` code, and place matching tests under `tests/unit/src/...` with namespaced PascalCase test classes.
- React components follow WordPress patterns (@wordpress packages)
- Prefer TypeScript for new client code where possible (`.ts`/`.tsx` over `.js`/`.jsx`), especially for new React components and shared data/types.
- For client UI changes, reuse existing WooPayments/WooCommerce components, typography, spacing, colors, and interaction patterns where appropriate; check nearby screens/components before introducing custom styles so new UI remains visually consistent with the rest of the client.
- PHP tests require Docker — ensure it's running before executing
- Always push only current branch: `git push origin HEAD`
- Always pull with rebase: `git pull origin $(git branch --show-current) --rebase`
- **PHPCS method ordering applies to `src/`:** `SlevomatCodingStandard.Classes.ClassStructure` requires public, protected, then private methods in `src/*` and excludes tests. Do not report this sniff against `includes/` or `tests/`. Check the configured scope in `phpcs.xml.dist` and run PHPCS on the file before claiming a lint violation.
- **Migration version_compare:** When adding a migration class in `includes/migrations/`, the `version_compare()` threshold names the release that ships it (e.g., `version_compare( '10.6.0', $previous_version, '>' )` for a migration shipping in 10.6.0) - not the release that introduced the old behavior. `@since` starts out matching it but stays put if the threshold is later bumped; see Database migrations for when bumping is allowed.
- **Styles cache invalidation on plugin update:** `WC_Payments_Styles_Cache::compute_styles_cache_version()` in `includes/class-wc-payments-styles-cache.php` uses `WCPAY_VERSION_NUMBER`, while its cached options persist across updates. Keep `WC_Payments_Styles_Cache::handle_theme_change()` hooked to `woocommerce_woocommerce_payments_updated` so the styles version and stored WooPay appearance are invalidated.
- **Abilities API registrations** (`src/Internal/Abilities/AbilitiesRegistrar.php` + `src/Internal/Abilities/Domain/*.php`): each ability lives in its own `Domain/<AbilityName>.php` class implementing `Automattic\WooCommerce\Abilities\AbilityDefinition`. When you change the code path behind a registered ability (REST controller callback, backing Request class, capability gate), audit the relevant Domain class for required updates (annotations, `input_schema`, `output_schema`, description). List abilities use the WC 10.9 paginated output envelope (`{ <collection>: [...], total_pages, page, per_page }`) via the `AbstractWCPayAbility` base. The feature gates on `class_exists('\Automattic\WooCommerce\Internal\Abilities\AbilitiesLoader')` and silently no-ops on WC < 10.9. Each Domain class points at the controller method that backs it with `@see`; the controller method points back at the Domain class with the same `@see` so the connection is visible from both sides — keep that pairing when adding a new ability. Run `vendor/bin/phpunit --filter 'Abilities'` after such changes — covers both the registrar coordinator and per-ability Domain tests.
- **ExPlat experiments — assign on the Tracks anon-ID from `WC_Tracks_Client::get_identity()`:** ExPlat joins an experiment's assignments to its Tracks events on identity, so any other assignment key reports zero conversions with no error. Resolve the anon-ID through the same helper that stamps the events rather than reading `$_COOKIE['tk_ai']` or minting one via `Jetpack_Tracks_Client`; those diverge when the cookie is absent, and the wrong ID then sticks in user meta. A `wpcom:user_id` identity (stores running the standalone Jetpack plugin) has no joinable key, so sit the experiment out. Resolve identity only after the consent check, since it persists user meta. Consent means `WC_Site_Tracking::is_tracking_enabled()`, the predicate that gates the events; the raw `woocommerce_allow_tracking` option misses the kill-switch filters. Implement this in the `assignment_key()` of each `WCPay\Internal\Experiment\Experiment` subclass.
- **`rawurlencode()` query values before `add_query_arg()`:** it appends values as-is, so a `+` in the base64 anon-ID arrives as a space and keys the assignment on a different identity than the Tracks events. Same pattern as WooCommerce core's copy of this class and PR #11815. See `Experimental_Abtest::request_variation()`.
- **Constants in tests — literals on the assert side:** When a value has a named constant (currency codes like `WCPay\Constants\Currency_Code`, status/enum constants, etc.), use the constant for *incidental* values in the **arrange/act** phases — fixtures, mock return values, setup, and values passed *into* the system under test in their own statements. Use **plain literals** for anything that is the point of an assertion: the expected value, mock `->with()` payloads, **and even an act-input nested inside an `assert*()` wrapper**. Rationale (Meszaros *xUnit Test Patterns* / Fowler): an assertion should pin its expected value *independently* of the code under test — reusing the system-under-test's own constant on both sides couples them and can mask a wrong/drifted constant, and a bare literal (`'EUR'`, `'complete'`) reads better as an expected value than the constant. Don't convert literals where the literal *is* the point: array **keys**, values whose **case** or invalidity is load-bearing (e.g. lowercase Stripe-response codes, rejection-path sentinels), or tests of the constant/formatting logic itself (literals there are the independent oracle). Quick guard: a constant shouldn't appear inside an `assert*()` call — e.g. `grep -n 'assert.*Currency_Code::'` returns nothing.
