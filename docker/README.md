### Setting up the Docker environment

Make sure everything has been installed:

`npm install`

To start a local development environment with the plugin locally enter this command:

`npm run up`

Remember to either build the JS (`npm run build`) or watch for JS changes (`npm start`)

### Connect Jetpack by using Ngrok
You don't need a paid plan for this.

In a new terminal window run:

```
ngrok http 8082 --host-header=rewrite
```

You will see it give a forwarding address like this one:
 http://e0747cffd8a3.ngrok.io

Visit the `<url>` , login and setup WCPay.
