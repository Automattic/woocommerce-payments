/**
 * External dependencies
 */
import { applyFilters } from '@wordpress/hooks';

/**
 * Internal dependencies
 */
import { setResolvedCurrency } from './resolved-currency-cache';

/**
 * Asynchronous filter pipeline that lets resolvers swap in a post-render
 * currency before Stripe.elements is instantiated.
 *
 * The filter is synchronous (standard `wp.hooks` `applyFilters`), but the
 * threaded value is a `Promise<string>`, so each callback can await upstream
 * resolution and return its own promise. Awaiting the final result yields
 * the lowercase ISO currency the ECE flow should use.
 *
 * @param {string} fallback Lowercase ISO currency to use if no resolver overrides.
 * @param {Object} ctx      Caller context passed through to filter callbacks.
 * @return {Promise<string>}  Resolved lowercase ISO currency.
 */
export async function resolveExpressCheckoutCurrency( fallback, ctx ) {
	const fallbackLower = ( fallback || '' ).toLowerCase();
	const piped = applyFilters(
		'wcpay.express-checkout.resolved-currency',
		Promise.resolve( fallbackLower ),
		ctx
	);

	let resolved = fallbackLower;
	try {
		const value = await piped;
		if ( typeof value === 'string' && value ) {
			resolved = value.toLowerCase();
		}
	} catch ( e ) {
		// A misbehaving resolver shouldn't break ECE init — keep the fallback.
	}

	setResolvedCurrency( resolved );
	return resolved;
}
