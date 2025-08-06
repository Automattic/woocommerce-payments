/** @format */

/**
 * External dependencies
 */
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

/**
 * Internal dependencies
 */
import PaymentRequestSettings from '../payment-request-settings';
import PaymentRequestButtonPreview from '../payment-request-button-preview';
import {
	usePaymentRequestEnabledSettings,
	usePaymentRequestLocations,
	usePaymentRequestButtonType,
	usePaymentRequestButtonSize,
	usePaymentRequestButtonTheme,
	useWooPayEnabledSettings,
} from '../../../data';
import WCPaySettingsContext from 'wcpay/settings/wcpay-settings-context';

jest.mock( '../../../data', () => ( {
	usePaymentRequestEnabledSettings: jest.fn(),
	usePaymentRequestLocations: jest.fn(),
	useTestMode: jest.fn().mockReturnValue( [ false ] ),
	usePaymentRequestButtonType: jest.fn().mockReturnValue( [ 'default' ] ),
	usePaymentRequestButtonBorderRadius: jest.fn().mockReturnValue( [ 4 ] ),
	usePaymentRequestButtonSize: jest.fn().mockReturnValue( [ 'small' ] ),
	usePaymentRequestButtonTheme: jest.fn().mockReturnValue( [ 'dark' ] ),
	useWooPayEnabledSettings: jest.fn(),
	useWooPayShowIncompatibilityNotice: jest.fn().mockReturnValue( false ),
} ) );

jest.mock( '../payment-request-button-preview' );
PaymentRequestButtonPreview.mockImplementation( () => '<></>' );

jest.mock( 'utils/express-checkout', () => ( {
	getExpressCheckoutConfig: jest.fn().mockReturnValue( {
		publishableKey: '123',
		accountId: '0001',
		locale: 'en',
	} ),
} ) );

const getMockPaymentRequestEnabledSettings = (
	isEnabled,
	updateIsPaymentRequestEnabledHandler
) => [ isEnabled, updateIsPaymentRequestEnabledHandler ];

const getMockWooPayEnabledSettings = ( isEnabled ) => [ isEnabled ];

const getMockPaymentRequestLocations = (
	isCheckoutEnabled,
	isProductPageEnabled,
	isCartEnabled,
	updatePaymentRequestLocationsHandler
) => [
	[
		isCheckoutEnabled && 'checkout',
		isProductPageEnabled && 'product',
		isCartEnabled && 'cart',
	].filter( Boolean ),
	updatePaymentRequestLocationsHandler,
];

// Mock CSS imports to prevent CSS parsing errors due to RangeControl.
jest.mock( '../index.scss', () => ( {} ) );

// Suppress CSS parsing errors from components
// eslint-disable-next-line no-console
const originalError = console.error;
beforeAll( () => {
	// eslint-disable-next-line no-console
	console.error = ( ...args ) => {
		// Suppress CSS parsing errors
		if (
			args[ 0 ] &&
			args[ 0 ].message &&
			args[ 0 ].message.includes( 'Could not parse CSS stylesheet' )
		) {
			return;
		}
		originalError.call( console, ...args );
	};
} );

afterAll( () => {
	// eslint-disable-next-line no-console
	console.error = originalError;
} );

const renderWithSettingsProvider = ( ui ) =>
	render(
		<WCPaySettingsContext.Provider
			value={ { accountStatus: {}, featureFlags: {} } }
		>
			{ ui }
		</WCPaySettingsContext.Provider>
	);

