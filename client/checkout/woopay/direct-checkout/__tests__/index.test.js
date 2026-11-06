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

let mockIsItemPendingQuantity = false;
jest.mock( '@wordpress/data', () => ( {
	select: () => ( {
		isItemPendingDelete: () => false,
		isItemPendingQuantity: () => mockIsItemPendingQuantity,
	} ),
	dispatch: () => ( {
		itemIsPendingDelete: () => {},
		itemIsPendingQuantity: () => {},
	} ),
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

const jQueryCallbacks = {};
global.jQuery = jest.fn( () => ( {
	on: ( events, callback ) => {
		events.split( ' ' ).forEach( ( event ) => {
			jQueryCallbacks[ event ] = callback;
		} );
	},
} ) );

// Microtasks only, so it also works while timers are faked.
const flushPromises = async () => {
	for ( let i = 0; i < 20; i++ ) {
		await Promise.resolve();
	}
};

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
		jest.useFakeTimers();
	} );

	afterEach( () => {
		jest.useRealTimers();
	} );

	it( 'should not prefetch encrypted session data on add item if third-party cookies are not enabled', async () => {
		WooPayDirectCheckout.isWooPayThirdPartyCookiesEnabled.mockResolvedValue(
			false
		);
		WooPayDirectCheckout.getCheckoutButtonElements.mockReturnValue( [] );

		fireEvent.load( window );
		await flushPromises();

		await wpHookCallbacks[
			'experimental__woocommerce_blocks-cart-add-item'
		]();
		jest.advanceTimersByTime( 400 );
		await flushPromises();

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
		WooPayDirectCheckout.getCheckoutButtonElements.mockReturnValue( [] );

		fireEvent.load( window );
		await flushPromises();
		jest.clearAllMocks();

		await wpHookCallbacks[
			'experimental__woocommerce_blocks-cart-add-item'
		]();
		jest.advanceTimersByTime( 400 );
		await flushPromises();

		expect(
			WooPayDirectCheckout.maybePrefetchEncryptedSessionData
		).toHaveBeenCalledTimes( 1 );
	} );

	it( 'prefetches once when the remove-item hook and the store sync event report the same removal', async () => {
		WooPayDirectCheckout.isWooPayThirdPartyCookiesEnabled.mockResolvedValue(
			true
		);
		WooPayDirectCheckout.isUserLoggedIn.mockResolvedValue( true );
		WooPayDirectCheckout.getCheckoutButtonElements.mockReturnValue( [] );

		fireEvent.load( window );
		await flushPromises();
		jest.clearAllMocks();

		await wpHookCallbacks[
			'experimental__woocommerce_blocks-cart-remove-item'
		]( { product: { key: 'cart-item-key' } } );
		window.dispatchEvent(
			new CustomEvent( 'wc-blocks_store_sync_required', {
				detail: { type: 'from_@wordpress/data' },
			} )
		);
		jest.advanceTimersByTime( 400 );
		await flushPromises();

		expect(
			WooPayDirectCheckout.maybePrefetchEncryptedSessionData
		).toHaveBeenCalledTimes( 1 );
	} );
	it( 'drops the prefetched session while a quantity change is still in flight', async () => {
		WooPayDirectCheckout.isWooPayThirdPartyCookiesEnabled.mockResolvedValue(
			true
		);
		WooPayDirectCheckout.isUserLoggedIn.mockResolvedValue( true );
		WooPayDirectCheckout.getCheckoutButtonElements.mockReturnValue( [] );

		fireEvent.load( window );
		await flushPromises();
		jest.clearAllMocks();
		mockIsItemPendingQuantity = true;

		wpHookCallbacks[
			'experimental__woocommerce_blocks-cart-set-item-quantity'
		]( { product: { key: 'cart-item-key' } } );
		jest.advanceTimersByTime( 400 );
		await flushPromises();

		expect(
			WooPayDirectCheckout.setEncryptedSessionDataAsNotPrefetched
		).toHaveBeenCalled();
		expect(
			WooPayDirectCheckout.maybePrefetchEncryptedSessionData
		).not.toHaveBeenCalled();

		mockIsItemPendingQuantity = false;
		jest.advanceTimersByTime( 100 );
		await flushPromises();
		jest.advanceTimersByTime( 400 );
		await flushPromises();
	} );
} );

