# Introduction

WooPayments REST APIs build upon the WooCommerce REST API, which extends the
WordPress REST API. They use the same authentication and request methods.

For requirements and other general details, refer to the [WooCommerce REST API docs](https://developer.woocommerce.com/docs/apis/rest-api/).

## Monetary amounts

Monetary values in Authorization, Payment intents, Reports, and Payouts
responses, such as `amount`, `application_fee_amount`, `fees`, `net`, and
`net_amount`, are integers in the currency's smallest unit (minor units).
The `minimum_amount` value in order capture errors also uses minor units.
For GBP, `1234` means £12.34; for a zero-decimal currency such as JPY,
`1234` means ¥1,234. Use the relevant currency when displaying these
amounts rather than always dividing by 100.

This differs from WooCommerce REST API monetary amounts, which are returned
as decimal strings such as `"12.34"`. Percentage and exchange-rate fields
are not monetary amounts.
