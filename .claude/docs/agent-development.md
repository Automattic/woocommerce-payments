# Development and delivery

**Last updated:** 2026-09-05

Read the sections relevant to the current task. Paths and commands in this reference are relative to the repository root unless stated otherwise.

## Common Commands

### Development
```bash
pnpm install                        # Install dependencies
pnpm start                          # Watch JS changes (alias: pnpm run watch)
pnpm run watch                      # Rebuild assets while developing locally
pnpm run hmr                        # Hot module replacement server
pnpm run up                         # Start Docker environment at http://localhost:8082
pnpm run dev                        # Start Docker + watch mode
```

### PHP Tests
```bash
pnpm run test:php                    # Run all (first run sets up environment)
pnpm run test:php-watch              # Watch mode
pnpm run test:php-coverage           # With coverage

# Specific test (after initial pnpm run test:php setup):
docker compose exec -u www-data wordpress bash -c \
  "cd /var/www/html/wp-content/plugins/woocommerce-payments && \
  vendor/bin/phpunit --configuration phpunit.xml.dist --filter 'TestClassName::test_method_name'"
```

### JavaScript Tests
```bash
pnpm run test:js                     # Run all JS tests
pnpm run test:watch                  # Watch mode
pnpm run test:debug                  # Debug mode
pnpm run test:update-snapshots       # Update snapshots
```

### E2E Tests

E2E tests use Playwright in Docker containers against a local WordPress site with real Stripe test transactions.

**First-time setup:** Run `bin/setup-e2e-local.sh` to auto-generate `tests/e2e/config/local.env` from your local infrastructure, then `pnpm run build:client && pnpm run test:e2e-setup`. See the E2E skill (`/e2e-testing`) or `tests/e2e/README.md` for full details.

```bash
pnpm run test:e2e                    # Run all E2E tests (headless)
pnpm run test:e2e-ui                 # Interactive UI mode (localhost:8077)
pnpm run test:e2e-setup              # First-time E2E environment setup
pnpm run test:e2e-up                 # Start existing E2E containers
pnpm run test:e2e-down               # Stop E2E containers

# Run specific tests
pnpm run test:e2e tests/e2e/specs/wcpay/merchant/  # All merchant tests
pnpm run test:e2e tests/e2e/specs/wcpay/shopper/   # All shopper tests
pnpm run test:e2e -g "dispute"                   # By test name
```

**E2E environment ports:** WordPress `:8084` | phpMyAdmin `:8085` | Transact Server `:8088` | Playwright UI `:8077`

### Build & Quality
```bash
pnpm run build:client                # Build production JS
pnpm run build                       # Build release package
pnpm run lint                        # Run all linters
pnpm run lint:js                     # ESLint + TypeScript
pnpm run lint:php                    # PHPCS
pnpm run lint:php-fix                # Auto-fix PHP issues
pnpm run format                      # Format with Prettier
pnpm run psalm                       # PHP static analysis
```

### Changelog
```bash
pnpm run changelog                   # Interactive
pnpm run changelog:add --type=fix --entry="Fixed a bug"
pnpm run changelog:add --type=add --entry="Added feature" --significance=minor
```
Types: `add`, `fix`, `update`, `dev`. Significances: `patch` (default), `minor`, `major`. Entries go in `changelog/`.

### Dependencies & supply-chain cooldown

All pnpm settings live in `pnpm-workspace.yaml` — since pnpm 11 the `package.json` `pnpm` field and non-auth `.npmrc` settings are no longer read. Three supply-chain guards are on:

- **`minimumReleaseAge: 1440`** — pnpm won't resolve an npm version until it is 1 day old, closing the window between a malicious publish and its detection. It gates *resolution* (`pnpm add`/`update`, lockfile regeneration); on pnpm ≥ 11.1.3 `pnpm install --frozen-lockfile` also re-validates existing lockfile entries and aborts on a too-young pin (`ERR_PNPM_MINIMUM_RELEASE_AGE_VIOLATION`). Dependabot's 7-day cooldown keeps its PRs clear of this.
- **`blockExoticSubdeps: true`** — transitive dependencies must resolve from the registry; a transitive git/tarball URL fails the install.
- **`allowBuilds`** — the build-script allowlist (`strictDepBuilds` is on by default, so a dependency whose install script is not listed here fails the install). List a package as `true` to let it build, `false` to silence it.

**Pushing an urgent security bump through the cooldown:** add the package to `minimumReleaseAgeExclude` in `pnpm-workspace.yaml` (bare name or exact `pkg@version`), or run `pnpm audit --fix`, which auto-exempts advisory-patched versions. Remove the exclude once the version ages past the window.

### Other
```bash
pnpm run i18n:pot                    # Generate translations
```


