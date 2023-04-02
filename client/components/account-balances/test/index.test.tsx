/**
 * External dependencies
 */
import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react';

/**
 * Internal dependencies
 */
import AccountBalances from '../';
import AccountBalancesHeader from '../header';
import AccountBalancesTabPanel from '../balances-tab-panel';
import BalanceTooltip from '../balance-tooltip';
import { getGreeting, getCurrencyTabTitle } from '../utils';
import { useCurrentWpUser } from '../hooks';
import { useAllDepositsOverviews } from 'wcpay/data';

const mockUser = {
	id: 123,
	first_name: 'Tester',
	username: 'admin',
	name: 'admin',
	nickname: 'Tester-nickname',
	last_name: 'Tester-lastname',
	email: 'tester@test.com',
	locale: 'en',
};

const mockAccount: AccountOverview.Account = {
	default_currency: 'USD',
	deposits_blocked: false,
	deposits_disabled: false,
	deposits_schedule: {
		delay_days: 0,
		interval: 'weekly',
		weekly_anchor: 'Monday',
	},
};

// Mock the wcpaySettings localized variables needed by these tests.
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
const mockWcPaySettings = {
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
	},
};

jest.mock( '../utils', () => ( {
	getTimeOfDayString: jest.fn(),
	getGreeting: jest.fn(),
	getCurrencyTabTitle: jest.fn(),
} ) );

jest.mock( '../hooks', () => ( {
	useCurrentWpUser: jest.fn(),
} ) );

jest.mock( 'wcpay/data', () => ( {
	useAllDepositsOverviews: jest.fn(),
} ) );

const mockGetGreeting = getGreeting as jest.MockedFunction<
	typeof getGreeting
>;
const mockUseCurrentWpUser = useCurrentWpUser as jest.MockedFunction<
	typeof useCurrentWpUser
>;
const mockGetCurrencyTabTitle = getCurrencyTabTitle as jest.MockedFunction<
	typeof getCurrencyTabTitle
>;
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

