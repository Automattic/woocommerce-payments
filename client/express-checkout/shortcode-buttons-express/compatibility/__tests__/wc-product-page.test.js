/* eslint-disable jsx-a11y/accessible-emoji */

/**
 * External dependencies
 */
import { applyFilters } from '@wordpress/hooks';
import { render } from '@testing-library/react';

/**
 * Internal dependencies
 */
import '../wc-product-page';

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
		it( 'uses the resolved variation ID directly when a variation is selected', () => {
			// Simulate the IAPI block DOM as documented in the issue:
			// The block wraps the form with .wp-block-add-to-cart-with-options
			// and resolves variation_id via data-wp-bind--value.
			document.body.innerHTML = [
				'<form class="wp-block-add-to-cart-with-options wc-block-add-to-cart-with-options cart">',
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

			// When the IAPI block is present and variation_id is resolved,
			// the filter should send the variation ID as `id` and clear `variation`.
			expect( productData ).toStrictEqual( {
				id: 263,
				variation: [],
			} );
		} );

		it( 'falls back to the parent product ID when no variation is selected', () => {
			document.body.innerHTML = [
				'<form class="wp-block-add-to-cart-with-options wc-block-add-to-cart-with-options cart">',
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

			// No variation resolved — falls back to parent product_id.
			// The click handler will block this with a "Please select" alert.
			expect( productData ).toStrictEqual( {
				id: 257,
				variation: [],
			} );
		} );

		it( 'does not attempt to read legacy variation selectors', () => {
			// Even if legacy-style elements somehow exist alongside the block,
			// the IAPI path should be used when the block class is present.
			document.body.innerHTML = [
				'<form class="wp-block-add-to-cart-with-options variations_form">',
				'  <table class="variations"><tbody><tr>',
				'    <th class="label"><label for="pa_color">Color</label></th>',
				'    <td><select id="pa_color" data-attribute_name="attribute_pa_color"><option value="red">Red</option></select></td>',
				'  </tr></tbody></table>',
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

			// Should use the IAPI path (variation_id = 105), NOT the legacy
			// attribute-parsing path.
			expect( productData ).toStrictEqual( {
				id: 105,
				variation: [],
			} );
		} );
	} );
} );
