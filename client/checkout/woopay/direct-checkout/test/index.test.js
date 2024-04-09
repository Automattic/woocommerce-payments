/* global $ */
/**
 * External dependencies
 */
import { fireEvent } from '@testing-library/react';

/**
 * Internal dependencies
 */
import WooPayDirectCheckout from 'wcpay/checkout/woopay/direct-checkout/woopay-direct-checkout';

const wpHookCallbacks = {};

jest.mock( '@wordpress/hooks', () => ( {
	addAction: ( _hookName, _namespace, callback ) => {
		wpHookCallbacks[ _hookName ] = callback;
	},
} ) );

jest.mock(
	'wcpay/checkout/woopay/direct-checkout/woopay-direct-checkout',
	() => ( {
		isWooPayDirectCheckoutEnabled: jest.fn(),
		init: jest.fn(),
		isWooPayThirdPartyCookiesEnabled: jest.fn(),
		getCheckoutRedirectElements: jest.fn(),
		isUserLoggedIn: jest.fn(),
		maybePrefetchEncryptedSessionData: jest.fn(),
		getClassicProceedToCheckoutButton: jest.fn(),
		redirectToWooPay: jest.fn(),
		setEncryptedSessionDataAsNotPrefetched: jest.fn(),
	} )
);

let updatedCartTotalsCallback;
global.$ = jest.fn( () => ( {
	on: ( event, callback ) => {
		if ( event === 'updated_cart_totals' ) {
			updatedCartTotalsCallback = callback;
		}
	},
	trigger: ( event ) => {
		if ( event === 'updated_cart_totals' && updatedCartTotalsCallback ) {
			updatedCartTotalsCallback();
		}
	},
} ) );

require( '../index.js' );

describe( 'WooPay direct checkout window "load" event listener', () => {
	beforeEach( () => {
		jest.clearAllMocks();
	} );

	it( 'does not initialize WooPay direct checkout if not enabled', async () => {
		WooPayDirectCheckout.isWooPayDirectCheckoutEnabled.mockReturnValue(
			false
		);

		fireEvent.load( window );

		await new Promise( ( resolve ) => setImmediate( resolve ) );

		expect(
			WooPayDirectCheckout.isWooPayDirectCheckoutEnabled
		).toHaveBeenCalled();
		expect( WooPayDirectCheckout.init ).not.toHaveBeenCalled();
	} );

	it( 'calls `redirectToWooPay` method if third-party cookies are enabled and user is logged-in', async () => {
		WooPayDirectCheckout.isWooPayDirectCheckoutEnabled.mockReturnValue(
			true
		);
		WooPayDirectCheckout.isWooPayThirdPartyCookiesEnabled.mockResolvedValue(
			true
		);
		WooPayDirectCheckout.isUserLoggedIn.mockResolvedValue( true );
		WooPayDirectCheckout.getCheckoutRedirectElements.mockReturnValue( [] );

		fireEvent.load( window );

		await new Promise( ( resolve ) => setImmediate( resolve ) );

		expect( WooPayDirectCheckout.init ).toHaveBeenCalled();
		expect(
			WooPayDirectCheckout.isWooPayThirdPartyCookiesEnabled
		).toHaveBeenCalled();
		expect( WooPayDirectCheckout.isUserLoggedIn ).toHaveBeenCalled();
		expect(
			WooPayDirectCheckout.maybePrefetchEncryptedSessionData
		).toHaveBeenCalled();
		expect( WooPayDirectCheckout.redirectToWooPay ).toHaveBeenCalledWith(
			expect.any( Array ),
			true
		);
	} );

	it( 'calls `redirectToWooPay` method with "checkout_redirect" if third-party cookies are disabled', async () => {
		WooPayDirectCheckout.isWooPayDirectCheckoutEnabled.mockReturnValue(
			true
		);
		WooPayDirectCheckout.isWooPayThirdPartyCookiesEnabled.mockResolvedValue(
			false
		);
		WooPayDirectCheckout.getCheckoutRedirectElements.mockReturnValue( [] );

		fireEvent.load( window );

		await new Promise( ( resolve ) => setImmediate( resolve ) );

		expect( WooPayDirectCheckout.init ).toHaveBeenCalled();
		expect(
			WooPayDirectCheckout.isWooPayThirdPartyCookiesEnabled
		).toHaveBeenCalled();
		expect( WooPayDirectCheckout.isUserLoggedIn ).not.toHaveBeenCalled();
		expect(
			WooPayDirectCheckout.maybePrefetchEncryptedSessionData
		).not.toHaveBeenCalled();
		expect( WooPayDirectCheckout.redirectToWooPay ).toHaveBeenCalledWith(
			expect.any( Array ),
			false
		);
	} );
} );

