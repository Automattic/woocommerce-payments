/** @format **/

/**
 * External dependencies
 */
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

/**
 * Internal dependencies
 */
import { useClientSecretEncryption } from 'wcpay/data';
import ClientSecretEncryptionToggle from '../client-secret-encryption-toggle';

jest.mock( '../../../data', () => ( {
	useClientSecretEncryption: jest.fn().mockReturnValue( [ true, jest.fn() ] ),
} ) );

describe( 'ClientSecretEncryptionToggle', () => {
	afterEach( () => {
		jest.clearAllMocks();
	} );

	it( 'renders the component', () => {
		render( <ClientSecretEncryptionToggle /> );

		expect(
			screen.queryByLabelText( 'Enable UPE Public Key Encryption' )
		).toBeInTheDocument();
	} );

	it.each( [ [ true ], [ false ] ] )(
		'updates client secret encryption enabled state to %s when toggling checkbox',
		( isEnabled ) => {
			const updateIsClientSecretEncryptionEnabledMock = jest.fn();
			useClientSecretEncryption.mockReturnValue( [
				isEnabled,
				updateIsClientSecretEncryptionEnabledMock,
			] );

			render( <ClientSecretEncryptionToggle /> );

			const enableClientSecretEncryptionCheckbox = screen.getByLabelText(
				'Enable UPE Public Key Encryption'
			);

			userEvent.click( enableClientSecretEncryptionCheckbox );
			expect(
				updateIsClientSecretEncryptionEnabledMock
			).toHaveBeenCalledWith( ! isEnabled );
		}
	);
} );
