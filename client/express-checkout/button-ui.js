/* global jQuery */

let $wcpayExpressCheckoutContainer = null;

const expressCheckoutButtonUi = {
	init: ( { $container } ) => {
		$wcpayExpressCheckoutContainer = $container;
	},

	getElements: () => {
		return $wcpayExpressCheckoutContainer;
	},

	getButtonSeparator: () => {
		return jQuery( '#wcpay-express-checkout-button-separator' );
	},

	blockButton: () => {
		// check if element isn't already blocked before calling block() to avoid blinking overlay issues
		// blockUI.isBlocked is either undefined or 0 when element is not blocked
		if ( $wcpayExpressCheckoutContainer.data( 'blockUI.isBlocked' ) ) {
			return;
		}

		$wcpayExpressCheckoutContainer.block( { message: null } );
	},

	unblockButton: () => {
		expressCheckoutButtonUi.show();
		$wcpayExpressCheckoutContainer.unblock();
	},

	showButton: ( eceButton ) => {
		if ( $wcpayExpressCheckoutContainer?.length ) {
			expressCheckoutButtonUi.show();
			eceButton.mount( '#wcpay-express-checkout-element' );
		}
	},

	hide: () => {
		expressCheckoutButtonUi.getElements().hide();
	},

	show: () => {
		expressCheckoutButtonUi.getElements().show();
	},
};

export default expressCheckoutButtonUi;
