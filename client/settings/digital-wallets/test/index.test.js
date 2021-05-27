/** @format **/

/**
 * External dependencies
 */
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

/**
 * Internal dependencies
 */
import DigitalWallets from '..';
import {
	useDigitalWalletsEnabledSettings,
	useDigitalWalletsLocations,
} from 'data';

jest.mock( 'data', () => ( {
	useDigitalWalletsEnabledSettings: jest.fn(),
	useDigitalWalletsLocations: jest.fn(),
} ) );

const getMockDigitalWalletsEnabledSettings = (
	isEnabled,
	updateIsDigitalWalletsEnabledHandler
) => [ isEnabled, updateIsDigitalWalletsEnabledHandler ];

const getMockDigitalWalletsLocations = (
	isCheckoutEnabled,
	isProductPageEnabled,
	isCartEnabled,
	updateDigitalWalletsLocationsHandler
) => [
	[
		isCheckoutEnabled && 'checkout',
		isProductPageEnabled && 'product',
		isCartEnabled && 'cart',
	].filter( Boolean ),
	updateDigitalWalletsLocationsHandler,
];

describe( 'DigitalWallets', () => {
	beforeEach( () => {
		useDigitalWalletsEnabledSettings.mockReturnValue(
			getMockDigitalWalletsEnabledSettings( false, jest.fn() )
		);
		useDigitalWalletsLocations.mockReturnValue(
			getMockDigitalWalletsLocations( true, true, true, jest.fn() )
		);
	} );

	it( 'should enable express checkout locations if express checkout is enabled', async () => {
		useDigitalWalletsEnabledSettings.mockReturnValue(
			getMockDigitalWalletsEnabledSettings( false, jest.fn() )
		);

		render( <DigitalWallets /> );

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
		useDigitalWalletsEnabledSettings.mockReturnValue(
			getMockDigitalWalletsEnabledSettings( true, jest.fn() )
		);

		render( <DigitalWallets /> );

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

	it( 'should trigger an action if express checkout is being toggled', async () => {
		const updateIsDigitalWalletsEnabledHandler = jest.fn();
		useDigitalWalletsEnabledSettings.mockReturnValue(
			getMockDigitalWalletsEnabledSettings(
				false,
				updateIsDigitalWalletsEnabledHandler
			)
		);

		render( <DigitalWallets /> );

		userEvent.click( screen.getByText( 'Enable express checkouts' ) );

		expect( updateIsDigitalWalletsEnabledHandler ).toHaveBeenCalledWith(
			true
		);
	} );

	it( 'should trigger an action to save the checked locations when un-checking the location checkboxes', async () => {
		const updateDigitalWalletsLocationsHandler = jest.fn();
		useDigitalWalletsEnabledSettings.mockReturnValue(
			getMockDigitalWalletsEnabledSettings( true, jest.fn() )
		);
		useDigitalWalletsLocations.mockReturnValue(
			getMockDigitalWalletsLocations(
				true,
				true,
				true,
				updateDigitalWalletsLocationsHandler
			)
		);

		render( <DigitalWallets /> );

		// Uncheck each checkbox, and verify them what kind of action should have been called
		userEvent.click( screen.getByText( 'Product page' ) );
		expect(
			updateDigitalWalletsLocationsHandler
		).toHaveBeenLastCalledWith( [ 'checkout', 'cart' ] );

		userEvent.click( screen.getByText( 'Checkout' ) );
		expect(
			updateDigitalWalletsLocationsHandler
		).toHaveBeenLastCalledWith( [ 'product', 'cart' ] );

		userEvent.click( screen.getByText( 'Cart' ) );
		expect(
			updateDigitalWalletsLocationsHandler
		).toHaveBeenLastCalledWith( [ 'checkout', 'product' ] );
	} );

	it( 'should trigger an action to save the checked locations when checking the location checkboxes', async () => {
		const updateDigitalWalletsLocationsHandler = jest.fn();
		useDigitalWalletsEnabledSettings.mockReturnValue(
			getMockDigitalWalletsEnabledSettings( true, jest.fn() )
		);
		useDigitalWalletsLocations.mockReturnValue(
			getMockDigitalWalletsLocations(
				false,
				false,
				false,
				updateDigitalWalletsLocationsHandler
			)
		);

		render( <DigitalWallets /> );

		userEvent.click( screen.getByText( 'Cart' ) );
		expect(
			updateDigitalWalletsLocationsHandler
		).toHaveBeenLastCalledWith( [ 'cart' ] );

		userEvent.click( screen.getByText( 'Product page' ) );
		expect(
			updateDigitalWalletsLocationsHandler
		).toHaveBeenLastCalledWith( [ 'product' ] );

		userEvent.click( screen.getByText( 'Checkout' ) );
		expect(
			updateDigitalWalletsLocationsHandler
		).toHaveBeenLastCalledWith( [ 'checkout' ] );
	} );

	it( 'has the correct href link to the digital wallets setting page', async () => {
		render( <DigitalWallets /> );

		const customizeAppearanceButton = screen.getByText(
			'Customize appearance'
		);
		expect( customizeAppearanceButton ).toHaveAttribute(
			'href',
			'admin.php?page=wc-settings&tab=checkout&section=woocommerce_payments_digital_wallets'
		);
	} );
} );
