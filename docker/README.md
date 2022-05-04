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
 
You may need to set your `siteurl` and `home` `wp_option`s to the new url. You can do this with phpMyAdmin or WP-CLI.

Visit the `<url>` , login and setup WCPay.
