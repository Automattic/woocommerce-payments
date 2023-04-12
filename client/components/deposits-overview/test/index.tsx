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
import { CachedDeposit } from 'wcpay/types/deposits';
import RecentDepositsList from '../recent-deposits-list';
import DepositsOverviewFooter from '../footer';
import DepositSchedule from '../deposit-schedule';
import SuspendedDepositNotice from '../suspended-deposit-notice';
import { useDepositIncludesLoan, useDeposits } from 'wcpay/data';
import { useSelectedCurrencyOverview } from 'wcpay/overview/hooks';
import strings from '../strings';

jest.mock( 'wcpay/data', () => ( {
	useDepositIncludesLoan: jest.fn(),
	useInstantDeposit: jest.fn(),
	useDeposits: jest.fn(),
} ) );

jest.mock( 'wcpay/overview/hooks', () => ( {
	useSelectedCurrencyOverview: jest.fn(),
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
		accountStatus: {
			deposits: {
				completed_waiting_period: boolean;
			};
		};
		accountDefaultCurrency: string;
		zeroDecimalCurrencies: string[];
		currencyData: Record< string, any >;
		connect: {
			country: string;
		};
	};
};

const mockDeposits = [
	{
		id: 'po_mock1',
		date: '2020-01-02 17:46:02',
		type: 'deposit',
		amount: 2000,
		status: 'paid',
		bankAccount: 'MOCK BANK •••• 1234 (USD)',
		currency: 'USD',
	} as CachedDeposit,
	{
		id: 'po_mock2',
		date: '2020-01-03 17:46:02',
		type: 'withdrawal',
		amount: 3000,
		status: 'pending',
		bankAccount: 'MOCK BANK •••• 1234 (USD)',
		currency: 'USD',
	} as CachedDeposit,
];

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

const createMockNewAccountOverview = (
	currencyCode: string
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
		lastPaid: undefined,
		nextScheduled: undefined,
		instant: undefined,
	};
};

const mockUseDepositIncludesLoan = useDepositIncludesLoan as jest.MockedFunction<
	typeof useDepositIncludesLoan
>;
const mockUseSelectedCurrencyOverview = useSelectedCurrencyOverview as jest.MockedFunction<
	typeof useSelectedCurrencyOverview
>;

const mockUseDeposits = useDeposits as jest.MockedFunction<
	typeof useDeposits
>;

// Mocks the DepositsOverviews hook to return the given currencies.
const mockOverviews = ( currencies: AccountOverview.Overview[] ) => {
	mockUseSelectedCurrencyOverview.mockReturnValue( {
		account: mockAccount,
		overview: currencies[ 0 ],
		isLoading: null === currencies || ! currencies.length,
	} );
};

