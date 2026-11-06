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
import {
	waitMilliseconds,
	waitForSelector,
} from 'wcpay/checkout/woopay/direct-checkout/utils';
import WooPayDirectCheckout from 'wcpay/checkout/woopay/direct-checkout/woopay-direct-checkout';
import { shouldSkipWooPay } from 'wcpay/checkout/woopay/utils';

let isThirdPartyCookieEnabled = false;

/**
 * Handle the WooPay direct checkout for the given checkout buttons.
 *
 * @param {HTMLElement[]} checkoutButtons An array of checkout button elements.
 */
const handleWooPayDirectCheckout = async ( checkoutButtons ) => {
	if ( ! checkoutButtons ) {
		return;
	}

	// Filter out null/undefined elements (e.g. when a button getter returns null).
	checkoutButtons = checkoutButtons.filter( Boolean );

	if ( isThirdPartyCookieEnabled ) {
		if ( await WooPayDirectCheckout.isUserLoggedIn() ) {
			WooPayDirectCheckout.maybePrefetchEncryptedSessionData();
			WooPayDirectCheckout.addRedirectToWooPayEventListener(
				checkoutButtons,
				true
			);
		}

		return;
	}

	// Pass false to indicate we are not sure if the user is logged in or not.
	WooPayDirectCheckout.addRedirectToWooPayEventListener(
		checkoutButtons,
		false
	);
};

/**
 * Add an event listener to the mini cart checkout button.
 */
const addMiniCartEventListener = () => {
	const checkoutButton =
		WooPayDirectCheckout.getMiniCartProceedToCheckoutButton();
	handleWooPayDirectCheckout( [ checkoutButton ] );
};

/**
 * Add an event listener to the footer mini cart checkout button.
 */
const addFooterCartEventListener = () => {
	const checkoutButton =
		WooPayDirectCheckout.getFooterMiniCartProceedToCheckoutButton();
	handleWooPayDirectCheckout( [ checkoutButton ] );
};

/**
 * If the mini cart widget is available on the page, attach event listeners to the checkout buttons.
 *
 * Supports two rendering modes:
 * - iAPI (WooCommerce 10.4+): The overlay and buttons are server-side rendered and already in the
 *   DOM at page load. Returns the button elements so the caller can merge them into a single
 *   handleWooPayDirectCheckout call (avoids concurrent isUserLoggedIn races).
 * - Legacy React: The drawer is dynamically injected into the DOM when opened, so we use a
 *   MutationObserver to detect insertion and then wait for the buttons to render.
 *
 * @return {HTMLElement[]} iAPI mini-cart buttons (empty array for legacy/absent mini-cart).
 */
const maybeObserveMiniCart = () => {
	// iAPI mini-cart (WC 10.4+): overlay & buttons are SSR'd, already in the DOM.
	// Return them so the caller can batch all buttons into one handleWooPayDirectCheckout call.
	if (
		document.querySelector(
			'[data-wp-interactive="woocommerce/mini-cart"]'
		)
	) {
		return [
			WooPayDirectCheckout.getMiniCartProceedToCheckoutButton(),
			WooPayDirectCheckout.getFooterMiniCartProceedToCheckoutButton(),
		];
	}

	// Legacy React mini-cart: check if the widget is available on the page.
	if (
		! document.querySelector( '[data-block-name="woocommerce/mini-cart"]' )
	) {
		return [];
	}

	// Create a MutationObserver to check when the mini cart drawer is added to the DOM.
	const observer = new MutationObserver( ( mutations ) => {
		for ( const mutation of mutations ) {
			if ( mutation?.addedNodes?.length > 0 ) {
				for ( const node of mutation.addedNodes ) {
					// Check if the mini cart drawer parent selector was added to the DOM.
					if (
						node.nodeType === 1 &&
						node.matches(
							'.wc-block-components-drawer__screen-overlay'
						)
					) {
						// Wait until the button is rendered and add the event listener to it.
						waitForSelector(
							WooPayDirectCheckout.redirectElements
								.BLOCKS_MINI_CART_PROCEED_BUTTON,
							addMiniCartEventListener
						);
						waitForSelector(
							WooPayDirectCheckout.redirectElements
								.BLOCKS_FOOTER_MINI_CART_PROCEED_BUTTON,
							addFooterCartEventListener
						);
						return;
					}
				}
			}
		}
	} );

	observer.observe( document.body, { childList: true } );

	return [];
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
 * Re-binds the checkout buttons and prefetches the WooPay session again once the cart settles.
 * WooCommerce re-renders some buttons on cart changes (e.g. the blocks cart going from empty
 * to filled), and several events often report the same change, so this waits for them to stop.
 */
const refreshAfterCartChange = debounce( () => {
	handleWooPayDirectCheckout( [
		...WooPayDirectCheckout.getCheckoutButtonElements(),
		WooPayDirectCheckout.getMiniCartProceedToCheckoutButton(),
		WooPayDirectCheckout.getFooterMiniCartProceedToCheckoutButton(),
	] );
}, 400 );

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

	// Collect iAPI mini-cart buttons (if present) and merge with checkout buttons
	// into a single handleWooPayDirectCheckout call to avoid concurrent isUserLoggedIn races.
	// Legacy React mini-cart sets up a MutationObserver internally and returns [].
	const miniCartButtons = maybeObserveMiniCart();

	const checkoutButtons = WooPayDirectCheckout.getCheckoutButtonElements();
	handleWooPayDirectCheckout( [ ...checkoutButtons, ...miniCartButtons ] );
} );

jQuery( ( $ ) => {
	$( document.body ).on( 'updated_cart_totals', async () => {
		if ( shouldSkipWooPay() ) {
			return;
		}

		// When "updated_cart_totals" is triggered, the classic 'Proceed to Checkout' button is
		// re-rendered. So, the click-event listener needs to be re-attached to the new button.
		const checkoutButton =
			WooPayDirectCheckout.getClassicProceedToCheckoutButton();
		handleWooPayDirectCheckout( [ checkoutButton ] );
	} );
} );
