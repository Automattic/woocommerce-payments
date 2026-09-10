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

		// Same overlay WooCommerce paints over the order review during a refresh.
		get$Container().block( {
			message: null,
			overlayCSS: { background: '#fff', opacity: 0.6 },
		} );
	},

	// Lifts the overlay without touching visibility, unlike `unblockButton()`.
	unblock: () => {
		get$Container().unblock();
	},

	unblockButton: () => {
		expressCheckoutButtonUi.showContainer();
		expressCheckoutButtonUi.unblock();
	},

	renderButton: ( eceButton ) => {
		if ( get$Container()?.length ) {
			eceButton.mount( expressCheckoutElementId );
		}
	},

	hideContainer: () => {
		get$Container().removeClass( 'is-ready' ).hide();
	},

	showContainer: () => {
		get$Container().addClass( 'is-ready' ).show();
	},
};

export default expressCheckoutButtonUi;
