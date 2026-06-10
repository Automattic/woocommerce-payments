/** @format */

/**
 * Internal dependencies
 */
import {
	getReportsBalanceSummary,
	getReportsBalanceSummaryError,
} from '../selectors';
import { getResourceId } from 'utils/data';
import balanceSummaryFixture from '../fixtures/balance-summary';

const query = {
	dateStart: '2024-03-01T00:00:00',
	dateEnd: '2024-03-31T23:59:59',
	currency: 'usd',
};

const emptyState = {};
const error = { code: 'rest_failure' };

describe( 'Balance summary selectors', () => {
	it( 'returns the same empty object for an empty state', () => {
		expect( getReportsBalanceSummary( emptyState, query ) ).toStrictEqual(
			{}
		);
		expect( getReportsBalanceSummary( emptyState, query ) ).toBe(
			getReportsBalanceSummary( emptyState, query )
		);
		expect(
			getReportsBalanceSummaryError( emptyState, query )
		).toStrictEqual( {} );
		expect( getReportsBalanceSummaryError( emptyState, query ) ).toBe(
			getReportsBalanceSummaryError( emptyState, query )
		);
	} );

	it( 'returns cached Balance summary data verbatim', () => {
		const state = {
			reports: {
				balanceSummary: {
					[ getResourceId( query ) ]: {
						data: balanceSummaryFixture,
					},
				},
			},
		};

		expect( getReportsBalanceSummary( state, query ) ).toBe(
			balanceSummaryFixture
		);
	} );

	it( 'returns cached Balance summary errors verbatim', () => {
		const state = {
			reports: {
				balanceSummary: {
					[ getResourceId( query ) ]: {
						error,
					},
				},
			},
		};

		expect( getReportsBalanceSummaryError( state, query ) ).toBe( error );
	} );
} );
