/**
 * Internal dependencies
 */
import { getAppearance } from 'wcpay/checkout/upe-styles';

const CACHE_KEY_PREFIX = 'wcpay_appearance_';

function getCacheKey( location ) {
	return CACHE_KEY_PREFIX + location;
}

/**
 * Retrieves a cached Stripe Elements appearance from localStorage.
 *
 * @param {string} location The elements location (e.g. 'blocks_checkout').
 * @param {string} version  The current cache version for invalidation.
 * @return {Object|null} The cached appearance object, or null on miss/mismatch.
 */
export function getCachedAppearance( location, version ) {
	try {
		const raw = localStorage.getItem( getCacheKey( location ) );
		if ( ! raw ) {
			return null;
		}
		const cached = JSON.parse( raw );
		if ( cached?.version === version ) {
			return cached.appearance;
		}
	} catch {
		// Silent failure (private browsing, quota exceeded, corrupt data, etc.)
	}
	return null;
}

/**
 * Stores a Stripe Elements appearance in localStorage.
 *
 * @param {string} location   The elements location (e.g. 'blocks_checkout').
 * @param {string} version    The current cache version for invalidation.
 * @param {Object} appearance The appearance object to cache.
 */
export function setCachedAppearance( location, version, appearance ) {
	try {
		localStorage.setItem(
			getCacheKey( location ),
			JSON.stringify( { version, appearance } )
		);
	} catch {
		// Silent failure
	}
}

/**
 * Retrieves only the theme from a cached Stripe Elements appearance.
 *
 * @param {string} location The elements location (e.g. 'blocks_checkout').
 * @param {string} version  The current cache version for invalidation.
 * @return {string|null} The cached theme ('stripe' or 'night'), or null on miss/mismatch.
 */
export function getCachedTheme( location, version ) {
	const appearance = getCachedAppearance( location, version );
	return appearance?.theme ?? null;
}

/**
 * Dispatches a synchronous CustomEvent that allows merchants to modify
 * the appearance object before it is cached and passed to Stripe Elements.
 *
 * Merchants hook in via:
 *   document.addEventListener( 'wcpay_elements_appearance', ( event ) => {
 *     event.detail.appearance.theme = 'night';
 *   } );
 *
 * @param {Object} appearance       The appearance object (mutated in-place by listeners).
 * @param {string} elementsLocation The location identifier (e.g. 'blocks_checkout').
 */
export function dispatchAppearanceEvent( appearance, elementsLocation ) {
	document.dispatchEvent(
		new CustomEvent( 'wcpay_elements_appearance', {
			detail: { appearance, elementsLocation },
		} )
	);
}

/**
 * Returns a ready-to-use Stripe Elements appearance object.
 *
 * On cache hit the cached value is returned immediately.
 * On cache miss it waits for web fonts to finish loading (so
 * getComputedStyle captures the real theme font), computes the
 * appearance from the DOM, dispatches the customisation event,
 * and stores the result in the cache.
 *
 * @param {string} location  The elements location (e.g. 'blocks_checkout').
 * @param {string} version   The current cache version for invalidation.
 * @param {boolean} forWooPay Whether to include WooPay-specific rules.
 * @param {Object} scope     The document scope to read styles from.
 * @return {Promise<Object>} The appearance object.
 */
export async function resolveAppearance(
	location,
	version,
	forWooPay = false,
	scope = document
) {
	const cached = getCachedAppearance( location, version );
	if ( cached ) {
		return cached;
	}

	// Wait for web fonts to load so getComputedStyle captures the actual
	// theme font instead of a fallback generic family.
	if ( scope.fonts?.ready ) {
		await scope.fonts.ready;
	}

	const appearance = getAppearance( location, forWooPay, scope );
	dispatchAppearanceEvent( appearance, location );
	setCachedAppearance( location, version, appearance );
	return appearance;
}
