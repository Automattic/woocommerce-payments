/**
 * Class name added to email input when Stripe Link checkout has been selected.
 */
export const STRIPE_LINK_ACTIVE_CLASS = 'stripe-link-checkout-initiated';

/**
 * Checks whether user in current checkout has selected Stripe Link.
 *
 * @param {string} emailInput Email field query selector.
 * @return {boolean} True, if Stripe Link checkout has been selected by user.
 */
export const isLinkCheckoutActive = ( emailInput ) => {
	return emailInput.classList.contains( STRIPE_LINK_ACTIVE_CLASS );
};
