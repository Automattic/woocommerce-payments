### Setting up the Docker environment

To start a local development environment with the plugin locally enter this command:

`npm run dev`

Install the core:

```
docker run -it --rm --volumes-from woocommerce_payments_wordpress --network woocommerce-payments_default wordpress:cli core install --url=localhost:8082 --title=test --admin_user=admin --admin_email=wordpress@example.com --admin_password=admin
```

Install and activate WooCommerce and Jetpack by running:

```
docker run -it --rm --volumes-from woocommerce_payments_wordpress --network woocommerce-payments_default  wordpress:cli plugin install woocommerce jetpack --activate
```

Install dev tools plugin:
https://github.com/automattic/woocommerce-payments-dev-tools

```sh
git clone git@github.com:Automattic/woocommerce-payments-dev-tools.git docker/wordpress/wp-content/plugins/woocommerce-payments-dev-tools

docker run -it --rm --volumes-from woocommerce_payments_wordpress --network woocommerce-payments_default  wordpress:cli plugin activate woocommerce-payments woocommerce-payments-dev-tools
```

If you will be using our local Docker server environment as well, be sure check "Enable API request redirection" in the dev mode settings

### Connect Jetpack by using Ngrok
You don't need a paid plan for this.

In a new terminal window run:

```
ngrok http 8082 --host-header=rewrite
```

You will see it give a forwarding address like this one:
 http://e0747cffd8a3.ngrok.io
 
Copy the address and use it in the following command (replacing `<url>`)

```
npm run wp option update home <url>
npm run wp option update siteurl <url>
```

Visit the `<url>` , login and connect to Jetpack. If this doesn't work, visit `localhost:8082` and you will be redirected 
to `<url>`.


Activate Jetpack and setup WCPay.


Turn off Ngrok by running:

```
npm run wp option update home http://localhost:8082
npm run wp option update siteurl http://localhost:8082
```

After this succeeds you can access the local site at

http://localhost:8082/

### Connect to the Server running locally

To get your blog ID run:

```
npm run wp eval 'echo Jetpack_Options::get_option( "id" );'
```

For the rest of the setup instructions see this link:
https://github.com/Automattic/woocommerce-payments-server/tree/master/local
