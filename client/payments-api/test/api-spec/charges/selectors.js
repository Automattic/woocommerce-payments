/** @format */

/**
 * Internal dependencies
 */
import chargesSelectors from '../../../api-spec/charges/selectors';
import { DEFAULT_REQUIREMENT } from '../../../constants';

describe( 'Charges selectors', () => {
	const now = Date.now();
	const secondBeforeNow = now - 1000;

	describe( 'getCharge()', () => {
		it( 'Returns empty object before a read operation', () => {
			const expected = {};
			const chargeId = 'ch_1';

			const mockGetResource = jest.fn();
			const mockRequireResource = jest.fn( () => expected );

			const charge = chargesSelectors.getCharge( mockGetResource, mockRequireResource )( chargeId );

			expect( mockGetResource ).not.toHaveBeenCalled();
			expect( mockRequireResource ).toHaveBeenCalledTimes( 1 );
			expect( mockRequireResource ).toHaveBeenCalledWith( DEFAULT_REQUIREMENT, chargeId );
			expect( charge ).toStrictEqual( expected );
		} );

		it( 'Returns the expected charge after a read operation', () => {
			const chargeId = 'ch_32ndsa';
			const expected = { data: { id: chargeId } };

			const mockGetResource = jest.fn();
			const mockRequireResource = jest.fn();

			mockRequireResource.mockReturnValue( { data: expected } );
			const charges = chargesSelectors.getCharge( mockGetResource, mockRequireResource )( chargeId );

			expect( mockGetResource ).not.toHaveBeenCalled();
			expect( mockRequireResource ).toHaveBeenCalledTimes( 1 );
			expect( mockRequireResource ).toHaveBeenCalledWith( DEFAULT_REQUIREMENT, chargeId );
			expect( charges ).toBe( expected );
		} );
	} );

	describe( 'getCharge state', () => {
		it( 'should be initial load when initializing', () => {
			const mockGetResource = jest.fn( () => ( { lastRequested: secondBeforeNow } ) );
			const isInInitialLoad = chargesSelectors.isChargeWaitingForInitialLoad( mockGetResource )();
			expect( isInInitialLoad ).toEqual( true );
		} );

		it( 'should be loading when initializing', () => {
			const mockGetResource = jest.fn( () => ( { lastRequested: secondBeforeNow } ) );
			const isInInitialLoad = chargesSelectors.isChargeLoading( mockGetResource )();
			expect( isInInitialLoad ).toEqual( true );
		} );

		it( 'should be loading after initialized when read operation is in flight', () => {
			const mockGetResource = jest.fn( () => ( { lastRequested: now, lastReceived: secondBeforeNow } ) );
			const isInInitialLoad = chargesSelectors.isChargeLoading( mockGetResource )();
			expect( isInInitialLoad ).toEqual( true );
		} );

		it( 'should not be initial load after initialized', () => {
			const mockGetResource = jest.fn( () => ( { lastRequested: secondBeforeNow, lastReceived: secondBeforeNow } ) );
			const isInInitialLoad = chargesSelectors.isChargeWaitingForInitialLoad( mockGetResource )();
			expect( isInInitialLoad ).toEqual( false );
		} );

		it( 'should not be loading when no reading operation is in flight', () => {
			const mockGetResource = jest.fn( () => ( { lastRequested: secondBeforeNow, lastReceived: secondBeforeNow } ) );
			const isInInitialLoad = chargesSelectors.isChargeLoading( mockGetResource )();
			expect( isInInitialLoad ).toEqual( false );
		} );
	} );
} );
