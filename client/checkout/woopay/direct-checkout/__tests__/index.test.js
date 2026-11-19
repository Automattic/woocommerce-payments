/* global $ */
/**
 * External dependencies
 */
import { fireEvent } from '@testing-library/react';

/**
 * Internal dependencies
 */
import WooPayDirectCheckout from 'wcpay/checkout/woopay/direct-checkout/woopay-direct-checkout';

// Must match the debounce waits and the pending-item polling in index.js.
const CART_SETTLE_DELAY_MS = 400;
const PENDING_POLL_INTERVAL_MS = 100;
const PENDING_POLL_ATTEMPTS = 60;
const MICROTASK_FLUSH_ROUNDS = 20;
const CART_ITEM = { product: { key: 'cart-item-key' } };

const wpHookCallbacks = {};

jest.mock( '@wordpress/hooks', () => ( {
	addAction: ( _hookName, _namespace, callback ) => {
		wpHookCallbacks[ _hookName ] = callback;
	},
} ) );

let mockIsItemPendingQuantity = false;
let mockIsItemPendingDelete = false;
jest.mock( '@wordpress/data', () => ( {
	select: () => ( {
		isItemPendingDelete: () => mockIsItemPendingDelete,
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
		isUserLoggedIn: jest.fn(),
		maybePrefetchEncryptedSessionData: jest.fn(),
		addRedirectToWooPayEventListener: jest.fn(),
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
			return updatedCartTotalsCallback();
		}
	},
} ) );
const jQueryCallbacks = {};
global.jQuery = jest.fn( ( selectorOrReadyCallback ) => {
	if ( typeof selectorOrReadyCallback === 'function' ) {
		return selectorOrReadyCallback( global.$ );
	}

	return {
		on: ( events, callback ) => {
			events.split( ' ' ).forEach( ( event ) => {
				jQueryCallbacks[ event ] = callback;
			} );
		},
	};
} );

// Microtasks only, so it also works while timers are faked.
const flushPromises = async () => {
	for ( let i = 0; i < MICROTASK_FLUSH_ROUNDS; i++ ) {
		await Promise.resolve();
	}
};

const advanceTimersAndFlush = async ( milliseconds ) => {
	jest.advanceTimersByTime( milliseconds );
	await flushPromises();
};

const settleCart = () => advanceTimersAndFlush( CART_SETTLE_DELAY_MS );

require( '../index.js' );

describe( 'WooPay direct checkout window "load" event listener', () => {
	beforeEach( () => {
		jest.clearAllMocks();
	} );

	it( 'prefetches and enables the logged-in redirect if third-party cookies are enabled and user is logged-in', async () => {
		WooPayDirectCheckout.isWooPayThirdPartyCookiesEnabled.mockResolvedValue(
			true
		);
		WooPayDirectCheckout.isUserLoggedIn.mockResolvedValue( true );

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
		).toHaveBeenCalledWith( true );
	} );

	it( 'enables the "checkout_redirect" redirect if third-party cookies are disabled', async () => {
		WooPayDirectCheckout.isWooPayThirdPartyCookiesEnabled.mockResolvedValue(
			false
		);

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
		).toHaveBeenCalledWith( false );
	} );
} );

describe( 'WooPay direct checkout "updated_cart_totals" jQuery event listener', () => {
	beforeEach( () => {
		jest.clearAllMocks();
		jest.useFakeTimers();
	} );

	afterEach( () => {
		jest.useRealTimers();
	} );

	it( 'drops the prefetched session at once and prefetches again after the classic cart settles', async () => {
		WooPayDirectCheckout.isWooPayThirdPartyCookiesEnabled.mockResolvedValue(
			true
		);
		WooPayDirectCheckout.isUserLoggedIn.mockResolvedValue( true );
		fireEvent.load( window );
		await flushPromises();
		jest.clearAllMocks();

		$( document.body ).trigger( 'updated_cart_totals' );

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
		expect(
			WooPayDirectCheckout.addRedirectToWooPayEventListener
		).toHaveBeenCalledWith( true );
	} );

	it( 'keeps the "checkout_redirect" redirect without checking the login if third-party cookies are disabled', async () => {
		WooPayDirectCheckout.isWooPayThirdPartyCookiesEnabled.mockResolvedValue(
			false
		);
		fireEvent.load( window );
		await flushPromises();
		jest.clearAllMocks();

		$( document.body ).trigger( 'updated_cart_totals' );
		await settleCart();

		expect( WooPayDirectCheckout.isUserLoggedIn ).not.toHaveBeenCalled();
		expect(
			WooPayDirectCheckout.maybePrefetchEncryptedSessionData
		).not.toHaveBeenCalled();
		expect(
			WooPayDirectCheckout.addRedirectToWooPayEventListener
		).toHaveBeenCalledWith( false );
	} );
} );

