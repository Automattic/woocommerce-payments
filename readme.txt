=== WooCommerce Payments ===
Contributors: woocommerce, automattic
Tags: woocommerce, payment, payment request, credit card, automattic
Requires at least: 5.3
Tested up to: 5.4
Requires PHP: 7.0
Stable tag: 1.5.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Securely accept credit and debit cards on your site. Manage transactions without leaving your WordPress dashboard. Only with WooCommerce Payments.

== Description ==

**Payments made simple, with no monthly fees – designed exclusively for WooCommerce stores.**

Securely accept major credit and debit cards, and allow customers to pay you directly without leaving your WooCommerce store. View and manage transactions from one convenient place – your WordPress dashboard.

See payments, track cash flow into your bank account, manage refunds, and stay on top of disputes without the hassle of having to log into a separate payment processor.

**Manage transactions from the comfort of your store**

Features previously only available on your payment provider’s website are now part of your store’s **integrated payments dashboard**. This enables you to:

- View the details of [payments, refunds, and other transactions](https://docs.woocommerce.com/document/payments/#section-4).
- View and respond to [disputes and chargebacks](https://docs.woocommerce.com/document/payments/disputes/).
- [Track deposits](https://docs.woocommerce.com/document/payments/#section-5) into your bank account or debit card.

**Pay as you go**

WooCommerce Payments is **free to install**, with **no setup fees or monthly fees**. Pay-as-you-go fees start at 2.9% + $0.30 per transaction for U.S.-issued cards. [Read more about transaction fees](https://docs.woocommerce.com/document/payments/faq/fees/).

**Supported by the WooCommerce team**

Our global support team is available to answer questions you may have about WooCommerce Payments installation, setup, or use. For assistance, [open a ticket on WooCommerce.com](https://woocommerce.com/my-account/create-a-ticket/?select=5278104).

== Getting Started ==

= Requirements =

* United States-based business.
* WordPress 5.3 or newer.
* WooCommerce 4.0 or newer.
* PHP version 7.0 or newer. PHP 7.2 or newer is recommended.

= Try it now =

To try WooCommerce Payments on your store, simply [install it](https://wordpress.org/plugins/woocommerce-payments/#installation) and follow the prompts.

== Installation ==

Install and activate the WooCommerce and WooCommerce Payments plugins, if you haven't already done so, then go to "Payments" in the WordPress admin menu and follow the instructions there.

== Frequently Asked Questions ==

= What countries and currencies are supported? =

If you are an individual or business based in the United States, you can sign-up with WooCommerce Payments. After completing sign up, you can accept payments from customers anywhere in the world in USD.

We are actively planning to expand into additional countries based on your interest. Let us know where you would like to [see WooCommerce Payments launch next](https://woocommerce.com/payments/#request-invite).

[Learn more](https://docs.woocommerce.com/document/payments/countries/).

= Why is a WordPress.com account and connection required? =

WooCommerce Payments uses the WordPress.com connection to authenticate each request, connecting your store with our payments partner.

= How do I set up a store for a client? =

If you are setting up a store that will process real payments, have the site owner complete the WooCommerce Payments setup. This ensures that the correct business details are set on the account during [onboarding](https://docs.woocommerce.com/document/payments/#section-3).

After the store setup has been completed, you can use [Test Mode](https://docs.woocommerce.com/document/payments/testing/) to simulate payments, refunds, and disputes.

If you are setting up WooCommerce Payments on a development or test site that will **never need to process real payments**, try [Dev Mode](https://docs.woocommerce.com/document/payments/testing/dev-mode/#section-1).

= How is WooCommerce Payments related to Stripe? =

WooCommerce Payments is proudly powered by [Stripe](https://stripe.com/). When you sign up for WooCommerce Payments, your personal and business information is verified with Stripe and stored in an account connected to the WooCommerce Payments service. This account is then used in the background for managing your business account information and activity via WooCommerce Payments. [Learn more](https://docs.woocommerce.com/document/payments/powered-by-stripe/).

= Are there Terms of Service and data usage policies? =

You can read our Terms of Service [here](https://en.wordpress.com/tos).

== Screenshots ==

1. View Transactions
2. View Transaction Details
3. Track Deposits
4. Manage Disputes

== Changelog ==

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
* Release for Public Beta

= 0.8.2 - 2020-03-10 =
* Add - Dispute file evidence upload support
* Add - Basic support for Pay for Order
* Fix - Styling improvements in wp-admin
* Fix - Undefined variable PHP notice in cancel_authorization
* Fix - Remove unused variable in authentication
* Fix - Improve Jetpack connection checking

= 0.8.1 - 2020-02-25 =
* Update link to test card documentation

= 0.8.0 - 2020-02-24 =
* First beta release

= 0.7.0 - 2020-02-05 =
* Alpha release
