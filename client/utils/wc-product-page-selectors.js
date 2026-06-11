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
