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
			expect( result ).toEqual( {
				id: 'ctoken_123',
				setupFutureUsage: null,
			} );
		} );

		// Read back off the token rather than echoed from the Elements options, so the
		// server learns what Stripe actually recorded.
		test( "reports the token's own setup_future_usage", async () => {
			stripeMock.createConfirmationToken.mockResolvedValue( {
				confirmationToken: {
					id: 'ctoken_123',
					setup_future_usage: 'off_session',
				},
			} );

			const result = await createPaymentCredential(
				stripeMock,
				elementsMock,
				true
			);

			expect( result ).toEqual( {
				id: 'ctoken_123',
				setupFutureUsage: 'off_session',
			} );
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
			// A PaymentMethod pins no setup_future_usage, so there is nothing to report.
			expect( result ).toEqual( {
				id: 'pm_456',
				setupFutureUsage: null,
			} );
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
