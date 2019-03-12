# Contributing to WooCommerce Payments

We follow the [WooCommerce contribution guidelines](https://github.com/woocommerce/woocommerce/blob/master/.github/CONTRIBUTING.md#coding-guidelines).

## Setting up linting

To set up linting, first [install composer](https://getcomposer.org/doc/00-intro.md#globally) globally.

Then, enter the plugin directory, and run

```
$ composer install
```

Once it's done, you can run PHPCS from the command line, like this:

```
$ ./vendor/bin/phpcs woocommerce-payments.php
```

You can also set up linting hints in your editor. Here are some useful instructions for [VS Code](https://marketplace.visualstudio.com/items?itemName=ikappas.phpcs), [Atom](https://atom.io/packages/linter-phpcs), and [PhpStorm](https://hackernoon.com/how-to-setup-php-code-sniffer-in-phpstorm-d8ad7fc0cc08).
