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

jQuery( ( $ ) => {
	$( document.body ).on( 'woocommerce_variation_has_changed', async () => {
		doAction( 'wcpay.express-checkout.update-button-data' );
	} );
} );

// Block the payment request button as soon as an "input" event is fired, to avoid sync issues
// when the customer clicks on the button before the debounced event is processed.
jQuery( ( $ ) => {
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

addFilter(
	'wcpay.express-checkout.cart-add-item',
	'automattic/wcpay/express-checkout',
	( productData ) => {
		const variationInformation = document.querySelector(
			'.single_variation_wrap'
		);
		if ( ! variationInformation ) {
			return productData;
		}

		const productId = variationInformation.querySelector(
			'input[name="product_id"]'
		).value;
		return {
			...productData,
			id: parseInt( productId, 10 ),
		};
	}
);
addFilter(
	'wcpay.express-checkout.cart-add-item',
	'automattic/wcpay/express-checkout',
	( productData ) => {
		const variationsForm = document.querySelector( '.variations_form' );
		if ( ! variationsForm ) {
			return productData;
		}

		const attributes = [];
		const variationSelectElements = variationsForm.querySelectorAll(
			'.variations select'
		);
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
