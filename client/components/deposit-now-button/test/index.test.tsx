/**
 * External dependencies
 */
import React from 'react';
import { render, within } from '@testing-library/react';
import user from '@testing-library/user-event';

/**
 * Internal dependencies
 */
import DepositNowButton from '..';
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

describe( 'DepositNowButton', () => {
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
		const depositDelayDays = 2;
		const availableBalance = {
			amount: 1.23,
			currency: 'aud',
		} as AccountOverview.Balance;

		const { getByRole } = render(
			<DepositNowButton
				availableBalance={ availableBalance }
				depositDelayDays={ depositDelayDays }
			/>
		);
		const button = getByRole( 'button', { name: 'Deposit now' } );
		expect( button ).not.toHaveAttribute( 'disabled' );
	} );

	test( 'renders correctly when available balance = 0', () => {
		const depositDelayDays = 5;
		const availableBalance = {
			amount: 0,
			currency: 'aud',
		} as AccountOverview.Balance;

		const { getByRole } = render(
			<DepositNowButton
				availableBalance={ availableBalance }
				depositDelayDays={ depositDelayDays }
			/>
		);
		const button = getByRole( 'button', { name: 'Deposit now' } );
		expect( button ).toHaveAttribute( 'disabled' );
	} );

	test( 'renders correctly when available balance < 0', () => {
		const depositDelayDays = 7;
		const availableBalance = {
			amount: -0.12,
			currency: 'aud',
		} as AccountOverview.Balance;

		const { getByRole } = render(
			<DepositNowButton
				availableBalance={ availableBalance }
				depositDelayDays={ depositDelayDays }
			/>
		);
		const button = getByRole( 'button', { name: 'Deposit now' } );
		expect( button ).toHaveAttribute( 'disabled' );
	} );

	test( 'displays deposit now modal when clicked', async () => {
		const depositDelayDays = 7;
		const availableBalance = {
			amount: 123,
			currency: 'eur',
		} as AccountOverview.Balance;

		const expectedFormattedBalance = formatCurrency(
			availableBalance.amount,
			availableBalance.currency
		);

		const { getByRole } = render(
			<DepositNowButton
				availableBalance={ availableBalance }
				depositDelayDays={ depositDelayDays }
			/>
		);
		const button = getByRole( 'button', { name: 'Deposit now' } );
		user.click( button );

		const modal = getByRole( 'dialog', { name: 'Deposit now' } );

		within( modal ).getByRole( 'button', {
			name: `Deposit ${ expectedFormattedBalance }`,
		} );
		within( modal ).getByRole( 'button', {
			name: /Cancel/,
		} );
	} );
} );
