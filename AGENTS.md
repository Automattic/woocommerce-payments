# WooPayments agent guide

WooPayments is a WordPress/WooCommerce payment plugin with a PHP backend and React/TypeScript client. Use **WooPayments** in user-facing text; keep the `woocommerce-payments` slug, text domain and existing public symbols for compatibility. Read version requirements from `woocommerce-payments.php` and `package.json`.

## Load the relevant references

Before working in an area below, read its linked reference and follow the applicable constraints. They are not loaded automatically. These references own the detailed rules and commands; load only the sections needed for the task. Paths and commands are relative to the repository root unless stated otherwise.

| Task | Required reference |
|------|--------------------|
| PHP, checkout, server requests, WooCommerce hooks or code placement | [Architecture and code placement](.claude/docs/agent-architecture.md) |
| Setup, builds, tests, dependencies, commits, PRs, Docker or tunnels | [Development and delivery](.claude/docs/agent-development.md) |
| Public signatures, hooks, subclass overrides, REST/Abilities, asset handles, options/meta, migrations, upstream contracts, multisite or install paths | [Compatibility and migrations](.claude/docs/agent-compatibility.md) |
| Styles cache, Abilities, ExPlat/Tracks, query encoding, test constants or agent documentation | [Feature rules and documentation](.claude/docs/agent-feature-rules.md) |
| E2E execution, setup or debugging | [E2E skill](.claude/skills/e2e-testing/SKILL.md) and [test overview](tests/README.md) |
| Code review | [.claude/review-rules.md](.claude/review-rules.md), plus the references for the affected areas |

For feature-specific investigations, the architecture reference indexes payment flow, mode, promotions, capital, disputes and payment-method lifecycle guides. Read [src/README.md](src/README.md) for DI and PSR-4 work, [includes/core/README.md](includes/core/README.md) for the Request/Response API, and [includes/core/CONTRIBUTING.md](includes/core/CONTRIBUTING.md) before adding Request classes.

## Implementation essentials

- Preserve the payment layers: checkout JS → gateway orchestration → typed Request classes → API client → HTTP/Jetpack → server → Stripe. Feature and gateway code must use typed Requests, not the API client directly. Do not modify the HTTP/Jetpack layer directly.
- Search both `src/` and `includes/`. Prefer existing files; new PHP belongs in `src/` with PSR-4, DI registration and matching tests, except when extending established legacy code or adding migrations in `includes/migrations/`.
- In `includes/`, keep the file docblock immediately after `<?php`; do not add `declare(strict_types=1)`. Import or fully qualify global classes from namespaced files. Run `composer run phpstan` before pushing new cross-namespace references.
- Reuse nearby WooPayments/WooCommerce UI and WordPress components, tokens and patterns. Check the WordPress/WooCommerce Storybooks before creating custom components. Prefer TypeScript for new client code and `@wordpress/data` for shared state.
- Trace upstream WooCommerce behavior for base classes, hooks and order/customer/product operations, particularly order-status emails and per-request hook costs. Check the supported dependency version and the actual configured lint scope.
- Verify assumptions against the current implementation. Trace affected callers and the source of the data before changing shared behavior.
- Handle failures, missing data and successful empty results explicitly. Preserve meaningful `false`, `0` and `null` values rather than treating them as absence.
- When fixing a bug, check nearby code and other callers for the same cause. Report related issues outside the change's scope rather than silently expanding it.

## Compatibility essentials

- Treat externally consumed symbols as contracts even under `Internal`. Prefer additive changes and deprecate existing public symbols instead of renaming or removing them.
- Read the compatibility reference before changing a contract. Preserve hook arguments and timing, overridable method calls, script/style handles and stored data, as well as PHP signatures. State the impact in the PR description. If the impact cannot be established, flag the affected change for review before proceeding with it.
- Validate hook inputs and filter outputs. Guard lifecycle/global dependencies in REST, cron, CLI and webhook contexts. Use WordPress path/URL APIs; account for multisite and say when it was not tested.
- Migration thresholds name the shipping release. Check fresh installs, repeat execution and downgrades; retain the original `@since`. Follow the migration reference before bumping a threshold or reshaping stored data.

## Verification and local environment

- Match checks to the affected behavior and complete required repository checks. Distinguish passes, failures and checks that could not run. Add tests that protect meaningful behavior; broaden or repeat checks only for a new change, failure or unresolved risk.
- Tests must exercise the changed behavior and assert an observable outcome. Do not mock away the behavior being checked; await asynchronous assertions and restore shared test state. Confirm a regression test fails without the fix where practical. Check version and feature gates before citing green CI.
- PHP tests require Docker. Read the development reference for commands and use the E2E skill when an E2E check is needed. Confirm tested code and built assets match the intended revision; run `pnpm run watch` when testing local frontend/admin edits.
- Check `.env` for the WordPress port. Preserve existing data, unrelated containers and user processes. Do not reset the local admin password unless explicitly requested; the development login is `admin` / `admin`.
- Never remove a worktree that is the current working directory; perform any authorised cleanup from the main clone.

## Git and delivery

- Base PRs on `develop`; `trunk` is the release branch.
- Before pushing, check whether the branch belonged to a merged PR. If so, create a new branch from `develop`. Push only the current branch with `git push origin HEAD` (or `git push -u origin HEAD`). Pull with rebase.
- Before creating a PR, add and commit a changelog entry with `pnpm run changelog:add --type=<type> --entry="<description>"`. Use [.github/PULL_REQUEST_TEMPLATE.md](.github/PULL_REQUEST_TEMPLATE.md) and open the PR in draft mode.
- After creating the PR, ask the author to review its description and testing instructions, then manually test. Add `pr: needs review` and reviewers only after manual testing and only when explicitly requested.
- Explain non-obvious constraints once, near their owner, and avoid comments that narrate the code. Update affected comments, reference docs and the PR description when the implementation changes.
- Keep incident details and command catalogs in the linked references. Add agent rules only when they apply beyond the incident that prompted them, once, in the appropriate owner, and update living reference dates when editing them.
