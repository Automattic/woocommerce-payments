/** @format **/

/**
 * External dependencies
 */
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

/**
 * Internal dependencies
 */
import DismissConfirmationModal from '../capability-request-dismiss-modal';

describe( 'DismissConfirmationModal', () => {
	it( 'calls the onClose handler on cancel', async () => {
		const handleCloseMock = jest.fn();
		render( <DismissConfirmationModal onClose={ handleCloseMock } /> );

		expect( handleCloseMock ).not.toHaveBeenCalled();

		userEvent.click( screen.getByText( 'Cancel' ) );

		expect( handleCloseMock ).toHaveBeenCalled();
	} );

	it( 'calls the onSubmit handler on cancel', async () => {
		const handleConfirmMock = jest.fn();
		render( <DismissConfirmationModal onSubmit={ handleConfirmMock } /> );

		expect( handleConfirmMock ).not.toHaveBeenCalled();

		userEvent.click( screen.getByText( 'Yes, continue' ) );

		expect( handleConfirmMock ).toHaveBeenCalled();
	} );
} );
