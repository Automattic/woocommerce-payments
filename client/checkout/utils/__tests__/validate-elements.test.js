/**
 * Internal dependencies
 */
import { validateElements } from '../validate-elements';

describe( 'validateElements', () => {
	it( 'rejects with the Stripe error message and type', async () => {
		const elements = {
			submit: jest.fn().mockResolvedValue( {
				error: {
					type: 'validation_error',
					message: 'Your card number is incomplete.',
				},
			} ),
		};

		await expect( validateElements( elements ) ).rejects.toMatchObject( {
			message: 'Your card number is incomplete.',
			type: 'validation_error',
		} );
	} );

	it( 'resolves when Stripe reports no error', async () => {
		const elements = {
			submit: jest.fn().mockResolvedValue( {} ),
		};

		await expect( validateElements( elements ) ).resolves.toBeUndefined();
	} );
} );
