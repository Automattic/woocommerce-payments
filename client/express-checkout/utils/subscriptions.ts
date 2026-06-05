type SubscriptionExtensions = {
	subscriptions?: unknown[] | Record< string, unknown >;
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
 * Gets the setupFutureUsage value that should be passed to Stripe Elements for
 * the current cart.
 *
 * @param cartData Cart data from Store API.
 * @return Stripe setupFutureUsage value.
 */
export const getSetupFutureUsageForCart = (
	cartData?: CartData
): 'off_session' | null =>
	cartHasAnySubscription( cartData ) ? 'off_session' : null;
