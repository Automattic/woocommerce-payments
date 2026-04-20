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
			updatedCartTotalsCallback();
		}
	},
} ) );

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

		await $( document.body ).trigger( 'updated_cart_totals' );

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
		expect(
			WooPayDirectCheckout.addRedirectToWooPayEventListener
		).toHaveBeenCalledWith( expect.any( Array ), false );
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

describe( 'WooPay direct checkout iAPI mini-cart', () => {
	let miniCartWidget;
	let miniCartButton;
	let footerButton;

	beforeEach( () => {
		jest.clearAllMocks();

		// Set up iAPI mini-cart widget element.
		miniCartWidget = document.createElement( 'div' );
		miniCartWidget.setAttribute(
			'data-wp-interactive',
			'woocommerce/mini-cart'
		);
		document.body.appendChild( miniCartWidget );

		// Set up SSR'd checkout button inside the overlay.
		miniCartButton = document.createElement( 'a' );
		miniCartButton.classList.add(
			'wp-block-woocommerce-mini-cart-checkout-button-block'
		);
		document.body.appendChild( miniCartButton );

		// Set up SSR'd footer checkout button.
		footerButton = document.createElement( 'a' );
		footerButton.classList.add( 'wc-block-mini-cart__footer-checkout' );
		document.body.appendChild( footerButton );
	} );

	afterEach( () => {
		miniCartWidget.remove();
		miniCartButton.remove();
		footerButton.remove();
	} );

	it( "attaches event listeners directly to SSR'd mini-cart buttons on window load", async () => {
		WooPayDirectCheckout.isWooPayThirdPartyCookiesEnabled.mockResolvedValue(
			false
		);
		WooPayDirectCheckout.getCheckoutButtonElements.mockReturnValue( [] );
		WooPayDirectCheckout.getMiniCartProceedToCheckoutButton.mockReturnValue(
			miniCartButton
		);
		WooPayDirectCheckout.getFooterMiniCartProceedToCheckoutButton.mockReturnValue(
			footerButton
		);

		fireEvent.load( window );

		await new Promise( ( resolve ) => setImmediate( resolve ) );

		// All buttons (checkout + iAPI mini-cart) are merged into a single call
		// to avoid concurrent isUserLoggedIn races via postMessage.
		expect(
			WooPayDirectCheckout.addRedirectToWooPayEventListener
		).toHaveBeenCalledTimes( 1 );

		// Verify both mini-cart buttons are included in the single call.
		expect(
			WooPayDirectCheckout.addRedirectToWooPayEventListener
		).toHaveBeenCalledWith( [ miniCartButton, footerButton ], false );
	} );
} );