describe( 'Deposits Overview information', () => {
	beforeEach( () => {
		global.wcpaySettings = {
			accountStatus: {
				deposits: {
					completed_waiting_period: true,
				},
			},
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
		mockUseDepositIncludesLoan.mockReturnValue( {
			includesFinancingPayout: false,
			isLoading: false,
		} );
	} );
	afterEach( () => {
		jest.clearAllMocks();
	} );

	test( 'Component Renders', () => {
		mockOverviews( [ createMockOverview( 'usd', 100, 0, 'estimated' ) ] );
		mockUseDeposits.mockReturnValue( {
			depositsCount: 0,
			deposits: mockDeposits,
			isLoading: false,
		} );

		const { container } = render( <DepositsOverview /> );
		expect( container ).toMatchSnapshot();
	} );

	test( 'Component renders without errors for new account', () => {
		mockOverviews( [ createMockNewAccountOverview( 'eur' ) ] );
		const { getByText } = render( <DepositsOverview /> );
		getByText( '€0.00' );
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
		expect( getByText( 'Unknown' ) ).toBeTruthy();
		expect( getByText( '—' ) ).toBeTruthy();
	} );

	test( 'Confirm recent deposits renders ', () => {
		mockUseDeposits.mockReturnValue( {
			depositsCount: 0,
			deposits: mockDeposits,
			isLoading: false,
		} );
		const { getByText } = render(
			<RecentDepositsList currency={ mockAccount.default_currency } />
		);
		getByText( 'January 2, 2020' );
	} );

	test( 'Confirm recent deposits does not render when no deposits', () => {
		mockUseDeposits.mockReturnValue( {
			depositsCount: 0,
			deposits: [],
			isLoading: false,
		} );

		const { container } = render(
			<RecentDepositsList currency={ mockAccount.default_currency } />
		);

		expect( container ).toBeEmptyDOMElement();
	} );

	test( 'Renders capital loan notice if deposit includes financing payout', () => {
		const overview = createMockOverview( 'usd', 100, 0, 'rubbish' );
		mockUseDepositIncludesLoan.mockReturnValue( {
			includesFinancingPayout: true,
			isLoading: false,
		} );

		const { getByRole, getByText } = render(
			<NextDepositDetails isLoading={ false } overview={ overview } />
		);

		getByText( strings.notices.depositIncludesLoan, {
			exact: false,
			ignore: '.a11y-speak-region',
		} );
		expect(
			getByRole( 'link', {
				name: 'Learn more',
			} )
		).toHaveAttribute( 'href', strings.documentationUrls.capital );
	} );

	test( `Doesn't render capital loan notice if deposit does not include financing payout`, () => {
		const overview = createMockOverview( 'usd', 100, 0, 'rubbish' );
		mockUseDepositIncludesLoan.mockReturnValue( {
			includesFinancingPayout: false,
			isLoading: false,
		} );

		const { queryByRole, queryByText } = render(
			<NextDepositDetails isLoading={ false } overview={ overview } />
		);

		expect(
			queryByText( strings.notices.depositIncludesLoan, {
				exact: false,
				ignore: '.a11y-speak-region',
			} )
		).toBeFalsy();
		expect(
			queryByRole( 'link', {
				name: 'Learn more',
			} )
		).toBeFalsy();
	} );

	test( 'Confirm new account waiting period notice does not show', () => {
		global.wcpaySettings.accountStatus.deposits.completed_waiting_period = true;
		const { queryByText } = render( <DepositsOverview /> );
		expect(
			queryByText( 'Your first deposit is held for seven business days' )
		).toBeFalsy();
	} );

	test( 'Confirm new account waiting period notice shows', () => {
		global.wcpaySettings.accountStatus.deposits.completed_waiting_period = false;
		const { getByText, getByRole } = render( <DepositsOverview /> );
		getByText( /Your first deposit is held for seven business days/, {
			ignore: '.a11y-speak-region',
		} );
		expect( getByRole( 'link', { name: /Why\?/ } ) ).toHaveAttribute(
			'href',
			'https://woocommerce.com/document/woocommerce-payments/deposits/deposit-schedule/#section-1'
		);
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

describe( 'Deposit Schedule renders', () => {
	test( 'with a weekly schedule', () => {
		const { container } = render(
			<DepositSchedule { ...mockAccount.deposits_schedule } />
		);
		const descriptionText = container.textContent;

		expect( descriptionText ).toContain(
			'Your deposits are dispatched automatically every Monday'
		);
	} );
	test( 'with a monthly schedule on the 14th', () => {
		mockAccount.deposits_schedule.interval = 'monthly';
		mockAccount.deposits_schedule.monthly_anchor = 14;

		const { container } = render(
			<DepositSchedule { ...mockAccount.deposits_schedule } />
		);
		const descriptionText = container.textContent;

		expect( descriptionText ).toContain(
			'Your deposits are dispatched automatically on the 14th of every month'
		);
	} );
	test( 'with a monthly schedule on the last day', () => {
		mockAccount.deposits_schedule.interval = 'monthly';
		mockAccount.deposits_schedule.monthly_anchor = 31;

		const { container } = render(
			<DepositSchedule { ...mockAccount.deposits_schedule } />
		);
		const descriptionText = container.textContent;

		expect( descriptionText ).toContain(
			'Your deposits are dispatched automatically on the last day of every month'
		);
	} );
	test( 'with a monthly schedule on the 2nd', () => {
		mockAccount.deposits_schedule.interval = 'monthly';
		mockAccount.deposits_schedule.monthly_anchor = 2;

		const { container } = render(
			<DepositSchedule { ...mockAccount.deposits_schedule } />
		);
		const descriptionText = container.textContent;

		expect( descriptionText ).toContain(
			'Your deposits are dispatched automatically on the 2nd of every month'
		);
	} );
	test( 'with a daily schedule', () => {
		mockAccount.deposits_schedule.interval = 'daily';

		const { container } = render(
			<DepositSchedule { ...mockAccount.deposits_schedule } />
		);
		const descriptionText = container.textContent;

		expect( descriptionText ).toContain(
			'Your deposits are dispatched automatically every day'
		);
	} );
	test( 'with a daily schedule', () => {
		mockAccount.deposits_schedule.interval = 'manual';

		const { container } = render(
			<DepositSchedule { ...mockAccount.deposits_schedule } />
		);

		// Check that a manual schedule is not rendered.
		expect( container ).toBeEmptyDOMElement();
	} );
} );

describe( 'Suspended Deposit Notice Renders', () => {
	test( 'Component Renders', () => {
		const { container } = render( <SuspendedDepositNotice /> );
		expect( container ).toMatchSnapshot();
	} );
} );
