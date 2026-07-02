/* global jQuery */
/**
 * External dependencies
 */
import { createRoot } from 'react-dom/client';

/**
 * External dependencies
 */
import CheckoutPageSaveUser from 'wcpay/components/woopay/save-user/checkout-page-save-user';

let blocksCheckoutRoot = null;

export const renderSaveUserSection = () => {
	const saveUserSection = document.getElementsByClassName(
		'woopay-save-new-user-container'
	)?.[ 0 ];

	if ( saveUserSection ) {
		return;
	}

	const blocksCheckout =
		document.getElementsByClassName( 'wc-block-checkout' );

	if ( blocksCheckout.length ) {
		let checkoutPageSaveUserContainer =
			document.querySelector( '#remember-me' );

		if ( ! checkoutPageSaveUserContainer ) {
			const paymentOptions = document.getElementsByClassName(
				'wp-block-woocommerce-checkout-payment-block'
			)?.[ 0 ];

			// Without the payment options block there's nowhere to attach the
			// section, so bail rather than mount into a detached node.
			if ( ! paymentOptions ) {
				return;
			}

			checkoutPageSaveUserContainer =
				document.createElement( 'fieldset' );

			checkoutPageSaveUserContainer.className =
				'wc-block-checkout__payment-method wp-block-woocommerce-checkout-remember-block wc-block-components-checkout-step ';
			checkoutPageSaveUserContainer.id = 'remember-me';

			// Render right after the payment options block, as a sibling element.
			paymentOptions.parentNode.insertBefore(
				checkoutPageSaveUserContainer,
				paymentOptions.nextSibling
			);

			// A fresh container means any cached root points at a stale node, so
			// unmount it (freeing its store subscriptions and listeners) and
			// mount into the new one.
			blocksCheckoutRoot?.unmount();
			blocksCheckoutRoot = null;
		}

		// Reuse the root across the AJAX-driven re-renders that fire on every
		// checkout update; a second createRoot() on the same node resets the
		// component (losing the checkbox state) and warns under React 18.
		if ( ! blocksCheckoutRoot ) {
			blocksCheckoutRoot = createRoot( checkoutPageSaveUserContainer );
		}

		blocksCheckoutRoot.render(
			<CheckoutPageSaveUser isBlocksCheckout={ true } />
		);
	} else {
		const checkoutPageSaveUserContainer = document.createElement( 'div' );
		checkoutPageSaveUserContainer.className =
			'woopay-save-new-user-container';

		const placeOrderButton = document.getElementsByClassName(
			'form-row place-order'
		)?.[ 0 ];
		const buttonParent = placeOrderButton?.parentNode;

		if ( placeOrderButton && buttonParent ) {
			buttonParent.insertBefore(
				checkoutPageSaveUserContainer,
				placeOrderButton
			);

			const root = createRoot( checkoutPageSaveUserContainer );
			root.render( <CheckoutPageSaveUser isBlocksCheckout={ false } /> );
		}
	}
};

window.addEventListener( 'load', () => {
	renderSaveUserSection();
} );

// mount component again if parent fragment if re-rendered after ajax request by woocommerce core
// https://github.com/woocommerce/woocommerce/blob/trunk/plugins/woocommerce/legacy/js/frontend/checkout.js#L372
jQuery( function ( $ ) {
	$( document ).ajaxComplete( function () {
		renderSaveUserSection();
	} );
} );
