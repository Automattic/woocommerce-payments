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
	useDigitalWalletsSettings,
	useDigitalWalletsSectionsToShowOn,
} from 'data';

jest.mock( 'data', () => ( {
	useDigitalWalletsSettings: jest.fn(),
	useDigitalWalletsSectionsToShowOn: jest.fn(),
} ) );

const getMockDigitalWalletsSettings = (
	isEnabled,
	updateIsDigitalWalletsEnabledHandler
) => ( {
	isDigitalWalletsEnabled: isEnabled,
	updateIsDigitalWalletsEnabled: updateIsDigitalWalletsEnabledHandler,
} );

const getMockDigitalWalletsSections = (
	checkoutStatus,
	productPageStatus,
	cartStatus,
	updateDigitalWalletsSectionsToShowOnHandler
) => ( {
	digitalWalletsSections: {
		checkout: checkoutStatus,
		// eslint-disable-next-line camelcase
		product_page: productPageStatus,
		cart: cartStatus,
	},
	updateDigitalWalletsSectionsToShowOn: updateDigitalWalletsSectionsToShowOnHandler,
} );

describe( 'DigitalWallets', () => {
	beforeEach( () => {
		useDigitalWalletsSettings.mockReturnValue(
			getMockDigitalWalletsSettings( false, jest.fn() )
		);
		useDigitalWalletsSectionsToShowOn.mockReturnValue(
			getMockDigitalWalletsSections( true, true, true, jest.fn() )
		);
	} );

	it( 'should enable 1-click checkout sections if 1-click checkout is enabled', async () => {
		const updateIsDigitalWalletsEnabledHandler = jest.fn();
		useDigitalWalletsSettings.mockReturnValue(
			getMockDigitalWalletsSettings(
				false,
				updateIsDigitalWalletsEnabledHandler
			)
		);

		render( <DigitalWallets /> );

		const [
			,
			checkoutCheckbox,
			productPageCheckbox,
			cartCheckbox,
		] = screen.getAllByRole( 'checkbox' );

		userEvent.click( screen.getByText( 'Enable 1-click checkouts' ) );

		// all "locations" checkboes are disabled and unchecked.
		expect( checkoutCheckbox ).toBeDisabled();
		expect( checkoutCheckbox ).not.toBeChecked();
		expect( productPageCheckbox ).toBeDisabled();
		expect( productPageCheckbox ).not.toBeChecked();
		expect( cartCheckbox ).toBeDisabled();
		expect( cartCheckbox ).not.toBeChecked();

		expect( updateIsDigitalWalletsEnabledHandler ).toBeCalledTimes( 1 );
	} );

	it( 'should disable 1-click checkout sections if 1-click checkout is disabled', async () => {
		const updateIsDigitalWalletsEnabledHandler = jest.fn();
		useDigitalWalletsSettings.mockReturnValue(
			getMockDigitalWalletsSettings(
				true,
				updateIsDigitalWalletsEnabledHandler
			)
		);

		render( <DigitalWallets /> );

		const [
			,
			checkoutCheckbox,
			productPageCheckbox,
			cartCheckbox,
		] = screen.getAllByRole( 'checkbox' );

		userEvent.click( screen.getByText( 'Enable 1-click checkouts' ) );

		// all checkboxes are checked by default, once the feature is enabled.
		expect( checkoutCheckbox ).not.toBeDisabled();
		expect( checkoutCheckbox ).toBeChecked();
		expect( productPageCheckbox ).not.toBeDisabled();
		expect( productPageCheckbox ).toBeChecked();
		expect( cartCheckbox ).not.toBeDisabled();
		expect( cartCheckbox ).toBeChecked();

		expect( updateIsDigitalWalletsEnabledHandler ).toBeCalledTimes( 1 );
	} );

	it( 'should trigger an action to save the checked sections when toggling the checkboxes', async () => {
		const updateDigitalWalletsSectionsToShowOnHandler = jest.fn();
		useDigitalWalletsSettings.mockReturnValue(
			getMockDigitalWalletsSettings( true, jest.fn() )
		);
		useDigitalWalletsSectionsToShowOn.mockReturnValue(
			getMockDigitalWalletsSections(
				true,
				true,
				true,
				updateDigitalWalletsSectionsToShowOnHandler
			)
		);

		render( <DigitalWallets /> );

		// Uncheck all three checkboxes, and verify them.
		userEvent.click( screen.getByText( 'Product page' ) );
		userEvent.click( screen.getByText( 'Checkout' ) );
		userEvent.click( screen.getByText( 'Cart' ) );

		expect(
			updateDigitalWalletsSectionsToShowOnHandler
		).toHaveBeenCalledWith( {
			// eslint-disable-next-line camelcase
			product_page: false,
		} );
		expect(
			updateDigitalWalletsSectionsToShowOnHandler
		).toHaveBeenCalledWith( {
			checkout: false,
		} );
		expect(
			updateDigitalWalletsSectionsToShowOnHandler
		).toHaveBeenCalledWith( {
			cart: false,
		} );

		// Check just cart and product_page, and verify them.
		userEvent.click( screen.getByText( 'Cart' ) );
		userEvent.click( screen.getByText( 'Product page' ) );

		expect(
			updateDigitalWalletsSectionsToShowOnHandler
		).toHaveBeenCalledWith( {
			cart: false,
		} );
		expect(
			updateDigitalWalletsSectionsToShowOnHandler
		).toHaveBeenCalledWith( {
			// eslint-disable-next-line camelcase
			product_page: false,
		} );
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
