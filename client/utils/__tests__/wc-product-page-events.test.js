/**
 * External dependencies
 */
import $ from 'jquery';

/**
 * Internal dependencies
 */
import { onProductAvailabilityChange } from '../wc-product-page-events';

// Make debounce synchronous so quantity-input assertions are deterministic.
jest.mock( 'lodash', () => ( {
	...jest.requireActual( 'lodash' ),
	debounce: ( fn ) => fn,
} ) );

describe( 'onProductAvailabilityChange', () => {
	beforeEach( () => {
		document.body.innerHTML = '';
		global.$ = global.jQuery = $;
	} );

	it( 'fires the callback on a classic variation change', () => {
		const cb = jest.fn();
		onProductAvailabilityChange( cb );

		$( document.body ).trigger( 'woocommerce_variation_has_changed' );

		expect( cb ).toHaveBeenCalledTimes( 1 );
	} );

	it( 'fires the callback when the quantity input changes', () => {
		document.body.innerHTML =
			'<div class="quantity"><input class="qty" value="1" /></div>';
		const cb = jest.fn();
		onProductAvailabilityChange( cb );

		$( '.quantity .qty' ).trigger( 'input' );

		expect( cb ).toHaveBeenCalled();
	} );

	it( 'stops firing after the returned unsubscribe is called', () => {
		const cb = jest.fn();
		const unsubscribe = onProductAvailabilityChange( cb );

		unsubscribe();
		$( document.body ).trigger( 'woocommerce_variation_has_changed' );

		expect( cb ).not.toHaveBeenCalled();
	} );
} );
