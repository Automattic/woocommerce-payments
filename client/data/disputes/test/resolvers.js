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
	const query = { paged: 1, perPage: 25 };

	beforeEach( () => {
		generator = getDisputes( query );
		expect( generator.next().value ).toEqual(
			apiFetch( { path: '/wc/v3/payments/disputes?page=1&pagesize=25' } )
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
	const mockSummary = { count: 42 };

	beforeEach( () => {
		generator = getDisputesSummary();
		expect( generator.next().value ).toEqual(
			apiFetch( { path: '/wc/v3/payments/disputes/summary' } )
		);
	} );

	afterEach( () => {
		expect( generator.next().done ).toStrictEqual( true );
	} );

	describe( 'on success', () => {
		test( 'should update state with disputes summary data', () => {
			expect( generator.next( mockSummary ).value ).toEqual(
				updateDisputesSummary( mockSummary )
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
