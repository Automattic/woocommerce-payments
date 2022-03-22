/** @format */

/**
 * External dependencies
 */
import { apiFetch } from '@wordpress/data-controls';

/**
 * Internal dependencies
 */
import {
	updateErrorForTransactions,
	updateErrorForTransactionsSummary,
	updateTransactions,
	updateTransactionsSummary,
} from '../actions';
import { getTransactions, getTransactionsSummary } from '../resolvers';

const errorResponse = { code: 'error' };

const paginationQuery = {
	paged: 1,
	perPage: 25,
	orderby: 'date',
	order: 'desc',
};
const filterQuery = {
	match: 'all',
	dateBefore: '2020-04-28 00:00:00',
	dateAfter: '2020-04-29 23:59:59',
	dateBetween: [ '2020-04-28 00:00:00', '2020-04-29 23:59:59' ],
	typeIs: 'charge',
	typeIsNot: 'dispute',
	depositId: 'mock_po_id',
	loanIdIs: 'mock_flxln_id',
	search: 'Test user',
};

describe( 'getTransactions resolver', () => {
	const successfulResponse = { data: [] };
	const query = { ...paginationQuery, ...filterQuery };
	const expectedQueryString =
		'page=1&pagesize=25&sort=date&direction=desc' +
		'&match=all&date_before=2020-04-29%2003%3A59%3A59&date_after=2020-04-29%2004%3A00%3A00' +
		'&date_between%5B0%5D=2020-04-28%2004%3A00%3A00&date_between%5B1%5D=2020-04-30%2003%3A59%3A59&type_is=charge' +
		'&type_is_not=dispute&loan_id_is=mock_flxln_id&deposit_id=mock_po_id&search=Test%20user';
	let generator = null;

	beforeEach( () => {
		generator = getTransactions( query );
		expect( generator.next().value ).toEqual(
			apiFetch( {
				path: `/wc/v3/payments/transactions?${ expectedQueryString }`,
			} )
		);
	} );

	afterEach( () => {
		expect( generator.next().done ).toStrictEqual( true );
	} );

	describe( 'on success', () => {
		test( 'should update state with transactions data', () => {
			expect( generator.next( successfulResponse ).value ).toEqual(
				updateTransactions( query, successfulResponse.data )
			);
		} );
	} );

	describe( 'on error', () => {
		test( 'should update state with error', () => {
			expect( generator.throw( errorResponse ).value ).toEqual(
				updateErrorForTransactions( query, null, errorResponse )
			);
		} );
	} );
} );

describe( 'getTransactionsSummary resolver', () => {
	const successfulResponse = {};
	const query = filterQuery;
	const expectedQueryString =
		'match=all&date_before=2020-04-29%2003%3A59%3A59&date_after=2020-04-29%2004%3A00%3A00' +
		'&date_between%5B0%5D=2020-04-28%2004%3A00%3A00&date_between%5B1%5D=2020-04-30%2003%3A59%3A59&type_is=charge' +
		'&type_is_not=dispute&loan_id_is=mock_flxln_id&deposit_id=mock_po_id&search=Test%20user';
	let generator = null;

	beforeEach( () => {
		generator = getTransactionsSummary( query );
		expect( generator.next().value ).toEqual(
			apiFetch( {
				path: `/wc/v3/payments/transactions/summary?${ expectedQueryString }`,
			} )
		);
	} );

	afterEach( () => {
		expect( generator.next().done ).toStrictEqual( true );
	} );

	describe( 'on success', () => {
		test( 'should update state with transactions summary data', () => {
			expect( generator.next( successfulResponse ).value ).toEqual(
				updateTransactionsSummary( query, successfulResponse )
			);
		} );
	} );

	describe( 'on error', () => {
		test( 'should update state with error', () => {
			expect( generator.throw( errorResponse ).value ).toEqual(
				updateErrorForTransactionsSummary( query, null, errorResponse )
			);
		} );
	} );
} );
