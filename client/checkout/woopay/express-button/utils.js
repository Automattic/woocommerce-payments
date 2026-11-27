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
 * Returns the store's message when WooPay can't load the pay-for-order order, if that's why
 * the session request was refused.
 *
 * Only for that error code: the session endpoints' other errors (an expired nonce, a missing
 * blog ID, a wrong order key) aren't written for shoppers, so they keep the generic text.
 *
 * @param {Object} response The parsed response of the session request.
 *
 * @return {string} The store's translated message, or an empty string.
 */
export const getSessionErrorMessage = ( response ) =>
	response?.success === false &&
	response.code === 'woopay_pay_for_order_unavailable' &&
	typeof response.data === 'string'
		? response.data
		: '';
