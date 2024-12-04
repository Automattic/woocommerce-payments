/* global jQuery */

let $expressCheckoutSeparator = null;
let expressCheckoutElementId = null;

const get$Container = () => jQuery( expressCheckoutElementId );

const expressCheckoutButtonUi = {
	init: ( { elementId, $separator } ) => {
		expressCheckoutElementId = elementId;
		$expressCheckoutSeparator = $separator;
	},

	getButtonSeparator: () => {
		return $expressCheckoutSeparator;
	},

	blockButton: () => {
		// check if element isn't already blocked before calling block() to avoid blinking overlay issues
		// blockUI.isBlocked is either undefined or 0 when element is not blocked
		if ( get$Container().data( 'blockUI.isBlocked' ) ) {
			return;
		}

		get$Container().block( { message: null } );
	},

	unblockButton: () => {
		expressCheckoutButtonUi.showContainer();
		get$Container().unblock();
	},

	renderButton: ( eceButton ) => {
		if ( get$Container()?.length ) {
			expressCheckoutButtonUi.showContainer();
			eceButton.mount( expressCheckoutElementId );
		}
	},

	hideContainer: () => {
		get$Container().hide();
	},

	showContainer: () => {
		get$Container().show();
	},
};

export default expressCheckoutButtonUi;
