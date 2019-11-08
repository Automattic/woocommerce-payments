/** @format */

/**
 * Internal dependencies
 */
import {
	readTransaction,
	readTransactions,
	transactionsToResources,
	transactionToResources,
} from '../../../api-spec/transactions/operations';
import { NAMESPACE } from '../../../constants';

describe( 'Transactions operations', () => {
	describe( 'readTransaction()', () => {
		it( 'Returns a list with promises when transactions are supplied', () => {
			const expectedUrl = ( id ) => `${ NAMESPACE }/payments/transactions/${ id }`;
			const resourceList = [ 'txn_1', 'txn_2', 'txn_3' ];
			const mockPromises = resourceList.map( resource => Promise.resolve( { id: resource } ) );
			const mockFetch = jest.fn( ( args ) => mockPromises[ args.path.split( '_' ).pop() - 1 ] );
			const mockToResources = jest.fn( ( transaction ) => transaction.id );

			const promises = readTransaction( resourceList, mockFetch, mockToResources );

			expect( mockFetch ).toHaveBeenCalledTimes( 3 );
			resourceList.forEach( resource => expect( mockFetch ).toHaveBeenCalledWith( { path: expectedUrl( resource ) } ) );

			return Promise.all( promises ).then( results => {
				expect( mockToResources ).toHaveBeenCalledTimes( 3 );
				expect( results ).toEqual( resourceList );
			} );
		} );

		it( 'Returns an empty list when wrong resource names are supplied', () => {
			const resourceList = [ 'tasxn_1', 'transaction-list', 'somethingelse' ];
			const mockFetch = jest.fn();
			const response = readTransaction( resourceList, mockFetch );
			expect( mockFetch ).not.toHaveBeenCalled();
			expect( response ).toStrictEqual( [] );
		} );
	} );

	describe( 'readTransactions()', () => {
		const expectedUrl = `${ NAMESPACE }/payments/transactions`;

		it( 'Returns a list with one promise when correct resource names are supplied', () => {
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

		it( 'Returns an empty list when wrong resource names are supplied', () => {
			const expected = [];

			const mockFetch = jest.fn();

			// Perform read operation.
			const promises = readTransactions( [ 'wrong', 'resource', 'names' ] );

			expect( mockFetch ).not.toHaveBeenCalled();
			expect( promises ).toStrictEqual( expected );
		} );
	} );

	describe( 'transactionToResources()', () => {
		it( 'should convert a transaction to resource', () => {
			const transaction = { id: 'txn_j329saja' };
			const expectedResource = {
				[ 'txn_j329saja' ]: { data: transaction },
			};

			const resource = transactionToResources( transaction );
			expect( resource ).toStrictEqual( expectedResource );
		} );
	} );

	describe( 'transactionsToResources()', () => {
		it( 'Transactions list is correctly converted to resources', () => {
			const mockData = { data: [ { id: 'txn_1' }, { id: 'txn_2' }, { id: 'txn_3' } ] };
			const expected = {
				[ 'transactions-list' ]: { data: mockData },
				[ 'txn_1' ]: { data: { id: 'txn_1' } },
				[ 'txn_2' ]: { data: { id: 'txn_2' } },
				[ 'txn_3' ]: { data: { id: 'txn_3' } },
			};

			const resources = transactionsToResources( mockData );
			expect( resources ).toStrictEqual( expected );
		} );
	} );
} );
