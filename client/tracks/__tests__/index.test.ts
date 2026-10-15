/**
 * Internal dependencies
 */
import {
	flushShopperEvents,
	maxBatchedShopperEvents,
	recordUserEvent,
	shopperEventsFlushDelay,
	shopperEventsMaxWait,
} from '..';
import { getConfig } from 'wcpay/utils/checkout';

// The tracks module is mocked globally in `tests/js/jest-test-file-setup.js`.
jest.unmock( '..' );
jest.mock( 'wcpay/utils/checkout', () => ( {
	getConfig: jest.fn(),
} ) );
jest.mock( 'wcpay/utils/express-checkout', () => ( {
	getExpressCheckoutConfig: jest.fn(),
} ) );

const mockAjaxUrl = 'https://example.com/wp-admin/admin-ajax.php';
const mockNonce = 'tracks-nonce';

const mockConfig = ( isShopperTrackingEnabled: boolean ) => {
	( getConfig as jest.Mock ).mockImplementation(
		( key: string ) =>
			(
				( {
					isShopperTrackingEnabled,
					platformTrackerNonce: mockNonce,
					ajaxUrl: mockAjaxUrl,
				} ) as Record< string, unknown >
			 )[ key ]
	);
};

const getSentEvents = ( body: FormData ) =>
	JSON.parse( body.get( 'tracksEvents' ) as string );

describe( 'recordUserEvent', () => {
	let fetchMock: jest.Mock;

	beforeEach( () => {
		jest.useFakeTimers();
		fetchMock = jest.fn( () => Promise.resolve() );
		global.fetch = fetchMock;
		mockConfig( true );
	} );

	afterEach( () => {
		flushShopperEvents();
		jest.useRealTimers();
		delete ( navigator as { sendBeacon?: unknown } ).sendBeacon;
	} );

	it( 'sends events recorded close together in a single request', () => {
		recordUserEvent( 'product_page_view', { source: 'product' } );
		jest.advanceTimersByTime( shopperEventsFlushDelay - 1 );
		recordUserEvent( 'gpay_button_load', { source: 'product' } );
		recordUserEvent( 'applepay_button_load', { source: 'product' } );

		expect( fetchMock ).not.toHaveBeenCalled();

		jest.advanceTimersByTime( shopperEventsFlushDelay );

		expect( fetchMock ).toHaveBeenCalledTimes( 1 );
		const [ url, options ] = fetchMock.mock.calls[ 0 ];
		expect( url ).toBe( 'https://example.com/wp-admin/admin-ajax.php' );
		expect( options.body.get( 'action' ) ).toBe( 'platform_tracks' );
		expect( options.body.get( 'tracksNonce' ) ).toBe( 'tracks-nonce' );
		expect( getSentEvents( options.body ) ).toEqual( [
			{ name: 'product_page_view', properties: { source: 'product' } },
			{ name: 'gpay_button_load', properties: { source: 'product' } },
			{ name: 'applepay_button_load', properties: { source: 'product' } },
		] );
	} );

	it( 'does not hold events for longer than the max wait', () => {
		recordUserEvent( 'product_page_view' );
		for (
			let elapsed = 0;
			elapsed < shopperEventsMaxWait;
			elapsed += shopperEventsFlushDelay / 2
		) {
			jest.advanceTimersByTime( shopperEventsFlushDelay / 2 );
			recordUserEvent( 'gpay_button_load' );
		}

		expect( fetchMock ).toHaveBeenCalledTimes( 1 );
	} );

	it( 'sends the queue right away when it is full', () => {
		for ( let i = 0; i < maxBatchedShopperEvents; i++ ) {
			recordUserEvent( 'cart_page_view' );
		}

		expect( fetchMock ).toHaveBeenCalledTimes( 1 );
		expect(
			getSentEvents( fetchMock.mock.calls[ 0 ][ 1 ].body )
		).toHaveLength( 20 );
	} );

	it( 'sends queued events with a beacon when the page is hidden', () => {
		const sendBeacon = jest.fn< boolean, [ string, FormData ] >(
			() => true
		);
		Object.defineProperty( navigator, 'sendBeacon', {
			value: sendBeacon,
			configurable: true,
		} );
		recordUserEvent( 'checkout_place_order_button_click' );

		window.dispatchEvent( new Event( 'pagehide' ) );

		expect( sendBeacon ).toHaveBeenCalledTimes( 1 );
		expect( fetchMock ).not.toHaveBeenCalled();
		expect( getSentEvents( sendBeacon.mock.calls[ 0 ][ 1 ] ) ).toEqual( [
			{ name: 'checkout_place_order_button_click', properties: {} },
		] );
	} );

	it( 'does not queue events when shopper tracking is disabled', () => {
		mockConfig( false );

		recordUserEvent( 'product_page_view' );
		jest.advanceTimersByTime( shopperEventsMaxWait );

		expect( fetchMock ).not.toHaveBeenCalled();
	} );
} );
