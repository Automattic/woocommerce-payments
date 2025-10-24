Fixes #

#### Changes proposed in this Pull Request

Adds a "Delete test orders" tool to the WooCommerce Status Tools page that allows merchants to bulk delete all orders created while WooPayments test mode was enabled.

The tool identifies test orders by the `_wcpay_mode` meta key with value `test` and permanently deletes them. Includes proper error handling and user feedback.

#### Testing instructions

1. Create 3-5 test orders with WooPayments in test mode
2. Navigate to `wp-admin/admin.php?page=wc-status&tab=tools`
3. Find "Delete test orders" tool and click "Delete"
4. Verify success message shows correct count and orders are deleted
5. Create live orders and verify they are NOT deleted when running the tool

Run unit tests:
```bash
./bin/run-tests.sh --filter=WC_Payments_Status_Test
```

-------------------

- [ ] Run `npm run changelog` to add a changelog file, choose `patch` to leave it empty if the change is not significant. You can add multiple changelog files in one PR by running this command a few times.
- [ ] Covered with tests (or have a good reason not to test in description ☝️)
- [ ] Tested on mobile (or does not apply)

**Post merge**

- [ ] Link to testing instructions from [release testing doc](https://github.com/Automattic/woocommerce-payments/wiki/Release-testing-instructions) following [these instructions](https://github.com/Automattic/woocommerce-payments/wiki/How-to-write-good-manual-testing-scenarios) : _Add link here / 'QA Testing Not Applicable'_
- [ ] Add or update [critical flows](https://github.com/Automattic/woocommerce-payments/wiki/Critical-flows) and [testing instructions for critical flows](https://github.com/Automattic/woocommerce-payments/wiki/Testing-instructions-for-critical-flows), if applicable.
- [ ] Add what's changed (description, screenshot, demo videos etc.) to the release announcement post, if applicable.
