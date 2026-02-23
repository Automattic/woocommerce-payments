/**
 * Internal dependencies
 */
import { createPaymentCredential } from '../payment-credentials';

jest.mock( '../confirmation-tokens', () => ( {
	shouldUseConfirmationTokens: jest.fn(),
} ) );

import { shouldUseConfirmationTokens } from '../confirmation-tokens';

describe( 'createPaymentCredential', () => {
	let mockStripe;
	let mockElements;

	beforeEach( () => {
		mockStripe = {
			createConfirmationToken: jest.fn(),
			createPaymentMethod: jest.fn(),
		};
		mockElements = {};
	} );

	describe( 'when confirmation tokens are enabled', () => {
		beforeEach( () => {
			shouldUseConfirmationTokens.mockReturnValue( true );
		} );

		test( 'creates a confirmation token', async () => {
			mockStripe.createConfirmationToken.mockResolvedValue( {
				confirmationToken: { id: 'ctoken_123' },
			} );

			const result = await createPaymentCredential(
				mockStripe,
				mockElements
			);

			expect( mockStripe.createConfirmationToken ).toHaveBeenCalledWith( {
				elements: mockElements,
			} );
			expect( result ).toEqual( {
				id: 'ctoken_123',
				type: 'confirmation_token',
			} );
		} );

		test( 'throws on Stripe error', async () => {
			const stripeError = { message: 'Token creation failed' };
			mockStripe.createConfirmationToken.mockResolvedValue( {
				error: stripeError,
			} );

			await expect(
				createPaymentCredential( mockStripe, mockElements )
			).rejects.toEqual( stripeError );
		} );
	} );

	describe( 'when confirmation tokens are disabled', () => {
		beforeEach( () => {
			shouldUseConfirmationTokens.mockReturnValue( false );
		} );

		test( 'creates a payment method', async () => {
			mockStripe.createPaymentMethod.mockResolvedValue( {
				paymentMethod: { id: 'pm_456' },
			} );

			const result = await createPaymentCredential(
				mockStripe,
				mockElements
			);

			expect( mockStripe.createPaymentMethod ).toHaveBeenCalledWith( {
				elements: mockElements,
			} );
			expect( result ).toEqual( {
				id: 'pm_456',
				type: 'payment_method',
			} );
		} );

		test( 'throws on Stripe error', async () => {
			const stripeError = { message: 'Payment method failed' };
			mockStripe.createPaymentMethod.mockResolvedValue( {
				error: stripeError,
			} );

			await expect(
				createPaymentCredential( mockStripe, mockElements )
			).rejects.toEqual( stripeError );
		} );
	} );
} );
