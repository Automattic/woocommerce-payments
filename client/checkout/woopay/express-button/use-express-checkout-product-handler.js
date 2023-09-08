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
		const productId = document.querySelector( '.single_add_to_cart_button' )
			.value;

		// Check if product is a bundle product.
		const bundleForm = document.querySelector( '.bundle_form' );
		// Check if product is a variable product.
		const variation = document.querySelector( '.single_variation_wrap' );

		let data = {
			product_id: productId,
			qty: document.querySelector( '.quantity .qty' ).value,
		};

		if ( bundleForm ) {
			const formData = new FormData( bundleForm );

			const attributes = {};

			for ( const fields of formData.entries() ) {
				attributes[ fields[ 0 ] ] = fields[ 1 ];
			}

			data = {
				...data,
				...attributes,
			};
		} else if ( variation ) {
			data.product_id = variation.querySelector(
				'input[name="product_id"]'
			).value;
			data.attributes = document.querySelector( '.variations_form' )
				? getAttributes()
				: [];
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

		setIsAddToCartDisabled( getIsAddToCartDisabled() );

		const enableAddToCartButton = () => {
			setIsAddToCartDisabled( false );
		};

		const disableAddToCartButton = () => {
			setIsAddToCartDisabled( true );
		};

		const bundleForm = document.querySelector( '.bundle_form' );
		const variationForm = document.querySelector( '.variations_form' );

		if ( bundleForm ) {
			// eslint-disable-next-line no-undef
			jQuery( bundleForm ).on(
				'woocommerce-product-bundle-show',
				enableAddToCartButton
			);

			// eslint-disable-next-line no-undef
			jQuery( bundleForm ).on(
				'woocommerce-product-bundle-hide',
				disableAddToCartButton
			);
		} else if ( variationForm ) {
			// eslint-disable-next-line no-undef
			jQuery( variationForm ).on(
				'show_variation',
				enableAddToCartButton
			);

			// eslint-disable-next-line no-undef
			jQuery( variationForm ).on(
				'hide_variation',
				disableAddToCartButton
			);
		}

		return () => {
			if ( bundleForm ) {
				// eslint-disable-next-line no-undef
				jQuery( bundleForm ).off(
					'woocommerce-product-bundle-show',
					enableAddToCartButton
				);
			} else if ( variationForm ) {
				// eslint-disable-next-line no-undef
				jQuery( variationForm ).off(
					'show_variation',
					enableAddToCartButton
				);
			}
		};
	}, [ isProductPage, setIsAddToCartDisabled ] );

	const addToCart = ( data ) => {
		return api.expressCheckoutAddToCart( data );
	};

	return {
		addToCart,
		getProductData,
		isAddToCartDisabled,
	};
};

export default useExpressCheckoutProductHandler;
