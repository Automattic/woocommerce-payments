/** @format */

/**
 * Internal dependencies.
 */
import transactionsSelectors from '../../../api-spec/transactions/selectors';
import { DEFAULT_REQUIREMENT } from '../../../constants';


describe( 'Transactions selectors', () => {
	const expectedResourceName = 'transactions-list';
	const now = Date.now();
	const second_before_now = now - 1000;

	describe( 'getTransactions()', () => {
		it( 'Returns empty list before a read operation', () => {
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

		it( 'Returns the expected transactions list after a read operation', () => {
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

	describe( 'getTransactionsIsLoading()', () => {
		it( "Returns false when a read operation isn't in flight", () => {
			const expected = false;

			const mockGetResource = jest.fn();

			mockGetResource.mockReturnValue( {
				lastRequested: second_before_now,
				lastReceived: now,
			} );
			const isLoading = transactionsSelectors.getTransactionsIsLoading( mockGetResource )();

			expect( mockGetResource ).toHaveBeenCalledTimes( 1 );
			expect( mockGetResource ).toHaveBeenCalledWith( expectedResourceName );
			expect( isLoading ).toStrictEqual( expected );
		} );

		it( 'Returns true when a read operation is in flight', () => {
			const expected = true;

			const mockGetResource = jest.fn();

			mockGetResource.mockReturnValue( {
				lastRequested: now,
				lastReceived: second_before_now,
			} );
			const isLoading = transactionsSelectors.getTransactionsIsLoading( mockGetResource )();

			expect( mockGetResource ).toHaveBeenCalledTimes( 1 );
			expect( mockGetResource ).toHaveBeenCalledWith( expectedResourceName );
			expect( isLoading ).toStrictEqual( expected );
		} );
	} );

	describe( 'isWaitingForInitialLoad()', () => {
		it( 'Returns false when transactions are initialized', () => {
			const expected = false;

			const mockGetResource = jest.fn();
			mockGetResource.mockReturnValue( {
				lastRequested: second_before_now,
				lastReceived: now,
			} );

			const initStatus = transactionsSelectors.isWaitingForInitialLoad( mockGetResource )();

			expect( mockGetResource ).toHaveBeenCalledTimes( 1 );
			expect( mockGetResource ).toHaveBeenCalledWith( expectedResourceName );
			expect( initStatus ).toBe( expected );
		} );

		it( "Returns true when transactions aren't initialized", () => {
			const expected = true;

			const mockGetResource = jest.fn();
			mockGetResource.mockReturnValue( {} );

			const initStatus = transactionsSelectors.isWaitingForInitialLoad( mockGetResource )();

			expect( mockGetResource ).toHaveBeenCalledTimes( 1 );
			expect( mockGetResource ).toHaveBeenCalledWith( expectedResourceName );
			expect( initStatus ).toBe( expected );
		} );

		it( "Returns true when first request in flight", () => {
			const expected = true;

			const mockGetResource = jest.fn();
			mockGetResource.mockReturnValue( {
				lastRequested: second_before_now,
			} );

			const initStatus = transactionsSelectors.isWaitingForInitialLoad( mockGetResource )();

			expect( mockGetResource ).toHaveBeenCalledTimes( 1 );
			expect( mockGetResource ).toHaveBeenCalledWith( expectedResourceName );
			expect( initStatus ).toBe( expected );
		} );
	} );

	describe( 'showTransactionsPlaceholder()', () => {
		it( "Returns true when transactions aren't initialized", () => {
			const expected = true;

			const mockGetResource = jest.fn();
			mockGetResource.mockReturnValue( {} );

			const showPlaceholder = transactionsSelectors.showTransactionsPlaceholder( mockGetResource )();

			expect( mockGetResource ).toHaveBeenCalledTimes( 1 );
			expect( mockGetResource ).toHaveBeenCalledWith( expectedResourceName );
			expect( showPlaceholder ).toBe( expected );
		} );

		it( "Returns true when first request in flight", () => {
			const expected = true;

			const mockGetResource = jest.fn();
			mockGetResource.mockReturnValue( {
				lastRequested: second_before_now,
			} );

			const initStatus = transactionsSelectors.showTransactionsPlaceholder( mockGetResource )();

			expect( mockGetResource ).toHaveBeenCalledTimes( 1 );
			expect( mockGetResource ).toHaveBeenCalledWith( expectedResourceName );
			expect( initStatus ).toBe( expected );
		} );

		it( "Returns false when transactions are initialized", () => {
			const expected = false;

			const mockGetResource = jest.fn();
			mockGetResource.mockReturnValue( {
				lastRequested: second_before_now,
				lastReceived: now,
			} );

			const showPlaceholder = transactionsSelectors.showTransactionsPlaceholder( mockGetResource )();

			expect( mockGetResource ).toHaveBeenCalledTimes( 1 );
			expect( mockGetResource ).toHaveBeenCalledWith( expectedResourceName );
			expect( showPlaceholder ).toBe( expected );
		} );
	} );
} );
