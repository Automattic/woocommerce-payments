# Contributing to WooCommerce Payments

We follow the [WooCommerce contribution guidelines](https://github.com/woocommerce/woocommerce/blob/trunk/.github/CONTRIBUTING.md#coding-guidelines).

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

- `$ npm run build:client`: Build a production version
- `$ npm run watch`: Build a development version, and watch for file changes
- `$ npm run hmr`: Instantiate a webpack server with HMR in development mode. Requires WordPress +6.0. If you see errors trying to connect to the HMR server, try trusting the generated SSL certificate.

When enqueuing the app JavaScript, `wordpress` and `woocommerce` dependencies are handled by `@wordpress/dependency-extraction-webpack-plugin`. WordPress dependencies don't need to be added manually anywhere, including the `$deps` parameter in `wp_enqueue_script` or in `webpack.config`.

We add each package as a dev dependency in `package.json`, though, since it enables auto-completion in our IDEs.

Dependencies not handled by `@wordpress/dependency-extraction-webpack-plugin` should be handled in `webpack.config` using the functions `requestToExternal` and `requestToHandle`, for example:

```
new WordPressExternalDependenciesPlugin( {
    requestToExternal( request ) {
        if (  request === '@woocommerce/components'  ) {
            return [ 'wc', 'components' ];
        }
    },
    requestToHandle( request ) {
        if ( request === '@woocommerce/components' ) {
            return 'wc-components';
        }
    },
} ),
```

When running webpack `index.asset.php` will be created, listing all the needed dependencies. More info can be found here: https://wordpress.org/gutenberg/handbook/designers-developers/developers/packages/packages-dependency-extraction-webpack-plugin/.

## Changelog entries

In order to add a changelog entry, please run following command (it'll guide you with necessary steps): `npm run changelog`.

Types of changes:

- Add: new features, endpoints, UI elements and pages.
- Update: changes in features, endpoints, UI elements and pages.
- Fix: bug-fixes.
- Dev:
  - dev tools and dev experience changes (non-functional).
  - WP/WC/PHP versions bumps.
  - dependency versions bumps.
  - PHP notices/warnings fixes.

What to mention:

Ideally, all merged PRs should be reflected in the changelog. A special case is functionality hidden behind feature flags: such features should be mentioned when released.
The entry should clarify which project or which core component is affected and a brief summary of changes.

## Docker Local Setup

Docker can be used to setup a local development environment:

* Ensure Docker is installed ([Docker Desktop](https://www.docker.com/products/docker-desktop) is a good option for developers)
* Follow the steps above in the Development section to build the project's JavaScript
* From the root of this project, run `docker-compose up -d`
* Once <http://localhost:8082> displays the WordPress install screen, run `./bin/docker-setup.sh`
* The fully configured site can now be accessed on <http://localhost:8082>
* The prompt to run the setup wizard can be dismissed unless there is something specific you would like to configure

To shutdown:

* Use `docker-compose down` to stop the running containers
* The state of the environment will be persisted in `docker/wordpress` and `docker/data`. To restart the environment simply run `docker-compose up` again. To start afresh, delete these folders and let `docker-compose up` re-create them.

IDE setup:

* Adding `docker/wordpress` to your IDE's PHP include path will allow it to provide hinting for WordPress functions etc.
* The WordPress container has xdebug setup. Add the following path mappings to your IDE so it can find the correct code:

   * `<project folder>/ -> /var/www/html/wp-content/plugins/woocommerce-payments`
   * `<project folder>/docker/wordpress -> /var/www/html`
