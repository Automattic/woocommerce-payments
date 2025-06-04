/**
 * Internal dependencies
 */
import { transformStripePaymentMethodForStoreApi } from '../stripe-to-wc';

describe( 'stripe-to-wc transformers', () => {
	describe( 'transformStripePaymentMethodForStoreApi', () => {
		it( 'should include customer_password when provided', () => {
			const result = transformStripePaymentMethodForStoreApi(
				{
					customer_password: 'magic133t',
					billingDetails: {
						name: 'John Doe',
						email: 'john@example.com',
					},
				},
				'pm_1234567890abcdef'
			);

			expect( result.customer_password ).toBe( 'magic133t' );
		} );

		it( 'should not include customer_password when empty string', () => {
			const result = transformStripePaymentMethodForStoreApi(
				{
					customer_password: '',
					billingDetails: {
						name: 'John Doe',
						email: 'john@example.com',
					},
				},
				'pm_1234567890abcdef'
			);

			expect( result.customer_password ).toBeUndefined();
		} );

		it( 'should not include customer_password when not provided', () => {
			const result = transformStripePaymentMethodForStoreApi(
				{
					billingDetails: {
						name: 'John Doe',
						email: 'john@example.com',
					},
				},
				'pm_1234567890abcdef'
			);

			expect( result.customer_password ).toBeUndefined();
		} );
	} );
} );
