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
 * The new block binds the variation ID via `data-wp-bind--value` onto
 * `<input name="variation_id">` inside `.single_variation_wrap`.
 * A non-empty, non-zero value means a variation has been fully resolved.
 *
 * @return {number|null} The variation ID, or null if not resolved.
 */
export const getVariationId = () => {
	// Try the IAPI block first, then fall back to the classic hidden input.
	const input =
		document.querySelector(
			'.wp-block-add-to-cart-with-options input[name="variation_id"]'
		) || document.querySelector( 'input.variation_id' );

	const value = parseInt( input?.value, 10 );
	return value > 0 ? value : null;
};
