/** @format */

/**
 * Internal dependencies
 */
import reducer from '../reducer';
import types from '../action-types';

describe( 'Capital reducer tests', () => {
	test( 'Active loan summary fetch is reduced correctly', () => {
		const mockSummary = {
			object: 'capital.financing_summary',
			details: {
				currency: 'usd',
				advance_amount: 100000,
				some_more: 'irrelevant_data',
			},
			status: 'active',
		};
		const reduced = reducer(
			undefined, // Default state.
			{
				type: types.SET_ACTIVE_LOAN_SUMMARY,
				data: mockSummary,
			}
		);

		expect( reduced ).toStrictEqual( {
			summary: mockSummary,
			summaryError: undefined,
		} );
	} );

	test( 'Active loan summary fetch error is reduced correctly', () => {
		const reduced = reducer(
			{
				summary: { some: 'data' },
			},
			{
				type: types.SET_ERROR_FOR_ACTIVE_LOAN_SUMMARY,
				error: { code: 'some_error' },
			}
		);

		expect( reduced ).toStrictEqual( {
			summary: undefined,
			summaryError: { code: 'some_error' },
		} );
	} );
} );
