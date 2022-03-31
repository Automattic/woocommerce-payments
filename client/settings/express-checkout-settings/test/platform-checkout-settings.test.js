/** @format */

/**
 * External dependencies
 */
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

/**
 * Internal dependencies
 */
import PlatformCheckoutSettings from '../platform-checkout-settings';
import {
	usePlatformCheckoutEnabledSettings,
	usePlatformCheckoutCustomMessage,
} from '../../../data';

jest.mock( '../../../data', () => ( {
	usePlatformCheckoutEnabledSettings: jest.fn(),
	usePlatformCheckoutCustomMessage: jest.fn(),
} ) );

const getMockPlatformCheckoutEnabledSettings = (
	isEnabled,
	updateIsPlatformCheckoutEnabledHandler
) => [ isEnabled, updateIsPlatformCheckoutEnabledHandler ];

const getMockPlatformCheckoutCustomMessage = (
	message,
	updatePlatformCheckoutCustomMessageHandler
) => [ message, updatePlatformCheckoutCustomMessageHandler ];

describe( 'PlatformCheckoutSettings', () => {
	beforeEach( () => {
		usePlatformCheckoutEnabledSettings.mockReturnValue(
			getMockPlatformCheckoutEnabledSettings( true, jest.fn() )
		);

		usePlatformCheckoutCustomMessage.mockReturnValue(
			getMockPlatformCheckoutCustomMessage( '', jest.fn() )
		);
	} );

	it( 'renders settings with defaults', () => {
		render( <PlatformCheckoutSettings section="general" /> );

		// confirm checkbox groups displayed
		const [ enableCheckbox ] = screen.queryAllByRole( 'checkbox' );

		expect( enableCheckbox ).toBeInTheDocument();

		// confirm settings headings
		expect(
			screen.queryByRole( 'heading', { name: 'Custom message' } )
		).toBeInTheDocument();

		// confirm radio button groups displayed
		const customMessageTextbox = screen.queryByRole( 'textbox' );

		expect( customMessageTextbox ).toBeInTheDocument();
	} );

	it( 'triggers the hooks when the enable setting is being interacted with', () => {
		const updateIsPlatformCheckoutEnabledHandler = jest.fn();

		usePlatformCheckoutEnabledSettings.mockReturnValue(
			getMockPlatformCheckoutEnabledSettings(
				true,
				updateIsPlatformCheckoutEnabledHandler
			)
		);

		render( <PlatformCheckoutSettings section="general" /> );

		expect( updateIsPlatformCheckoutEnabledHandler ).not.toHaveBeenCalled();

		userEvent.click( screen.getByLabelText( /Enable Platform Checkout/ ) );
		expect( updateIsPlatformCheckoutEnabledHandler ).toHaveBeenCalledWith(
			false
		);
	} );

	it( 'triggers the hooks when the custom message setting is being interacted with', () => {
		const updatePlatformCheckoutCustomMessageHandler = jest.fn();

		usePlatformCheckoutCustomMessage.mockReturnValue(
			getMockPlatformCheckoutCustomMessage(
				'',
				updatePlatformCheckoutCustomMessageHandler
			)
		);

		render( <PlatformCheckoutSettings section="general" /> );

		expect(
			updatePlatformCheckoutCustomMessageHandler
		).not.toHaveBeenCalled();

		userEvent.type( screen.getByRole( 'textbox' ), 'test' );
		expect(
			updatePlatformCheckoutCustomMessageHandler
		).toHaveBeenLastCalledWith( 'test' );
	} );
} );
