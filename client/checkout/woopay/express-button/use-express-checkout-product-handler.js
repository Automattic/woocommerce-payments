/**
 * External dependencies
 */
import isEmail from 'validator/lib/isEmail';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import {
	getProductId,
	getQuantity,
	getProductForm,
} from 'wcpay/express-checkout/compatibility/wc-product-page-selectors';

const useExpressCheckoutProductHandler = ( api ) => {
	const getAttributes = () => {
		const select = document
			.querySelector( '.variations_form' )
			?.querySelectorAll( '.variations select' );
		const attributes = {};

		if ( select ) {
			select.forEach( ( s ) => {
				const attributeName = s.name;
				const value = s.value || '';

				attributes[ attributeName ] = value;
			} );
		}

		return attributes;
	};

	const validateGiftCardFields = ( data ) => {
		const requiredFields = [
			'wc_gc_giftcard_to',
			'wc_gc_giftcard_from',
			'wc_gc_giftcard_to_multiple',
		];

		for ( const requiredField of requiredFields ) {
			if (
				data.hasOwnProperty( requiredField ) &&
				! data[ requiredField ]
			) {
				alert(
					__(
						'Please fill out all required fields',
						'woocommerce-payments'
					)
				);
				return false;
			}
		}

		if ( data.hasOwnProperty( 'wc_gc_giftcard_to_multiple' ) ) {
			if (
				! data.wc_gc_giftcard_to_multiple
					.split( ',' )
					.every( ( email ) => isEmail( email.trim() ) )
			) {
				alert(
					__(
						'Please type only valid emails',
						'woocommerce-payments'
					)
				);
				return false;
			}
		}

		if ( data.hasOwnProperty( 'wc_gc_giftcard_to' ) ) {
			if ( ! isEmail( data.wc_gc_giftcard_to ) ) {
				alert(
					__(
						'Please type only valid emails',
						'woocommerce-payments'
					)
				);
				return false;
			}
		}

		return true;
	};

	const getProductData = () => {
		const productId = getProductId();

		if ( ! productId ) {
			return false;
		}

		// Check if product is a bundle product.
		const bundleForm = document.querySelector( '.bundle_form' );
		// Check if product is a variable product.
		const variation = document.querySelector( '.single_variation_wrap' );

		let data = {
			product_id: productId,
			quantity: getQuantity(),
		};

		if ( variation && ! bundleForm ) {
			data.product_id = variation.querySelector(
				'input[name="product_id"]'
			).value;
			data.attributes = document.querySelector( '.variations_form' )
				? getAttributes()
				: [];
		} else {
			const form = getProductForm();

			if ( form ) {
				const formData = new FormData( form );

				// Remove add-to-cart attribute to prevent redirection
				// when "Redirect to the cart page after successful addition"
				// option is enabled.
				formData.delete( 'add-to-cart' );

				const attributes = {};

				for ( const fields of formData.entries() ) {
					attributes[ fields[ 0 ] ] = fields[ 1 ];
				}

				data = {
					...data,
					...attributes,
				};
			}
		}

		const addOnForm = getProductForm();

		if ( addOnForm ) {
			const formData = new FormData( addOnForm );

			formData.forEach( ( value, name ) => {
				if ( /^(addon-|wc_)/.test( name ) ) {
					if ( /\[\]$/.test( name ) ) {
						const fieldName = name.substring( 0, name.length - 2 );

						if ( data[ fieldName ] ) {
							data[ fieldName ].push( value );
						} else {
							data[ fieldName ] = [ value ];
						}
					} else {
						data[ name ] = value;
					}
				}
			} );

			if ( ! validateGiftCardFields( data ) ) {
				return false;
			}
		}

		return data;
	};

	const addToCart = ( data ) => {
		return api.expressCheckoutAddToCart( data );
	};

	return {
		addToCart,
		getProductData,
	};
};

export default useExpressCheckoutProductHandler;
