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

		expect( checkoutCheckbox.disabled ).toBeTruthy();
		expect( productPageCheckbox.disabled ).toBeTruthy();
		expect( cartCheckbox.disabled ).toBeTruthy();

		userEvent.click(
			screen.getByText(
				'Enable digital wallets & express payment methods'
			)
		);

		expect( checkoutCheckbox.disabled ).toBeFalsy();
		expect( productPageCheckbox.disabled ).toBeFalsy();
		expect( cartCheckbox.disabled ).toBeFalsy();

		userEvent.click(
			screen.getByText(
				'Enable digital wallets & express payment methods'
			)
		);

		expect( checkoutCheckbox.disabled ).toBeTruthy();
		expect( productPageCheckbox.disabled ).toBeTruthy();
		expect( cartCheckbox.disabled ).toBeTruthy();
	} );
} );
