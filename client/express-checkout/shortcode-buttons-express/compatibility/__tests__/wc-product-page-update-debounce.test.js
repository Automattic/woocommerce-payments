/**
 * External dependencies
 */
import { doAction } from '@wordpress/hooks';

/**
 * Internal dependencies
 */
import { onProductAvailabilityChange } from 'wcpay/utils/wc-product-page-events';

jest.mock( '@wordpress/hooks', () => ( {
	doAction: jest.fn(),
	addFilter: jest.fn(),
} ) );

jest.mock( 'wcpay/utils/wc-product-page-events', () => ( {
	onProductAvailabilityChange: jest.fn(),
} ) );

jest.mock( 'wcpay/express-checkout/utils', () => ( {
	// Anything other than 'product' so the quantity-input branch bails early and
	// the test only exercises the variation availability path.
	getExpressCheckoutData: jest.fn( () => 'cart' ),
} ) );

const UPDATE_ACTION = 'wcpay.express-checkout.update-button-data';

const updateActionCalls = () =>
	doAction.mock.calls.filter( ( call ) => call[ 0 ] === UPDATE_ACTION )
		.length;

describe( 'ECE product page update-button-data debounce', () => {
	let availabilityCallback;

	beforeEach( () => {
		jest.useFakeTimers();
		jest.clearAllMocks();

		// Run jQuery's ready callback synchronously so the module registers its
		// availability listener at import time.
		global.jQuery = jest.fn( ( arg ) => {
			if ( typeof arg === 'function' ) {
				arg( global.jQuery );
			}
			return { on: jest.fn() };
		} );

		onProductAvailabilityChange.mockImplementation( ( callback ) => {
			availabilityCallback = callback;
			return () => {};
		} );

		jest.isolateModules( () => {
			require( '../wc-product-page' );
		} );
	} );

	afterEach( () => {
		jest.useRealTimers();
		delete global.jQuery;
	} );

	it( 'coalesces a burst of availability changes into a single update', () => {
		// The shared watcher fires several times for one variation change
		// (WC change event + the add-to-cart MutationObserver settling).
		availabilityCallback();
		availabilityCallback();
		availabilityCallback();

		// Nothing fires until the debounce window elapses.
		expect( updateActionCalls() ).toBe( 0 );

		jest.advanceTimersByTime( 100 );

		expect( updateActionCalls() ).toBe( 1 );
	} );

	it( 'fires again for a later, separate change', () => {
		availabilityCallback();
		jest.advanceTimersByTime( 100 );
		expect( updateActionCalls() ).toBe( 1 );

		availabilityCallback();
		jest.advanceTimersByTime( 100 );
		expect( updateActionCalls() ).toBe( 2 );
	} );
} );
