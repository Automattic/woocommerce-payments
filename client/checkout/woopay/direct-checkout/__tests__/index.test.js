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
		init: jest.fn(),
		isWooPayThirdPartyCookiesEnabled: jest.fn(),
		initPostMessageTimeout: jest.fn(),
		getCheckoutButtonElements: jest.fn(),
		isUserLoggedIn: jest.fn(),
		maybePrefetchEncryptedSessionData: jest.fn(),
		getClassicProceedToCheckoutButton: jest.fn(),
		getMiniCartProceedToCheckoutButton: jest.fn(),
		getFooterMiniCartProceedToCheckoutButton: jest.fn(),
		addRedirectToWooPayEventListener: jest.fn(),
		setEncryptedSessionDataAsNotPrefetched: jest.fn(),
		redirectElements: {
			BLOCKS_MINI_CART_PROCEED_BUTTON:
				'a.wp-block-woocommerce-mini-cart-checkout-button-block',
			BLOCKS_FOOTER_MINI_CART_PROCEED_BUTTON:
				'a.wc-block-mini-cart__footer-checkout',
		},
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
			return updatedCartTotalsCallback();
		}
	},
} ) );
global.jQuery = ( callback ) => callback( global.$ );

require( '../index.js' );

describe( 'WooPay direct checkout window "load" event listener', () => {
	beforeEach( () => {
		jest.clearAllMocks();
	} );

	it( 'calls `addRedirectToWooPayEventListener` method if third-party cookies are enabled and user is logged-in', async () => {
		WooPayDirectCheckout.isWooPayThirdPartyCookiesEnabled.mockResolvedValue(
			true
		);
		WooPayDirectCheckout.isUserLoggedIn.mockResolvedValue( true );
		WooPayDirectCheckout.getCheckoutButtonElements.mockReturnValue( [] );

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
		expect(
			WooPayDirectCheckout.addRedirectToWooPayEventListener
		).toHaveBeenCalledWith( expect.any( Array ), true );
	} );

	it( 'calls `addRedirectToWooPayEventListener` method with "checkout_redirect" if third-party cookies are disabled', async () => {
		WooPayDirectCheckout.isWooPayThirdPartyCookiesEnabled.mockResolvedValue(
			false
		);
		WooPayDirectCheckout.getCheckoutButtonElements.mockReturnValue( [] );

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
		expect(
			WooPayDirectCheckout.addRedirectToWooPayEventListener
		).toHaveBeenCalledWith( expect.any( Array ), false );
	} );
} );

describe( 'WooPay direct checkout "updated_cart_totals" jQuery event listener', () => {
	const CLASSIC_PROCEED_BUTTON = document.createElement( 'a' );

	beforeEach( () => {
		jest.clearAllMocks();
		WooPayDirectCheckout.getCheckoutButtonElements.mockReturnValue( [] );
		WooPayDirectCheckout.getClassicProceedToCheckoutButton.mockReturnValue(
			CLASSIC_PROCEED_BUTTON
		);
	} );

	it( 're-attaches the logged-in redirect to the classic button if third-party cookies are enabled and user is logged-in', async () => {
		WooPayDirectCheckout.isWooPayThirdPartyCookiesEnabled.mockResolvedValue(
			true
		);
		WooPayDirectCheckout.isUserLoggedIn.mockResolvedValue( true );
		fireEvent.load( window );
		await new Promise( ( resolve ) => setImmediate( resolve ) );
		jest.clearAllMocks();

		await $( document.body ).trigger( 'updated_cart_totals' );
		await new Promise( ( resolve ) => setImmediate( resolve ) );

		expect(
			WooPayDirectCheckout.maybePrefetchEncryptedSessionData
		).toHaveBeenCalled();
		expect(
			WooPayDirectCheckout.addRedirectToWooPayEventListener
		).toHaveBeenCalledWith( [ CLASSIC_PROCEED_BUTTON ], true );
	} );

	it( 're-attaches the "checkout_redirect" redirect to the classic button if third-party cookies are disabled', async () => {
		WooPayDirectCheckout.isWooPayThirdPartyCookiesEnabled.mockResolvedValue(
			false
		);
		fireEvent.load( window );
		await new Promise( ( resolve ) => setImmediate( resolve ) );
		jest.clearAllMocks();

		await $( document.body ).trigger( 'updated_cart_totals' );
		await new Promise( ( resolve ) => setImmediate( resolve ) );

		expect( WooPayDirectCheckout.isUserLoggedIn ).not.toHaveBeenCalled();
		expect(
			WooPayDirectCheckout.addRedirectToWooPayEventListener
		).toHaveBeenCalledWith( [ CLASSIC_PROCEED_BUTTON ], false );
	} );
} );
