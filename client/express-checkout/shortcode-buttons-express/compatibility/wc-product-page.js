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
	const variationSelectors = document.querySelectorAll(
		'.wp-block-add-to-cart-with-options .wp-block-woocommerce-add-to-cart-with-options-variation-selector-attribute'
	);
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
 * Override the product ID with the one from the variations wrapper.
 *
 * Both the classic form and the IAPI block expose the parent product ID via
 * `.single_variation_wrap input[name="product_id"]`; the Store API resolves
 * the concrete variation from the attributes appended below.
 */
addFilter(
	'wcpay.express-checkout.cart-add-item',
	'automattic/wcpay/express-checkout',
	( productData ) => {
		const productIdInput = document.querySelector(
			'.single_variation_wrap input[name="product_id"]'
		);
		if ( productIdInput ) {
			return {
				...productData,
				id: parseInt( productIdInput.value, 10 ),
			};
		}
		return productData;
	}
);

/**
 * Append the selected variation attributes, reading them from whichever form
 * is rendered. The Store API needs them even when a variation is resolved:
 * "Any"-valued attributes carry no value on the variation, so it rejects the
 * request unless the shopper's selection is sent.
 */
addFilter(
	'wcpay.express-checkout.cart-add-item',
	'automattic/wcpay/express-checkout',
	( productData ) => {
		const attributes = isIAPIBlock()
			? getIAPIVariationAttributes()
			: getClassicVariationAttributes();

		if ( ! attributes.length ) {
			return productData;
		}

		return {
			...productData,
			variation: [ ...productData.variation, ...attributes ],
		};
	}
);
