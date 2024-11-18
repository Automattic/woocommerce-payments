/* global jQuery */

let $expressCheckoutSeparator = null;
let $wcpayExpressCheckoutContainer = null;
let expressCheckoutElementId = null;

const expressCheckoutButtonUi = {
	init: ( { elementId, $separator } ) => {
		expressCheckoutElementId = elementId;
		$wcpayExpressCheckoutContainer = jQuery( expressCheckoutElementId );
		$expressCheckoutSeparator = $separator;
	},

	getButtonSeparator: () => {
		return $expressCheckoutSeparator;
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
			eceButton.mount( expressCheckoutElementId );
		}
	},

	hide: () => {
		$wcpayExpressCheckoutContainer.hide();
	},

	show: () => {
		$wcpayExpressCheckoutContainer.show();
	},
};

export default expressCheckoutButtonUi;
