/** @format */

/**
 * External dependencies
 */
import { useSelect } from '@wordpress/data';

/**
 * Internal dependencies
 */
import { useCharge, useChargeFromOrder } from '../';
import { STORE_NAME } from '../../constants';
import { chargeId, chargeMock } from '../../payment-intents/test/hooks';

jest.mock( '@wordpress/data' );

const orderId = '42';

describe( 'Charges data hooks', () => {
	let selectors: Record< string, () => any >;

	beforeEach( () => {
		selectors = {};

		const selectMock = jest.fn( ( storeName ) =>
			STORE_NAME === storeName ? selectors : {}
		);

		( useSelect as jest.Mock ).mockImplementation(
			( cb: ( callback: any ) => jest.Mock ) => cb( selectMock )
		);
	} );

	describe( 'useCharge', () => {
		it( 'should return the correct data', async () => {
			selectors = {
				getCharge: jest.fn().mockReturnValue( chargeMock ),
				getChargeError: jest.fn().mockReturnValue( undefined ),
				isResolving: jest.fn().mockReturnValue( false ),
				hasFinishedResolution: jest.fn().mockReturnValue( true ),
			};

			const result = useCharge( chargeId );

			expect( result ).toEqual( {
				data: chargeMock,
				error: undefined,
				isLoading: false,
			} );
		} );

		it( 'should return the correct error', async () => {
			const error = { code: 'error' };

			selectors = {
				getCharge: jest.fn().mockReturnValue( undefined ),
				getChargeError: jest.fn().mockReturnValue( error ),
				isResolving: jest.fn().mockReturnValue( false ),
				hasFinishedResolution: jest.fn().mockReturnValue( true ),
			};

			const result = useCharge( chargeId );

			expect( result ).toEqual( {
				data: undefined,
				error: error,
				isLoading: false,
			} );
		} );
	} );

	describe( 'useChargeFromOrder', () => {
		it( 'should return the correct data', async () => {
			selectors = {
				getChargeFromOrder: jest.fn().mockReturnValue( chargeMock ),
				getChargeFromOrderError: jest.fn().mockReturnValue( undefined ),
				isResolving: jest.fn().mockReturnValue( false ),
				hasFinishedResolution: jest.fn().mockReturnValue( true ),
			};

			const result = useChargeFromOrder( orderId );

			expect( result ).toEqual( {
				data: chargeMock,
				error: undefined,
				isLoading: false,
			} );
		} );

		it( 'should return the correct error', async () => {
			const error = { code: 'error' };

			selectors = {
				getChargeFromOrder: jest.fn().mockReturnValue( undefined ),
				getChargeFromOrderError: jest.fn().mockReturnValue( error ),
				isResolving: jest.fn().mockReturnValue( false ),
				hasFinishedResolution: jest.fn().mockReturnValue( true ),
			};

			const result = useChargeFromOrder( orderId );

			expect( result ).toEqual( {
				data: undefined,
				error: error,
				isLoading: false,
			} );
		} );
	} );
} );
