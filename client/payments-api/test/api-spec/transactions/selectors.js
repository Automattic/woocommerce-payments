/** @format */

/**
 * Internal dependencies
 */
import transactionsSelectors from '../../../api-spec/transactions/selectors';
import { DEFAULT_REQUIREMENT } from '../../../constants';

describe( 'Transactions selectors', () => {
	const expectedResourceName = 'transactions-list';
	const now = Date.now();
	const secondBeforeNow = now - 1000;

	describe( 'getTransaction()', () => {
		it( 'Returns empty object before a read operation', () => {
			const expected = {};
			const transactionId = 'txn_1';

			const mockGetResource = jest.fn();
			const mockRequireResource = jest.fn( () => expected );

			const transaction = transactionsSelectors.getTransaction( mockGetResource, mockRequireResource )( transactionId );

			expect( mockGetResource ).not.toHaveBeenCalled();
			expect( mockRequireResource ).toHaveBeenCalledTimes( 1 );
			expect( mockRequireResource ).toHaveBeenCalledWith( DEFAULT_REQUIREMENT, transactionId );
			expect( transaction ).toStrictEqual( expected );
		} );

		it( 'Returns the expected transaction after a read operation', () => {
			const transactionId = 'txn_32ndsa';
			const expected = { data: { id: transactionId } };

			const mockGetResource = jest.fn();
			const mockRequireResource = jest.fn();

			mockRequireResource.mockReturnValue( { data: expected } );
			const transactions = transactionsSelectors.getTransaction( mockGetResource, mockRequireResource )( transactionId );

			expect( mockGetResource ).not.toHaveBeenCalled();
			expect( mockRequireResource ).toHaveBeenCalledTimes( 1 );
			expect( mockRequireResource ).toHaveBeenCalledWith( DEFAULT_REQUIREMENT, transactionId );
			expect( transactions ).toBe( expected );
		} );
	} );

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

	describe( 'getTransaction state', () => {
		it( 'should be initial load when initializing', () => {
			const mockGetResource = jest.fn( () => ( { lastRequested: secondBeforeNow } ) );
			const isInInitialLoad = transactionsSelectors.isTransactionWaitingForInitialLoad( mockGetResource )();
			expect( isInInitialLoad ).toEqual( true );
		} );

		it( 'should be loading when initializing', () => {
			const mockGetResource = jest.fn( () => ( { lastRequested: secondBeforeNow } ) );
			const isInInitialLoad = transactionsSelectors.isTransactionLoading( mockGetResource )();
			expect( isInInitialLoad ).toEqual( true );
		} );

		it( 'should be loading after initialized when read operation is in flight', () => {
			const mockGetResource = jest.fn( () => ( { lastRequested: now, lastReceived: secondBeforeNow } ) );
			const isInInitialLoad = transactionsSelectors.isTransactionLoading( mockGetResource )();
			expect( isInInitialLoad ).toEqual( true );
		} );

		it( 'should not be initial load after initialized', () => {
			const mockGetResource = jest.fn( () => ( { lastRequested: secondBeforeNow, lastReceived: secondBeforeNow } ) );
			const isInInitialLoad = transactionsSelectors.isTransactionWaitingForInitialLoad( mockGetResource )();
			expect( isInInitialLoad ).toEqual( false );
		} );

		it( 'should not be loading when no reading operation is in flight', () => {
			const mockGetResource = jest.fn( () => ( { lastRequested: secondBeforeNow, lastReceived: secondBeforeNow } ) );
			const isInInitialLoad = transactionsSelectors.isTransactionLoading( mockGetResource )();
			expect( isInInitialLoad ).toEqual( false );
		} );
	} );

	describe( 'isTransactionListLoading()', () => {
		it( "Returns false when a read operation isn't in flight", () => {
			const expected = false;

			const mockGetResource = jest.fn();

			mockGetResource.mockReturnValue( {
				lastRequested: secondBeforeNow,
				lastReceived: now,
			} );
			const isLoading = transactionsSelectors.isTransactionListLoading( mockGetResource )();

			expect( mockGetResource ).toHaveBeenCalledTimes( 1 );
			expect( mockGetResource ).toHaveBeenCalledWith( expectedResourceName );
			expect( isLoading ).toStrictEqual( expected );
		} );

		it( 'Returns true when a read operation is in flight', () => {
			const expected = true;

			const mockGetResource = jest.fn();

			mockGetResource.mockReturnValue( {
				lastRequested: now,
				lastReceived: secondBeforeNow,
			} );
			const isLoading = transactionsSelectors.isTransactionListLoading( mockGetResource )();

			expect( mockGetResource ).toHaveBeenCalledTimes( 1 );
			expect( mockGetResource ).toHaveBeenCalledWith( expectedResourceName );
			expect( isLoading ).toStrictEqual( expected );
		} );
	} );

	describe( 'isTransactionListWaitingForInitialLoad()', () => {
		it( 'Returns false when transactions are initialized', () => {
			const expected = false;

			const mockGetResource = jest.fn();
			mockGetResource.mockReturnValue( {
				lastRequested: secondBeforeNow,
				lastReceived: now,
			} );

			const initStatus = transactionsSelectors.isTransactionListWaitingForInitialLoad( mockGetResource )();

			expect( mockGetResource ).toHaveBeenCalledTimes( 1 );
			expect( mockGetResource ).toHaveBeenCalledWith( expectedResourceName );
			expect( initStatus ).toBe( expected );
		} );

		it( "Returns true when transactions aren't initialized", () => {
			const expected = true;

			const mockGetResource = jest.fn();
			mockGetResource.mockReturnValue( {} );

			const initStatus = transactionsSelectors.isTransactionListWaitingForInitialLoad( mockGetResource )();

			expect( mockGetResource ).toHaveBeenCalledTimes( 1 );
			expect( mockGetResource ).toHaveBeenCalledWith( expectedResourceName );
			expect( initStatus ).toBe( expected );
		} );

		it( 'Returns true when first request in flight', () => {
			const expected = true;

			const mockGetResource = jest.fn();
			mockGetResource.mockReturnValue( {
				lastRequested: secondBeforeNow,
			} );

			const initStatus = transactionsSelectors.isTransactionListWaitingForInitialLoad( mockGetResource )();

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

		it( 'Returns true when first request in flight', () => {
			const expected = true;

			const mockGetResource = jest.fn();
			mockGetResource.mockReturnValue( {
				lastRequested: secondBeforeNow,
			} );

			const initStatus = transactionsSelectors.showTransactionsPlaceholder( mockGetResource )();

			expect( mockGetResource ).toHaveBeenCalledTimes( 1 );
			expect( mockGetResource ).toHaveBeenCalledWith( expectedResourceName );
			expect( initStatus ).toBe( expected );
		} );

		it( 'Returns false when transactions are initialized', () => {
			const expected = false;

			const mockGetResource = jest.fn();
			mockGetResource.mockReturnValue( {
				lastRequested: secondBeforeNow,
				lastReceived: now,
			} );

			const showPlaceholder = transactionsSelectors.showTransactionsPlaceholder( mockGetResource )();

			expect( mockGetResource ).toHaveBeenCalledTimes( 1 );
			expect( mockGetResource ).toHaveBeenCalledWith( expectedResourceName );
			expect( showPlaceholder ).toBe( expected );
		} );
	} );
} );