describe( 'WooPay direct checkout cart item listeners', () => {
	beforeEach( () => {
		jest.clearAllMocks();
		jest.useFakeTimers();
	} );

	afterEach( () => {
		mockIsItemPendingQuantity = false;
		mockIsItemPendingDelete = false;
		jest.useRealTimers();
	} );

	it( 'should not prefetch encrypted session data on add item if third-party cookies are not enabled', async () => {
		WooPayDirectCheckout.isWooPayThirdPartyCookiesEnabled.mockResolvedValue(
			false
		);

		fireEvent.load( window );
		await flushPromises();

		await wpHookCallbacks[
			'experimental__woocommerce_blocks-cart-add-item'
		]();
		await settleCart();

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
		await flushPromises();
		jest.clearAllMocks();

		await wpHookCallbacks[
			'experimental__woocommerce_blocks-cart-add-item'
		]();
		await settleCart();

		expect(
			WooPayDirectCheckout.maybePrefetchEncryptedSessionData
		).toHaveBeenCalledTimes( 1 );
	} );

	it( 'prefetches once when the remove-item hook and the store sync event report the same removal', async () => {
		WooPayDirectCheckout.isWooPayThirdPartyCookiesEnabled.mockResolvedValue(
			true
		);
		WooPayDirectCheckout.isUserLoggedIn.mockResolvedValue( true );

		fireEvent.load( window );
		await flushPromises();
		jest.clearAllMocks();

		await wpHookCallbacks[
			'experimental__woocommerce_blocks-cart-remove-item'
		]( CART_ITEM );
		window.dispatchEvent(
			new CustomEvent( 'wc-blocks_store_sync_required', {
				detail: { type: 'from_@wordpress/data' },
			} )
		);
		await settleCart();

		expect(
			WooPayDirectCheckout.maybePrefetchEncryptedSessionData
		).toHaveBeenCalledTimes( 1 );
	} );

	it( 'drops the prefetched session while a quantity change is in flight and prefetches once it is done', async () => {
		WooPayDirectCheckout.isWooPayThirdPartyCookiesEnabled.mockResolvedValue(
			true
		);
		WooPayDirectCheckout.isUserLoggedIn.mockResolvedValue( true );

		fireEvent.load( window );
		await flushPromises();
		jest.clearAllMocks();
		mockIsItemPendingQuantity = true;

		wpHookCallbacks[
			'experimental__woocommerce_blocks-cart-set-item-quantity'
		]( CART_ITEM );
		await settleCart();

		expect(
			WooPayDirectCheckout.setEncryptedSessionDataAsNotPrefetched
		).toHaveBeenCalled();
		expect(
			WooPayDirectCheckout.maybePrefetchEncryptedSessionData
		).not.toHaveBeenCalled();

		mockIsItemPendingQuantity = false;
		await advanceTimersAndFlush( PENDING_POLL_INTERVAL_MS );
		await settleCart();

		expect(
			WooPayDirectCheckout.maybePrefetchEncryptedSessionData
		).toHaveBeenCalledTimes( 1 );
	} );

	it( 'does not prefetch when a quantity change is still pending after the last poll', async () => {
		WooPayDirectCheckout.isWooPayThirdPartyCookiesEnabled.mockResolvedValue(
			true
		);
		WooPayDirectCheckout.isUserLoggedIn.mockResolvedValue( true );

		fireEvent.load( window );
		await flushPromises();
		jest.clearAllMocks();
		mockIsItemPendingQuantity = true;

		wpHookCallbacks[
			'experimental__woocommerce_blocks-cart-set-item-quantity'
		]( CART_ITEM );
		await settleCart();
		for ( let i = 0; i < PENDING_POLL_ATTEMPTS; i++ ) {
			await advanceTimersAndFlush( PENDING_POLL_INTERVAL_MS );
		}
		// Once polling gave up, the change finishing must not trigger a prefetch either.
		mockIsItemPendingQuantity = false;
		await advanceTimersAndFlush( PENDING_POLL_INTERVAL_MS );
		await settleCart();

		expect(
			WooPayDirectCheckout.setEncryptedSessionDataAsNotPrefetched
		).toHaveBeenCalled();
		expect(
			WooPayDirectCheckout.maybePrefetchEncryptedSessionData
		).not.toHaveBeenCalled();
	} );

	it( 'drops the prefetched session while a removal is in flight and prefetches once it is done', async () => {
		WooPayDirectCheckout.isWooPayThirdPartyCookiesEnabled.mockResolvedValue(
			true
		);
		WooPayDirectCheckout.isUserLoggedIn.mockResolvedValue( true );

		fireEvent.load( window );
		await flushPromises();
		jest.clearAllMocks();
		mockIsItemPendingDelete = true;

		wpHookCallbacks[ 'experimental__woocommerce_blocks-cart-remove-item' ](
			CART_ITEM
		);
		await settleCart();

		expect(
			WooPayDirectCheckout.setEncryptedSessionDataAsNotPrefetched
		).toHaveBeenCalled();
		expect(
			WooPayDirectCheckout.maybePrefetchEncryptedSessionData
		).not.toHaveBeenCalled();

		mockIsItemPendingDelete = false;
		await advanceTimersAndFlush( PENDING_POLL_INTERVAL_MS );
		await settleCart();

		expect(
			WooPayDirectCheckout.maybePrefetchEncryptedSessionData
		).toHaveBeenCalledTimes( 1 );
	} );
} );

