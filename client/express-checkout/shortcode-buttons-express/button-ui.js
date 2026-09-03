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

		// Match the white overlay WooCommerce paints over the order review during
		// the same refresh; blockUI's own default is black with a wait cursor.
		get$Container().block( {
			message: null,
			overlayCSS: { background: '#fff', opacity: 0.6 },
		} );
	},

	// Removes the overlay but leaves visibility alone. Callers that let
	// another routine decide whether the button belongs on screen need this:
	// unblocking would otherwise reveal a container that routine hid.
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
