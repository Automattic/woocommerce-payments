/**
 * External dependencies
 */
import { useEffect, useState } from 'react';

export const useExpressCheckoutProductHandler = (
	api,
	isProductPage = false
) => {
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

		return api.paymentRequestAddToCart( data );
	};

	useEffect( () => {
		const getIsAddToCartDisabled = () => {
			if ( ! isProductPage ) {
				return false;
			}

			const addToCartButton = document.querySelector(
				'.single_add_to_cart_button'
			);

			return (
				addToCartButton.disabled ||
				addToCartButton.classList.contains( 'disabled' )
			);
		};
		setIsAddToCartDisabled( getIsAddToCartDisabled() );
	}, [ isProductPage ] );

	return {
		addToCart: addToCart,
		isAddToCartDisabled: isAddToCartDisabled,
	};
};
