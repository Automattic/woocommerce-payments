# E2E Testing Skill Design

**Last updated:** 2026-02-18

## Goal

Enable agents to verify their changes work from a user's perspective by running E2E tests against a local WooPayments environment with real Stripe test transactions.

## Architecture

### File Structure

```
.claude/skills/e2e-testing/
└── SKILL.md                          # Canonical skill (Claude reads from here)

.agents/skills/e2e-testing/
└── SKILL.md                          # Symlink → ../../../.claude/skills/e2e-testing/SKILL.md

.claude/commands/
└── e2e-testing.md                    # Symlink → ../skills/e2e-testing/SKILL.md

bin/
└── setup-e2e-local.sh                # Automated local.env generator
```

### Credential Auto-Detection

The setup script extracts credentials from existing local infrastructure:

| Variable | Auto-detected from |
|----------|-------------------|
| `E2E_WCPAY_STRIPE_TEST_PUBLIC_KEY` | `transact-platform-server/local/secrets.php` |
| `E2E_WCPAY_STRIPE_TEST_SECRET_KEY` | `transact-platform-server/local/secrets.php` |
| `E2E_WCPAY_STRIPE_TEST_WEBHOOK_SIGNATURE_KEY` | `transact-platform-server/local/secrets.php` |
| `E2E_WCPAY_STRIPE_ACCOUNT_ID` | Dev Docker WP-CLI: `wp option get wcpay_account_data` |
| `TRANSACT_PLATFORM_SERVER_REPO` | Local path to `transact-platform-server` repo |
| `WCP_DEV_TOOLS_REPO` | Dev Docker: `docker/wordpress/wp-content/plugins/woocommerce-payments-dev-tools/` |
| `E2E_WOOPAY_BLOG_ID` | Asked interactively (not available locally) |

### E2E Environment Topology

```
Main Dev Docker (port 8082)          E2E Docker (port 8084)
┌─────────────────────┐              ┌─────────────────────┐
│ wcpay_wp_default    │              │ wcp_e2e_wordpress    │
│ - WooPayments       │              │ - WooPayments        │
│ - Dev Tools         │              │ - Dev Tools (cloned) │
│ - WooCommerce       │              │ - WooCommerce        │
└─────────────────────┘              └─────────────────────┘
         │                                     │
         ▼                                     ▼
Transact Platform Server              Transact Platform Server
(port 8086, local dev)                (port 8088, E2E mode)
```

## Skill Workflows

### 1. Setup (one-time, interactive)

```
Agent detects local.env missing
  → Asks: Local Server (default) or Live Server?
  → For Local Server:
    → bin/setup-e2e-local.sh auto-detects:
      - transact-platform-server path
      - Stripe keys from secrets.php
      - Stripe Account ID from dev Docker WP-CLI
      - Dev tools path
    → Asks for missing values only
    → Generates tests/e2e/config/local.env
  → npm run build:client
  → npm run test:e2e-setup
```

### 2. Run Tests (main agent use case)

```
Agent wants to verify a change
  → Prerequisite checks:
    - Docker running?
    - E2E containers up? (if not: npm run test:e2e-up)
    - Client built? (if not: npm run build:client)
  → Choose scope:
    - Specific spec: npm run test:e2e tests/e2e/specs/wcpay/merchant/specific.spec.ts
    - By grep: npm run test:e2e -- -g "test name"
    - By group: E2E_GROUP=wcpay E2E_BRANCH=merchant
    - All: npm run test:e2e
  → Parse results from playwright-report/
  → On failure: read screenshots/traces from test-results/
```

### 3. Debug Failures

```
Test failed
  → Check test-results/ for:
    - Screenshots (only-on-failure)
    - Trace files (retain-on-failure)
  → Access E2E site: http://localhost:8084/wp-admin/
    - Admin: admin / password
    - Customer: customer / password
  → Check container logs:
    - docker logs wcp_e2e_wordpress
    - docker logs transact_platform_server_wordpress_e2e
  → Run npx playwright show-report for HTML report
```

## Setup Script Design (bin/setup-e2e-local.sh)

```bash
#!/usr/bin/env bash
# Auto-generates tests/e2e/config/local.env from local infrastructure

1. Accept --server-path arg (default: look in ../transact-platform-server, ~/src/transact-platform-server)
2. Validate transact-platform-server exists and has local/secrets.php
3. Parse Stripe keys from secrets.php using grep/sed
4. Get Stripe Account ID from dev Docker via WP-CLI (fallback: ask)
5. Detect dev-tools location (Docker plugins dir, fallback: ask for repo URL)
6. Ask for E2E_WOOPAY_BLOG_ID if not provided as arg
7. Write tests/e2e/config/local.env
8. Print summary and next steps
```

## CLAUDE.md Changes

Expand the existing "E2E Tests" section with:
- Note about `local.env` requirement and setup script
- Link to the skill for full interactive setup
- Quick reference for common test scenarios

## Decisions

- **Canonical skill location**: `.claude/skills/` (Claude-native), symlinked to `.agents/skills/` (cross-agent)
- **Local Server as default**: Most developers have transact-platform-server locally
- **Auto-detection over manual config**: Script finds credentials from existing infrastructure
- **Skill is for complex setup/debug**: CLAUDE.md keeps the quick-reference commands
