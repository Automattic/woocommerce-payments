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
		expect( checkoutCheckbox.disabled ).toBeTruthy();
		expect( checkoutCheckbox.checked ).toBeFalsy();
		expect( productPageCheckbox.disabled ).toBeTruthy();
		expect( productPageCheckbox.checked ).toBeFalsy();
		expect( cartCheckbox.disabled ).toBeTruthy();
		expect( cartCheckbox.checked ).toBeFalsy();

		userEvent.click(
			screen.getByText(
				'Enable digital wallets & express payment methods'
			)
		);

		// only the checkout checkbox is checked by default, once the feature is enabled.
		expect( checkoutCheckbox.disabled ).toBeFalsy();
		expect( checkoutCheckbox.checked ).toBeTruthy();
		expect( productPageCheckbox.disabled ).toBeFalsy();
		expect( productPageCheckbox.checked ).toBeFalsy();
		expect( cartCheckbox.disabled ).toBeFalsy();
		expect( cartCheckbox.checked ).toBeFalsy();

		// enabling the product page location.
		userEvent.click( screen.getByText( 'Product page' ) );
		// disabling the checkout location.
		userEvent.click( screen.getByText( 'Checkout' ) );

		// disabling the feature again.
		userEvent.click(
			screen.getByText(
				'Enable digital wallets & express payment methods'
			)
		);

		// all checkboxes are disabled an not checked.
		expect( checkoutCheckbox.disabled ).toBeTruthy();
		expect( checkoutCheckbox.checked ).toBeFalsy();
		expect( productPageCheckbox.disabled ).toBeTruthy();
		expect( productPageCheckbox.checked ).toBeFalsy();
		expect( cartCheckbox.disabled ).toBeTruthy();
		expect( cartCheckbox.checked ).toBeFalsy();

		// enabling the feature again.
		userEvent.click(
			screen.getByText(
				'Enable digital wallets & express payment methods'
			)
		);

		// only the product page checkbox is checked once the feature is enabled again.
		expect( checkoutCheckbox.disabled ).toBeFalsy();
		expect( checkoutCheckbox.checked ).toBeFalsy();
		expect( productPageCheckbox.disabled ).toBeFalsy();
		expect( productPageCheckbox.checked ).toBeTruthy();
		expect( cartCheckbox.disabled ).toBeFalsy();
		expect( cartCheckbox.checked ).toBeFalsy();
	} );
} );
