/** @format */
/* eslint-disable camelcase */

/**
 * External dependencies
 */
import { apiFetch, dispatch } from '@wordpress/data-controls';

/**
 * Internal dependencies
 */
import { updateDisputes } from '../actions';
import { getDisputes } from '../resolvers';

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
			expect( generator.next( { data: mockDisputes } ).value )
				.toEqual( updateDisputes( query, mockDisputes ) );
		} );
	} );

	describe( 'on error', () => {
		test( 'should update state with error on error', () => {
			expect( generator.throw( errorResponse ).value ).toEqual(
				dispatch( 'core/notices', 'createErrorNotice', expect.any( String ) )
			);
		} );
	} );
} );
