<?php
namespace WCPay\Core\Enums;

final class Wc_Pay_Endpoints
{
	const ENDPOINT_BASE          = 'https://public-api.wordpress.com/wpcom/v2';
	const ENDPOINT_SITE_FRAGMENT = 'sites/%s';
	const ENDPOINT_REST_BASE     = 'wcpay';

	const ACCOUNTS_API                 = 'accounts';
	const CAPABILITIES_API             = 'accounts/capabilities';
	const PLATFORM_CHECKOUT_API        = 'accounts/platform_checkout';
	const APPLE_PAY_API                = 'apple_pay';
	const CHARGES_API                  = 'charges';
	const CONN_TOKENS_API              = 'terminal/connection_tokens';
	const TERMINAL_LOCATIONS_API       = 'terminal/locations';
	const CUSTOMERS_API                = 'customers';
	const CURRENCY_API                 = 'currency';
	const INTENTIONS_API               = 'intentions';
	const REFUNDS_API                  = 'refunds';
	const DEPOSITS_API                 = 'deposits';
	const TRANSACTIONS_API             = 'transactions';
	const DISPUTES_API                 = 'disputes';
	const FILES_API                    = 'files';
	const ONBOARDING_API               = 'onboarding';
	const TIMELINE_API                 = 'timeline';
	const PAYMENT_METHODS_API          = 'payment_methods';
	const SETUP_INTENTS_API            = 'setup_intents';
	const TRACKING_API                 = 'tracking';
	const PRODUCTS_API                 = 'products';
	const PRICES_API                   = 'products/prices';
	const INVOICES_API                 = 'invoices';
	const SUBSCRIPTIONS_API            = 'subscriptions';
	const SUBSCRIPTION_ITEMS_API       = 'subscriptions/items';
	const READERS_CHARGE_SUMMARY       = 'reader-charges/summary';
	const TERMINAL_READERS_API         = 'terminal/readers';
	const MINIMUM_RECURRING_AMOUNT_API = 'subscriptions/minimum_amount';
	const CAPITAL_API                  = 'capital';
	const WEBHOOK_FETCH_API            = 'webhook/failed_events';
	const DOCUMENTS_API                = 'documents';
	const VAT_API                      = 'vat';
	const LINKS_API                    = 'links';
	const AUTHORIZATIONS_API           = 'authorizations';
}
