/** @format */

/**
 * Internal dependencies.
 */
import transactionsSelectors from '../../../api-spec/transactions/selectors';
import { DEFAULT_REQUIREMENT } from '../../../constants';


describe( 'Transactions selectors', () => {
	const expectedResourceName = 'transactions-list';

	describe( 'getTransactions()', () => {
		it( 'getTransactions before a read operation returns empty list', () => {
			const expected = {};

			const mockGetResource = jest.fn();
			const mockRequireResource = jest.fn();

			mockRequireResource.mockReturnValue( {} );
			const transactions = transactionsSelectors.getTransactions( mockGetResource, mockRequireResource )();

			expect( mockGetResource ).not.toHaveBeenCalled();
			expect( mockRequireResource ).toHaveBeenCalledTimes( 1 );
			expect( mockRequireResource ).toHaveBeenCalledWith( DEFAULT_REQUIREMENT, expectedResourceName );
			expect( transactions ).toStrictEqual( expected );
		} );

		it( 'getTransactions after a read operation returns the expected transactions list', () => {
			const expected = { data: [ {}, {}, {} ] };

			const mockGetResource = jest.fn();
			const mockRequireResource = jest.fn();

			mockRequireResource.mockReturnValue( { data: expected } );
			const transactions = transactionsSelectors.getTransactions( mockGetResource, mockRequireResource )();

			expect( mockGetResource ).not.toHaveBeenCalled();
			expect( mockRequireResource ).toHaveBeenCalledTimes( 1 );
			expect( mockRequireResource ).toHaveBeenCalledWith( DEFAULT_REQUIREMENT, expectedResourceName );
			expect( transactions ).toBe( expected );
		} );
	} );

	describe( 'getTransactionsIsLoading', () => {
		it( "getTransactionsIsLoading returns false when a read operation isn't in flight", () => {
			const expected = false;

			const mockGetResource = jest.fn();

			mockGetResource.mockReturnValue( {
				data: { data: {} },
				lastRequested: 0,
				lastReceived: 1,
			} );
			const isLoading = transactionsSelectors.getTransactionsIsLoading( mockGetResource );

			expect( mockGetResource ).toHaveBeenCalledTimes( 1 );
			expect( mockGetResource ).toHaveBeenCalledWith( expectedResourceName );
			expect( isLoading ).toStrictEqual( expected );
		} );

		it( 'getTransactionsIsLoading returns true when a read operation is in flight', () => {
			const expected = true;

			const mockGetResource = jest.fn();

			mockGetResource.mockReturnValue( {
				data: { data: {} },
				lastRequested: 1,
				lastReceived: 0,
			} );
			const isLoading = transactionsSelectors.getTransactionsIsLoading( mockGetResource );

			expect( mockGetResource ).toHaveBeenCalledTimes( 1 );
			expect( mockGetResource ).toHaveBeenCalledWith( expectedResourceName );
			expect( isLoading ).toStrictEqual( expected );
		} );

		it( "getTransactionsIsLoading returns true when the requested data doesn't exist", () => {
			const expected = true;

			const mockGetResource = jest.fn();

			// Note that it's important here that lastRequested < lastReceived.
			mockGetResource.mockReturnValue( {
				data: {},
				lastRequested: 0,
				lastReceived: 1,
			} );
			const isLoading = transactionsSelectors.getTransactionsIsLoading( mockGetResource );

			expect( mockGetResource ).toHaveBeenCalledTimes( 1 );
			expect( mockGetResource ).toHaveBeenCalledWith( expectedResourceName );
			expect( isLoading ).toStrictEqual( expected );
		} );
	} );
} );
