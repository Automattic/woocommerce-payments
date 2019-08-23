/** @format */

/**
 * Internal dependencies
 */
import {
	getTransactionsResourcePage,
	getTransactionsResourcePerPage,
	getPageIndex,
	getPerPageIndex,
} from '../../../api-spec/transactions/utils';

describe( 'Transactions utility functions', () => {
	describe( 'getPageIndex()', () => {
		it( '1 digit page index returns right index', () => {
			const resourceName = 'transactions-list-page-1-perpage-25';
			const expected = 23;

			const index = getPageIndex( resourceName );

			expect( index ).toBe( expected );
		} );

		it( '2 digit page index returns right index', () => {
			const resourceName = 'transactions-list-page-13-perpage-25';
			const expected = 23;

			const index = getPageIndex( resourceName );

			expect( index ).toBe( expected );
		} );

		it( '3 digit page index returns right index', () => {
			const resourceName = 'transactions-list-page-746-perpage-25';
			const expected = 23;

			const index = getPageIndex( resourceName );

			expect( index ).toBe( expected );
		} );
	} );

	describe( 'getPerPageIndex()', () => {
		it( '1 digit page index returns right index', () => {
			const resourceName = 'transactions-list-page-1-perpage-25';
			const expected = 33;

			const index = getPerPageIndex( resourceName );

			expect( index ).toBe( expected );
		} );

		it( '2 digit page index returns right index', () => {
			const resourceName = 'transactions-list-page-13-perpage-25';
			const expected = 34;

			const index = getPerPageIndex( resourceName );

			expect( index ).toBe( expected );
		} );

		it( '3 digit page index returns right index', () => {
			const resourceName = 'transactions-list-page-746-perpage-25';
			const expected = 35;

			const index = getPerPageIndex( resourceName );

			expect( index ).toBe( expected );
		} );
	} );

	describe( 'getTransactionsResourcePage()', () => {
		it( '1 digit page number returns the right page number', () => {
			const expected = 2;
			const resourceName = `transactions-list-page-${ expected }-perpage-75`;

			const page = getTransactionsResourcePage( resourceName );

			expect( page ).toBe( expected );
		} );

		it( '2 digit page number returns the right page number', () => {
			const expected = 25;
			const resourceName = `transactions-list-page-${ expected }-perpage-75`;

			const page = getTransactionsResourcePage( resourceName );

			expect( page ).toBe( expected );
		} );

		it( '3 digit page number returns the right page number', () => {
			const expected = 836;
			const resourceName = `transactions-list-page-${ expected }-perpage-75`;

			const page = getTransactionsResourcePage( resourceName );

			expect( page ).toBe( expected );
		} );
	} );

	describe( 'getTransactionsResourcePerPage()', () => {
		it( '1 digit page number returns the right 2 digit per page number', () => {
			const expected = 25;
			const resourceName = `transactions-list-page-5-perpage-${ expected }`;

			const page = getTransactionsResourcePerPage( resourceName );

			expect( page ).toBe( expected );
		} );

		it( '2 digit page number returns the right 2 digit per page number', () => {
			const expected = 25;
			const resourceName = `transactions-list-page-10-perpage-${ expected }`;

			const page = getTransactionsResourcePerPage( resourceName );

			expect( page ).toBe( expected );
		} );

		it( '3 digit page number returns the right 2 digit per page number', () => {
			const expected = 25;
			const resourceName = `transactions-list-page-186-perpage-${ expected }`;

			const page = getTransactionsResourcePerPage( resourceName );

			expect( page ).toBe( expected );
		} );

		it( '1 digit page number returns the right 3 digit per page number', () => {
			const expected = 100;
			const resourceName = `transactions-list-page-5-perpage-${ expected }`;

			const page = getTransactionsResourcePerPage( resourceName );

			expect( page ).toBe( expected );
		} );

		it( '2 digit page number returns the right 3 digit per page number', () => {
			const expected = 100;
			const resourceName = `transactions-list-page-10-perpage-${ expected }`;

			const page = getTransactionsResourcePerPage( resourceName );

			expect( page ).toBe( expected );
		} );

		it( '3 digit page number returns the right 3 digit per page number', () => {
			const expected = 100;
			const resourceName = `transactions-list-page-186-perpage-${ expected }`;

			const page = getTransactionsResourcePerPage( resourceName );

			expect( page ).toBe( expected );
		} );
	} );
} );
