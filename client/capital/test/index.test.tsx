/**
 * External dependencies
 */
import React from 'react';
import { render, screen } from '@testing-library/react';
/**
 * Internal dependencies
 */
import { useActiveLoanSummary, useLoans } from 'wcpay/data';
import CapitalPage from '../index';

// Mock the useLoans hook
jest.mock( 'wcpay/data', () => ( {
	useLoans: jest.fn(),
	useActiveLoanSummary: jest.fn(),
} ) );

declare const global: {
	wcpaySettings: {
		zeroDecimalCurrencies: string[];
		testMode: boolean;
		connect: {
			country: string;
		};
		accountLoans: {
			has_active_loan: boolean;
		};
	};
};

describe( 'CapitalPage', () => {
	beforeEach( () => {
		global.wcpaySettings = {
			zeroDecimalCurrencies: [],
			connect: {
				country: 'US',
			},
			accountLoans: { has_active_loan: true },
			testMode: true,
		};
	} );

	it( 'renders the TableCard component with loan data', () => {
		const loans = [
			{
				wcpay_capital_loan_id: 1,
				stripe_account_id: 'acct_1QD3WwR45o7u2iE3',
				stripe_offer_id: 'financingoffer_1QEpQ2J5cIRIG92xWoVJJE3S',
				stripe_loan_id: 'financingoffer_1QEpQ2J5cIRIG92xWoVJJE3S',
				amount: 100000,
				currency: 'usd',
				fee_amount: 10000,
				withhold_rate: 0.15,
				accepted_at: '2024-10-28 10:10:50',
				paid_out_at: '2024-10-28 10:11:40',
				first_paydown_at: null,
				fully_paid_at: null,
				deleted_at: null,
			},
		];
		( useLoans as jest.Mock ).mockReturnValue( {
			loans,
			isLoading: false,
		} );

		( useActiveLoanSummary as jest.Mock ).mockReturnValue( {
			summary: {
				details: {
					advance_amount: 100000,
					advance_paid_out_at: 1729505500.7316985,
					currency: 'usd',
					current_repayment_interval: {
						due_at: 1735294500,
						paid_amount: 0,
						remaining_amount: 12223,
					},
					fee_amount: 10000,
					paid_amount: 0,
					remaining_amount: 110000,
					repayments_begin_at: 1730110500,
					withhold_rate: 0.15,
				},
				isLoading: false,
			},
		} );

		const { container } = render( <CapitalPage /> );

		expect(
			screen.getByRole( 'heading', { name: 'All loans' } )
		).toBeInTheDocument();
		expect( container ).toMatchSnapshot();
	} );

	it( 'shows loading state when loans are being fetched', () => {
		( useLoans as jest.Mock ).mockReturnValue( {
			loans: [],
			isLoading: true,
		} );

		render( <CapitalPage /> );

		expect(
			screen.getByText( 'Your requested data is loading' )
		).toBeInTheDocument();
	} );
} );
