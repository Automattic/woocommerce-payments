// On product pages, the currency localized server-side at render time can
// be wrong — for example, on cached pages where multi-currency runs in
// cache-optimized mode, the server doesn't know the visitor's currency when
// the cache entry is built, so it falls back to the store base.
// The resolver figures out the actual currency a bit later, and we hold
// onto it here so that when `cart-api.js` calls the Store API, the
// `?currency=` query arg matches what the visitor is shopping in.
let resolvedCurrency = null;

export const setResolvedCurrency = ( currency ) => {
	resolvedCurrency = currency || null;
};

export const getResolvedCurrency = ( fallback ) => resolvedCurrency || fallback;

export const __resetResolvedCurrencyForTests = () => {
	resolvedCurrency = null;
};
