/** @format */

/**
 * External dependencies
 */
import { apiFetch } from '@wordpress/data-controls';
import { controls } from '@wordpress/data';

/**
 * Internal dependencies
 */
import { updateCharge, updateErrorForCharge } from '../actions';
import { getCharge, getChargeFromOrder } from '../resolvers';

const orderId = '42';

const errorResponse = { code: 'error' };

const chargeResponse = {
	data: {
		id: 'test_ch_1',
		date: 1585617029000,
		amount: 8903,
	},
};

const orderResponse = {
	data: {
		id: orderId,
		total: '8903',
	},
};

describe( 'getCharge resolver', () => {
	let generator: Generator< unknown >;

	beforeEach( () => {
		generator = getCharge( 'test_ch_1' );
		expect( generator.next().value ).toEqual(
			apiFetch( { path: '/wc/v3/payments/charges/test_ch_1' } )
		);
	} );

	afterEach( () => {
		expect( generator.next().done ).toStrictEqual( true );
	} );

	describe( 'on success', () => {
		test( 'should update state with charge data', () => {
			expect( generator.next( chargeResponse.data ).value ).toEqual(
				updateCharge( chargeResponse.data.id, chargeResponse.data )
			);
		} );
	} );

	describe( 'on error', () => {
		test( 'should update state with error on error', () => {
			expect( generator.throw( errorResponse ).value ).toEqual(
				controls.dispatch(
					'core/notices',
					'createErrorNotice',
					expect.any( String )
				)
			);
			expect( generator.next().value ).toEqual(
				updateErrorForCharge( 'test_ch_1', null, errorResponse )
			);
		} );
	} );
} );

describe( 'getChargeFromOrder resolver', () => {
	let generator: Generator< unknown >;

	beforeEach( () => {
		generator = getChargeFromOrder( orderId );
		expect( generator.next().value ).toEqual(
			apiFetch( { path: `/wc/v3/payments/charges/order/${ orderId }` } )
		);
	} );

	afterEach( () => {
		expect( generator.next().done ).toStrictEqual( true );
	} );

	describe( 'on success', () => {
		test( 'should update state with charge data', () => {
			expect( generator.next( orderResponse.data ).value ).toEqual(
				updateCharge( orderResponse.data.id, orderResponse.data )
			);
		} );
	} );

	describe( 'on error', () => {
		test( 'should update state with error on error', () => {
			expect( generator.throw( errorResponse ).value ).toEqual(
				controls.dispatch(
					'core/notices',
					'createErrorNotice',
					expect.any( String )
				)
			);
			expect( generator.next().value ).toEqual(
				updateErrorForCharge( orderId, null, errorResponse )
			);
		} );
	} );
} );
