/**
 * Class name added to email input when Stripe Link user has authenticated at checkout.
 */
export const STRIPE_LINK_AUTHENTICATED_CLASS =
	'stripe-link-checkout-authenticated';

/**
 * Checks whether user in current checkout has selected Stripe Link.
 *
 * @param {Object} emailInput Email field DOM element.
 * @return {boolean} True, if Stripe Link user has authenticated via OTP verification.
 */
export const isLinkCheckoutAuthenticated = ( emailInput ) => {
	return emailInput.classList.contains( STRIPE_LINK_AUTHENTICATED_CLASS );
};

/**
 * Returns DOM object for checkout email input field.
 *
 * @param {Object} emailInput Email input DOM object or null.
 * @return {Object} Email input DOM object.
 */
const getEmailInput = ( emailInput ) => {
	if ( null === emailInput ) {
		emailInput =
			document.querySelector( '#billing_email' ) ||
			document.querySelector( '#email' );
	}
	return emailInput;
};

/**
 * Checks whether WooPay is in the process of querying /user/exists or if registered user has been found.
 *
 * @param {Object} emailInput Email field DOM element.
 * @return {string} 'true', if email is valid registered user. 'false', if email is not registered. undefined, if still querying.
 */
export const getWooPayQueryStatus = ( emailInput ) => {
	emailInput = getEmailInput( emailInput );
	return emailInput.dataset.foundUser;
};

/**
 * Sets input data attribute to match status of /user/exists query.
 *
 * @param {Object} emailInput Email field DOM element.
 * @param {boolean} value True, if email is registered user. False, if otherwise.
 */
export const setWooPayQueryStatus = ( emailInput, value ) => {
	emailInput = getEmailInput( emailInput );
	emailInput.dataset.foundUser = value;
};

/**
 * Removes /user/exists query status data attribute.
 *
 * @param {Object} emailInput Email field DOM element.
 */
export const clearWooPayQueryStatus = ( emailInput ) => {
	emailInput = getEmailInput( emailInput );
	emailInput.removeAttribute( 'data-found-user' );
};

/**
 * Checks if Stripe Link autofill modal is open.
 *
 * @return {boolean} True, if modal is displayed on page.
 */
export const isLinkModalOpen = () => {
	const iframes = document.getElementsByTagName( 'iframe' );
	for ( const frame of iframes ) {
		// Find Stripe Link Autofill modal iframe.
		if (
			frame.name.startsWith( '__privateStripeFrame' ) &&
			-1 !== frame.src.indexOf( 'link-autofill-modal-inner' )
		) {
			// Is modal visible on page.
			return 'none' !== frame.parentNode.style.display;
		}
	}
	return false;
};

/**
 * Set input data attribute to describe presence of Link OTP modal.
 *
 * @param {Object} emailInput Email field DOM element.
 * @param {boolean} value True, if modal is open. False, if otherwise.
 */
export const setLinkModalStatus = ( emailInput, value ) => {
	const status = value ? 'open' : 'closed';
	emailInput.dataset.linkModalStatus = status;
};

/**
 * Get current status of Link OTP modal.
 *
 * @param {Object} emailInput Email field DOM element
 * @return {string} Status of Link OTP modal: 'open', 'closed', or undefined (not yet opened).
 */
export const getLinkModalStatus = ( emailInput ) => {
	return emailInput.dataset.linkModalStatus;
};
