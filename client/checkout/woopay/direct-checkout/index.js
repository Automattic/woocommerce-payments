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
import { shouldSkipWooPay } from 'wcpay/checkout/woopay/utils';

let isThirdPartyCookieEnabled = false;

/**
 * Prefetches the WooPay session if the shopper is logged in to WooPay, and sends clicks on the
 * checkout buttons to WooPay.
 */
const handleWooPayDirectCheckout = async () => {
	if ( isThirdPartyCookieEnabled ) {
		if ( await WooPayDirectCheckout.isUserLoggedIn() ) {
			WooPayDirectCheckout.maybePrefetchEncryptedSessionData();
			WooPayDirectCheckout.addRedirectToWooPayEventListener( true );
		}

		return;
	}

	// Pass false to indicate we are not sure if the user is logged in or not.
	WooPayDirectCheckout.addRedirectToWooPayEventListener( false );
};

/**
 * Determines whether the encrypted session data should be prefetched.
 *
 * @return {Promise<boolean|*>} True if the encrypted session data should be prefetched.
 */
const shouldPrefetchEncryptedSessionData = async () => {
	return (
		isThirdPartyCookieEnabled &&
		( await WooPayDirectCheckout.isUserLoggedIn() )
	);
};

/**
 * Prefetches the WooPay session again once the cart settles.
 * Several events often report the same change, so this waits for them to stop.
 */
const refreshAfterCartChange = debounce( handleWooPayDirectCheckout, 400 );

/**
 * The prefetched session carries a copy of the cart, which WooPay shows first.
 * Drop it right away, so a click before the refresh fetches a fresh one.
 */
const handleCartChange = () => {
	WooPayDirectCheckout.setEncryptedSessionDataAsNotPrefetched();
	refreshAfterCartChange();
};

/**
 * The callback function to be called when an item's quantity is updated.
 * Note: debounceSetItemQtyCallback is debounced to prevent multiple calls to
 * maybePrefetchEncryptedSessionData when the quantity of an item is being updated
 * multiple times in quick succession.
 *
 * @type {DebouncedFunc<(function({product: *}): Promise<void>)|*>} The debounced callback function.
 */
const debounceSetItemQtyCallback = debounce( async ( { product } ) => {
	WooPayDirectCheckout.setEncryptedSessionDataAsNotPrefetched();
	if ( ! ( await shouldPrefetchEncryptedSessionData() ) ) {
		return;
	}

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
		handleCartChange();
	}
}, 400 );

/**
 * The callback function to be called when an item is removed from the cart.
 *
 * @param {Object} product The product that is being removed.
 * @return {Promise<void>} A promise that resolves when the callback is complete.
 */
const removeItemCallback = async ( { product } ) => {
	WooPayDirectCheckout.setEncryptedSessionDataAsNotPrefetched();
	if ( ! ( await shouldPrefetchEncryptedSessionData() ) ) {
		return;
	}

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
		handleCartChange();
	}
};

window.addEventListener( 'load', async () => {
	if ( shouldSkipWooPay() ) {
		return;
	}

	WooPayDirectCheckout.init();

	isThirdPartyCookieEnabled =
		await WooPayDirectCheckout.isWooPayThirdPartyCookiesEnabled();

	await WooPayDirectCheckout.initPostMessageTimeout();

	// Note, although the following hooks are prefixed with 'experimental__', they will be
	// graduated to stable in the near future (it'll include the 'experimental__' prefix).
	addAction(
		'experimental__woocommerce_blocks-cart-add-item',
		'wcpay_woopay_direct_checkout',
		handleCartChange
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

	// The hooks above only fire from the React cart and product button. These events also cover
	// the Interactivity API cart (WC 9.8+), classic add-to-cart buttons and the classic mini-cart.
	// `wc-blocks_store_sync_required` also fires on any other cart data change, such as a coupon or
	// shipping rate. Those change the totals in the prefetched session too, so it isn't filtered by type.
	document.body.addEventListener(
		'wc-blocks_added_to_cart',
		handleCartChange
	);
	window.addEventListener(
		'wc-blocks_store_sync_required',
		handleCartChange
	);
	jQuery( document.body ).on(
		'added_to_cart removed_from_cart',
		handleCartChange
	);

	handleWooPayDirectCheckout();
} );

jQuery( ( $ ) => {
	// The classic cart fires only this after "Update cart" or removing an item.
	$( document.body ).on( 'updated_cart_totals', () => {
		if ( shouldSkipWooPay() ) {
			return;
		}

		handleCartChange();
	} );
} );
