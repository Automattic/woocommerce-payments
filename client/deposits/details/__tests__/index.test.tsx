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
import { useDeposit } from 'wcpay/data/deposits';
import { DepositOverview, DepositDetails } from '../';

jest.mock( 'wcpay/data/deposits', () => ( {
	useDeposit: jest.fn(),
} ) );

jest.mock( 'transactions/list', () => () => (
	<div data-testid="transactions-list" />
) );

jest.mock( 'components/test-mode-notice', () => ( {
	TestModeNotice: () => null,
} ) );

const mockUseDeposit = useDeposit as jest.MockedFunction< typeof useDeposit >;

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

const mockManualDeposit = {
	...mockDeposit,
	automatic: false,
	fee: 0,
	fee_percentage: 0,
} as CachedDeposit;

const mockInstantDeposit = {
	...mockDeposit,
	automatic: false,
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

beforeEach( () => {
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

describe( 'Deposit overview', () => {
	beforeAll( () => {
		jest.spyOn( console, 'error' ).mockImplementation( () => null );
		jest.spyOn( console, 'warn' ).mockImplementation( () => null );
	} );

	beforeEach( () => {
		jest.clearAllMocks();
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

	test( 'renders manual payout date label for a manual payout', () => {
		const { getByText, queryByText } = render(
			<DepositOverview deposit={ mockManualDeposit } />
		);
		expect( getByText( /Manual payout date:/ ) ).toBeInTheDocument();
		expect( queryByText( /Instant payout date:/ ) ).not.toBeInTheDocument();
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

describe( 'Deposit details', () => {
	const renderDetails = ( deposit: CachedDeposit ) => {
		mockUseDeposit.mockReturnValue( { deposit, isLoading: false } );
		return render( <DepositDetails query={ { id: deposit.id } } /> );
	};

	beforeEach( () => {
		jest.clearAllMocks();
	} );

	test( 'shows the transactions list for an automatic payout', () => {
		const { getByTestId, queryByText } = renderDetails( mockDeposit );
		expect( getByTestId( 'transactions-list' ) ).toBeInTheDocument();
		expect(
			queryByText( /unable to show transaction history/ )
		).not.toBeInTheDocument();
	} );

	test( 'shows a notice instead of the transactions list for an instant payout', () => {
		const { getByText, queryByText, queryByTestId } =
			renderDetails( mockInstantDeposit );
		expect(
			getByText( /unable to show transaction history on instant payouts/ )
		).toBeInTheDocument();
		expect( getByText( 'Learn more' ) ).toBeInTheDocument();
		expect(
			queryByText( /transaction history on manual payouts/ )
		).not.toBeInTheDocument();
		expect( queryByTestId( 'transactions-list' ) ).not.toBeInTheDocument();
	} );

	test( 'shows a notice instead of the transactions list for a manual payout', () => {
		const { getByText, queryByText, queryByTestId } =
			renderDetails( mockManualDeposit );
		expect( getByText( /Manual payout date:/ ) ).toBeInTheDocument();
		expect(
			getByText( /unable to show transaction history on manual payouts/ )
		).toBeInTheDocument();
		expect(
			queryByText( /transaction history on instant payouts/ )
		).not.toBeInTheDocument();
		expect( queryByText( 'Learn more' ) ).not.toBeInTheDocument();
		expect( queryByTestId( 'transactions-list' ) ).not.toBeInTheDocument();
	} );
} );
