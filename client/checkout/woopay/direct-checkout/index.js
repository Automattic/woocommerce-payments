/* global jQuery */
/**
 * External dependencies
 */
import { dispatch, select } from '@wordpress/data';
import { addAction } from '@wordpress/hooks';
/**
 * Internal dependencies
 */
import { WC_STORE_CART } from 'wcpay/checkout/constants';
import { waitMilliseconds } from 'wcpay/checkout/woopay/direct-checkout/utils';
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

const removeItemCallback = async ( { product } ) => {
	const cartStore = select( WC_STORE_CART );
	const cartDispatch = dispatch( WC_STORE_CART );

	// product is being removed so set itemIsPendingDelete to true. Expect the
	// wcblocks-cart to set itemIsPendingDelete to false after the item is removed.
	cartDispatch.itemIsPendingDelete( product.key, true );

	// Set attempts to 60 (100 ms * 60 = 6 seconds).
	// This is also set to prevent an infinite loop.
	let attempts = 60;

	// Wait for the item to be removed or until attempts is 0.
	while ( cartStore.isItemPendingDelete( product.key ) && attempts > 0 ) {
		attempts = attempts - 1;
		await waitMilliseconds( 100 );
	}

	const isItemRemovedBeforeOutOfAttempts = attempts > 0;
	if ( isItemRemovedBeforeOutOfAttempts ) {
		// Only prefetch the WooPay session data if the item is removed.
		WooPayDirectCheckout.maybePrefetchWooPaySession();
	} else {
		// Force the WooPay session data to be fetched upon button click.
		WooPayDirectCheckout.setWooPaySessionAsNotPrefetched();
	}
};

addAction(
	'experimental__woocommerce_blocks-cart-add-item',
	'wcpay_woopay_direct_checkout',
	addItemCallback
);

addAction(
	'experimental__woocommerce_blocks-cart-remove-item',
	'wcpay_woopay_direct_checkout',
	removeItemCallback
);
