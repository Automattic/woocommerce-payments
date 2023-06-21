/** @format */
/**
 * External dependencies
 */
import { render, screen, act } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

/**
 * Internal dependencies
 */
import { woopayPaymentMethod } from '..';

jest.mock( 'utils/checkout', () => ( {
	getConfig: jest.fn(),
} ) );

describe( 'woopayPaymentMethod', () => {
	let nativeWindowLocation;

	const apiMock = {
		initWooPay: jest.fn(),
	};

	beforeEach( () => {
		nativeWindowLocation = Object.getOwnPropertyDescriptor(
			window,
			'location'
		);
		Object.defineProperty( window, 'location', {
			writable: true,
		} );
		apiMock.initWooPay = jest.fn();
	} );

	afterEach( () => {
		Object.defineProperty( window, 'location', nativeWindowLocation );
	} );

	test( 'creates expected payment method object', () => {
		const method = woopayPaymentMethod( apiMock );
		expect( method ).toEqual(
			expect.objectContaining( {
				name: 'woopay',
				paymentMethodId: 'woocommerce_payments',
			} )
		);
	} );

	test( 'method front-end matches expectation', () => {
		const method = woopayPaymentMethod( apiMock );
		render( method.content );
		expect(
			screen.queryByRole( 'button', {
				name: /Buy now with.*woopay/,
			} )
		).toBeInTheDocument();
	} );

	test( 'method edit mode matches expectation', () => {
		const method = woopayPaymentMethod( apiMock );

		render( method.edit );

		// No button this time but the text should still be there.
		expect(
			screen.queryByRole( 'button', {
				name: /Buy now with.*woopay/,
			} )
		).not.toBeInTheDocument();
		expect(
			screen.queryByText( /Buy now with.*woopay/ )
		).toBeInTheDocument();
	} );

	test( 'clicking checkout button calls initWooPay()', async () => {
		let resolvePromise;
		const initCheckoutPromise = new Promise( ( resolve ) => {
			resolvePromise = resolve;
		} );
		apiMock.initWooPay.mockReturnValue( initCheckoutPromise );

		const method = woopayPaymentMethod( apiMock );
		render( method.content );

		// Button should not be disabled initially.
		const buyNowButton = screen.getByRole( 'button', {
			name: /Buy now with.*woopay/,
		} );
		expect( buyNowButton ).not.toBeDisabled();

		// Let's click it. It should be disabled and initWooPay should be called.
		userEvent.click( buyNowButton );
		expect( buyNowButton ).toBeDisabled();
		expect( apiMock.initWooPay ).toHaveBeenCalledTimes( 1 );

		/*
		 * The button should be enabled again once the promise is resolved and
		 * the browser should be told to navigate to a new URL.
		 */
		resolvePromise( {
			url: 'https://example.org/',
		} );
		await act( async () => {
			await initCheckoutPromise;
		} );
		expect( buyNowButton ).not.toBeDisabled();
		expect( window.location ).toEqual( 'https://example.org/' );
	} );
} );
