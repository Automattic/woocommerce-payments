/** @format */
/**
 * External dependencies
 */
import { render, screen, act } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

/**
 * Internal dependencies
 */
import { platformCheckoutPaymentMethod } from '..';

jest.mock( 'utils/checkout', () => ( {
	getConfig: jest.fn(),
} ) );

describe( 'platformCheckoutPaymentMethod', () => {
	let nativeWindowLocation;

	const apiMock = {
		initPlatformCheckout: jest.fn(),
	};

	beforeEach( () => {
		nativeWindowLocation = Object.getOwnPropertyDescriptor(
			window,
			'location'
		);
		Object.defineProperty( window, 'location', {
			writable: true,
		} );
		apiMock.initPlatformCheckout = jest.fn();
	} );

	afterEach( () => {
		Object.defineProperty( window, 'location', nativeWindowLocation );
	} );

	test( 'creates expected payment method object', () => {
		const method = platformCheckoutPaymentMethod( apiMock );
		expect( method ).toEqual(
			expect.objectContaining( {
				name: 'platform_checkout',
				paymentMethodId: 'woocommerce_payments',
			} )
		);
	} );

	test( 'method front-end matches expectation', () => {
		const method = platformCheckoutPaymentMethod( apiMock );
		render( method.content );
		expect(
			screen.queryByRole( 'button', {
				name: /Buy now with.*platform checkout/,
			} )
		).toBeInTheDocument();
	} );

	test( 'method edit mode matches expectation', () => {
		const method = platformCheckoutPaymentMethod( apiMock );

		render( method.edit );

		// No button this time but the text should still be there.
		expect(
			screen.queryByRole( 'button', {
				name: /Buy now with.*platform checkout/,
			} )
		).not.toBeInTheDocument();
		expect(
			screen.queryByText( /Buy now with.*platform checkout/ )
		).toBeInTheDocument();
	} );

	test( 'clicking checkout button calls initPlatformCheckout()', async () => {
		let resolvePromise;
		const initCheckoutPromise = new Promise( ( resolve ) => {
			resolvePromise = resolve;
		} );
		apiMock.initPlatformCheckout.mockReturnValue( initCheckoutPromise );

		const method = platformCheckoutPaymentMethod( apiMock );
		render( method.content );

		// Button should not be disabled initially.
		const buyNowButton = screen.getByRole( 'button', {
			name: /Buy now with.*platform checkout/,
		} );
		expect( buyNowButton ).not.toBeDisabled();

		// Let's click it. It should be disabled and initPlatformCheckout should be called.
		userEvent.click( buyNowButton );
		expect( buyNowButton ).toBeDisabled();
		expect( apiMock.initPlatformCheckout ).toHaveBeenCalledTimes( 1 );

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
