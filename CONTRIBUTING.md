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

## Running the tests

Follow the instructions in the [tests readme](tests/README.md).

## Development

After cloning the repo, install dependencies using `npm install`. You can build the files using one of these npm scripts:

- `$ npm run build`: Build a production version
- `$ npm run watch`: Build a development version, and watch for file changes

When enqueuing the app JavaScript, `wordpress` and `woocommerce` dependencies are handled by `@wordpress/dependency-extraction-webpack-plugin`. WordPress dependencies don't need to be added manually anywhere, including the `$deps` parameter in `wp_enqueue_script` or in `webpack.config`.

We add each package as a dev dependency in `package.json`, though, since it enables auto-completion in our IDEs.

Dependencies not handled by `@wordpress/dependency-extraction-webpack-plugin` should be handled in `webpack.config` using the functions `requestToExternal` and `requestToDependency`, for example:

```
new WordPressExternalDependenciesPlugin( {
    requestToExternal( request ) {
        if (  request === '@woocommerce/components'  ) {
            return [ 'wc', 'components' ];
        }
    },
    requestToDependency( request ) {
        if ( request === '@woocommerce/components' ) {
            return 'wc-components';
        }
    },
} ),
```

When running webpack `index.deps.json` will be created, listing all the needed dependencies. More info can be found here: https://wordpress.org/gutenberg/handbook/designers-developers/developers/packages/packages-dependency-extraction-webpack-plugin/.