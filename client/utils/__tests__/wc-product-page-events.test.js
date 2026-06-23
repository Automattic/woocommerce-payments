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

	it( 'fires when the classic add-to-cart button is enabled after the variation event', async () => {
		// Reproduces the timing race: WooCommerce removes the button's `disabled`
		// class a tick after `woocommerce_variation_has_changed`, without re-firing
		// it. Observing the button's class must still notify the subscriber so a
		// valid, in-stock variation reveals the express button.
		document.body.innerHTML =
			'<button class="single_add_to_cart_button disabled">Add to cart</button>';
		const cb = jest.fn();
		onProductAvailabilityChange( cb );

		document
			.querySelector( '.single_add_to_cart_button' )
			.classList.remove( 'disabled' );

		// MutationObserver delivers asynchronously.
		await new Promise( ( resolve ) => setTimeout( resolve, 0 ) );

		expect( cb ).toHaveBeenCalled();
	} );

	it( 'fires when the IAPI form validity class changes', async () => {
		document.body.innerHTML =
			'<form class="wp-block-add-to-cart-with-options is-invalid"></form>';
		const cb = jest.fn();
		onProductAvailabilityChange( cb );

		document
			.querySelector( '.wp-block-add-to-cart-with-options' )
			.classList.remove( 'is-invalid' );

		await new Promise( ( resolve ) => setTimeout( resolve, 0 ) );

		expect( cb ).toHaveBeenCalled();
	} );

	it( 'stops firing after the returned unsubscribe is called', async () => {
		document.body.innerHTML =
			'<button class="single_add_to_cart_button disabled">Add to cart</button>';
		const cb = jest.fn();
		const unsubscribe = onProductAvailabilityChange( cb );

		unsubscribe();
		$( document.body ).trigger( 'woocommerce_variation_has_changed' );
		document
			.querySelector( '.single_add_to_cart_button' )
			.classList.remove( 'disabled' );
		await new Promise( ( resolve ) => setTimeout( resolve, 0 ) );

		expect( cb ).not.toHaveBeenCalled();
	} );
} );
