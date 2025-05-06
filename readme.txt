=== WooPayments: Integrated WooCommerce Payments ===
Contributors: woocommerce, automattic
Tags: woocommerce payments, apple pay, credit card, google pay, payment, payment gateway
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.3
Stable tag: 9.3.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Securely accept credit and debit cards on your WooCommerce store. Manage payments without leaving your WordPress dashboard. Only with WooPayments.

== Description ==

**Payments made simple, with no monthly fees – designed exclusively for WooCommerce stores.**

Securely accept major credit and debit cards, and allow customers to pay you directly without leaving your WooCommerce store. View and manage transactions from one convenient place – your WordPress dashboard.

See payments, track cash flow into your bank account, manage refunds, and stay on top of disputes without the hassle of having to log into a separate payment processor.

**Manage transactions from the comfort of your store**

Features previously only available on your payment provider’s website are now part of your store’s **integrated payments dashboard**. This enables you to:

- View the details of [payments, refunds, and other transactions](https://woocommerce.com/document/woopayments/managing-money/).
- View and respond to [disputes and chargebacks](https://woocommerce.com/document/woopayments/fraud-and-disputes/managing-disputes/).
- [Track payouts](https://woocommerce.com/document/woopayments/payouts/) into your bank account or debit card.

**Pay as you go**

WooPayments is **free to install**, with **no setup fees or monthly fees**. Our pay-as-you-go pricing model means we're incentivized to help you succeed! [Read more about transaction fees](https://woocommerce.com/document/woopayments/fees-and-debits/fees/).

**Supported by the WooCommerce team**

Our global support team is available to answer questions you may have about WooPayments installation, setup, or use. For assistance, [open a ticket on woocommerce.com](https://woocommerce.com/my-account/contact-support/?select=5278104).

== Getting Started ==

= Requirements =

* WordPress 6.0 or newer.
* WooCommerce 7.6 or newer.
* PHP 7.3 or newer.

= Try it now =

To try WooPayments (previously WooCommerce Payments) on your store, simply [install it](https://wordpress.org/plugins/woocommerce-payments/#installation) and follow the prompts. Please see our [Startup Guide](https://woocommerce.com/document/woopayments/startup-guide/) for a full walkthrough of the process.

== Installation ==

Install and activate the WooCommerce and WooPayments plugins, if you haven't already done so, then go to "Payments" in the WordPress admin menu and follow the instructions there.

== Frequently Asked Questions ==

= What countries and currencies are supported? =

If you are an individual or business based in [one of these countries](https://woocommerce.com/document/woopayments/compatibility/countries/#supported-countries), you can sign-up with WooPayments. After completing sign up, you can accept payments from customers anywhere in the world, as long as they are paying with [a supported currency](https://woocommerce.com/document/woopayments/currencies/available-currencies/).

We are actively planning to expand into additional countries based on your interest. Let us know where you would like to [see WooPayments launch next](https://woocommerce.com/payments/#request-invite).

= Why is a WordPress.com account and connection required? =

WooPayments uses the WordPress.com connection to securely communicate with our payments server. This is necessary for WooPayments to function correctly, so it's not possible to use WooPayments without connecting. Please see [this document](https://woocommerce.com/document/woopayments/our-policies/connection/) for more information.

Note that you do not need to host your site itself on WordPress.com, nor do you need to host *any* site on WordPress.com at all. You only need an account.

Note that you do not need to host your site itself on WordPress.com. You only need an account on WordPress.com to establish the connection. You can create a WordPress.com account [at this link](https://wordpress.com/start/account/user-social).

= How do I set up a store for a client? =

If you are a developer or agency setting up a site for a client, please see [this page](https://woocommerce.com/document/woopayments/account-management/developer-or-agency-setup/) of our documentation for some tips on how to install WooPayments on client sites.

= How is WooPayments related to Stripe? =

WooPayments is built in partnership with Stripe [Stripe](https://stripe.com/). When you sign up for WooPayments, your personal and business information is verified with Stripe and stored in an account connected to the WooPayments service. This account is then used in the background for managing your business account information and activity via WooPayments. [Learn more](https://woocommerce.com/document/woopayments/account-management/partnership-with-stripe/).

= Are there Terms of Service and data usage policies? =

You can read our Terms of Service and other policies [here](https://woocommerce.com/document/woopayments/our-policies/).

== Screenshots ==

1. View Transactions
2. View Transaction Details
3. Track Payouts
4. Manage Disputes

== Changelog ==

= 9.3.0 - 2025-05-05 =
* Add - Add dedicated onboarding REST API endpoint for disabling test drive account, when possible.
* Add - Transaction Fees breakdown component in the Payment details.
* Fix - Disabled the Transactions Breakdown section to avoid misleading data in certain scenarios.
* Fix - fix: ensuring that Google Pay/Apple Pay buttons hide on shortcode cart & checkout when totals go to 0
* Fix - fix: Multibanco payment instructions font size adjustment on some block-based themes (e.g.: Twenty-Twenty-Four, Twenty-Twenty-Three)
* Fix - Fix a bug when the notice after downloading CSV that was mispositioned.
* Fix - Fix capabilities query param type
* Fix - Fixed inconsistent spacing between "Add to Cart" button and express checkout buttons on product pages.
* Fix - Fixes a styling issue when the payment method has a tooltip next to it, it was shifting the logo to the right.
* Fix - Fix for validation issue with POST params in some cases generating account session.
* Fix - Improve styling of the Embedded components to be closer to WPDS.
* Fix - Improve subscriptions code compatibility to avoid causing fatal errors.
* Fix - Remove the referrer check to update the fraud protection settings
* Fix - Set the onboarding test mode while creating test drive account with API
* Update - Add dedicated onboarding REST API endpoint for resetting onboarding, when possible.
* Update - Advanced fraud protection settings redesign.
* Update - chore: removed notices about the JCB capability request. JCB will be automatically requested for every new and existing merchant, regardless of the merchant country.
* Update - Include a failure message in the order notes when Stripe Billing subscription renewal has failed.
* Update - On the payment settings page, change the "Credit/Debit Cards" icon to a more generic icon and add a static list of card brands below the "Credit/Debit Cards" element.
* Update - Remove progressive onboarding eligibility check during embedded KYC session creation
* Update - Simplified refund handling with clear errors and standard reasons to aid resolution.
* Update - Updated the Stripe locales list.
* Update - Update log file format for better compatibility with the WooCommerce log viewer.
* Dev - Add Cursor config folder to .gitignore
* Dev - Bump WC tested up to version to 9.8.1
* Dev - Bump WC tested up to version to 9.8.2
* Dev - Delete NOX profile during account reset from Overview or Connect page.
* Dev - Fix Apple Pay and Google Pay not requested from NOX In-context flow.
* Dev - Merged WC_REST_Payments_Payment_Intents_Create_Controller back into WC_REST_Payments_Payment_Intents_Controller after confirming that the issue that caused the split was solved.
* Dev - Replace WCPay in messages with WooPayments

= 9.2.1 - 2025-04-23 =
* Update - Update account session creation route definition to use POST rather than GET.


= 9.2.0 - 2025-04-09 =
* Add - Add back button for tertiary+ level pages in WooPayments settings.
* Fix - fix: cancel GooglePay/ApplePay dialog on product page if add-to-cart product validation fails
* Fix - fix: fatal error when Klarna is enabled on an EU account and a non-EU currency is configured on the store.
* Fix - fix: Google Pay/Apple Pay display on pay-for-order pages.
* Fix - Fix deprecated hook woocommerce_rest_api_option_permissions
* Fix - Fix errors in WooCommerce email settings preview
* Fix - Fix Multi-currency conversion for WooCommerce Bookings range type cost adjustments
* Fix - Fix PMME display on shortcode cart with block-based themes.
* Fix - Fix WooPay enabled during NOX onboarding despite being disabled in recommended payment methods.
* Fix - Handle pending refunds properly
* Fix - Linked account ID to product ID to maintain consistency and prevent issues when the account ID changes.
* Fix - Prevent unsaved changes dialog when changes have been saved.
* Fix - Removed hard-coded lists of payment methods where possible.
* Fix - Remove unused wcpay_date_format_notice_dismissed option from the permission list
* Fix - Set background color to white for the Payments settings page
* Fix - update: ensure Google Pay/Apple Pay honor 'Display prices during cart and checkout' setting
* Fix - Update WooPay icon on order page.
* Update - Added _wcpay_net to the metadata.
* Update - Chore: check the array type in dismissed noticeces component.
* Update - chore: disable request of JCB capability
* Update - fix: Google Pay/Apple Pay HK test address override.
* Update - fix: parsing of error message for GooglePay/ApplePay buttons to be displayed to customer, instead of displaying generic error message on failure.
* Update - Improve the ECE container loading experience.
* Update - Move payment method map definition to the backend
* Update - Prevent creation of the renewal orders if original order was created in the different WooPayments mode.
* Update - refactor: delete temporary Google Pay/Apple Pay cart contents right after making the request, to improve performance and avoiding bots sending wrong session data in subsequent requests.
* Update - Remove fraud protection discoverability and update tour
* Update - Stripe Billing and Manual Capture incompatibility notice on the Settings page.
* Update - Update Settings page as per the new design
* Update - Update to Cash App Afterpay branding.
* Dev - Bump WC tested up to version to 9.7.1.
* Dev - Fix unneeded double square brackets in the post-merge script
* Dev - Removed the deprecated wcpay_exit_survey_dismissed option from the ALLOWED_OPTIONS list.
* Dev - Remove level3 retry logic and legacy request_with_level3_data method
* Dev - Updated the progressive parameter in the KYC session creation API to use a boolean type.
* Dev - We switch to using site instead of url as the key in the self assessment data to avoid XSS firewall false-positives.

= 9.1.0 - 2025-03-19 =
* Add - Add a prompt to encourage merchants to provide feedback or leave a WordPress.org review for WooPayments
* Add - Add order failure message on Order Received page.
* Add - Add support to change payment method to 3DS card for subscriptions.
* Add - Add WeChat Pay response handling.
* Add - Implement Multibanco payment method
* Add - Implement specific handling for insufficient_balance_for_refund when refunding the admin order management
* Add - Inform Stripe when a store switch from coming soon to live mode
* Add - Show failure reason in details page for failed payout. This will help merchants get better support, or understand the next steps needed to fix the failing payouts.
* Fix - Add an order note when a recurring payment fails or when updating the payment method fails due to a missing or invalid payment token.
* Fix - Bypass enabled at checkout checks for rendering payment methods configs.
* Fix - Correct the dispute notice for Klarna Inquiries
* Fix - fix: GooglePay/ApplePay fail when there are more than 9 shipping options.
* Fix - fix: GooglePay/ApplePay script dependencies with WooCommerce 9.7
* Fix - Fix issue where survey modal is not scrollable on smaller screen sizes.
* Fix - Fix validation for support phone numbers for Singapore
* Fix - Improve payout failure messages for better clarity and accuracy
* Fix - Inconsitent Safe Mode notice with the latest Jetpack version
* Fix - Init PMME container in cart block so that it can be dynamically rendered once the requirements are met.
* Fix - Make sure that WooPayments gateways follow the main WooPayments card gateway in gateway ordering on the page.
* Fix - Manual capture fails in the transaction detail screen with a customized order number
* Fix - Properly extract styles when using the site editor.
* Fix - Renamed function parameters to avoid reserved keyword conflicts
* Fix - Resolved an issue on stores that had the Stripe Billing feature enabled (US-only) and then changed their store location to an ineligible country.
* Fix - Scoped CSS selectors for WP components to prevent unintended styling on other pages
* Fix - Show Express Checkout button previews in template editor
* Fix - Skip email input search in pay for order flow and use email provided in order data for WooPay iframe.
* Fix - Skip limits per currency check on admin pages
* Fix - Tests: Suppressed unexpected JSON output in maybe_handle_onboarding test by wrapping execution with ob_start() and ob_end_clean()
* Update - Better handling of HTTPs errors in embedded components.
* Update - Change wording for Sales Channel, Online Store, In-Person, and In-Person (POS)
* Update - Enhancements to country select field in onboarding.
* Update - feat: add compatibility notice for Google Pay with live mode accounts.
* Update - Jetpack packages in composer
* Update - Remove the 60 day survey admin note, since it will be redundant after we add the reviews prompt.
* Update - Track action complete event in Stripe Notification embedded component.
* Update - update: tokenize ECE initialization and update flow on pricing change.
* Dev - Add centralized payment method definitions to streamline implementation and maintenance of payment methods.
* Dev - Exclude playwright-report from eslint.
* Dev - Include transaction ID when requesting card reader fee charges summary.
* Dev - Refactors to the embedded compoennts logic.
* Dev - Update @wordpress/icons version to latest version 10.19.0

= 9.0.0 - 2025-02-26 =
* Add - Add E2E tests for currency switching at checkout.
* Add - Add GrabPay payment method details to the View Transaction page.
* Add - Add GrabPay to the settings page when eligible.
* Add - Add loader indication for Stripe notifications banner component
* Add - Add payment method logos to checkout block card label.
* Add - Adds store_id property to body of WooPay tracker events.
* Add - Add Stripe embedded account notifications component on the Overview page
* Add - Add WeChat Pay settings.
* Add - Add WeChat Pay support to checkout.
* Add - feat: add initial support for Alipay settings
* Add - feat: add order notes to tokenized ECE GooglePay/ApplePay on blocks checkout
* Add - Implement checkout for GrabPay payments.
* Add - Pass the business name to the express checkout handler.
* Fix - Avoid duplicated payment methods detection notice on new stores.
* Fix - Avoid PMME init for unavailable BNPL use-cases.
* Fix - Ensure multi-currency widget markup getter don't throw errors.
* Fix - Ensures that the tokenised cart for ECE implementation is disabled by default.
* Fix - fix: attribute selection from PDPs with tokenized ECE
* Fix - fix: tokenized ECE improvements with WC Deposits, Bookings, Bundles.
* Fix - Fixed an issue with the copy test card number on Astra theme
* Fix - Fixes incorrect defined statement for WC_Install::STORE_ID_OPTION constant.
* Fix - Fixes thrown PHP warning in checkout config.
* Fix - Fix Stripe KYC flow Woo color
* Fix - Fix the default value of the FRT advanced options.
* Fix - Improve how Stripe Billing integration is loaded to prevent unnecessary queries on every page load.
* Fix - Prevent fatal errors when subscription is deactivated due outdated version.
* Fix - Set orderby to `none` for order queries where checking if order exists.
* Fix - Update the size of Woo logo on the test drive onboarding page.
* Update - Add a notice indicating that overview page needs to be reloaded after completing the requirements in the embedded component.
* Update - Add JSON-formatted minimum amount details to the amount too small error message.
* Update - feat: GooglePay/ApplePay refactor to leverage Store API is enabled by default - second try.
* Update - Improve CSV export experience and deliver consistent reports via async transact platform service-based CSV exports.
* Update - Remove the receipt details tab in the card readers page.
* Update - update: add payment method functionality to honor WC rate limit.
* Update - update: tokenized ECE to exclude itemized items on rounding discrepancies of totals.
* Update - Update copy in the Woopayments Reset account modal for incomplete onboarding accounts
* Update - Update Credit Card / Debit Card label to Cards
* Update - Update handling for 0 feeAmount to be consistent with details page.
* Update - Update handling for refund processing in case of insufficient funds.
* Update - Update handling of PR as a country in the terminal locations endpoint.
* Update - Update the copy in the existing modal for resetting a WooPayments account.
* Dev - Add critical tag to tests that are part of the critical flows.
* Dev - Add new E2E tests to make sure a non-admin user, such as an editor, can access the wp-admin without errors.
* Dev - Add Slack integration to Playwright E2E tests.
* Dev - Cache calls to wc_get_orders in the incentives class
* Dev - Extract the Account Session logic from Embedded components onboarding into a reusable utility hook.
* Dev - Fix potential flakiness while creating a page with the checkout block.
* Dev - Remove Puppeteer references.
* Dev - Remove routing to the partially re-engineered payment process"
* Dev - Update E2E NPM scripts.
* Dev - Update E2E readme doc.
* Dev - Update E2E setup scripts to avoid conflicts with other containers and permissions.
* Dev - Update Github actions and workflows. Change e2e tests directory structure.

= 8.9.2 - 2025-02-21 =
* Fix - Fixed WooPay opt-in race condition


= 8.9.1 - 2025-02-07 =
* Fix - Ensures that the tokenised cart for ECE implementation is disabled by default.


= 8.9.0 - 2025-02-04 =
* Add - Add a popover to WooPayments to present all possible payment methods
* Add - Added persistent column visibility preferences for reporting tables, allowing merchants to customize and save their preferred table view layouts across sessions.
* Add - Add support for `WP_DEVELOPMENT_MODE`. Any non-empty setting will force the plugin into development mode.
* Add - Add wcpay_capture_error_amount_too_small error type and minimum amount details when capturing payments below the supported threshold.
* Add - Admin analytics page E2E tests for Playwright
* Add - Enable "Reader fee" type filtering for transactions list.
* Add - Show Bank reference key on top of the payout details page, whenever available.
* Fix - Add currency code to fee breakdown when multi-currency is enabled, and currencies share the same symbol.
* Fix - Added timestamp to the order note when terminal payment fails.
* Fix - fix: add payment method page query initialization - ensuring that the WP_Query is initialized when checking for payment methods availability
* Fix - fix: avoid ECE error when no address is provided on initialization
* Fix - Fix cart subtotal amount when manually renewing a subscription.
* Fix - Fixed fraud prevention token not available on blocks checkout
* Fix - Fix flaky Multi-Currency test.
* Fix - Fix global styling for popovers
* Fix - Fix guest users being able to buy subscriptions with WooPay via Direct Checkout.
* Fix - Fix incorrect payment method logos used on WooPayment Settings
* Fix - Fix the amounts in the Filter by Price block to reflect the shopper's selected currency.
* Fix - Pass theme editor footer and header styles to WooPay.
* Fix - Prevent potential fatal when initializing the WooPay express checkout button.
* Fix - Set a default minimum height to the WooPay button.
* Fix - Update gateway form fields references to prevent errors.
* Fix - Update set up live payments task list item copy
* Fix - WooPay blocks checkout terms and condition default text
* Update - Design improvements related to the overview page
* Update - feat: GooglePay/ApplePay refactor to leverage Store API is enabled by default. Please contact us if you encounter new issues with these buttons.
* Update - Improve loading experience in embedded KYC
* Update - Remove date format notice across all admin pages.
* Update - Removes Sofort payment method from settings and checkout, permanently deprecates Sofort from settings.
* Update - Replace payments overview page connection success notice with a modal for live accounts.
* Update - Simplify localization of CSV exports to use user language settings from WP Admin, allowing the CSV export to match the localization of the data presented in the admin UI.
* Update - Update the loader image for test account creation
* Update - Woo Colors and logos
* Dev - Add E2E environment variables to Playwright config.
* Dev - Add e2e tests for the multi-currency widget setup.
* Dev - Converted E2E merchant-progressive-onboarding spec from Puppeteer to Playwright.
* Dev - Convert merchant-orders-partial-refund spec from Puppeteer to Playwright.
* Dev - Convert merchant admin transactions E2E test to Playwright
* Dev - Convert order refund failure E2E tests to Playwright
* Dev - Convert shopper-checkout-purchase spec from Puppeteer to Playwright
* Dev - Convert shopper-multi-currency-widget spec from Puppeteer to Playwright
* Dev - Convert shopper checkout save card and purchase test to Playwright
* Dev - Convert shopper checkout with site editor theme spec to Playwright
* Dev - Convert shopper checkout with UPE methods E2E test to Playwright, split saving card tests to separate file
* Dev - Convert shopper free coupon checkout E2E test to Playwright
* Dev - Convert shopper purchase multiple subscriptions E2E tests to Playwright
* Dev - Convert Shopper WC Blocks saved card checkout and usage test to Playwright
* Dev - Convert the merchant orders status change spec from Puppeteer to Playwright.
* Dev - Convert the shopper-myaccount-payment-methods-add-fail spec from Puppeteer to Playwright.
* Dev - E2E Playwright Migration: convert Admin Disputes spec
* Dev - E2E Playwright Migration: convert Order Manual Capture spec
* Dev - E2E Playwright Migration: convert shopper-checkout-failures spec to Playwright and remove Puppeteer spec.
* Dev - E2E Playwright Migration: convert shopper-wc-blocks-checkout-purchase spec
* Dev - Enhance log file format to provide more information about the request flow.
* Dev - Fix flakiness in saved card tests caused by selling the same cart multiple times, triggering duplicate order protection
* Dev - Migrate merchant subscription settings spec to Playwright
* Dev - Migrate shopper subscription free-trial purchase E2E test to Playwright
* Dev - Migrate shopper subscriptions - No signup fee E2E test to Playwright
* Dev - Migrate the merchant subscriptions renew action scheduler spec from Puppeteer to Playwright.
* Dev - Migrate the Shopper Renew Subscription spec to Playwright and remove the corresponding Puppeteer test.
* Dev - Migrate the Shopper Subscriptions Manage Payments spec to Playwright and remove the corresponding Puppeteer test.
* Dev - Optimise memory consumption when formatting log entries.
* Dev - Port merchant subscription renewal E2E test to Playwright
* Dev - Refactoring of snackbar checks in Playwright e2e tests
* Dev - Refresh customer instance with REST API, replace customer creation by new order with anonymous customer
* Dev - Removed unused get single transaction endpoint
* Dev - Remove the subscriptions spec for Puppeteer
* Dev - Restore activated currencies after all tests that deactivate current ones.

= 8.8.0 - 2025-01-15 =
* Add - Allow transactions filtered by Payment Method.
* Add - Falback terms and conditions for WooPay.
* Fix - Broaden billing field queries from form-scoped to document-scoped.
* Fix - Checkout: Make sure the font size for PMME is smaller than the labels.
* Fix - Ensure consistent formatting of refund notes with MC.
* Fix - Fix incompatibility with some bookings plugins.
* Fix - Fix JS exception when ECE payment fails.
* Fix - Fix transaction list sorting by payout currency.
* Fix - Improve the mobile rendering of the Balances box within Payment Overview.
* Fix - Update currency conversion method for booking products.
* Fix - Update WordPress.org readme.txt file.
* Fix - Upgrade `@woocommerce/csv-export` package to v1.10.0 – fixes unnecessary escaping of negative values in CSV exports that was preventing numerical analysis in spreadsheet applications.
* Fix - Zero dollar subscriptions with zero dollar renewals checkout error.
* Update - Bump stable tag for 8.7.0.
* Update - Improve BNPL PMME and icon placement in shortcode checkout.
* Update - Remove the overlay that announces the rename from deposits to payouts.
* Update - Replaced the term "VAT" with "Tax" in Documents Listing Page.
* Update - round to nearest lowest denominator instead of ceiling before applying currency rounding settings.
* Dev - Add the Playwright Pay for Order spec and remove the equivalent Puppeteer spec.
* Dev - Add type assertion for disputes CSV export response to ensure type safety and fix TypeScript error.
* Dev - Update phpunit-watcher dev package version.
* Dev - Update the PHP version and the Xdebug version used in the E2E testing environment.
* Dev - Update the Playwright Payment Gateways Confirmation spec and remove the Puppeteer Payment Gateways Confirmation spec.

= 8.7.1 - 2025-01-14 =
* Fix - Broaden billing field queries from form-scoped to document-scoped

= 8.7.0 - 2024-12-25 =
* Add - Add seller_message to failed order notes
* Add - Add WooPay Klaviyo newsletter integration.
* Add - Clickwrap terms and conditions support on WooPay
* Add - Implement gateway method to retrieve recommended payment method.
* Add - Migrate active capabilities from test-drive account when switching to live account.
* Add - Refresh the cart and checkout pages when ECE is dismissed and the shipping options were modified in the payment sheet.
* Fix - Add a rounding entry to Level 3 data for rare cases where rounding errors break calculations.
* Fix - Added conditional use of Jetpack Config callback to avoid i18n notices.
* Fix - Browser error no longer shows after dispute evidence submission
* Fix - Ceil product prices after applying currency conversion, but before charm pricing and price rounding from settings is applied.
* Fix - Consider WooPay eligibility when retrieving WooPay enable state in the settings.
* Fix - Enable ECE for Virtual Variable Subscriptions with Free Trials.
* Fix - Ensure captured transactions appear in the Transactions tab without requiring a page refresh.
* Fix - Ensure ECE login confirmation dialog is shown on Blocks.
* Fix - Ensure WooPay 'enabled by default' value is correctly set in sandbox mode.
* Fix - Errors were incorrectly marked as info in logs.
* Fix - fix: undefined $cart_contains_subscription
* Fix - Fix blank Payments > Overview page when WC onboarding is disabled.
* Fix - Fixed Affirm using black logo on dark themes
* Fix - Fixed an issue where order metadata was not updated when capturing an order in the processing state.
* Fix - Fixed UPE country detection in Checkout for non-logged in users
* Fix - Fix filtering in async Disputes CSV export
* Fix - Fix inconsistent alignment of the download button across transactions, payouts, and disputes reporting views for a more cohesive user interface.
* Fix - Fix Jetpack onboarding URL query from "woocommerce-payments" to "woocommerce-core-profiler"
* Fix - Fix payment method filtering when billing country changes in Blocks checkout.
* Fix - Fix styling of transaction details page in mobile view.
* Fix - Hide transaction fee on admin view order screen when transaction is not captured.
* Fix - Load checkout scripts when they are not previously loaded on checkout page.
* Fix - Localize postal code check label based on country.
* Fix - Normalize HK addresses for ECE
* Fix - Order notes for inquiries have clearer content.
* Fix - Performance improvements for Disputes Needing Response task shown in WooCommerce admin.
* Fix - Remove translations during initialization, preventing unnecessary warnings.
* Fix - Restrict Stripe Link to credit card payment method and improve cleanup.
* Fix - Set payment method title once title is known.
* Fix - Show express checkout for products w/o shipping but where tax is included into price.
* Fix - Support 'type_is_in' filter for Transactions list report, to allow easy filtering by multiple types.
* Fix - Use "currency conversion fee" instead "foreign exchange fee" in payment timeline and various other places.
* Fix - Use translatable strings on the fee breakdown tooltip of the payment settings screen.
* Update - Add failure reason to failed payments in the timeline.
* Update - Add support for showing `In-Person (POS)` as the transaction channel for mobile POS transactions in wp-admin Payments, enhancing visibility in both transaction lists and details.
* Update - Adjust the go-live modal to match the latest design.
* Update - Apply User-Defined Date Formatting Settings to WP Admin React Components
* Update - Change 'Bank reference key' label to 'Bank reference ID' in Payouts list column for consistency.
* Update - chore: renamed PRB references in GooglePay/ApplePay implementation docs and logs files to ECE.
* Update - Ensure more robust selectors scoping to improve theme compatibility.
* Update - Make test instructions copy icon use the same color as the text next to it
* Update - Remove payout timing notice and update the help tooltil on Payments Overview page.
* Update - Update confirmation modal after onbarding
* Update - Update Embedded Components and MOX to support custom width and paddings.
* Update - Update error messages for payment authorization actions to provide more specific and user-friendly feedback.
* Update - Update Jetpack onboarding flow
* Update - WooPay theming copy in the settings page
* Dev - Add support for utilizing NOX capabilities as URL parameters during account creation.
* Dev - Enable Payment Methods preselected by NOX after onboarding accounts
* Dev - Fixing issue with parsing QIT authentication.Fixing issue with parsing QIT authentication.
* Dev - Refine verification for disabling ECE on subscriptions that require shipping.
* Dev - Remove hooks from customer and token services to dedicated methods
* Dev - Update the tunelling setup.

= 8.6.1 - 2024-12-17 =
* Fix - Checkout: Fix error when wc_address_i18n_params does not have data for a given country
* Fix - Skip mysqlcheck SSL Requirement during E2E environment setup


= 8.6.0 - 2024-12-04 =
* Add - Add Bank reference key column in Payout reports. This will help reconcile WooPayments Payouts with bank statements.
* Add - Display credit card brand icons on order received page.
* Fix - Add support to load stripe js asynchronously when it is not immediately available in the global scope.
* Fix - Add the missing "Download" column heading label and toggle menu option to the Payments → Documents list view table.
* Fix - Ensure ECE button load events are triggered for multiple buttons on the same page.
* Fix - Ensure ECE is displayed correctly taking into account the tax settings.
* Fix - Evidence submission is no longer available for Klarna inquiries as this is not supported by Stripe / Klarna.
* Fix - fix: express checkout to use its own css files.
* Fix - fix: missing ece is_product_page checks
* Fix - Fix ECE Tracks events not triggering when WooPay is disabled.
* Fix - Fix WooPay component spacing.
* Fix - Fix WooPay trial subscriptions purchases.
* Fix - Make sure the status of manual capture enablement is fetched from the right place.
* Fix - Prevent express checkout from being used if cart total becomes zero after coupon usage.
* Fix - Resolved issue with terminal payments in the payment intent failed webhook processing.
* Fix - Set the support phone field as mandatory in the settings page.
* Fix - Update Link logo alignment issue when WooPay is enabled and a specific version of Gutenberg is enabled.
* Fix - Use paragraph selector instead of label for pmme appearance
* Fix - Validate required billing fields using data from objects instead of checking the labels.
* Update - Avoid getting the appearance for pay for order page with the wrong appearance key.
* Update - chore: rename wrapper from payment-request to express-checkout
* Update - feat: add `wcpay_checkout_use_plain_method_label` filter to allow themes or merchants to force the "plain" WooPayments label on shortcode checkout.
* Update - refactor: express checkout initialization page location checks
* Update - refactor: express checkout utility for button UI interactions
* Dev - Allow redirect to the settings page from WCPay connect
* Dev - chore: removed old PRB implementation for ApplePay/GooglePay in favor of the ECE implementation; cleaned up ECE feature flag;
* Dev - Disable visual regression testing from Playwright until a more reliable approach is defined.
* Dev - Ensure proper return types in the webhook processing service.
* Dev - fix: E_DEPRECATED on BNPL empty PMME
* Dev - Fix return types
* Dev - Update snapshots for E2E Playwright screenshots

= 8.5.1 - 2024-11-25 =
* Fix - fix: remove "test mode" badge from shortcode checkout.


= 8.5.0 - 2024-11-13 =
* Add - Add country-specific test card numbers for credit card testing
* Add - Add risk level information to the fraud and risk box on the order details page.
* Add - Add support for allowedShippingCountries in Express Checkout Element.
* Fix - Avoid PHP warnings for requests with an empty path.
* Fix - BNPL methods now work properly in Pay for Order when they are available. Default values are also provided when available.
* Fix - fix: payment method icon alt text
* Fix - Fix compatibility issues with CustomSelectControl component styles for WordPress 6.7
* Fix - Fix duplicate saving of 3DS card entry after checkout
* Fix - Fixed single product page view tracks when BNPL and PRB payment methods are inactive.
* Fix - Fix PMME appearance in checkout
* Fix - Fix settings display on the advanced fraud protection page.
* Fix - Fix WooPay button preview in plugin settings.
* Fix - Fix WooPay duplicated Save my info section.
* Fix - Include missing scripts that handle refunds for non credit card payments in the order details page.
* Fix - Introducing consistency in colors for deposits across pages
* Fix - Prevent dead space on product pages when no BNPL offers are available.
* Fix - Provide backwards-compatible method for retrieving the multi-currency instance.
* Fix - Register Expresss Checkout block only when enabled in the settings
* Fix - Remove unnecessary '.woocommerce-table' css overrides to fix WC Analytics styling and a11y issues
* Fix - Use 'Withdrawal' and 'Deducted' labels when referring to withdrawal deposits, to more accurately communicate the type of transaction that has occurred
* Update - Add in-memory cache fallback for our database-cached objects in case of database write failures.
* Update - Decoupled Multi-currency module from gateway dependencies.
* Update - Improvements to events during onboarding flow.
* Update - Refactor loadStripe for Express Checkouts.
* Update - Rename 'deposit' to 'payout' across various places in the WooPayments admin UI, docs and doc URLs.
* Update - update: payment method "test mode" label at checkout to be displayed only when payment method is selected
* Update - update: show LPM payment method icon on order success page
* Update - Use preview versions of the actual ECE buttons in the Block editor.
* Update - When tracking is enabled, send a tracks event when the merchant is redirected to a Stripe Capital offer.
* Dev - Add Jest tests for the disputed order notices
* Dev - Add unit tests for the Capital Loans page component.
* Dev - Bump WC tested up to version to 9.4.0

= 8.4.0 - 2024-10-23 =
* Add - Add test mode badge to classic checkout and add payment method.
* Add - Using Floating Labels with Stripe Appearance API for Blocks Checkout
* Fix - Converting text color rgba to hex to prevent Stripe warning
* Fix - Fix the color contrast of links within tooltips to improve readability.
* Fix - Omit the test mode badge in the change payment method form for subscriptions.
* Fix - Pass container styling data to WooPay
* Fix - Prevented detaching payment methods from live Stripe accounts when working in non-production environments.
* Fix - Rendering Test Model badge only for Credit Card
* Fix - Stop enqueuing woopay-express-button.css to prevent 404 errors
* Fix - The amounts used by the PMMEs will match the displayed price of the product regardless of the tax settings.
* Fix - WooPay user registration via classic checkout
* Update - Add support for the style controls for the WooPay button
* Update - chore: remove deprecated is_in_dev_mode() and is_in_test_mode() methods
* Update - Payments task onboarding flows skip the Connect page.
* Dev - Bump WC tested up to version to 9.3.3.
* Dev - fix: prevent multiple instances of WC_Payments_Apple_Pay_Registration
* Dev - Fixed wrong utils path that would prevent checkout with WooPay OTP
* Dev - Migrate WizardTaskItem and CollapsibleBody components to TypeScript, making the className prop optional.
* Dev - Use official `phpmyadmin` Docker Hub container image

= 8.3.1 - 2024-10-16 =
* Fix - Auto-enabled WooPay for new accounts.
* Fix - Load Stripe with merchant account's key when checking payment method availability.


= 8.3.0 - 2024-10-03 =
* Add - Add compatibility with the buttonAttributes API from Woo Blocks
* Add - Add rate limiter to compatibility data updates
* Add - Add UTC to the date time column header of transactions list page.
* Add - Disable save changes button until a setting has changed.
* Add - Enables Klarna with amount limitations for FR country code.
* Add - Provide locale to Express Checkout Element.
* Add - Redesigned the Payment Method labels for the Blocks Checkout
* Add - Show a notice in Payments > Settings > Deposits if there is an error with the bank account.
* Add - Updates to the Embedded KYC to ensure compatibility with Progressive Onboarding
* Fix - Allow the network saved card payment method setting to be overridden by the `wcpay_force_network_saved_cards` filter
* Fix - Create div container element with JS dynamically.
* Fix - Do not display BNPL methods and PMME when WooPayments is disabled
* Fix - Fixed CC form input fields appearance when using RGBA
* Fix - Fixed invalid appearance warnings
* Fix - Fix event prefix duplication in Tracks events
* Fix - Handle loadError in ECE for Block Context Initialization.
* Fix - Prevent failures and notices related to trying to schedule AS jobs before init.
* Fix - Prevent multi-currency conversion during a REST API request.
* Fix - Remove text color from WooPay component.
* Fix - Rendering Test Model badge only for Credit Card
* Fix - Sync phone changes with WooPay phone field.
* Fix - Update payment methods when manual capture is enabled.
* Update - Default express checkout button label to "Only icon".
* Update - Remove feature flag to make embedded KYC enabled by default
* Update - Some minor styling updates on the Onboarding form.
* Update - Update express payment methods with a title, description and gatewayId
* Dev - fix: prevent multiple instances of WC_Payments_Apple_Pay_Registration
* Dev - Fix gutenberg blueberry focus color
* Dev - Fix progressive onboarding e2e test
* Dev - Some refactors to embedded KYC logic.

= 8.2.2 - 2024-09-24 =
* Fix - Fix WooPay pre-checking place order bug when buying a subscription.


= 8.2.1 - 2024-09-13 =
* Fix - Create div container element with JS dynamically.


= 8.2.0 - 2024-09-11 =
* Add - add: test instructions icon animation
* Add - Added Embdedded KYC, currently behind feature flag.
* Fix - Avoid unnecessary account data cache refresh on WooPayments pages refresh.
* Fix - Check payment method is available before rendering it.
* Fix - Disables custom checkout field detection due to compatibility issues and false positives.
* Fix - Disables testing instructions clipboard button on HTTP sites when navigator.clipboard is undefined.
* Fix - fix: missing translations on testing instructions.
* Fix - fix: platform_global_theme_support_enabled undefined index
* Fix - fix: testing instructions dark theme support
* Fix - Fix caching with tracking cookie.
* Fix - Fixed an issue where the Connect page would scroll to the top upon clicking the Enable Sandbox Mode button.
* Fix - Fixed default borderRadius value for the express checkout buttons
* Fix - Fix shipping rates retrieval method for shortcode cart/checkout.
* Fix - Fix support for merchant site styling when initializing WooPay via classic checkout
* Fix - Fix WooPay direct checkout.
* Fix - Handle loadError in ECE for Block Context Initialization.
* Fix - Move woopay theme support checkbox to the appearance section.
* Fix - Pass appearance data when initiating WooPay via the email input flow
* Fix - Prevent preload of BNPL messaging if minimum order amount isn't hit.
* Fix - Redirect user to WooPay OTP when the email is saved.
* Fix - Remove obsolete ApplePay warning on wp-admin for test sites.
* Fix - Update cache after persisting the User session via WooPay
* Fix - Updates test mode instructions copy for cards at checkout.
* Update - update: payment method fees in one line
* Update - Update Jetpack packages to the latest versions
* Dev - Fix failing e2e tests for saved cards.
* Dev - Fix Klarna product page message E2E test after the contents inside the iframe were updated.
* Dev - Migrate Klarna E2E tests to playwright. Reduce noise in E2E tests console output.
* Dev - Migrate multi-currency e2e tests to Playwright.

= 8.1.1 - 2024-08-29 =
* Fix - Ensure 55px is the maximum height for Apple Pay button.
* Fix - Fixed sandbox mode accounts being able to disable test mode for the payment gateway settings.


= 8.1.0 - 2024-08-21 =
* Add - Add button rules to appearance
* Add - Add heading rules to appearance
* Add - Add link rules to appearance
* Add - Adds skeleton loading element for BNPL payment messaging element on product details page.
* Add - Add tokenized cart PRB support to cart and checkout blocks.
* Add - Add WooPay global theme support flag
* Add - Enhance Sandbox mode onboarding by skipping KYC and making it fully automated for all supported countries.
* Fix - Added better error message when mandate is invalid.
* Fix - Do not enqueue Cart scripts if WooPayments is not enabled
* Fix - fix: alignment of info icon w/ WooPay/Link settings
* Fix - fix: cart association on tokenized PRB orders via custom session handler
* Fix - fix: platform_global_theme_support_enabled undefined index
* Fix - fix: translatable strings around currencies list
* Fix - Fix GooglePay button missing in Safari.
* Fix - Fix onboarding redirect loop when starting from Woo Settings Payments.
* Fix - Fix spacing on Express Checkout buttons.
* Fix - Fix uncaught error on the block based Cart page when WooPayments is disabled.
* Fix - Fix WooPay checkboxes while signed in.
* Fix - If a payment method fails to be created in the frontend during checkout, forward the errors to the server so it can be recorded in an order.
* Fix - Reverts changes related to Direct Checkout that broke the PayPal extension.
* Fix - Translate hardcoded strings on the Connect page
* Update - refactor: separate BNPL methods from settings list
* Update - Updated the integration between WooPayments Multi-Currency and Product Add-Ons.
* Update - Update outdated express checkout settings notification.
* Dev - Add e2e tests for critical flow: merchant account balance overview
* Dev - Bump WC tested up to version to 9.2.0
* Dev - Fix Klarna and Refund Failures E2E tests
* Dev - Match the Node version in nvm with the minimum version in package.json.
* Dev - Remove unnecessary console.warn statements added in #9121.
* Dev - Update bundle size checker workflow to support node v20
* Dev - Migrate to Docker Compose V2 for test runner environment setup scripts

= 8.0.2 - 2024-08-07 =
* Fix - Add opt-in checks to prevent blocking customers using other payment methods.
* Fix - Fix error in Express Checkout Element use with coupons.
* Fix - Only enable Direct Checkout when WooPayments gateway is enabled.


= 8.0.1 - 2024-07-31 =
* Fix - Reverts changes related to Direct Checkout that broke the PayPal extension.


= 8.0.0 - 2024-07-31 =
* Add - Add ECE support for multiple product types.
* Add - Add ECE support for WooCommerce Deposits.
* Add - Added filter to enable updating Level 3 data based on order data.
* Add - Add independent ECE instances in WC Blocks.
* Add - Add the new payment method logos to the connect page.
* Add - Apply WooPay direct checkout flow to alternative mini cart checkout button.
* Add - Pass Blocks checkout appearance on init WooPay
* Add - Pass product, blocks cart, classic cart and checkout on get WooPay session
* Add - Set ECE as Default for Express Checkout Buttons unless it was disabled.
* Add - Support adding tax details (corporate number) for Japan merchants (so can generate tax documents for consumption tax, aka VAT).
* Add - Use new payment_method_domains endpoint for domain registration.
* Add - Use Stripe's Express Checkout Element (ECE) for express checkout button previews in the settings when ECE is enabled.
* Fix - Allow Afterpay gateway to process payments when the state/county is optional for GB and NZ addresses.
* Fix - Allow the purchase of physical subscriptions using ECE if no shipping options are defined.
* Fix - Disable ECE for non shipping products if Tax is calculated on billing address.
* Fix - Disable WooPay’s Direct Checkout feature if WooPayments is not enabled as payment gateway.
* Fix - Ensure that 'wcSettings' exists before attempting to use 'wcSettings.wcBlocksConfig'.
* Fix - Ensure the 'Proceed to Checkout' button does not collapse when adding a loading spinner, in the Direct Checkout flow.
* Fix - Fix Express Checkout Element button width.
* Fix - Fixing fatal errors when subscription classes are not available
* Fix - Fix minimum width for express checkout buttons on checkout block.
* Fix - Fix payment method title for Express Checkout Element orders.
* Fix - Fix UI state when processing ECE payment on Cart Block.
* Fix - Fix WooPay opt-in blocks field on WooCommerce 9.1+.
* Fix - Make Google Pay ECE compatible with WooPay.
* Fix - Prevent WooPay opt-in from blocking the place order button
* Fix - Remove bullet from WooPay button on cart block in Safari
* Fix - Replace WooPay's development environment constants with global variables.
* Fix - Send optional fields data to WooPay.
* Fix - Updates payments settings copy to support pay now with klarna.
* Fix - Use the customer id saved in the subscription to process renewal payments.
* Update - Deprecate Giropay.
* Update - Increase font size and update the design of the WooPay button
* Update - Reorder onboarding wizard business types to always have Company as the first option.
* Update - Set express checkout max button height to 55px
* Dev - Add error logging to ECE critical endpoints.
* Dev - Avoid using deprecated hook for processed checkout order.
* Dev - Bump WC tested up to version to 9.1.2
* Dev - Update bundle size checker workflow to support node v20
* Dev - Update node to v20

= 7.9.2 - 2024-07-18 =
* Fix - Fix store connection loop for onboarding flows started from the Woo > Settings > Payments page.


= 7.9.1 - 2024-07-11 =
* Fix - Fix Documents API regex to allow documents with dashes in name to be viewed.


= 7.9.0 - 2024-07-10 =
* Add - Add a separate transient to save UPE appearance styles for the Add Payment Method standalone page. Correct regression that prevented proper styles calculation in the shortcode checkout.
* Add - Add Pay for Order support in Express Checkout Elements.
* Add - Add support for configuring button radius when ECE is enabled
* Add - Add support for ECE elements on the Shortcode Cart and Checkout pages
* Add - Add support for the Express Checkout Element on product pages.
* Add - Add telemetry events from PRBs into ECE.
* Add - Ensure shoppers can still checkout, even when WooPay is slow or unavailable.
* Add - feat: tokenized cart PRBs on shortcode cart and checkout behind feature flag.
* Add - Support style settings for ECE buttons
* Fix - Clearly display available instant deposit amount on notice and button label on Payment Overview page
* Fix - Disable Stripe Link in ECE.
* Fix - Disable WooPay for suspended and rejected accounts.
* Fix - Display an invalid address error instead of generic one in the checkout form when Afterpay is selected as payment method
* Fix - Display payment error message in the Payment context with Blocks.
* Fix - fix: display refund amount w/ tokenized cart PRBs
* Fix - fix: pricing decimal formatting for tokenized cart
* Fix - fix: tokenized PRBs payment type
* Fix - Fixed an error when renewing subscriptions without a billing country
* Fix - Fix output for compatibility data.
* Fix - Fix transaction list and document list advanced filter styling issue preventing dates to be input on mobile screens.
* Fix - Fix WooPay Direct Checkout feature check.
* Fix - Fix WooPay OTP modal not rendering on the shortcode checkout if BNPL methods are available.
* Fix - Hide payment methods with domestic transactions restrictions (Klarna, Affirm, Afterpay) when conditions are not met.
* Fix - Make the search box, and typed search term visible clearly on the 'Payments > Transactions' page, when there are too many existing search tags.
* Fix - Properly wait for tokenized cart data updates before refreshing PRB data.
* Fix - Retrieve saved tokens only relevant for the specific payment gateway.
* Update - Deprecate Giropay.
* Update - Update payment receipt settings to remove mention of the printed receipts.
* Dev - Add validation for path variables.
* Dev - Migrate Affirm and Afterpay payment method components to TypeScript.
* Dev - Prevent infinite loop in usePaymentFailHandler effect
* Dev - Refactor redirects logic in payments

= 7.8.1 - 2024-06-25 =
* Fix - Fix "Dispute not loaded" error that was affecting responding to disputes.


= 7.8.0 - 2024-06-19 =
* Add - Add a feedback survey modal upon deactivation.
* Add - Add new select component to be used for reporting filters, e.g. Payments overview currency select
* Add - Add payment processing using ECE in the Blocks checkout and cart pages.
* Add - Add the WooPay Direct Checkout flow to the classic mini cart widget.
* Add - Add woocommerce-return-previous-exceptions filter
* Add - Enable adapted extensions compatibility with Direct Checkout.
* Add - feat: add pay-for-order support w/ tokenized cart PRBs
* Add - Fix ECE not working without WooPay.
* Add - Reset notifications about duplicate enabled payment methods when new plugins are enabling them.
* Fix - Fall back to credit card as default payment method when a payment method is toggled off.
* Fix - fix: address normalization on checkout for tokenized cart PRBs
* Fix - fix: itemized totals & pending amount on tokenized cart
* Fix - fix: Store API tokenized cart payment method title
* Fix - Fixes some cases where redirects to the onboarding will open in a new tab.
* Fix - Fix input-specific credit card errors.
* Fix - Fix Payment method title for PRBs not displaying correctly because of ECE code.
* Fix - Fix Teams for WooCommerce Memberships on product WooPay Express Checkout Button.
* Fix - Fix WooPay Direct Checkout feature check.
* Fix - Improve consistency of Manage button for different WooPayments KYC states
* Fix - Make it so that the WooPay button is not triggered on Checkout pages when the "Enter" key is pressed on a keyboard.
* Fix - Prevent account creation during WooPay preflight request.
* Update - chore: update incompatibility notice wrapping
* Update - Declare compatibility with the Cart and Checkout blocks.
* Update - Improve the transition from the WCPay KYC to the WC Admin Payments Task
* Update - Update the Payments Overview screen with a new currency selection UI for stores with multiple deposit currencies
* Update - Use FILTER_SANITIZE_EMAIL to sanitize email input
* Dev - Add New_Process_Payment_Exception
* Dev - Add Order_ID_Mismatch_Exception
* Dev - Add sh support in pre-push husky script.
* Dev - Add validation for path variables.
* Dev - Bump WooCommerce Tested To version to 8.9.2
* Dev - Bump WooCommerce Tested To version to 8.9.3
* Dev - chore: EPMs to always send shipping phone
* Dev - Clean up and refactor some old code which is no longer in use.
* Dev - Fix PHPStan warnings.
* Dev - Fix unused parameter phpcs sniffs in checkout classes.
* Dev - Improve test coverage of upe.js and rename isPaymentMethodRestrictedToLocation to hasPaymentMethodCountryRestrictions
* Dev - Remove redundant wrapper around method invocation.

= 7.7.0 - 2024-05-29 =
* Add - Add share key query param when sending data to Stripe KYC.
* Add - Add the WooPay Direct Checkout flow to the blocks mini cart widget.
* Add - feat: add multi-currency support to Store API.
* Add - feat: error message on 1M+ amount.
* Add - feat: tokenized cart PRBs on PDPs via feature flag.
* Add - Render ECE buttons behind a feature flag.
* Fix - Charm pricing and rounding options corrected for all currencies that aren't presented with decimal points.
* Fix - Fix "Pay for order" infinite loading when submitting form without payment details.
* Fix - fix: remove WooPay checkout pages scripts from non-checkout pages.
* Fix - fix: settings notices consistency.
* Fix - fix: Store API tokenized cart nonce verification.
* Fix - Fix a bug in Tracks where shopper events are not fired properly.
* Fix - Fix ECE error in the blocks checkout when PRBs are disabled.
* Fix - Fix Payment block render error while editing the block checkout page.
* Fix - Fix shortcode orders when using WooPay Direct Checkout.
* Fix - Improve visibility of WooPay button on light and outline button themes.
* Fix - Updating saved payment method billing address before processing the payment.
* Update - Do not auto-redirect to WooPay on page load.
* Update - Pass previous exception with exception.
* Update - Removed deprecated deposit_status key from account status.
* Update - Remove public key encryption setting from WooPayments settings.
* Update - Update XPF currency formatting.
* Dev - Add command to run QIT PHPStan tests.
* Dev - Add local release package support for PHPStan.
* Dev - Bump tested up to version for WP to 6.5 and WC to 8.9.1.
* Dev - Fix Klarna E2E tests.
* Dev - Guarantee REST intialization on REST request context (avoiding rest_preload_api_request context).
* Dev - Upgrade jetpack sync package version.

= 7.6.0 - 2024-05-08 =
* Add - Add additional data to Compatibility service
* Add - Add User Satisfaction Survey for Payments Overview Widget
* Add - Detect payment methods enabled by multiple payment gateways.
* Add - Display BNPL payment method logos on the thank you page.
* Add - Non user-facing changes. Behind feature flag. Add tooltip messages to tiles within Payment activity widget
* Add - Not user-facing: hidden behind feature flag. Use Reporting API to fetch and populate data in the Payment Activity widget.
* Add - Pre check save my info for eligible contries
* Add - Support for starting auto-renewing subscriptions for In-Person Payments.
* Fix - Add notice when no rules are enabled in advanced fraud settings
* Fix - Adjust positioning of BNPL messaging on the classic cart page.
* Fix - Avoid updating billing details for legacy card objects.
* Fix - Ensure the WooPay preview in the admin dashboard matches the actual implementation.
* Fix - fix: BNPL announcement link.
* Fix - fix: Stripe terms warning at checkout when Link is enabled
* Fix - Fix issue with transient check related to fraud protection settings.
* Fix - Fix order notes entry and risk meta box content when a payment is blocked due to AVS checks while the corresponding advanced fraud rule is enabled.
* Fix - Fix type error for fraud outcome API.
* Fix - Fix WooPay tracks user ID for logged in users.
* Fix - Hide Fraud info banner until first transaction happens
* Fix - Improve merchant session request with preloaded data.
* Fix - Improve signing of minimum WooPay session data.
* Fix - Make sure an explicit currency code is present in the cart and checkout blocks when multi-currency is enabled
* Fix - Prevent Stripe Link from triggering the checkout fields warning
* Fix - Remove risk review request from the transactions page.
* Fix - Resolves "Invalid recurring shipping method" errors when purchasing multiple subscriptions with Apple Pay and Google Pay.
* Fix - Revert: Add Multi-Currency Support to Page Caching via Cookies.
* Update - Add source param to onboarding and complete KYC links
* Update - Add support of a third-party plugin with PRBs into duplicates detection mechanism.
* Update - Remove feature flag for the pay-for-order flow
* Dev - Add Playwright e2e test suite ready for incremental migration and visual regression testing
* Dev - Avoid warnings about fatal error during plugin update due to problems with plugin initialization.
* Dev - Remove legacy method from `WooPay_Utilities`.
* Dev - Remove obsolete docker-compose key `version`
* Dev - Upgraded jetpack sync package version.

= 7.5.3 - 2024-04-22 =
* Fix - Fix subscription renewals exceptions
* Dev - Remove deprecated param from asset data registry interface.


= 7.5.2 - 2024-04-22 =
* Fix - Bugfix for failing subscription renewal payments.
* Dev - Remove deprecated param from asset data registry interface.


= 7.5.1 - 2024-04-18 =
* Fix - Avoid updating billing details for legacy card objects.
* Fix - fix: BNPL announcement link.


= 7.5.0 - 2024-04-17 =
* Add - Add a parent wrapper component for Payment Activity widget. This will be visible on the Payments Overview page
* Add - Add a task on WooCommerce Home page to remind accounts operating in sandbox mode to set up live payments.
* Add - Add BNPL messaging to cart page.
* Add - Add BNPL terms to checkout payment methods.
* Add - Added support to Cartes Bancaires
* Add - Adding a tracking event for external redirects to finish setup and start receiving deposits.
* Add - Add Multi-Currency Support to Page Caching via Cookies.
* Add - Extend 'skip WooPay' flag to user session.
* Add - feat: BNPL April announcement.
* Add - Improve payment settings UX.
* Add - Not user-facing: hidden behind feature flag. Add an empty state view for the Payments Activity widget. This is shown when the merchant is yet to have any transactions over WooPayments.
* Add - Not user facing - Changes are behind a feature flag. Adds the basic UI scaffold for the Payments Activity widget.
* Add - Prepopulate Vertical selection in the onboarding form based on Woo Core selection.
* Fix - Add an instructive error message when customer tries to use 2 different currencies for Stripe Billing subscriptions.
* Fix - Address PHPCS reports in checkout classes.
* Fix - Adds a check to see if the session exists before calling get()
* Fix - Change IP country rule after country settings are changed in WC settings page
* Fix - Defensive check for cart block PMME which hasn't yet been deployed.
* Fix - Don't register WooPay Order Webhook if account is rejected.
* Fix - Don't register WooPay Order Webhook if account is under review.
* Fix - Ensure "Proceed to checkout" button's loading spinner doesn't affect button spacing when Direct Checkout is enabled.
* Fix - Ensure that the currency configurations are set correctly when multi-currency is enabled.
* Fix - Ensure we avoid an infinite recursive call stack through 'wc_get_price_decimal_separator' filter.
* Fix - fix: error message on 402 status code
* Fix - Fix an incorrect warning about Puerto Rico being unsupported by WooPayments
* Fix - Fix collision between WooPayments header and Woo Express survey banner.
* Fix - Fix Decline button state for Accept loading on ToS modal
* Fix - Fixed Afterpay logo size on settings page
* Fix - Fixed billing address error for subscription without some billing details
* Fix - Fixed optional billing field validation
* Fix - Fix Fatal Error showing when connect to Jetpack on localhost
* Fix - Fix JS error when clicking GPay button on blocks checkout with subscription (w/sign up fee) in cart.
* Fix - Fix payment icons on connect page (Klarna, Afterpay)
* Fix - Hide account tools finish setup button for accounts completed the KYC and change it's link to Stripe KYC instead of the Stripe Express.
* Fix - Remove extra WooPay icon on connect page
* Fix - Remove incorrect "UTC" label from the time column of Transactions page
* Fix - Remove redundant message after the account is onboarded
* Fix - Resolves "Invalid recurring shipping method" errors when purchasing multiple subscriptions with Apple Pay and Google Pay.
* Fix - Sync discount brakedown with how server processes it
* Update - Adding a tracking event for external redirects to update account details, more consistent behaviour for redirects.
* Update - Cleanup unused payment confirmation code.
* Update - Reduce the visual footprint of the sandbox mode notice.
* Update - Remove @wordpress/data dependency in the email input iframe file
* Update - Remove ToS acceptance copy from all entrypoints in MOX
* Update - Remove unwanted css overrides on the Payment activity widget wrapper
* Update - Replace deprecated filter.
* Update - Update Discover and Diners logos
* Update - Updated Link by Stripe logo
* Update - Update links across the plugin from woo.com to woocommerce.com (previous platform HQ URL).
* Update - Update MOX cancellation to consistently redirect back to the payment connect page.
* Dev - Address update PHPCS error - a single one recommending escaping an exception message
* Dev - Add TypeScript development guidelines
* Dev - Add VariableAnalysis sniffs for better ergonomics around unused and undefined variables
* Dev - Changes are not user facing, behind a feature flag. Refactor - tooltip component flattened in place, instead of calling it from a separate file.
* Dev - Clear WP cache on writes inside Database_Cache
* Dev - Escaping error logs and ignoring noticese where there are no issues.
* Dev - Fix e2e tests for BNPL checkout
* Dev - Fixed phpcs errors
* Dev - Fixes to comply with updates to PHPCS linter.
* Dev - Ignore alternative function WordPress PHPCS sniffs in the GH workflows and tests
* Dev - Increase unit test coverage for WooPay direct checkout flow.
* Dev - Payments Activity - add scaffolding for Total Payment Volume.
* Dev - refactor: reduce wp-data dependency on shortcode checkout
* Dev - Updated PHPCS and sniffs used for static analysis
* Dev - Updates to remove deprecated function usage in the Jetpack Connection manager. Doesn't affect functionality.

= 7.4.0 - 2024-03-27 =
* Add - Add account reset for sandboxes
* Add - Add a loading spinner to the "Proceed to Checkout" button in shortcode cart.
* Add - Add data telemetry for the Proceed to Checkout button and the WooPay direct checkout.
* Add - Added a notice about custom checkout fields conflicting with express checkouut
* Add - Added a notice to inform the merchant when the payout bank account is in errored state
* Add - Added a notice to the Deposits Listing screen when deposits are paused
* Add - Add WooPay direct checkout flow behind a server-side feature flag.
* Add - Add woopayMinimumSessionData in wcpayConfig when Express Checkout button's disabled o on car page.
* Add - Customizing BNPL messaging with Appearance API
* Add - Handle refunds triggered externally, outside WP Admin
* Add - Preload WooPay session data for WooPay Direct Checkout flow.
* Fix - Add empty validation when quering order by meta key and meta value
* Fix - Add settings object for every gateway
* Fix - Allow WooPay button preview on settings page
* Fix - Apply the WooPay Direct Checkout flow only to the "Proceed to Checkout" button, in cthe classic cart.
* Fix - Ensure card gateway is not running through the settings migration.
* Fix - Ensure every gateway has individual settings object.
* Fix - Ensure WooPay Direct Checkout continues to work as intended when WooPay Express Checkout is disabled on the cart page.
* Fix - fix: "Add payment method" page initialization on missing UPE styles
* Fix - fix: better error message when saved card and amount too large
* Fix - Fixed a 1px gap on the right side of some payment method icons in transaction details.
* Fix - Fixed Clearpay aria-label for UK sites
* Fix - Fixes some instances where Stripe UPE styles add a black background to input fields.
* Fix - Fix the cursor pointer when hovering over disabled checkboxes in Advanced Settings
* Fix - Fix WooPay direct checkout eligibility checks.
* Fix - Hide the option to refund in full from the transaction details menu when a transaction is partially refunded.
* Fix - Inform hard blocked merchants they're under review
* Fix - Load deposit overview only when deposit is present
* Fix - Move test mode transactions notice to the top of the page.
* Fix - Order completed and refunded emails are no longer sent when a dispute is closed.
* Fix - Preventing stock quantity from being reduced twice.
* Fix - Re-enable Direct-to-Checkout Feature Flag in WooPay OTP Iframe.
* Fix - strtolower deprecation warning no longer appears on the Order Received and View Order pages when using PHP version 8.1+
* Fix - Used client side navigation to improve the UX for "View Deposit History"
* Fix - Uses WCPayAPI to initialise Stripe for BNPL payment element, includes necessary required parameters.
* Update - Allow WooPay to request full session data from store.
* Update - Clean up remaining unused code from a past experiment - `wcpay_empty_state_preview_mode`, done on Deposits list.
* Update - Make the order note for `dispute funds withdrawn` event clearly mention that the dispute amount and fee would be deducted from the next deposit.
* Update - Remove mention of test mode from general settings help text.
* Update - Updated deposits API documentation to add default_external_accounts element
* Update - Updates to reduce the amount of steps required during onboarding flow.
* Update - Update the Payments Connect page design and logic
* Update - Validate deposit id before sending a request to fetch deposit.
* Dev - Bump WC tested up to version to 8.7.0 and set Requires Plugins header.
* Dev - Ensure pre-push hook understands terminal & non-terminal environments
* Dev - Fix a bug in Tracks where admin events were not recorded properly
* Dev - Implement a feature flag for the Payment Overview widget.
* Dev - Minor refactor to disputes utility function inInquiry to make it accept only dispute status as a param, instead of whole dispute object.
* Dev - refactor: validator package bundle size improvements

= 7.3.0 - 2024-03-06 =
* Add - Added confirmation modals for order status changes with pending authorizations
* Add - Add migration script to cover situations with Link and WooPay both enabled after plugin update.
* Add - Add support for bookable products to payment request buttons on product pages.
* Add - Alert user when they try to leave advanced fraud settings page with unsaved changes
* Add - E2E tests for card testing prevention measures
* Add - feat: add UPE appearance filter
* Add - Handle timeouts in direct to WooPay checkout flow.
* Fix - Add checks for cart and checkout blocks to enqueue_style call.
* Fix - Added a small separator line between converted currency and the next line item.
* Fix - Added support for WooCommerce Deposits when using Apple Pay and Google Pay
* Fix - Adjustments to the wording of confirmation modals on the order and transaction details pages.
* Fix - Do not open the email input iframe when there is no wcpay as payment method
* Fix - Do not open the email input iframe without wcpay payment method
* Fix - Ensure gateways accessibility for use cases which don't require the gateway to be enabled
* Fix - Fees are now correctly applied to the Capture amount instead of the Authorize amount.
* Fix - fix: add confirmation modal when disabling WooPayments from settings page
* Fix - fix: ensure BNPL enablement is not adding unnecessary currencies
* Fix - fix: list of payment methods in disable confirmation modal
* Fix - fix: multi-currency confirmation modal ui
* Fix - fix: onboarding currency messaging for BNPLs
* Fix - fix: payment method checkbox loadable state
* Fix - fix: remove Afterpay EUR currency
* Fix - Fix a few untranslated phrases in the plugin
* Fix - Fix currency search input field size and margins in Woo Express.
* Fix - Fixed billing address line 2 not being updated for saved payment methods
* Fix - Fixed positioning on buy-now-pay-later Stripe element on product page.
* Fix - Fixes Stripe appearances API implementation to support dark themes and new elements, includes amends to checkout logos.
* Fix - Fix fraud prevention token not showing up on site editor checkout page
* Fix - Fix typo of transaction type "Loan dispersement" to "Loan disbursement" on transactions list page.
* Fix - Make tk_ai cookie usage cache compatible
* Fix - Only load `blocks-checkout.css` on single product, cart, and checkout pages.
* Fix - Rename log file to woopayments
* Fix - Show an informative tooltip instead of estimating the next deposit date (which can be inaccurate).
* Fix - Updating saved payment method billing address before processing the payment
* Update - Add deposit schedule help tooltip to deposits list screen so it's consistent with Payments Overview.
* Update - chore: convert payment request CTA to select.
* Update - chore: remove UPE feedback survey
* Update - On Payments Overview page, show total balance (pending + available) instead of pending balance.
* Update - Refactor WooPay First Party Auth and WooPay Direct Checkout to reuse similar functionality.
* Update - Update status chip to WooCommerce pill for transaction status component.
* Dev - Add Klarna e2e tests.
* Dev - Bump WC tested up to version to 8.6.0.
* Dev - chore: remove BNPL feature flag check
* Dev - chore: remove unused brandTitles property from PMs
* Dev - Dev: additional check when pushing to protected branches.
* Dev - Fire `wcpay_disputes_row_action_click` for any click to dispute details (not just `Respond` button).
* Dev - Remove unused `/deposits/overview` data-fetching code.
* Dev - Removing unsupported `deposits/overview` REST API. `deposits/overview-all` should be used instead.
* Dev - Reverts removed REST controller class to prevent error on update from older versions of the plugin.

= 7.2.0 - 2024-02-14 =
* Add - Add compatibility data to onboarding init payload.
* Add - Add WooPay direct checkout flow behind a feature flag.
* Add - Apply localization to CSV exports for transactions, deposits, and disputes sent via email.
* Add - Displaying Clearpay instead of Afterpay for UK based stores
* Add - Enhance WooPay session validation
* Add - Filtering APMs by billing country
* Add - Show a notice to the merchant when the available balance is below the minimum deposit amount.
* Add - Show charge id on payments details page, so merchants can grab it to fill out the dispute evidence form when needed.
* Add - Showing "started" event in transaction timeline
* Add - Support Stripe Link payments with 3DS cards.
* Fix - Adjust WordPress locale code to match the languages supported by the server.
* Fix - Displaying the correct method name in Order Edit page for HPOS
* Fix - Don't instantiate `Fraud_Prevention_Service` in checkout if processing an authorized WooPay request.
* Fix - fix: help text alignment with Gutenberg plugin enabled
* Fix - fix: pay-for-order compatibility with other gateways
* Fix - Fixed a bug where the 'deposits paused while balance is negative' notice was erroneously shown after an instant deposit.
* Fix - Fixes Pay for Order checkout using non-card payment methods.
* Fix - Fix losing cart contents during the login at checkout.
* Fix - Merge duplicated Payment Request and WooPay button functionality .
* Fix - Prevent coupon usage increase in a WooPay preflight check.
* Fix - Prevent WooPay webhook creation when account is suspended
* Update - Add source to the onboarding flow page and track it
* Update - Refactor the WooPay checkout flow UX
* Update - Some minor update to tracking parameters to pass additional data like Woo store ID.
* Update - Stop relying on Woo core for loading plugin translations.
* Dev - Added ENUM class for currency codes
* Dev - Bump WC tested up to version to 8.5.2.
* Dev - chore: removed deprecated functions since 5.0.0
* Dev - chore: remove unused checkout API methods
* Dev - chore: remove unused gateway class methods
* Dev - chore: remove unused isOrderPage return value from confirmIntent
* Dev - chore: update colors on documentation pages
* Dev - Comment: Bump qit-cli dependency to version 0.4.0.
* Dev - E2E test - Merchant facing multi-currency on-boarding screen.
* Dev - Fix for E2E shopper tests around 3DS and UPE settings
* Dev - Refactoring the tracking logic
* Dev - Refactor to how tracking events are defined for better readability.
* Dev - Remove unnecessary tracks events for dispute accept success/error.
* Dev - Update REST API documentation for deposits endpoints with changes to estimated and instant deposits
* Dev - Update Tracks conditions

= 7.1.0 - 2024-01-25 =
* Add - Add active plugins array to compatibility data.
* Add - Add post_types and their counts as an array to compatibility data.
* Add - Add the active theme name of the blog to the compatibility service
* Add - Expose the refund transaction ID in WooCommerce Order Refund API
* Add - Select the proper payment element when using saved Stripe Link tokens or choosing to use Stripe Link for new email.
* Add - Track filtering interactions on the Transactions page.
* Fix - Allow subscription purchase via Payment Request when no shipping methods are present.
* Fix - Allow zero-amount refunds for backwards compatibility with basic payment gateway and to allow re-stock of refunded orders.
* Fix - Checking if wcpayPaymentRequestPayForOrderParams before using it in Pay for Order page
* Fix - Checkout error when page URL is too long
* Fix - Comment: Fix QIT security tests errors.
* Fix - Fix incorrect test mode notice when left KYC early after going live from builder mode
* Fix - Fix network error that occurs when viewing an test mode order with test mode disabled, and vice versa.
* Fix - fix pay-for-order quirks and 3DS behavior
* Fix - Fix Safe Mode message reversed host
* Fix - Fix Stripe Link autofill on checkout.
* Fix - Fix Stripe Link button alignment in the Checkout Block
* Fix - Hide the transaction details refund menu for ineligble disputed transactions
* Fix - Improve clarity & readability of disputed order notice (not all text bold).
* Fix - Prevent possible fatal when using get_edit_post_link filter.
* Fix - Re-render WooPay button when cart updates, when checkout updates.
* Fix - Reinstate first deposit waiting period notice in payments overview (fix bug)
* Fix - Remove unnecessary import statement which leads to a warning when first loaded
* Fix - Resolved an error that would occur with WC 8.5.0 when editing a subscription customer from the admin dashboard.
* Fix - Resolved an issue that caused ordering the Admin Subscriptions List Table to not work when HPOS is enabled.
* Fix - Restock order items when performing full refund from transaction details page
* Fix - Reverting to manual styling over native WordPress components to fix CSS defects on Analytics page
* Fix - Send metadata in error message
* Fix - Show the correct number of days in the new account waiting period notice.
* Fix - Update WooPay tablet breakpoint.
* Fix - Verify that order exists before offering "Partial refund" option on transaction details page.
* Update - Changed the edit subscription product "Expire after" (Subscription length) so it more clearly describes when a subscription will automatically stop renewing.
* Update - Pass currency parameter and not transaction_ids parameter when creating instant deposit.
* Update - Store balance transaction ID in order metadata.
* Update - Updated BNPL sorting in settings for consistency with onboarding.
* Update - Update references to dev mode to use sandbox mode terminology.
* Update - Updates to the styling of the onboarding mode selection page.
* Update - Update style of notices within the deposits section of the settings screen.
* Dev - Added enum class for country codes
* Dev - Add new Tracks events to WooPay Save My Info checkbox
* Dev - Allow test pipelines to pass by slightly adjusting HTML selectors
* Dev - Merge UPE tests into the single and main gateway test file for unit and E2E tests.
* Dev - Place order button Tracks
* Dev - Track payment-request-button loads
* Dev - Update jetpack dependencies for syncing.
* Dev - Updates to account status logic to streamline it.
* Dev - Update subscriptions-core to 6.7.1.

= 7.0.0 - 2024-01-03 =
* Add - Add Account Management tools with reset account functionality for partially onboarded accounts.
* Add - Adding Compatibility Service to assist with flagging possible compatibility issues in the future.
* Add - Add refund controls to transaction details view
* Add - Add test mode notice in page order detail.
* Add - Display a Confirmaton Modal on enabling Test Mode
* Add - Introduce Customer currency, Deposit currency, Amount in Customer Currency and Deposit ID columns to the Transaction list UI and CSV export
* Fix - Allow test phone number as Support Phone in Dev mode
* Fix - Avoid using the removed deferred UPE flag
* Fix - Ensure proper backfilling of subscription metadata (i.e. dates and cache) to the postmeta table when HPOS is enabled and compatibility mode (data syncing) is turned on.
* Fix - Fetch and update the `_cancelled_email_sent` meta in a HPOS compatibile way.
* Fix - fix: account currency hook return value
* Fix - Fix account status error messages with links.
* Fix - Fix country names with accents not showing correctly on international country fraud filter
* Fix - Fix currency negative sign position on JS rendered amounts
* Fix - Fixed a Level 3 error occurring during the capture of an authorization for amounts lower than the initial authorization amount.
* Fix - Fixed Apple Pay Double Tax Calculation Issue
* Fix - Fixed broken styles in authorization capture notifications
* Fix - Fix incorrect amounts caused by zero-decimal currencies on Transactions, Deposits and Deposits CSV export
* Fix - Fix missing customer data from transactions report
* Fix - Fix missing order number in transaction reports CSV
* Fix - Fix WooPay integration with AutomateWoo - Refer a Friend extension.
* Fix - Improved error message for invalid payment method
* Fix - Include discount fee in fees tooltip
* Fix - Introduce WC_Payments_Express_Checkout_Button_Utils class.
* Fix - Pass the pay-for-order params to get the pre-fetch session data
* Fix - Prevents a PHP fatal error that occurs when the cart contains a renewal order item that no longer exists.
* Fix - Resolved an issue that would cause undefined $current_page, $max_num_pages, and $paginate variable errors when viewing a page with the subscriptions-shortcode.
* Fix - Revemoved pre-fretch session for button to prevent draft order creation
* Fix - Update account balances on the Payments Overview screen when an instant deposit is requested
* Fix - Update Qualitative Feedback note to have more efficient sql query.
* Fix - When HPOS is enabled and data compatibility mode is turned on, make sure subscription date changes made to postmeta are synced to orders_meta table.
* Fix - When using the checkout block to pay for renewal orders, ensure the order's cart hash is updated to make sure the existing order can be used.
* Update - Actualized cards-related assets for settings and transactions pages.
* Update - Cleanup the deprecated payment gateway processing - part II
* Update - Cleanup the deprecated payment gateway processing - part III
* Update - Confirmation when cancelling order with pending authorization. Automatic order changes submission if confirmed.
* Update - Updates the anchor text for the fraud and risk tools documentation link on the Payments Settings page.
* Update - Updates the behavior and display of the international IP address rule card if the rule is being affected by the WooCommerce core selling locations general option.
* Dev - Add e2e tests for the currency switcher widget.
* Dev - Added documentation for deposits REST API endpoints.
* Dev - Bump WC tested up to version to 8.4.0.
* Dev - Cleanup enqueueing of the scripts which were removed
* Dev - Cleanup the deprecated payment gateway processing - part IV
* Dev - Cleanup the deprecated payment gateway processing - part V
* Dev - Cleanup the deprecated payment gateway processing - part VI
* Dev - Comment: Fix declined 3DS card E2E test.
* Dev - Deprecate the WC_Subscriptions_Synchroniser::add_to_recurring_cart_key(). Use WC_Subscriptions_Synchroniser::add_to_recurring_product_grouping_key() instead.
* Dev - E2E test - Merchant facing: Multi-currency setup
* Dev - Improve E2E checkout tests
* Dev - Introduce a new wcs_get_subscription_grouping_key() function to generate a unique key for a subscription based on its billing schedule. This function uses the existing recurring cart key concept.
* Dev - Remove "Set-up refund policy" Inbox note as superfluous.
* Dev - remove unused factor flag for deferred UPE
* Dev - Thank you page Tracks event
* Dev - Updated subscriptions-core to version 6.6.0

= 6.9.2 - 2023-12-14 =
* Add - Notice is added when merchant has funds that are not yet available for deposit.
* Add - Show a deposit schedule notice on the deposits list page to indicate that future deposits can be expected.
* Fix - Show deposit schedule message when deposits are unrestricted
* Fix - Transactions List - indicate when a transaction is expected to be included in a future deposit


= 6.9.1 - 2023-12-07 =
* Fix - Display Klarna & Afterpay on the checkout for UK based stores


= 6.9.0 - 2023-12-06 =
* Add - Added cleanup code after Payment Processing - RPP.
* Add - Adds new option to track dismissal of PO eligibility modal.
* Add - Display an error banner on the connect page when the WooCommerce country is not supported.
* Add - Filter to disable WooPay checkout auto-redirect and email input hooks.
* Add - Handle failed transaction rate limiter in RPP.
* Add - Handle fraud prevention service in InitialState (project RPP).
* Add - Handle mimium amount in InitialState (project RPP).
* Add - Introduce filters for channel, customer country, and risk level on the transactions list page.
* Add - Store the working mode of the gateway (RPP).
* Fix - Add AutomateWoo - Refer A Friend Add-On support on WooPay.
* Fix - Add date_between filter for Authorization Reporting API.
* Fix - Add invalid product id error check.
* Fix - Allow Gradual signup accounts to continue with the Gradual KYC after abandoning it.
* Fix - Allow requests with item IDs to be extended without exceptions.
* Fix - Check that the email is set in the post global.
* Fix - Display notice when clicking the WooPay button if variable product selection is incomplete.
* Fix - Do not show the WooPay button on the product page when WC Bookings require confirmation.
* Fix - Enable deferred intent creation when initialization process encounters cache unavailability.
* Fix - Ensure express payment methods (Google and Apple Pay) correctly reflect eligible shipping methods after closing and reattempting payment.
* Fix - Fixes a redirect to show the new onboarding when coming from WC Core.
* Fix - Fix saved card payments not working on block checkout while card testing prevention is active.
* Fix - Pass the pay-for-order params to the first-party auth flow.
* Fix - Prevent merchants to access onboarding again after starting it in new flow.
* Fix - Remove unsupported EUR currency from Afterpay payment method.
* Fix - Show Payments menu sub-items only for merchants that completed KYC.
* Fix - Support 'variation' product type when re-adding items to a cart.
* Fix - When rendering customer reference in transaction details, fallback to order data.
* Fix - When rendering customer reference on transaction details page, handle case with name being not provided in the order.
* Update - Change PRB default height for new installations.
* Update - Cleanup the deprecated payment gateway processing - part I.
* Update - Correct some links that now lead to better documentation.
* Update - Enable the new onboarding flow as default for all users.
* Update - Exclude estimated deposits from the deposits list screen.
* Update - Improvements to the dev mode and test mode indicators.
* Update - Remove estimated status option from the advanced filters on the deposits list screen.
* Update - Replace the deposit overview transactions list with a "transaction history is unavailable for instant deposits" message.
* Update - Update Payments Overview deposits UI to simplify how we communicate upcoming deposits.
* Update - Update to the new onboarding builder flow to not prefill country/address to US.
* Dev - Add client user-agent value to Tracks event props.
* Dev - Add E2E tests for Affirm and Afterpay checkouts.
* Dev - Add E2E tests for checking out with Giropay.
* Dev - Added customer details management within the re-engineered payment process.
* Dev - Adds WCPay options to Woo Core option allow list to avoid 403 responses from Options API when getting and updating options in non-prod env.
* Dev - Bump WC tested up to version to 8.3.1.
* Dev - Fix a bug in WooPay button update Tracks.
* Dev - Introduce filter `wcpay_payment_request_is_cart_supported`.  Allow plugins to conditionally disable payment request buttons on cart and checkout pages containing products that do not support them.
* Dev - Upgrade the csv-export JS package to the latest version.

= 6.8.0 - 2023-11-16 =
* Add - Added mechanism to track and log changes to the payment context (reengineering payment process)
* Add - Add rejected payment method capability status
* Add - Per-country amount limits for payment methods
* Fix - Add Affiliate for WooCommerce support on WooPay.
* Fix - Add WooCommerce Multi-Currency support on WooPay.
* Fix - Allow customers using express payment methods (eg Apple Pay, Google Pay) to create an account automatically when purchasing subscription products if the store settings allow.
* Fix - Display express payment buttons on checkout blocks pay-for-order page
* Fix - Do not load WooPay button on external/affiliate product pages
* Fix - Ensure shortcode renders for Privacy Policy & Terms of Service.
* Fix - Fix builders being stuck after leaving KYC without finishing
* Fix - Fix Multi-Currency formatting for totals on My Account > Subscriptions page.
* Fix - Fix not allowed page when clicking Set up WooPayments from Core and account is already onboarded
* Fix - Fix spelling of cancellation
* Fix - Fix the amount conversion rate for blocked transactions on the transaction details page.
* Fix - Fix total price for Google Pay on quantity change.
* Fix - Fix transaction failure with UGX currency
* Fix - Fix WooPay session handler's Store API route checks.
* Fix - Handle checkout errors that appear during payment method creation request
* Fix - Redact Stripe support contact prompt from error message when capturing amounts greater than authorized.
* Fix - Remove references to In-Person Payments from Transaction settings when Cash on Delivery is disabled.
* Fix - Show Google Pay/Apple Pay buttons in the Pay for Order page
* Fix - Some array key info were not redacted in the logs
* Fix - Update Fraud & Risk tools radio input background color to match the current theme.
* Fix - Update plugin name to WooPayments
* Fix - Updates to the account status logic to reflect status more accurately in some cases.
* Update - Get WooPay 1st party auth flow to work on page load.
* Update - Restructure the pay-for-order check
* Update - Update links across the plugin from woocommerce.com to woo.com (new site URL).
* Update - Update pay-for-order js config billing email to session email
* Dev - Bump tested up to version for WP to 6.4
* Dev - Fire a tracks event for disputed order notice view.
* Dev - Introduce `wcpay_terminal_payment_completed_order_status` filter. Allows overriding the order status after a successful terminal payment.
* Dev - Remove outdated wcpay_transactions_download tracking events.
* Dev - Remove unused JS code
* Dev - Use automatic capture as default flag in new payment process.

= 6.7.1 - 2023-11-03 =
* Fix - Replaced the concrete logging class with a logger interface


= 6.7.0 - 2023-11-01 =
* Add - Added an internal logger class, meant for use by classes within src.
* Add - Added Authorizations reporting endpoint.
* Add - Added documentation for reports API
* Add - Added WooPay button locations setting
* Add - Add IE, DK, FI, NO, and SE as supported countries to Klarna payment method
* Add - Integrate Duplicate Payment Prevention Service to the new payment process (project RPP).
* Add - Introduce tracking for fraud purposes prior to WooPayments onboarding.
* Add - Use admin theme color and the correct WooCommerce colors across subscription interfaces.
* Add - Validate order phone number in InitialState (RPP).
* Fix - Add additional security checks in the plugin
* Fix - Capital loans page broken on WooCommerce 8.2.0
* Fix - Clear floats for payment request button wrapper.
* Fix - Correct the display style for duplicate relevant notices in the thank-you page.
* Fix - Ensure subscriptions have a date created that correctly accounts for the site's timezone. Fixes issues with subscriptions having a date created double the site's UTC offset.
* Fix - Fix Afterpay checkout error when shipping information is missing
* Fix - Fix Documents page loading on WooCommerce 8.2.0.
* Fix - Fixed tooltip alignment for mobile view in payment settings
* Fix - Fix Multi-Currency formatting on My Account > Orders page.
* Fix - Fix order status inconsistency in HPOS mode on Order Edit screen.
* Fix - Fix WooPay Same My Info adding unnecessary spacing to checkout
* Fix - Format the display of created date for payment intent instead of timestamp.
* Fix - Improve multi-currency compatibility with WooCommerce Deposits
* Fix - Limit early WooPay session requests based on feature flag.
* Fix - Prevent deleting wrong order in the duplicate payment prevention service
* Fix - Prevent settings access to partially onboarded accounts
* Fix - Prevent subscription from turning into manual renewal after deleting payment method.
* Fix - Removed link to setup intent
* Fix - Resolve an issue that would cause 3rd party plugin edit product fields with the show_if_variable-subscription class to be incorrectly hidden.
* Fix - Set WooPay first party feature flag to off when incompatible extensions are active.
* Fix - show/hide WooPay checkout page tooltip on click
* Fix - Show loading state when accepting a dispute from the transaction details screen.
* Fix - Update to properly show tooltip on Payments > Settings page when account is in PO state.
* Fix - When HPOS is enabled, fix quick-editing the subscription statuses on the admin list table.
* Update - Align deferred intent creation UPE checkout script with UPE inn terms of fonts appearance
* Update - Enables deferred intent UPE for existing legacy card stores
* Update - feat: add WooPay button SSR
* Update - Unify payment method icon design
* Update - Update Checkout appearance section to display WooPay checkout preview UI.
* Dev - Added authentication required state
* Dev - Add pay-for-order Tracks events
* Dev - Add Shopper Tracks events
* Dev - Bump WC and WP tested up to versions to 8.2.0 and 6.3
* Dev - Fixed version check regex
* Dev - PHP 8.1 compatibility
* Dev - Remove redundant Tracks events in WooPay OTP flow
* Dev - Restore skipped e2e merchant tests
* Dev - Update subscriptions-core to 6.4.0.

= 6.6.2 - 2023-10-20 =
* Fix - Fix PSR container conflicts
* Update - Deprecate Sofort for any merchants who have not enabled it. Warn existing merchants about future deprecation.


= 6.6.1 - 2023-10-19 =
* Fix - Fix Documents page loading on WooCommerce 8.2.0.
* Fix - Stripe Link eligibility at checkout


= 6.6.0 - 2023-10-11 =
* Add - Add a notice on the Settings page to request JCB capability for Japanese customers.
* Add - Add current user data to the onboarding init request payload. This data is used for fraud prevention.
* Add - Added API endpoint to fetch customer's saved payment methods.
* Add - Added docs for cancel_authorization endpoint
* Add - Added documentation for create payment intent API endpoint.
* Add - Added documentation for payment methods API endpoint
* Add - Add functionality to enable WooPay first party auth behind feature flag.
* Add - Add helper function/method for raw currency amount conversion.
* Add - Add Klarna payment method
* Add - Add loading state to WooPay button
* Add - Add payment intent creation endpoint
* Add - Add the feature flag check for pay-for-order flow
* Add - Add WC blocks spinner to the WooPay checkout styles.
* Add - Behind a feature flag: dispute message added to transactions screen for disputes not needing a response.
* Add - Display dispute information, recommended resolution steps, and actions directly on the transaction details screen to help merchants with dispute resolution.
* Add - Display server error messages on Settings save
* Add - Expand the data points added to the WooCommerce SSR to include all the main WooPayments features.
* Add - Handle server-side feature flag for new UPE type enablement.
* Add - Introduce the "Subscription Relationship" column under the Orders list admin page when HPOS is enabled.
* Add - Show survey for merchants that disable WooPay.
* Fix - Add Mix and Match Products support on WooPay.
* Fix - Add multi-currency enablement check in WooPay session handling.
* Fix - Comment: Behind a feature flag: Update documentation links (new/changed docs content) when notifying merchant that a dispute needs response.
* Fix - Disable automatic currency switching and switcher widgets on pay_for_order page.
* Fix - Ensure renewal orders paid via the Block Checkout are correctly linked to their subscription.
* Fix - Ensure the order needs processing transient is deleted when a subscription order (eg renewal) is created. Fixes issues with renewal orders going straight to a completed status.
* Fix - fix: save platform checkout info on blocks
* Fix - Fix Apple Pay and Google Pay if card payments are disabled.
* Fix - Fix error when disabling WCPay with core disabled.
* Fix - Fix init WooPay and empty cart error
* Fix - Fix modal header alignment on safari browser
* Fix - Fix onboarding section on MultiCurrency settings page.
* Fix - Fix WooPay express checkout button with product bundles on product page.
* Fix - Hide tooltip related to Storefront theme in Multi-Currency settings when Storefront is not the active theme
* Fix - Improved product details script with enhanced price calculation, and fallbacks for potential undefined values.
* Fix - Improve escaping around attributes.
* Fix - Load multi-currency class on setup page.
* Fix - Missing styles on the Edit Subscription page when HPOS is enabled.
* Fix - Only request WooPay session data once on blocks pages.
* Fix - Payment method section missing for Affirm and Afterpay on transaction details page
* Fix - Prevent charging completed or processing orders with a new payment intent ID
* Fix - Prevent WooPay-related implementation to modify non-WooPay-specific webhooks by changing their data.
* Fix - Prevent WooPay multiple redirect requests.
* Fix - Redirect back to the connect page when attempting to access the new onboarding flow without a server connection.
* Fix - Redirect back to the pay-for-order page when it's pay-for-order order
* Fix - Resolved an issue that caused paying for failed/pending parent orders that include Product Add-ons to not calculate the correct total.
* Fix - Speed up capturing terminal and authorized payments.
* Fix - Store the correct subscription start date in postmeta and ordermeta when HPOS and data syncing is being used.
* Fix - Tracking conditions
* Fix - Virtual variable products no longer require shipping details when checking out with Apple Pay and Google Pay
* Fix - When HPOS is enabled, deleting a customer will now delete their subscriptions.
* Fix - When HPOS is enabled, make the orders_by_type_query filter box work in the WooCommerce orders screen.
* Fix - WooPay save my info phone number fallback for virtual products
* Update - Adapt the PO congratulations card copy for pending account status.
* Update - Allow deferred intent creation UPE to support SEPA payments.
* Update - Enhance design of bnpl payment methods status in settings screen
* Update - Increase GBP transaction limit for Afterpay
* Update - Only display the WCPay Subscriptions setting to existing users as part of deprecating this feature.
* Update - Set WooPay First Party Authentication feature flag to default on.
* Update - Store customer currencies as an option to avoid expensive calculation.
* Update - Updated Transaction Details summary with added fee breakdown tooltip for disputed transactions.
* Update - Update links that pointed to the dispute details screen to point to the transaction details screen
* Update - Update Name Your Price compatibility to use new Compatibility methods.
* Update - Update the content of modals that are displayed when deactivating the WooPayments or Woo Subscriptions plugins when the store has active Stripe Billing subscriptions.
* Update - Update URL used to communicate with WooPay from the iFrame in the merchant site.
* Dev - Added missing API docs links for payment intents and payment methods API endpoints
* Dev - Capitalize the JCB label on transactions details page.
* Dev - e2e tests for progressive onboarding
* Dev - Extracting payment metadata and level 3 data generation into services.
* Dev - Migrate away from hooking into actions in certain classes
* Dev - Move fraud related service hooks out of class constructors and into new init_hooks methods.
* Dev - Move hooks out of MultiCurrency constructor into own init_hooks method.
* Dev - Refactored request class send() method
* Dev - Refactor to move hook initialisation out of constructors.
* Dev - This work is part of a UI improvements to increase disputes response that is behind a feature flag. A changelog entry will be added to represent the work as a whole.
* Dev - Update subscriptions-core to 6.3.0.

= 6.5.1 - 2023-09-26 =
* Fix - fix incorrect payment method title for non-WooPayments gateways


= 6.5.0 - 2023-09-21 =
* Add - Add a new task prompt to set up APMs after onboarding. Fixed an issue where a notice would show up in some unintended circumstances on the APM setup.
* Add - Add an option on the Settings screen to enable merchants to migrate their Stripe Billing subscriptions to on-site billing.
* Add - Added additional meta data to payment requests
* Add - Add onboarding task incentive badge.
* Add - Add payment request class for loading, sanitizing, and escaping data (reengineering payment process)
* Add - Add the express button on the pay for order page
* Add - add WooPay checkout appearance documentation link
* Add - Fall back to site logo when a custom WooPay logo has not been defined
* Add - Introduce a new setting that enables store to opt into Subscription off-site billing via Stripe Billing.
* Add - Load payment methods through the request class (re-engineering payment process).
* Add - Record the source (Woo Subscriptions or WCPay Subscriptions) when a Stripe Billing subscription is created.
* Add - Record the subscriptions environment context in transaction meta when Stripe Billing payments are handled.
* Add - Redirect back to the pay-for-order page when it is pay-for-order order
* Add - Support kanji and kana statement descriptors for Japanese merchants
* Add - Warn about dev mode enabled on new onboarding flow choice
* Fix - Allow request classes to be extended more than once.
* Fix - Avoid empty fields in new onboarding flow
* Fix - Corrected an issue causing incorrect responses at the cancel authorization API endpoint.
* Fix - Disable automatic currency switching and switcher widgets on pay_for_order page.
* Fix - Ensure the shipping phone number field is copied to subscriptions and their orders when copying address meta.
* Fix - Ensure the Stripe Billing subscription is cancelled when the subscription is changed from WooPayments to another payment method.
* Fix - express checkout links UI consistency & area increase
* Fix - fix: save platform checkout info on blocks
* Fix - fix checkout appearance width
* Fix - Fix Currency Switcher Block flag rendering on Windows platform.
* Fix - Fix deprecation warnings on blocks checkout.
* Fix - Fix double indicators showing under Payments tab
* Fix - Fixes the currency formatting for AED and SAR currencies.
* Fix - Fix init WooPay and empty cart error
* Fix - Fix Multi-currency exchange rate date format when using custom date or time settings.
* Fix - Fix Multicurrency widget error on post/page edit screen
* Fix - Fix single currency manual rate save producing error when no changes are made
* Fix - Fix the way request params are loaded between parent and child classes.
* Fix - Fix WooPay Session Handler in Store API requests.
* Fix - Improve escaping around attributes.
* Fix - Increase admin enqueue scripts priority to avoid compatibility issues with WooCommerce Beta Tester plugin.
* Fix - Modify title in task to continue with onboarding
* Fix - Prevent WooPay-related implementation to modify non-WooPay-specific webhooks by changing their data.
* Fix - Refactor Woo Subscriptions compatibility to fix currency being able to be updated during renewals, resubscribes, or switches.
* Fix - Update inbox note logic to prevent prompt to set up payment methods from showing on not fully onboarded account.
* Update - Add notice for legacy UPE users about deferred UPE upcoming, and adjust wording for non-UPE users
* Update - Disable refund button on order edit page when there is active or lost dispute.
* Update - Enhanced Analytics SQL, added unit test for has_multi_currency_orders(). Improved code quality and test coverage.
* Update - Improved `get_all_customer_currencies` method to retrieve existing order currencies faster.
* Update - Improve the transaction details redirect user-experience by using client-side routing.
* Update - Temporarily disable saving SEPA
* Update - Update Multi-currency documentation links.
* Update - Update outdated public documentation links on WooCommerce.com
* Update - Update Tooltip component on ConvertedAmount.
* Update - When HPOS is disabled, fetch subscriptions by customer_id using the user's subscription cache to improve performance.
* Dev - Adding factor flags to control when to enter the new payment process.
* Dev - Adding issuer evidence to dispute details. Hidden behind a feature flag
* Dev - Comment: Update GH workflows to use PHP version from plugin file.
* Dev - Comment: Update occurence of all ubuntu versions to ubuntu-latest
* Dev - Deprecated the 'woocommerce_subscriptions_not_found_label' filter.
* Dev - Fix payment context and subscription payment metadata stored on subscription recurring transactions.
* Dev - Fix Tracks conditions
* Dev - Migrate DetailsLink component to TypeScript to improve code quality
* Dev - Migrate link-item.js to typescript
* Dev - Migrate woopay-item to typescript
* Dev - Remove reference to old experiment.
* Dev - Update Base_Constant to return the singleton object for same static calls.
* Dev - Updated subscriptions-core to 6.2.0
* Dev - Update the name of the A/B experiment on new onboarding.

= 6.4.2 - 2023-09-14 =
* Fix - Fix an error in the checkout when Afterpay is selected as payment method.


= 6.4.1 - 2023-09-06 =
* Fix - checkout processing when fields are hidden via customizer
* Fix - Potential fatal error when viewing WooCommerce home because we try to check if store has been fully onboarded but account service is not yet initialized.
* Fix - Resolved an issue with WCPay Subscription orders being set to failed during payment processing when Woo Subscriptions plugin is active.
* Fix - Use the gateway from the Checkout class in case the main registered gateway isn't initialized for some reason.
* Dev - Revert - Bump minimum required version of WooCommerce to 8.0.0 and WP to 6.1
* Dev - Setting the minimum required version of WooCommerce back to 7.8.0 and WP to 6.0

= 6.4.0 - 2023-08-31 =
* Add - Added the Transactions reporting endpoint
* Add - Adjust WooPay "custom message" to be reused for T&C and privacy policy
* Add - Combine Session Initialization with User Authentication on WooPay.
* Add - Enables deferred intent UPE for existing split UPE stores and newly onboarded stores.
* Add - Onboarding flow state persistence
* Fix - Adds consistent payment token labels for saved Stripe Link payment methods across my account, shortcode checkout, and blocks checkout pages.
* Fix - Adds the possibility of continuing in progress onboarding process
* Fix - Add `is_user_connected()` and `get_connected_user_data()` methods to `WC_Payments_Http_Interface`
* Fix - Display onboarding MCC field validation error
* Fix - Ensures that Stripe Link and SEPA Debit saved payment tokens are stored and validated with correct gateway IDs for relevant feature flags enabled.
* Fix - Fixes subscription renewals with the UPE enabled.
* Fix - Fix express checkout button design issues.
* Fix - Fix phone number input widget on checkout page
* Fix - Fix the WooPay logo so that it stays scaled up and visible on the WooPay button.
* Fix - Fix zero decimal currency display in order notes
* Fix - JavaScript is now correctly loaded on admin order screens when HPOS is enabled.
* Fix - Prevent displaying "Fraud protection" menu on half-onboarded stores
* Fix - Prevent fetching disputes on WooCommerce home task when store is not connected to a WooPayments account.
* Fix - Prevent Progressive Onboarding accounts from adding APMs until completing full verification.
* Fix - Provide per active discount Terms and Conditions link in the Account details card.
* Fix - Remove precision overriding in multi-currency scenarios
* Fix - Use domestic currency, instead of default deposit currency, to check if a payment method is compatible with the presentment currency in the checkout form.
* Update - BNPLs: updated methods copy on settings page
* Update - Change Payment_Intent_Status to Intent_Status
* Update - Improve `Chip` component styles to with improved visual design and accessible contrast ratios.
* Update - Removed wcpay_empty_state_preview_mode_v5 experiment code
* Update - Set WooPay button default enabled for product/cart/checkout pages
* Update - Updated copy for credit and debit card in settings
* Update - Updated payment method tooltip in settings so that it is rendered correctly in mobile view
* Update - Updated section "Payment Methods" in page "Settings" for mobile view
* Update - Update express checkouts section in settings for mobile view
* Update - Update tooltip styles to improve readability of interactive tooltip content.
* Dev - Adding dispute object to DisputeDetails component
* Dev - Adding HooksProxy and LegacyProxy to src, used to access code outside of it (hooks, globals, functions, and static methods).
* Dev - Adding Psalm checks to function calls within `src`.
* Dev - Add interface and concrete classes for Payment Method (project reengineering payment process).
* Dev - Add LegacyContainer to `src` to allow loading classes from `includes`.
* Dev - Add TypeScript development guidelines
* Dev - Bump minimum required version of WooCommerce to 8.0.0 and WP to 6.1
* Dev - Fixing a mistake in the doc regarding the customer service.
* Dev - Fix Tracks to record checkout view on all stores
* Dev - Ignore updating currency precision if the country is Japan
* Dev - Move Multi-Currency Order Meta Helper functionality behind url param.
* Dev - Refactor the deposit status UI element to use the `Chip` component.
* Dev - Track WooPay Save My Info checkbox usage

= 6.3.2 - 2023-08-17 =
* Fix - Revert fix WooPay Session Handler in Store API requests.


= 6.3.1 - 2023-08-14 =
* Fix - Fix AutomateWoo error on WooPay redirection.
* Fix - Fix WooPay Session Handler in Store API requests.

= 6.3.0 - 2023-08-09 =
* Add - Add constant flag to use the new payment service (project reengineering payment process).
* Add - Add JCB payment method coming soon notice
* Add - Add payment service class (project reengineering payment process).
* Add - Adds integration for Stripe Link while using split UPE with deferred intent creation.
* Add - Add support for Japan
* Add - Add support for the Enabled status from Stripe (will replace Restricted Soon in the case where there is no active deadline).
* Add - Add support for United Arab Emirates
* Add - Add Tracks events around account connect when promo is active.
* Add - Add warning to the advanced settings page for WooPay incompatible extensions
* Add - Add WooPay Gift Cards support.
* Add - Add WooPay on onboarding payment methods.
* Add - Add WooPay Points and Rewards support
* Add - Allow WooPay verified email requests
* Add - Ensure WooPay compatibility with the split UPE that has deferred intent creation implementation.
* Add - Include WooPay merchant terms on WooCommerce Payment Methods settings.
* Add - Prefill the Business Name and Country fields during WooPayments KYC.
* Fix - Adding more descriptive error messages in gradual signup
* Fix - Allow card gateway to load properly with WooPay enabled and subscription item in the cart.
* Fix - Allow only domestic payments for BNPL payment methods
* Fix - Allow only Japanese phone numbers for Japanese accounts
* Fix - Avoid creating duplicate paid orders from a single payment intent.
* Fix - Disputes listing column renamed to Respond by and will show empty when dispute does not need response or if respond by date has passed.
* Fix - Enable customers who have migrated a WCPay subscription to a tokenised subscription to pay for existing failed orders.
* Fix - Fatal error when using latest MailPoet with WooPay
* Fix - Fixed the creation of the nonce that is sent to WooPay
* Fix - Fix error while selecting product variations. Make the stripe payment messaging element load only if at least one BNPL method is active.
* Fix - Fix extra requests when clicking WooPay express checkout button.
* Fix - Fix Fraud and Risk Tools welcome tour to only show if Fraud and Risk banner learn more button is clicked and tour not previously dismissed.
* Fix - Get WooPay adapted extensions from server
* Fix - Highlight menu item when transaction details, deposit details, and disputes details page are opened.
* Fix - Improve split UPE support of WooPay with multiple payment methods enabled.
* Fix - Minor copy changes on the Set Up Real Payments modal.
* Fix - Remove daily deposits for JP merchants as it's not permitted by our payment processor
* Fix - Reverting change to the plugin name because of compatibility with iOS app.
* Fix - Send correct shipping address to Afterpay in Classic Checkout
* Fix - Send shipping address correctly to Afterpay in block checkout, when separate billing address is provided.
* Fix - Update excerpt in readme.txt to improve ranking
* Fix - Visual fixes for the Connect page hero.
* Update - Allows nulls in min/max payment ranges definitions for UPE payment methods
* Update - Minor copy fixes on the onboarding form.
* Update - Modify 'Contact WooCommerce Support' badge located on the 'Payments accepted on checkout' section of Payments > Settings.
* Update - Only show the post-onboarding congratulations message if user did not onboard in test mode.
* Update - Unify payment method icon design
* Dev - Add a dependency container for the new src directory.
* Dev - Add generic Get_Request class, and migrate current simple requests to use it
* Dev - Add PSR-4 autoloading for the src directory.
* Dev - Add unit tests to cover WooPay button eligibility.
* Dev - Add webpack script to generate RTL .css files and enqueue it for RTL languages
* Dev - Adjust coding standards to align with WC Core.
* Dev - Avoiding product-service exceptions during checkout, making debugging easier.
* Dev - Fix Husky post-merge script to conform to `sh` syntax
* Dev - Ignore updating currency precision if the country is Japan
* Dev - Introduce model class WC_Payments_API_Setup_Intention for setup intents
* Dev - Migrate certain WCPay shopper tracks to use wcpay prefix
* Dev - Migrate Chip component to TypeScript to improve code quality.
* Dev - Migrate DisputeStatusChip comp to TypeScript to improve code quality.
* Dev - Pass tracks identity to WooPay iframe
* Dev - Pass Tracks identity to WooPay to improve telemetry

= 6.2.2 - 2023-08-01 =
* Fix - Move the email title hook from the UPE class to the parent legacy gateway class, to avoid multiple callback invocations for the split UPE


= 6.2.1 - 2023-07-31 =
* Fix - Enhance query parameters validation in redirected requests.

= 6.2.0 - 2023-07-19 =
* Add - Add Android option in Device type advanced filter
* Add - Add dispute notice to the WooCommerce order screen to highlight disputes awaiting a response.
* Add - Added flag to allow us to remotely set if WooPay should be enabled or not for new merchants.
* Add - Add tooltip and ARIA labels to payment method logos in transaction list
* Add - Check for invalid extensions when one is activated or deactivated.
* Add - Make Affirm and Afterpay Stripe messaging show up correctly for variable products, especially when we change variations.
* Add - Prefill the store URL on the new Onboarding Form.
* Add - Sending preloaded_requests to WooPay to avoid waiting for external requests
* Fix - Add array_filter callback method
* Fix - Added logic to check if the recurring cart array is present before displaying the recurring totals section in the cart.
* Fix - Allow webhooks without livemode to be received.
* Fix - Ensure when a customer changes the shipping method on cart and checkout that the recurring totals correctly reflect the chosen method.
* Fix - Fix a fatal error on sites using WC Subscriptions versions below 4.0.0
* Fix - Fix Country informed as empty for logged-out user in the BNPL site messaging configuration.
* Fix - Fixed typo in businessInfo strings in strings.tsx file
* Fix - Fix fatal errors when get_product method returns null
* Fix - Fix incorrect channel value in transaction description screen for Tap to Pay for Android transactions
* Fix - Fix issue where subscription signup fees are not converted correctly with Multi-Currency.
* Fix - Fix outdated documentation links.
* Fix - Fix Save my info section style when Payment options is not numbered.
* Fix - Remove duplicated payment method on thank you page when using WooPay.
* Fix - Resolve an issue that prevented the "Used for variations" checkbox from being enabled on the variable subscription product edit screen on WC version v7.9.0.
* Fix - Resolved an issue that caused the payment type metadata to not be included in payment requests.
* Fix - Resolved errors that occurred when activating the WC Subscriptions plugin via bulk action on the WP plugins screen or updating the plugin via the WooCommerce Extensions screen.
* Fix - Restore removed condition after naming convention.
* Fix - Reverting change to the plugin name because of compatibility with iOS app.
* Fix - When HPOS is enabled, permanently deleting a subscription related order wasn't updating the related orders cache properly.
* Fix - Wrap list of payment method logos on next line
* Update - Add incentive cache invalidation based on store context hash.
* Update - Another chunk of branding rollout: update wordpress.org assets
* Update - Check WCPay Subscriptions eligibility after store completes WooCommerce onboarding wizard.
* Update - Confirm subscription being switched is owned by customer before trying to possibly use its currency to prevent error.
* Update - Highlight the active dispute task if disputes are due within 72 hours.
* Update - Improve disputes list page by hiding the "Disputed on" column by default, add an "Action" column with clear call to action button, highlight urgent disputes' due dates, and color code disputes' statuses.
* Update - Mark an expired uncaptured order as 'Failed" instead of 'Canceled'
* Update - Refactoring and cleanup of code
* Update - Remove the Remind Me Later option from the Fraud and Risk Tools discoverability banner.
* Update - Remove WooCommerce Payments from taking over the WC core settings page
* Update - Simplify the active dispute task title when disputes are for multiple currencies to improve readability.
* Update - Update WooCommerce Payments to WooPayments across the plugin
* Update - WC Payments inbuilt subscriptions functionality is no longer enabled by default for eligible US based stores.
* Dev - Add E2E tests for Fraud & Risk tools.
* Dev - Adding a tracking property to record whether user went through the new onboarding UX or not.
* Dev - Add tool to allow merchants to fix Multi-Currency exchange rates in orders.
* Dev - Affirm&Afterpay: add new test cases to ensure the method availability on checkout
* Dev - Affirm&Afterpay: refactor subscription products detection by using existing subs API
* Dev - Extracting functionality for preventing duplicate payments into a service.
* Dev - Fix tests by ensuring the rest_pre_dispatch filter returns a WP_REST_Response
* Dev - Migrate `HorizontalList` component to TS
* Dev - Removed an old flag for a feature which is now enabled for old users. Some refactoring of the task lists code (no impact on functionality).
* Dev - Remove FRT E2E tests.
* Dev - Update subscriptions-core to 6.0.0.

= 6.1.1 - 2023-06-29 =
* Fix - Fix syntax for advanced filters in WC 7.8 and over

= 6.1.0 - 2023-06-28 =
* Add - Add additional validation in VAT controller.
* Add - Add Affirm and Afterpay to checkout block.
* Add - Add Affirm and Afterpay to classic checkout.
* Add - Add BNPL messaging to the product details page via Stripe Payment Method Messaging Element.
* Add - Added a check to disable WooPay in case incompatible extensions are found.
* Add - Adds implementation to handle deferred intent for the UPE in My account page.
* Add - Adds support for UPE with deferred intent creation on the Blocks checkout page.
* Add - Add Tap to Pay device type filter on transactions list page.
* Add - Add usage tracking to WooPay express button location updates.
* Add - Affirm and Afterpay logo support in transactions listing and transaction details.
* Add - Connect page incentive for eligible merchants.
* Add - Deposit schedule changes are disabled when an account has restrictions on it.
* Add - Ensure Affirm and Afterpay available on checkout only when the payment is in expected range.
* Add - Improve the wording and style of the "Active Disputes" task list item on the Payments → Overview screen to better communicate the urgency of resolving these disputes.
* Add - Links in subscription deactivation modal open in a new tab.
* Add - Show checkbox options for Affirm and Afterpay BNPL payment options.
* Add - Update Affirm & Afterpay logos with border and icon images.
* Add - Check for invalid extensions when one is activated or deactivated.
* Fix - Add deadline and amount to clarify disputed order note.
* Fix - Affirm&Afterpay: do not show messaging element for subscription products.
* Fix - Allow `card_` prefix when validating payment method IDs to fix failing subscription renewals.
* Fix - Check that a currency is available before adding it to the current currencies. Minor admin text string updates. Minor refactoring of MultiCurrency/RestController class.
* Fix - Corrected bug where checkbox could not be enabled when credit card was disabled.
* Fix - Fixed payment intents still getting confirmed on UPE when intent update fails.
* Fix - Fix untranslated strings on the checkout page.
* Fix - Fraudulent disputes will now show as Transaction unauthorized.
* Fix - Hide Google Pay and Apple Pay buttons when total amount is zero on item details, cart, and checkout pages.
* Fix - Improved user experience on onboarding form when validating fields if Enter key is pressed.
* Fix - Improve the logic which determines if a user is activating the WC Subscriptions plugin when determining the need to load built-in subscriptions functionality.
* Fix - Move WP hooks registration out of the core classes' constructors.
* Fix - Remove all actions on preflight check.
* Fix - Show descriptive dispute reasons in order notes.
* Fix - Updated correct link for request classes docs.
* Fix - Uses correct payment method title in order confirmation emails.
* Update - Display the "Active Disputes" task list item on the Payments → Overview screen only if there are disputes due within seven days.
* Update - Improve copy in Subscriptions deactivation modal.
* Update - Improve the wording of the "Active Disputes" task list item on the WooCommerce → Home screen to better communicate the urgency of resolving these disputes.
* Update - Moved the overview task list to the welcome greeting to improve visibility of important tasks.
* Update - Update the design for UPE settings block.
* Dev - Add support for Czech Republic, Hungary, and Sweden.
* Dev - Bump minimum required version of WooCommerce to 7.8.0.
* Dev - Comment: Add script to run QIT security tests locally.
* Dev - Gracefully handle missing payment method constants.
* Dev - minor refactor from js to tsx.
* Dev - minor tsx refactor.
* Dev - Tracking events for BNPL payment methods.

= 6.0.0 - 2023-06-08 =
* Add - Show Progressive Onboarding Express using Explat experiment
* Fix - Add a session check to avoid fatal errors.
* Fix - Add error notice when using ISK with decimals
* Fix - Adjust style of deposits icon and text in account details for progressive accounts
* Fix - Disabled subscription-related actions, webhooks, and invoice services on staging sites to avoid unintended interactions with the server and live site.
* Fix - Ensures 3DS authenticated payment methods can be saved and reused with deferred intent UPE.
* Fix - Ensures WCPay is present as default gateway on WC Settings screen when split UPE is enabled.
* Fix - Fix account status chip from restricted to pending for PO accounts
* Fix - Fixes pay for order page functionality in split and deferred intent UPE.
* Fix - Fix for PO eligible request
* Fix - Fix fraud and risk tools welcome tour copy to remove mentions of risk review.
* Fix - Fix incorrectly labelled card gateway for the enabled deferred intent creation UPE
* Fix - Fix single currency settings conversion preview for zero decimal currencies
* Fix - Fix to an API route for Progressive Onboarding feature which was previously only used in development environment.
* Fix - Just a few CSS updates for WooPay buttons.
* Fix - Progressive onboarding design fixes
* Fix - Resolves issue with multiple charge attempts during manual capture with UPE.
* Fix - Show setup deposits task for PO accounts without sales and right after acount creation
* Update - Connect account page design.
* Update - Pass the setup mode selected during Progressive Onboarding to the server onboarding init.
* Update - Update @wordpress/components to v19.8.5
* Dev - Behind progressive onboarding feature flag – Stardardize and send PO collected merchant info to server.
* Dev - Fixes intermittently failing UPE E2E tests.
* Dev - Fix failing e2e shopper test - WC Beta
* Dev - Migrate DepositsStatus and PaymentsStatus components to Typescript
* Dev - Remove fraud and risk tools feature flag checks and tests
* Dev - Skip failing E2E refund tests.
* Dev - Tracking for account balance section on the Payments > Overview page.
* Dev - Update @woocommerce/components to v12.0.0

= 5.9.1 - 2023-06-05 =
* Fix - Improve validation of WC analytics query filters
* Fix - Improved validation of the order key arg when redirecting to subscription's change payment method URL.
* Fix - Resolved an issue with customers being redirected to an incorrect Pay for Order URL after login.
* Dev - Update subscriptions-core to 5.7.2

= 5.9.0 - 2023-05-17 =
* Add - Adds the minimal functionality for the new Stripe payment flow that allows deferred payment/setup intent creation. The functionality is hidden behind the feature flag.
* Add - Add support for 6 new countries in WCPay: Bulgaria, Croatia, and Romania
* Add - Add support for complete_kyc_link wcpay-link-handler
* Add - Disable the WooPay auto-redirect and SMS OTP modal in unsupported contexts.
* Add - Enhanced fraud protection for your store. Reduce fraudulent transactions by using a set of customizable rules.
* Add - Hide WooPay button in unsupported contexts
* Add - PO fields data and controls, behind a feature flag.
* Add - Support pending verification account status
* Fix - Add fraud prevention token to cart and checkout payment request buttons
* Fix - Check for the `AbstractCartRoute` class before making WooPay available.
* Fix - Fatal error from third-party extensions using the `woocommerce_update_order` expecting the second parameter.
* Fix - Fix AJAX response notice for multi-currency due to empty currencies data
* Fix - Fixed blocks currency switcher widget
* Fix - fixed php 8.1 wp-admin errors
* Fix - Fix keyboard navigation for account balance tooltips on the Payments → Overview screen.
* Fix - Handle WooPay requests using Store API cart token and Jetpack blog token.
* Fix - Minor change for i18n - Remove extra trailing space in translation string, outside of the __ tag.
* Fix - No longer display the Deposits card on the Payments Overview page for new merchants who don't have an estimated deposit
* Fix - Prevent express checkout buttons from displaying when payments are disabled.
* Fix - Prevent WooPay redirection when OTP frame is closed
* Fix - Remove WooPay subscriptions user check endpoint.
* Fix - Supply correct payment method instance to process_redirect_payment.
* Fix - Usage tracking props when placing WooPay orders
* Fix - Use timezone to check WooPay available countries
* Update - Change from convention Platform Checkout to WooPay consistently.
* Update - Handle incorrect address errors in terminal location API
* Update - Refactor express payment method button display
* Update - Remove the `simplifyDepositsUi` feature flag and legacy deposits UI code.
* Update - Show a link to the documentation in the tooltip when the pending balance is negative.
* Update - Update @woocommerce/experimental to v3.2.0
* Update - Update @wordpress/data-controls to v2.6.1
* Update - WooPay specific admin area usage tracking
* Dev - Adds HTML coverage report for developer reference.
* Dev - Add the 'wcs_recurring_shipping_package_rates_match_standard_rates' filter to enable third-parties to override whether the subscription packages match during checkout validation.
* Dev - Behind progressive onboarding feature flag – Add PO eligibility modal.
* Dev - Convert fraud protection settings related JavaScript files to TypeScript.
* Dev - Enable third-party code to alter the delete payment token URL returned from flag_subscription_payment_token_deletions.
* Dev - Explicitly mention gridicons and @wordpress/primitives as dev dependencies.
* Dev - Pass the subscription object as the second parameter to `woocommerce_update_subscription` hook (and `woocommerce_update_order` for backwards compatibility).
* Dev - Remove pinned composer version 2.0.6 from workflows
* Dev - Resolve errors for third-party code using the URLs returned from WC_Subscriptions_Admin::add_subscription_url() and WCS_Cart_Renewal::get_checkout_payment_url() because they were erroneously escaped.
* Dev - Return a response from the WC_Subscription::set_status() function in line with the parent WC_Order::set_status() function.
* Dev - Run only pending timers to avoid recursive loop for AddPaymentMethodsTask tests.
* Dev - Update @woocommerce/currency to v 4.2.0
* Dev - Update @woocommerce/date to v4.2.0
* Dev - Update @woocommerce/explat to v2.3.0
* Dev - Update @wordpress/api-fetch to v6.3.1
* Dev - Update @wordpress/babel-plugin-makepot to v4.3.2
* Dev - Update @wordpress/base-styles to v4.3.1
* Dev - Update @wordpress/block-editor to v8.5.10
* Dev - Update @wordpress/blocks to v11.5.3
* Dev - Update @wordpress/data to v6.6.1
* Dev - Update @wordpress/date to v4.5.0
* Dev - Update @wordpress/element dependency to 4.4.1
* Dev - Update @wordpress/hooks to v3.6.1
* Dev - Update @wordpress/html-entities to v3.6.1
* Dev - Update @wordpress/i18n to v4.6.1
* Dev - Update @wordpress/icons to v8.2.3
* Dev - Update @wordpress/jest-preset-default to v8.1.2
* Dev - Update @wordpress/plugins to v.4.4.3
* Dev - Update @wordpress/scripts to v19.2.3
* Dev - Update @wordpress/url to v3.7.1
* Dev - Update react-dom dependency to 17.0.2
* Dev - Update react dev dependency to 17.0.2
* Dev - Update subscriptions-core to 5.7.1
* Dev - Update version detection API for subscriptions-core
* Dev - Update `@wordpress/dom-ready` to v3.6.1
* Dev - Usage tracking for deposits admin UI.

= 5.8.1 - 2023-05-03 =
* Fix - Fix WooPay express checkout button display issue on Cart blocks.


= 5.8.0 - 2023-04-26 =
* Add - Add additional fruad meta box statuses and messages.
* Add - Add order failed hook
* Add - Add settings page section anchors
* Add - Clear account cache on update plugin
* Add - Disable the WooPay auto-redirect and SMS OTP modal in unsupported contexts.
* Add - Hide WooPay button in unsupported contexts
* Add - New UI for Account Balances and Deposits on the Payments -> Overview admin screen, providing an enhanced user experience and a clearer account balance breakdown, upcoming deposit information, and deposit history.
* Add - Prefill Stripe KYC with hardcoded data for test mode
* Fix - Add check to prevent fraud & risk tools interacting with multi-currency if it is not enabled.
* Fix - Added phone number length validation
* Fix - Bump up the size of Capture action button on the Authorizations list page.
* Fix - Check for the `AbstractCartRoute` class before making WooPay available.
* Fix - Correctly determine subscription free shipping eligibility when the initial payment cart isn't eligible. Fixes erroneous "Invalid recurring shipping method" errors on checkout.
* Fix - Handle WooPay requests using Store API cart token and Jetpack blog token.
* Fix - Revert the added tests for Stripe Link due to rate limiting during concurrent execution.
* Fix - Support subscription_variation product type when re-adding items to a cart.
* Update - Improve how capture date deadline notice shows up in the details page
* Update - Replace fraud status logic to use the same data from the fraud meta box.
* Update - Update fraud transactions list to show manually blocked transactions in the correct tab.
* Update - Update Jetpack dependencies, remove outdated ones, and fix failing tests.
* Update - Updates legal mandate displayed prior to enabling WooPay.
* Update - Updates the display of the remind me later button and changes the text of the dismiss button to 'dismiss' on the fraud and risk tools discoverability banner.
* Update - Updates to the WordPress.org readme.txt file.
* Update - Update the status column on the Payments > Deposits table to display as a Pill.
* Update - Update the WooPay logo.
* Update - Use WooCommerce Pill component to show risk levels in the transactions list.
* Dev - Add payment_method_types array returned from Stripe payment_intent into WC_Payments_API_Intention object.
* Dev - Add PHP unit tests for multiple files.
* Dev - Bump minimum required version of WooCommerce to 7.4.1
* Dev - Bump WP (6.2) and PHP minimum version (7.2)
* Dev - Change all the JS files to TSX files in the client/components/fraud-risk-tools-banner directory and its subdirectories.
* Dev - Change is hidden behind feature flag, will have changelog added at later date
* Dev - Expands the test coverage for the Stripe Link.
* Dev - Fixed precision loss notice that occurs when running PHP 8.1.
* Dev - Fix phpcs and semgrep warnings to improve code quality.
* Dev - Migrate API get_account_data to use the core Request class.
* Dev - Migrated refunds API endpoints to request classes
* Dev - Migrate login_links, capital_links, tos_agreement to use the core Request class.
* Dev - Migrate update_account API to the core Request class.
* Dev - Remove the feature flag for custom deposit schedules, now enabled by default.
* Dev - Replace DAY_IN_MS with new constant TIME.DAY_IN_MS in the task-list index file.
* Dev - Update subscriptions-core to 5.6.0.
* Dev - Update the WCPay Pill component to use WooCommerce Core's Pill component
* Dev - Updating the multiline changelog entries to single line
* Dev - Use `wp_safe_redirect()` when processing a payment method change request.

= 5.7.0 - 2023-04-05 =
* Add - Add ClickTooltip and HoverTooltip components to improve mobile UX when tooltip content is interactive.
* Add - Add concurrency to workflows
* Add - Added a confirmation modal when Order Status is changed to Cancel on Edit Order page
* Add - Added Transaction Fee to order metadata and display it on Order admin screen.
* Add - Add GitHub workflow to build live branch and inform the zip file to Jetpack Beta Builder.
* Add - Add new fraud protection elements to the transaction details page.
* Add - Add order handling and meta box updates for fraud and risk tools.
* Add - Add order id support for payment details page.
* Add - Add risk review and blocked tabs to transactions list page.
* Add - Adds a filter to enable fraud settings.
* Add - Adds fraud and risk tools banner to WCPay overview page. All fraud and risk tools, including this banner, are currently behind a feature flag.
* Add - Add shopper IP country to fraud prevention metadata sent to the server
* Add - Add store currency support for the price threshold fraud rule.
* Add - Add welcome tour for fraud protection settings section.
* Add - Advanced fraud protection level settings user interface
* Add - Fraud protection settings data builder implementation
* Add - Implement connection to the Intelligent Router and flow switching
* Add - Introduce a Banner Notice component to be used in upcoming UI elements
* Add - Prefill Stripe KYC data for non-progressive onboarding scenario
* Add - Record the following events: wcpay_fraud_protection_banner_rendered, wcpay_fraud_protection_banner_learn_more_button_clicked, wcpay_fraud_protection_banner_remind_later_button_clicked, wcpay_fraud_protection_tour_clicked_through, wcpay_fraud_protection_tour_abandoned, wcpay_fraud_protection_risk_level_preset_enabled, wcpay_fraud_protection_advanced_settings_saved, wcpay_fraud_protection_transaction_reviewed_merchant_blocked, wcpay_fraud_protection_transaction_reviewed_merchant_approved, wcpay_fraud_protection_standard_modal_viewed, wcpay_fraud_protection_high_modal_viewed, wcpay_fraud_protection_advanced_settings_card_avs_mismatch_viewed, wcpay_fraud_protection_advanced_settings_card_cvc_verification_viewed, wcpay_fraud_protection_advanced_settings_card_international_ip_address_card_viewed, wcpay_fraud_protection_advanced_settings_card_international_billing_address_viewed, wcpay_fraud_protection_advanced_settings_card_address_mismatch_viewed, wcpay_fraud_protection_advanced_settings_card_price_threshold_viewed, wcpay_fraud_protection_advanced_settings_card_items_threshold_viewed, wcpay_fraud_protection_order_details_link_clicked
* Add - Show Tap to Pay icon in transactions list page for Tap to Pay transactions
* Fix - Check whether we have an instance of WC_Cart before invoking its methods on checkout
* Fix - Fatal errors on the thank-you page due to the strong type check in our filters.
* Fix - Fix bug with showing WC tasks when there is no WCPay account
* Fix - Fix new user not being saved in platform
* Fix - Fix the Request WCPay core class conflict with WP 6.2.
* Fix - Fix WooPay phone number on blocks checkout
* Fix - Prevent the WooPay Express button from showing up for pre-order products to be paid upon release.
* Fix - Sort uncaptured transactions with oldest first.
* Update - Add documentation links to fraud & risk tools.
* Update - Adds basic protection level modal and fixes standard and high level protection modal rules
* Update - Change the link in the Express checkouts setting.
* Update - Change user-facing wording from payouts to deposits.
* Update - Connection success notice.
* Update - Improve Fraud & Risk tools layout and add missing UI elements.
* Update - Replace custom tour component with TourKit from the WooCommerce components library.
* Update - Replace international billing address with IP country filter on fraud and risk tools
* Update - Update international filters to use selling locations settings instead stripe account country
* Update - Update test mode notice for Authorizations tab
* Update - Update the payment details page to reflect the Tap to Pay transactions channel
* Dev - Adds UPE deferred intent feature flag for future development
* Dev - Add userscript for WCPay Live Branch.
* Dev - Bump minimum required version of WooCommerce to 7.3.0.
* Dev - Made timezones consistent in tests by generating dynamically.
* Dev - Optimize asset bundling and loading
* Dev - Pass an instance of Platform_Checkout_Utilities when instantiating WC_Payments_Platform_Checkout_Button_Handler.
* Dev - Refactor the `Tooltip` component to use TypeScript
* Dev - Removed unused code from WC Payments API client class

= 5.6.2 - 2023-03-23 =
* Update - Security update.

= 5.6.1 - 2023-03-20 =
* Fix - Check whether we have an instance of WC_Cart before invoking its methods on checkout
* Fix - Fatal errors on the thank-you page due to the strong type check in our filters.
* Fix - Fix new user not being saved in platform

= 5.6.0 - 2023-03-15 =
* Add - Add a component that provides a tour experience.
* Add - Add a notice for Norwegian merchants using WCPay to update WooCommerce to at least 7.5
* Add - Added WC_Payments_Customer_Service_API in WCPayCore
* Add - Add Fraud Protection section to WCPay Settings screen. Section is hidden behind a feature flag DB option.
* Add - Add WCPay support for CY, LU, DK, EE, FI, GR, LT, LV, MT, NO, SI, SK
* Add - Display tap to pay fees in transaction timeline view
* Add - Moved methods used for order metadata update to the Order Service class.
* Add - Show PO todos in the Things To Do on WCPay overview page.
* Fix - Added function exist check to avoid fatal error for undefined function.
* Fix - Add merge queue trigger to the relevant workflows.
* Fix - Appending the countries dropdown to body to prevent it being clipped
* Fix - Fix bug with showing WC tasks when there is no WCPay account
* Fix - Fix deposit schedule monthly anchor label when set to 'last day of the month'.
* Fix - Fixes a problem where the Transactions table would have its layout modified when hovering over the currency conversion icon
* Fix - Fixes focus change on gateway select with split UPE enabled.
* Fix - Fixing the broken search on the Disputes page.
* Fix - Fix WooPay request on stores with WooPay disabled
* Fix - Prevent admin error notices being shown for the "subscription trial end" event that was caused by no callbacks being attached to this scheduled action.
* Fix - Prevent auto-scroll on page load when WooPay is enabled.
* Fix - Prevent WooPay and Link to be enabled at the same time
* Fix - prevent WooPay modal from showing up when editing the theme via wp-admin
* Fix - Re-enable split UPE E2E tests and ensure they pass.
* Fix - Remove the recurring shipping method cache that caused bugs for third-party plugins like Conditional Shipping and Payments.
* Fix - Replace PHP dependency myclabs/php-enum with a built-in solution due to conflicts with multiple PHP versions.
* Fix - Right align cutomize button on the settings page
* Fix - Use generated script dependencies to load script assets
* Fix - WCPay Checkout JS is loaded for zero carts
* Update - Log error message when calling redirect_to_login fails.
* Update - Updated express payment method UI in settings page
* Update - Update the deprecating set-output command
* Dev - Add a feature flag for fraud and risk tools features.
* Dev - Adding a centralized class to manage the gateway's mode (live/dev/test).
* Dev - Fixes a flaky e2e test.
* Dev - Made timezones consistent in tests by generating dynamically.
* Dev - Release automation - Run smoke tests automatically after release zip is built.
* Dev - Remove unused code for updating WCPay subscription dates.
* Dev - Update GH workflow runner images from Ubuntu 18.04 to 20.04
* Dev - Update subscriptions-core to 5.5.0

= 5.5.1 - 2023-03-01 =
* Add - When enabling WooPay, if legacy UPE is enabled, upgrades feature flag to split UPE instead.
* Fix - Avoid rendering save cards checkbox for logged out users
* Fix - Fix get woopay available countries return type
* Fix - Fix handling saved tokens for payment gateways while using shortcode checkout
* Fix - Fix subscription renewal creating multiple charges with UPE.
* Fix - Fix WooPay settings notice visibility

= 5.5.0 - 2023-02-22 =
* Add - Added learn more link to deposits page
* Add - Added tracking for the split UPE feature flag.
* Add - Declare WooCommerce Payments compatible with High-Performance Order Storage.
* Add - New support phone and email fields the general settings page.
* Add - Pass settings fields of the plugins that use newsletter block to woopay.
* Add - Pass the namespaces from the Store API checkout schema data to WooPay
* Add - Pass the store's test mode value to WooPay requests to the OTP endpoint.
* Add - The UPE is now the default checkout experience for newly onboarded merchants. It can sbe disabled with these instructions: https://woocommerce.com/?p=3337362#disabling
* Fix - Add wp-i18n as split UPE script dependency to load split UPE elements.
* Fix - Disable WooPay for unavailable countries
* Fix - Display an error when the request for initiating the platform checkout fails.
* Fix - External link accessibilty text style
* Fix - Fixes Stripe Link compatibility with split UPE payment gateway
* Fix - For stores using HPOS, ensure the originating subscription's currency is used when initiating a subscription switch.
* Fix - Make sure available payment methods are provided for the automatic subscription renewals.
* Fix - Point the "Learn more" link to a more appropriate document in the Apple Pay domain registration failure notification.
* Fix - Re-enabled email triggered WooPay flow with Express Checkout flow. WooPay Express Checkout is currently behind a feature flag.
* Fix - Remove unnecessary style dependency from WooPay checkbox.
* Fix - Track user viewport and url when using WooPay
* Update - Removed saved methods listing in My Account Add Payment Method page
* Update - Updated express payment method UI in settings page
* Update - Updated the Express checkout settings page
* Update - WooPay CTA text in shortcode checkout
* Dev - Adding a feature flag to allow further development of onboarding UX - currently this will have no effect on live stores.
* Dev - Merge progressive onboarding prototype under a feature flag

= 5.4.0 - 2023-02-01 =
* Add - Add logging and order notes when WCPay Subscriptions are suspended or put on-hold.
* Add - Highlight subscriptions with overdue payment in list view with red icon & tooltip.
* Add - More context to the business details to show the actual message from Stripe, shows a modal if there is more than one error from Stripe.
* Add - New wcs_set_order_address() helper function to set an array of address fields on an order or subscription.
* Add - Skipping the email input step for WooPay express checkout flow when the email input field has already a value. The WooPay express checkout feature is behind a feature flag currently.
* Fix - "Subscriptions by Payment Gateway" in WooCommerce → Status now shows the correct values when HPOS is enabled.
* Fix - Add check for session before getting properties from session to avoid fatals in WooCommerce Subscriptions Multi-Currency compatibility.
* Fix - Catch exceptions when changing payment method associated with a subscription to avoid fatal errors.
* Fix - Check whether the order actually exists before accessing order properties in wcs_order_contains_subscription().
* Fix - Decode entities for the store name in the WooPay preview section.
* Fix - Edit, add, and list Subscription admin pages now work when HPOS is enabled.
* Fix - Fatal error when loading the Edit Subscription page with custom admin billing or shipping fields.
* Fix - Fix ability to change currency when a subscription renewal is in the cart.
* Fix - Fixed issues where multiple subscription purchases wouldn't appear on the My Account > Subscriptions screen on HPOS environments.
* Fix - Fix TOS acceptance can prevent saving settings or accessing account dashboard
* Fix - Fix WooPay redirection with express checkout when email is prepopulated. The WooPay express checkout is currently behind a feature flag.
* Fix - Make the tooltip and fee description pill in Payments > Settings, show the correct Base rate when we have promotional rates applied.
* Fix - Merge any custom meta_query args passed to wcs_get_orders_with_meta_query() to avoid overriding WC core args that map onto meta_query.
* Fix - On HPOS environments, ensure subscription related order caches are updated when relationship order meta (eg `_subscription_renewal` or `_subscription_switch`) is updated.
* Fix - On HPOS environments, update related orders cache when subscription is trashed, deleted, or restored / untrashed.
* Fix - Prevent erroneously resyncing a subscription every time it is loaded from the database on HPOS environments.
* Fix - Prevent initializing `Fraud_Prevention_Service` on contexts without session
* Fix - Refactor `WCS_Meta_Box_Subscription_Data::save` to support HPOS stores, fixing a PHP warning notice when updating an order via the Edit Order screen.
* Fix - Refactor `WC_Subscriptions_Renewal_Order` and `WC_Subscriptions_Tracker` classes to support HPOS stores.
* Fix - Removed the potential for an infinite loop when getting a subscription's related orders while the subscription is being loaded.
* Fix - Repair a potentially corrupted state of enabled payment method settings, that causes an error while saving settings.
* Fix - Replace code using wp_count_posts(), get_post_type(), get_posts and wp_delete_post() with equivalent WC Data Store functions to support stores that have HPOS enabled.
* Fix - Set the `download_permissions_granted` value when purchasing a downloadable subscription product when HPOS is enabled.
* Fix - Shipping address correctly set when resubscribing to subscriptions that contains different billing and shipping addresses.
* Fix - Updates to the styling of the task list title on the main account overview page to match the component default.
* Fix - When a customer changes their address on their account or subscription, make sure the new address is saved when HPOS is enabled.
* Fix - When a subscription's parent order is trashed or deleted, make sure the related subscription is also trashed or deleted on stores with HPOS enabled.
* Fix - When a subscription is trashed or deleted, make sure it is cancelled first on stores with HPOS enabled.
* Fix - When processing customer requests to update all their subscription payment methods, ensure the updated subscription is used to fetch the new payment meta, not and old instance.
* Update - Add Apple Pay and Google Pay as supported payment methods in gateway descriptions.
* Update - Enable WooPay Express Checkout button feature by default.
* Update - Generate mandate only for orders using INR currency
* Update - Show the cards payment method when using UPE and make it active and locked as per UPE requirements.
* Dev - Add subscriptions-core library version to the WooCommerce system status report.
* Dev - Allow loading chunks with JS concatenation enabled.
* Dev - Fix failing unit tests due to spelling change in WooCommerce core.
* Dev - Fix phpcs violations in the `WC_Subscriptions_Tracker` and `WCS_Admin_System_Status` classes to improve code quality.
* Dev - Introduced a new `untrash_order()` in the `WCS_Orders_Table_Subscription_Data_Store` class to fix untrashing subscriptions on stores that have HPOS enabled.
* Dev - Introduced a WCS_Object_Data_Cache_Manager and WCS_Object_Data_Cache_Manager_Many_To_One class as HPOS equivalents of the WCS_Post_Meta_Cache_Manager classes.
* Dev - Moved the trash, untrash & delete related `add_actions()` in the `WC_Subscriptions_Manager` class to be added on the `woocommerce_loaded` action.
* Dev - Remove deprecated `strptime` function in favour of `DateTime::createFromFormat`.
* Dev - Skip running blocks E2E tests, add comment for the same.
* Dev - unified express checkout settings
* Dev - Update subscriptions-core to 5.2.0.
* Dev - Update subscriptions-core to 5.3.1.

= 5.3.0 - 2023-01-11 =
* Add - Added support for WooPay express checkout on product page. This feature is currently behind a feature flag and is not yet publicly available.
* Add - Add the processing order_id to WC Session and its handling to prevent duplicate orders
* Fix - Ensure script dependencies are loaded properly even when trying to register them again in the same request.
* Fix - Fix an issue which caused synced wcpay subscription to not sync the first payment date when the customer is charged upfront.
* Fix - Fix subscriptions remaining on-hold after processing a WCPay Subscription renewal on HPOS environments.
* Fix - Make the webhook processing respect the test/live mode setting for the gateway
* Fix - Prevent 'No such customer' errors after store is migrated to a new WCPay account.
* Fix - Prevent occasional fatal errors when creating customers via ActionScheduler jobs
* Fix - Updates subscriptions' payment token when a new default payment method is set.
* Update - Bail out before payment processing for WooPay's order validation request
* Update - Check the status of previously initiated payments and mark orders as processing instead of initiating a new payment.
* Update - Enhance save my info section on classic and Blocks checkout
* Dev - Bump minimum required version of WooCommerce to 7.1.1.
* Dev - Remove redundant compatibility utility class for admin notes.
* Dev - Replace typed payment intent strings with constants saved in an Enum clas
* Dev - Retry API requests on network failure if Idempotency-Key header is present.
* Dev - The PR replaces hard-coded order status constants, with a dedicated Enum class to make it reusable across the codebase.

= 5.2.1 - 2022-12-30 =
* Fix - UPE not loading on checkout page due to undefined i18n.

= 5.2.0 - 2022-12-21 =
* Add - Add WooPay Express Checkout button iframe functionality.
* Add - When deactivating WCPay plugin, warn merchant that active WCPay Subscriptions will continue collecting payment.
* Fix - Allow the "Your payment information is incomplete." UPE checkout block message to be translated.
* Fix - Extend Stripe Link availability check with available fees
* Fix - Prevent `Additional Payment Methods` (UPE) checkboxes within `Payments > Settings` , from getting unchecked and checked automatically while selecting multiple items quickly.
* Fix - Fix an undefined variable bug in WooPay Express Checkout.
* Fix - Fix for an issue where a timestamp in the past could be displayed on the update business details task card.
* Fix - Fix translations job from the post-release workflow
* Fix - Fix wrong time displayed in the "You need to capture this charge before..." text on the payment details page.
* Fix - Hide "capture charge" and "cancel authorization" actions on order details page when order status is processing or completed.
* Fix - Make the tooltips on UPE settings ( Settings > Payments accepted on checkout ) , show the correct custom rates whenever applicable.
* Fix - PR created by the code freeze workflow is now done by botwoo to allow PR checks to be triggered
* Fix - Prevent checkboxes from getting unchecked automatically within the UPE onboarding form, when we click multiple selections within a very short time ( 1.5 seconds )
* Fix - Recurring payments for cards issued by Indian banks.
* Fix - Remove the UPE notification badge on the Payments > Settings menu item
* Fix - Set commit author as github-actions bot for the code freeze workflow
* Fix - Updates subscriptions' payment token when a new default payment method is set.
* Update - Update currency_rate endpoint to not make a request if currency_from value is not valid/expected value.
* Dev - Bump minimum required version of WooCommerce to 7.0.
* Dev - Remove unused pre-release workflow
* Dev - Replace direct database queries which fetched orders with an invoice ID with an equivalent wc_get_orders() query
* Dev - Replace direct database queries which fetched subscriptions with a wcpay subscription ID with an equivalent wc_get_orders() query
* Dev - Show correct authorizations count when switching between live and test modes
* Dev - Support custom REMOTE_PORT for xDebug

= 5.1.2 - 2022-12-03 =
* Fix - Import critical package instead of lazy loading.

= 5.1.1 - 2022-12-02 =
* Fix - Minor patch fix to cron functionality that does not appear to have front-end ramifications for customers.

= 5.1.0 - 2022-11-30 =
* Add - Add a counter of pending authorizations to Uncaptured tab in Transactions page.
* Add - Added support for a WooPay express checkout button on checkout blocks. This feature is currently behind a feature flag and is not yet publicly available.
* Add - Adds encryption to the exposed `client_secret` to harden the store against card testing attacks
* Add - Add uncaptured transactions count badge to Transactions menu.
* Add - Enable the improvements to the authorization and capture workflow, that were hidden behind a feature flag.
* Add - Improve fingerprint mechanism on checkout page
* Add - New wcs_get_orders_with_meta_query() helper function to query for orders and subscriptions.
* Add - Send merchant's setting for ship_to_billing_address_only to platform checkout
* Add - Support creating new platform checkout user from checkout blocks.
* Fix - Fix an error in the Uncaptured transactions table when it is sorted using the Capture by column.
* Fix - Fix blocks checkout when card testing prevention is active
* Fix - fixed bug that would not allow customers to add new payment methods to WooPay
* Fix - Fixed bug that would not allow customers using firefox to log in to WooPay sometimes
* Fix - Fix the "Learn more" link on Express Payments section
* Fix - Fix undefined element error on Cart block for WooPay enabled site.
* Fix - Handle errors in retrieving a file gracefully
* Fix - On HPOS stores, ensure payment tokens are copied from the subscription to the renewal order.
* Fix - On HPOS stores, make sure the links in the related-orders table redirect to the new Edit Order URL.
* Fix - On HPOS stores, when a subscription is loaded from the database, make sure all core subscription properties are read directly from meta.
* Fix - On HPOS stores, when querying for subscriptions with wcs_get_orders_with_meta_query() with status 'any', ensure that wc_get_orders() queries for subscription statuses.
* Fix - On HPOS stores, when saving a subscription make sure subscription properties (ie `_requires_manual_renewal`) are saved to the database.
* Fix - Processing a manual renewal order with HPOS and data syncing enabled correctly saves the related order cache metadata on the subscription and prevents the post and order meta data getting out of sync.
* Fix - Redirect modal not closing when customer clicks back button on safari
* Fix - Refactor `WCS_Meta_Box_Schedule::save` to support HPOS stores, fixing a PHP warning notice when updating an order via the Edit Order screen.
* Fix - Return a fresh instance of the renewal order after creating it. Fixes caching issues on HPOS sites where the returned order has no line items.
* Fix - Set payment tokens when copying data between orders and subscriptions in a CRUD compatible way. Fixes PHP notices during renewal order process.
* Fix - Update margin to fix cropping of search field for Multi-Currency currency search
* Fix - Use botwoo user for a job in the post-release-updates workflow
* Fix - Use supported CRUD apis to determine if subscriptions are present on store (`wcs_do_subscriptions_exist`)
* Fix - When viewing My Account > Subscriptions, fix an issue where no subscriptions were listed when HPOS is enabled.
* Fix - With HPOS and data syncing enabled, updating the status of a pending manual renewal order to a paid status correctly activates the related subscription.
* Update - Display related orders table when viewing the new "Edit Order" page (HPOS enabled stores).
* Update - Don't load WooPay scripts and styles when the WCPay payment gateway isn't available.
* Update - Refactor our Related Orders data store classes (WCS_Related_Order_Store_Cached_CPT and WCS_Related_Order_Store_CPT) to use CRUD methods to support subscriptions and orders stored in HPOS.
* Update - Refactor the `wcs_is_subscription` helper function to support HPOS.
* Update - Replace instances of `get_posts()` across codebase with new wcs_get_orders_with_meta_query() function.
* Update - Update copy of warning modal appearing while deactivating Subscriptions extension.
* Dev - Add API docs for authorization endpoints
* Dev - Add description for capture authorization endpoint
* Dev - Add new workflow to amend (or generate) the changelog for the release
* Dev - Add PHPCS `PEAR.WhiteSpace.ObjectOperatorIndent` rule.
* Dev - Bump minimum required version of WooCommerce to 6.9 and WordPress to 5.9
* Dev - Fix section divider in Authentication API docs
* Dev - Introduce a WC_Subscription::set_status() function to handle subscriptions set with a draft or auto-draft status. Replaces the need for the overriding WC_Subscription::get_status() which has been deleted.
* Dev - Manual renewal orders created with HPOS and data syncing enabled are properly linked to the subscription by its `_subscription_renewal` meta and backfilled to posts table.
* Dev - Refactor the saving of subscription dates in the subscription datastore to separate fetching changes and saving. Enables backfilling subscription dates when HPOS syncing is enabled.
* Dev - Removed the deprecated "wcs_subscriptions_for_{$relation_type}_order" dynamic hook used to filter the list of related subscriptions for the given relation type. The following hooks have been removed with no alternative: wcs_subscriptions_for_renewal_order, wcs_subscriptions_for_switch_order, wcs_subscriptions_for_resubscribe_order
* Dev - Show uncaptured transactions tab only when some specific criteria is met

= 5.0.3 - 2022-11-15 =
* Fix - Purchasing a synced subscription with WCPay Subscriptions correctly sets the next payment date to the sync date in Stripe.

= 5.0.2 - 2022-11-14 =
* Fix - Fixed rest api error for payment_gateways endpoint

= 5.0.1 - 2022-11-10 =
* Fix - Fix fatal error when non-admin access admin pages.

= 5.0.0 - 2022-11-09 =
* Add - Add capture authorization support from the list of authorizations
* Add - Add capture authorization support from the payment details page.
* Add - Added a Refund Confirmation modal on Edit Order screen status change
* Add - Add endpoint to get platform checkout signature at time of request
* Add - Add event when skipped the platform checkout
* Add - Add Stripe Link set up inbox notification
* Add - New data copier class to copy data to subscriptions and related orders in place of direct database queries in prepraration for HPOS support.
* Add - New WCS_Orders_Table_Data_Store_Controller class to load the proper subscriptions data store when the store has HPOS enabled.
* Add - New WCS_Orders_Table_Subscription_Data_Store class to support subscriptions stored in High-Performance Order Storage (HPOS).
* Add - Pass the value of 'woocommerce_tax_display_cart' option from the merchant's store to WooPay
* Add - Updated the wording in the balance component header and added a link to the settings page.
* Add - Wire authorizations data to the transactions > uncaptured screen
* Fix - Adjust regex to check the format of a tag for a pre-release in our GitHub workflow
* Fix - Currency switcher block padding while editing it
* Fix - Enable Link to support authorization/capture scenarios.
* Fix - Fetch authorization data in payment details page only when the payment needs manual capture
* Fix - Fixed error when visiting the plugins page
* Fix - Fix platform checkout auto redirection for user with pre-populated email when they land on the checkout page.
* Fix - Prevent proceeding to WooPay when in a preview context.
* Fix - Update Stripe Link inbox notification wording.
* Fix - When saving sync meta data on a new subscription, use 'woocommerce_new_subscription' instead of 'save_post'. This is to prevent errors when purchasing a subscription on stores that have HPOS enabled.[.
* Update - Adjust texts and links in WC admin express checkout section.
* Update - Hide upload buttons and minor UI improvements on submitted dispute form.
* Update - Improve maybe_add_subscription_meta() and subscription_contains_synced_product() inside our WC_Subscriptions_Synchroniser class to use CRUD methods.
* Update - Improve wcs_copy_order_address() to use modern APIs for setting address fields.
* Update - Remove IE11 support.
* Update - The subscription creation function `wcs_create_subscription` has been updated to use WooCommerce CRUD methods in preparation for supporting High Performance Order Storage (HPOS).
* Update - Update priority for Multi-Currency filters for frontend prices and currency to later priority to avoid plugin conflicts.
* Dev - Add filter to record wcpay server api response time
* Dev - Add new GH workflow for post-release steps and improve formatting/naming in recent workflow files introduced
* Dev - Add new GitHub workflows for release management (pre-release and release packages)
* Dev - Add php unit tests watcher
* Dev - Adds a new tracking event when the new KYC informational modal is opened.
* Dev - Change mocked data in Authorizations store with actual data from API
* Dev - Deprecated the "wcs_{type}_meta" dynamic hook used to filter data copied to subscriptions and renewal orders. Third-parties should use wc_subscriptions_{type}_data instead.
* Dev - Deprecated the "wcs_{type}_meta_query" dynamic hook used to alter the database query used to fetch the meta data to copy between subscriptions and renewal orders. There is no direct replacement. Third-parties should use the "wc_subscriptions_{type}_data" or "wc_subscriptions_object_data" hooks instead.
* Dev - Fix tests with WordPress 6.1
* Dev - i18n usage of strftime has been deprecated for subscription titles. Date is now formatted using woocommerce standard date formatting.
* Dev - Refactor WC_Payment_Gateway_WCPay part 1
* Dev - Remove unnecessary babel plugins after IE11 support drop.
* Dev - Replace the use of the deprecated wcs_renewal_order_meta hook with wc_subscription_renewal_order_data in the WCS_Related_Order_Store_Cached_CPT class.
* Dev - Replace the use of the deprecated wcs_renewal_order_meta_query hook with wc_subscription_renewal_order_data
* Dev - Switch to @woocommerce/dependency-extraction-webpack-plugin
* Dev - Update a few workflows to use an env variable for the L-2 version of WC/WP
* Dev - Update Docker image to wordpress:php7.4 so client dev environment runs with PHP 7.4
* Dev - Update Gridicons imports to be indivuals, reducing our bundle size.
* Dev - Update husky to v 8.0.1.
* Dev - Update lodash to version 4.17.21
* Dev - Update subscriptions-core to 2.5.1.
* Dev - Update the deprecated interpolate-components npm package with the @automattic/interpolate-components package, and update to v1.2.1.
* Dev - wcs_get_objects_property and wcs_set_objects_property have been marked as deprecated. Getters/Setters should be used on the objects instead.
* Dev - woocommerce_new_subscription_data hook will only work with CPT datastore and so has been deprecated.

= 4.9.0 - 2022-10-20 =
* Add - Adds new notice and modal informing users about verifying their account during onboarding.
* Add - Declare WooCommerce Payments incompatible with COT
* Add - New Multi-Currency filter to display Analytics > Orders in customer currency.
* Fix - Fix dropdown menu appearance in UPE payment methods when Gutenberg is active.
* Fix - Fixed issue with Stripe rate limit during the checkout
* Fix - Fix fatal error with call to MultiCurrency/Compatibility::convert_order_prices method.
* Fix - Fix platform checkout store logo preview.
* Fix - Move One Time Shipping metabox fields to use the woocommerce_product_options_shipping_product_data hook introduced in WC 6.0.
* Fix - Prevent OTP modal from showing up when auto redirection is in progress.
* Fix - Prevent WooPay OTP after click Place Order button
* Fix - Save Link payment method tokens for subscription renewal
* Fix - The ellipsis menu is now readable and we can dismiss the task in the "Things to do" task list from Payments > Overview page
* Fix - Update default platform checkout host to pay.woo.com
* Update - Improve handling of subscription bulk action execution.
* Update - Update formatCurrency to decode HTML entities for rendering currency symbols.
* Update - Update webhook processing to override existing meta data.
* Dev - Add authorizations endpoints
* Dev - Added Apple Pay and Google Pay to tags for the plugin.
* Dev - Bump minimum required version of WooCommerce to 6.8 to support L2 policy
* Dev - changed WooPay otp url
* Dev - Split webpack configuration and enable HMR
* Dev - Update E2E docker image and change image user
* Dev - Update subscriptions-core to 2.3.0.

= 4.8.1 - 2022-10-04 =
* Fix - Fix fatal error thrown during the renewal order payment flow when the store doesn't have the WCPay Subscriptions feature enabled

= 4.8.0 - 2022-09-29 =
* Add - Add bundle size check for PR's.
* Add - Allow subscription processing via WooPay.
* Add - Auto redirect logged in platform checkout users.
* Add - Remove deprecated beta headers from Stripe requests.
* Add - Send a few extra pieces of data when checking if a WooPay user exists.
* Add - StripeLink - prefill first and last names on checkout.
* Add - Timezone formatting for transaction filters.
* Fix - Ask for login when guest mode is disabled while checking out with WooPay.
* Fix - Change 'zero-cost-fee' to 'empty-order', for product_code while sending level 3 data for shipping-only orders, without products.
* Fix - Correct empty email error when StripeLink is active on checkout page.
* Fix - Fix off by one error.
* Fix - Fix the rate calculation when using Table Rate Shipping and per item or per line item calculation type.
* Fix - Fix trial subscription checkout without WooPay signing up.
* Fix - Hide button below email field at checkout, when StripeLink is disabled.
* Fix - Hide Link payment method for non-us accounts.
* Fix - Limit level 3 product code within 12 digits.
* Fix - Prevent circumstance where WCPay Subscriptions customers could be double charged when the WC Subscriptions extension is active.
* Fix - The feature flag for the task list in Payments > Overview page was not passed correctly. We now see the business details and reconnect wpcom user task when appropriate.
* Update - Add timezone formatting only in case client provides user timezone.
* Dev - Bump minimum required version of WooCommerce from 6.4 to 6.6.
* Dev - Removes gutenberg plugin installation from E2E environment setup.
* Dev - Update node to v16 and npm to v8.
* Dev - Update to add E2E tests for the Multi-Currency functionality.

= 4.7.2 - 2022-09-15 =
* Fix - Fixes Order ID appearing as N/A in Payments > Transactions

= 4.7.1 - 2022-09-13 =
* Fix - Fix Apple Pay domain verify file missing error notice constantly displayed
* Fix - Retain test mode context in CRON jobs queued up while checking out.

= 4.7.0 - 2022-09-07 =
* Add - Added meta to payment tokens used in subscriptions.
* Add - Adding an authorization page part of the transactions view. Currently behind a flag and using mocked data.
* Add - Adding support for WCA's Analytics and Multi-Currency when using custom order tables.
* Add - Add support for getting a Stripe invoice.
* Add - indicate setup-intent use in the request.
* Add - Merchants can change their deposit schedule via the settings page.
* Fix - Actualize FAQ link for 'Set up refund policy' inbox note.
* Fix - Add customer ID to WP user during Store API checkout.
* Fix - Add handling for guest user while updating customer with order data.
* Fix - Analytics: Ensure the store default currency always displays in list.
* Fix - Create WooPay user from trial subscription.
* Fix - Dismissible country error message for Apple Pay.
* Fix - Fix - Fatal Error caused in rare cases where quantity is zero during renewal, builds upon fix released in WC Pay 4.3.0.
* Fix - Fix adding payment tokens for platform-created setup intents.
* Fix - Fix deprecation notice for Automattic\WooCommerce\Blocks\StoreApi\RoutesController.
* Fix - Fix error in updating subscription when saved cards are more than posts per page setting.
* Fix - Fix file permission and merchant country errors for apple pay registration.
* Fix - Fix Link errors after blocks plugin update.
* Fix - Improvements to express checkout functionality: prevent errors on PHP 8 with empty product prices, and more percise taxes.
* Fix - Remove duplication of deposit schedule on overview page.
* Fix - Update id card to available payment method after disabling UPE.
* Fix - Update WooCommerce Payments business details via "Things to do" task list leading to a blank page.
* Fix - Upon losing a dispute, orders will no longer appear as processing, but as refunded instead.
* Update - Modified query to get customer currencies when COT enabled.
* Update - Modified usage tracking queries when COT enabled.
* Update - Move the "Instant deposit" button on the Payments > Overview screen to the "Available balance" block.
* Update - Only store a new token when paying for a subscription via WooPay if it doesn't exist already.
* Update - Replaced direct DB query in oorders_with_charge_id_from_charge_ids with wc_get_orders.
* Update - Replaced direct DB query in order_id_from_meta_key_value with wc_get_orders.
* Update - The Payments > Overview "Temporarily Suspended" notice will only appear when deposits are "blocked".
* Dev - Add new E2E workflow for pull requests & split existing tests into 3 jobs.
* Dev - Bump minimum required version of WooCommerce from 6.2 to 6.4.
* Dev - Bump minimum required version of WooCommerce in GH compatibility workflow from 6.2.2 to 6.4.1.
* Dev - Minor readability change in tests.
* Dev - Update E2E flows for subscription tests.
* Dev - Update php-stubs/woocommerce-stubs to 6.8.0.
* Dev - Update subscriptions-core to 2.2.1.
* Dev - Update WC and Gutenberg versions in GH's oldest compatibility test.
* Dev - Upgraded NodeJS version to 14.

= 4.6.0 - 2022-08-18 =
* Add - Adding support for payment request buttons (Apple Pay and Google Pay) to the Pay for Order page.
* Add - Add transactions channel (In-Person or Online).
* Add - Pass a parameter when creating an intention when the request comes from the platform checkout and it has a subscription.
* Fix - Ask for login when buying a subscription with WooPay.
* Fix - Avoid saving a session cookie when the currency is changed because of geolocation.
* Fix - Check payment method before updating payment method title.
* Fix - Fatal error when activating WooCommerce Subscriptions via WP-CLI when WooCommerce Payments is active.
* Fix - Fix an issue while loading the Transaction Detail Page with py_ charge ids.
* Fix - Fix compatibility issues with the new WooCommerce Blocks.
* Fix - Fix error when changing subscription payment method via UPE checkout more than once in a session.
* Fix - Not focusing email field when showing error message to prevent the autocompletion box from covering the error message.
* Fix - Update currencies modal height.
* Update - Make updating existing customer details during checkout async.
* Update - Remove Charge request from Transactions Details page. The Charge data will be retrieved from the Payment Intent request.
* Update - Update public WooPay link in registration copy.
* Dev - Avoid execution context errors during E2E tests.
* Dev - Bump minimum required version of WooCommerce from 6.0 to 6.2.
* Dev - E2E GitHub Workflow: Re-run Failed Test Files.
* Dev - Fixes E2E dispute test flow.
* Dev - Force jest to use en_US.UTF-8 LANG.

= 4.5.1 - 2022-08-08 =
* Update - Security update.

= 4.5.0 - 2022-07-27 =
* Add - Add "Things to do" task list to the Payments Overview screen
* Add - Add a task to the WooCommerce > Home screen notifying merchants of disputed payments that need a response.
* Add - Add E2E test to measure checkout page performance
* Add - Add redirect from charge ID to the payment intent ID equivalent in the transactions detail screen
* Add - Adds support for filtering by customer currency in order analytics section
* Add - Add support for filtering by multiple customer currencies in analytics
* Add - Customer currency filter added to transactions page.
* Add - Multi-Currency compatibility with Points & Rewards plugin.
* Fix - Correctly show UPE payment methods when UPE is first enabled while manual capture is already enabled
* Fix - Exclude blocks tests against incompatible WC versions + exclude specific WC versions for WP nightly tests
* Fix - Fix a grammatical issue in the dispute task on the Payments > Overview screen when there is more than 1 dispute which needs a response.
* Fix - Fix an issue with sorting by customer currency in Analytics > Orders
* Fix - Fix caching issues after accepting a dispute. Resolves issues where the number of disputes needing a response doesn't update after accepting a dispute.
* Fix - Fixed missing intent metadata in order
* Fix - Fix for an issue where a console error relating to wcSettings displayed on WooCommerce > Settings page.
* Fix - Shipping tax conversion while using Multicurrency.
* Fix - Show the correct number of disputes needing a response in the Payments > Overview task list.
* Fix - Show WooPay error message.
* Update - Align Pricing display on Apple Pay/ Google Pay pop-ups with Cart
* Update - Make adding fee breakdown to order notes async.
* Update - Make updating saved payment method async.
* Update - Move the “Things to do” task list to a more visible position on the Payments Overview screen.
* Update - Redirect users to the disputes screen filtered to disputes which need a response when clicking on the Payments > Overview dispute task.
* Update - Skip explicit currency format in admin area when no additional currencies are enabled, matching current fronted behaviour.
* Update - Update transaction details link to use Payment Intent ID instead of Charge ID
* Dev - Bump minimum required version of WooCommerce from 5.8 to 6.0 and WordPress from 5.7 to 5.8.
* Dev - Included prelease version of WordPress into E2E tests
* Dev - Tweak TypeScript definitions for Card readers as suggested on GitHub.
* Dev - Use country-phone input component for terminal settings phone field

= 4.4.0 - 2022-07-06 =
* Add - Add handler for authenticated server links
* Add - Add platform checkout order status sync webhooks
* Add - Display a badge indicating the number of disputes which need a response in Payments > Disputes
* Add - Disputes page: add a new filter option to the Show dropdown for displaying disputes awaiting a response.
* Add - In Person Payments: Extend terminal intent creation to support payment_method_types, metadata, customer and capture_method parameters.
* Add - Introduce StripeLink into WooCommerce blocks
* Add - Support remote inbox notes with relative admin URLs
* Fix - Fix payment methods in account after enabling Stripe Link
* Fix - Hide Platform Checkout iframe on browser back button.
* Fix - Platform Checkout settings responsiveness.
* Fix - Use high-level order currency API for multicurrency subscription renewal orders (get_post_meta is not recommended for orders).
* Update - Bump minimum required version of WooCommerce from 5.6 to 5.8.
* Update - disable loader so that Stripe's skeleton loader is not used.
* Update - Refactor WC_Payments_API_Intention to receive an instance of WC_Payments_API_Charge instead of multiple charge-related fields.
* Dev - Include the WCPay version in the requests to the Platform Checkout
* Dev - Update selectors & flow for dispute related tests

= 4.3.0 - 2022-06-15 =
* Add - Add ARN (Acquirer Reference Number) to refunds in payment details timeline.
* Add - Add support for custom order numbers in addition to order IDs.
* Add - record wcpay version in Platform Checkout Tracks events
* Fix - Billing emails containing spaces.
* Fix - Copy payment from a subscription to its renewal order when retrying failed renewal payment.
* Fix - Dates presented in the "Respond by" column on the Payments → Disputes page are displayed in local time rather than UTC time.
* Fix - Fatal Error caused in rare cases where a subscription line item's quantity is zero during renewal.
* Fix - Fix default terminal location creation for store when blog name is empty.
* Fix - Make hardcoded string in the checkout page translatable
* Fix - Pass capture method preference to platform store
* Fix - Preventing duplicate order notes and emails by clearing the cache before checking order status.
* Fix - Verify domain with Apple Pay on websites using alternate folder structure.
* Update - Add a new flag to conditionally display the Card Readers page when account has connected card readers.
* Update - Bump minimum required version of WooCommerce from 5.4 to 5.6.
* Update - Prevent expensive JOIN queries in Multi-Currency analytics if the store has never used Multi-Currency.
* Dev - Add developer document for "Version Support Policy"
* Dev - Update subscriptions-core to 2.1.0.

= 4.2.1 - 2022-06-06 =
* Fix - Add check to prevent fatal errors on checkout
* Fix - Fix refunding of orders without _payment_method_id
* Fix - Fix subscription renewal prices purchased in zero decimal based currencies like Yen

= 4.2.0 - 2022-05-26 =
* Add - Add a confirmation modal when enabling manual capture, and update UPE methods appearance if manual capture is enabled
* Add - Fee details to order notes for successful payments.
* Add - Introduced wcpay_test_mode filter to manipulate gateway test mode status
* Add - Show WooPay Specific info on success page when customer paid with WooPay
* Fix - Added support for new account status
* Fix - Allow merchant to set store logo on Platform Checkout settings
* Fix - Change type parameter with transaction_type for transactions url
* Fix - Do not show country code on Platform Checkout opt-in.
* Fix - Fixes fatal error on payment intent succeeded webhook.
* Fix - Fix invalid_request_error when creating a payment with a negative unit_cost in level3 data
* Fix - Fix store api url used by platform checkout to work on different permalink preferences
* Fix - Fix the subscriptions onboarding modal and toast on newer WooCommerce versions (6.5.0+).
* Fix - Pass store API mode to platform checkout session and endpoints.
* Fix - Prevent fatal errors when fetching payment methods on the checkout block
* Fix - Prevent sending empty values for required support email and phone fields.
* Fix - Register draft order status hooks to stores with platform checkout enabled.
* Fix - Update platform URL to pay.woo.com
* Update - Bump minimum required version of WooCommerce from 5.2 to 5.4.
* Update - E2E environment setup & workflow optimizations
* Update - Enhance UPE survey.
* Update - Modify the pointer content on the "Add new product" page when WooCommerce Subscriptions is not active.
* Update - Refactor functions regarding timeline captured events for testing.
* Update - Update KYC reminder email Tracks properties
* Update - Update payment gateway method description
* Update - Update session init request to platform checkout to use Jetpack Connection.
* Dev - Deprecate the WC_Subscriptions_Order::get_meta() function. Use wcs_get_objects_property( $order, $meta_key, "single", $default ) instead.
* Dev - In subscriptions-core source files, replace all cases of update_post_meta() where an Order ID is passed to use WC_Order::update_meta_data() instead.
* Dev - In subscriptions-core source files, replace code using get_post_type( $order_id ) with WC Data Store get_order_type().
* Dev - In subscriptions-core source files, replace the get_post_meta() calls in WCS_Post_Meta_Cache_Manager with WC_Order::get_meta().
* Dev - Retrieving user subscription orders has been updated to use the WooCommerce specific APIs in WC_Subscriptions_Order.
* Dev - Start using dart-sass for sass compilation by upgrading @wordpress/scripts package to 12.6.0
* Dev - Update subscriptions-core to 2.0.0.
* Dev - Update the wcs_get_objects_property() function to prevent calls to get_post_meta() on objects that support calling the get_meta() function.

= 4.1.0 - 2022-05-05 =
* Add - Add documents and VAT invoices feature for supported countries.
* Add - Adding StripeLink logo in the transactions list
* Add - Add more logging info when sending requests to WooCommerce Payments server.
* Add - Add StripeLink payment method in WCPay
* Add - Moving email field on checkout page when StripeLink enabled.
* Add - Send the blog locale to the server to enable server-side translations.
* Fix - Ensure platform checkout SMS OTP iframe modal is always visible.
* Fix - Fix an error in refunding In-Person Payments
* Fix - Fix compatibility tests with Woo core 6.5.
* Fix - Fixed clearing of stored payment methods when account is updated via webhook
* Fix - Fixed issue with order tracking when mode is changed
* Fix - Fixed redirect URL when user is already onboarded
* Fix - Fix platform checkout eligibility check through ajax requests
* Fix - Fix UPE alignment issues on classic checkout
* Fix - Generate and add styles to UPE payment form on Blocks checkout
* Fix - New KYC flow treatment mode issus with API calls and settings menu.
* Fix - Prevent Stripe from sending email receipts for terminal payments
* Fix - protect usage of account status to not break Payments > Overview page when account data is not defined
* Fix - Replace enable toggle with account eligibility flag
* Fix - Send receipt for Interac payments.
* Fix - update _charge_id metadata to fix Refund button
* Update - Card testing: rework card errors handling.
* Update - Remove Stripe specific branding options from the card readers settings page.
* Dev - Optimize E2E Setup to install Action Scheduler & WC Blocks from WordPress.org
* Dev - Remove merchant onboarding E2E tests along with dependency.
* Dev - Update currency rate cache mechanism
* Dev - Updated documentation for REST-endpoints.
* Dev - Update GitHub Actions E2E workflow to skip running WC Blocks tests against incompatible WooCommerce versions.

= 4.0.2 - 2022-04-27 =
* Add - Adds user's email to platform checkout request data.
* Fix - Fixed non-working emails customization setting page caused by WCPay.
* Fix - Fix missing customer_id on platform checkout
* Fix - Inject Payments API into Blocks Package to remove dependency on the Blocks plugin for the platform checkout.

= 4.0.1 - 2022-04-20 =
* Fix - Fix templates folder missing in release package

= 4.0.0 - 2022-04-13 =
* Add - Add card readers business URL field browser validation.
* Add - Add data fingerprinting for card testing prevention part of intent.
* Add - Added handling for refund failure in payment timeline.
* Add - Add fraud prevention section in the settings page, behind a feature flag.
* Add - Add new merchant onboarding flow experiment to WCPay.
* Add - Adds option to delete WC Refunds when WCPay refund fails.
* Add - Add support for larger deposits export via async mail.
* Add - Add support for larger disputes export via async email.
* Add - Allow filtering API request params.
* Add - Enable card readers section of WCPay admin area.
* Add - Enable platform checkout if only no subscription product in cart.
* Add - Force checkout refresh on fraudulent payment.
* Add - In-Person Payments: Custom email for payment receipt.
* Add - New connect account page design experiment.
* Add - Record the event wcpay_kyc_reminder_merchant_returned in Tracks when visiting the URL for redirecting to Stripe.
* Add - Send CVC confirmation to server for fraud prevention.
* Fix - Add error notices when transactions cannot be retrieved.
* Fix - Additional checks for domain verification file operations to prevent throwing Warnings on hosts that do not allow for suppression with `@`.
* Fix - Card Readers: Preview receipt functionality.
* Fix - Certain subscription renewal effects not executing for Subscriptions with WooCommerce Payments.
* Fix - Empty file input to allow the user to select the same file again if there's an error.
* Fix - Enable card readers branding section.
* Fix - Enable WooCommerce Blocks checkout to hide option to save payment methods for a non-reusable payment method.
* Fix - Using any other payment methods apart from WooCommerce Payments in "Pay for order" form triggers validation errors when UPE checkout is enabled.
* Fix - Fix an error in refunding In-Person Payments.
* Fix - Fixed the pricing displayed on Google Pay/ Apple Pay preview for variable subscription products.
* Fix - Fix placeholders not being injected into the New Receipt email.
* Fix - Fix printed receipt preview in Card Readers page not working on Firefox browser.
* Fix - Fix saving UPE payment methods with WooCommerce Blocks checkout when a non-reusable payment method is enabled.
* Fix - In-Person Payments: receipts are missing fees and shipping.
* Fix - Prevent platform checkout iframe appear when go back.
* Fix - Prevent refunding Interact payments (managed by Mobile apps).
* Fix - Prevent Stripe from sending email receipts for terminal payments.
* Fix - Style tweaks on checkout page for platform related elements.
* Fix - Switch to global functions to remove deprecation warnings originating from WooCommerce Blocks.
* Fix - Updates refund order note with reason format to fix failing E2E tests.
* Update - Bump minimum required version of WooCommerce from 5.0 to 5.2.
* Update - Enable platform-checkout tracking in stores without Jetpack plugin.
* Update - Exclude Level3 data when capturing a terminal payment.
* Update - Prevent webhook duplicate actions.
* Update - Update Jetpack IDC package, and add two new options dynamicSiteUrlText and dynamicSiteUrlSupportLink.
* Update - WooPay string and styling updates.
* Dev - Account data caching improvements.
* Dev - Deprecate the create customer endpoint.
* Dev - Fixed bash warning when running tests locally.
* Dev - Fix linter warnings in DepositsList.
* Dev - Further migration of JavaScript components to TypeScript.
* Dev - Refactor the KYC Optimisation to use the new Database_Cache class.
* Dev - Skip e2e tests if WC version is 5.0.0 because of WooCommerce Checkout Blocks minimum WC Required version.

= 3.9.3 - 2022-04-05 =
* Fix - Payment Request Button - Do not set WC session cookie and HTML container on the product page if not enabled.

= 3.9.2 - 2022-04-01 =
* Fix - Fixing error related to some currencies

= 3.9.1 - 2022-03-29 =
* Fix - Fix single currency settings page error.

= 3.9.0 - 2022-03-24 =
* Add - Add compatibility between Multi-Currency and WooCommerce Name Your Price.
* Add - Add wcpay_is_wcpay_subscriptions_enabled filter
* Add - Add Webhook_Reliability service to fetch and process failed webhook events.
* Add - Add `wcpay_metadata_from_order` filter which allows for injecting of arbitrary metadata and/or overriding of the `payment_context`
* Add - Allow handling of previously paid for payment intents via the Checkout Store API
* Add - Allow saving credit cards to platform account from classic checkout.
* Add - Implement Tracks events to capture OTP usage
* Add - New filter introduced: 'wc_payments_account_id_for_intent_confirmation'.
* Add - Redirect merchants to the onboarding flow when a URL parameter is present
* Add - Tracking for checkout start and completion
* Add - Tracking for when platform checkout is offered
* Fix - Add - when order data has no billing last name (ex. Google Pay payment)
* Fix - Add all subscription line items not just subscription products when creating WCPay Subscriptions.
* Fix - Check for variables before using them to prevent possible errors during UPE checkout process.
* Fix - Don't anonymize new subscriptions related to old subscriptions via a resubscribe relationship
* Fix - Do not show multi-currency inbox note until the merchant has set up a WCPay account
* Fix - Fix bug when woocommerce_order_actions is called by a plugin or custom code.
* Fix - Fix checkout as guest on platform checkout
* Fix - Fixed failed order getting updated on successful payment in UPE
* Fix - Fix Printed receipt endpoint throws error when called from Jetpack API
* Fix - Fix the conversion from amount in the transactions list.
* Fix - Fix the number formatting issues in Capital loans page loans list
* Fix - Fix `is_platform_payment_method` flag on blocks checkout
* Fix - Improve visibility of checkout fields for WCPay payment options on a darker theme
* Fix - Load currencies from WooCommerce core.
* Fix - Remove account business URL validation to allow values without http/s:// prefix.
* Fix - Sets up subscriptions integration with the Mini Cart Block and adds new hook to filter compatible blocks.
* Fix - Show currencies based on store country currency
* Fix - Subscription token notices that appear on the My account > Payment methods page should be translatable.
* Fix - update the menu count html tag for wp.com
* Fix - When there is only one Shipping Method available in the recurring shipping package, make sure that this method is treated as selected in the current session and the `woocommerce_after_shipping_rate` action runs.
* Update - Bump minimum required version of WooCommerce from 4.8 to 5.0.
* Update - Fixes fee display that caused confusion for Merchants
* Update - Remove feature flagged code that enable platform checkout inside UPE block.
* Update - Update payment methods icons.
* Update - Update transaction csv download emails to be sent to the current logged in admin.
* Dev - Document usage of metadata generated from order
* Dev - Do not enqueue admin assets if user cannot manage_woocommerce.
* Dev - Refactor the processing part of Webhook Controller to a separate service.
* Dev - Remove type "Tweak" from the list of changelog types.
* Dev - REST API documentation
* Dev - Skip e2e tests if WC version is 5.0.0 because of WooCommerce Checkout Blocks minimum WC Required version
* Dev - Unit test support for PHP 8 and upgrade PHPUnit version to 9.5.14
* Dev - Updated contribution notes (how to add a changelog)

= 3.8.2 - 2022-03-03 =
* Fix - Fix fatal error when a subscription renews automatically.

= 3.8.1 - 2022-03-03 =
* Fix - Fix JavaScript error in blocks checkout and Customizer.

= 3.8.0 - 2022-03-02 =
* Add - Add a preview of uploaded logo and icon on terminal card reader receipt settings page.
* Add - Add endpoint to retrieve a file content via API.
* Add - Add jetpack-sync package to support Woo Mobile.
* Add - Add jetpack-tracking module to track platform-checkout events.
* Add - Add logic and UI to accept and see information about business loans, powered by Stripe Capital.
* Add - Add merchant branding logo to print receipt.
* Add - Add Preview printed version of IPP receipt to Card Readers settings page.
* Add - Introduce `wcpay_payment_request_payment_method_title_suffix` filter. Allows plugins to replace "(WooCommerce Payments)" suffix on title of payment request buttons.
* Fix - Conditionally add subscription payment gateway features.
* Fix - Fix email download for deposit transactions.
* Fix - Fix Stripe Level 3 data API limit when basket size is more than 200 items.
* Fix - Styling issue in the subscription product publish modal.
* Update - Add tracking for enabling and disabling platform checkout.
* Update - Bump minimum required version of WooCommerce from 4.6 to 4.8.
* Update - Enable capture terminal payment for succeeded intents.
* Dev - Use Jetpack Changelogger to manage changelog files.
* Tweak - Only add admin notes on non-AJAX requests.
* Tweak - Refactor to add Order Service for updating order statuses.

= 3.7.0 - 2022-02-10 =
* Add - Filter by currency in disputes list
* Add - Link to customer in disputes list
* Update - Bump minimum required version of WordPress from 5.6 to 5.7.
* Update - Bump minimum required version of WooCommerce from 4.5 to 4.6.
* Add - Introduce sorting on disputes page.
* Fix - Currency name not translated the Overview card title.
* Add - Introduce advance filters on disputes page.
* Add - UPE payment methods - BECS Direct Debit.
* Fix - Missing currency field in disputes export file.
* Add - Implement Jetpack Identity Crisis / Safe Mode banner.
* Fix - Checkout with block-based themes.
* Add - UPE payment method - EPS.
* Fix - Replace uses of is_ajax() with wp_doing_ajax() in subscriptions-core.
* Update - Improve handling of session data.
* Fix - When changing the payment method, make sure the subscription total returns $0 when `subscriptions-core` is loaded after the `woocommerce_loaded` action hook.

= 3.6.1 - 2022-01-27 =
* Fix - Remove packages not compatible with PHP 7.0
* Update - Security update.

= 3.6.0 - 2022-01-20 =
* Update - Bump minimum required version of WooCommerce from 4.4 to 4.5.
* Fix - UPE validation error visibility on checkout page.
* Tweak - Load translations for js files directly from lang-pack json files.
* Add - Add support for full transaction exports.
* Fix - Simple subscription elements on the product edit page not shown/hidden when necessary.
* Fix - Prevent fatal errors on the admin subscriptions screen when a subscription fails to load.
* Fix - Compatibility issue when loading subscriptions templates.
* Fix - Flag emoji rendering in currency switcher block widget
* Fix - Error when saved Google Pay payment method does not have billing address name
* Update - Update Payment Element from beta version to release version.
* Fix - Ensure order is always associated with corresponding transaction in UPE Checkout.
* Tweak - Display a more specific error message when a customer attempts to purchase a WCPay Subscription below the minimum transact-able amount.
* Add - Add handling for payment_failed webhooks.
* Add - Disputes pagination.
* Add - Show a warning when attempting to create a subscription product with a price below the minimum amount.
* Fix - When viewing a WCPay Subscription product page, make sure other gateway's express payment buttons aren't shown.
* Fix - When viewing a WC Product page with a WCPay subscription product in cart, make sure other gateway's express payment buttons are shown.
* Fix - Don't limit subscription products being created with an interval of more than one year when the WC Subscriptions plugin is active.
* Fix - Subscriptions not renewing with subscription products that use a free trial period.
* Fix - "Fees" column values are different in the downloaded CSV file for the transaction table

= 3.5.0 - 2021-12-29 =
* Fix - Error when renewing subscriptions with saved payment methods disabled.
* Add - JS error boundaries to admin screens.
* Update - Remove task from the overview list for setting up multiple currencies.
* Update - Return to task "Set up payments" after finishing KYC from WC-Admin.
* Fix - Improve race condition checks to prevent duplicate order status changes.
* Fix - Explicit currency formatting in customer-facing emails.
* Fix - Update tooltip wording when deleting product variation.
* Fix - Remove references to WooCommerce Subscriptions extension in the tooltips found on the Payment Methods settings table.
* Fix - Update the Automatic Recurring Payments column on the Payment Methods table to only show which payment methods are supported by WooCommerce Subscriptions Core.
* Fix - Prevent deprecation warnings when purchasing subscriptions products using WooCommerce Blocks.
* Tweak - Update recurring payments copy on payment gateways page.
* Fix - Incorrect text when filtering subscriptions to no results.
* Changed - Subscription products must have a recurring amount greater than $0.
* Fix - Return correct product prices datatype in WCPay.
* Fix - Stop errors when viewing Subscription details when purchased via SEPA Direct Debit.
* Fix - Force currency check when preparing a payment intent to request even when is_admin() returns true.
* Update - Bump minimum supported version of WooCommerce from 5.5 to 5.8.

= 3.4.0 - 2021-12-08 =
* Add - Allow UI customizations on checkout payment fields.
* Add - Introduce `wcpay_payment_request_is_product_supported` filter. Allow plugins to conditionally disable payment request buttons on products that do not support them.
* Update - Display hardware costs for the period in the transaction list with link to the details page
* Fix - Incorrect customer links on Transactions page.
* Fix - Incorrect prices in Payment Request Button for certain currencies.
* Fix - Updates to fraud protection.
* Add - Add support for suggested gateway methods in WC-Admin.
* Fix - Prevent Payment Request buttons from showing up in Composite Product pages.
* Update - Updated @woocommerce/experimental package to v2.1.0.
* Add - Add support for suggested gateway methods in WC-Admin.
* Add - Onboarding flows on the admin WooCommerce > Subscriptions screen for stores with no subscriptions yet.
* Add - Card Reader receipt settings page.
* Fix - Fatal error on thank you page for deleted orders.
* Add - Error messages when dispute evidence exceeds Stripe limits.
* Add - Export Disputes to CSV
* Update - Remove "Boost your sales by accepting new payment methods" from the overview tasks list.
* Fix - Onboarding must be completed before Subscriptions products can be published.
* Fix - Show the prices in the correct currency when using the "All Products" block.
* Add - Support business account branding settings.
* Update - Capture order-related metadata not captured by mobile app for in-person payment transactions.
* Add - REST endpoint to print IPP receipts.
* Add - Deposit Status to Transaction export.

= 3.3.0 - 2021-11-18 =
* Add - Add Idempotency Key to POST headers.
* Add - Add dispute order notes to Edit Order page.
* Fix - Show a specific message instead of a generic one in the checkout block when non-UPE payment processing fails.
* Update - Avoid having invalid intervals (greater than 1 year) in subscription products.
* Update - The subscription fee label in the transaction timeline.
* Update - Show red setup badge after 3 days instead of 7
* Add - Add compatibility between Multi-Currency and WooCommerce Bookings.
* Add - Add compatibility between Multi-Currency and WooCommerce Pre-Orders.
* Fix - Do not show default currency selector on Account Details page when only one currency is available.
* Add - Add filters to disable or filter Multi-Currency sql query clauses for analytics.
* Fix - Display risk for payment methods without risk assessment
* Fix - Use configured domain instead of current domain for Apple Pay verification.
* Fix - Fatal error when deactivating the WooCommerce plugin when WCPay Subscriptions is enabled.
* Fix - Error where url parameters would get cleared on order-pay page if currency switcher block used.
* Fix - Currency format on order-pay page if currency was changed via switcher.
* Fix - Do not create WooCommerce Payments Subscriptions when using payment methods other than WooCommerce Payments.
* Fix - Show proper payment gateway title on checkout load before updated by JavaScript.
* Fix - Prevent a race condition leading to duplicate order paid statuses transitions.
* Fix - 'payment_intent not found' errors when attempting to process the first invoice for a subscription.
* Fix - UPE element not remounting on checkout update
* Fix - Validate subscription product create and update args before submitting them to server.
* Fix - Improve error messages when the minimum order amount has not been reached and allow fields to be displayed with less than the minimum amount.
* Fix - Add consistent margins to the recurring taxes totals row on the Checkout and Cart block for subscription line items.
* Fix - Fatal error due on subscription orders with no created date in order row template.
* Fix - Fatal error on the customer payment page for subscription renewal orders with deleted products.
* Fix - Misleading subscription order note on payment method change.
* Fix - Incorrect error message when card ZIP validation fails.
* Add - `Requires PHP` and `Requires at least` to the main plugin file.

= 3.2.3 - 2021-11-01 =
* Fix - Card fields on checkout not shown when the 'Enable payments via saved cards' setting is disabled.

= 3.2.2 - 2021-10-29 =
* Fix - Multisite compatibility - don't load subscriptions-core if already loaded by another multisite plugin.
* Fix - Errors when attempting to get the WooCommerce Subscriptions Core version during PayPal requests.

= 3.2.1 - 2021-10-28 =
* Fix - PHP 7.2 compatibility - remove trailing commas from function args in subscriptions-core.

= 3.2.0 - 2021-10-28 =
* Add - Add subscriptions functionality via Stripe Billing and WC Subscriptions core.
* Add - UPE track on upgrade and on setting toggle.
* Fix - Prevent currency switcher to show when enabled currencies list is empty.
* Fix - Show currency switcher notice until customer explicitly dismisses it.
* Update - Switch the PaymentIntent ID and the Charge ID in the order notes and transaction details pages.
* Fix - Track 'wcpay_payment_request_settings_change' for when updating the Payment Requests setting not being recorded.
* Update - Fee breakdown when there's only a base fee
* Fix - Inconsistent shipping options in Payment Request popup.
* Fix - Payment methods checkbox UI looking off when Gutenberg is active.
* Update - Remove unused "wcpay_deposits_summary_empty_state_click" track.
* Fix - Border style not being applied properly on Multi-Currency block widget.
* Fix - Applied sentence case on all strings
* Fix - Missing customer information after guest checkout via Checkout Block.
* Fix - Show correct payment method name during checkout using upe methods.
* Fix - Multi-Currency settings rounding option and preview.
* Fix - Payment failure on checkout block with UPE when phone number field is hidden
* Update - Adds a scheduled action which makes updating the account cache more efficient
* Add - Add compatibility between Multi-Currency and WooCommerce UPS shipping extension.
* Add - Early access: allow your store to collect payments with SEPA Direct Debit. Enable the feature in settings!
* Add - Add compatibility between Multi-Currency and WooCommerce FedEx shipping extension.
* Fix - Fix decimal error with shipping calculations with Multi-Currency.
* Add - Add support for float type values for quantity.
* Fix - Allow payment_intent_succeeded webhook to handle orders without intent_id attached.
* Add - Add compatibility between Multi-Currency and WooCommerce Product Add Ons version 4.3.0 and higher.
* Add - Enable Bancontact UPE method.
* Add - Enable P24 UPE method.
* Add - Enable iDeal UPE method.
* Add - Payment method activation requirements modal and API integration.
* Add - Add state handling for UPE methods for better merchant notification on what methods are able to be used.
* Fix - Order currency incorrect if new user/customer created during checkout.
* Fix - Validation now works when adding a new payment method, or paying for an order.

= 3.1.0 - 2021-10-06 =
* Fix - Issue affecting analytics for Multi-Currency orders made with a zero-decimal to non-zero decimal conversion.
* Add - Customer Multi-Currency onboarding flow.
* Add - Checkbox toggle for disabling customer Multi-Currency feature in Advanced Settings.
* Add - Update layout of the Multi-Currency settings screen.
* Fix - Fixed missing file error for removed CSS file.
* Add - Currency deletion confirmation modal for currencies that are bound to an UPE method.
* Fix - Currency switcher does not affect order confirmation screen prices.
* Fix - Error when attempting to change the payment method for a subscription with UPE enabled.
* Add - Multi-Currency track currency added.
* Fix - Fill missing order_intent_info even if an exception occurs.
* Fix - Authorize and capture payments later with new credit cards.
* Add - Gutenberg Block Widget for Multi-Currency.
* Update - WCPay logo.
* Fix - Translations in transaction/deposit exports
* Fix - Update shipping cost in payment sheet when changing payment method.
* Fix - Transaction search with translated terms.
* Update - Replace REST endpoint for onboarding initialization.
* Fix - UPE missing international card fees.
* Update - Bump minimum supported version of WooCommerce from 5.4 to 5.5.
* Update - Bump minimum required version of WooCommerce from 4.0 to 4.4.
* Fix - Add credit card on My Account using other payment gateways does not show "Your card number is incomplete" error.
* Update - Continue loading WCPay if the account is connected.
* Add - Message to suggest using the previous version of WooCommerce Payments for old Woo core versions.
* Fix - Appearance of upload file buttons inside challenge dispute page.
* Fix - Enable logging for UPE checkout errors.
* Update - Composer package `automattic/jetpack-connection` from v1.20.0 to v1.30.5.

= 3.0.0 - 2021-09-16 =
* Add - Download deposits report in CSV.
* Fix - Use store currency on analytics leaderboard when Multi-Currency is enabled.
* Add - Add API to expose Multi-Currency widget to theme/plugin developers for easy integration.
* Fix - Enabled currencies modal UI.
* Fix - User order currency format on admin refund button.
* Fix - Clear the list of selected currencies after closing the modal for adding currencies.
* Fix - Fix subscription change payment method errors after entering a payment method that fails.
* Fix - Prevent duplicate account onboarding requests.
* Fix - Filter out merchant-facing payment errors from customer error notices.
* Fix - Add primary action to high priority tasks.

= 2.9.1 - 2021-09-07 =
* Fix - Error while checking out with UPE when fields are hidden.
* Fix - Unable to onboard when in treatment mode.

= 2.9.0 - 2021-08-25 =
* Add - Split discount line in timeline into variable fee and fixed fee.
* Add - Order status validation for payments/orders/{order_id}/create_customer API.
* Add - Add country code parameter to ExPlat API requests.
* Add - Add a new hook to get a list of enabled payment request methods.
* Fix - Align table items according to design correctly.
* Fix - Fatal error if wcpay_multi_currency_enabled_currencies is a string.
* Fix - Show the estimated deposit date in the transactions CSV export rather than the deposit ID.
* Fix - Keep track of customer id in non logged in users.
* Update - Bump minimum supported version of WooCommerce from 5.3 to 5.4.

= 2.8.4 - 2021-08-17 =
* Fix - Fix database connection error on account cache clear.
* Fix - Fix fatal error logged when updating analytics data when account is not connected to Stripe.
* Fix - Multi-Currency Compatibility fatal error with Subscriptions when account is not connected to Stripe.

= 2.8.3 - 2021-08-10 =
* Fix - Fix for payment request buttons when the new payment methods gateway is enabled.

= 2.8.2 - 2021-08-05 =
* Fix - If account is disconnected or not set up do not display onboarding task and UPE inbox note.
* Fix - Fix for the site acting as disconnected after the account cache expires.
* Fix - Fix for failed Giropay and Sofort transactions causing an error.

= 2.8.1 - 2021-08-04 =
* Fix - Enable Multi-Currency only if there is a linked WooCommerce Payments account.

= 2.8.0 - 2021-08-04 =
* Add - Allow merchants to add additional currencies to their store, allowing a store’s customers to shop and browse in the currency of their choice.
* Add - *Early access*: allow your store to collect payments with Giropay and Sofort. Enable the feature in settings!
* Add - Use date picker for applicable dispute evidence fields.
* Fix - Avoid crash when seeing the details of an empty deposit.
* Fix - Disabled Payment Request Buttons when order has to be split into multiple packages because Payment Requests do not support that use case.
* Fix - Fee discounts should use the discount currency rather than the base fee currency.
* Fix - Do not redirect to the onboarding page when account retrieval fails.
* Add - Allow the customer to perform SCA authentication on Subscription renewals.
* Update - Actualized supported countries list for onboarding.
* Add - Dispute Status Chip into the header of the Dispute Details page.
* Fix - Use a singular label in the summary of Transactions and Deposits lists.
* Add - Disable payment gateway when not in test mode and not using https or ssl checkout enforcement.
* Fix - Improved errors handling during onboarding and page overview.
* Update - Remove Account in the old Settings page.
* Update - Bump minimum supported version of WooCommerce from 5.2 to 5.3.
* Update - Bump minimum supported version of WordPress from 5.5 to 5.6.
* Fix - Stop refund process when using an invalid amount
* Fix - Improve sanitization of ExPlat cookie.
* Add - Show fee breakdown in transaction details timeline.
* Add - REST endpoint to get customer id from an order.
* Fix - Explat not caching when no variation is returned.

= 2.7.1 - 2021-07-26 =
* Fix - Ensure test mode setting value is correctly saved.
* Fix - Onboarding redirection occasionally not finalizing account connection.

= 2.7.0 - 2021-07-14 =
* Add - Add a link to the snackbar notice that appears after submitting or saving evidence for a dispute challenge.
* Add - Support saving new cards and paying with previously saved cards in the WooCommerce Checkout Block.
* Fix - WooCommerce Payments admin pages redirect to the onboarding page when the WooCommerce Payments account is disconnected.
* Fix - Do not overwrite admin pages when account is disconnected.
* Update - Set a description when creating payment intents.
* Add - Add dispute resolution task.

= 2.6.1 - 2021-07-01 =
* Fix - Updates the notes query filters to prevent breaking the WooCommerce > Home inbox.

= 2.6.0 - 2021-06-23 =
* Add - Notify the admin if WordPress.com user connection is broken.
* Add - Experimental PHP client for Explat.
* Add - WooCommerce Payment inbox notifications to the overview screen.
* Fix - Fix fatal error if store currency is changed after enabled (multi) currencies set.
* Fix - Use of deprecated call-style to registerPaymentMethods. WooCommerce Payments now requires WooCommerce Blocks of at least version 3.9.0.
* Fix - Deposit date on Transactions list page.
* Fix - Rounding error when displaying fee percentages on the Overview and Transactions pages.
* Add - Error message when total size of dispute evidence files uploaded goes over limit.
* Update - Pass currency to wc_price when adding intent notes to orders.
* Update - Instant deposit inbox note wording.
* Fix - Deposit overview details for non instant ones.
* Add - Introduce new settings layout
* Update - Removed "Branded" and "Custom label" options on Payment request buttons to align with design guidelines.
* Update - Converted payment request button size value to distinct options to align with design guidelines.
* Tweak - Run post-upgrade actions during any request instead of only on wp-admin requests.
* Update - Payment request button should guide users to login when necessary.
* Add - When setting WooCommerce Payments up, inform if merchant business country is not supported.
* Update - Bump minimum supported version of WooCommerce from 4.8 to 5.2.
* Add - Introduce advance filters on deposits page.
* Update - Prefill OAuth flow with WC store country.

= 2.5.0 - 2021-06-02 =
* Fix - Fix hover dialog for close button on modals, unify styling and layout of modal buttons.
* Update - Use Site Language when rendering Stripe elements.
* Update - Use blog ID for authenticating most of the requests.
* Fix - Misaligned columns on Deposits page.
* Add - Tracking for returning from OAuth connection.
* Fix - Transactions and deposits counts on the table summary are rendered as "undefined".
* Update - Deposit overview details.
* Add - Redirect to WooCommerce home page after successful WooCommerce Payments KYC (Know Your Customer).
* Fix - Added CSV column heading for transaction id column.
* Update - Bump minimum supported version of WordPress from 5.4 to 5.5.
* Update - Bump minimum supported version of WooCommerce from 4.5 to 4.8.
* Add - Deposit overviews have been added to the overview page.
* Update - Account overview page is now GA and default page for woocommerce payments.
* Update - Base fee and account status has been moved to overview page from WCPay settings.
* Fix - Express payment method being displayed on blocks checkout when Payment Request is not supported.
* Fix - Subscription sign-up fees not included in total for Payment Request Button.

= 2.4.0 - 2021-05-12 =
* Update - Improve the Connect Account page.
* Update - Base UI components and their styling.
* Fix - Deposits overview details not displayed.
* Fix - WooCommerce Payments disappeared from WooCommerce Settings if WooCommerce Subscriptions is activated.
* Add - REST endpoint to capture payments by order ID.
* Add - Explat package for A/B tests.
* Add - Payment request button support for checkout and cart blocks.
* Update - Bump minimum supported WooCommerce version from 4.0 to 4.5.
* Update - Implement expirement on Connect Page.
* Fix - Columns are misaligned on Payments->Transactions/Disputes page.
* Fix - Risk level is displayed as a "Numeric" value in transactions CSV.

= 2.3.3 - 2021-05-06 =
* Update - Additional logic and styling for instant deposits.

= 2.3.2 - 2021-04-27 =
* Fix - Error when purchasing free trial subscriptions.

= 2.3.1 - 2021-04-26 =
* Fix - Various account connection cache tweaks
* Update - Use option instead of transient for caching account data
* Fix - Error when using SCA / 3DS credit card in checkout block.

= 2.3.0 - 2021-04-21 =
* Add - Introduced deposit currency filter for transactions overview page.
* Add - Download transactions report in CSV.
* Update - Tweak the connection detection logic.
* Add - Notification badge next to payments menu.
* Fix - Fixed broken search on transactions list page.
* Add - More helpful message on checkout errors.
* Update - Change the default track `recordEvent` to use @woocommerce/tracks.
* Add - WPCOM connection status event prop to 'wcpay_connect_account_clicked' track.
* Add - Allow users to clear the account cache.
* Update - Bump minimum supported version of WordPress from 5.3 to 5.4.
* Add - Add a new admin note to collect qualitative feedback.
* Add - Introduced deposit currency filter for deposits overview page.
* Update - Make Payment Request Button available for all merchants.
* Add - Configurable Payment Request Button locations.
* Add - Addition of the Instant Deposits feature to allow qualified merchants to manually trigger payouts.

= 2.2.0 - 2021-03-31 =
* Fix - Paying with a saved card for a subscription with a free trial will now correctly save the chosen payment method to the order for future renewals.
* Add - Payment Request Button support for US merchants (Apple Pay, Google Pay, Microsoft Pay, and the browser standard Payment Request API).
* Update - Not passing level3 data for non-US merchants.
* Add - REST endpoint for fetching account data.
* Add - Deposits list pagination and sorting.
* Fix - Deposit overview now displays placeholder information instead of continuing to load when an error happens.

= 2.1.1 - 2021-03-23 =
* Fix - Fatal error when a subscription is processed with action scheduler hook.

= 2.1.0 - 2021-03-16 =
* Update - Show last 4 digit credit card number in order note when payment method is updated on failed renewal subscription order.
* Update - Define constant for the group to be used for scheduled actions.
* Update - Enable multiple customer currencies support in live mode.
* Add - Rate limit failed account connection checks.
* Add - Support displaying non-USD base fees on settings page.

= 2.0.0 - 2021-02-22 =
* Update - Render customer details in transactions list as text instead of link if order missing.
* Update - Render transaction summary on details page for Multi-Currency transactions.
* Update - Improvements to fraud prevention.
* Fix - Added better notices for end users if there are connection errors when making payments.
* Fix - If account is set to manual payouts display 'Temporarily suspended' under Payments > Settings.
* Add - Add file dropzones to dispute evidence upload fields
* Add - Currency conversion indicator to Transactions list.
* Add - Transaction timeline details for Multi-Currency transactions.
* Update - Link order note with transaction details page.
* Fix - Updating payment method using saved payment for WC Subscriptions orders.

= 1.9.2 - 2021-02-05 =
* Fix - Checkout and cart blocks aren't usable in editor when WooCommerce Payments is enabled.
* Fix - Missing global config error in Checkout block integration, and incompatibility with latest block API.

= 1.9.1 - 2021-02-03 =
* Fix - Incompatibility with WC Subscriptions.
* Fix - Missing order causing broken transactions list.

= 1.9.0 - 2021-02-02 =
* Add - Improved fraud prevention.
* Add - New setting to manage whether to enable saving cards during checkout. (Defaults to being enabled).
* Fix - Fixed issue where an empty alert would appear when trying to refund an authorization charge.
* Update - Link customer name on transaction detail page to filtered transaction list page.
* Update - Test mode notice width is now consistent across all pages.
* Fix - Fix error which could occur when a 100% off coupon was applied during checkout.
* Add - New notification to urge setting SSL for checkout pages if store doesn't use HTTPS
* Fix - Fixed connection timeout configuration.
* Fix - Specify error code when refund fails in admin to prevent blank alert.
* Fix - Add fees as line items sent to Stripe to prevent Level 3 errors.
* Fix - Currency format in non-USD order note when capturing, refunding, and processing subscription renewal.
* Update - Link customer name from transaction list page to WooCommerce's Customers page filtered by the customer's name.
* Fix - Use proper currency information when rendering deposits overview and details.

= 1.8.0 - 2020-12-16 =
* Add - Include information about failing payment into order notes.
* Fix - Fix crash when a user has 10 or more saved credit cards.
* Fix - Fix crash if there's a problem connecting to the server.
* Fix - Store Stripe customer for test and live mode.
* Fix - Display Payments menu in the sidebar if there's a problem connecting to the server.
* Add - Display fee structure in transaction timelines.
* Add - Use site username for recording ToS acceptance.
* Update - Display transaction tables with full width.
* Add - Action hooks before and after webhook delivery.

= 1.7.1 - 2020-12-03 =
* Fix - Pass ISO strings instead of Moment objects to dateI18n.

= 1.7.0 - 2020-11-17 =
* Fix - Fix ordering of payment detail timeline events.
* Fix - Payment form hides when saved card is selected.
* Fix - Render dispute evidence file upload errors.
* Fix - Increase timeout for calls to the API server.
* Fix - Correctly display the fee and net amounts for a charge with an inquiry.
* Fix - Catch unhandled exceptions when cancelling a payment authorization.
* Add - Security.md with security and vulnerability reporting guidelines.
* Add - Introduced "Set up refund policy" notification in WooCommerce inbox.
* Fix - Fix error when retrying to save a card in the Add Payment Method screen after failing SCA authentication.
* Add - Allow signing up for a subscription with free trial with a credit card that requires SCA authentication.
* Add - Remote note service.
* Add - Show details about the current fees in the Settings screen.

= 1.6.0 - 2020-10-15 =
* Fix - Trimming the whitespace when updating the bank statement descriptor.
* Add - Initial support for the checkout block.
* Add - Support wp_get_environment_type() and enable dev-mode when environment is 'development' or 'staging'.
* Update - Introduced payments-specific exceptions instead of generic one.
* Update - Transaction timeline: enabled timestamps rendering for all entries.

= 1.5.0 - 2020-09-24 =
* Fix - Save payment method checkbox for Subscriptions customer-initiated payment method updates.
* Fix - Support checkout on Internet Explorer 11.
* Fix - Webhook processing with no Jetpack plugin installed.
* Fix - Do not block the checkout card field from loading when customer meta is invalid or related to an old account.
* Fix - Saving account statement descriptor with an ampersand character.
* Fix - Do not attempt to render the payment timeline if the intention ID is missing.
* Add - Display payment method details on account subscriptions pages.
* Add - Redact sensitive data before logging.
* Add - Support for WooCommerce Subscriptions admin-initiated payment method changes.
* Add - Link to Subscription admin pages from Transactions views.
* Add - Support for Subscriptions in transaction search.

= 1.4.1 - 2020-09-07 =
* Fix - Only redirect to the onboarding screen if the plugin has been individually activated using the plugins page.

= 1.4.0 - 2020-09-02 =
* Add - Initial support for WooCommerce Subscriptions: Signing up for subscriptions, scheduled payments, and customer-initiated payment method changes.
* Add - Added a link to transaction details from order screens.
* Add - Allow merchant to edit statement descriptor.
* Fix - Do not redirect to the onboarding page after completing the WC4.5-beta wizard.
* Fix - Save order metadata before the payment is completed to avoid missing payments.
* Update - Bumped the minimum Jetpack requirement to version 8.2.

= 1.3.0 - 2020-08-17 =
* Add - Support for saved cards.
* Add - Search bar for transactions.
* Fix - Redirect to WC core onboarding instead of WC Payments onboarding when appropriate.
* Fix - Creating an account during checkout with 3DS cards.
* Fix - Missing payment statuses for certain disputes.
* Fix - Missing translators comments.

= 1.2.0 - 2020-07-20 =
* Add - 3DS support when using the pay for order page
* Add - Display confirmation dialog when enabling manual captures
* Add - Update the order when an authorised payment has expired
* Add - Timeline view in transactions detail, requires WooCommerce 4.4
* Add - Tracking for basic user action events
* Fix - Admin UI styling tweaks

= 1.1.0 - 2020-06-16 =
* Add - Allow WooCommerce Payments set up without Jetpack plugin
* Fix - Orders missing after payment processing error
* Fix - Clearing pagination when selecting transactions advanced filters
* Fix - After onboarding, redirect to the WCPay task of the task list, instead of to the task list

= 1.0.1 - 2020-06-01 =
* Add - Support 3D Secure verification
* Add - Transaction date and type filters to transactions list
* Update - Redirect to the "Connect" page on plugin activation or when trying to navigate to the settings screen
* Fix - Add deposit delay notice to deposit schedule
* Fix - Avoid localizing deposit date or displaying time-of-day precision
* Fix - Display dispute currency code in dispute info

= 1.0.0 - 2020-05-19 =
* Add - Level 3 data to payment requests
* Update - Expose public method for checking connection status
* Fix - Pending requirements state for improved messaging
* Fix - Dispute details typo
* Remove - Unused POST /charges endpoint
* Remove - "Beta" messaging

= 0.9.2 - 2020-05-14 =
* Add - Customer ID to payment intent
* Update - Register and enqueue js.stripe.com on WCPay admin pages
* Update - Override needs_setup to redirect from Payments settings
* Update - Copy and image on Connect Account screen
* Add - Add deposits overview component
* Add - URL to pass for prefilling OAuth form
* Add - Test card details in Checkout
* Add - Task list redirect upon return from OAuth flow
* Add - Handling for failed refund and other webhooks
* Add - Transaction list sorting
* Update - Disable gateway when payments are disabled on the account
* Update - Make table rows clickable
* Add - Prompt before navigating away from unsaved dispute evidence changes
* Update - Labels to sentence case
* Update - Automated testing
* Add - Metadata when creating payment intent
* Update - PHP versions supported

= 0.9.1 - 2020-04-09 =
* Fix - Add logging for OAuth initialization failures

= 0.9.0 - 2020-04-08 =
* Add - Release for Public Beta.

= 0.8.2 - 2020-03-10 =
* Add - Dispute file evidence upload support
* Add - Basic support for Pay for Order
* Fix - Styling improvements in wp-admin
* Fix - Undefined variable PHP notice in cancel_authorization
* Fix - Remove unused variable in authentication
* Fix - Improve Jetpack connection checking

= 0.8.1 - 2020-02-25 =
* Update - Link to test card documentation.

= 0.8.0 - 2020-02-24 =
* Add - First beta release.

= 0.7.0 - 2020-02-05 =
* Add - Alpha release.
