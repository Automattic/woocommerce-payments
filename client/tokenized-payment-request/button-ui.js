/* global jQuery */

let $wcpayPaymentRequestContainer = null;

const paymentRequestButtonUi = {
	init: ( { $container } ) => {
		$wcpayPaymentRequestContainer = $container;
	},

	getElements: () => {
		return jQuery(
			'.wcpay-payment-request-wrapper,#wcpay-payment-request-button-separator'
		);
	},

	blockButton: () => {
		// check if element isn't already blocked before calling block() to avoid blinking overlay issues
		// blockUI.isBlocked is either undefined or 0 when element is not blocked
		if ( $wcpayPaymentRequestContainer.data( 'blockUI.isBlocked' ) ) {
			return;
		}

		$wcpayPaymentRequestContainer.block( { message: null } );
	},

	unblockButton: () => {
		paymentRequestButtonUi.show();
		$wcpayPaymentRequestContainer.unblock();
	},

	showButton: ( paymentRequestButton ) => {
		if ( $wcpayPaymentRequestContainer.length ) {
			paymentRequestButtonUi.show();
			paymentRequestButton.mount( '#wcpay-payment-request-button' );
		}
	},

	hide: () => {
		paymentRequestButtonUi.getElements().hide();
	},

	show: () => {
		paymentRequestButtonUi.getElements().show();
	},
};

export default paymentRequestButtonUi;
