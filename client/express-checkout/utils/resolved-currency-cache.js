/**
 * Module-level cache for the currency the Express Checkout flow has resolved
 * for the current page render. Read by `cart-api.js` so Store API requests
 * carry the post-resolution currency rather than the localized server value
 * baked at `wp_enqueue_scripts` time, which is wrong on session-less pages
 * where currency is resolved client-side (e.g. WCPBC AJAX mode, our own
 * cache-optimized multi-currency mode).
 */

let resolvedCurrency = null;

export const setResolvedCurrency = ( currency ) => {
	resolvedCurrency = currency || null;
};

export const getResolvedCurrency = ( fallback ) => resolvedCurrency || fallback;

export const __resetResolvedCurrencyForTests = () => {
	resolvedCurrency = null;
};