// Creates a mock Overview object for the given currency code and balance amounts.
const createMockOverview = (
	currencyCode: string,
	pendingAmount: number,
	availableAmount: number
): AccountOverview.Overview => {
	return {
		currency: currencyCode,
		pending: {
			amount: pendingAmount,
			currency: currencyCode,
			source_types: [],
		},
		available: {
			amount: availableAmount,
			currency: currencyCode,
			source_types: [],
		},
		lastPaid: {
			id: '123',
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
			amount: 0,
			automatic: true,
			currency: null,
			bankAccount: null,
			created: Date.now(),
			date: Date.now(),
			fee: 0,
			fee_percentage: 0,
			status: 'scheduled',
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

describe( 'AccountBalances', () => {
	beforeEach( () => {
		global.wcpaySettings = mockWcPaySettings;
	} );

	test( 'renders', () => {
		const expectedGreeting = 'Good afternoon, Tester 👋';
		mockGetGreeting.mockReturnValue( expectedGreeting );
		mockUseCurrentWpUser.mockReturnValue( {
			user: mockUser,
			isLoading: false,
		} );
		mockGetCurrencyTabTitle.mockReturnValue( 'USD Balance' );
		mockOverviews( [ createMockOverview( 'usd', 100, 200 ) ] );
		const { container } = render( <AccountBalances /> );
		expect( container ).toMatchSnapshot();
	} );
} );

describe( 'AccountBalancesHeader', () => {
	test( 'renders the correct greeting in the header', () => {
		const expectedGreeting = 'Good afternoon, Tester 👋';
		mockGetGreeting.mockReturnValue( expectedGreeting );
		mockUseCurrentWpUser.mockReturnValue( {
			user: mockUser,
			isLoading: false,
		} );
		const { getByText } = render( <AccountBalancesHeader /> );
		getByText( expectedGreeting );
	} );
} );

describe( 'AccountBalancesTabPanel', () => {
	beforeEach( () => {
		global.wcpaySettings = mockWcPaySettings;
	} );

	test( 'renders the correct tab title and currency data', () => {
		mockGetCurrencyTabTitle.mockReturnValue( 'USD Balance' );
		mockOverviews( [ createMockOverview( 'usd', 10000, 20000 ) ] );

		// Use a query method returned by the render function: (you could also use `container` which will represent `document`)
		const { getByText, getByLabelText } = render(
			<AccountBalancesTabPanel />
		);

		// Check the tab title is rendered correctly.
		getByText( 'Available funds' );
		getByText( 'Pending funds' );

		const availableAmount = getByLabelText( 'Available funds' );
		const pendingAmount = getByLabelText( 'Pending funds' );

		// Check the available and pending amounts are rendered correctly.
		expect( availableAmount ).toHaveTextContent( '$200.00' );
		expect( pendingAmount ).toHaveTextContent( '$100.00' );
	} );

	test( 'renders JPY currency correctly', () => {
		mockGetCurrencyTabTitle.mockReturnValue( 'JPY Balance' );
		mockOverviews( [ createMockOverview( 'jpy', 12300, 4560 ) ] );

		const { getByText, getByLabelText } = render(
			<AccountBalancesTabPanel />
		);

		// Check the tab title is rendered correctly.
		getByText( 'Available funds' );
		getByText( 'Pending funds' );

		const availableAmount = getByLabelText( 'Available funds' );
		const pendingAmount = getByLabelText( 'Pending funds' );

		// Check the available and pending amounts are rendered correctly.
		expect( availableAmount ).toHaveTextContent( '¥46' );
		expect( pendingAmount ).toHaveTextContent( '¥123' );
	} );

	test( 'renders multiple currency tabs', () => {
		mockGetCurrencyTabTitle.mockImplementation(
			( currencyCode: string ) => {
				return `${ currencyCode.toUpperCase() } Balance`;
			}
		);
		mockOverviews( [
			createMockOverview( 'eur', 7660, 2739 ),
			createMockOverview( 'usd', 84875, 47941 ),
			createMockOverview( 'jpy', 2000, 9000 ),
		] );

		const { getByLabelText } = render( <AccountBalancesTabPanel /> );

		// Get all the tab elements to check the tab titles are rendered correctly and for testing tab switching.
		const tabTitles = screen.getAllByRole( 'tab' );

		expect( tabTitles[ 0 ] ).toHaveTextContent( 'EUR Balance' );
		expect( tabTitles[ 1 ] ).toHaveTextContent( 'USD Balance' );
		expect( tabTitles[ 2 ] ).toHaveTextContent( 'JPY Balance' );

		// Check the first tab (EUR).
		const eurAvailableAmount = getByLabelText( 'Available funds' );
		const eurPendingAmount = getByLabelText( 'Pending funds' );

		// Check the available and pending amounts are rendered correctly for the first tab.
		expect( eurAvailableAmount ).toHaveTextContent( '€27.39' );
		expect( eurPendingAmount ).toHaveTextContent( '€76.60' );

		/**
		 * Change the tab to the second tab (USD).
		 */
		fireEvent.click( tabTitles[ 1 ] );

		const usdAvailableAmount = getByLabelText( 'Available funds' );
		const usdPendingAmount = getByLabelText( 'Pending funds' );

		// Check the available and pending amounts are rendered correctly for the first tab.
		expect( usdAvailableAmount ).toHaveTextContent( '$479.41' );
		expect( usdPendingAmount ).toHaveTextContent( '$848.75' );

		/**
		 * Change the tab to the third tab (JPY).
		 */
		fireEvent.click( tabTitles[ 2 ] );

		const jpyAvailableAmount = getByLabelText( 'Available funds' );
		const jpyPendingAmount = getByLabelText( 'Pending funds' );

		// Check the available and pending amounts are rendered correctly for the first tab.
		expect( jpyAvailableAmount ).toHaveTextContent( '¥90' );
		expect( jpyPendingAmount ).toHaveTextContent( '¥20' );
	} );
} );

describe( 'BalanceTooltip', () => {
	test( 'renders the correct tooltip text for the available balance', () => {
		const expectedTooltipText =
			'The amount of funds available to be deposited.';

		render(
			<BalanceTooltip
				label="Available funds tooltip"
				content={ expectedTooltipText }
			/>
		);

		const tooltipButton = screen.getByRole( 'button', {
			name: 'Available funds tooltip',
		} );
		fireEvent.click( tooltipButton );

		screen.getByText( expectedTooltipText );
	} );

	test( 'renders the correct tooltip text for a negative available balance', () => {
		const expectedTooltipText =
			'Learn more about why your account balance may be negative.';

		render(
			<BalanceTooltip
				label="Available funds tooltip"
				content={ expectedTooltipText }
			/>
		);

		const tooltipButton = screen.getByRole( 'button', {
			name: 'Available funds tooltip',
		} );
		fireEvent.click( tooltipButton );

		screen.getByText( expectedTooltipText );
	} );

	test( 'renders the correct tooltip text for the pending balance', () => {
		const delayDays = 17;
		// Insert the delayDays value into the expected tooltip text.
		const expectedTooltipText = `The amount of funds still in the ${ delayDays } day pending period.`;

		render(
			<BalanceTooltip
				label="Pending funds tooltip"
				content={ expectedTooltipText }
			/>
		);

		const tooltipButton = screen.getByRole( 'button', {
			name: 'Pending funds tooltip',
		} );
		fireEvent.click( tooltipButton );

		screen.getByText( expectedTooltipText );
	} );
} );
