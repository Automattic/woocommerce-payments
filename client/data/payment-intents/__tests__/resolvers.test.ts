/** @format */

/**
 * External dependencies
 */
import { apiFetch } from '@wordpress/data-controls';
import { controls } from '@wordpress/data';

/**
 * Internal dependencies
 */
import { updatePaymentIntent, updateErrorForPaymentIntent } from '../actions';
import { getPaymentIntent } from '../resolvers';
import { PaymentIntent } from 'wcpay/types/payment-intents';
import { paymentIntentId, paymentIntentMock } from '../__fixtures__';

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
				controls.dispatch(
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

describe( 'getPaymentIntent resolver with an invalid ID', () => {
	const invalidId = '..';

	test( 'should record an error without requesting another endpoint', () => {
		const generator = getPaymentIntent( invalidId );

		expect( generator.next().value ).toEqual(
			controls.dispatch(
				'core/notices',
				'createErrorNotice',
				expect.any( String )
			)
		);
		expect( generator.next().value ).toEqual(
			updateErrorForPaymentIntent(
				'..',
				expect.objectContaining( {
					code: 'wcpay_invalid_path_segment',
				} )
			)
		);
		expect( generator.next().done ).toStrictEqual( true );
	} );

	test( 'should encode characters that would change the request', () => {
		const generator = getPaymentIntent( 'pi_1/refund?x=1' );

		expect( generator.next().value ).toEqual(
			apiFetch( {
				path: '/wc/v3/payments/payment_intents/pi_1%2Frefund%3Fx%3D1',
			} )
		);
	} );
} );
