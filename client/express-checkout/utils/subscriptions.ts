/**
 * External dependencies
 */
import { applyFilters } from '@wordpress/hooks';

/**
 * Internal dependencies
 */
import { getExpressCheckoutData } from './express-checkout-data';

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
 * Applies the extensibility filter that has the last word on `setupFutureUsage`.
 *
 * Declare `off_session` only when the payment method really will be saved. Stripe
 * inherits the token's value onto the PaymentIntent even when the intent itself omits
 * it, so over-declaring silently attaches the shopper's card to the Stripe customer on
 * an ordinary one-off purchase, with no WooPayments token recorded against it.
 *
 * @param value    Server-computed (or heuristic) value.
 * @param cartData Cart data from Store API, when the caller has it.
 * @return Stripe setupFutureUsage value.
 */
const filterSetupFutureUsage = (
	value: SetupFutureUsage,
	cartData?: CartData
): SetupFutureUsage =>
	applyFilters(
		'wcpay.express-checkout.setup-future-usage',
		value,
		cartData
	) as SetupFutureUsage;

/**
 * Reads the server's decision out of the localized params, honouring the older
 * `has_subscription` flag it replaced.
 *
 * `has_subscription` was the only lever before `setup_future_usage` existed, and
 * `wcpay_express_checkout_js_params` is a documented extension point, so an integration
 * may already be forcing it — quite likely one working around this very bug. The server
 * never reports `has_subscription` true without also declaring `off_session`, so this
 * only ever fires for an override.
 *
 * Enabling only: a `has_subscription` of false cannot be told apart from the server
 * computing false, so it is not treated as a suppression. Use the
 * `wcpay_express_checkout_setup_future_usage` filter to suppress.
 *
 * @deprecated `has_subscription` support here is transitional; declare through the filter.
 *
 * @return Stripe setupFutureUsage value.
 */
const getLocalizedSetupFutureUsage = (): SetupFutureUsage => {
	const declared = getExpressCheckoutData( 'setup_future_usage' ) ?? null;
	const legacy = getExpressCheckoutData( 'has_subscription' )
		? 'off_session'
		: null;

	return declared ?? legacy;
};

/**
 * Gets the setupFutureUsage value that should be passed to Stripe Elements for
 * the current cart.
 *
 * The server decides this — it is the only side that knows every reason the payment
 * method might be saved, and it exposes one filter for the reasons it can't infer. The
 * WC Subscriptions heuristic below only runs when the cart response carries no `wcpay`
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

	return filterSetupFutureUsage( value, cartData );
};

/**
 * Gets the setupFutureUsage value for contexts that have no Store API cart to read —
 * the product page, where the express button is rendered before anything is in the cart.
 *
 * @return Stripe setupFutureUsage value.
 */
export const getSetupFutureUsageForContext = (): SetupFutureUsage =>
	filterSetupFutureUsage( getLocalizedSetupFutureUsage() );
