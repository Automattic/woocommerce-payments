/**
 * External dependencies
 */
import { addFilter } from '@wordpress/hooks';
import { __ } from '@wordpress/i18n';

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

	// Check if cart total is zero
	const cartTotal = parseInt( cartData.totals?.total_price || '0', 10 );
	if ( cartTotal > 0 ) {
		return false;
	}

	// Check if any items are trial subscriptions
	return cartData.items.some( isTrialSubscriptionItem );
};

/**
 * Gets the recurring subscription total from cart extensions.
 * When there are multiple subscription schedules, returns the sum of all recurring totals.
 * Note: subscription.totals.total_price already includes shipping.
 *
 * @param {Object} cartData Cart data from Store API.
 * @return {Object|null} Object with { amount, totals } or null if no subscriptions.
 */
const getRecurringCartTotal = ( cartData ) => {
	const subscriptions = cartData?.extensions?.subscriptions;
	if ( ! subscriptions || ! Array.isArray( subscriptions ) ) {
		return null;
	}

	// Sum up all recurring subscription totals (already includes shipping)
	let totalRecurring = 0;
	let totalItems = 0;
	let totalTax = 0;
	let totalShipping = 0;
	let totalShippingTax = 0;
	let currencyMinorUnit = 2;
	const taxLines = [];

	subscriptions.forEach( ( subscription ) => {
		if ( subscription.totals?.total_price ) {
			// total_price already includes items + shipping + tax
			totalRecurring += parseInt( subscription.totals.total_price, 10 );
			totalItems += parseInt(
				subscription.totals.total_items || '0',
				10
			);
			totalTax += parseInt( subscription.totals.total_tax || '0', 10 );
			totalShipping += parseInt(
				subscription.totals.total_shipping || '0',
				10
			);
			totalShippingTax += parseInt(
				subscription.totals.total_shipping_tax || '0',
				10
			);
			currencyMinorUnit =
				subscription.totals.currency_minor_unit ?? currencyMinorUnit;

			// Collect tax lines
			if ( subscription.totals.tax_lines ) {
				taxLines.push( ...subscription.totals.tax_lines );
			}
		}
	} );

	if ( totalRecurring === 0 ) {
		return null;
	}

	// Build aggregated totals object
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
 * Modifies the total amount for Stripe ECE when cart has trial subscriptions.
 * Returns the recurring subscription total instead of $0.
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

		// Transform the recurring amount to Stripe's expected format
		return transformPrice( recurringTotal.amount, recurringTotal.totals );
	}
);

/**
 * Filter: wcpay.express-checkout.is-cart-eligible
 *
 * Determines if ECE buttons should be shown for the current cart.
 * Returns true for trial subscriptions even when cart total is $0.
 *
 * @param {boolean} isEligible Whether the cart is eligible for ECE.
 * @param {Object} cartData Cart data from Store API.
 * @return {boolean} Whether ECE buttons should be shown.
 */
addFilter(
	'wcpay.express-checkout.is-cart-eligible',
	'automattic/wcpay/express-checkout/wc-subscriptions',
	( isEligible, cartData ) => {
		// If already eligible, no need to check further
		if ( isEligible ) {
			return true;
		}

		// Check if this is a trial subscription that should be eligible
		// even though the cart total is $0
		if ( hasTrialSubscriptionInCart( cartData ) ) {
			const recurringTotal = getRecurringCartTotal( cartData );
			return recurringTotal !== null && recurringTotal.amount > 0;
		}

		return isEligible;
	}
);

/**
 * Gets shipping rates from subscription extensions for trial subscriptions.
 * When cart has trial subscriptions, shipping is deferred and rates are only
 * available in the subscription extensions, not in the main cart.
 *
 * The subscription shipping_rates structure mirrors the main cart:
 * subscription.shipping_rates[0].shipping_rates = array of rate objects
 *
 * @param {Object} cartData Cart data from Store API.
 * @return {Array|null} Array of shipping rates or null if none available.
 */
const getSubscriptionShippingRates = ( cartData ) => {
	const subscriptions = cartData?.extensions?.subscriptions;
	if ( ! subscriptions || ! Array.isArray( subscriptions ) ) {
		return null;
	}

	// Get shipping rates from the first subscription package that has them
	for ( const subscription of subscriptions ) {
		const packages = subscription.shipping_rates;
		if ( ! packages || ! Array.isArray( packages ) ) {
			continue;
		}

		// Get rates from the first package
		const firstPackage = packages[ 0 ];
		if (
			firstPackage?.shipping_rates &&
			Array.isArray( firstPackage.shipping_rates ) &&
			firstPackage.shipping_rates.length > 0
		) {
			return firstPackage.shipping_rates;
		}
	}

	return null;
};

