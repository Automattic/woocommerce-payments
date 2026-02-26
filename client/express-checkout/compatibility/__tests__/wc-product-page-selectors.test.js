/**
 * Internal dependencies
 */
import {
	getAddToCartButton,
	getProductId,
	getProductForm,
	getQuantity,
} from '../wc-product-page-selectors';

describe( 'wc-product-page-selectors', () => {
	beforeEach( () => {
		document.body.innerHTML = '';
	} );

	describe( 'getAddToCartButton', () => {
		it( 'returns the classic add-to-cart button', () => {
			document.body.innerHTML =
				'<button class="single_add_to_cart_button">Add to cart</button>';
			expect( getAddToCartButton() ).toBe(
				document.querySelector( '.single_add_to_cart_button' )
			);
		} );

		it( 'returns the new block submit button', () => {
			document.body.innerHTML =
				'<div class="wp-block-add-to-cart-with-options"><button type="submit">Add to cart</button></div>';
			expect( getAddToCartButton() ).toBe(
				document.querySelector( 'button[type="submit"]' )
			);
		} );

		it( 'prefers classic button when both exist', () => {
			document.body.innerHTML = [
				'<button class="single_add_to_cart_button" value="10">Add to cart</button>',
				'<div class="wp-block-add-to-cart-with-options"><button type="submit">Add to cart</button></div>',
			].join( '' );
			expect( getAddToCartButton().classList ).toContain(
				'single_add_to_cart_button'
			);
		} );

		it( 'returns null when neither exists', () => {
			expect( getAddToCartButton() ).toBeNull();
		} );
	} );

	describe( 'getProductId', () => {
		it( 'returns product ID from classic button value', () => {
			document.body.innerHTML =
				'<button class="single_add_to_cart_button" value="42">Add to cart</button>';
			expect( getProductId() ).toBe( '42' );
		} );

		it( 'returns product ID from new block hidden input', () => {
			document.body.innerHTML =
				'<div class="wp-block-add-to-cart-with-options"><input type="hidden" name="add-to-cart" value="99" /></div>';
			expect( getProductId() ).toBe( '99' );
		} );

		it( 'returns undefined when neither element exists', () => {
			expect( getProductId() ).toBeUndefined();
		} );

		it( 'does not match add-to-cart input outside the new block', () => {
			document.body.innerHTML =
				'<input type="hidden" name="add-to-cart" value="77" />';
			expect( getProductId() ).toBeUndefined();
		} );
	} );

	describe( 'getProductForm', () => {
		it( 'returns the classic form.cart', () => {
			document.body.innerHTML =
				'<form class="cart"><input type="hidden" /></form>';
			expect( getProductForm() ).toBe(
				document.querySelector( 'form.cart' )
			);
		} );

		it( 'returns the new block form', () => {
			document.body.innerHTML =
				'<form class="wp-block-add-to-cart-with-options"><input type="hidden" /></form>';
			expect( getProductForm() ).toBe(
				document.querySelector(
					'form.wp-block-add-to-cart-with-options'
				)
			);
		} );

		it( 'returns null when neither form exists', () => {
			expect( getProductForm() ).toBeNull();
		} );
	} );

	describe( 'getQuantity', () => {
		it( 'returns the quantity input value', () => {
			document.body.innerHTML =
				'<div class="quantity"><input class="qty" value="3" /></div>';
			expect( getQuantity() ).toBe( 3 );
		} );

		it( 'defaults to 1 when no quantity input exists', () => {
			expect( getQuantity() ).toBe( 1 );
		} );

		it( 'defaults to 1 when quantity input has invalid value', () => {
			document.body.innerHTML =
				'<div class="quantity"><input class="qty" value="" /></div>';
			expect( getQuantity() ).toBe( 1 );
		} );
	} );
} );
