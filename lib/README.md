# WooPayments Lib Directory

Similarly to how Composer loads packages into the `vendor` directory, this directory will contain WooPayments-specific libraries. There are a couple of differences here:

- All packages have their namespaces prefixed by `WooPayments\Vendor\` to prevent conflicts with other plugins. This is automated through the `coenjacobs/mozart` package.
- Even though `vendor` packages are not commited to git, the packages within `lib/packages` will be commited.

Currently the directory only contains the `League\Container` package.

> __Do not make any changes within `packages`, they will be lost!__

## Adding new packages

TBD

## Updating packages

TBD
