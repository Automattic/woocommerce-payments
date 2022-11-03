/** @format */

/**
 * External dependencies
 */
import { apiFetch, dispatch } from '@wordpress/data-controls';
import type { Query } from '@woocommerce/navigation';

/**
 * Internal dependencies
 */
import {
	updateAuthorizations,
	updateAuthorizationsSummary,
	updateErrorForAuthorizations,
	updateErrorForAuthorizationsSummary,
} from '../actions';
import { getAuthorizations, getAuthorizationsSummary } from '../resolvers';

const errorResponse = {
	name: 'authorizations-error',
	message: 'There was an error',
	code: '42',
};

const paginationQuery = {
	paged: '1',
	per_page: '25',
	orderby: 'created',
	order: 'desc',
};

describe( 'getAuthorizations resolver', () => {
	const successfulResponse = { data: [] };
	const query: Query = { ...paginationQuery };
	const expectedQueryString =
		'page=1&pagesize=25&sort=created&direction=desc';
	let generator: Generator;

	beforeEach( () => {
		generator = getAuthorizations( query );
		expect( generator.next().value ).toEqual(
			apiFetch( {
				path: `/wc/v3/payments/authorizations?${ expectedQueryString }`,
			} )
		);
	} );

	afterEach( () => {
		expect( generator.next().done ).toStrictEqual( true );
	} );

	describe( 'on success', () => {
		test( 'should update state with authorizations data', () => {
			expect( generator.next( successfulResponse ).value ).toEqual(
				updateAuthorizations( query, successfulResponse.data )
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
			expect( generator.next().value ).toEqual(
				updateErrorForAuthorizations( query, errorResponse )
			);
		} );
	} );
} );

describe( 'getAuthorizationsSummary resolver', () => {
	const successfulResponse = {};
	const query = {};
	const expectedQueryString = '';
	let generator: Generator;

	beforeEach( () => {
		generator = getAuthorizationsSummary( query );
		expect( generator.next().value ).toEqual(
			apiFetch( {
				path: `/wc/v3/payments/authorizations/summary?${ expectedQueryString }`,
			} )
		);
	} );

	afterEach( () => {
		expect( generator.next().done ).toStrictEqual( true );
	} );

	describe( 'on success', () => {
		test( 'should update state with authorizations summary data', () => {
			expect( generator.next( successfulResponse ).value ).toEqual(
				updateAuthorizationsSummary( query, successfulResponse )
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

			expect( generator.next().value ).toEqual(
				updateErrorForAuthorizationsSummary( query, errorResponse )
			);
		} );
	} );
} );
