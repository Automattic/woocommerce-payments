/** @format */

/**
 * External dependencies
 */
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

/**
 * Internal dependencies
 */
import WooPaySettings from '../woopay-settings';
import {
	useWooPayEnabledSettings,
	useWooPayCustomMessage,
	useWooPayStoreLogo,
	usePaymentRequestButtonType,
	usePaymentRequestButtonSize,
	usePaymentRequestButtonTheme,
	useWooPayLocations,
	useWooPayShowIncompatibilityNotice,
} from '../../../data';

jest.mock( '../../../data', () => ( {
	useWooPayEnabledSettings: jest.fn(),
	useWooPayCustomMessage: jest.fn(),
	useWooPayStoreLogo: jest.fn(),
	usePaymentRequestButtonType: jest.fn(),
	usePaymentRequestButtonSize: jest.fn(),
	usePaymentRequestButtonTheme: jest.fn(),
	useWooPayLocations: jest.fn(),
	useWooPayShowIncompatibilityNotice: jest.fn().mockReturnValue( false ),
} ) );

jest.mock( '@wordpress/data', () => ( {
	useDispatch: jest.fn( () => ( { createErrorNotice: jest.fn() } ) ),
} ) );

const getMockWooPayEnabledSettings = (
	isEnabled,
	updateIsWooPayEnabledHandler
) => [ isEnabled, updateIsWooPayEnabledHandler ];

const getMockWooPayCustomMessage = (
	message,
	updateWooPayCustomMessageHandler
) => [ message, updateWooPayCustomMessageHandler ];
const getMockWooPayStoreLogo = ( message, updateWooPayStoreLogoHandler ) => [
	message,
	updateWooPayStoreLogoHandler,
];

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
const getMockWooPayLocations = ( message, updateWooPayLocationsHandler ) => [
	message,
	updateWooPayLocationsHandler,
];

describe( 'WooPaySettings', () => {
	beforeEach( () => {
		useWooPayEnabledSettings.mockReturnValue(
			getMockWooPayEnabledSettings( true, jest.fn() )
		);

		useWooPayCustomMessage.mockReturnValue(
			getMockWooPayCustomMessage( '', jest.fn() )
		);

		useWooPayStoreLogo.mockReturnValue(
			getMockWooPayStoreLogo( '', jest.fn() )
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

		useWooPayLocations.mockReturnValue(
			getMockWooPayLocations( [ true, true, true ], jest.fn() )
		);

		global.wcpaySettings = {
			restUrl: 'http://example.com/wp-json/',
		};
	} );

	it( 'renders settings with defaults', () => {
		render( <WooPaySettings section="enable" /> );

		// confirm checkbox groups displayed
		const [ enableCheckbox ] = screen.queryAllByRole( 'checkbox' );

		expect( enableCheckbox ).toBeInTheDocument();
	} );

	it( 'triggers the hooks when the enable setting is being interacted with', () => {
		const updateIsWooPayEnabledHandler = jest.fn();

		useWooPayEnabledSettings.mockReturnValue(
			getMockWooPayEnabledSettings( true, updateIsWooPayEnabledHandler )
		);

		render( <WooPaySettings section="enable" /> );

		expect( updateIsWooPayEnabledHandler ).not.toHaveBeenCalled();

		userEvent.click( screen.getByLabelText( /Enable WooPay/ ) );
		expect( updateIsWooPayEnabledHandler ).toHaveBeenCalledWith( false );
	} );

	it( 'triggers the hooks when the custom message setting is being interacted with', () => {
		const updateWooPayCustomMessageHandler = jest.fn();

		useWooPayCustomMessage.mockReturnValue(
			getMockWooPayCustomMessage( '', updateWooPayCustomMessageHandler )
		);

		render( <WooPaySettings section="appearance" /> );

		// confirm settings headings
		expect(
			screen.queryByRole( 'heading', { name: 'Custom message' } )
		).toBeInTheDocument();

		// confirm radio button groups displayed
		const customMessageTextbox = screen.queryByRole( 'textbox' );

		expect( customMessageTextbox ).toBeInTheDocument();

		expect( updateWooPayCustomMessageHandler ).not.toHaveBeenCalled();

		userEvent.type( screen.getByRole( 'textbox' ), 'test' );
		expect( updateWooPayCustomMessageHandler ).toHaveBeenLastCalledWith(
			'test'
		);
	} );

	it( 'triggers the hooks when the enable setting is being interacted with', () => {
		useWooPayShowIncompatibilityNotice.mockReturnValue( true );

		render( <WooPaySettings section="enable" /> );

		expect(
			screen.queryByText(
				'One or more of your extensions are incompatible with WooPay.'
			)
		).toBeInTheDocument();
	} );

	it( 'triggers the hooks when the enable setting is being interacted with', () => {
		useWooPayShowIncompatibilityNotice.mockReturnValue( false );

		render( <WooPaySettings section="enable" /> );

		expect(
			screen.queryByText(
				'One or more of your extensions are incompatible with WooPay.'
			)
		).not.toBeInTheDocument();
	} );
} );
