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

	afterEach( () => {
		delete window.wcpayExpressCheckoutParams;
	} );

	describe( 'when confirmation tokens are enabled', () => {
		beforeEach( () => {
			window.wcpayExpressCheckoutParams = {
				flags: { isEceUsingConfirmationTokens: true },
			};
		} );

		test( 'creates a confirmation token', async () => {
			stripeMock.createConfirmationToken.mockResolvedValue( {
				confirmationToken: { id: 'ctoken_123' },
			} );

			const result = await createPaymentCredential(
				stripeMock,
				elementsMock
			);

			expect( stripeMock.createConfirmationToken ).toHaveBeenCalledWith( {
				elements: elementsMock,
			} );
			expect( result ).toEqual( {
				id: 'ctoken_123',
				type: 'confirmation_token',
			} );
		} );

		test( 'throws on Stripe error', async () => {
			const stripeError = { message: 'Token creation failed' };
			stripeMock.createConfirmationToken.mockResolvedValue( {
				error: stripeError,
			} );

			await expect(
				createPaymentCredential( stripeMock, elementsMock )
			).rejects.toEqual( stripeError );
		} );
	} );

	describe( 'when confirmation tokens are disabled', () => {
		beforeEach( () => {
			window.wcpayExpressCheckoutParams = {
				flags: { isEceUsingConfirmationTokens: false },
			};
		} );

		test( 'creates a payment method', async () => {
			stripeMock.createPaymentMethod.mockResolvedValue( {
				paymentMethod: { id: 'pm_456' },
			} );

			const result = await createPaymentCredential(
				stripeMock,
				elementsMock
			);

			expect( stripeMock.createPaymentMethod ).toHaveBeenCalledWith( {
				elements: elementsMock,
			} );
			expect( result ).toEqual( {
				id: 'pm_456',
				type: 'payment_method',
			} );
		} );

		test( 'throws on Stripe error', async () => {
			const stripeError = { message: 'Payment method failed' };
			stripeMock.createPaymentMethod.mockResolvedValue( {
				error: stripeError,
			} );

			await expect(
				createPaymentCredential( stripeMock, elementsMock )
			).rejects.toEqual( stripeError );
		} );
	} );
} );
