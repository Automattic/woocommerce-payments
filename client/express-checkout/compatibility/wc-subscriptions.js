/**
 * External dependencies
 */
import { addFilter } from '@wordpress/hooks';
import { __ } from '@wordpress/i18n';

// This module is imported by both the shortcode entry point (express-checkout/index.js)
// and the blocks entry point (express-checkout/blocks/index.js). Because addFilter
// with the same namespace replaces any previously registered callback, the filters
// are effectively idempotent and safe to import from both bundles.

/**
 * Internal dependencies
 */
import { transformPrice } from '../transformers/wc-to-stripe';

/**
 * Checks if a cart item is a subscription with a free trial (no sign-up fee).
 *
 * @param {Object} item Cart item from Store API.
 * @return {boolean} True if the item is a trial subscription.
 */
const isTrialSubscriptionItem = ( item ) => {
	const subscriptionData = item?.extensions?.subscriptions;
	if ( ! subscriptionData ) {
		return false;
	}

	return (
		subscriptionData.trial_length > 0 &&
		parseInt( subscriptionData.sign_up_fees || '0', 10 ) === 0
	);
};

/**
 * Checks if the cart contains any trial subscriptions with zero total.
 *
 * @param {Object} cartData Cart data from Store API.
 * @return {boolean} True if cart has trial subscriptions with zero total.
 */
const hasTrialSubscriptionInCart = ( cartData ) => {
	if ( ! cartData?.items || ! cartData?.extensions?.subscriptions ) {
		return false;
	}

	const cartTotal = parseInt( cartData.totals?.total_price || '0', 10 );
	if ( cartTotal > 0 ) {
		return false;
	}

	return cartData.items.some( isTrialSubscriptionItem );
};

/**
 * Gets the recurring subscription total from cart extensions.
 * When there are multiple subscription schedules, returns the sum of all recurring totals.
 *
 * @param {Object} cartData Cart data from Store API.
 * @return {Object|null} Object with { amount, totals } or null if no subscriptions.
 */
const getRecurringCartTotal = ( cartData ) => {
	const subscriptions = cartData?.extensions?.subscriptions;
	if ( ! subscriptions || ! Array.isArray( subscriptions ) ) {
		return null;
	}

	// total_price already includes items + shipping + tax.
	let totalRecurring = 0;
	let totalItems = 0;
	let totalTax = 0;
	let totalShipping = 0;
	let totalShippingTax = 0;
	let currencyMinorUnit = 2;
	const taxLines = [];

	subscriptions.forEach( ( subscription ) => {
		if ( ! subscription.totals?.total_price ) {
			return;
		}

		totalRecurring += parseInt( subscription.totals.total_price, 10 );
		totalItems += parseInt( subscription.totals.total_items || '0', 10 );
		totalTax += parseInt( subscription.totals.total_tax || '0', 10 );

		// During free trials, subscription.totals.total_shipping may be 0
		// even after a shipping rate is selected, because shipping is deferred.
		// Read the selected rate's price from the extension's shipping_rates instead.
		const selectedRate = subscription.shipping_rates?.[ 0 ]?.shipping_rates?.find(
			( r ) => r.selected
		);
		if ( selectedRate ) {
			totalShipping += parseInt( selectedRate.price || '0', 10 );
			totalShippingTax += parseInt( selectedRate.taxes || '0', 10 );
		} else {
			totalShipping += parseInt(
				subscription.totals.total_shipping || '0',
				10
			);
			totalShippingTax += parseInt(
				subscription.totals.total_shipping_tax || '0',
				10
			);
		}

		currencyMinorUnit =
			subscription.totals.currency_minor_unit ?? currencyMinorUnit;

		if ( subscription.totals.tax_lines ) {
			taxLines.push( ...subscription.totals.tax_lines );
		}
	} );

	if ( totalRecurring === 0 ) {
		return null;
	}

	const baseTotals = subscriptions[ 0 ]?.totals || cartData.totals;

	return {
		amount: totalRecurring,
		currencyMinorUnit,
		totals: {
			...baseTotals,
			total_price: String( totalRecurring ),
			total_items: String( totalItems ),
			total_tax: String( totalTax ),
			total_shipping: String( totalShipping ),
			total_shipping_tax: String( totalShippingTax ),
			tax_lines: taxLines,
		},
	};
};

