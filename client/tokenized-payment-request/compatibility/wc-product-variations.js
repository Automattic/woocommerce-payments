/* global jQuery */

/**
 * External dependencies
 */
import { addFilter, doAction } from '@wordpress/hooks';
import paymentRequestButtonUi from '../button-ui';
import { waitForAction } from '../frontend-utils';

jQuery( ( $ ) => {
	$( document.body ).on( 'woocommerce_variation_has_changed', async () => {
		try {
			paymentRequestButtonUi.blockButton();

			doAction( 'wcpay.payment-request.update-button-data' );
			await waitForAction( 'wcpay.payment-request.update-button-data' );

			paymentRequestButtonUi.unblockButton();
		} catch ( e ) {
			paymentRequestButtonUi.hide();
		}
	} );
} );

addFilter(
	'wcpay.payment-request.cart-add-item',
	'automattic/wcpay/payment-request',
	( productData ) => {
		const $variationInformation = jQuery( '.single_variation_wrap' );
		if ( ! $variationInformation.length ) {
			return productData;
		}

		const productId = $variationInformation
			.find( 'input[name="product_id"]' )
			.val();
		return {
			...productData,
			id: parseInt( productId, 10 ),
		};
	}
);
addFilter(
	'wcpay.payment-request.cart-add-item',
	'automattic/wcpay/payment-request',
	( productData ) => {
		const $variationsForm = jQuery( '.variations_form' );
		if ( ! $variationsForm.length ) {
			return productData;
		}

		const attributes = [];
		const $variationSelectElements = $variationsForm.find(
			'.variations select'
		);
		$variationSelectElements.each( function () {
			const $select = jQuery( this );
			const attributeName =
				$select.data( 'attribute_name' ) || $select.attr( 'name' );

			attributes.push( {
				// The Store API accepts the variable attribute's label, rather than an internal identifier:
				// https://github.com/woocommerce/woocommerce-blocks/blob/trunk/src/StoreApi/docs/cart.md#add-item
				// It's an unfortunate hack that doesn't work when labels have special characters in them.
				attribute: document.querySelector(
					`label[for="${ attributeName.replace(
						'attribute_',
						''
					) }"]`
				).innerHTML,
				value: $select.val() || '',
			} );
		} );

		return {
			...productData,
			variation: [ ...productData.variation, ...attributes ],
		};
	}
);
