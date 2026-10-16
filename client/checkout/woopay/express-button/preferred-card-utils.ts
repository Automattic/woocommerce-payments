const cacheKey = 'woopay_preferred_card';
const last4Pattern = /^\d{4}$/;

export interface PreferredCard {
	brand: string;
	last4: string;
}

/**
 * Maps Stripe display_brand values to the short brand names used by
 * wooPayCardBrands. WCPay's token service stores display_brand
 * (e.g. "american_express") when available, but our card icon lookup
 * uses the short form (e.g. "amex").
 */
const brandAliases: Record< string, string > = {
	american_express: 'amex',
	diners_club: 'diners',
	union_pay: 'unionpay',
};

export const normalizeBrand = ( brand: string ): string => {
	return brandAliases[ brand ] || brand;
};

export const isValidPreferredCard = (
	card: unknown
): card is PreferredCard => {
	if ( ! card || typeof card !== 'object' ) {
		return false;
	}
	const candidate = card as Partial< PreferredCard >;
	return (
		typeof candidate.brand === 'string' &&
		candidate.brand.length > 0 &&
		typeof candidate.last4 === 'string' &&
		last4Pattern.test( candidate.last4 )
	);
};

/**
 * Shallow equality for preferred-card values. Cheaper and clearer than
 * JSON.stringify, and safe because the shape is fixed at `{ brand, last4 }`.
 */
export const isSameCard = (
	a: PreferredCard | null,
	b: PreferredCard | null
): boolean => {
	return a?.brand === b?.brand && a?.last4 === b?.last4;
};

export const getCachedPreferredCard = (): PreferredCard | null => {
	try {
		const cached = localStorage.getItem( cacheKey );
		if ( ! cached ) {
			return null;
		}
		const parsed = JSON.parse( cached );
		return isValidPreferredCard( parsed ) ? parsed : null;
	} catch {
		return null;
	}
};

export const setCachedPreferredCard = ( card: PreferredCard | null ): void => {
	try {
		if ( isValidPreferredCard( card ) ) {
			localStorage.setItem(
				cacheKey,
				JSON.stringify( { brand: card.brand, last4: card.last4 } )
			);
		} else {
			localStorage.removeItem( cacheKey );
		}
	} catch {
		// localStorage unavailable (e.g. private browsing) — silently ignore.
	}
};
