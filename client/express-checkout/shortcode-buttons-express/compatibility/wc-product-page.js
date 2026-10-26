/* global jQuery */
/**
 * Internal dependencies
 */
import expressCheckoutButtonUi from '../button-ui';
import debounce from '../debounce';

/**
 * External dependencies
 */
import { addFilter, doAction } from '@wordpress/hooks';
import { getExpressCheckoutData } from 'wcpay/express-checkout/utils';
import {
	isIAPIBlock,
	getIAPIVariationAttributes,
	getClassicVariationAttributes,
	getIAPIVariationSelectorGroups,
} from 'wcpay/utils/wc-product-page-selectors';

jQuery( ( $ ) => {
	// Classic shortcode: listen for jQuery variation-change event.
	$( document.body ).on( 'woocommerce_variation_has_changed', async () => {
		doAction( 'wcpay.express-checkout.update-button-data' );
	} );

	// IAPI block: the new block doesn't fire the legacy jQuery event, and its
	// variation pills resolve selections through Interactivity API directives
	// rather than native `change`/`input` events — so DOM event listeners miss
	// them. The block does re-render its selectors (toggling `aria-checked`,
	// selected classes, options) when the selection changes, so a
	// MutationObserver on the selectors catches every path: pills, dropdowns,
	// and default/URL-preselected variations.
	//
	// We observe only the variation selectors, never the whole form: the block
	// renders the express button inside the same form, and refreshing the
	// button mutates it (block/unblock overlays), which would retrigger the
	// observer in a loop. The idempotency guard is a second line of defense —
	// it ignores mutations that don't change the actual selection.
	const variationSelectors = getIAPIVariationSelectorGroups();
	if ( variationSelectors.length ) {
		let lastSelection = null;
		const observer = new MutationObserver(
			debounce( 250, () => {
				const selection = JSON.stringify(
					getIAPIVariationAttributes()
				);
				if ( selection === lastSelection ) {
					return;
				}
				lastSelection = selection;
				doAction( 'wcpay.express-checkout.update-button-data' );
			} )
		);
		variationSelectors.forEach( ( selector ) =>
			observer.observe( selector, {
				subtree: true,
				childList: true,
				attributes: true,
			} )
		);
	}
} );

// Block the payment request button as soon as an "input" event is fired, to avoid sync issues
// when the customer clicks on the button before the debounced event is processed.
jQuery( ( $ ) => {
	if ( getExpressCheckoutData( 'button_context' ) !== 'product' ) {
		return;
	}

	const $quantityInput = $( '.quantity' );
	const handleQuantityChange = () => {
		expressCheckoutButtonUi.blockButton();
	};
	$quantityInput.on( 'input', '.qty', handleQuantityChange );
	$quantityInput.on(
		'input',
		'.qty',
		debounce( 250, async () => {
			doAction( 'wcpay.express-checkout.update-button-data' );
		} )
	);
} );

/**
 * Resolve a variable product to its variation for the cart-add-item request.
 *
 * Both the classic form and the IAPI block expose the parent product ID via
 * `.single_variation_wrap input[name="product_id"]` and hold the selection in
 * their respective DOM. We send the parent ID plus the selected attributes;
 * the Store API resolves the concrete variation. The attributes are required
 * even when a variation is resolved, because "Any"-valued attributes carry no
 * value on the variation and the Store API rejects the request without them.
 */
addFilter(
	'wcpay.express-checkout.cart-add-item',
	'automattic/wcpay/express-checkout',
	( productData ) => {
		const result = { ...productData };

		const productIdInput = document.querySelector(
			'.single_variation_wrap input[name="product_id"]'
		);
		if ( productIdInput ) {
			result.id = parseInt( productIdInput.value, 10 );
		}

		const attributes = isIAPIBlock()
			? getIAPIVariationAttributes()
			: getClassicVariationAttributes();
		if ( attributes.length ) {
			result.variation = [ ...productData.variation, ...attributes ];
		}

		return result;
	}
);
