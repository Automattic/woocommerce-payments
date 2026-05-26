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
	getVariationId,
} from 'wcpay/utils/wc-product-page-selectors';

jQuery( ( $ ) => {
	// Classic shortcode: listen for jQuery variation-change event.
	$( document.body ).on( 'woocommerce_variation_has_changed', async () => {
		doAction( 'wcpay.express-checkout.update-button-data' );
	} );

	// IAPI block: the new block doesn't fire the legacy jQuery event.
	// Listen for native option changes inside the block to keep button data in sync.
	if ( isIAPIBlock() ) {
		const updateButtonData = debounce( 250, () => {
			doAction( 'wcpay.express-checkout.update-button-data' );
		} );

		const blockRoot = document.querySelector(
			'.wp-block-add-to-cart-with-options'
		);
		if ( blockRoot ) {
			blockRoot.addEventListener( 'change', updateButtonData, true );
			blockRoot.addEventListener( 'input', updateButtonData, true );
		}

		// Also observe attribute updates for cases where the value is set via setAttribute.
		const variationInput = blockRoot?.querySelector(
			'input[name="variation_id"]'
		);
		if ( variationInput ) {
			const observer = new MutationObserver( updateButtonData );
			observer.observe( variationInput, {
				attributes: true,
				attributeFilter: [ 'value' ],
			} );
		}
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
 * Filter 1: Override the product ID.
 *
 * Classic form: read from `.single_variation_wrap input[name="product_id"]`.
 * IAPI block:  read from the hidden `product_id` input inside the block, OR
 *              if a variation is already resolved, use the variation ID
 *              directly (the Store API accepts a variation ID as `id`).
 */
addFilter(
	'wcpay.express-checkout.cart-add-item',
	'automattic/wcpay/express-checkout',
	( productData ) => {
		// --- Classic shortcode path (unchanged) ---
		const variationInformation = document.querySelector(
			'.single_variation_wrap'
		);
		if ( variationInformation && ! isIAPIBlock() ) {
			const productIdInput = variationInformation.querySelector(
				'input[name="product_id"]'
			);
			if ( productIdInput ) {
				return {
					...productData,
					id: parseInt( productIdInput.value, 10 ),
				};
			}
		}

		// --- IAPI block path ---
		if ( isIAPIBlock() ) {
			// When a variation is fully resolved, send the variation ID as the
			// cart item `id`. The Store API treats a variation ID as a valid
			// product identifier, which avoids the need to send individual
			// attribute key/value pairs (and the brittle label-matching they
			// require). This is the same approach WooCommerce core uses when
			// submitting the IAPI form.
			const variationId = getVariationId();
			if ( variationId ) {
				return {
					...productData,
					id: variationId,
					// Clear the variation array — not needed when sending
					// the resolved variation ID directly.
					variation: [],
				};
			}

			// Variation not yet resolved — fall back to parent product ID
			// so the caller can decide how to handle it (the click handler
			// will show an alert via the disabled-button guard).
			const hiddenInput = document.querySelector(
				'.wp-block-add-to-cart-with-options input[name="product_id"]'
			);
			if ( hiddenInput ) {
				return {
					...productData,
					id: parseInt( hiddenInput.value, 10 ),
				};
			}
		}

		return productData;
	}
);

/**
 * Filter 2: Append variation attributes.
 *
 * Classic form: read from `.variations_form .variations select`.
 * IAPI block:  skip — Filter 1 already sends the resolved variation ID,
 *              which makes attribute key/value pairs unnecessary.
 */
addFilter(
	'wcpay.express-checkout.cart-add-item',
	'automattic/wcpay/express-checkout',
	( productData ) => {
		// When the IAPI block is active and a variation ID is resolved, the
		// `id` already points to the specific variation (set in Filter 1).
		// No need to parse attributes from the DOM.
		if ( isIAPIBlock() ) {
			return productData;
		}

		// --- Classic shortcode path (unchanged) ---
		const variationsForm = document.querySelector( '.variations_form' );
		if ( ! variationsForm ) {
			return productData;
		}

		const attributes = [];
		const variationSelectElements =
			variationsForm.querySelectorAll( '.variations select' );
		Array.from( variationSelectElements ).forEach( function ( select ) {
			const attributeName =
				select.dataset.attribute_name || select.dataset.name;

			attributes.push( {
				// The Store API accepts the variable attribute's label, rather than an internal identifier:
				// https://github.com/woocommerce/woocommerce-blocks/blob/trunk/src/StoreApi/docs/cart.md#add-item
				// It's an unfortunate hack that doesn't work when labels have special characters in them.
				// fallback until https://github.com/woocommerce/woocommerce/pull/55317 has been consolidated in WC Core.
				attribute: Array.from(
					document.querySelector(
						`label[for="${ attributeName.replace(
							'attribute_',
							''
						) }"]`
					).childNodes
				)[ 0 ].textContent,
				value: select.value || '',
			} );

			// proper logic for https://github.com/woocommerce/woocommerce/pull/55317 .
			attributes.push( {
				attribute: attributeName,
				value: select.value || '',
			} );
		} );

		return {
			...productData,
			variation: [ ...productData.variation, ...attributes ],
		};
	}
);
