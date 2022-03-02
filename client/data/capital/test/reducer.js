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
			{
				existing: 'state',
				shouldBe: 'preserved',
			},
			{
				type: types.SET_ACTIVE_LOAN_SUMMARY,
				data: mockSummary,
			}
		);

		expect( reduced ).toStrictEqual( {
			existing: 'state',
			shouldBe: 'preserved',
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

	test( 'Loans list fetch is reduced correctly', () => {
		const mockLoansList = [ { aaa: 'bbb' }, { ccc: 'ddd' } ];
		const reduced = reducer(
			{
				existing: 'state',
				shouldBe: 'preserved',
			},
			{
				type: types.SET_LOANS,
				data: mockLoansList,
			}
		);

		expect( reduced ).toStrictEqual( {
			existing: 'state',
			shouldBe: 'preserved',
			loans: mockLoansList,
			loansError: undefined,
		} );
	} );

	test( 'Loans list fetch error is reduced correctly', () => {
		const reduced = reducer(
			{
				loans: { some: 'data' },
			},
			{
				type: types.SET_ERROR_FOR_LOANS,
				error: { code: 'some_error' },
			}
		);

		expect( reduced ).toStrictEqual( {
			loans: undefined,
			loansError: { code: 'some_error' },
		} );
	} );
} );
