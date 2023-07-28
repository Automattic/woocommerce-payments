# WooPayments Lib Directory

Similarly to how Composer loads packages into the `vendor` directory, this directory will contain WooPayments-specific libraries. There are a couple of differences here:

- All packages have their namespaces prefixed by `WooPayments\Vendor\` to prevent conflicts with other plugins. This is automated through the `coenjacobs/mozart` package.
- Even though `vendor` packages are not commited to git, the packages within `lib/packages` will be commited.

Currently the directory only contains the `League\Container` package.

> __Do not make any changes within `packages`, they will be lost!__

## Adding new packages

Please be mindful of adding packages to `lib`, as they will both live in git, require maintenance, and be distributed within the plugin.

- Navigate to this directory `lib`.
- Modify `extra.mozart.packages` within `composer.json`, adding the new package.
- Require the package through Composer (ex. `composer require --dev provider/package`).
- Open a PR, and commit the result.

## Updating packages

- Navigate to this directory `lib`.
- Run `composer update`.
- Open a PR, and commit the result.
