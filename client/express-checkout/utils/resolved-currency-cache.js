// Cache-optimized multi-currency mode caches product pages at the store
// base, so the localized currency is wrong until the resolver figures it
// out post-render. We hold onto the resolved value here so cart-api can
// pass it as `?currency=` on Store API calls.
let resolvedCurrency = null;

export const setResolvedCurrency = ( currency ) => {
	resolvedCurrency = currency || null;
};

export const getResolvedCurrency = ( fallback ) => resolvedCurrency || fallback;

export const __resetResolvedCurrencyForTests = () => {
	resolvedCurrency = null;
};
