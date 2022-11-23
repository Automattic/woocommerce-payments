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
import { PaymentIntent } from '../../../types/payment-intents';
import { paymentIntentId, paymentIntentMock } from './hooks';

const errorResponse = { code: 'error' };

const paymentIntentResponse: { data: PaymentIntent } = {
	data: paymentIntentMock,
};

describe( 'getPaymentIntent resolver', () => {
	let generator: Generator< unknown >;

	beforeEach( () => {
		generator = getPaymentIntent( paymentIntentId );
		expect( generator.next().value ).toEqual(
			apiFetch( {
				path: `/wc/v3/payments/payment_intents/${ paymentIntentId }`,
			} )
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
				updateErrorForPaymentIntent( paymentIntentId, errorResponse )
			);
		} );
	} );
} );
