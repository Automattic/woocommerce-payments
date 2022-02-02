/** @format */

/**
 * External dependencies
 */
import { apiFetch, dispatch } from '@wordpress/data-controls';

/**
 * Internal dependencies
 */
import {
	updateDispute,
	updateDisputes,
	updateDisputesSummary,
} from '../actions';
import { getDispute, getDisputes, getDisputesSummary } from '../resolvers';

const mockDisputes = [
	{
		id: 'dp_mock1',
		reason: 'product_unacceptable',
	},
	{
		id: 'dp_mock2',
		reason: 'fraudulent',
	},
];
const errorResponse = { code: 'error' };
const filterQuery = {
	match: 'all',
	dateBefore: '2020-04-28 00:00:00',
	dateAfter: '2020-04-29 23:59:59',
	dateBetween: [ '2020-04-28 00:00:00', '2020-04-29 23:59:59' ],
	statusIs: 'lost',
	statusIsNot: 'won',
	storeCurrencyIs: 'gbp',
};

describe( 'getDispute resolver', () => {
	let generator = null;

	beforeEach( () => {
		generator = getDispute( 'dp_mock1' );
		expect( generator.next().value ).toEqual(
			apiFetch( { path: '/wc/v3/payments/disputes/dp_mock1' } )
		);
	} );

	afterEach( () => {
		expect( generator.next().done ).toStrictEqual( true );
	} );

	describe( 'on success', () => {
		test( 'should update state with dispute data', () => {
			expect( generator.next( mockDisputes[ 0 ] ).value ).toEqual(
				updateDispute( mockDisputes[ 0 ] )
			);
		} );
	} );

	describe( 'on error', () => {
		test( 'should update state with error on error', () => {
			expect( generator.throw( errorResponse ).value ).toEqual(
				dispatch(
					'core/notices',
					'createErrorNotice',
					expect.any( String )
				)
			);
		} );
	} );
} );

describe( 'getDisputes resolver', () => {
	let generator = null;
	const query = { paged: 1, perPage: 25, orderBy: 'someKey' };

	beforeEach( () => {
		generator = getDisputes( query );
		expect( generator.next().value ).toEqual(
			apiFetch( {
				path:
					'/wc/v3/payments/disputes?page=1&pagesize=25&sort=some_key',
			} )
		);
	} );

	afterEach( () => {
		expect( generator.next().done ).toStrictEqual( true );
	} );

	describe( 'on success', () => {
		test( 'should update state with disputes data', () => {
			expect( generator.next( { data: mockDisputes } ).value ).toEqual(
				updateDisputes( query, mockDisputes )
			);
		} );
	} );

	describe( 'on error', () => {
		test( 'should update state with error on error', () => {
			expect( generator.throw( errorResponse ).value ).toEqual(
				dispatch(
					'core/notices',
					'createErrorNotice',
					expect.any( String )
				)
			);
		} );
	} );
} );

describe( 'getDisputesSummary resolver', () => {
	let generator = null;
	const query = filterQuery;
	const mockSummary = { count: 42 };
	const expectedQueryString =
		// eslint-disable-next-line max-len
		'match=all&store_currency_is=gbp&date_before=2020-04-29%2003%3A59%3A59&date_after=2020-04-29%2004%3A00%3A00&date_between%5B0%5D=2020-04-28%2004%3A00%3A00&date_between%5B1%5D=2020-04-30%2003%3A59%3A59&status_is=lost&status_is_not=won';

	beforeEach( () => {
		generator = getDisputesSummary( query );
		expect( generator.next().value ).toEqual(
			apiFetch( {
				path: `/wc/v3/payments/disputes/summary?${ expectedQueryString }`,
			} )
		);
	} );

	afterEach( () => {
		expect( generator.next().done ).toStrictEqual( true );
	} );

	describe( 'on success', () => {
		test( 'should update state with disputes summary data', () => {
			expect( generator.next( mockSummary ).value ).toEqual(
				updateDisputesSummary( query, mockSummary )
			);
		} );
	} );

	describe( 'on error', () => {
		test( 'should update state with error', () => {
			expect( generator.throw( errorResponse ).value ).toEqual(
				dispatch(
					'core/notices',
					'createErrorNotice',
					expect.any( String )
				)
			);
		} );
	} );
} );
