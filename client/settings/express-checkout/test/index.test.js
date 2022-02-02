/** @format **/

/**
 * External dependencies
 */
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

/**
 * Internal dependencies
 */
import PaymentRequest from '..';
import {
	usePaymentRequestEnabledSettings,
	usePaymentRequestLocations,
} from 'wcpay/data';

jest.mock( 'wcpay/data', () => ( {
	usePaymentRequestEnabledSettings: jest.fn(),
	usePaymentRequestLocations: jest.fn(),
} ) );

const getMockPaymentRequestEnabledSettings = (
	isEnabled,
	updateIsPaymentRequestEnabledHandler
) => [ isEnabled, updateIsPaymentRequestEnabledHandler ];

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

describe( 'PaymentRequest', () => {
	beforeEach( () => {
		usePaymentRequestEnabledSettings.mockReturnValue(
			getMockPaymentRequestEnabledSettings( false, jest.fn() )
		);
		usePaymentRequestLocations.mockReturnValue(
			getMockPaymentRequestLocations( true, true, true, jest.fn() )
		);
	} );

	it( 'should enable express checkout locations if express checkout is enabled', async () => {
		usePaymentRequestEnabledSettings.mockReturnValue(
			getMockPaymentRequestEnabledSettings( false, jest.fn() )
		);

		render( <PaymentRequest /> );

		const [
			,
			checkoutCheckbox,
			productPageCheckbox,
			cartCheckbox,
		] = screen.getAllByRole( 'checkbox' );

		// all "locations" checkboes are disabled and unchecked.
		expect( checkoutCheckbox ).toBeDisabled();
		expect( checkoutCheckbox ).not.toBeChecked();
		expect( productPageCheckbox ).toBeDisabled();
		expect( productPageCheckbox ).not.toBeChecked();
		expect( cartCheckbox ).toBeDisabled();
		expect( cartCheckbox ).not.toBeChecked();
	} );

	it( 'should disable express checkout locations if express checkout is disabled', async () => {
		usePaymentRequestEnabledSettings.mockReturnValue(
			getMockPaymentRequestEnabledSettings( true, jest.fn() )
		);

		render( <PaymentRequest /> );

		const [
			,
			checkoutCheckbox,
			productPageCheckbox,
			cartCheckbox,
		] = screen.getAllByRole( 'checkbox' );

		// all checkboxes are checked by default, once the feature is enabled.
		expect( checkoutCheckbox ).not.toBeDisabled();
		expect( checkoutCheckbox ).toBeChecked();
		expect( productPageCheckbox ).not.toBeDisabled();
		expect( productPageCheckbox ).toBeChecked();
		expect( cartCheckbox ).not.toBeDisabled();
		expect( cartCheckbox ).toBeChecked();
	} );

	it( 'should dispatch enabled status update if express checkout is being toggled', async () => {
		const updateIsPaymentRequestEnabledHandler = jest.fn();
		usePaymentRequestEnabledSettings.mockReturnValue(
			getMockPaymentRequestEnabledSettings(
				false,
				updateIsPaymentRequestEnabledHandler
			)
		);

		render( <PaymentRequest /> );

		userEvent.click( screen.getByText( 'Enable express checkouts' ) );

		expect( updateIsPaymentRequestEnabledHandler ).toHaveBeenCalledWith(
			true
		);
	} );

	it( 'should trigger an action to save the checked locations when un-checking the location checkboxes', async () => {
		const updatePaymentRequestLocationsHandler = jest.fn();
		usePaymentRequestEnabledSettings.mockReturnValue(
			getMockPaymentRequestEnabledSettings( true, jest.fn() )
		);
		usePaymentRequestLocations.mockReturnValue(
			getMockPaymentRequestLocations(
				true,
				true,
				true,
				updatePaymentRequestLocationsHandler
			)
		);

		render( <PaymentRequest /> );

		// Uncheck each checkbox, and verify them what kind of action should have been called
		userEvent.click( screen.getByText( 'Product page' ) );
		expect(
			updatePaymentRequestLocationsHandler
		).toHaveBeenLastCalledWith( [ 'checkout', 'cart' ] );

		userEvent.click( screen.getByText( 'Checkout' ) );
		expect(
			updatePaymentRequestLocationsHandler
		).toHaveBeenLastCalledWith( [ 'product', 'cart' ] );

		userEvent.click( screen.getByText( 'Cart' ) );
		expect(
			updatePaymentRequestLocationsHandler
		).toHaveBeenLastCalledWith( [ 'checkout', 'product' ] );
	} );

	it( 'should trigger an action to save the checked locations when checking the location checkboxes', async () => {
		const updatePaymentRequestLocationsHandler = jest.fn();
		usePaymentRequestEnabledSettings.mockReturnValue(
			getMockPaymentRequestEnabledSettings( true, jest.fn() )
		);
		usePaymentRequestLocations.mockReturnValue(
			getMockPaymentRequestLocations(
				false,
				false,
				false,
				updatePaymentRequestLocationsHandler
			)
		);

		render( <PaymentRequest /> );

		userEvent.click( screen.getByText( 'Cart' ) );
		expect(
			updatePaymentRequestLocationsHandler
		).toHaveBeenLastCalledWith( [ 'cart' ] );

		userEvent.click( screen.getByText( 'Product page' ) );
		expect(
			updatePaymentRequestLocationsHandler
		).toHaveBeenLastCalledWith( [ 'product' ] );

		userEvent.click( screen.getByText( 'Checkout' ) );
		expect(
			updatePaymentRequestLocationsHandler
		).toHaveBeenLastCalledWith( [ 'checkout' ] );
	} );

	it( 'has the correct href link to the payment request setting page', async () => {
		render( <PaymentRequest /> );

		const customizeAppearanceButton = screen.getByText(
			'Customize appearance'
		);
		expect( customizeAppearanceButton ).toHaveAttribute(
			'href',
			'admin.php?page=wc-settings&tab=checkout&section=woocommerce_payments&method=payment_request'
		);
	} );
} );
