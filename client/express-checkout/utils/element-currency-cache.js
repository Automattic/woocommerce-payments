// The currency the Stripe Express Checkout Element was created with. We
// surface it on Store API checkout requests so the server can refuse to
// place an order when the cart's currency has drifted (e.g. a multi-currency
// plugin flips the cart based on the shipping address chosen inside the
// wallet sheet) after the Element has already booted.
let elementCurrency = null;

export const setElementCurrency = ( currency ) => {
	elementCurrency = currency || null;
};

export const getElementCurrency = () => elementCurrency;

export const __resetElementCurrencyForTests = () => {
	elementCurrency = null;
};
