/**
 * External dependencies
 */
import React from 'react';
import { render, within } from '@testing-library/react';
import user from '@testing-library/user-event';

/**
 * Internal dependencies
 */
import StandardDepositButton from '..';
import { formatCurrency } from 'wcpay/utils/currency';

declare const global: {
	wcpaySettings: {
		zeroDecimalCurrencies: string[];
		currencyData: Record< string, any >;
		connect: {
			country: string;
		};
	};
};

describe( 'StandardDepositButton', () => {
	beforeAll( () => {
		global.wcpaySettings = {
			zeroDecimalCurrencies: [],
			connect: {
				country: 'US',
			},
			currencyData: {
				US: {
					code: 'USD',
					symbol: '$',
					symbolPosition: 'left',
					thousandSeparator: ',',
					decimalSeparator: '.',
					precision: 2,
				},
			},
		};
	} );

	test( 'renders correctly when available balance > 0', () => {
		const availableBalance = {
			amount: 1.23,
			currency: 'aud',
			source_types: [],
			transaction_ids: [],
		};

		const { getByRole } = render(
			<StandardDepositButton
				availableBalance={ availableBalance }
				lastManualDeposit={ undefined }
			/>
		);
		const button = getByRole( 'button', { name: 'Deposit funds' } );
		expect( button ).not.toHaveAttribute( 'disabled' );
	} );

	test( 'renders correctly when available balance = 0', () => {
		const availableBalance = {
			amount: 0,
			currency: 'aud',
			source_types: [],
			transaction_ids: [],
		};

		const { getByRole } = render(
			<StandardDepositButton
				availableBalance={ availableBalance }
				lastManualDeposit={ undefined }
			/>
		);
		const button = getByRole( 'button', { name: 'Deposit funds' } );
		expect( button ).toHaveAttribute( 'disabled' );
	} );

	test( 'renders correctly when available balance < 0', () => {
		const availableBalance = {
			amount: -0.12,
			currency: 'aud',
			source_types: [],
			transaction_ids: [],
		};

		const { getByRole } = render(
			<StandardDepositButton
				availableBalance={ availableBalance }
				lastManualDeposit={ undefined }
			/>
		);
		const button = getByRole( 'button', { name: 'Deposit funds' } );
		expect( button ).toHaveAttribute( 'disabled' );
	} );

	test( 'displays modal when clicked', async () => {
		const availableBalance = {
			amount: 123,
			currency: 'eur',
			source_types: [],
			transaction_ids: [],
		};

		const expectedFormattedBalance = formatCurrency(
			availableBalance.amount,
			availableBalance.currency
		);

		const { getByRole } = render(
			<StandardDepositButton
				availableBalance={ availableBalance }
				lastManualDeposit={ undefined }
			/>
		);
		const button = getByRole( 'button', { name: 'Deposit funds' } );
		user.click( button );

		const modal = getByRole( 'dialog', { name: 'Deposit funds' } );

		within( modal ).getByRole( 'button', {
			name: `Deposit ${ expectedFormattedBalance }`,
		} );
		within( modal ).getByRole( 'button', {
			name: /Cancel/,
		} );
	} );
} );
