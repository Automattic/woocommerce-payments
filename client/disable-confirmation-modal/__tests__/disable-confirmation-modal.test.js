/** @format **/

/**
 * External dependencies
 */
import React from 'react';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

/**
 * Internal dependencies
 */
import DisableConfirmationModal from '..';
import WCPaySettingsContext from 'wcpay/settings/wcpay-settings-context';

jest.mock( '../../data', () => ( {
	useEnabledPaymentMethodIds: jest
		.fn()
		.mockReturnValue( [ [ 'card', 'giropay', 'link' ] ] ),
	useWooPayEnabledSettings: jest.fn().mockReturnValue( [ true ] ),
	usePaymentRequestEnabledSettings: jest.fn().mockReturnValue( [ true ] ),
	useAmazonPayEnabledSettings: jest.fn().mockReturnValue( [ false ] ),
} ) );

const renderWithSettingsProvider = ( ui ) =>
	render(
		<WCPaySettingsContext.Provider
			value={ { featureFlags: { amazonPay: false } } }
		>
			{ ui }
		</WCPaySettingsContext.Provider>
	);

describe( 'DisableConfirmationModal', () => {
	it( 'calls the onClose handler on cancel', async () => {
		const handleCloseMock = jest.fn();
		renderWithSettingsProvider(
			<DisableConfirmationModal onClose={ handleCloseMock } />
		);

		expect( handleCloseMock ).not.toHaveBeenCalled();

		await userEvent.click( screen.getByText( 'Cancel' ) );

		expect( handleCloseMock ).toHaveBeenCalled();
	} );

	it( 'calls the onConfirm handler on cancel', async () => {
		const handleConfirmMock = jest.fn();
		renderWithSettingsProvider(
			<DisableConfirmationModal onConfirm={ handleConfirmMock } />
		);

		expect( handleConfirmMock ).not.toHaveBeenCalled();

		await userEvent.click( screen.getByText( 'Disable' ) );

		expect( handleConfirmMock ).toHaveBeenCalled();
	} );
} );
