/** @format **/

/**
 * External dependencies
 */
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

/**
 * Internal dependencies
 */
import DisableConfirmationModal from '..';

jest.mock( '../../data', () => ( {
	useEnabledPaymentMethodIds: jest
		.fn()
		.mockReturnValue( [ [ 'card', 'giropay', 'link' ] ] ),
	useWooPayEnabledSettings: jest.fn().mockReturnValue( [ true ] ),
	usePaymentRequestEnabledSettings: jest.fn().mockReturnValue( [ true ] ),
} ) );

describe( 'DisableConfirmationModal', () => {
	it( 'calls the onClose handler on cancel', async () => {
		const handleCloseMock = jest.fn();
		render( <DisableConfirmationModal onClose={ handleCloseMock } /> );

		expect( handleCloseMock ).not.toHaveBeenCalled();

		userEvent.click( screen.getByText( 'Cancel' ) );

		expect( handleCloseMock ).toHaveBeenCalled();
	} );

	it( 'calls the onConfirm handler on cancel', async () => {
		const handleConfirmMock = jest.fn();
		render( <DisableConfirmationModal onConfirm={ handleConfirmMock } /> );

		expect( handleConfirmMock ).not.toHaveBeenCalled();

		userEvent.click( screen.getByText( 'Disable' ) );

		expect( handleConfirmMock ).toHaveBeenCalled();
	} );
} );
