# WooPayments

This is a feature plugin for accepting payments via a WooCommerce-branded payment gateway.

## Dependencies

-   WooCommerce

## Version support policy

We adopt the L-2 version support policy for WordPress core strictly, and a loose L-2 policy for WooCommerce. See [more details](./docs/version-support-policy.md).

## Development

### Install dependencies & build

This repo pins its pnpm version via the `packageManager` field in `package.json`. The simplest way to get the matching version is [Corepack](https://nodejs.org/api/corepack.html) (bundled with Node), which is also what CI uses:

-   `corepack enable pnpm`
-   `pnpm install`
-   `composer install`
-   `pnpm run build:client`, or if you're developing the client you can have it auto-update when changes are made: `pnpm start`

If you run into errors with `pnpm install` it may be due to node version, try `nvm install` followed by `nvm use` then try again.

If you installed pnpm standalone (e.g. via Homebrew) and see an error like `The packageManager dependency "pnpm@..." in pnpm-lock.yaml must use a registry package path and an integrity-only resolution`, you're on a newer pnpm than the repo pins. Run `corepack enable pnpm` to use the pinned version instead.

When running the `composer install/update`, composer may prompt you for a GitHub OAuth token before it can fetch the `subscriptions-core` package from github.

```
Loading composer repositories with package information
GitHub API limit (0 calls/hr) is exhausted, could not fetch https://api.github.com/repos/automattic/woocommerce-subscriptions-core. Create a GitHub OAuth token to go over the API rate limit. You can also wait until ? for the rate limit to reset.

Head to https://github.com/settings/tokens/new?scopes=repo&description=Composer+XXXXXX to retrieve a token. It will be stored in "/Users/yourname/.composer/auth.json" for future use by Composer.
```

To fix this up, follow the link provided in the prompt and paste the token into the terminal window to continue.

### Extending WooPayments

If you are extending WooPayments, or building on top of it, please refer to the [core docs](includes/core/README.md) and directory (`includes/core`) for guides and recommended ways of doing it.

## Setup

### Docker environment (recommended)

For detailed Docker setup instructions, see [docker/README.md](docker/README.md).

Quick start:
```bash
pnpm install                  # Install dependencies
pnpm run up:recreate          # Start WordPress and run setup (auto-starts infrastructure if needed)
```

Your site will be available at `http://localhost:<PORT>/wp-admin/` (check `.env` for your port; default is 8082).

### Manual setup

Install the following plugins:

-   WooCommerce
-   WCPay Dev Tools (clone or download [the GitHub repo](https://github.com/Automattic/woocommerce-payments-dev-tools))
    - This dependency is automatically updated to the latest version each time you perform a `git pull` or `git merge` in this repository, as long as the WCPay Dev Tools repository is cloned locally and remains on the `trunk` branch. For more details, please refer to the [post-merge](.husky/post-merge) hook.

### Optional local.env file

If you are using a custom local development setup (as opposed to the Docker-based one), you can create a `local.env` file to provide environment variables for our development scripts.

We currently support the following variables:

-   `LOCAL_WCPAY_DEV_TOOLS_PLUGIN_REPO_PATH`: The path to your local WCPay Dev Tools plugin directory for auto-updates. This is primarily useful for non-Docker setups. With the Docker setup, the dev tools plugin is stored in a shared Docker volume and is installed automatically by the setup script.

## Test account setup

For setting up a test account follow [these instructions](https://woocommerce.com/document/woopayments/testing-and-troubleshooting/test-accounts/).

You will need an externally accessible URL to set up the plugin. You can use ngrok for this.

`ngrok http <PORT>` (check `.env` for your port; default is 8082)

See: [CONTRIBUTING.md](CONTRIBUTING.md) for more development details.

## Testing

WooPayments has PHP unit, JavaScript unit, E2E, and QIT test suites. Once your [Docker environment](docker/README.md) is running:

```bash
pnpm test          # Run JS and PHP unit tests
```

See [tests/README.md](tests/README.md) for how to run each suite, and [docs/test-matrix.md](docs/test-matrix.md) for the full inventory of suites and what they gate.

## Debugging

If you are following the [Docker setup](docker/README.md), Xdebug is ready to use for debugging.

Install [Xdebug Helper browser extension mentioned here](https://xdebug.org/docs/remote) to enable Xdebug on demand.