describe( 'PaymentRequestSettings', () => {
	beforeEach( () => {
		usePaymentRequestEnabledSettings.mockReturnValue(
			getMockPaymentRequestEnabledSettings( true, jest.fn() )
		);

		usePaymentRequestLocations.mockReturnValue(
			getMockPaymentRequestLocations( true, true, true, jest.fn() )
		);

		useWooPayEnabledSettings.mockReturnValue(
			getMockWooPayEnabledSettings( true )
		);
	} );

	it( 'renders enable settings with defaults', () => {
		renderWithSettingsProvider(
			<PaymentRequestSettings section="enable" />
		);

		// confirm checkbox groups displayed
		const [ enableCheckbox ] = screen.queryAllByRole( 'checkbox' );

		expect( enableCheckbox ).toBeInTheDocument();
	} );

	it( 'triggers the hooks when the enable setting is being interacted with', async () => {
		const updateIsPaymentRequestEnabledHandler = jest.fn();

		usePaymentRequestEnabledSettings.mockReturnValue(
			getMockPaymentRequestEnabledSettings(
				true,
				updateIsPaymentRequestEnabledHandler
			)
		);

		renderWithSettingsProvider(
			<PaymentRequestSettings section="enable" />
		);

		expect( updateIsPaymentRequestEnabledHandler ).not.toHaveBeenCalled();

		expect(
			screen.getByLabelText( 'Show on checkout page' )
		).toBeChecked();
		expect( screen.getByLabelText( 'Show on product page' ) ).toBeChecked();
		expect( screen.getByLabelText( 'Show on cart page' ) ).toBeChecked();

		await userEvent.click( screen.getByLabelText( /Enable Apple Pay/ ) );
		expect( updateIsPaymentRequestEnabledHandler ).toHaveBeenCalledWith(
			false
		);
	} );

	it( 'renders general settings with defaults', () => {
		renderWithSettingsProvider(
			<PaymentRequestSettings section="general" />
		);

		// confirm settings labels
		expect(
			screen.queryByRole( 'combobox', { name: 'Call to action' } )
		).toBeInTheDocument();
		expect( screen.queryByText( 'Button size' ) ).toBeInTheDocument();
		expect( screen.queryByText( 'Theme' ) ).toBeInTheDocument();
		expect( screen.queryByText( 'Preview' ) ).toBeInTheDocument();

		// confirm radio button groups displayed
		const [ sizeRadio, themeRadio ] = screen.queryAllByRole( 'radio' );

		expect( sizeRadio ).toBeInTheDocument();
		expect( themeRadio ).toBeInTheDocument();

		// confirm default values
		expect(
			screen.getByRole( 'combobox', {
				name: 'Call to action',
			} )
		).toHaveValue( 'default' );
		expect( screen.getByLabelText( 'Small (40 px)' ) ).toBeChecked();
		expect( screen.getByLabelText( /Dark/ ) ).toBeChecked();
	} );

	it( 'triggers the hooks when the enabled settings are being interacted with', async () => {
		const updatePaymentRequestLocationsHandler = jest.fn();
		usePaymentRequestLocations.mockReturnValue(
			getMockPaymentRequestLocations(
				false,
				false,
				false,
				updatePaymentRequestLocationsHandler
			)
		);
		renderWithSettingsProvider(
			<PaymentRequestSettings section="enable" />
		);

		expect( updatePaymentRequestLocationsHandler ).not.toHaveBeenCalled();

		await userEvent.click(
			screen.getByLabelText( /Show on checkout page/ )
		);
		expect(
			updatePaymentRequestLocationsHandler
		).toHaveBeenLastCalledWith( [ 'checkout' ] );

		await userEvent.click(
			screen.getByLabelText( /Show on product page/ )
		);
		expect(
			updatePaymentRequestLocationsHandler
		).toHaveBeenLastCalledWith( [ 'product' ] );

		await userEvent.click( screen.getByLabelText( /Show on cart page/ ) );
		expect(
			updatePaymentRequestLocationsHandler
		).toHaveBeenLastCalledWith( [ 'cart' ] );
	} );

	it( 'triggers the hooks when the general settings are being interacted with', async () => {
		const setButtonTypeMock = jest.fn();
		const setButtonSizeMock = jest.fn();
		const setButtonThemeMock = jest.fn();

		usePaymentRequestButtonType.mockReturnValue( [
			'default',
			setButtonTypeMock,
		] );
		usePaymentRequestButtonSize.mockReturnValue( [
			'small',
			setButtonSizeMock,
		] );
		usePaymentRequestButtonTheme.mockReturnValue( [
			'dark',
			setButtonThemeMock,
		] );

		renderWithSettingsProvider(
			<PaymentRequestSettings section="general" />
		);

		expect( setButtonTypeMock ).not.toHaveBeenCalled();
		expect( setButtonSizeMock ).not.toHaveBeenCalled();
		expect( setButtonThemeMock ).not.toHaveBeenCalled();

		await userEvent.click( screen.getByLabelText( /Light/ ) );
		expect( setButtonThemeMock ).toHaveBeenCalledWith( 'light' );

		await userEvent.selectOptions(
			screen.getByRole( 'combobox', {
				name: 'Call to action',
			} ),
			'book'
		);
		expect( setButtonTypeMock ).toHaveBeenCalledWith(
			'book',
			expect.anything()
		);

		await userEvent.click( screen.getByLabelText( 'Large (55 px)' ) );
		expect( setButtonSizeMock ).toHaveBeenCalledWith( 'large' );
	} );

	it( 'should trigger an action to save the checked locations when un-checking the location checkboxes', async () => {
		const updatePaymentRequestLocationsHandler = jest.fn();

		usePaymentRequestLocations.mockReturnValue(
			getMockPaymentRequestLocations(
				true,
				true,
				true,
				updatePaymentRequestLocationsHandler
			)
		);

		renderWithSettingsProvider(
			<PaymentRequestSettings section="enable" />
		);

		// Uncheck each checkbox, and verify them what kind of action should have been called
		await userEvent.click( screen.getByText( 'Show on product page' ) );
		expect(
			updatePaymentRequestLocationsHandler
		).toHaveBeenLastCalledWith( [ 'checkout', 'cart' ] );

		await userEvent.click( screen.getByText( 'Show on checkout page' ) );
		expect(
			updatePaymentRequestLocationsHandler
		).toHaveBeenLastCalledWith( [ 'product', 'cart' ] );

		await userEvent.click( screen.getByText( 'Show on cart page' ) );
		expect(
			updatePaymentRequestLocationsHandler
		).toHaveBeenLastCalledWith( [ 'checkout', 'product' ] );
	} );
} );
