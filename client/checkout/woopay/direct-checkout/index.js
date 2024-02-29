/* global jQuery */
/**
 * External dependencies
 */
import { dispatch, select } from '@wordpress/data';
import { addAction } from '@wordpress/hooks';
import { debounce } from 'lodash';
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
	WooPayDirectCheckout.maybePrefetchEncryptedSessionData();

	isThirdPartyCookieEnabled = await WooPayDirectCheckout.isWooPayThirdPartyCookiesEnabled();
	const checkoutElements = WooPayDirectCheckout.getCheckoutRedirectElements();
	if ( isThirdPartyCookieEnabled ) {
		if ( await WooPayDirectCheckout.isUserLoggedIn() ) {
			WooPayDirectCheckout.redirectToWooPay( checkoutElements, false );
		}

		return;
	}

	// Pass true to append '&checkout_redirect=1' and let WooPay decide the checkout flow.
	WooPayDirectCheckout.redirectToWooPay( checkoutElements, true );
} );

jQuery( ( $ ) => {
	$( document.body ).on( 'updated_cart_totals', async () => {
		if ( ! WooPayDirectCheckout.isWooPayDirectCheckoutEnabled() ) {
			return;
		}

		WooPayDirectCheckout.maybePrefetchEncryptedSessionData();

		// When "updated_cart_totals" is triggered, the classic 'Proceed to Checkout' button is
		// re-rendered. So, the click-event listener needs to be re-attached to the new button.
		const checkoutButton = WooPayDirectCheckout.getClassicProceedToCheckoutButton();
		if ( isThirdPartyCookieEnabled ) {
			if ( await WooPayDirectCheckout.isUserLoggedIn() ) {
				WooPayDirectCheckout.redirectToWooPay(
					[ checkoutButton ],
					false
				);
			}

			return;
		}

		WooPayDirectCheckout.redirectToWooPay( [ checkoutButton ], true );
	} );
} );

const addItemCallback = async () => {
	// The 'experimental__woocommerce_blocks-cart-add-item' hook is triggered after an item
	// is added to the cart. So, no special handling is needed here.
	WooPayDirectCheckout.maybePrefetchEncryptedSessionData();
};

const debounceSetItemQtyCallback = debounce( async ( { product } ) => {
	// setItemQtyCallback is debounced to prevent multiple calls to maybePrefetchWooPaySession
	// when the quantity of an item is being updated multiple times in quick succession.
	const cartStore = select( WC_STORE_CART );
	const cartDispatch = dispatch( WC_STORE_CART );

	// product's quantity is being updated so set itemIsPendingQuantity to true. Expect
	// the wcblocks-cart to set itemIsPendingQuantity to false after the quantity is updated.
	cartDispatch.itemIsPendingQuantity( product.key, true );

	// Set attempts to 60 (100 ms * 60 = 6 seconds).
	// This is also set to prevent an infinite loop.
	let attempts = 60;

	// Wait for the item's quantity to be updated or until attempts is 0.
	while ( cartStore.isItemPendingQuantity( product.key ) && attempts > 0 ) {
		attempts = attempts - 1;
		await waitMilliseconds( 100 );
	}

	const isItemQtyUpdatedBeforeOutOfAttempts = attempts > 0;
	if ( isItemQtyUpdatedBeforeOutOfAttempts ) {
		// Only prefetch the WooPay session data if the item's quantity is updated.
		WooPayDirectCheckout.maybePrefetchEncryptedSessionData();
	} else {
		// Force the WooPay session data to be fetched upon button click.
		WooPayDirectCheckout.setEncryptedSessionDataAsNotPrefetched();
	}
}, 400 );

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
		WooPayDirectCheckout.maybePrefetchEncryptedSessionData();
	} else {
		// Force the WooPay session data to be fetched upon button click.
		WooPayDirectCheckout.setEncryptedSessionDataAsNotPrefetched();
	}
};

// Note, although the following hooks are prefixed with 'experimental__', they will be
// graduated to stable in the near future (it'll include the 'experimental__' prefix).
addAction(
	'experimental__woocommerce_blocks-cart-add-item',
	'wcpay_woopay_direct_checkout',
	addItemCallback
);

addAction(
	'experimental__woocommerce_blocks-cart-set-item-quantity',
	'wcpay_woopay_direct_checkout',
	debounceSetItemQtyCallback
);

addAction(
	'experimental__woocommerce_blocks-cart-remove-item',
	'wcpay_woopay_direct_checkout',
	removeItemCallback
);
