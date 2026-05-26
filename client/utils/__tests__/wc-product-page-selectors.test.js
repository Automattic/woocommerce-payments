/**
 * Internal dependencies
 */
import {
	getAddToCartButtonElement,
	getProductId,
	getQuantity,
	isIAPIBlock,
	getIAPIVariationId,
} from '../wc-product-page-selectors';

describe( 'wc-product-page-selectors', () => {
	beforeEach( () => {
		document.body.innerHTML = '';
	} );

	describe( 'getAddToCartButtonElement', () => {
		it( 'returns the classic add-to-cart button', () => {
			document.body.innerHTML =
				'<button class="single_add_to_cart_button">Add to cart</button>';
			expect( getAddToCartButtonElement() ).toBe(
				document.querySelector( '.single_add_to_cart_button' )
			);
		} );

		it( 'returns the new block submit button', () => {
			document.body.innerHTML =
				'<div class="wp-block-add-to-cart-with-options"><button type="submit">Add to cart</button></div>';
			expect( getAddToCartButtonElement() ).toBe(
				document.querySelector( 'button[type="submit"]' )
			);
		} );

		it( 'prefers classic button when both exist', () => {
			document.body.innerHTML = [
				'<button class="single_add_to_cart_button" value="10">Add to cart</button>',
				'<div class="wp-block-add-to-cart-with-options"><button type="submit">Add to cart</button></div>',
			].join( '' );
			expect( getAddToCartButtonElement().classList ).toContain(
				'single_add_to_cart_button'
			);
		} );

		it( 'returns null when neither exists', () => {
			expect( getAddToCartButtonElement() ).toBeNull();
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

	describe( 'isIAPIBlock', () => {
		it( 'returns true when the IAPI block form is present', () => {
			document.body.innerHTML =
				'<form class="wp-block-add-to-cart-with-options"></form>';
			expect( isIAPIBlock() ).toBe( true );
		} );

		it( 'returns false when only the classic form is present', () => {
			document.body.innerHTML =
				'<form class="variations_form cart"></form>';
			expect( isIAPIBlock() ).toBe( false );
		} );

		it( 'returns false when no form is present', () => {
			expect( isIAPIBlock() ).toBe( false );
		} );
	} );

	describe( 'getVariationId', () => {
		it( 'returns variation ID from the IAPI block hidden input', () => {
			document.body.innerHTML = [
				'<form class="wp-block-add-to-cart-with-options">',
				'  <input type="hidden" name="variation_id" value="263" />',
				'</form>',
			].join( '' );
			expect( getIAPIVariationId() ).toBe( 263 );
		} );

		it( 'returns null when variation_id is empty', () => {
			document.body.innerHTML = [
				'<form class="wp-block-add-to-cart-with-options">',
				'  <input type="hidden" name="variation_id" value="" />',
				'</form>',
			].join( '' );
			expect( getIAPIVariationId() ).toBeNull();
		} );

		it( 'returns null when variation_id is 0', () => {
			document.body.innerHTML = [
				'<form class="wp-block-add-to-cart-with-options">',
				'  <input type="hidden" name="variation_id" value="0" />',
				'</form>',
			].join( '' );
			expect( getIAPIVariationId() ).toBeNull();
		} );

		it( 'returns null when no variation input exists', () => {
			expect( getIAPIVariationId() ).toBeNull();
		} );

		it( 'prefers the IAPI block input over the classic input', () => {
			document.body.innerHTML = [
				'<form class="wp-block-add-to-cart-with-options">',
				'  <input type="hidden" name="variation_id" value="500" />',
				'</form>',
				'<input type="hidden" class="variation_id" name="variation_id" value="42" />',
			].join( '' );
			expect( getIAPIVariationId() ).toBe( 500 );
		} );
	} );
} );
