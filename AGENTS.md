# AGENTS.md — WooPayments Repository Guide

This file provides orientation for AI coding agents (Copilot, Claude, etc.) working in this repository.

## Repository overview

WooCommerce Payments is a WordPress plugin developed by Automattic. The repository uses:

- **PHP** for server-side plugin code (`includes/`, `src/`)
- **TypeScript / React** for the admin UI (`client/`)
- **Docker Compose** for the local development environment

## Local Docker environment

The standard dev environment is started with:

```bash
npm run up:recreate   # First-time setup (builds images, runs docker-setup.sh)
npm run up            # Subsequent starts
npm run down          # Stop containers
```

WordPress is accessible at `http://localhost:${WORDPRESS_PORT}` (default **8082**).

## Multiple checkouts on the same machine

The repo is designed to support multiple simultaneous checkouts — whether through `git worktree` or independent clones — without port or container-name conflicts.

### How it works

Each checkout stores its own Docker configuration in a local `.env` file (gitignored):

| Variable | Purpose | Default |
|---|---|---|
| `WORKTREE_ID` | Human-readable identifier for this checkout | basename of the checkout directory |
| `COMPOSE_PROJECT_NAME` | Namespaces Docker containers, networks, and volumes | `wcpay_<worktree-id>` |
| `WORDPRESS_PORT` | Host port mapped to the WordPress container | `8082` |
| `MYSQL_PORT` | Host port mapped to MySQL | `5678` |
| `PHPMYADMIN_PORT` | Host port mapped to phpMyAdmin | `8083` |

The `docker-compose.yml` uses these variables via `${VAR:-default}` substitution, so the defaults are preserved for checkouts that don't have a `.env` yet.

### Setting up a new checkout or worktree

Run the port-setup script **once** per checkout to generate its `.env`:

```bash
npm run worktree:setup
```

This script:
1. Detects sibling worktrees (via `git worktree list`) and reads their `.env` files to avoid port conflicts.
2. Also checks for ports already bound on the host, so it works with independent clones too.
3. Writes a `.env` file with unique ports and a namespaced `COMPOSE_PROJECT_NAME`.

After running `worktree:setup`, the normal `npm run up` / `npm run up:recreate` commands work as usual — they call `worktree:setup` automatically so subsequent runs are safe.

### Viewing all checkouts

```bash
npm run worktree:status
```

Shows a table of all `git worktree` paths together with their configured ports and Docker container status.

## Git Worktrees

Worktrees provide isolated working directories for parallel feature work. Each worktree automatically gets its own Docker port range (8180–8199 for WordPress, 5679–5699 for MySQL, 8200–8219 for phpMyAdmin).

**Setup:**

```bash
# Create a new worktree for a feature branch
git worktree add ../.worktrees/feat-my-feature feat/my-feature

# Configure its Docker environment
cd ../.worktrees/feat-my-feature
npm run worktree:setup

# Start Docker for this worktree
npm run up:recreate
```

**CRITICAL: Never remove a worktree that is your current working directory.** Removing the CWD causes all subsequent commands in the current shell to fail. Open a new terminal to recover.

**Safe cleanup sequence (always from the main repo):**

```bash
# 1. Switch to main repo FIRST
cd /path/to/main/repo

# 2. Stop and remove Docker containers for the worktree
cd /path/to/worktree && npm run down && cd /path/to/main/repo

# 3. Remove the worktree
git worktree remove /path/to/worktree

# 4. Prune stale metadata
git worktree prune
git branch -d feat/my-feature
```

**Merging worktree work:** `git checkout main` fails inside a worktree when main is checked out elsewhere. Use `git -C` from the main repo:

```bash
git -C /path/to/main/repo merge feat/my-feature
```

## Admin bar worktree indicator

When multiple checkouts are running simultaneously, the WordPress admin bar shows a 🌿 indicator with the `WORKTREE_ID` and port, making it easy to identify which checkout you are looking at.

This is provided by `docker/mu-plugins/wcpay-worktree-indicator.php`, which is automatically loaded by WordPress as a must-use plugin.

## Building and testing

```bash
npm run build       # Production build (JS + PHP)
npm run watch       # Development build with file watching
npm run test        # JS + PHP tests
npm run test:js     # Jest unit tests
npm run test:php    # PHPUnit tests
npm run lint        # JS + CSS + PHP linting
```