## Git Workflow

- **PR base:** `develop` | **Release branch:** `trunk`
- Husky manages git hooks

**Before pushing:** Verify branch isn't from a merged PR:
```bash
gh pr list --head "$(git branch --show-current)" --state merged --json number --jq length
```
If non-zero, create a new branch off `develop` instead.

**Before creating a PR:**
- Add and commit a changelog entry: `pnpm run changelog:add --type=<type> --entry="<description>"`
- Use PR template from `.github/PULL_REQUEST_TEMPLATE.md`
- Open PRs in **draft mode** (`gh pr create --draft`).

**After creating a PR:**
- Ask the author to review the PR description and testing instructions, then manually test the changes.
- Add the `pr: needs review` label and reviewers only after the PR has been manually tested, and only when explicitly asked.


## Git Worktrees

Worktrees provide isolated working directories for parallel feature work. Each worktree gets its own Docker port range (8180-8199).

**Setup:** `pnpm run worktree:setup` (configures `.env`), `pnpm run worktree:status` (list all), `pnpm run tube:start` (tunnel — see [Jurassic Tube](#jurassic-tube-ssh-tunnels))

**CRITICAL: Never remove a worktree that is your current working directory.** Removing the CWD makes ALL subsequent commands fail irrecoverably — no `cd`, no subshell can fix it.

**Safe cleanup sequence (always from the main repo):**
```bash
# 1. Switch to main repo FIRST
cd /path/to/main/repo

# 2. Now safe to remove
git worktree remove /path/to/worktree

# 3. Clean up
git worktree prune
git branch -d worktree-feat/branch-name
```

**Merging worktree work:** `git checkout main` fails inside a worktree when main is checked out elsewhere. Use `git -C` from the main repo:
```bash
cd /path/to/main/repo
git -C /path/to/main/repo merge worktree-feat/branch-name
```


## Docker Environment

| Service | URL/Port |
|---------|----------|
| WordPress | `http://localhost:<PORT>` (check `.env`; default 8082, worktrees 8180-8199) |
| phpMyAdmin | `http://localhost:8083` |
| MySQL | `localhost:5678` |

- First-time: `pnpm run up:recreate`
- Subsequent: `pnpm run up` brings the local WordPress server up at `http://localhost:8082` by default.
- When testing local frontend/admin UI changes, run `pnpm run watch` so built assets are regenerated.
- Xdebug ready (requires IDE path mapping)
- Local WP admin credentials are `admin` / `admin`. Do **not** change the local admin password with `wp user update admin --user_pass=...` unless explicitly requested. If browser/MCP login fails, ask before resetting credentials.


## Jurassic Tube (SSH Tunnels)

Jurassic Tube creates public HTTPS tunnels (`<subdomain>.jurassic.tube`) to your local WordPress instance. Useful for testing webhooks, mobile devices, or sharing a dev site.

### Commands

| Command | Purpose |
|---------|---------|
| `pnpm run tube:setup` | First-time setup: registers subdomain, generates SSH keys, creates `bin/jurassictube/config.env` |
| `pnpm run tube:start` | Starts tunnel (WordPress URLs resolve automatically via `wp-config.php`) |
| `pnpm run tube:stop` | Stops tunnel |
| `pnpm run tube:status` | Shows subdomain, port, tunnel state, and worktree info |

### Worktree Support

`tube:start` is worktree-aware. It auto-detects worktrees and handles configuration automatically:

**Default (one tunnel at a time):**
- In a worktree, `tube:start` copies config/keys from the main repo if no local config exists
- Reads `WORDPRESS_PORT` from the worktree's `.env` to forward the tunnel to the correct port
- Only one tunnel can use a subdomain at a time — starting in a worktree redirects the subdomain to the worktree's port

**Per-worktree subdomains (parallel tunnels):**
- Run `pnpm run tube:setup` in the worktree to register a dedicated subdomain
- Each worktree then has its own `bin/jurassictube/config.env` with a unique subdomain
- Multiple tunnels can run simultaneously on different subdomains

**Agent workflow for tunnels in worktrees:**
```bash
# 1. Ensure worktree has a port assigned
pnpm run worktree:setup

# 2. Ensure Docker is running
pnpm run up

# 3. Start tunnel (auto-copies config from main repo if needed)
pnpm run tube:start

# 4. When done
pnpm run tube:stop
```

**Key details:**
- `bin/jurassictube/` is gitignored — config and keys are never committed
- Port is resolved at runtime from `WORDPRESS_PORT` in `.env` (never hardcoded in config)
- WordPress URLs resolve automatically via `wp-config.php` (`DOCKER_HOST` from `HTTP_HOST`) — no DB updates needed
