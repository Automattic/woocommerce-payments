=== WooCommerce Payments ===
Contributors: automattic
Tags: woocommerce, payment, payment request, credit card, automattic
Requires at least: 5.0
Tested up to: 5.4
Requires PHP: 7.0
Stable tag: 0.9.2
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Securely accept credit and debit cards on your site. Manage transactions without leaving your WordPress dashboard. Only with WooCommerce Payments

== Description ==

Securely accept major credit and debit cards on your WooCommerce site. View and manage your transactions without leaving your WordPress dashboard.

The only payments plugin designed exclusively for WooCommerce, by WooCommerce.

Currently in public beta.

**See and manage all transactions in your WooCommerce Payments integrated dashboard**

Features normally only available at your payment provider's website are now part of your site's dashboard.

- View the details of [payments, refunds and other transactions](https://docs.woocommerce.com/document/payments/#section-4)
- View and respond to [disputes / chargebacks](https://docs.woocommerce.com/document/payments/disputes/)
- [View deposits](https://docs.woocommerce.com/document/payments/#section-5) from your account balance to your bank account or debit card

**Accept all major credit and debit cards**

Increase conversion rates by securely accepting all major credit and debit cards directly on your site.

**Pay as you go**

No set up fees. No monthly fees. Just 2.9% + $0.30 per transaction on U.S.-issued cards. See all the [fees](https://docs.woocommerce.com/document/payments/faq/fees/).

== Getting Started ==

= Requirements =

* United States-based business.
* WordPress 5.3 or newer.
* WooCommerce 3.9 or newer.
* WooCommerce Admin 1.0 or newer (if running a WooCommerce version less than 4.0)
* [Jetpack](http://wordpress.org/plugins/jetpack) 5.3 or newer.
* PHP version 7.0 or newer. PHP 7.2 or newer is recommended.

== Installation ==

Install and activate the WooCommerce and Jetpack plugins, if you haven't already done so, and connect your site to WordPress.com

1. Log in to your WordPress dashboard.
1. Go to: Plugins > Add New.
1. Enter "WooCommerce Payments" in the Search field.
1. Click "Install Now".
1. Go to: Payments
1. Create your WooCommerce Payments account.

== Frequently Asked Questions ==

= What countries and currencies are supported? =

If you are an individual or business based in the United States, you can sign-up with WooCommerce Payments. After completing sign up, you can accept payments from customers anywhere in the world in USD. [Learn more](https://docs.woocommerce.com/document/payments/countries/).

= This works with WooCommerce, right? =

Yep! WooCommerce version 3.9 or newer, please.

= Why is a WordPress.com account and connection required? =

WooCommerce Payments uses the WordPress.com connection to authenticate each request, connecting your store with our payments partner.

= How is WooCommerce Payments related to Stripe? =

WooCommerce Payments is proudly powered by [Stripe](https://stripe.com/). When you sign up for WooCommerce Payments, your personal and business information is verified with Stripe and stored in an account connected to the WooCommerce Payments service. This account is then used in the background for managing your business account information and activity via WooCommerce Payments. [Learn more](https://docs.woocommerce.com/document/payments/powered-by-stripe/).

= Are there Terms of Service and data usage policies? =

You can read our Terms of Service [here](https://en.wordpress.com/tos).

== Screenshots ==

== Changelog ==

= 0.9.2 - 2020-xx-xx =
* Fix - Remove unused variable
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
