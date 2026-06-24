/* global jQuery */
/**
 * Internal dependencies
 */
import expressCheckoutButtonUi from '../button-ui';
import debounce from '../debounce';

/**
 * External dependencies
 */
import { addFilter, doAction } from '@wordpress/hooks';
import { getExpressCheckoutData } from 'wcpay/express-checkout/utils';
import {
	isIAPIBlock,
	getIAPIVariationAttributes,
	getClassicVariationAttributes,
} from 'wcpay/utils/wc-product-page-selectors';
import { onProductAvailabilityChange } from 'wcpay/utils/wc-product-page-events';

jQuery( () => {
	// The shared availability watcher fires more than once for a single variation
	// change: the WooCommerce change event lands immediately, then a DOM
	// MutationObserver fires again once the add-to-cart control's enabled/disabled
	// state settles a tick later. Each call re-fetches the variation's totals and
	// re-renders the express button, so without debouncing one selection triggers
	// several redundant refetches behind a single (deduped) loading overlay.
	// Coalesce them into one update per change.
	const triggerButtonDataUpdate = debounce( 100, () => {
		doAction( 'wcpay.express-checkout.update-button-data' );
	} );
	onProductAvailabilityChange( triggerButtonDataUpdate );
} );

// Block the payment request button as soon as an "input" event is fired, to avoid sync issues
// when the customer clicks on the button before the debounced event is processed.
jQuery( ( $ ) => {
	if ( getExpressCheckoutData( 'button_context' ) !== 'product' ) {
		return;
	}

	const $quantityInput = $( '.quantity' );
	const handleQuantityChange = () => {
		expressCheckoutButtonUi.blockButton();
	};
	$quantityInput.on( 'input', '.qty', handleQuantityChange );
	$quantityInput.on(
		'input',
		'.qty',
		debounce( 250, async () => {
			doAction( 'wcpay.express-checkout.update-button-data' );
		} )
	);
} );

/**
 * Resolve a variable product to its variation for the cart-add-item request.
 *
 * Both the classic form and the IAPI block expose the parent product ID via
 * `.single_variation_wrap input[name="product_id"]` and hold the selection in
 * their respective DOM. We send the parent ID plus the selected attributes;
 * the Store API resolves the concrete variation. The attributes are required
 * even when a variation is resolved, because "Any"-valued attributes carry no
 * value on the variation and the Store API rejects the request without them.
 */
addFilter(
	'wcpay.express-checkout.cart-add-item',
	'automattic/wcpay/express-checkout',
	( productData ) => {
		const result = { ...productData };

		const productIdInput = document.querySelector(
			'.single_variation_wrap input[name="product_id"]'
		);
		if ( productIdInput ) {
			result.id = parseInt( productIdInput.value, 10 );
		}

		const attributes = isIAPIBlock()
			? getIAPIVariationAttributes()
			: getClassicVariationAttributes();
		if ( attributes.length ) {
			result.variation = [ ...productData.variation, ...attributes ];
		}

		return result;
	}
);
