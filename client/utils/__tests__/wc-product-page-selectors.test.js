/**
 * Internal dependencies
 */
import {
	getAddToCartButtonElement,
	getProductId,
	getQuantity,
	isIAPIBlock,
	getIAPIVariationId,
	getIAPIVariationAttributes,
	getClassicVariationAttributes,
	isAddToCartBlocked,
	isVariationUnavailable,
} from '../wc-product-page-selectors';

// Mirrors the rendered IAPI variation selectors: one attribute group per
// `…-variation-selector-attribute` element, with the attribute name in
// `data-wp-context` and the chosen value on the checked pill / the <select>.
const attributeGroup = ( name, optionsHtml ) =>
	`<div class="wp-block-woocommerce-add-to-cart-with-options-variation-selector-attribute" data-wp-context='${ JSON.stringify(
		{ name }
	) }'>${ optionsHtml }</div>`;

const pill = ( value, checked ) =>
	`<button role="radio" value="${ value }" aria-checked="${
		checked ? 'true' : 'false'
	}">${ value }</button>`;

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

	describe( 'getIAPIVariationId', () => {
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

	describe( 'getIAPIVariationAttributes', () => {
		it( 'reads the checked pill value from each attribute group', () => {
			document.body.innerHTML = [
				'<form class="wp-block-add-to-cart-with-options">',
				attributeGroup(
					'Flavor',
					pill( 'orange-flavor', true ) + pill( 'unflavored', false )
				),
				attributeGroup(
					'Size',
					pill( 'medium', true ) + pill( 'small', false )
				),
				'</form>',
			].join( '' );

			expect( getIAPIVariationAttributes() ).toEqual( [
				{ attribute: 'Flavor', value: 'orange-flavor' },
				{ attribute: 'Size', value: 'medium' },
			] );
		} );

		it( 'reads the value from a dropdown (select) attribute group', () => {
			document.body.innerHTML = [
				'<form class="wp-block-add-to-cart-with-options">',
				attributeGroup(
					'Flavor',
					'<select><option value="">Choose</option><option value="orange-flavor" selected>Orange</option></select>'
				),
				'</form>',
			].join( '' );

			expect( getIAPIVariationAttributes() ).toEqual( [
				{ attribute: 'Flavor', value: 'orange-flavor' },
			] );
		} );

		it( 'skips groups with no selection', () => {
			document.body.innerHTML = [
				'<form class="wp-block-add-to-cart-with-options">',
				attributeGroup(
					'Flavor',
					pill( 'orange-flavor', true ) + pill( 'unflavored', false )
				),
				attributeGroup(
					'Size',
					pill( 'medium', false ) + pill( 'small', false )
				),
				'</form>',
			].join( '' );

			expect( getIAPIVariationAttributes() ).toEqual( [
				{ attribute: 'Flavor', value: 'orange-flavor' },
			] );
		} );

		it( 'returns an empty array when there are no attribute groups', () => {
			document.body.innerHTML =
				'<form class="wp-block-add-to-cart-with-options"></form>';
			expect( getIAPIVariationAttributes() ).toEqual( [] );
		} );

		it( 'reads label-valued custom attributes verbatim', () => {
			// Custom (non-taxonomy) attributes use the label as the value,
			// special characters and all — these must round-trip unchanged.
			document.body.innerHTML = [
				'<form class="wp-block-add-to-cart-with-options">',
				attributeGroup(
					'棒球',
					pill( 'Baseball ⚾️', true ) + pill( 'Soccer ⚽️', false )
				),
				'</form>',
			].join( '' );

			expect( getIAPIVariationAttributes() ).toEqual( [
				{ attribute: '棒球', value: 'Baseball ⚾️' },
			] );
		} );
	} );

	describe( 'getClassicVariationAttributes', () => {
		it( 'reads label and slug pairs from the classic variations form', () => {
			document.body.innerHTML = [
				'<form class="variations_form">',
				'  <table class="variations"><tbody>',
				'    <tr><th><label for="pa_color">Color</label></th>',
				'    <td><select data-attribute_name="attribute_pa_color">',
				'      <option value="blue" selected>Blue</option>',
				'    </select></td></tr>',
				'  </tbody></table>',
				'</form>',
			].join( '' );

			expect( getClassicVariationAttributes() ).toEqual( [
				{ attribute: 'Color', value: 'blue' },
				{ attribute: 'attribute_pa_color', value: 'blue' },
			] );
		} );

		it( 'returns an empty array when there is no classic form', () => {
			document.body.innerHTML =
				'<form class="wp-block-add-to-cart-with-options"></form>';
			expect( getClassicVariationAttributes() ).toEqual( [] );
		} );
	} );

	describe( 'isAddToCartBlocked', () => {
		it( 'is true when the IAPI block form is invalid', () => {
			document.body.innerHTML =
				'<form class="wp-block-add-to-cart-with-options is-invalid"></form>';
			expect( isAddToCartBlocked() ).toBe( true );
		} );

		it( 'is false when the IAPI block form is valid', () => {
			document.body.innerHTML =
				'<form class="wp-block-add-to-cart-with-options"></form>';
			expect( isAddToCartBlocked() ).toBe( false );
		} );

		it( 'is true when the classic add-to-cart button is disabled', () => {
			document.body.innerHTML =
				'<button class="single_add_to_cart_button disabled">Add</button>';
			expect( isAddToCartBlocked() ).toBe( true );
		} );

		it( 'is false when the classic add-to-cart button is enabled', () => {
			document.body.innerHTML =
				'<button class="single_add_to_cart_button">Add</button>';
			expect( isAddToCartBlocked() ).toBe( false );
		} );
	} );

	describe( 'isVariationUnavailable', () => {
		it( 'is true for an unavailable classic combination', () => {
			document.body.innerHTML =
				'<button class="single_add_to_cart_button disabled wc-variation-is-unavailable">Add</button>';
			expect( isVariationUnavailable() ).toBe( true );
		} );

		it( 'is false for a classic button that is merely disabled', () => {
			document.body.innerHTML =
				'<button class="single_add_to_cart_button disabled">Add</button>';
			expect( isVariationUnavailable() ).toBe( false );
		} );

		it( 'is always false for the IAPI block (no unavailable sub-state)', () => {
			document.body.innerHTML =
				'<form class="wp-block-add-to-cart-with-options is-invalid"></form>';
			expect( isVariationUnavailable() ).toBe( false );
		} );
	} );
} );
