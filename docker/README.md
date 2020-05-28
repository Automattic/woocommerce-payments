### Setting up the Docker environment

To start a local development environment with the plugin locally enter this command:

`npm run dev`

Install and activate WooCommerce and Jetpack by running:

```
docker-compose exec -u www-data wordpress wp plugin install woocommerce jetpack --activate
```

Install dev tools plugin:
https://github.com/automattic/woocommerce-payments-dev-tools


### Connect Jetpack by using Ngrok
You don't need a paid plan for this.

In a new terminal window run:

```
ngrok http 8082 --host-header=rewrite
```

You will see it give a forwarding address like this one:
 http://e0747cffd8a3.ngrok.io
 
Copy the address and use it in the following command (replacing <url>)

```
docker-compose exec -u www-data wordpress wp option update home <url>
docker-compose exec -u www-data wordpress wp option update siteurl <url>
```

Visit the <url> , login and connect to Jetpack. If this doesn't work, visit `localhost:8082` and you will be redirected 
to <url>.


Activate Jetpack and setup WCPay.


Turn off Ngrok by running:

```
docker-compose exec -u www-data wordpress wp option update home http://localhost
docker-compose exec -u www-data wordpress wp option update siteurl http://localhost
```

After this succeeds you can access the local site at

http://localhost:8082/

### Connect to the Server running locally

To get your blog ID run:

```
docker-compose exec -u www-data wordpress wp eval 'echo Jetpack_Options::get_option( "id" );'
```

For the rest of the setup instructions see this link:
https://github.com/Automattic/woocommerce-payments-server/tree/master/local
