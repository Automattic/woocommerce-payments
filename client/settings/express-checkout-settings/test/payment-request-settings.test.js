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

jest.mock( '../../../data', () => ( {
	usePaymentRequestEnabledSettings: jest.fn(),
	usePaymentRequestLocations: jest.fn(),
	usePaymentRequestButtonType: jest.fn().mockReturnValue( [ 'buy' ] ),
	usePaymentRequestButtonSize: jest.fn().mockReturnValue( [ 'default' ] ),
	usePaymentRequestButtonTheme: jest.fn().mockReturnValue( [ 'dark' ] ),
	useWooPayEnabledSettings: jest.fn(),
} ) );

jest.mock( '../payment-request-button-preview' );
PaymentRequestButtonPreview.mockImplementation( () => '<></>' );

jest.mock( 'payment-request/utils', () => ( {
	getPaymentRequestData: jest.fn().mockReturnValue( {
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
		render( <PaymentRequestSettings section="enable" /> );

		// confirm there is a heading
		expect(
			screen.queryByText(
				'Enable Apple Pay and Google Pay on selected pages'
			)
		).toBeInTheDocument();

		// confirm checkbox groups displayed
		const [ enableCheckbox ] = screen.queryAllByRole( 'checkbox' );

		expect( enableCheckbox ).toBeInTheDocument();
	} );

	it( 'triggers the hooks when the enable setting is being interacted with', () => {
		const updateIsPaymentRequestEnabledHandler = jest.fn();

		usePaymentRequestEnabledSettings.mockReturnValue(
			getMockPaymentRequestEnabledSettings(
				true,
				updateIsPaymentRequestEnabledHandler
			)
		);

		render( <PaymentRequestSettings section="enable" /> );

		expect( updateIsPaymentRequestEnabledHandler ).not.toHaveBeenCalled();

		expect( screen.getByLabelText( 'Checkout Page' ) ).toBeChecked();
		expect( screen.getByLabelText( 'Product Page' ) ).toBeChecked();
		expect( screen.getByLabelText( 'Cart Page' ) ).toBeChecked();

		userEvent.click( screen.getByLabelText( /Enable Apple Pay/ ) );
		expect( updateIsPaymentRequestEnabledHandler ).toHaveBeenCalledWith(
			false
		);
	} );

	it( 'renders general settings with defaults', () => {
		render( <PaymentRequestSettings section="general" /> );

		// confirm settings headings
		expect(
			screen.queryByRole( 'heading', { name: 'Call to action' } )
		).toBeInTheDocument();
		expect(
			screen.queryByRole( 'heading', { name: 'Button size' } )
		).toBeInTheDocument();

		expect(
			screen.queryByRole( 'heading', { name: 'Theme' } )
		).toBeInTheDocument();

		expect(
			screen.queryByRole( 'heading', { name: 'Preview' } )
		).toBeInTheDocument();

		// confirm radio button groups displayed
		const [ ctaRadio, sizeRadio, themeRadio ] = screen.queryAllByRole(
			'radio'
		);

		expect( ctaRadio ).toBeInTheDocument();
		expect( sizeRadio ).toBeInTheDocument();
		expect( themeRadio ).toBeInTheDocument();

		// confirm default values
		expect( screen.getByLabelText( 'Buy with' ) ).toBeChecked();
		expect( screen.getByLabelText( 'Default (40 px)' ) ).toBeChecked();
		expect( screen.getByLabelText( /Dark/ ) ).toBeChecked();
	} );

	it( 'triggers the hooks when the enabled settings are being interacted with', () => {
		const updatePaymentRequestLocationsHandler = jest.fn();
		usePaymentRequestLocations.mockReturnValue(
			getMockPaymentRequestLocations(
				false,
				false,
				false,
				updatePaymentRequestLocationsHandler
			)
		);
		render( <PaymentRequestSettings section="enable" /> );

		expect( updatePaymentRequestLocationsHandler ).not.toHaveBeenCalled();

		userEvent.click( screen.getByLabelText( /Checkout/ ) );
		expect(
			updatePaymentRequestLocationsHandler
		).toHaveBeenLastCalledWith( [ 'checkout' ] );

		userEvent.click( screen.getByLabelText( /Product Page/ ) );
		expect(
			updatePaymentRequestLocationsHandler
		).toHaveBeenLastCalledWith( [ 'product' ] );

		userEvent.click( screen.getByLabelText( /Cart/ ) );
		expect(
			updatePaymentRequestLocationsHandler
		).toHaveBeenLastCalledWith( [ 'cart' ] );
	} );

	it( 'triggers the hooks when the general settings are being interacted with', () => {
		const setButtonTypeMock = jest.fn();
		const setButtonSizeMock = jest.fn();
		const setButtonThemeMock = jest.fn();

		usePaymentRequestButtonType.mockReturnValue( [
			'buy',
			setButtonTypeMock,
		] );
		usePaymentRequestButtonSize.mockReturnValue( [
			'default',
			setButtonSizeMock,
		] );
		usePaymentRequestButtonTheme.mockReturnValue( [
			'dark',
			setButtonThemeMock,
		] );

		render( <PaymentRequestSettings section="general" /> );

		expect( setButtonTypeMock ).not.toHaveBeenCalled();
		expect( setButtonSizeMock ).not.toHaveBeenCalled();
		expect( setButtonThemeMock ).not.toHaveBeenCalled();

		userEvent.click( screen.getByLabelText( /Light/ ) );
		expect( setButtonThemeMock ).toHaveBeenCalledWith( 'light' );

		userEvent.click( screen.getByLabelText( 'Book with' ) );
		expect( setButtonTypeMock ).toHaveBeenCalledWith( 'book' );

		userEvent.click( screen.getByLabelText( 'Large (56 px)' ) );
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

		render( <PaymentRequestSettings section="enable" /> );

		// Uncheck each checkbox, and verify them what kind of action should have been called
		userEvent.click( screen.getByText( 'Product Page' ) );
		expect(
			updatePaymentRequestLocationsHandler
		).toHaveBeenLastCalledWith( [ 'checkout', 'cart' ] );

		userEvent.click( screen.getByText( 'Checkout Page' ) );
		expect(
			updatePaymentRequestLocationsHandler
		).toHaveBeenLastCalledWith( [ 'product', 'cart' ] );

		userEvent.click( screen.getByText( 'Cart Page' ) );
		expect(
			updatePaymentRequestLocationsHandler
		).toHaveBeenLastCalledWith( [ 'checkout', 'product' ] );
	} );
} );