/**
 * Filter: wcpay.express-checkout.total-amount
 *
 * For trial subscriptions with $0 cart total, returns the recurring
 * subscription total so Stripe ECE can display a meaningful amount.
 *
 * @param {number} total The original total amount (already transformed for Stripe).
 * @param {Object} cartData Cart data from Store API.
 * @return {number} The total to use for Stripe ECE.
 */
addFilter(
	'wcpay.express-checkout.total-amount',
	'automattic/wcpay/express-checkout/wc-subscriptions',
	( total, cartData ) => {
		if ( ! hasTrialSubscriptionInCart( cartData ) ) {
			return total;
		}

		const recurringTotal = getRecurringCartTotal( cartData );
		if ( ! recurringTotal ) {
			return total;
		}

		return transformPrice( recurringTotal.amount, recurringTotal.totals );
	}
);

/**
 * Filter: wcpay.express-checkout.is-cart-eligible
 *
 * Allows ECE buttons for trial subscriptions even when cart total is $0,
 * because the customer still needs to authorize the recurring payment.
 *
 * @param {boolean} isEligible Whether the cart is eligible for ECE.
 * @param {Object} cartData Cart data from Store API.
 * @return {boolean} Whether ECE buttons should be shown.
 */
addFilter(
	'wcpay.express-checkout.is-cart-eligible',
	'automattic/wcpay/express-checkout/wc-subscriptions',
	( isEligible, cartData ) => {
		if ( isEligible ) {
			return true;
		}

		if ( hasTrialSubscriptionInCart( cartData ) ) {
			const recurringTotal = getRecurringCartTotal( cartData );

			return recurringTotal !== null && recurringTotal.amount > 0;
		}

		return isEligible;
	}
);

/**
 * Gets shipping rates from subscription extensions for trial subscriptions.
 * During free trials, shipping is deferred so rates only exist in the
 * subscription extensions, not in the main cart shipping packages.
 *
 * @param {Object} cartData Cart data from Store API.
 * @return {Array|null} Array of shipping rates or null if none available.
 */
const getSubscriptionShippingRates = ( cartData ) => {
	const subscriptions = cartData?.extensions?.subscriptions;
	if ( ! subscriptions || ! Array.isArray( subscriptions ) ) {
		return null;
	}

	for ( const subscription of subscriptions ) {
		const rates = subscription.shipping_rates?.[ 0 ]?.shipping_rates;
		if ( rates?.length > 0 ) {
			return rates;
		}
	}

	return null;
};

/**
 * Filter: wcpay.express-checkout.shipping-rates
 *
 * For trial subscriptions, falls back to shipping rates from subscription
 * extensions when the main cart has none (shipping is deferred for trials).
 *
 * @param {Array|null} shippingRates Original shipping rates from cart.
 * @param {Object} cartData Cart data from Store API.
 * @return {Array|null} Shipping rates to use.
 */
addFilter(
	'wcpay.express-checkout.shipping-rates',
	'automattic/wcpay/express-checkout/wc-subscriptions',
	( shippingRates, cartData ) => {
		if ( shippingRates && shippingRates.length > 0 ) {
			return shippingRates;
		}

		if ( ! hasTrialSubscriptionInCart( cartData ) ) {
			return shippingRates;
		}

		return getSubscriptionShippingRates( cartData ) || shippingRates;
	}
);

