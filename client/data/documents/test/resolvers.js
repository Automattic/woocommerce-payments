/** @format */

/**
 * External dependencies
 */
import { apiFetch, dispatch } from '@wordpress/data-controls';

/**
 * Internal dependencies
 */
import {
	updateErrorForDocuments,
	updateErrorForDocumentsSummary,
	updateDocuments,
	updateDocumentsSummary,
} from '../actions';
import { getDocuments, getDocumentsSummary } from '../resolvers';

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
	typeIs: 'test_doc',
	typeIsNot: 'doc_brown',
};

describe( 'getDocuments resolver', () => {
	const successfulResponse = { data: [] };
	const query = { ...paginationQuery, ...filterQuery };
	const expectedQueryString =
		'page=1&pagesize=25&sort=date&direction=desc' +
		'&match=all&date_before=2020-04-29%2003%3A59%3A59&date_after=2020-04-29%2004%3A00%3A00' +
		'&date_between%5B0%5D=2020-04-28%2004%3A00%3A00&date_between%5B1%5D=2020-04-30%2003%3A59%3A59' +
		'&type_is=test_doc&type_is_not=doc_brown';
	let generator = null;

	beforeEach( () => {
		generator = getDocuments( query );
		expect( generator.next().value ).toEqual(
			apiFetch( {
				path: `/wc/v3/payments/documents?${ expectedQueryString }`,
			} )
		);
	} );

	afterEach( () => {
		expect( generator.next().done ).toStrictEqual( true );
	} );

	describe( 'on success', () => {
		test( 'should update state with documents data', () => {
			expect( generator.next( successfulResponse ).value ).toEqual(
				updateDocuments( query, successfulResponse.data )
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
				updateErrorForDocuments( query, null, errorResponse )
			);
		} );
	} );
} );

describe( 'getDocumentsSummary resolver', () => {
	const successfulResponse = {};
	const query = filterQuery;
	const expectedQueryString =
		'match=all&date_before=2020-04-29%2003%3A59%3A59&date_after=2020-04-29%2004%3A00%3A00' +
		'&date_between%5B0%5D=2020-04-28%2004%3A00%3A00&date_between%5B1%5D=2020-04-30%2003%3A59%3A59' +
		'&type_is=test_doc&type_is_not=doc_brown';
	let generator = null;

	beforeEach( () => {
		generator = getDocumentsSummary( query );
		expect( generator.next().value ).toEqual(
			apiFetch( {
				path: `/wc/v3/payments/documents/summary?${ expectedQueryString }`,
			} )
		);
	} );

	afterEach( () => {
		expect( generator.next().done ).toStrictEqual( true );
	} );

	describe( 'on success', () => {
		test( 'should update state with documents summary data', () => {
			expect( generator.next( successfulResponse ).value ).toEqual(
				updateDocumentsSummary( query, successfulResponse )
			);
		} );
	} );

	describe( 'on error', () => {
		test( 'should update state with error', () => {
			expect( generator.throw( errorResponse ).value ).toEqual(
				updateErrorForDocumentsSummary( query, null, errorResponse )
			);
		} );
	} );
} );
