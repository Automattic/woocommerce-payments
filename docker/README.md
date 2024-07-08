### Setting up the Docker environment

Make sure everything has been installed:

`npm install`

To create and start a local development environment with the plugin locally enter this command:

`npm run up:recreate`

This will (re-)create all containers and run a setup script to ensure everything is configured. 

Once you've created the environment, you can quickly bring it back up with `npm run up`.

Remember to either build the JS (`npm run build`) or watch for JS changes (`npm start`)

### WordPress Admin
Open http://localhost:8082/wp-admin/
```
Username: admin
Password: admin
```

### Connecting to MySQL
Open phpMyAdmin at http://localhost:8083/, or connect using other MySQL clients with these credentials:
```
Host: localhost
Port: 5678
Username: wordpress
Password: wordpress
```

### Connect Jetpack by using Ngrok
You don't need a paid plan for this.

In a new terminal window run:

```
ngrok http 8082
```

You will see it give a forwarding address like this one:
 http://e0747cffd8a3.ngrok.io
 
You may need to temporarily set your `siteurl` and `home` `wp_option`s to the new url. You can do this with phpMyAdmin or WP-CLI.

Visit the `<url>` , login and setup WCPay.

### Changing default port for xDebug
To change the default port for xDebug you should create `docker-compose.override.yml` with the following contents:
```
version: '3'

services:
  wordpress:
    build:
      args:
        - XDEBUG_REMOTE_PORT=9003 # IDE/Editor's listener port
```
I used port `9003` as an example.
To apply the change, restart your containers using `npm run down && npm run up`

### Adding local helper scripts/hacks

You can add local PHP scripts in the `docker/mu-plugins` directory since it's mounted as the `wp-content/mu-plugins` WordPress directory in your Docker container. These PHP scripts will be loaded automatically because they are treated as [WordPress must-use plugins](https://developer.wordpress.org/advanced-administration/plugins/mu-plugins/).

**Note:** Please make sure that you try to think of these scripts as _temporary solutions/helpers_ and not as permanent code to be run constantly (unless you are sure that is what you want). 

One _recommended way_ of working with your collection of helper scripts is to take advantage of the fact that _WordPress will not automatically load PHP files_ in subdirectories of `wp-content/mu-plugins` (as it does with regular plugins in `wp-content/plugins`).

1. Create a new directory in `docker/mu-plugins` for your scripts, e.g. `docker/mu-plugins/local-helpers`. WordPress will not automatically load PHP files in subdirectories of `mu-plugins`, so you need to include them manually.
2. Create a new PHP file in `docker/mu-plugins`,e.g. `docker/mu-plugins/0-local-helpers.php`.
3. Add lines like `require_once __DIR__ . '/local-helpers/your-script.php';` to `docker/mu-plugins/0-local-helpers.php` to load your scripts.
4. Comment/uncomment the `require_once` lines to load the scripts you need for your particular itch.
5. Make sure you comment out any lines once you are finished with that itch to avoid unexpected/non-standard behavior on your local environment going forward - leftover helpers are not helpful!
