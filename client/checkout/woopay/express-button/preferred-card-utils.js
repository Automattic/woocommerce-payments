/**
 * Internal dependencies
 */
import WooPayUserConnect from 'wcpay/checkout/woopay/connect/user-connect';

const PREFERRED_CARD_CACHE_KEY = 'woopay_preferred_card';
const LAST4_PATTERN = /^\d{4}$/;

/**
 * Maps Stripe display_brand values to the short brand names used by getCardBrands().
 * WCPay's token service stores display_brand (e.g. "american_express") when available,
 * but our card icon lookup uses the short form (e.g. "amex").
 */
const BRAND_ALIASES = {
	american_express: 'amex',
	diners_club: 'diners',
	union_pay: 'unionpay',
};

/**
 * Normalizes a card brand name from Stripe's display_brand format to the
 * short form used by getCardBrands().
 *
 * @param {string} brand The brand string (may be display_brand or short form).
 * @return {string} The normalized short brand name.
 */
export const normalizeBrand = ( brand ) => {
	return BRAND_ALIASES[ brand ] || brand;
};

/**
 * Validates that a preferred card object has the expected shape.
 *
 * @param {Object|null} card The card data to validate.
 * @return {boolean} Whether the card has a valid brand and last4.
 */
export const isValidPreferredCard = ( card ) => {
	return (
		card &&
		typeof card.brand === 'string' &&
		card.brand.length > 0 &&
		typeof card.last4 === 'string' &&
		LAST4_PATTERN.test( card.last4 )
	);
};

/**
 * Reads the cached preferred card from localStorage.
 *
 * @return {Object|null} The cached preferred card ({ brand, last4 }) or null.
 */
export const getCachedPreferredCard = () => {
	try {
		const cached = localStorage.getItem( PREFERRED_CARD_CACHE_KEY );
		if ( ! cached ) {
			return null;
		}
		const parsed = JSON.parse( cached );
		return isValidPreferredCard( parsed ) ? parsed : null;
	} catch {
		return null;
	}
};

/**
 * Writes or clears the preferred card cache in localStorage.
 *
 * @param {Object|null} card The preferred card data or null to clear.
 */
export const setCachedPreferredCard = ( card ) => {
	try {
		if ( isValidPreferredCard( card ) ) {
			localStorage.setItem(
				PREFERRED_CARD_CACHE_KEY,
				JSON.stringify( { brand: card.brand, last4: card.last4 } )
			);
		} else {
			localStorage.removeItem( PREFERRED_CARD_CACHE_KEY );
		}
	} catch {
		// localStorage unavailable (e.g. private browsing) — silently ignore.
	}
};

/**
 * Queries the WooPay Connect iframe for the user's preferred payment method.
 * Returns the card data and cleans up the message listener.
 *
 * @return {Promise<Object|null>} The preferred card ({ brand, last4 }) or null.
 */
export const fetchPreferredCard = async () => {
	const userConnect = new WooPayUserConnect();
	try {
		const card = await userConnect.getPreferredPaymentMethod();
		return isValidPreferredCard( card ) ? card : null;
	} finally {
		userConnect.detachMessageListener();
	}
};
