/* eslint-disable jsx-a11y/accessible-emoji */

/**
 * External dependencies
 */
import { applyFilters } from '@wordpress/hooks';
import { render } from '@testing-library/react';
import $ from 'jquery';

/**
 * Internal dependencies
 */
import '../wc-product-page';

const ATTR_CLASS =
	'wp-block-woocommerce-add-to-cart-with-options-variation-selector-attribute';

// A rendered IAPI variation attribute group with a single pill option.
const iapiAttribute = ( name, value, checked = true ) =>
	`<div class="${ ATTR_CLASS }" data-wp-context='${ JSON.stringify( {
		name,
	} ) }'>` +
	`<button role="radio" value="${ value }" aria-checked="${ checked }">${ value }</button>` +
	`</div>`;

// The same group rendered as a dropdown (the other option style).
const iapiDropdownAttribute = ( name, value ) =>
	`<div class="${ ATTR_CLASS }" data-wp-context='${ JSON.stringify( {
		name,
	} ) }'>` +
	`<select><option value="">Choose</option><option value="${ value }" selected>${ value }</option></select>` +
	`</div>`;

describe( 'ECE product page compatibility', () => {
	afterEach( () => {
		document.body.innerHTML = '';
	} );

	describe( 'Classic shortcode form', () => {
		it( 'returns the variation data', () => {
			render(
				<form className="variations_form">
					<table className="variations" role="presentation">
						<tbody>
							<tr>
								<th className="label">
									<label htmlFor="pa_color">Color</label>
								</th>
								<td className="value">
									<select
										id="pa_color"
										name="attribute_pa_color"
										data-attribute_name="attribute_pa_color"
										defaultValue="red"
									>
										<option value="">
											Choose an option
										</option>
										<option value="blue">Blue</option>
										<option value="green">Green</option>
										<option value="red">Red</option>
									</select>
								</td>
							</tr>
							<tr>
								<th className="label">
									<label htmlFor="logo">Logo</label>
								</th>
								<td className="value">
									<select
										id="logo"
										name="attribute_logo"
										data-attribute_name="attribute_logo"
										defaultValue="Yes"
									>
										<option value="">
											Choose an option
										</option>
										<option value="Yes">Yes</option>
										<option value="No">No</option>
									</select>
								</td>
							</tr>
						</tbody>
					</table>
					<div className="single_variation_wrap">
						<input type="hidden" name="product_id" value="10" />
					</div>
				</form>
			);
			const productData = applyFilters(
				'wcpay.express-checkout.cart-add-item',
				{
					variation: [],
				}
			);

			expect( productData ).toStrictEqual( {
				id: 10,
				variation: [
					{
						attribute: 'Color',
						value: 'red',
					},
					{
						attribute: 'attribute_pa_color',
						value: 'red',
					},
					{
						attribute: 'Logo',
						value: 'Yes',
					},
					{
						attribute: 'attribute_logo',
						value: 'Yes',
					},
				],
			} );
		} );

		it( 'ensures compatibility with plugins modifying the DOM with additional markup', () => {
			// this markup is simulating the output of the "woo-variation-swatches" plugin.
			render(
				<form className="variations_form">
					<table className="variations">
						<tbody>
							<tr>
								<th className="label">
									<label htmlFor="size%f0%9f%98%86">
										Size😆
										<span className="cfvsw-selected-label">
											Medium
										</span>
									</label>
								</th>
								<td className="value woo-variation-items-wrapper">
									<select
										id="size%f0%9f%98%86"
										name="attribute_size%f0%9f%98%86"
										data-attribute_name="attribute_size%f0%9f%98%86"
										defaultValue="Medium"
									>
										<option value="">
											Choose an option
										</option>
										<option value="Small">Small</option>
										<option value="Medium">Medium</option>
									</select>
								</td>
							</tr>
							<tr>
								<th className="label">
									<label htmlFor="color-%e2%9c%8f%ef%b8%8f">
										Color ✏️
									</label>
									<span>: Blue</span>
								</th>
								<td className="value woo-variation-items-wrapper">
									<select
										id="color-%e2%9c%8f%ef%b8%8f"
										name="attribute_color-%e2%9c%8f%ef%b8%8f"
										data-attribute_name="attribute_color-%e2%9c%8f%ef%b8%8f"
										defaultValue="Green"
									>
										<option value="">
											Choose an option
										</option>
										<option value="Blue">Blue</option>
										<option value="Green">Green</option>
									</select>
								</td>
							</tr>
							<tr>
								<th className="label">
									<label htmlFor="autograph-choice-%e2%9c%8f%ef%b8%8f">
										Autograph choice ✏️
									</label>
									<span>: Yes 👍</span>
								</th>
								<td className="value woo-variation-items-wrapper">
									<select
										id="autograph-choice-%e2%9c%8f%ef%b8%8f"
										name="attribute_autograph-choice-%e2%9c%8f%ef%b8%8f"
										data-attribute_name="attribute_autograph-choice-%e2%9c%8f%ef%b8%8f"
										defaultValue="Yes 👍"
									>
										<option value="">
											Choose an option
										</option>
										<option value="Yes 👍">Yes 👍</option>
										<option value="No 👎">No 👎</option>
									</select>
								</td>
							</tr>
						</tbody>
					</table>
					<div className="single_variation_wrap">
						<input type="hidden" name="product_id" value="10" />
					</div>
				</form>
			);
			const productData = applyFilters(
				'wcpay.express-checkout.cart-add-item',
				{
					variation: [],
				}
			);

			expect( productData ).toStrictEqual( {
				id: 10,
				variation: [
					{
						attribute: 'Size😆',
						value: 'Medium',
					},
					{
						attribute: 'attribute_size%f0%9f%98%86',
						value: 'Medium',
					},
					{
						attribute: 'Color ✏️',
						value: 'Green',
					},
					{
						attribute: 'attribute_color-%e2%9c%8f%ef%b8%8f',
						value: 'Green',
					},
					{
						attribute: 'Autograph choice ✏️',
						value: 'Yes 👍',
					},
					{
						attribute:
							'attribute_autograph-choice-%e2%9c%8f%ef%b8%8f',
						value: 'Yes 👍',
					},
				],
			} );
		} );
	} );

	describe( 'IAPI Add to Cart + Options block', () => {
		it( 'sends the parent product ID with the selected attributes', () => {
			// The Store API resolves the variation from the parent ID plus the
			// selected attributes — which must be sent even when a variation is
			// resolved, since "Any"-valued attributes carry no value on it.
			document.body.innerHTML = [
				'<form class="wp-block-add-to-cart-with-options wc-block-add-to-cart-with-options cart">',
				iapiAttribute( 'Flavor', 'orange-flavor' ),
				iapiAttribute( 'Size', 'medium' ),
				'  <div class="single_variation_wrap">',
				'    <input type="hidden" name="add-to-cart" value="257" />',
				'    <input type="hidden" name="product_id" value="257" />',
				'    <input type="hidden" name="variation_id" value="263" />',
				'  </div>',
				'</form>',
			].join( '' );

			const productData = applyFilters(
				'wcpay.express-checkout.cart-add-item',
				{
					variation: [],
				}
			);

			expect( productData ).toStrictEqual( {
				id: 257,
				variation: [
					{ attribute: 'Flavor', value: 'orange-flavor' },
					{ attribute: 'Size', value: 'medium' },
				],
			} );
		} );

		it( 'reads attributes from the dropdown option style too', () => {
			document.body.innerHTML = [
				'<form class="wp-block-add-to-cart-with-options wc-block-add-to-cart-with-options cart">',
				iapiDropdownAttribute( 'Flavor', 'orange-flavor' ),
				'  <div class="single_variation_wrap">',
				'    <input type="hidden" name="product_id" value="257" />',
				'    <input type="hidden" name="variation_id" value="263" />',
				'  </div>',
				'</form>',
			].join( '' );

			const productData = applyFilters(
				'wcpay.express-checkout.cart-add-item',
				{
					variation: [],
				}
			);

			expect( productData ).toStrictEqual( {
				id: 257,
				variation: [ { attribute: 'Flavor', value: 'orange-flavor' } ],
			} );
		} );

		it( 'sends no attributes when no variation is selected', () => {
			document.body.innerHTML = [
				'<form class="wp-block-add-to-cart-with-options wc-block-add-to-cart-with-options cart">',
				iapiAttribute( 'Flavor', 'orange-flavor', false ),
				'  <div class="single_variation_wrap">',
				'    <input type="hidden" name="add-to-cart" value="257" />',
				'    <input type="hidden" name="product_id" value="257" />',
				'    <input type="hidden" name="variation_id" value="" />',
				'  </div>',
				'</form>',
			].join( '' );

			const productData = applyFilters(
				'wcpay.express-checkout.cart-add-item',
				{
					variation: [],
				}
			);

			// The click handler blocks this case with a "Please select" alert
			// before it reaches the Store API.
			expect( productData ).toStrictEqual( {
				id: 257,
				variation: [],
			} );
		} );

		it( 'reads attributes from the block, not legacy variation selectors', () => {
			document.body.innerHTML = [
				'<form class="wp-block-add-to-cart-with-options variations_form">',
				'  <table class="variations"><tbody><tr>',
				'    <th class="label"><label for="pa_color">Color</label></th>',
				'    <td><select id="pa_color" data-attribute_name="attribute_pa_color"><option value="red">Red</option></select></td>',
				'  </tr></tbody></table>',
				iapiAttribute( 'Color', 'red' ),
				'  <div class="single_variation_wrap">',
				'    <input type="hidden" name="product_id" value="100" />',
				'    <input type="hidden" name="variation_id" value="105" />',
				'  </div>',
				'</form>',
			].join( '' );

			const productData = applyFilters(
				'wcpay.express-checkout.cart-add-item',
				{
					variation: [],
				}
			);

			expect( productData ).toStrictEqual( {
				id: 100,
				variation: [ { attribute: 'Color', value: 'red' } ],
			} );
		} );
	} );

	describe( 'Classic variation refresh', () => {
		let refreshMock, buttonUi;

		beforeEach( async () => {
			global.$ = global.jQuery = $;
			// Invoke document.ready callbacks immediately.
			$.fn.ready = ( callback ) => callback( $ );
			global.wcpayExpressCheckoutParams = { button_context: 'product' };

			// The listeners must be attached by, and dispatch to, the same
			// module registry the test spies on.
			await jest.isolateModulesAsync( async () => {
				( { default: buttonUi } = await import( '../../button-ui' ) );
				const { addAction } = await import( '@wordpress/hooks' );
				refreshMock = jest.fn();
				addAction(
					'wcpay.express-checkout.update-button-data',
					'test',
					refreshMock
				);
				jest.spyOn( buttonUi, 'blockButton' ).mockImplementation();
				jest.spyOn( buttonUi, 'unblock' ).mockImplementation();
				await import( '../wc-product-page' );
			} );
		} );

		afterEach( () => {
			$( document.body ).off();
			global.jQuery = jest.fn();
			delete global.$;
			delete global.wcpayExpressCheckoutParams;
		} );

		// The listener defers to a microtask, so let it run.
		const settle = () => Promise.resolve();

		it( 'refreshes once WooCommerce shows the picked variation, not on the change itself', async () => {
			// WooCommerce 9.8+ order: the add to cart button only changes state
			// on `show_variation`, 300ms after the change events.
			$( document.body ).trigger( 'found_variation' );
			$( document.body ).trigger( 'woocommerce_variation_has_changed' );
			await settle();

			expect( buttonUi.blockButton ).toHaveBeenCalled();
			expect( refreshMock ).not.toHaveBeenCalled();

			$( document.body ).trigger( 'show_variation' );
			await settle();

			expect( refreshMock ).toHaveBeenCalledTimes( 1 );
		} );

		it( 'refreshes after the whole change when WooCommerce shows the variation synchronously', async () => {
			// Before WooCommerce 9.8, `show_variation` is triggered inside the
			// `found_variation` handler, so it reaches the body first.
			$( document.body ).trigger( 'show_variation' );
			$( document.body ).trigger( 'found_variation' );
			$( document.body ).trigger( 'woocommerce_variation_has_changed' );
			await settle();

			expect( refreshMock ).toHaveBeenCalledTimes( 1 );
			// The refresh lifts the block, or skips the fetch and lifts it for an
			// unavailable variation, so it must come after the block.
			expect(
				buttonUi.blockButton.mock.invocationCallOrder[ 0 ]
			).toBeLessThan( refreshMock.mock.invocationCallOrder[ 0 ] );
		} );

		it( 'refreshes when clearing the selection hides the variation', async () => {
			$( document.body ).trigger( 'woocommerce_variation_has_changed' );
			$( document.body ).trigger( 'show_variation' );
			await settle();
			refreshMock.mockClear();

			// A cleared selection resolves synchronously, so WooCommerce hides
			// the variation before it reports the change.
			$( document.body ).trigger( 'hide_variation' );
			await settle();

			expect( refreshMock ).toHaveBeenCalledTimes( 1 );
		} );

		it( 'only lifts the block for the selection WooCommerce replays on page load', async () => {
			// Pre-9.8 order for a default variation: the block lands last.
			$( document.body ).trigger( 'hide_variation' );
			$( document.body ).trigger( 'show_variation' );
			$( document.body ).trigger( 'found_variation' );
			await settle();

			expect( refreshMock ).not.toHaveBeenCalled();
			expect( buttonUi.unblock ).toHaveBeenCalledTimes( 2 );
			expect(
				buttonUi.blockButton.mock.invocationCallOrder[ 0 ]
			).toBeLessThan( buttonUi.unblock.mock.invocationCallOrder[ 1 ] );
		} );
	} );
} );
