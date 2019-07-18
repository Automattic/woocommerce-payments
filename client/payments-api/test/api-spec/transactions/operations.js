/** @format */

/**
 * Internal dependencies.
 */
import { readTransactions, transactionsToResources } from '../../../api-spec/transactions/operations';
import { NAMESPACE } from '../../../constants';

describe( 'Transactions operations', () => {
	describe( 'readTransactions()', () => {
		const expectedUrl = `${ NAMESPACE }/payments/transactions`;

		it( 'Transactions read operation returns a list with one promise when correct resource names are supplied', () => {
			const mockData = [ {}, {}, {} ];
			const expectedResolvedPromise = {
				[ "transactions-list" ]: {
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
			promises[0].then( result => {
				expect( mockToResources ).toHaveBeenCalledTimes( 1 );
				expect( mockToResources ).toHaveBeenCalledWith( mockData );
				expect( result ).toBe( expectedResolvedPromise );
			} );
		} );

		it( 'Transactions read operation returns an empty list when wrong resource names are supplied', () => {
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
} );
