/**
 * Internal dependencies
 */
import { createPaymentCredential } from '../payment-credentials';

describe( 'createPaymentCredential', () => {
	let stripeMock;
	let elementsMock;

	beforeEach( () => {
		stripeMock = {
			createConfirmationToken: jest.fn(),
			createPaymentMethod: jest.fn(),
		};
		elementsMock = {};
	} );

	describe( 'when using confirmation tokens', () => {
		test( 'creates a confirmation token and returns its id', async () => {
			stripeMock.createConfirmationToken.mockResolvedValue( {
				confirmationToken: { id: 'ctoken_123' },
			} );

			const result = await createPaymentCredential(
				stripeMock,
				elementsMock,
				true
			);

			expect( stripeMock.createConfirmationToken ).toHaveBeenCalledWith( {
				elements: elementsMock,
			} );
			expect( result ).toBe( 'ctoken_123' );
		} );

		test( 'throws on Stripe error', async () => {
			const stripeError = { message: 'Token creation failed' };
			stripeMock.createConfirmationToken.mockResolvedValue( {
				error: stripeError,
			} );

			await expect(
				createPaymentCredential( stripeMock, elementsMock, true )
			).rejects.toEqual( stripeError );
		} );
	} );

	describe( 'when using payment methods', () => {
		test( 'creates a payment method and returns its id', async () => {
			stripeMock.createPaymentMethod.mockResolvedValue( {
				paymentMethod: { id: 'pm_456' },
			} );

			const result = await createPaymentCredential(
				stripeMock,
				elementsMock,
				false
			);

			expect( stripeMock.createPaymentMethod ).toHaveBeenCalledWith( {
				elements: elementsMock,
			} );
			expect( result ).toBe( 'pm_456' );
		} );

		test( 'throws on Stripe error', async () => {
			const stripeError = { message: 'Payment method failed' };
			stripeMock.createPaymentMethod.mockResolvedValue( {
				error: stripeError,
			} );

			await expect(
				createPaymentCredential( stripeMock, elementsMock, false )
			).rejects.toEqual( stripeError );
		} );
	} );
} );
