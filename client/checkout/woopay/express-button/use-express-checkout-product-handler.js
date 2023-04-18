/**
 * External dependencies
 */
import { useEffect, useState } from 'react';

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

	const addToCart = () => {
		let productId = document.querySelector( '.single_add_to_cart_button' )
			.value;

		// Check if product is a variable product.
		const variation = document.querySelector( '.single_variation_wrap' );
		if ( variation ) {
			productId = variation.querySelector( 'input[name="product_id"]' )
				.value;
		}

		const data = {
			product_id: productId,
			qty: document.querySelector( '.quantity .qty' ).value,
			attributes: document.querySelector( '.variations_form' )
				? getAttributes()
				: [],
		};

		const addOnForm = document.querySelector( 'form.cart' );

		if ( addOnForm ) {
			const formData = new FormData( addOnForm );

			formData.forEach( ( value, name ) => {
				if ( /^addon-/.test( name ) ) {
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
		}

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
		setIsAddToCartDisabled( getIsAddToCartDisabled() );

		const onVariationChange = () =>
			setIsAddToCartDisabled( getIsAddToCartDisabled() );

		const variationList = document.querySelector( '.variations_form' );

		if ( variationList ) {
			variationList.addEventListener( 'change', onVariationChange );
		}

		return () => {
			if ( variationList ) {
				variationList.removeEventListener(
					'change',
					onVariationChange
				);
			}
		};
	}, [ isProductPage, setIsAddToCartDisabled ] );

	return {
		addToCart: addToCart,
		isAddToCartDisabled: isAddToCartDisabled,
	};
};

export default useExpressCheckoutProductHandler;
