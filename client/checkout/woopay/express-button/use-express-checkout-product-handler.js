/**
 * External dependencies
 */
import { useEffect, useState } from 'react';
import validator from 'validator';

const useExpressCheckoutProductHandler = ( api, isProductPage = false ) => {
	const [ isAddToCartDisabled, setIsAddToCartDisabled ] = useState( false );

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
				alert( 'Please fill out all required fields' );
				return false;
			}
		}

		if ( data.hasOwnProperty( 'wc_gc_giftcard_to_multiple' ) ) {
			if (
				! data.wc_gc_giftcard_to_multiple
					.split( ',' )
					.every( ( email ) => validator.isEmail( email.trim() ) )
			) {
				alert( 'Please type only valid emails' );
				return false;
			}
		}

		if ( data.hasOwnProperty( 'wc_gc_giftcard_to' ) ) {
			if ( ! validator.isEmail( data.wc_gc_giftcard_to ) ) {
				alert( 'Please type only valid emails' );
				return false;
			}
		}

		return true;
	};

	const getProductData = () => {
		let productId = document.querySelector( '.single_add_to_cart_button' )
			.value;

		// Check if product is a bundle product.
		const bundle = document.querySelector( '.bundle_form' );

		let data = {
			product_id: productId,
			qty: document.querySelector( '.quantity .qty' ).value,
		};

		if ( bundle ) {
			const formData = new FormData( bundle );

			const attributes = {};

			for ( const fields of formData.entries() ) {
				attributes[ fields[ 0 ] ] = fields[ 1 ];
			}

			data = { ...data, ...attributes };
		} else {
			// Check if product is a variable product.
			const variation = document.querySelector(
				'.single_variation_wrap'
			);
			if ( variation ) {
				productId = variation.querySelector(
					'input[name="product_id"]'
				).value;

				data.attributes = document.querySelector( '.variations_form' )
					? getAttributes()
					: [];
			}
		}

		const addOnForm = document.querySelector( 'form.cart' );

		if ( addOnForm ) {
			const formData = new FormData( addOnForm );

			formData.forEach( ( value, name ) => {
				if (
					/^addon-/.test( name ) ||
					/^wc_gc_giftcard_/.test( name )
				) {
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

	useEffect( () => {
		if ( ! isProductPage ) {
			return;
		}

		const getIsAddToCartDisabled = () => {
			const addToCartButton = document.querySelector(
				'.single_add_to_cart_button'
			);

			return (
				addToCartButton.disabled ||
				addToCartButton.classList.contains( 'disabled' )
			);
		};

		const onVariationChange = () => {
			setIsAddToCartDisabled( getIsAddToCartDisabled() );
		};

		// eslint-disable-next-line no-undef
		jQuery( '.variations_form' ).on( 'hide_variation', onVariationChange );

		// eslint-disable-next-line no-undef
		jQuery( '.variations_form' ).on( 'show_variation', () => {
			// The event can take up to 200 milliseconds to be triggered
			// eslint-disable-next-line max-len
			// https://github.com/woocommerce/woocommerce/blob/850523284653ef66ce671815f12d4fa4e6f2cf50/plugins/woocommerce/client/legacy/js/frontend/add-to-cart-variation.js#L318
			setTimeout( () => {
				onVariationChange();
			}, 200 );
		} );

		return () => {
			// eslint-disable-next-line no-undef
			jQuery( '.variations_form' ).off(
				'hide_variation',
				onVariationChange
			);

			// eslint-disable-next-line no-undef
			jQuery( '.variations_form' ).off(
				'show_variation',
				onVariationChange
			);
		};
	}, [ isProductPage, setIsAddToCartDisabled ] );

	return {
		addToCart: addToCart,
		getProductData: getProductData,
		isAddToCartDisabled: isAddToCartDisabled,
	};
};

export default useExpressCheckoutProductHandler;
