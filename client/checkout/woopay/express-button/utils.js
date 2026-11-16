/**
 * Internal dependencies
 */
import { getConfig } from 'wcpay/utils/checkout';

/**
 * Show an error message to the user, from the WooPay express checkout button.
 *
 * @param {string} context      The context for where the button is being displayed.
 * @param {string} errorMessage The error message to display.
 */
export const showErrorMessage = ( context, errorMessage ) => {
	// Handle Blocks Cart and Checkout notices. WooCommerce renders the pay-for-order page with
	// its shortcode even on block themes, so nothing there shows the Blocks notice store.
	const isBlocksCartOrCheckout =
		'wcSettings' in window && wcSettings.wcBlocksConfig;
	if (
		isBlocksCartOrCheckout &&
		context !== 'product' &&
		context !== 'pay_for_order'
	) {
		// This handles adding the error notice to the cart page.
		wp.data
			.dispatch( 'core/notices' )
			?.createNotice( 'error', errorMessage, {
				context: `wc/${ context }`,
			} );
	} else {
		// We're either on a shortcode cart/checkout or single product page.
		fetch( getConfig( 'ajaxUrl' ), {
			method: 'POST',
			body: new URLSearchParams( {
				action: 'woopay_express_checkout_button_show_error_notice',
				_ajax_nonce: getConfig( 'woopayButtonNonce' ),
				context,
				message: errorMessage,
			} ),
		} )
			.then( ( response ) => response.json() )
			.then( ( response ) => {
				if ( response.success ) {
					// We need to manually add the notice to the page.
					const noticesWrapper = document.querySelector(
						'.woocommerce-notices-wrapper'
					);
					const wrapper = document.createElement( 'div' );
					wrapper.innerHTML = response.data.notice;
					noticesWrapper.insertBefore( wrapper, null );

					noticesWrapper.scrollIntoView( {
						behavior: 'smooth',
						block: 'center',
					} );
				}
			} );
	}
};

/**
 * Returns the message the store sent with a refused pay-for-order WooPay session, if any.
 *
 * Only on the pay-for-order page: elsewhere the session endpoint's errors (an expired nonce,
 * a missing blog ID) aren't written for shoppers.
 *
 * @param {string} context  The context for where the button is being displayed.
 * @param {Object} response The parsed response of the session request.
 *
 * @return {string} The store's translated message, or an empty string.
 */
export const getSessionErrorMessage = ( context, response ) =>
	context === 'pay_for_order' &&
	response?.success === false &&
	typeof response.data === 'string'
		? response.data
		: '';
