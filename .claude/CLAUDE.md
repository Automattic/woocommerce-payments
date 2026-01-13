# CLAUDE.md - WooCommerce Payments Repository Guide

This file provides context about the WooCommerce Payments repository to help Claude Code assist more effectively.

## Repository Overview

WooCommerce Payments (WCPay) is a WordPress plugin that provides payment processing capabilities for WooCommerce stores. It's a complex project combining PHP backend code with a React-based admin interface.

**Key Info:**
- Plugin Name: WooPayments
- License: GPL-3.0-or-later
- Repository: github:Automattic/woocommerce-payments

**Version & Requirements:**
- See `woocommerce-payments.php` header for current version and WordPress/WooCommerce/PHP requirements
- See `package.json` for Node.js version requirements (engines field)

## Directory Structure

### PHP Code
- **`src/`** - Modern PHP code using PSR-4 autoloading and dependency injection
  - Uses a service `Container` for dependency injection
  - Preferred location for new PHP code
- **`includes/`** - Legacy PHP codebase organized by feature
  - `admin/`, `payment-methods/`, `subscriptions/`, `multi-currency/`, etc.
  - Still actively used but prefer `src/` for new code

### Frontend Code
- **`client/`** - React/TypeScript frontend application
  - `components/` - Reusable UI components
  - `settings/`, `checkout/`, `onboarding/` - Feature areas
  - `data/` - Redux state management (@wordpress/data)
  - Uses React 18.3 and TypeScript

### Tests
- **`tests/unit/`** - PHP unit tests (PHPUnit)
- **`tests/js/`** - JavaScript test configuration
- **`tests/e2e/`** - End-to-end tests (Playwright)
- JS tests are co-located with source files in `client/**/__tests__/`

### Build & Config
- **`webpack/`** - Modular webpack configuration (shared, production, development, HMR)
- **`tasks/`** - Build and release automation
- **`bin/`** - Helper scripts
- **`docker/`** - Docker development environment

## Technology Stack

**Backend:** PHP, WordPress APIs, WooCommerce hooks, Composer
**Frontend:** React, TypeScript, @wordpress/data (Redux), SCSS
**Build:** Webpack, Babel, PostCSS, @wordpress/scripts
**Testing:** PHPUnit, Jest, Playwright, React Testing Library
**Quality:** ESLint, PHPCS, Psalm, TypeScript, Prettier

*See `composer.json`, `package.json`, and `woocommerce-payments.php` for specific version requirements*

## Common Commands

### Development
```bash
npm install             # Install dependencies
npm start               # Watch JS changes (alias: npm run watch)
npm run hmr             # Hot module replacement server
npm run up              # Start Docker environment
npm run dev             # Start Docker + watch mode
```

### Testing

**PHP Tests:**
```bash
# Standard approach
npm run test:php                    # Run all PHP tests in Docker
npm run test:php-watch              # Watch mode
npm run test:php-coverage           # With coverage
```

**JavaScript Tests:**
```bash
npm run test:js                     # Run all JS tests
npm run test:watch                  # Watch mode
npm run test:debug                  # Debug mode
npm run test:update-snapshots       # Update snapshots
```

**E2E Tests:**
```bash
npm run test:e2e                    # Run E2E tests
npm run test:e2e-ui                 # UI mode
npm run test:e2e-up                 # Setup test environment
npm run test:e2e-down               # Teardown
```

### Build & Quality
```bash
npm run build:client                # Build production JS
npm run build                       # Build release package
npm run lint                        # Run all linters
npm run lint:js                     # ESLint + TypeScript
npm run lint:php                    # PHPCS
npm run lint:php-fix                # Auto-fix PHP issues
npm run format                      # Format with Prettier
npm run psalm                       # PHP static analysis
```

### Other
```bash
npm run changelog                   # Add changelog entry
npm run i18n:pot                    # Generate translations
```

## Development Workflows

### Code Organization
- **New PHP code:** Use `src/` with dependency injection
- **Legacy PHP:** Lives in `includes/`, prefer refactoring to `src/`
- **Frontend:** React components in `client/` with TypeScript
- **Tests:** Mirror source structure in `tests/unit/` for PHP

### Testing Conventions
- PHP tests use PHPUnit and follow WordPress testing practices
- JS tests use Jest with @wordpress/scripts preset
- Co-locate JS tests with source files or in `__tests__/` directories
- PHP tests run in Docker via `npm run test:php` (see `bin/run-tests.sh`)

### Git Workflow
- Main branch for PRs: `develop`
- Release branch: `trunk`
- Husky manages git hooks
- **Before creating a PR:**
  - Must run `npm run changelog add` and commit the changelog entry (choose 'patch' if change is not significant)
  - Changelog must be committed and pushed before creating the PR
- Use PR template from `.github/PULL_REQUEST_TEMPLATE.md` when creating pull requests
  - Include testing instructions
  - Check mobile testing requirement
  - Link to release testing docs post-merge

### Docker Environment
- WordPress: http://localhost:8082
- phpMyAdmin: http://localhost:8083
- MySQL: localhost:5678
- Xdebug ready (requires IDE path mapping)

### Dependency Management
- WordPress dependencies extracted automatically via webpack plugin
- External packages added via `requestToExternal` and `requestToHandle` in webpack.config.js
- Use Composer for PHP dependencies
- Use npm for JavaScript dependencies

### Changelog
- Use `npm run changelog` to add entries
- Types: Add, Fix, Update, Dev
- Entries go in `changelog/` directory

## Important Configuration Files

| File | Purpose |
|------|---------|
| `package.json` | npm scripts and dependencies |
| `composer.json` | PHP dependencies and autoloading |
| `webpack.config.js` | Main webpack entry |
| `phpunit.xml.dist` | PHPUnit configuration |
| `phpcs.xml.dist` | PHP coding standards |
| `tests/js/jest.config.js` | Jest configuration |
| `tests/e2e/playwright.config.ts` | E2E test config |
| `tsconfig.json` | TypeScript configuration |
| `.eslintrc` | ESLint rules |

## Version Support Policy
- WordPress: Strict L-2 (supports current and 2 previous major versions)
- WooCommerce: Loose L-2
- See `docs/version-support-policy.md` for details

## Documentation
- `README.md` - Main setup and overview
- `CONTRIBUTING.md` - Contribution guidelines
- `tests/README.md` - Testing guide
- `docker/README.md` - Docker setup
- `includes/core/README.md` - Extensibility docs
- `docs/` - Additional documentation

## Tips for Claude
- Prefer editing existing files over creating new ones
- Check both `src/` and `includes/` when searching for PHP code
- React components follow WordPress coding patterns (@wordpress packages)
- Test files mirror source structure
- PHP tests require Docker - ensure it's running before executing tests
- Use `npm run test:php` to run all tests or edit the command to pass PHPUnit filters
- When pushing, always push only the current branch: `git push origin HEAD` (not `git push` which tries to push all configured branches)
- When pulling, always pull only the current branch: `git pull origin $(git branch --show-current)` or `git pull --rebase origin HEAD`
