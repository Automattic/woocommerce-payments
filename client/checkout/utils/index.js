/**
 * Normalizes the amount to the accuracy of the minor unit.
 *
 * @param {integer} amount The amount to normalize
 * @param {integer} minorUnit The number of decimal places amount currently represents
 * @param {integer} accuracy The number of decimal places to normalize to
 * @return {integer} The normalized amount
 */
export const normalizeCurrencyToMinorUnit = (
	amount,
	minorUnit = 2,
	accuracy = 2
) => {
	return parseInt( amount * Math.pow( 10, accuracy - minorUnit ), 10 );
};
