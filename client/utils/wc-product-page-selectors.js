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
 * Used by the WooPay button, which passes the resolved variation ID to the WC
 * AJAX add-to-cart endpoint. The ECE/Store API path resolves the variation
 * differently — from the parent ID plus the posted attributes
 * (`getIAPIVariationAttributes`) — and doesn't use this.
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
 * The IAPI block's variation attribute groups (one per attribute).
 *
 * @return {NodeList} The variation-selector-attribute elements.
 */
export const getIAPIVariationSelectorGroups = () =>
	document.querySelectorAll(
		'.wp-block-add-to-cart-with-options .wp-block-woocommerce-add-to-cart-with-options-variation-selector-attribute'
	);

/**
 * Read the shopper's selected variation attributes from the IAPI block.
 *
 * The product ID alone is not enough: when the matched variation is "Any" on
 * an attribute, that attribute has no value on the variation, so the Store API
 * rejects the request (`woocommerce_rest_missing_variation_data`) until the
 * shopper's chosen value is posted. Posting the selected attributes lets the
 * Store API resolve the variation and record the choice — the same data the
 * block's own `addToCart` action sends.
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

	getIAPIVariationSelectorGroups().forEach( ( group ) => {
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
 * Read the shopper's selected variation attributes from the classic form.
 *
 * @return {Array<{attribute: string, value: string}>} Selected attribute pairs.
 */
export const getClassicVariationAttributes = () => {
	const variationsForm = document.querySelector( '.variations_form' );
	if ( ! variationsForm ) {
		return [];
	}

	const attributes = [];
	variationsForm
		.querySelectorAll( '.variations select' )
		.forEach( ( select ) => {
			const attributeName =
				select.dataset.attribute_name || select.dataset.name;
			if ( ! attributeName ) {
				return;
			}

			// The Store API matches on the attribute's registered key, which
			// differs between WooCommerce versions: older ones expect the
			// human label, newer ones the `attribute_*` slug. We send both so
			// the backend resolves one of them — the label form is fragile when
			// labels contain special characters.
			// The Store API accepts the variable attribute's label, rather than an internal identifier:
			// https://github.com/woocommerce/woocommerce-blocks/blob/trunk/src/StoreApi/docs/cart.md#add-item
			// fallback until https://github.com/woocommerce/woocommerce/pull/55317 has been consolidated in WC Core.
			const label = document.querySelector(
				`label[for="${ attributeName.replace( 'attribute_', '' ) }"]`
			);
			if ( label ) {
				attributes.push( {
					attribute: Array.from( label.childNodes )[ 0 ].textContent,
					value: select.value || '',
				} );
			}

			// proper logic for https://github.com/woocommerce/woocommerce/pull/55317 .
			attributes.push( {
				attribute: attributeName,
				value: select.value || '',
			} );
		} );

	return attributes;
};

/**
 * Whether the current product can't be added to cart.
 *
 * The classic button carries a `.disabled` class; the IAPI block doesn't add
 * that class to variable products, so it signals the same state through its
 * form's `is-invalid` class (`data-wp-class--is-invalid="!state.isFormValid"`),
 * which reflects the block's own validation verdict — unselected variation,
 * out-of-stock combination, or invalid quantity. Simple products keep a valid
 * form, so they're never reported as blocked.
 *
 * @return {boolean} True when the add-to-cart action is blocked.
 */
export const isAddToCartBlocked = () => {
	if ( isIAPIBlock() ) {
		const form = document.querySelector(
			'.wp-block-add-to-cart-with-options'
		);
		return !! form && form.classList.contains( 'is-invalid' );
	}

	const button = getAddToCartButtonElement();
	return !! button && button.classList.contains( 'disabled' );
};

/**
 * Whether the blocked product is an unavailable variation combination, as
 * opposed to one the shopper simply hasn't finished selecting.
 *
 * Only the classic button distinguishes the two (via `.wc-variation-is-unavailable`);
 * the IAPI block exposes a single invalid state, so this is always false there.
 *
 * @return {boolean} True when the selected combination is unavailable.
 */
export const isVariationUnavailable = () => {
	if ( isIAPIBlock() ) {
		return false;
	}

	const button = getAddToCartButtonElement();
	return (
		!! button && button.classList.contains( 'wc-variation-is-unavailable' )
	);
};
