export const getAppearanceType = () => {
	if ( document.querySelector( '.wp-block-woocommerce-checkout' ) ) {
		return 'blocks_checkout';
	}

	if ( document.querySelector( '.woocommerce-billing-fields' ) ) {
		return 'woopay_shortcode_checkout';
	}

	if ( document.querySelector( '.wp-block-woocommerce-cart' ) ) {
		return 'bnpl_cart_block';
	}

	if ( document.querySelector( '.woocommerce-cart-form' ) ) {
		return 'bnpl_classic_cart';
	}

	if ( document.querySelector( '.single-product' ) ) {
		return 'bnpl_product_page';
	}
};
