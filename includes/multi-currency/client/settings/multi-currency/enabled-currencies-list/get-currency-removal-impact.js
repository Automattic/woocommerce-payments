/**
 * Splits the payment methods affected by removing a currency into two tiers.
 *
 * A method is `unavailable` when the removed currency is the only enabled
 * currency it supports — it will no longer be offered at checkout. It is
 * `limited` when it supports the removed currency but also another currency
 * that stays enabled, so it keeps working for those.
 *
 * @param {Object}   paymentMethodsMap Map of method id to `{ title, currencies }`.
 * @param {string}   removedCode       Code of the currency being removed.
 * @param {string[]} enabledCodes      Codes of all currently enabled currencies.
 * @return {{ unavailable: string[], limited: string[] }} Affected method ids per tier.
 */
const getCurrencyRemovalImpact = (
	paymentMethodsMap,
	removedCode,
	enabledCodes
) => {
	const unavailable = [];
	const limited = [];

	if ( ! paymentMethodsMap ) {
		return { unavailable, limited };
	}

	const remainingCodes = enabledCodes.filter(
		( code ) => code !== removedCode
	);

	Object.entries( paymentMethodsMap ).forEach( ( [ method, data ] ) => {
		const currencies = data.currencies ?? [];

		if ( ! currencies.includes( removedCode ) ) {
			return;
		}

		if ( currencies.some( ( code ) => remainingCodes.includes( code ) ) ) {
			limited.push( method );
		} else {
			unavailable.push( method );
		}
	} );

	return { unavailable, limited };
};

export default getCurrencyRemovalImpact;
