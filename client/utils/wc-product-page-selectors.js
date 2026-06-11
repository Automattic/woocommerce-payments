/**
 * Product page DOM selectors with fallbacks for both the classic Add to Cart
 * block (.wp-block-add-to-cart-form / form.cart) and the new Add to Cart +
 * Options block (.wp-block-add-to-cart-with-options).
 *
 * The new block does NOT add `class="cart"` to its <form> when running in
 * Interactivity API mode, and its submit button does NOT have the
 * `single_add_to_cart_button` class. These helpers abstract the differences.
 */

/**
 * Get the add-to-cart button element.
 *
 * Classic block / shortcode: .single_add_to_cart_button
 * Add to Cart + Options block: button[type="submit"] inside the block form
 *
 * @return {HTMLElement|null} The add-to-cart button, or null.
 */
export const getAddToCartButtonElement = () => {
	return (
		document.querySelector( '.single_add_to_cart_button' ) ||
		document.querySelector(
			'.wp-block-add-to-cart-with-options button[type="submit"]'
		)
	);
};

/**
 * Get the product ID from the add-to-cart form.
 *
 * Classic block: .single_add_to_cart_button value attribute
 * Add to Cart + Options block: hidden input[name="add-to-cart"] value
 *
 * @return {string|undefined} The product ID, or undefined.
 */
export const getProductId = () => {
	const classicButton = document.querySelector(
		'.single_add_to_cart_button'
	);
	if ( classicButton ) {
		return classicButton.value;
	}

	const hiddenInput = document.querySelector(
		'.wp-block-add-to-cart-with-options input[name="add-to-cart"]'
	);
	return hiddenInput?.value;
};

/**
 * Get the quantity input value.
 *
 * @return {number} Quantity (defaults to 1).
 */
export const getQuantity = () => {
	const qty = document.querySelector( '.quantity .qty' );
	return qty ? parseInt( qty.value, 10 ) || 1 : 1;
};

/**
 * Detect whether the page is using the new Add to Cart + Options block
 * (Interactivity API mode) instead of the legacy shortcode form.
 *
 * @return {boolean} True when the IAPI block form is present.
 */
export const isIAPIBlock = () => {
	return !! document.querySelector( '.wp-block-add-to-cart-with-options' );
};

/**
 * Get the resolved variation ID from the IAPI block's hidden input.
 *
 * The new block binds the variation ID onto `<input name="variation_id">`
 * inside `.single_variation_wrap`. A non-empty, non-zero value means a
 * variation has been fully resolved.
 *
 * The hidden input is the only stable surface to read this from: the binding
 * target behind it has changed across WooCommerce releases (the private
 * `woocommerce/product-data` store became `woocommerce/products`, and the
 * state path changed too), but the rendered `input[name="variation_id"]` has
 * stayed the same. Reading the DOM avoids coupling to that locked, renamed
 * store — and is the interim integration the block maintainers themselves
 * recommend for express payment methods (WOOPLUG-4625, where the equivalent
 * back-compat request for variable products was declined).
 *
 * @return {number|null} The variation ID, or null if not resolved.
 */
export const getIAPIVariationId = () => {
	const input = document.querySelector(
		'.wp-block-add-to-cart-with-options input[name="variation_id"]'
	);

	const value = parseInt( input?.value, 10 );
	return value > 0 ? value : null;
};

/**
 * Read the shopper's selected variation attributes from the IAPI block.
 *
 * Sending the resolved variation ID alone is not enough: when the matched
 * variation is "Any" on an attribute, the Store API has no value to fill in
 * and rejects the request (`woocommerce_rest_missing_variation_data`) until
 * the shopper's chosen value is posted. So we always send the selected
 * attributes alongside the variation ID — exactly what the block's own
 * `addToCart` action does (`id: variation, variation: selectedAttributes`).
 *
 * The block holds the selection in its (locked, private) Interactivity store
 * and renders no `attribute_*` form inputs, so the only available source is
 * the rendered selectors: each attribute group is a
 * `…-variation-selector-attribute` element whose `data-wp-context` carries the
 * attribute `name`; the chosen value lives on the checked pill's `value`
 * attribute (pills) or the native `<select>` (dropdown).
 *
 * @return {Array<{attribute: string, value: string}>} Selected attribute pairs.
 */
export const getIAPIVariationAttributes = () => {
	const attributes = [];

	document
		.querySelectorAll(
			'.wp-block-add-to-cart-with-options .wp-block-woocommerce-add-to-cart-with-options-variation-selector-attribute'
		)
		.forEach( ( group ) => {
			let name;
			try {
				name = JSON.parse( group.dataset.wpContext )?.name;
			} catch ( e ) {
				name = undefined;
			}
			if ( ! name ) {
				return;
			}

			const select = group.querySelector( 'select' );
			const value = select
				? select.value
				: group.querySelector( '[aria-checked="true"]' )?.value;

			if ( value ) {
				attributes.push( { attribute: name, value } );
			}
		} );

	return attributes;
};

/**
 * Whether the IAPI block currently can't be added to cart.
 *
 * The block toggles the `is-invalid` class on its form via
 * `data-wp-class--is-invalid="!state.isFormValid"`, so the class reflects the
 * block's own validation verdict — a variable product with no resolved
 * variation, an out-of-stock combination, or an invalid quantity. Simple
 * products keep a valid form, so they're never reported as invalid here.
 *
 * @return {boolean} True when the IAPI block form is in an invalid state.
 */
export const isIAPIFormInvalid = () => {
	const form = document.querySelector( '.wp-block-add-to-cart-with-options' );
	return !! form && form.classList.contains( 'is-invalid' );
};
