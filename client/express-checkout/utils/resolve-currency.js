/**
 * External dependencies
 */
import { applyFilters } from '@wordpress/hooks';

/**
 * Internal dependencies
 */
import { setResolvedCurrency } from './resolved-currency-cache';

/**
 * Threads a `Promise<string>` through `applyFilters` so resolvers can defer
 * (e.g. wait for an async currency lookup) before ECE instantiates Stripe's
 * elements.
 *
 * @param {string} fallback Used if no resolver overrides.
 * @param {Object} ctx      Passed through to filter callbacks.
 * @return {Promise<string>} Lowercase ISO currency.
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
		// a misbehaving resolver shouldn't break ECE init
	}

	setResolvedCurrency( resolved );

	return resolved;
}