describe( 'WooPay direct checkout "updated_cart_totals" jQuery event listener', () => {
	beforeEach( () => {
		jest.clearAllMocks();
	} );

	it( 'should not proceed if direct checkout is not enabled', async () => {
		WooPayDirectCheckout.isWooPayDirectCheckoutEnabled.mockReturnValue(
			false
		);

		fireEvent.load( window );

		await new Promise( ( resolve ) => setImmediate( resolve ) );

		$( document.body ).trigger( 'updated_cart_totals' );

		expect(
			WooPayDirectCheckout.isWooPayDirectCheckoutEnabled
		).toHaveBeenCalled();
		expect(
			WooPayDirectCheckout.getClassicProceedToCheckoutButton
		).not.toHaveBeenCalled();
	} );

	it( 'calls `redirectToWooPay` method if third-party cookies are enabled and user is logged-in', async () => {
		WooPayDirectCheckout.isWooPayDirectCheckoutEnabled.mockReturnValue(
			true
		);
		WooPayDirectCheckout.isWooPayThirdPartyCookiesEnabled.mockResolvedValue(
			true
		);
		WooPayDirectCheckout.isUserLoggedIn.mockResolvedValue( true );
		WooPayDirectCheckout.getCheckoutRedirectElements.mockReturnValue( [] );

		fireEvent.load( window );

		await new Promise( ( resolve ) => setImmediate( resolve ) );

		await $( document.body ).trigger( 'updated_cart_totals' );

		expect( WooPayDirectCheckout.init ).toHaveBeenCalled();
		expect(
			WooPayDirectCheckout.isWooPayThirdPartyCookiesEnabled
		).toHaveBeenCalled();
		expect( WooPayDirectCheckout.isUserLoggedIn ).toHaveBeenCalled();
		expect(
			WooPayDirectCheckout.maybePrefetchEncryptedSessionData
		).toHaveBeenCalled();
		expect( WooPayDirectCheckout.redirectToWooPay ).toHaveBeenCalledWith(
			expect.any( Array ),
			true
		);
	} );

	it( 'calls `redirectToWooPay` method with "checkout_redirect" if third-party cookies are disabled', async () => {
		WooPayDirectCheckout.isWooPayDirectCheckoutEnabled.mockReturnValue(
			true
		);
		WooPayDirectCheckout.isWooPayThirdPartyCookiesEnabled.mockResolvedValue(
			false
		);
		WooPayDirectCheckout.getClassicProceedToCheckoutButton.mockReturnValue(
			[]
		);

		fireEvent.load( window );

		await new Promise( ( resolve ) => setImmediate( resolve ) );

		await $( document.body ).trigger( 'updated_cart_totals' );

		expect( WooPayDirectCheckout.init ).toHaveBeenCalled();
		expect(
			WooPayDirectCheckout.isWooPayThirdPartyCookiesEnabled
		).toHaveBeenCalled();
		expect( WooPayDirectCheckout.isUserLoggedIn ).not.toHaveBeenCalled();
		expect(
			WooPayDirectCheckout.maybePrefetchEncryptedSessionData
		).not.toHaveBeenCalled();
		expect( WooPayDirectCheckout.redirectToWooPay ).toHaveBeenCalledWith(
			expect.any( Array ),
			false
		);
	} );
} );

describe( 'WooPay direct checkout cart item listeners', () => {
	beforeEach( () => {
		jest.clearAllMocks();
	} );

	it( 'should not prefetch encrypted session data on add item if third-party cookies are not enabled', async () => {
		WooPayDirectCheckout.isWooPayThirdPartyCookiesEnabled.mockResolvedValue(
			false
		);

		fireEvent.load( window );

		await new Promise( ( resolve ) => setImmediate( resolve ) );

		await wpHookCallbacks[
			'experimental__woocommerce_blocks-cart-add-item'
		]();

		expect(
			WooPayDirectCheckout.setEncryptedSessionDataAsNotPrefetched
		).toHaveBeenCalled();
		expect(
			WooPayDirectCheckout.maybePrefetchEncryptedSessionData
		).not.toHaveBeenCalled();
	} );

	it( 'should prefetch encrypted session data on add item if third-party cookies are enabled and user is logged-in', async () => {
		WooPayDirectCheckout.isWooPayThirdPartyCookiesEnabled.mockResolvedValue(
			true
		);
		WooPayDirectCheckout.isUserLoggedIn.mockResolvedValue( true );

		fireEvent.load( window );

		await new Promise( ( resolve ) => setImmediate( resolve ) );

		await wpHookCallbacks[
			'experimental__woocommerce_blocks-cart-add-item'
		]();

		expect(
			WooPayDirectCheckout.maybePrefetchEncryptedSessionData
		).toHaveBeenCalled();
		expect(
			WooPayDirectCheckout.setEncryptedSessionDataAsNotPrefetched
		).not.toHaveBeenCalled();
	} );
} );
