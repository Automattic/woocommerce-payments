/* global jQuery */
/**
 * External dependencies
 */
import { addAction } from '@wordpress/hooks';
/**
 * Internal dependencies
 */
import WooPayDirectCheckout from 'wcpay/checkout/woopay/direct-checkout/woopay-direct-checkout';

let isThirdPartyCookieEnabled = false;

window.addEventListener( 'load', async () => {
	if ( ! WooPayDirectCheckout.isWooPayDirectCheckoutEnabled() ) {
		return;
	}

	WooPayDirectCheckout.init();
	WooPayDirectCheckout.maybePrefetchWooPaySession();

	isThirdPartyCookieEnabled = await WooPayDirectCheckout.isWooPayThirdPartyCookiesEnabled();
	const checkoutElements = WooPayDirectCheckout.getCheckoutRedirectElements();
	if ( isThirdPartyCookieEnabled ) {
		if ( await WooPayDirectCheckout.isUserLoggedIn() ) {
			WooPayDirectCheckout.redirectToWooPaySession( checkoutElements );
		}

		return;
	}

	WooPayDirectCheckout.redirectToWooPay( checkoutElements );
} );

jQuery( ( $ ) => {
	$( document.body ).on( 'updated_cart_totals', async () => {
		if ( ! WooPayDirectCheckout.isWooPayDirectCheckoutEnabled() ) {
			return;
		}

		WooPayDirectCheckout.maybePrefetchWooPaySession();

		// When "updated_cart_totals" is triggered, the classic 'Proceed to Checkout' button is
		// re-rendered. So, the click-event listener needs to be re-attached to the new button.
		const checkoutButton = WooPayDirectCheckout.getClassicProceedToCheckoutButton();
		if ( isThirdPartyCookieEnabled ) {
			if ( await WooPayDirectCheckout.isUserLoggedIn() ) {
				WooPayDirectCheckout.redirectToWooPaySession( [
					checkoutButton,
				] );
			}

			return;
		}

		WooPayDirectCheckout.redirectToWooPay( [ checkoutButton ] );
	} );
} );

const addItemCallback = async () => {
	// The 'experimental__woocommerce_blocks-cart-add-item' hook is triggered after an item
	// is added to the cart. So, no special handling is needed here.
	WooPayDirectCheckout.maybePrefetchWooPaySession();
};

addAction(
	'experimental__woocommerce_blocks-cart-add-item',
	'wcpay_woopay_direct_checkout',
	addItemCallback
);
