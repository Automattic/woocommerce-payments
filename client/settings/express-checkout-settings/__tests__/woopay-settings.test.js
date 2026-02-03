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
	useEnabledPaymentMethodIds,
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
	useEnabledPaymentMethodIds: jest.fn(),
	useWooPayEnabledSettings: jest.fn(),
	useWooPayCustomMessage: jest.fn(),
	useWooPayStoreLogo: jest.fn(),
	usePaymentRequestButtonType: jest.fn(),
	usePaymentRequestButtonSize: jest.fn(),
	usePaymentRequestButtonTheme: jest.fn(),
	useWooPayLocations: jest.fn(),
	useWooPayShowIncompatibilityNotice: jest.fn().mockReturnValue( false ),
	useWooPayGlobalThemeSupportEnabledSettings: jest
		.fn()
		.mockReturnValue( [ false, jest.fn() ] ),
} ) );

jest.mock( '@wordpress/data', () => ( {
	useDispatch: jest.fn( () => ( { createErrorNotice: jest.fn() } ) ),
} ) );

jest.mock( '@woocommerce/components', () => ( {
	Link: jest
		.fn()
		.mockImplementation( ( { href, children } ) => (
			<a href={ href }>{ children }</a>
		) ),
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
		useEnabledPaymentMethodIds.mockReturnValue( [ [], jest.fn() ] );

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
			getMockPaymentRequestButtonType( [ 'default' ], jest.fn() )
		);

		usePaymentRequestButtonSize.mockReturnValue(
			getMockPaymentRequestButtonSize( [ 'small' ], jest.fn() )
		);

		usePaymentRequestButtonTheme.mockReturnValue(
			getMockPaymentRequestButtonTheme( [ 'dark' ], jest.fn() )
		);

		useWooPayLocations.mockReturnValue(
			getMockWooPayLocations( [ true, true, true ], jest.fn() )
		);

		useWooPayShowIncompatibilityNotice.mockReturnValue( false );

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

	it( 'triggers the hooks when the enable setting is being interacted with', async () => {
		const updateIsWooPayEnabledHandler = jest.fn();

		useWooPayEnabledSettings.mockReturnValue(
			getMockWooPayEnabledSettings( true, updateIsWooPayEnabledHandler )
		);

		render( <WooPaySettings section="enable" /> );

		expect( updateIsWooPayEnabledHandler ).not.toHaveBeenCalled();

		await userEvent.click( screen.getByLabelText( /Enable WooPay/ ) );
		expect( updateIsWooPayEnabledHandler ).toHaveBeenCalledWith( false );
	} );

	it( 'triggers the hooks when the custom message setting is being interacted with', async () => {
		const updateWooPayCustomMessageHandler = jest.fn();

		useWooPayCustomMessage.mockReturnValue(
			getMockWooPayCustomMessage( '', updateWooPayCustomMessageHandler )
		);

		render( <WooPaySettings section="appearance" /> );

		// confirm settings labels
		expect(
			screen.queryByRole( 'textbox', {
				name: 'Checkout policies',
			} )
		).toBeInTheDocument();

		// confirm radio button groups displayed
		const customMessageTextbox = screen.queryByRole( 'textbox' );

		expect( customMessageTextbox ).toBeInTheDocument();

		expect( updateWooPayCustomMessageHandler ).not.toHaveBeenCalled();

		await userEvent.paste( screen.getByRole( 'textbox' ), 'test' );
		expect( updateWooPayCustomMessageHandler ).toHaveBeenCalledWith(
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

	it( 'disables the enable checkbox and shows warning when Stripe Link is enabled', () => {
		useEnabledPaymentMethodIds.mockReturnValue( [ [ 'link' ], jest.fn() ] );

		render( <WooPaySettings section="enable" /> );

		const enableCheckbox = screen.getByLabelText( /Enable WooPay/ );
		expect( enableCheckbox ).toBeDisabled();

		// Use getAllByText since a11y regions may duplicate the text
		expect(
			screen.getAllByText(
				'To enable WooPay, you must first disable Link by Stripe.'
			)[ 0 ]
		).toBeInTheDocument();
	} );

	it( 'hides incompatibility notice when Stripe Link is enabled', () => {
		useEnabledPaymentMethodIds.mockReturnValue( [ [ 'link' ], jest.fn() ] );
		useWooPayShowIncompatibilityNotice.mockReturnValue( true );

		render( <WooPaySettings section="enable" /> );

		expect(
			screen.queryByText(
				'One or more of your extensions are incompatible with WooPay.'
			)
		).not.toBeInTheDocument();

		// Use getAllByText since a11y regions may duplicate the text
		expect(
			screen.getAllByText(
				'To enable WooPay, you must first disable Link by Stripe.'
			)[ 0 ]
		).toBeInTheDocument();
	} );

	it( 'does not show notices when Stripe Link is not enabled', () => {
		useEnabledPaymentMethodIds.mockReturnValue( [ [], jest.fn() ] );

		const { container } = render( <WooPaySettings section="enable" /> );

		const enableCheckbox = screen.getByLabelText( /Enable WooPay/ );
		expect( enableCheckbox ).not.toBeDisabled();

		// checking that there are no `InlineNotice`s rendered, just in case.
		expect(
			container.querySelector( '.wcpay-inline-notice' )
		).not.toBeInTheDocument();
	} );
} );
