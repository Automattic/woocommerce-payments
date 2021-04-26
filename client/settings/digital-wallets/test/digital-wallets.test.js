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
	it( 'toggles the payment method locations visibility', async () => {
		render( <DigitalWallets /> );

		expect(
			screen.queryByText(
				'Show digital wallets & express payment methods on:'
			)
		).not.toBeInTheDocument();

		userEvent.click(
			screen.getByText(
				'Enable digital wallets & express payment methods'
			)
		);

		expect(
			await screen.findByText(
				'Show digital wallets & express payment methods on:'
			)
		).toBeInTheDocument();

		userEvent.click(
			screen.getByText(
				'Enable digital wallets & express payment methods'
			)
		);

		expect(
			screen.queryByText(
				'Show digital wallets & express payment methods on:'
			)
		).not.toBeInTheDocument();
	} );
} );
