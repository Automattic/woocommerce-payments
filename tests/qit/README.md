## WooCommerce Payments QIT tests

We currently only use the security tests from the [QIT toolkit](https://qit.woo.com/docs/) and these can be run locally.

#### Setup and running
- Create `local.env` inside the `tests/qit/config/` directory by copying the variables from `default.env`.
- To get the actual values for local config, refer to this [secret store](https://mc.a8c.com/secret-store/?secret_id=11043) link.
- Once configured, the first time you run the `npm` command, it should create a local auth file which will be used for subsequent runs.

- Currently, two types of tests are available through the `npm` command: Security and PHPStan tests. PHPStan tests can also be run against the local development build.
- For running, use one of the following commands based on your requirements:
   ```
   npm run test:qit-security
   npm run test:qit-phpstan
   npm run test:qit-phpstan-local
   ```

- The commands use the `build:release` to create `woocommerce-payments.zip` at the root of the directory which is then uploaded and used for the QIT tests.


#### Analysing results
- Once the test run is done, you'll see a result URL along with the test summary.
- Look at any errors that might have been surfaced and associate with PRs that has introduced the same by using `git blame`.
- Ping the author for fixing the error, or fix it yourself if it is straightforward enough.
