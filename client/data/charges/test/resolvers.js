/** @format */

/**
 * External dependencies
 */
import { apiFetch, dispatch } from '@wordpress/data-controls';

/**
 * Internal dependencies
 */
import { updateCharge, updateErrorForCharge } from '../actions';
import { getCharge } from '../resolvers';

const errorResponse = { code: 'error' };

const chargeResponse = {
	data: {
		id: 'test_ch_1',
		date: 1585617029000,
		amount: 8903,
	},
};

describe( 'getCharge resolver', () => {
	let generator = null;

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
				dispatch(
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
