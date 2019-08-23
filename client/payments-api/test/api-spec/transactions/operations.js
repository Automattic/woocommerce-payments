/** @format */

/**
 * Internal dependencies
 */
import {
	readTransactions,
	readTransactionsPage,
	transactionsToResources,
	transactionsPageToResources,
} from '../../../api-spec/transactions/operations';
import { NAMESPACE } from '../../../constants';

describe( 'Transactions operations', () => {
	describe( 'readTransactions()', () => {
		const expectedUrl = `${ NAMESPACE }/payments/transactions`;

		it( 'Returns a list with 1 promise when 1 correct resource name is supplied', () => {
			const mockData = [ {}, {}, {} ];
			const expectedResolvedPromise = {
				[ 'transactions-list' ]: {
					data: mockData,
				},
			};

			const mockToResources = jest.fn();
			mockToResources.mockReturnValue( expectedResolvedPromise );

			const mockPromise = new Promise( () => mockData, () => {} );
			const expectedPromises = [ mockPromise ];

			const mockFetch = jest.fn();
			mockFetch.mockReturnValue( mockPromise );

			// Perform read operation.
			const promises = readTransactions( [ 'transactions-list' ], mockFetch, mockToResources );

			expect( mockFetch ).toHaveBeenCalledTimes( 1 );
			expect( mockFetch ).toHaveBeenCalledWith( { path: expectedUrl } );
			expect( promises ).toStrictEqual( expectedPromises );
			promises[ 0 ].then( result => {
				expect( mockToResources ).toHaveBeenCalledTimes( 1 );
				expect( mockToResources ).toHaveBeenCalledWith( mockData );
				expect( result ).toBe( expectedResolvedPromise );
			} );
		} );

		it( 'Returns an empty list when only wrong resource names are supplied', () => {
			const expected = [];

			const mockFetch = jest.fn();

			// Perform read operation.
			const promises = readTransactions( [ 'wrong', 'resource', 'names' ] );

			expect( mockFetch ).not.toHaveBeenCalled();
			expect( promises ).toStrictEqual( expected );
		} );
	} );

	describe( 'transactionsToResources()', () => {
		it( 'Transactions list is correctly converted to resources', () => {
			const mockData = [ {}, {}, {} ];
			const expected = {
				[ 'transactions-list' ]: {
					data: mockData,
				},
			};

			const resources = transactionsToResources( mockData );
			expect( resources ).toStrictEqual( expected );
		} );
	} );

	describe( 'readTransactionsPage()', () => {
		const expectedUrl = `${ NAMESPACE }/payments/transactions`;

		it( 'Returns a list with 1 promise when list with only 1 correct resource name is supplied', () => {
			const mockData = {
				summary: {
					page: 2,
					per_page: 25,
				},
				transactions: [ {}, {}, {} ],
			};
			const expectedResolvedPromise = {
				[ `transactions-list-page-${ mockData.summary.page }-perpage-${ mockData.summary.per_page }` ]: {
					data: mockData,
				},
			};

			const mockToResources = jest.fn();
			mockToResources.mockReturnValue( expectedResolvedPromise );

			const mockPromise = new Promise( () => mockData, () => {} );
			const expectedPromises = [ mockPromise ];

			const mockFetch = jest.fn();
			mockFetch.mockReturnValue( mockPromise );

			// Perform read operation.
			const promises = readTransactionsPage(
				[ `transactions-list-page-${ mockData.summary.page }-perpage-${ mockData.summary.per_page }` ],
				mockFetch,
				mockToResources,
			);

			expect( mockFetch ).toHaveBeenCalledTimes( 1 );
			expect( mockFetch ).toHaveBeenCalledWith( { path: expectedUrl, data: mockData.summary } );
			expect( promises ).toStrictEqual( expectedPromises );
			promises[ 0 ].then( result => {
				expect( mockToResources ).toHaveBeenCalledTimes( 1 );
				expect( mockToResources ).toHaveBeenCalledWith( mockData );
				expect( result ).toBe( expectedResolvedPromise );
			} );
		} );

		it( 'Returns a list with 1 promise when list with 1 correct and 1 incorrect resource name is supplied', () => {
			const mockData = {
				summary: {
					page: 2,
					per_page: 25,
				},
				transactions: [ {}, {}, {} ],
			};
			const expectedResolvedPromise = {
				[ `transactions-list-page-${ mockData.summary.page }-perpage-${ mockData.summary.per_page }` ]: {
					data: mockData,
				},
			};

			const mockToResources = jest.fn();
			mockToResources.mockReturnValue( expectedResolvedPromise );

			const mockPromise = new Promise( () => mockData, () => {} );
			const expectedPromises = [ mockPromise ];

			const mockFetch = jest.fn();
			mockFetch.mockReturnValue( mockPromise );

			// Perform read operation.
			const promises = readTransactionsPage(
				[
					`transactions-list-page-${ mockData.summary.page }-perpage-${ mockData.summary.per_page }`,
					'wrong-resource-name',
				],
				mockFetch,
				mockToResources,
			);

			expect( mockFetch ).toHaveBeenCalledTimes( 1 );
			expect( mockFetch ).toHaveBeenCalledWith( { path: expectedUrl, data: mockData.summary } );
			expect( promises ).toStrictEqual( expectedPromises );
			promises[ 0 ].then( result => {
				expect( mockToResources ).toHaveBeenCalledTimes( 1 );
				expect( mockToResources ).toHaveBeenCalledWith( mockData );
				expect( result ).toBe( expectedResolvedPromise );
			} );
		} );

		it( 'Returns a list with 2 promises when list with 2 correct resource names is supplied', () => {
			const pageOneMockData = {
				summary: {
					page: 1,
					per_page: 25,
				},
				transactions: [ {}, {}, {} ],
			};
			const pageTwoMockData = {
				summary: {
					page: 2,
					per_page: 25,
				},
				transactions: [ {}, {}, {} ],
			};
			const expectedPageOneResolvedPromise = {
				[ `transactions-list-page-${ pageOneMockData.summary.page }-perpage-${ pageOneMockData.summary.per_page }` ]: {
					data: pageOneMockData,
				},
			};
			const expectedPageTwoResolvedPromise = {
				[ `transactions-list-page-${ pageTwoMockData.summary.page }-perpage-${ pageTwoMockData.summary.per_page }` ]: {
					data: pageOneMockData,
				},
			};

			const mockToResources = jest.fn();
			mockToResources
				.mockReturnValueOnce( expectedPageOneResolvedPromise )
				.mockReturnValueOnce( expectedPageTwoResolvedPromise );

			const mockPageOnePromise = new Promise( () => pageOneMockData, () => {} );
			const mockPageTwoPromise = new Promise( () => pageTwoMockData, () => {} );
			const expectedPromises = [ mockPageOnePromise, mockPageTwoPromise ];

			const mockFetch = jest.fn();
			mockFetch
				.mockReturnValue( mockPageOnePromise )
				.mockReturnValueOnce( mockPageTwoPromise );

			// Perform read operation.
			const promises = readTransactionsPage(
				[
					`transactions-list-page-${ pageOneMockData.summary.page }-perpage-${ pageOneMockData.summary.per_page }`,
					`transactions-list-page-${ pageTwoMockData.summary.page }-perpage-${ pageTwoMockData.summary.per_page }`,
				],
				mockFetch,
				mockToResources,
			);

			expect( mockFetch ).toHaveBeenCalledTimes( 2 );
			expect( mockFetch ).toHaveBeenCalledWith( { path: expectedUrl, data: pageOneMockData.summary } );
			expect( mockFetch ).toHaveBeenCalledWith( { path: expectedUrl, data: pageTwoMockData.summary } );
			expect( promises ).toStrictEqual( expectedPromises );
			promises[ 0 ].then( result => {
				expect( mockToResources ).toHaveBeenCalledTimes( 1 );
				expect( mockToResources ).toHaveBeenCalledWith( pageOneMockData );
				expect( mockToResources ).not.toHaveBeenCalledWith( pageTwoMockData );
				expect( result ).toBe( expectedPageOneResolvedPromise );
			} );
			promises[ 1 ].then( result => {
				expect( mockToResources ).toHaveBeenCalledTimes( 2 );
				expect( mockToResources ).toHaveBeenCalledWith( pageTwoMockData );
				expect( result ).toBe( expectedPageTwoResolvedPromise );
			} );
		} );

		it( 'Returns an empty list when wrong resource names are supplied', () => {
			const expected = [];

			const mockFetch = jest.fn();

			// Perform read operation.
			const promises = readTransactionsPage( [ 'wrong', 'resource', 'names' ] );

			expect( mockFetch ).not.toHaveBeenCalled();
			expect( promises ).toStrictEqual( expected );
		} );
	} );

	describe( 'transactionsPageToResources()', () => {
		it( 'Transactions list is correctly converted to resources', () => {
			const mockData = {
				summary: {
					page: 1,
					per_page: 25,
				},
				transactions: [ {}, {}, {} ],
			};
			const expected = {
				[ `transactions-list-page-${ mockData.summary.page }-perpage-${ mockData.summary.per_page }` ]: {
					data: mockData,
				},
			};

			const resources = transactionsPageToResources( mockData );
			expect( resources ).toStrictEqual( expected );
		} );
	} );
} );