describe( 'WooPay direct checkout cart change listeners', () => {
	const loadAsLoggedInShopper = async () => {
		WooPayDirectCheckout.isWooPayThirdPartyCookiesEnabled.mockResolvedValue(
			true
		);
		WooPayDirectCheckout.isUserLoggedIn.mockResolvedValue( true );
		WooPayDirectCheckout.getCheckoutButtonElements.mockReturnValue( [] );

		fireEvent.load( window );
		await flushPromises();
		jest.clearAllMocks();
	};

	const settleCart = async () => {
		jest.advanceTimersByTime( 400 );
		await flushPromises();
	};

	beforeEach( () => {
		jest.clearAllMocks();
		jest.useFakeTimers();
	} );

	afterEach( () => {
		jest.useRealTimers();
	} );

	it( 'drops the prefetched session at once and prefetches again after the cart settles', async () => {
		await loadAsLoggedInShopper();

		document.body.dispatchEvent(
			new CustomEvent( 'wc-blocks_added_to_cart' )
		);

		expect(
			WooPayDirectCheckout.setEncryptedSessionDataAsNotPrefetched
		).toHaveBeenCalled();
		expect(
			WooPayDirectCheckout.maybePrefetchEncryptedSessionData
		).not.toHaveBeenCalled();

		await settleCart();

		expect(
			WooPayDirectCheckout.maybePrefetchEncryptedSessionData
		).toHaveBeenCalledTimes( 1 );
	} );

	it.each( [ 'from_iAPI', 'from_@wordpress/data' ] )(
		'prefetches again after a store sync event %s',
		async ( type ) => {
			await loadAsLoggedInShopper();

			window.dispatchEvent(
				new CustomEvent( 'wc-blocks_store_sync_required', {
					detail: { type },
				} )
			);
			await settleCart();

			expect(
				WooPayDirectCheckout.maybePrefetchEncryptedSessionData
			).toHaveBeenCalledTimes( 1 );
		}
	);

	it.each( [ 'added_to_cart', 'removed_from_cart' ] )(
		'prefetches again after the classic %s event',
		async ( event ) => {
			await loadAsLoggedInShopper();

			jQueryCallbacks[ event ]();
			await settleCart();

			expect(
				WooPayDirectCheckout.maybePrefetchEncryptedSessionData
			).toHaveBeenCalledTimes( 1 );
		}
	);

	it( 'prefetches once for a burst of events about the same change', async () => {
		await loadAsLoggedInShopper();

		jQueryCallbacks.added_to_cart();
		document.body.dispatchEvent(
			new CustomEvent( 'wc-blocks_added_to_cart' )
		);
		document.body.dispatchEvent(
			new CustomEvent( 'wc-blocks_added_to_cart' )
		);
		await settleCart();

		expect(
			WooPayDirectCheckout.maybePrefetchEncryptedSessionData
		).toHaveBeenCalledTimes( 1 );
	} );

	it( 'binds a checkout button that WooCommerce renders after a cart change', async () => {
		const newButton = document.createElement( 'a' );
		await loadAsLoggedInShopper();
		WooPayDirectCheckout.getCheckoutButtonElements.mockReturnValue( [
			newButton,
		] );

		document.body.dispatchEvent(
			new CustomEvent( 'wc-blocks_added_to_cart' )
		);
		await settleCart();

		expect(
			WooPayDirectCheckout.addRedirectToWooPayEventListener
		).toHaveBeenCalledWith( [ newButton ], true );
	} );

	it( 'does not prefetch after a cart change when third-party cookies are disabled', async () => {
		WooPayDirectCheckout.isWooPayThirdPartyCookiesEnabled.mockResolvedValue(
			false
		);
		WooPayDirectCheckout.getCheckoutButtonElements.mockReturnValue( [] );
		fireEvent.load( window );
		await flushPromises();
		jest.clearAllMocks();

		document.body.dispatchEvent(
			new CustomEvent( 'wc-blocks_added_to_cart' )
		);
		await settleCart();

		expect(
			WooPayDirectCheckout.maybePrefetchEncryptedSessionData
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