describe( 'WooPay direct checkout cart change listeners', () => {
	const loadAsLoggedInShopper = async () => {
		WooPayDirectCheckout.isWooPayThirdPartyCookiesEnabled.mockResolvedValue(
			true
		);
		WooPayDirectCheckout.isUserLoggedIn.mockResolvedValue( true );

		fireEvent.load( window );
		await flushPromises();
		jest.clearAllMocks();
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

	it( 'prefetches once, after the events about a change stop for the settle delay', async () => {
		await loadAsLoggedInShopper();

		jQueryCallbacks.added_to_cart();
		await advanceTimersAndFlush( CART_SETTLE_DELAY_MS - 1 );
		document.body.dispatchEvent(
			new CustomEvent( 'wc-blocks_added_to_cart' )
		);
		await advanceTimersAndFlush( CART_SETTLE_DELAY_MS - 1 );

		expect(
			WooPayDirectCheckout.maybePrefetchEncryptedSessionData
		).not.toHaveBeenCalled();

		await advanceTimersAndFlush( 1 );

		expect(
			WooPayDirectCheckout.maybePrefetchEncryptedSessionData
		).toHaveBeenCalledTimes( 1 );
	} );

	it( 'enables the redirect with the login state checked after a cart change', async () => {
		await loadAsLoggedInShopper();

		document.body.dispatchEvent(
			new CustomEvent( 'wc-blocks_added_to_cart' )
		);
		await settleCart();

		expect( WooPayDirectCheckout.isUserLoggedIn ).toHaveBeenCalledTimes(
			1
		);
		expect(
			WooPayDirectCheckout.addRedirectToWooPayEventListener
		).toHaveBeenCalledWith( true );
	} );

	it( 'does not prefetch after a cart change when third-party cookies are disabled', async () => {
		WooPayDirectCheckout.isWooPayThirdPartyCookiesEnabled.mockResolvedValue(
			false
		);
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
