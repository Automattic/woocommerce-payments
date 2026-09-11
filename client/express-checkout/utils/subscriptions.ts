/**
 * Internal dependencies
 */
import {
	getExpressCheckoutData,
	hasExpressCheckoutData,
} from './express-checkout-data';

export type SetupFutureUsage = 'off_session' | null;

type SubscriptionExtensions = {
	subscriptions?: unknown[] | Record< string, unknown >;
	wcpay?: {
		setup_future_usage?: SetupFutureUsage;
	};
};

type CartItem = {
	extensions?: SubscriptionExtensions;
	[ key: string ]: unknown;
};

type CartData = {
	extensions?: SubscriptionExtensions;
	items?: CartItem[];
	[ key: string ]: unknown;
};

const isSubscriptionData = (
	subscriptionData: SubscriptionExtensions[ 'subscriptions' ]
): boolean => {
	if ( Array.isArray( subscriptionData ) ) {
		return subscriptionData.length > 0;
	}

	if ( typeof subscriptionData !== 'object' || subscriptionData === null ) {
		return false;
	}

	const billingPeriod = subscriptionData.billing_period;
	const billingInterval = subscriptionData.billing_interval;

	return (
		typeof billingPeriod === 'string' &&
		billingPeriod.length > 0 &&
		typeof billingInterval === 'number' &&
		billingInterval > 0
	);
};

/**
 * Checks if the cart contains any subscription schedule (trial or recurring).
 * Detects every cart shape that should trigger `setup_future_usage=off_session`
 * on the PaymentIntent backend-side: initial subscription purchase, trial with
 * sign-up fee, renewal, resubscribe, and switch carts.
 *
 * WC Subscriptions exposes subscription data on the Store API response in two
 * places, and which one is populated depends on the cart shape:
 *   - `cartData.extensions.subscriptions` — populated for initial subscription
 *     purchases (one entry per recurring schedule). Empty for renewal carts.
 *   - `cartData.items[].extensions.subscriptions` — populated on each cart item
 *     that is a subscription product, including renewals/resubscribes/switches
 *     where the item is the existing subscription line item.
 *
 * Checking both keeps the detection robust across WC Subscriptions versions and
 * cart shapes.
 *
 * @param cartData Cart data from Store API.
 * @return True if cart contains any subscription schedule.
 */
export const cartHasAnySubscription = ( cartData?: CartData ): boolean => {
	const schedules = cartData?.extensions?.subscriptions;
	if ( Array.isArray( schedules ) && schedules.length > 0 ) {
		return true;
	}

	const items = cartData?.items;
	if ( ! Array.isArray( items ) ) {
		return false;
	}

	return items.some( ( item ) =>
		isSubscriptionData( item?.extensions?.subscriptions )
	);
};

/**
 * Reads the server's decision out of the localized params, honouring the older
 * `has_subscription` flag it replaced.
 *
 * Presence, not truthiness. The server declaring `null` is a decision — "this payment does
 * not save the payment method" — and it has to outrank the older flag, because the two are
 * computed by different predicates over different state: `has_subscription` reads the live
 * cart via `has_subscription_product()`, while `setup_future_usage` reads the order on the
 * order-pay page. A shopper with a subscription in the cart paying an unrelated one-off
 * order would otherwise mint a token declaring `off_session` against an intent that never
 * asks for it, and Stripe would silently vault their card with no WooPayments token
 * recorded against it.
 *
 * The server always sends the key, so the fallback below only covers a stale cached asset
 * or a payload something else has stripped. `wcpay_express_checkout_js_params` is a
 * documented extension point, so an integration forcing `has_subscription` should move to
 * the `wcpay_express_checkout_setup_future_usage` filter, which is evaluated server-side
 * alongside everything else.
 *
 * @deprecated `has_subscription` support here is transitional; declare through the filter.
 *
 * @return Stripe setupFutureUsage value.
 */
const getLocalizedSetupFutureUsage = (): SetupFutureUsage => {
	if ( hasExpressCheckoutData( 'setup_future_usage' ) ) {
		return getExpressCheckoutData( 'setup_future_usage' ) ?? null;
	}

	return getExpressCheckoutData( 'has_subscription' ) ? 'off_session' : null;
};

/**
 * Gets the setupFutureUsage value that should be passed to Stripe Elements for
 * the current cart.
 *
 * The server decides this — it is the only side that knows every reason the payment method
 * might be saved, and `wcpay_express_checkout_setup_future_usage` is where it takes
 * declarations for the reasons it can't infer. There is deliberately no client-side filter:
 * the server also decides whether the payment actually saves the method, so a value
 * declared only in the browser would guarantee the divergence Stripe punishes.
 *
 * The WC Subscriptions heuristic below only runs when the cart response carries no `wcpay`
 * extension, which no supported WooCommerce should produce; it is there so a cart that
 * loses the extension degrades to the old behaviour rather than to "never save".
 *
 * @param cartData Cart data from Store API.
 * @return Stripe setupFutureUsage value.
 */
export const getSetupFutureUsageForCart = (
	cartData?: CartData
): SetupFutureUsage => {
	const wcpayExtension = cartData?.extensions?.wcpay;
	let value: SetupFutureUsage;

	if ( wcpayExtension && 'setup_future_usage' in wcpayExtension ) {
		// Presence, not truthiness: an explicit `null` from the server is a decision
		// ("this cart does not save the payment method") and must beat the heuristic.
		value = wcpayExtension.setup_future_usage ?? null;
	} else if (
		getExpressCheckoutData( 'button_context' ) === 'pay_for_order'
	) {
		// Pay-for-order reads an order, not a cart, and the extension registers on the
		// cart schema only — so this data will never carry it. The subscription being
		// renewed lives on the order, which only the server can see, and the order does
		// not change under us the way a cart does.
		value = getLocalizedSetupFutureUsage();
	} else {
		value = cartHasAnySubscription( cartData ) ? 'off_session' : null;
	}

	return value;
};

/**
 * Gets the setupFutureUsage value for contexts that have no Store API cart to read —
 * the product page, where the express button is rendered before anything is in the cart.
 *
 * @return Stripe setupFutureUsage value.
 */
export const getSetupFutureUsageForContext = (): SetupFutureUsage =>
	getLocalizedSetupFutureUsage();
