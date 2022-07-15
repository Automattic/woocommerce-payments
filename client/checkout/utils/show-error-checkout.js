/* global jQuery */

// Show error notice at top of checkout form.
const showErrorCheckout = (
	errorMessage,
	isFirst = false,
	validateFields = true
) => {
	let messageWrapper = '';
	if ( errorMessage.includes( 'woocommerce-error' ) ) {
		messageWrapper = errorMessage;
	} else {
		messageWrapper =
			'<ul class="woocommerce-error" role="alert">' +
			errorMessage +
			'</ul>';
	}
	let $container = jQuery( '.woocommerce-notices-wrapper, form.checkout' );

	if ( isFirst ) {
		$container = $container.first();
	} else {
		$container = $container.last();
	}

	if ( ! $container.length ) {
		return;
	}

	// Adapted from WooCommerce core @ ea9aa8c, assets/js/frontend/checkout.js#L514-L529
	jQuery(
		'.woocommerce-NoticeGroup-checkout, .woocommerce-error, .woocommerce-message'
	).remove();
	$container.prepend(
		'<div class="woocommerce-NoticeGroup woocommerce-NoticeGroup-checkout">' +
			messageWrapper +
			'</div>'
	);
	if ( validateFields ) {
		$container
			.find( '.input-text, select, input:checkbox' )
			.trigger( 'validate' )
			.blur();
	}

	let scrollElement = jQuery( '.woocommerce-NoticeGroup-checkout' );
	if ( ! scrollElement.length ) {
		scrollElement = $container;
	}

	jQuery.scroll_to_notices( scrollElement );
	jQuery( document.body ).trigger( 'checkout_error' );
};

export default showErrorCheckout;
