/**
 * External dependencies
 */
import React from 'react';
import { render } from '@testing-library/react';

/**
 * Internal dependencies
 */
import DepositsOverview from '..';
import NextDepositDetails from '../next-deposit';
import DepositsOverviewFooter from '../footer';
import { useAllDepositsOverviews } from 'wcpay/data';

jest.mock( 'wcpay/data', () => ( {
	useAllDepositsOverviews: jest.fn(),
	useInstantDeposit: jest.fn(),
} ) );

const mockAccount: AccountOverview.Account = {
	default_currency: 'USD',
	deposits_blocked: false,
	deposits_disabled: false,
	deposits_schedule: {
		delay_days: 0,
		interval: 'weekly',
		weekly_anchor: 'Monday',
		monthly_anchor: 1,
	},
};

declare const global: {
	wcpaySettings: {
		accountDefaultCurrency: string;
		zeroDecimalCurrencies: string[];
		currencyData: Record< string, any >;
		connect: {
			country: string;
		};
	};
};

// Creates a mock Overview object for the given currency code and balance amounts.
const createMockOverview = (
	currencyCode: string,
	depositAmount: number,
	depositDate: number,
	depositStatus: string
): AccountOverview.Overview => {
	return {
		currency: currencyCode,
		pending: {
			amount: 0,
			currency: currencyCode,
			source_types: [],
		},
		available: {
			amount: 0,
			currency: currencyCode,
			source_types: [],
		},
		lastPaid: {
			id: '0',
			type: 'deposit',
			amount: 0,
			automatic: false,
			currency: null,
			bankAccount: null,
			created: Date.now(),
			date: Date.now(),
			fee: 0,
			fee_percentage: 0,
			status: 'paid',
		},
		nextScheduled: {
			id: '456',
			type: 'deposit',
			amount: depositAmount,
			automatic: true,
			currency: currencyCode,
			bankAccount: null,
			created: Date.now(),
			date: depositDate,
			fee: 0,
			fee_percentage: 0,
			status: depositStatus,
		},
		instant: {
			currency: currencyCode,
			amount: 0,
			fee: 0,
			net: 0,
			fee_percentage: 0,
			transaction_ids: [],
		},
	};
};

const mockUseAllDepositsOverviews = useAllDepositsOverviews as jest.MockedFunction<
	typeof useAllDepositsOverviews
>;

// Mocks the DepositsOverviews hook to return the given currencies.
const mockOverviews = ( currencies: AccountOverview.Overview[] ) => {
	mockUseAllDepositsOverviews.mockReturnValue( {
		overviews: {
			currencies: currencies,
			account: mockAccount,
		},
		isLoading: null === currencies || ! currencies.length,
	} );
};

describe( 'Deposits Overview information', () => {
	beforeEach( () => {
		global.wcpaySettings = {
			accountDefaultCurrency: 'USD',
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
				EU: {
					code: 'EUR',
					symbol: '€',
					symbolPosition: 'left',
					thousandSeparator: '.',
					decimalSeparator: ',',
					precision: 2,
				},
			},
		};
	} );
	afterEach( () => {
		jest.clearAllMocks();
	} );

	test( 'Component Renders', () => {
		mockOverviews( [ createMockOverview( 'usd', 100, 0, 'estimated' ) ] );
		const { container } = render( <DepositsOverview /> );
		expect( container ).toMatchSnapshot();
	} );

	test( 'Confirm next deposit in EUR amount', () => {
		const overview = createMockOverview( 'usd', 100, 0, 'estimated' );
		const { getByText } = render(
			<NextDepositDetails isLoading={ false } overview={ overview } />
		);

		expect( getByText( '$1.00' ) ).toBeTruthy();
	} );

	test( 'Confirm next deposit in EUR amount', () => {
		global.wcpaySettings.connect.country = 'EU';

		const overview = createMockOverview( 'EUR', 647049, 0, 'estimated' );
		const { getByText } = render(
			<NextDepositDetails isLoading={ false } overview={ overview } />
		);

		expect( getByText( '€6.470,49' ) ).toBeTruthy();
	} );

	test( 'Confirm next deposit dates', () => {
		const date = Date.parse( '2021-10-01' );
		const overview = createMockOverview( 'usd', 100, date, 'estimated' );

		const { getByText } = render(
			<NextDepositDetails isLoading={ false } overview={ overview } />
		);
		expect( getByText( 'October 1, 2021' ) ).toBeTruthy();
	} );

	test( 'Confirm next deposit default status and date', () => {
		const overview = createMockOverview( 'usd', 100, 0, 'rubbish' );

		const { getByText } = render(
			<NextDepositDetails isLoading={ false } overview={ overview } />
		);
		expect( getByText( 'Estimated' ) ).toBeTruthy();
		expect( getByText( '—' ) ).toBeTruthy();
	} );
} );

describe( 'Deposits Overview footer renders', () => {
	test( 'Component Renders', () => {
		const { container, getByText } = render( <DepositsOverviewFooter /> );
		expect( container ).toMatchSnapshot();

		// Check that the button and link is rendered.
		getByText( 'View full deposits history' );
		getByText( 'Change deposit schedule' );
	} );
} );
