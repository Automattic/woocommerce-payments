/** @format */

/**
 * External dependencies
 */
import { render } from '@testing-library/react';
import React from 'react';

/**
 * Internal dependencies
 */
import type { CachedDeposit } from 'types/deposits';
import { DepositOverview } from '../';

const mockDeposit = {
	id: 'po_mock',
	date: '2020-01-02 17:46:02',
	type: 'deposit',
	amount: 2000,
	status: 'paid',
	bankAccount: 'MOCK BANK •••• 1234 (USD)',
	automatic: true,
	fee: 30,
	fee_percentage: 1.5,
	currency: 'USD',
} as CachedDeposit;

const mockWithdrawal = {
	id: 'po_mock',
	date: '2020-01-02 17:46:02',
	type: 'withdrawal',
	amount: -2000,
	status: 'paid',
	bankAccount: 'MOCK BANK •••• 1234 (USD)',
	automatic: true,
	fee: 30,
	fee_percentage: 1.5,
	currency: 'USD',
} as CachedDeposit;

declare const global: {
	wcpaySettings: {
		zeroDecimalCurrencies: string[];
		currencyData: Record< string, any >;
		connect: {
			country: string;
		};
		dateFormat: string;
	};
	wcSettings: { countries: Record< string, string > };
};

describe( 'Deposit overview', () => {
	beforeEach( () => {
		jest.clearAllMocks();
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
			dateFormat: 'M j, Y',
		};
	} );

	test( 'renders automatic payout correctly', () => {
		const { container: overview, getByText } = render(
			<DepositOverview deposit={ mockDeposit } />
		);
		getByText( /Payout date:/ );
		getByText( 'Completed (paid)' );
		expect( overview ).toMatchSnapshot();
	} );

	test( 'renders automatic withdrawal correctly', () => {
		const { container: overview, getByText } = render(
			<DepositOverview deposit={ mockWithdrawal } />
		);
		getByText( /Withdrawal date:/ );
		getByText( 'Completed (deducted)' );
		expect( overview ).toMatchSnapshot();
	} );

	test( 'renders instant deposit correctly', () => {
		const { container: overview } = render(
			<DepositOverview deposit={ { ...mockDeposit, automatic: false } } />
		);
		expect( overview ).toMatchSnapshot();
	} );

	// test when deposit data could not be found, it renders a notice
	test( 'renders notice when deposit data is not found', () => {
		const { container: overview } = render(
			<DepositOverview deposit={ undefined } />
		);
		expect( overview ).toMatchSnapshot();
	} );

	test( 'renders failure reason when deposit has a known failure code', () => {
		const failedDeposit = {
			...mockDeposit,
			status: 'failed',
			failure_code: 'insufficient_funds',
		} as CachedDeposit;

		const { getByText } = render(
			<DepositOverview deposit={ failedDeposit } />
		);

		expect( getByText( 'Failure reason:' ) ).toBeInTheDocument();
		expect(
			getByText(
				'Your account has insufficient funds to cover your negative balance.'
			)
		).toBeInTheDocument();
	} );

	test( 'renders failure_message when failure_code is new and not included in our mapping', () => {
		// @ts-expect-error Testing invalid failure code scenario
		const failedDeposit = {
			...mockDeposit,
			status: 'failed',
			failure_code: 'unknown_failure_code',
			failure_message:
				'Failure error message originally captured from the Stripe Payout object',
		} as CachedDeposit;

		const { getByText } = render(
			<DepositOverview deposit={ failedDeposit } />
		);

		expect( getByText( 'Failure reason:' ) ).toBeInTheDocument();
		expect(
			getByText(
				'Failure error message originally captured from the Stripe Payout object'
			)
		).toBeInTheDocument();
	} );

	test( 'renders failure_message when no failure_code exists', () => {
		const failedDeposit = {
			...mockDeposit,
			status: 'failed',
			failure_message:
				'Failure error message originally captured from the Stripe Payout object',
		} as CachedDeposit;

		const { getByText } = render(
			<DepositOverview deposit={ failedDeposit } />
		);

		expect( getByText( 'Failure reason:' ) ).toBeInTheDocument();
		expect(
			getByText(
				'Failure error message originally captured from the Stripe Payout object'
			)
		).toBeInTheDocument();
	} );

	test( 'renders Unknown when no failure_code nor failure_message exist - edge case', () => {
		const failedDeposit = {
			...mockDeposit,
			status: 'failed',
		} as CachedDeposit;

		const { getByText } = render(
			<DepositOverview deposit={ failedDeposit } />
		);

		expect( getByText( 'Failure reason:' ) ).toBeInTheDocument();
		expect( getByText( 'Unknown' ) ).toBeInTheDocument();
	} );
} );
