/** @format */

/**
 * External dependencies
 */
import { apiFetch, dispatch } from '@wordpress/data-controls';

/**
 * Internal dependencies
 */
import { updatePaymentIntent, updateErrorForPaymentIntent } from '../actions';
import { getPaymentIntent } from '../resolvers';

const errorResponse = { code: 'error' };

const paymentIntentResponse = {
	data: {
		id: 'pi_test_1',
		amount: 8903,
		currency: 'USD',
		charge: {
			id: 'ch_test_1',
			amount: 8903,
			created: {
				date: '2022-06-09 20:23:55.000000',
				timezone_type: 3,
				timezone: 'UTC',
			},
			payment_method_details: {
				card: {},
				type: 'card',
			},
		},
		created: {
			date: '2022-06-09 20:23:55.000000',
			timezone_type: 3,
			timezone: 'UTC',
		},
		customer: 'cus_test',
		metadata: {},
		payment_method: 'pm_test',
		status: 'requires_capture',
	},
};

describe( 'getPaymentIntent resolver', () => {
	let generator = null;

	beforeEach( () => {
		generator = getPaymentIntent( 'pi_test_1' );
		expect( generator.next().value ).toEqual(
			apiFetch( { path: '/wc/v3/payments/payment_intents/pi_test_1' } )
		);
	} );

	afterEach( () => {
		expect( generator.next().done ).toStrictEqual( true );
	} );

	describe( 'on success', () => {
		test( 'should update state with payment intent data', () => {
			expect(
				generator.next( paymentIntentResponse.data ).value
			).toEqual(
				updatePaymentIntent(
					paymentIntentResponse.data.id,
					paymentIntentResponse.data
				)
			);
		} );
	} );

	describe( 'on error', () => {
		test( 'should update state with error', () => {
			expect( generator.throw( errorResponse ).value ).toEqual(
				dispatch(
					'core/notices',
					'createErrorNotice',
					expect.any( String )
				)
			);
			expect( generator.next().value ).toEqual(
				updateErrorForPaymentIntent( 'pi_test_1', null, errorResponse )
			);
		} );
	} );
} );
