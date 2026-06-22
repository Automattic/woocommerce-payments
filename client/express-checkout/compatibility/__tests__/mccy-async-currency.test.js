/**
 * External dependencies
 */
import { applyFilters, removeAllFilters } from '@wordpress/hooks';

const FILTER = 'wcpay.express-checkout.resolved-currency';

// Resolver registers its filter as a side effect of being imported. Loading
// once at module top means we share the @wordpress/hooks registry with the
// test's `applyFilters` import; isolating would give each scope its own hooks
// instance and the filter would never run.
require( '../mccy-async-currency' );

describe( 'MCCY async-renderer currency resolver', () => {
	beforeEach( () => {
		jest.useFakeTimers();
	} );

	afterEach( () => {
		jest.useRealTimers();
		delete global.wcpayAsyncPriceConfig;
		delete window.wcpayAsyncCurrency;
	} );

	afterAll( () => {
		removeAllFilters( FILTER );
	} );

	test( 'returns the upstream value when async mode is not active', async () => {
		const result = await applyFilters(
			FILTER,
			Promise.resolve( 'usd' ),
			{}
		);

		expect( result ).toBe( 'usd' );
	} );

	test( 'returns the upstream value when wcpayAsyncCurrency global is missing', async () => {
		global.wcpayAsyncPriceConfig = {};

		const result = await applyFilters(
			FILTER,
			Promise.resolve( 'usd' ),
			{}
		);

		expect( result ).toBe( 'usd' );
	} );

	test( 'resolves with the renderer-reported currency code', async () => {
		global.wcpayAsyncPriceConfig = {};
		window.wcpayAsyncCurrency = { ready: Promise.resolve( 'EUR' ) };

		const piped = applyFilters( FILTER, Promise.resolve( 'usd' ), {} );

		await expect( piped ).resolves.toBe( 'eur' );
	} );

	test( 'falls back to upstream when the renderer reports an empty value', async () => {
		global.wcpayAsyncPriceConfig = {};
		window.wcpayAsyncCurrency = { ready: Promise.resolve( '' ) };

		const piped = applyFilters( FILTER, Promise.resolve( 'usd' ), {} );

		await expect( piped ).resolves.toBe( 'usd' );
	} );

	test( 'hard watchdog falls back to upstream if the renderer never resolves', async () => {
		global.wcpayAsyncPriceConfig = {};
		window.wcpayAsyncCurrency = { ready: new Promise( () => {} ) };

		const piped = applyFilters( FILTER, Promise.resolve( 'usd' ), {} );

		jest.advanceTimersByTime( 6000 );
		await Promise.resolve(); // flush microtasks

		await expect( piped ).resolves.toBe( 'usd' );
	} );
} );
