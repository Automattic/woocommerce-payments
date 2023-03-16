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
	usePlatformCheckoutStoreLogo,
	usePaymentRequestButtonType,
	usePaymentRequestButtonSize,
	usePaymentRequestButtonTheme,
	usePlatformCheckoutLocations,
} from '../../../data';

jest.mock( '../../../data', () => ( {
	usePlatformCheckoutEnabledSettings: jest.fn(),
	usePlatformCheckoutCustomMessage: jest.fn(),
	usePlatformCheckoutStoreLogo: jest.fn(),
	usePaymentRequestButtonType: jest.fn(),
	usePaymentRequestButtonSize: jest.fn(),
	usePaymentRequestButtonTheme: jest.fn(),
	usePlatformCheckoutLocations: jest.fn(),
} ) );

jest.mock( '@wordpress/data', () => ( {
	useDispatch: jest.fn( () => ( { createErrorNotice: jest.fn() } ) ),
} ) );

const getMockPlatformCheckoutEnabledSettings = (
	isEnabled,
	updateIsPlatformCheckoutEnabledHandler
) => [ isEnabled, updateIsPlatformCheckoutEnabledHandler ];

const getMockPlatformCheckoutCustomMessage = (
	message,
	updatePlatformCheckoutCustomMessageHandler
) => [ message, updatePlatformCheckoutCustomMessageHandler ];
const getMockPlatformCheckoutStoreLogo = (
	message,
	updatePlatformCheckoutStoreLogoHandler
) => [ message, updatePlatformCheckoutStoreLogoHandler ];

const getMockPaymentRequestButtonType = (
	message,
	updatePaymentRequestButtonTypeHandler
) => [ message, updatePaymentRequestButtonTypeHandler ];
const getMockPaymentRequestButtonSize = (
	message,
	updatePaymentRequestButtonSizeHandler
) => [ message, updatePaymentRequestButtonSizeHandler ];
const getMockPaymentRequestButtonTheme = (
	message,
	updatePaymentRequestButtonThemeHandler
) => [ message, updatePaymentRequestButtonThemeHandler ];
const getMockPlatformCheckoutLocations = (
	message,
	updatePlatformCheckoutLocationsHandler
) => [ message, updatePlatformCheckoutLocationsHandler ];

describe( 'PlatformCheckoutSettings', () => {
	beforeEach( () => {
		usePlatformCheckoutEnabledSettings.mockReturnValue(
			getMockPlatformCheckoutEnabledSettings( true, jest.fn() )
		);

		usePlatformCheckoutCustomMessage.mockReturnValue(
			getMockPlatformCheckoutCustomMessage( '', jest.fn() )
		);

		usePlatformCheckoutStoreLogo.mockReturnValue(
			getMockPlatformCheckoutStoreLogo( '', jest.fn() )
		);

		usePaymentRequestButtonType.mockReturnValue(
			getMockPaymentRequestButtonType( [ 'buy' ], jest.fn() )
		);

		usePaymentRequestButtonSize.mockReturnValue(
			getMockPaymentRequestButtonSize( [ 'default' ], jest.fn() )
		);

		usePaymentRequestButtonTheme.mockReturnValue(
			getMockPaymentRequestButtonTheme( [ 'dark' ], jest.fn() )
		);

		usePlatformCheckoutLocations.mockReturnValue(
			getMockPlatformCheckoutLocations( [ true, true, true ], jest.fn() )
		);

		global.wcpaySettings = {
			restUrl: 'http://example.com/wp-json/',
		};
	} );

	it( 'renders settings with defaults', () => {
		render( <PlatformCheckoutSettings section="enable" /> );

		// confirm checkbox groups displayed
		const [ enableCheckbox ] = screen.queryAllByRole( 'checkbox' );

		expect( enableCheckbox ).toBeInTheDocument();
	} );

	it( 'triggers the hooks when the enable setting is being interacted with', () => {
		const updateIsPlatformCheckoutEnabledHandler = jest.fn();

		usePlatformCheckoutEnabledSettings.mockReturnValue(
			getMockPlatformCheckoutEnabledSettings(
				true,
				updateIsPlatformCheckoutEnabledHandler
			)
		);

		render( <PlatformCheckoutSettings section="enable" /> );

		expect( updateIsPlatformCheckoutEnabledHandler ).not.toHaveBeenCalled();

		userEvent.click( screen.getByLabelText( /Enable WooPay/ ) );
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

		render( <PlatformCheckoutSettings section="appearance" /> );

		// confirm settings headings
		expect(
			screen.queryByRole( 'heading', { name: 'Custom message' } )
		).toBeInTheDocument();

		// confirm radio button groups displayed
		const customMessageTextbox = screen.queryByRole( 'textbox' );

		expect( customMessageTextbox ).toBeInTheDocument();

		expect(
			updatePlatformCheckoutCustomMessageHandler
		).not.toHaveBeenCalled();

		userEvent.type( screen.getByRole( 'textbox' ), 'test' );
		expect(
			updatePlatformCheckoutCustomMessageHandler
		).toHaveBeenLastCalledWith( 'test' );
	} );
} );