/**
 * Filter: wcpay.express-checkout.shipping-rates
 *
 * Provides shipping rates for trial subscriptions when the main cart
 * doesn't have shipping rates (because shipping is deferred for trials).
 *
 * @param {Array|null} shippingRates Original shipping rates from cart.
 * @param {Object} cartData Cart data from Store API.
 * @return {Array|null} Shipping rates to use.
 */
addFilter(
	'wcpay.express-checkout.shipping-rates',
	'automattic/wcpay/express-checkout/wc-subscriptions',
	( shippingRates, cartData ) => {
		// If we already have valid shipping rates, use them
		if ( shippingRates && shippingRates.length > 0 ) {
			return shippingRates;
		}

		// For trial subscriptions, get shipping rates from subscription extensions
		if ( ! hasTrialSubscriptionInCart( cartData ) ) {
			return shippingRates;
		}

		const subscriptionShippingRates = getSubscriptionShippingRates(
			cartData
		);
		if ( subscriptionShippingRates ) {
			return subscriptionShippingRates;
		}

		return shippingRates;
	}
);

/**
 * Filter: wcpay.express-checkout.shipping-package-id
 *
 * Returns the correct package ID for shipping rate selection.
 * For trial subscriptions, returns the subscription's package ID instead of 0.
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
		// For trial subscriptions, use the subscription package ID
		if ( ! hasTrialSubscriptionInCart( cartData ) ) {
			return packageId;
		}

		// Check if the rate exists in subscription shipping rates
		const subscriptions = cartData?.extensions?.subscriptions;
		if ( ! subscriptions || ! Array.isArray( subscriptions ) ) {
			return packageId;
		}

		// Find the package that contains this rate
		for ( const subscription of subscriptions ) {
			const packages = subscription.shipping_rates;
			if ( ! packages || ! Array.isArray( packages ) ) {
				continue;
			}

			for ( const pkg of packages ) {
				const rates = pkg?.shipping_rates;
				if ( ! rates || ! Array.isArray( rates ) ) {
					continue;
				}

				const hasRate = rates.some(
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
 * Modifies cart line items to show recurring subscription amounts
 * instead of $0 for trial subscriptions.
 *
 * @param {Object} cartData Cart data from Store API (may be modified by other filters).
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

		// Create a copy of items to avoid mutating the original
		const modifiedItems = [ ...cartData.items ];

		// For each subscription schedule, update the item totals to show recurring amounts
		subscriptions.forEach( ( subscription ) => {
			// Find items that match this subscription schedule and update their display totals
			modifiedItems.forEach( ( item, index ) => {
				const itemSubscription = item.extensions?.subscriptions;
				if (
					itemSubscription &&
					itemSubscription.trial_length > 0 &&
					itemSubscription.billing_period ===
						subscription.billing_period
				) {
					// Calculate per-item recurring price from subscription totals
					const matchingItemsCount = cartData.items.filter(
						( i ) =>
							i.extensions?.subscriptions?.billing_period ===
							subscription.billing_period
					).length;

					const itemRecurringPrice = Math.round(
						parseInt(
							subscription.totals?.total_items || '0',
							10
						) / matchingItemsCount
					);

					modifiedItems[ index ] = {
						...item,
						// Add "(recurring)" to name to indicate this is the recurring amount
						name: `${ item.name } (${ __(
							'recurring',
							'woocommerce-payments'
						) })`,
						// Override totals to show recurring amount
						totals: {
							...item.totals,
							line_subtotal: String( itemRecurringPrice ),
							line_total: String( itemRecurringPrice ),
						},
						// Add subscription info to item_data for display
						item_data: [
							...( item.item_data || [] ),
							{
								name: __(
									'First payment',
									'woocommerce-payments'
								),
								value: subscription.next_payment_date,
							},
						],
					};
				}
			} );
		} );

		// Also update the cart totals to reflect recurring amounts
		const recurringTotal = getRecurringCartTotal( cartData );

		if ( ! recurringTotal ) {
			return {
				...cartData,
				items: modifiedItems,
			};
		}

		// Use the subscription totals which include tax information
		const subscriptionTotals = recurringTotal.totals;

		return {
			...cartData,
			items: modifiedItems,
			totals: {
				...cartData.totals,
				total_price: String( recurringTotal.amount ),
				total_items: subscriptionTotals.total_items || '0',
				total_tax: subscriptionTotals.total_tax || '0',
				total_shipping: subscriptionTotals.total_shipping || '0',
				total_shipping_tax:
					subscriptionTotals.total_shipping_tax || '0',
				tax_lines: subscriptionTotals.tax_lines || [],
			},
		};
	}
);
