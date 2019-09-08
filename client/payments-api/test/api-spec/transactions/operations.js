/** @format */

/**
 * Internal dependencies
 */
import {
	readTransactionsPage,
	transactionsPageToResources,
	readTransactionsSummary,
	transactionsSummaryToResources,
} from '../../../api-spec/transactions/operations';
import { NAMESPACE } from '../../../constants';
import { getResourceName } from '../../../utils';
import { resourcePrefixes } from '../../../api-spec/transactions/constants';

describe( 'Transactions operations', () => {
	describe( 'readTransactionsPage()', () => {
		const expectedUrl = `${ NAMESPACE }/payments/transactions`;

		it( 'Returns a list with 1 promise when list with only 1 correct resource name is supplied', () => {
			// Prepare original mocked data.
			const mockData = {
				summary: {
					page: 2,
					per_page: 25,
				},
				transactions: [ {}, {}, {} ],
			};
			const resourceName = getResourceName( resourcePrefixes.list, mockData.summary );

			// Prepare expected results from resolved promises.
			const expectedResolvedPromise = {
				[ resourceName ]: {
					data: mockData,
				},
			};

			// Prepare mocked functions and their return values.
			const mockToResources = jest.fn();
			mockToResources.mockReturnValue( expectedResolvedPromise );

			// Prepare returned promises from fetch function call.
			const mockPromise = new Promise( () => mockData, () => {} );
			const expectedPromises = [ mockPromise ];

			const mockFetch = jest.fn();
			mockFetch.mockReturnValue( mockPromise );

			// Perform read operation.
			const promises = readTransactionsPage(
				[ resourceName ],
				mockFetch,
				mockToResources,
			);

			// Ensure fetch is called appropriately.
			expect( mockFetch ).toHaveBeenCalledTimes( 1 );
			expect( mockFetch ).toHaveBeenCalledWith( {
				path: expectedUrl + `?page=${ mockData.summary.page }&per_page=${ mockData.summary.per_page }`,
			} );

			// Ensure the correct promises are returned.
			expect( promises ).toStrictEqual( expectedPromises );

			// Ensure promises resolve correctly.
			promises[ 0 ].then( result => {
				expect( mockToResources ).toHaveBeenCalledTimes( 1 );
				expect( mockToResources ).toHaveBeenCalledWith( mockData );
				expect( result ).toBe( expectedResolvedPromise );
			} );
		} );

		it( 'Returns a list with 1 promise when list with 1 correct and 1 incorrect resource name is supplied', () => {
			// Prepare original mocked data.
			const mockData = {
				summary: {
					page: 2,
					per_page: 25,
				},
				transactions: [ {}, {}, {} ],
			};
			const resourceName = getResourceName( resourcePrefixes.list, mockData.summary );

			// Prepare expected results from resolved promises.
			const expectedResolvedPromise = {
				[ resourceName ]: {
					data: mockData,
				},
			};

			// Prepare mocked functions and their return values.
			const mockToResources = jest.fn();
			mockToResources.mockReturnValue( expectedResolvedPromise );

			// Prepare returned promises from fetch function call.
			const mockPromise = new Promise( () => mockData, () => {} );
			const expectedPromises = [ mockPromise ];

			const mockFetch = jest.fn();
			mockFetch.mockReturnValue( mockPromise );

			// Perform read operation.
			const promises = readTransactionsPage(
				[
					resourceName,
					'wrong-resource-name',
				],
				mockFetch,
				mockToResources,
			);

			// Ensure fetch is called appropriately.
			expect( mockFetch ).toHaveBeenCalledTimes( 1 );
			expect( mockFetch ).toHaveBeenCalledWith( {
				path: expectedUrl + `?page=${ mockData.summary.page }&per_page=${ mockData.summary.per_page }`,
			} );

			// Ensure the correct promises are returned.
			expect( promises ).toStrictEqual( expectedPromises );

			// Ensure promises resolve correctly.
			promises[ 0 ].then( result => {
				expect( mockToResources ).toHaveBeenCalledTimes( 1 );
				expect( mockToResources ).toHaveBeenCalledWith( mockData );
				expect( result ).toBe( expectedResolvedPromise );
			} );
		} );

		it( 'Returns a list with 2 promises when list with 2 correct resource names is supplied', () => {
			// Prepare original mocked data.
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
			const pageOneResourceName = getResourceName( resourcePrefixes.list, pageOneMockData.summary );
			const pageTwoResourceName = getResourceName( resourcePrefixes.list, pageTwoMockData.summary );

			// Prepare expected results from resolved promises.
			const expectedPageOneResolvedPromise = {
				[ pageOneResourceName ]: {
					data: pageOneMockData,
				},
			};
			const expectedPageTwoResolvedPromise = {
				[ pageTwoResourceName ]: {
					data: pageTwoMockData,
				},
			};

			// Prepare mocked functions and their return values.
			const mockToResources = jest.fn();
			mockToResources
				.mockReturnValueOnce( expectedPageOneResolvedPromise )
				.mockReturnValueOnce( expectedPageTwoResolvedPromise );

			// Prepare returned promises from fetch function call.
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
					pageOneResourceName,
					pageTwoResourceName,
				],
				mockFetch,
				mockToResources,
			);

			// Ensure fetch is called appropriately.
			expect( mockFetch ).toHaveBeenCalledTimes( 2 );
			expect( mockFetch ).toHaveBeenCalledWith( {
				path: expectedUrl + `?page=${ pageOneMockData.summary.page }&per_page=${ pageOneMockData.summary.per_page }`,
			} );
			expect( mockFetch ).toHaveBeenCalledWith( {
				path: expectedUrl + `?page=${ pageTwoMockData.summary.page }&per_page=${ pageTwoMockData.summary.per_page }`,
			} );

			// Ensure the correct promises are returned.
			expect( promises ).toStrictEqual( expectedPromises );

			// Ensure promises resolve correctly.
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
			const promises = readTransactionsPage( [ 'wrong', 'resource', 'names' ], mockFetch, jest.fn() );

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
			const resourceName = getResourceName( resourcePrefixes.list, mockData.summary );

			const expected = {
				[ resourceName ]: {
					data: mockData,
				},
			};

			const resources = transactionsPageToResources( mockData );
			expect( resources ).toStrictEqual( expected );
		} );
	} );

	describe( 'readTransactionsSummary()', () => {
		const expectedUrl = `${ NAMESPACE }/payments/transactions/summary`;

		it( 'Returns a list with 1 promise when list with only 1 correct resource name is supplied', () => {
			// Prepare original mocked data.
			const mockData = {
				number_of_transactions: 1234,
				total: 123,
				fees: 12,
				net: 1,
			};

			// Prepare expected results from resolved promises.
			const expectedResolvedPromise = {
				[ resourcePrefixes.summary ]: {
					data: mockData,
				},
			};

			// Prepare mocked functions and their return values.
			const mockToResources = jest.fn();
			mockToResources.mockReturnValue( expectedResolvedPromise );

			// Prepare returned promises from fetch function call.
			const mockPromise = new Promise( () => mockData, () => {} );
			const expectedPromises = [ mockPromise ];

			const mockFetch = jest.fn();
			mockFetch.mockReturnValue( mockPromise );

			// Perform read operation.
			const promises = readTransactionsSummary(
				[ resourcePrefixes.summary ],
				mockFetch,
				mockToResources,
			);

			// Ensure fetch is called appropriately.
			expect( mockFetch ).toHaveBeenCalledTimes( 1 );
			expect( mockFetch ).toHaveBeenCalledWith( {
				path: expectedUrl,
			} );

			// Ensure the correct promises are returned.
			expect( promises ).toStrictEqual( expectedPromises );

			// Ensure promises resolve correctly.
			promises[ 0 ].then( result => {
				expect( mockToResources ).toHaveBeenCalledTimes( 1 );
				expect( mockToResources ).toHaveBeenCalledWith( mockData );
				expect( result ).toBe( expectedResolvedPromise );
			} );
		} );

		it( 'Returns a list with 1 promise when list with 1 correct and 1 incorrect resource name is supplied', () => {
			// Prepare original mocked data.
			const mockData = {
				number_of_transactions: 1234,
				total: 123,
				fees: 12,
				net: 1,
			};

			// Prepare expected results from resolved promises.
			const expectedResolvedPromise = {
				[ resourcePrefixes.summary ]: {
					data: mockData,
				},
			};

			// Prepare mocked functions and their return values.
			const mockToResources = jest.fn();
			mockToResources.mockReturnValue( expectedResolvedPromise );

			// Prepare returned promises from fetch function call.
			const mockPromise = new Promise( () => mockData, () => {} );
			const expectedPromises = [ mockPromise ];

			const mockFetch = jest.fn();
			mockFetch.mockReturnValue( mockPromise );

			// Perform read operation.
			const promises = readTransactionsSummary(
				[
					resourcePrefixes.summary,
					'wrong-resource-name',
				],
				mockFetch,
				mockToResources,
			);

			// Ensure fetch is called appropriately.
			expect( mockFetch ).toHaveBeenCalledTimes( 1 );
			expect( mockFetch ).toHaveBeenCalledWith( {
				path: expectedUrl,
			} );

			// Ensure the correct promises are returned.
			expect( promises ).toStrictEqual( expectedPromises );

			// Ensure promises resolve correctly.
			promises[ 0 ].then( result => {
				expect( mockToResources ).toHaveBeenCalledTimes( 1 );
				expect( mockToResources ).toHaveBeenCalledWith( mockData );
				expect( result ).toBe( expectedResolvedPromise );
			} );

		} );
		it( 'Returns an empty list when wrong resource names are supplied', () => {
			const expected = [];

			const mockFetch = jest.fn();

			// Perform read operation.
			const promises = readTransactionsSummary( [ 'wrong', 'resource', 'names' ], mockFetch, jest.fn() );

			expect( mockFetch ).not.toHaveBeenCalled();
			expect( promises ).toStrictEqual( expected );
		} );
	} );

	describe( 'transactionsSummaryToResources()', () => {
		it( 'Transactions summary is correctly converted to resources', () => {
			const mockData = {
				number_of_transactions: 1234,
				total: 123,
				fees: 12,
				net: 1,
			};
			const expected = {
				[ resourcePrefixes.summary ]: {
					data: mockData,
				},
			};

			const resources = transactionsSummaryToResources( mockData );
			expect( resources ).toStrictEqual( expected );
		} );
	} );
} );
