/**
 * External dependencies
 */
import { applyFilters, removeAllFilters } from '@wordpress/hooks';

const FILTER = 'wcpay.express-checkout.resolved-currency';
const SET_CURRENCY_EVENT = 'wc_price_based_country_set_currency_params';
const RETRIGGER_EVENT = 'wc_price_based_country_ajax_geolocation';

const buildJQueryMock = () => {
	const handlers = new Map();
	const triggerCounts = new Map();

	const $body = {
		on: jest.fn( ( event, handler ) => {
			if ( ! handlers.has( event ) ) {
				handlers.set( event, new Set() );
			}
			handlers.get( event ).add( handler );
			return $body;
		} ),
		off: jest.fn( ( event, handler ) => {
			handlers.get( event )?.delete( handler );
			return $body;
		} ),
		triggerHandler: jest.fn( ( event, args ) => {
			triggerCounts.set( event, ( triggerCounts.get( event ) ?? 0 ) + 1 );
			handlers
				.get( event )
				?.forEach( ( h ) => h( {}, ...( args ?? [] ) ) );
			return $body;
		} ),
	};

	const jQuery = jest.fn( () => $body );
	jQuery.__emit = ( event, ...args ) => $body.triggerHandler( event, args );
	jQuery.__triggerCount = ( event ) => triggerCounts.get( event ) ?? 0;
	return jQuery;
};

// Resolver registers its filter as a side effect of being imported. Loading
// once at module top means we share the @wordpress/hooks registry with the
// test's `applyFilters` import; isolating in `jest.isolateModules` would give
// each scope its own hooks instance and the filter would never run.
require( '../wcpbc-currency' );

describe( 'WCPBC currency resolver', () => {
	beforeEach( () => {
		jest.useFakeTimers();
		global.jQuery = buildJQueryMock();
	} );

	afterEach( () => {
		jest.useRealTimers();
		delete global.jQuery;
		delete global.wc_price_based_country_ajax_geo_params;
	} );

	afterAll( () => {
		removeAllFilters( FILTER );
	} );

	test( 'returns the upstream value when WCPBC AJAX mode is not active', async () => {
		const result = await applyFilters(
			FILTER,
			Promise.resolve( 'usd' ),
			{}
		);

		expect( result ).toBe( 'usd' );
	} );

	test( 'resolves with the WCPBC-reported currency code', async () => {
		global.wc_price_based_country_ajax_geo_params = {};

		const piped = applyFilters( FILTER, Promise.resolve( 'usd' ), {} );

		// WCPBC fires its event after the AJAX response lands.
		global.jQuery.__emit( SET_CURRENCY_EVENT, { code: 'EUR' } );

		await expect( piped ).resolves.toBe( 'eur' );
	} );

	test( 'soft watchdog re-triggers WCPBC geolocation if the event never fires', async () => {
		global.wc_price_based_country_ajax_geo_params = {};

		const piped = applyFilters( FILTER, Promise.resolve( 'usd' ), {} );

		expect( global.jQuery.__triggerCount( RETRIGGER_EVENT ) ).toBe( 0 );

		jest.advanceTimersByTime( 3000 );

		expect( global.jQuery.__triggerCount( RETRIGGER_EVENT ) ).toBe( 1 );

		// Event eventually arrives after the retrigger.
		global.jQuery.__emit( SET_CURRENCY_EVENT, { code: 'CAD' } );

		await expect( piped ).resolves.toBe( 'cad' );
	} );

	test( 'hard watchdog falls back to the upstream value', async () => {
		global.wc_price_based_country_ajax_geo_params = {};

		const piped = applyFilters( FILTER, Promise.resolve( 'usd' ), {} );

		jest.advanceTimersByTime( 6000 );
		await Promise.resolve(); // flush microtasks for the fallback's await

		await expect( piped ).resolves.toBe( 'usd' );
	} );

	test( 'ignores events without a currency code', async () => {
		global.wc_price_based_country_ajax_geo_params = {};

		const piped = applyFilters( FILTER, Promise.resolve( 'usd' ), {} );

		// Resolution stays pending; the hard watchdog will close it out.
		global.jQuery.__emit( SET_CURRENCY_EVENT, {} );
		jest.advanceTimersByTime( 6000 );
		await Promise.resolve();

		await expect( piped ).resolves.toBe( 'usd' );
	} );
} );
