/* global jQuery */
/**
 * External dependencies
 */
import { debounce } from 'lodash';

/**
 * Internal dependencies
 */
import {
	getIAPIVariationSelectorGroups,
	getIAPIVariationAttributes,
} from 'wcpay/utils/wc-product-page-selectors';

/**
 * Subscribe to changes in the product page's add-to-cart availability.
 *
 * Fires `callback` whenever the shopper changes the variation selection or the
 * quantity — i.e. whenever the answer to "can this product be added to the cart
 * right now?" might have changed. The current answer is read separately via
 * `isAddToCartBlocked()`.
 *
 * Covers the classic shortcode form (jQuery `woocommerce_variation_has_changed`),
 * the new IAPI Add to Cart + Options block (which resolves selections through
 * Interactivity directives rather than DOM events, so we observe its re-rendered
 * variation selectors), and the quantity input.
 *
 * It also observes the add-to-cart control's own state — the classic button's
 * `disabled` class and the IAPI form's `is-invalid` class. WooCommerce toggles
 * those *after* it fires `woocommerce_variation_has_changed` when a variation
 * resolves, so reading availability only on that event races and can miss a
 * button that was just enabled (the express button would stay hidden for a
 * valid, in-stock variation). Observing the control's class settles the race by
 * firing once the real state lands.
 *
 * @param {Function} callback Invoked when availability may have changed.
 * @return {Function} Unsubscribe function that detaches all listeners.
 */
export const onProductAvailabilityChange = ( callback ) => {
	const $body = jQuery( document.body );
	$body.on( 'woocommerce_variation_has_changed', callback );

	const onQuantityInput = debounce( callback, 250 );
	jQuery( '.quantity' ).on( 'input', '.qty', onQuantityInput );

	let selectorObserver = null;
	const variationSelectors = getIAPIVariationSelectorGroups();
	if ( variationSelectors.length ) {
		let lastSelection = null;
		selectorObserver = new MutationObserver(
			debounce( () => {
				const selection = JSON.stringify(
					getIAPIVariationAttributes()
				);
				if ( selection === lastSelection ) {
					return;
				}
				lastSelection = selection;
				callback();
			}, 250 )
		);
		variationSelectors.forEach( ( selector ) =>
			selectorObserver.observe( selector, {
				subtree: true,
				childList: true,
				attributes: true,
			} )
		);
	}

	// Observe the add-to-cart control's enabled/disabled state directly, so we
	// react when WooCommerce enables/disables it after a variation resolves —
	// which can land a tick after `woocommerce_variation_has_changed`.
	const onAddToCartStateChange = debounce( callback, 50 );
	const addToCartStateElements = [
		document.querySelector( '.single_add_to_cart_button' ),
		document.querySelector( '.wp-block-add-to-cart-with-options' ),
	].filter( Boolean );
	let addToCartObserver = null;
	if ( addToCartStateElements.length ) {
		addToCartObserver = new MutationObserver( onAddToCartStateChange );
		addToCartStateElements.forEach( ( el ) =>
			addToCartObserver.observe( el, {
				attributes: true,
				attributeFilter: [ 'class', 'disabled' ],
			} )
		);
	}

	return () => {
		$body.off( 'woocommerce_variation_has_changed', callback );
		jQuery( '.quantity' ).off( 'input', '.qty', onQuantityInput );
		if ( selectorObserver ) {
			selectorObserver.disconnect();
		}
		if ( addToCartObserver ) {
			addToCartObserver.disconnect();
		}
	};
};
