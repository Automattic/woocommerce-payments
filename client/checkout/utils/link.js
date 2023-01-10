/**
 * Class name added to email input when Stripe Link checkout has been selected.
 */
export const STRIPE_LINK_ACTIVE_CLASS = 'stripe-link-checkout-initiated';

/**
 * Checks whether user in current checkout has selected Stripe Link.
 *
 * @param {Object} emailInput Email field DOM element.
 * @return {boolean} True, if Stripe Link checkout has been selected by user.
 */
export const isLinkCheckoutActive = ( emailInput ) => {
	return emailInput.classList.contains( STRIPE_LINK_ACTIVE_CLASS );
};

/**
 * Checks whether WooPay is in the process of querying /user/exists or if registered user has been found.
 *
 * @param {Object} emailInput Email field DOM element.
 * @return {boolean} True, if WooPay is querying for registered email or email is valid registered user.
 */
export const getWooPayQueryStatus = ( emailInput ) => {
	return 'false' !== emailInput.dataset.foundUser;
};

/**
 * Sets input data attribute to match status of /user/exists query.
 *
 * @param {Object} emailInput Email field DOM element.
 * @param {boolean} value True, if email is registered user. False, if otherwise
 */
export const setWooPayQueryStatus = ( emailInput, value ) => {
	emailInput.dataset.foundUser = value;
};

/**
 * Removes /user/exists query status data attribute.
 *
 * @param {Object} emailInput Email field DOM element.
 */
export const clearWooPayQueryStatus = ( emailInput ) => {
	emailInput.removeAttribute( 'data-found-user' );
};
