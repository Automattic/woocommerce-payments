/**
 * External dependencies
 */
import React from 'react';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

/**
 * Internal dependencies
 */
import WizardTaskContext from '../../wizard/task/context';
import WcPayUpeContext from '../../../settings/wcpay-upe-toggle/context';

import EnableUpePreviewTask from '../enable-upe-preview-task';

describe( 'EnableUpePreviewTask', () => {
	it( 'should enable the UPE flag when clicking the "Enable" button', async () => {
		const setCompletedMock = jest.fn();
		const setIsUpeEnabledMock = jest.fn().mockResolvedValue( true );

		render(
			<WcPayUpeContext.Provider
				value={ {
					setIsUpeEnabled: setIsUpeEnabledMock,
					status: 'resolved',
					isUpeEnabled: false,
				} }
			>
				<WizardTaskContext.Provider
					value={ { setCompleted: setCompletedMock } }
				>
					<EnableUpePreviewTask />
				</WizardTaskContext.Provider>
			</WcPayUpeContext.Provider>
		);

		expect( setCompletedMock ).not.toHaveBeenCalled();
		expect( setIsUpeEnabledMock ).not.toHaveBeenCalled();

		userEvent.click( screen.getByText( 'Enable' ) );

		expect( setIsUpeEnabledMock ).toHaveBeenCalledWith( true );
		await waitFor( () => expect( setIsUpeEnabledMock ).toHaveReturned() );
		expect( setCompletedMock ).toHaveBeenCalledWith(
			true,
			// change this to the second step's ID, once implemented
			'setup-complete'
		);
	} );
} );
