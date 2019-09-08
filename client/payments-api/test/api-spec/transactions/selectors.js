/** @format */

/**
 * Internal dependencies
 */
import transactionsSelectors from '../../../api-spec/transactions/selectors';
import { DEFAULT_REQUIREMENT } from '../../../constants';
import { resourcePrefixes } from '../../../api-spec/transactions/constants';
import { getResourceName } from '../../../utils';

describe( 'Transactions selectors', () => {
	const identifier = {
		page: 2,
		per_page: 50,
	};
	const now = Date.now();
	const second_before_now = now - 1000;

	describe( 'getTransactionsPage()', () => {
		const expectedResourceName = getResourceName( resourcePrefixes.list, identifier );

		it( 'Returns empty object before a read operation', () => {
			const expected = {};

			const mockGetResource = jest.fn();
			const mockRequireResource = jest.fn();

			mockRequireResource.mockReturnValue( {} );
			const transactions = transactionsSelectors.getTransactionsPage( mockGetResource, mockRequireResource )(
				identifier.page, identifier.per_page
			);

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
			const transactions = transactionsSelectors.getTransactionsPage( mockGetResource, mockRequireResource )(
				identifier.page, identifier.per_page
			);

			expect( mockGetResource ).not.toHaveBeenCalled();
			expect( mockRequireResource ).toHaveBeenCalledTimes( 1 );
			expect( mockRequireResource ).toHaveBeenCalledWith( DEFAULT_REQUIREMENT, expectedResourceName );
			expect( transactions ).toBe( expected );
		} );
	} );

	describe( 'getTransactionsPageIsLoading()', () => {
		const expectedResourceName = getResourceName( resourcePrefixes.list, identifier );

		it( "Returns false when a read operation isn't in flight", () => {
			const expected = false;

			const mockGetResource = jest.fn();

			mockGetResource.mockReturnValue( {
				lastRequested: second_before_now,
				lastReceived: now,
			} );
			const isLoading = transactionsSelectors.getTransactionsPageIsLoading( mockGetResource )( identifier.page, identifier.per_page );

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
			const isLoading = transactionsSelectors.getTransactionsPageIsLoading( mockGetResource )( identifier.page, identifier.per_page );

			expect( mockGetResource ).toHaveBeenCalledTimes( 1 );
			expect( mockGetResource ).toHaveBeenCalledWith( expectedResourceName );
			expect( isLoading ).toStrictEqual( expected );
		} );
	} );

	describe( 'isWaitingForInitialPageLoad()', () => {
		const expectedResourceName = getResourceName( resourcePrefixes.list, identifier );

		it( 'Returns false when transactions are initialized', () => {
			const expected = false;

			const mockGetResource = jest.fn();
			mockGetResource.mockReturnValue( {
				data: {},
			} );

			const initStatus = transactionsSelectors.isWaitingForInitialPageLoad( mockGetResource )( identifier.page, identifier.per_page );

			expect( mockGetResource ).toHaveBeenCalledTimes( 1 );
			expect( mockGetResource ).toHaveBeenCalledWith( expectedResourceName );
			expect( initStatus ).toBe( expected );
		} );

		it( "Returns true when transactions aren't initialized", () => {
			const expected = true;

			const mockGetResource = jest.fn();
			mockGetResource.mockReturnValue( {} );

			const initStatus = transactionsSelectors.isWaitingForInitialPageLoad( mockGetResource )( identifier.page, identifier.per_page );

			expect( mockGetResource ).toHaveBeenCalledTimes( 1 );
			expect( mockGetResource ).toHaveBeenCalledWith( expectedResourceName );
			expect( initStatus ).toBe( expected );
		} );

		it( 'Returns true when first request in flight', () => {
			const expected = true;

			const mockGetResource = jest.fn();
			mockGetResource.mockReturnValue( {
				lastRequested: second_before_now,
			} );

			const initStatus = transactionsSelectors.isWaitingForInitialPageLoad( mockGetResource )( identifier.page, identifier.per_page );

			expect( mockGetResource ).toHaveBeenCalledTimes( 1 );
			expect( mockGetResource ).toHaveBeenCalledWith( expectedResourceName );
			expect( initStatus ).toBe( expected );
		} );
	} );

	describe( 'showTransactionsPagePlaceholder()', () => {
		const expectedResourceName = getResourceName( resourcePrefixes.list, identifier );

		it( "Returns true when transactions aren't initialized", () => {
			const expected = true;

			const mockGetResource = jest.fn();
			mockGetResource.mockReturnValue( {} );

			const showPlaceholder = transactionsSelectors.showTransactionsPagePlaceholder( mockGetResource )(
				identifier.page, identifier.per_page
			);

			expect( mockGetResource ).toHaveBeenCalledTimes( 1 );
			expect( mockGetResource ).toHaveBeenCalledWith( expectedResourceName );
			expect( showPlaceholder ).toBe( expected );
		} );

		it( 'Returns true when first request in flight', () => {
			const expected = true;

			const mockGetResource = jest.fn();
			mockGetResource.mockReturnValue( {
				lastRequested: second_before_now,
			} );

			const showPlaceholder = transactionsSelectors.showTransactionsPagePlaceholder( mockGetResource )(
				identifier.page, identifier.per_page
			);

			expect( mockGetResource ).toHaveBeenCalledTimes( 1 );
			expect( mockGetResource ).toHaveBeenCalledWith( expectedResourceName );
			expect( showPlaceholder ).toBe( expected );
		} );

		it( 'Returns false when transactions are initialized', () => {
			const expected = false;

			const mockGetResource = jest.fn();
			mockGetResource.mockReturnValue( {
				data: {},
			} );

			const showPlaceholder = transactionsSelectors.showTransactionsPagePlaceholder( mockGetResource )(
				identifier.page, identifier.per_page
			);

			expect( mockGetResource ).toHaveBeenCalledTimes( 1 );
			expect( mockGetResource ).toHaveBeenCalledWith( expectedResourceName );
			expect( showPlaceholder ).toBe( expected );
		} );
	} );

	describe( 'getTransactionsSummary()', () => {
		it( 'Returns empty object before read operation', () => {
			const expected = {};

			const mockGetResource = jest.fn();
			const mockRequireResource = jest.fn();
			mockRequireResource.mockReturnValue( {} );

			const summary = transactionsSelectors.getTransactionsSummary( mockGetResource, mockRequireResource )();

			expect( mockRequireResource ).toHaveBeenCalledWith( DEFAULT_REQUIREMENT, resourcePrefixes.summary );
			expect( mockRequireResource ).toHaveBeenCalledTimes( 1 );
			expect( summary ).toStrictEqual( expected );
		} );
		it( 'Returns summary after read operation', () => {
			const expected = { data: {} };

			const mockGetResource = jest.fn();
			const mockRequireResource = jest.fn();
			mockRequireResource.mockReturnValue( { data: expected } );

			const summary = transactionsSelectors.getTransactionsSummary( mockGetResource, mockRequireResource )();

			expect( mockRequireResource ).toHaveBeenCalledWith( DEFAULT_REQUIREMENT, resourcePrefixes.summary );
			expect( mockRequireResource ).toHaveBeenCalledTimes( 1 );
			expect( summary ).toBe( expected );
		} );
	} );
} );
