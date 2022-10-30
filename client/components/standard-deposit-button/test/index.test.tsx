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

declare const global: {
	wcpaySettings: {
		accountStatus: {
			deposits: {
				completed_waiting_period: boolean;
			};
		};
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
			accountStatus: {
				deposits: {
					completed_waiting_period: true,
				},
			},
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
		const standardBalance = {
			amount: 5000,
			currency: 'aud',
			source_types: [],
			transaction_ids: [],
		};

		const { getByRole } = render(
			<StandardDepositButton
				lastManualDeposit={ undefined }
				standardBalance={ standardBalance }
			/>
		);
		const button = getByRole( 'button', { name: 'Deposit funds' } );
		expect( button ).not.toHaveAttribute( 'disabled' );
	} );

	test( 'renders correctly when available balance = 0', () => {
		const standardBalance = {
			amount: 0,
			currency: 'aud',
			source_types: [],
			transaction_ids: [],
		};

		const { getByRole } = render(
			<StandardDepositButton
				lastManualDeposit={ undefined }
				standardBalance={ standardBalance }
			/>
		);
		const button = getByRole( 'button', { name: 'Deposit funds' } );
		expect( button ).toHaveAttribute( 'disabled' );
	} );

	test( 'renders correctly when available balance < 0', () => {
		const standardBalance = {
			amount: -5000,
			currency: 'aud',
			source_types: [],
			transaction_ids: [],
		};

		const { getByRole } = render(
			<StandardDepositButton
				lastManualDeposit={ undefined }
				standardBalance={ standardBalance }
			/>
		);
		const button = getByRole( 'button', { name: 'Deposit funds' } );
		expect( button ).toHaveAttribute( 'disabled' );
	} );

	test( 'displays modal when clicked', async () => {
		const standardBalance = {
			amount: 5000,
			currency: 'eur',
			source_types: [],
			transaction_ids: [],
		};

		const { getByRole } = render(
			<StandardDepositButton
				lastManualDeposit={ undefined }
				standardBalance={ standardBalance }
			/>
		);
		const button = getByRole( 'button', { name: 'Deposit funds' } );
		user.click( button );

		const modal = getByRole( 'dialog', { name: 'Deposit funds' } );

		within( modal ).getByRole( 'button', {
			name: 'Submit deposit',
		} );
		within( modal ).getByRole( 'button', {
			name: /Cancel/,
		} );
	} );
} );
