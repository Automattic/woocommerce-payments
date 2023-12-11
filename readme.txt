=== WooPayments - Fully Integrated Solution Built and Supported by Woo ===
Contributors: woocommerce, automattic
Tags: woocommerce payments, apple pay, credit card, google pay, payment, payment gateway
Requires at least: 6.0
Tested up to: 6.4
Requires PHP: 7.3
Stable tag: 6.9.1
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Securely accept credit and debit cards on your WooCommerce store. Manage payments without leaving your WordPress dashboard. Only with WooPayments.

== Description ==

**Payments made simple, with no monthly fees – designed exclusively for WooCommerce stores.**

Securely accept major credit and debit cards, and allow customers to pay you directly without leaving your WooCommerce store. View and manage transactions from one convenient place – your WordPress dashboard.

See payments, track cash flow into your bank account, manage refunds, and stay on top of disputes without the hassle of having to log into a separate payment processor.

**Manage transactions from the comfort of your store**

Features previously only available on your payment provider’s website are now part of your store’s **integrated payments dashboard**. This enables you to:

- View the details of [payments, refunds, and other transactions](https://woo.com/document/woopayments/managing-money/).
- View and respond to [disputes and chargebacks](https://woo.com/document/woopayments/fraud-and-disputes/managing-disputes/).
- [Track deposits](https://woo.com/document/woopayments/deposits/) into your bank account or debit card.

**Pay as you go**

WooPayments is **free to install**, with **no setup fees or monthly fees**. Pay-as-you-go fees start at 2.9% + $0.30 per transaction for U.S.-issued cards. [Read more about transaction fees](https://woo.com/document/woopayments/fees-and-debits/fees/).

**Supported by the WooCommerce team**

Our global support team is available to answer questions you may have about WooPayments installation, setup, or use. For assistance, [open a ticket on Woo.com](https://woo.com/my-account/create-a-ticket/?select=5278104).

== Getting Started ==

= Requirements =

* WordPress 6.0 or newer.
* WooCommerce 7.6 or newer.
* PHP 7.3 or newer is recommended.

= Try it now =

To try WooPayments (previously WooCommerce Payments) on your store, simply [install it](https://wordpress.org/plugins/woocommerce-payments/#installation) and follow the prompts. Please see our [Startup Guide](https://woo.com/document/woopayments/startup-guide/) for a full walkthrough of the process.

WooPayments has experimental support for the Checkout block from [WooCommerce Blocks](https://wordpress.org/plugins/woo-gutenberg-products-block/). Please check the [FAQ section](#faq) for more information.

== Installation ==

Install and activate the WooCommerce and WooPayments plugins, if you haven't already done so, then go to "Payments" in the WordPress admin menu and follow the instructions there.

== Frequently Asked Questions ==

= What countries and currencies are supported? =

If you are an individual or business based in [one of these countries](https://woo.com/document/woopayments/compatibility/countries/#supported-countries), you can sign-up with WooPayments. After completing sign up, you can accept payments from customers anywhere in the world.

We are actively planning to expand into additional countries based on your interest. Let us know where you would like to [see WooPayments launch next](https://woo.com/payments/#request-invite).

= Why is a WordPress.com account and connection required? =

WooPayments uses the WordPress.com connection to authenticate each request, connecting your store with our payments partner.

= How do I set up a store for a client? =

If you are a developer or agency setting up a site for a client, please see [this page](https://woo.com/document/woopayments/account-management/developer-or-agency-setup/) of our documentation for some tips on how to install WooPayments on client sites.

= How is WooPayments related to Stripe? =

WooPayments is built in partnership with Stripe [Stripe](https://stripe.com/). When you sign up for WooPayments, your personal and business information is verified with Stripe and stored in an account connected to the WooPayments service. This account is then used in the background for managing your business account information and activity via WooPayments. [Learn more](https://woo.com/document/woopayments/account-management/partnership-with-stripe/).

= Are there Terms of Service and data usage policies? =

You can read our Terms of Service and other policies [here](https://woo.com/document/woopayments/our-policies/).

= How does the Checkout block work? =

You need the [WooCommerce Blocks](https://wordpress.org/plugins/woo-gutenberg-products-block/) plugin to be installed and active to use the Checkout block. Once you add the block to a page, WooPayments will automatically appear as an option.

Please note that our support for the checkout block is still experimental and the following features of the gateway will probably not work:

* Using saved cards and saving cards.
* Integration with WooCommerce Subscriptions.

== Screenshots ==

1. View Transactions
2. View Transaction Details
3. Track Deposits
4. Manage Disputes

== Changelog ==

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
