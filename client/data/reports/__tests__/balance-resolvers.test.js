/** @format */

/**
 * External dependencies
 */
import { apiFetch } from '@wordpress/data-controls';
import { controls } from '@wordpress/data';

/**
 * Internal dependencies
 */
import {
	updateErrorForReportsBalanceSummary,
	updateReportsBalanceSummary,
} from '../actions';
import {
	formatReportsBalanceQuery,
	getReportsBalanceSummary,
} from '../resolvers';
import balanceSummaryFixture from '../fixtures/balance-summary';

const errorResponse = { code: 'error' };

const query = {
	dateStart: '2024-03-01T00:00:00',
	dateEnd: '2024-03-31T23:59:59',
	currency: 'USD',
};

const expectApiFetchPath = ( control, expectedPath, expectedParams ) => {
	expect( control ).toEqual(
		apiFetch( {
			path: expect.any( String ),
		} )
	);

	const url = new URL( control.request.path, 'https://example.test' );

	expect( url.pathname ).toBe( expectedPath );
	expect( Object.fromEntries( url.searchParams.entries() ) ).toEqual(
		expectedParams
	);
};

describe( 'formatReportsBalanceQuery', () => {
	it( 'formats the endpoint params and lowercases currency', () => {
		expect( formatReportsBalanceQuery( query ) ).toEqual( {
			date_start: '2024-03-01T00:00:00',
			date_end: '2024-03-31T23:59:59',
			currency: 'usd',
		} );
	} );

	it( 'uses an empty currency when none is provided', () => {
		expect(
			formatReportsBalanceQuery( {
				dateStart: '2024-03-01T00:00:00',
				dateEnd: '2024-03-31T23:59:59',
			} )
		).toEqual( {
			date_start: '2024-03-01T00:00:00',
			date_end: '2024-03-31T23:59:59',
			currency: '',
		} );
	} );
} );

describe( 'getReportsBalanceSummary resolver', () => {
	let consoleErrorSpy;

	beforeEach( () => {
		consoleErrorSpy = jest
			.spyOn( console, 'error' )
			.mockImplementation( () => undefined );
	} );

	afterEach( () => {
		consoleErrorSpy.mockRestore();
	} );

	it( 'updates state with Balance summary data on success', () => {
		const generator = getReportsBalanceSummary( query );

		expectApiFetchPath(
			generator.next().value,
			'/wc/v3/payments/reports/balance',
			{
				date_start: '2024-03-01T00:00:00',
				date_end: '2024-03-31T23:59:59',
				currency: 'usd',
			}
		);
		expect( generator.next( balanceSummaryFixture ).value ).toEqual(
			updateReportsBalanceSummary( query, balanceSummaryFixture )
		);
		expect( generator.next().done ).toStrictEqual( true );
	} );

	it( 'updates state with the Balance summary error on failure', () => {
		const generator = getReportsBalanceSummary( query );

		expectApiFetchPath(
			generator.next().value,
			'/wc/v3/payments/reports/balance',
			{
				date_start: '2024-03-01T00:00:00',
				date_end: '2024-03-31T23:59:59',
				currency: 'usd',
			}
		);
		expect( generator.throw( errorResponse ).value ).toEqual(
			controls.dispatch(
				'core/notices',
				'createErrorNotice',
				expect.any( String )
			)
		);
		const errorUpdate = generator.next().value;

		expect( consoleErrorSpy ).toHaveBeenCalledWith(
			'Balance summary resolver failed:',
			{ query, error: errorResponse }
		);
		expect( errorUpdate ).toEqual(
			updateErrorForReportsBalanceSummary( query, errorResponse )
		);
		expect( generator.next().done ).toStrictEqual( true );
	} );
} );
