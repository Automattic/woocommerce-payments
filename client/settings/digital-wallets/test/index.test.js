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

describe( 'DigitalWallets', () => {
	it( 'toggles the payment method locations status', async () => {
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

		userEvent.click( screen.getByText( 'Enable 1-click checkouts' ) );

		// all checkboxes are checked by default, once the feature is enabled.
		expect( checkoutCheckbox ).not.toBeDisabled();
		expect( checkoutCheckbox ).toBeChecked();
		expect( productPageCheckbox ).not.toBeDisabled();
		expect( productPageCheckbox ).toBeChecked();
		expect( cartCheckbox ).not.toBeDisabled();
		expect( cartCheckbox ).toBeChecked();

		// disabling the product page location.
		userEvent.click( screen.getByText( 'Product page' ) );
		// disabling the checkout location.
		userEvent.click( screen.getByText( 'Checkout' ) );

		// disabling the feature again.
		userEvent.click( screen.getByText( 'Enable 1-click checkouts' ) );

		// all checkboxes are disabled an not checked.
		expect( checkoutCheckbox ).toBeDisabled();
		expect( checkoutCheckbox ).not.toBeChecked();
		expect( productPageCheckbox ).toBeDisabled();
		expect( productPageCheckbox ).not.toBeChecked();
		expect( cartCheckbox ).toBeDisabled();
		expect( cartCheckbox ).not.toBeChecked();

		// enabling the feature again.
		userEvent.click( screen.getByText( 'Enable 1-click checkouts' ) );

		// only the cart checkbox is checked once the feature is enabled again.
		expect( checkoutCheckbox ).not.toBeDisabled();
		expect( checkoutCheckbox ).not.toBeChecked();
		expect( productPageCheckbox ).not.toBeDisabled();
		expect( productPageCheckbox ).not.toBeChecked();
		expect( cartCheckbox ).not.toBeDisabled();
		expect( cartCheckbox ).toBeChecked();
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