/**
 * Filter: wcpay.express-checkout.shipping-package-id
 *
 * For trial subscriptions, returns the subscription's package ID so the
 * correct shipping package is updated when a rate is selected.
 *
 * @param {number|string} packageId The original package ID (usually 0).
 * @param {Object} cartData Cart data from Store API.
 * @param {string} rateId The shipping rate ID being selected.
 * @return {number|string} The package ID to use.
 */
addFilter(
	'wcpay.express-checkout.shipping-package-id',
	'automattic/wcpay/express-checkout/wc-subscriptions',
	( packageId, cartData, rateId ) => {
		if ( ! hasTrialSubscriptionInCart( cartData ) ) {
			return packageId;
		}

		const subscriptions = cartData?.extensions?.subscriptions;
		if ( ! subscriptions || ! Array.isArray( subscriptions ) ) {
			return packageId;
		}

		for ( const subscription of subscriptions ) {
			const packages = subscription.shipping_rates;
			if ( ! packages || ! Array.isArray( packages ) ) {
				continue;
			}

			for ( const pkg of packages ) {
				const hasRate = pkg?.shipping_rates?.some(
					( rate ) => rate.rate_id === rateId
				);
				if ( hasRate && pkg.package_id ) {
					return pkg.package_id;
				}
			}
		}

		return packageId;
	}
);

/**
 * Filter: wcpay.express-checkout.map-line-items
 *
 * For trial subscriptions, replaces $0 line items with recurring amounts
 * so the Stripe payment sheet shows what the customer will actually be charged.
 *
 * @param {Object} cartData Cart data from Store API.
 * @return {Object} Modified cart data with subscription line items.
 */
addFilter(
	'wcpay.express-checkout.map-line-items',
	'automattic/wcpay/express-checkout/wc-subscriptions',
	( cartData ) => {
		if ( ! hasTrialSubscriptionInCart( cartData ) ) {
			return cartData;
		}

		const subscriptions = cartData?.extensions?.subscriptions;
		if ( ! subscriptions || ! Array.isArray( subscriptions ) ) {
			return cartData;
		}

		// Shallow copy to avoid mutating the original.
		const modifiedItems = [ ...cartData.items ];

		subscriptions.forEach( ( subscription ) => {
			const matchingItemsCount = cartData.items.filter(
				( i ) =>
					i.extensions?.subscriptions?.billing_period ===
					subscription.billing_period
			).length;

			if ( matchingItemsCount === 0 ) {
				return;
			}

			const itemRecurringPrice = Math.round(
				parseInt( subscription.totals?.total_items || '0', 10 ) /
					matchingItemsCount
			);

			modifiedItems.forEach( ( item, index ) => {
				const itemSubscription = item.extensions?.subscriptions;
				if (
					! itemSubscription ||
					! ( itemSubscription.trial_length > 0 ) ||
					itemSubscription.billing_period !==
						subscription.billing_period
				) {
					return;
				}

				modifiedItems[ index ] = {
					...item,
					name: `${ item.name } (${ __(
						'recurring',
						'woocommerce-payments'
					) })`,
					totals: {
						...item.totals,
						line_subtotal: String( itemRecurringPrice ),
						line_total: String( itemRecurringPrice ),
					},
					item_data: [
						...( item.item_data || [] ),
						{
							name: __( 'First payment', 'woocommerce-payments' ),
							value: subscription.next_payment_date,
						},
					],
				};
			} );
		} );

		const recurringTotal = getRecurringCartTotal( cartData );
		if ( ! recurringTotal ) {
			return {
				...cartData,
				items: modifiedItems,
			};
		}

		return {
			...cartData,
			items: modifiedItems,
			totals: {
				...cartData.totals,
				total_price: String( recurringTotal.amount ),
				total_items: recurringTotal.totals.total_items || '0',
				total_tax: recurringTotal.totals.total_tax || '0',
				total_shipping: recurringTotal.totals.total_shipping || '0',
				total_shipping_tax:
					recurringTotal.totals.total_shipping_tax || '0',
				tax_lines: recurringTotal.totals.tax_lines || [],
			},
		};
	}
);
