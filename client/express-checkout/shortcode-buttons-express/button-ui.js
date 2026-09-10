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

	// blockUI clears this on `unblock()`, before the overlay finishes fading.
	isBlocked: () => !! get$Container().data( 'blockUI.isBlocked' ),

	blockButton: () => {
		// re-blocking a blocked element would blink the overlay.
		if ( expressCheckoutButtonUi.isBlocked() ) {
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
