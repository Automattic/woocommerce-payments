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
 * @param {Function} callback Invoked when availability may have changed.
 * @return {Function} Unsubscribe function that detaches all listeners.
 */
export const onProductAvailabilityChange = ( callback ) => {
	const $body = jQuery( document.body );
	$body.on( 'woocommerce_variation_has_changed', callback );

	const onQuantityInput = debounce( callback, 250 );
	jQuery( '.quantity' ).on( 'input', '.qty', onQuantityInput );

	let observer = null;
	const variationSelectors = getIAPIVariationSelectorGroups();
	if ( variationSelectors.length ) {
		let lastSelection = null;
		observer = new MutationObserver(
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
			observer.observe( selector, {
				subtree: true,
				childList: true,
				attributes: true,
			} )
		);
	}

	return () => {
		$body.off( 'woocommerce_variation_has_changed', callback );
		jQuery( '.quantity' ).off( 'input', '.qty', onQuantityInput );
		if ( observer ) {
			observer.disconnect();
		}
	};
};
