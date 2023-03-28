/** @format */

/**
 * Internal dependencies
 */
import { chargeId, chargeMock } from '../../payment-intents/test/hooks';
import {
	getCharge,
	getChargeError,
	getChargeFromOrder,
	getChargeFromOrderError,
} from '../selectors';

const chargeStateMock = {
	data: chargeMock,
	error: { code: 'error' },
};

const stateMock = {
	charges: {
		[ chargeId ]: chargeStateMock,
	},
};

describe( 'Charges data selectors', () => {
	describe( 'getCharge', () => {
		it( 'should return the charge data', () => {
			const result = getCharge( stateMock, chargeId );
			expect( result ).toEqual( chargeMock );
		} );

		it( 'should return an empty object if the id is not present in the state', () => {
			const result = getCharge( stateMock, 'not-found' );
			expect( result ).toEqual( {} );
		} );
	} );

	describe( 'getChargeError', () => {
		it( 'should return the charge error data', () => {
			const result = getChargeError( stateMock, chargeId );
			expect( result ).toEqual( chargeStateMock.error );
		} );

		it( 'should return an empty object if the id is not present in the state', () => {
			const result = getChargeError( stateMock, 'not-found' );
			expect( result ).toEqual( {} );
		} );
	} );

	describe( 'getChargeFromOrder', () => {
		it( 'should return the charge data', () => {
			const result = getChargeFromOrder( stateMock, chargeId );
			expect( result ).toEqual( chargeMock );
		} );

		it( 'should return an empty object if the id is not present in the state', () => {
			const result = getChargeFromOrder( stateMock, 'not-found' );
			expect( result ).toEqual( {} );
		} );
	} );

	describe( 'getChargeFromOrderError', () => {
		it( 'should return the charge error data', () => {
			const result = getChargeFromOrderError( stateMock, chargeId );
			expect( result ).toEqual( chargeStateMock.error );
		} );

		it( 'should return an empty object if the id is not present in the state', () => {
			const result = getChargeFromOrderError( stateMock, 'not-found' );
			expect( result ).toEqual( {} );
		} );
	} );
} );
