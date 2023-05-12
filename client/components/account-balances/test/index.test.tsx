/**
 * External dependencies
 */
import React from 'react';
import { render, screen, fireEvent, within } from '@testing-library/react';

/**
 * Internal dependencies
 */
import AccountBalances from '../';
import AccountBalancesHeader from '../header';
import AccountBalancesTabPanel from '../balances-tab-panel';

import { getGreeting, getCurrencyTabTitle } from '../utils';
import { useCurrentWpUser } from '../hooks';
import { useAllDepositsOverviews, useInstantDeposit } from 'wcpay/data';
import { useSelectedCurrency } from 'wcpay/overview/hooks';

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
		delay_days: 17,
		interval: 'weekly',
		weekly_anchor: 'Monday',
		monthly_anchor: 1,
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
	useInstantDeposit: jest.fn(),
} ) );

jest.mock( 'wcpay/overview/hooks', () => ( {
	useSelectedCurrency: jest.fn(),
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
const mockUseSelectedCurrency = useSelectedCurrency as jest.MockedFunction<
	typeof useSelectedCurrency
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

// Mocks the useSelectedCurrency hook to return no previously selected currency.
const mockSetSelectedCurrency = jest.fn();
mockUseSelectedCurrency.mockReturnValue( {
	selectedCurrency: undefined,
	setSelectedCurrency: mockSetSelectedCurrency,
} );

const mockUseInstantDeposit = useInstantDeposit as jest.MockedFunction<
	typeof useInstantDeposit
>;
mockUseInstantDeposit.mockReturnValue( {
	deposit: undefined,
	inProgress: false,
	// eslint-disable-next-line @typescript-eslint/no-empty-function
	submit: () => {},
} );

// Creates a mock Overview object for the given currency code and balance amounts.
const createMockOverview = (
	currencyCode: string,
	pendingAmount: number,
	availableAmount: number,
	instantAmount: number
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
			amount: instantAmount,
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
		mockOverviews( [ createMockOverview( 'usd', 100, 200, 0 ) ] );

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
		mockOverviews( [ createMockOverview( 'usd', 10000, 20000, 0 ) ] );

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
		mockOverviews( [ createMockOverview( 'jpy', 12300, 4560, 0 ) ] );

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

	test( 'renders with selected currency correctly', () => {
		mockGetCurrencyTabTitle.mockImplementation(
			( currencyCode: string ) => {
				return `${ currencyCode.toUpperCase() } Balance`;
			}
		);
		mockOverviews( [
			createMockOverview( 'eur', 7660, 2739, 0 ),
			createMockOverview( 'usd', 84875, 47941, 0 ),
			createMockOverview( 'jpy', 2000, 9000, 0 ),
		] );
		mockUseSelectedCurrency.mockReturnValue( {
			selectedCurrency: 'jpy',
			setSelectedCurrency: mockSetSelectedCurrency,
		} );

		const { getByLabelText, getByRole } = render(
			<AccountBalancesTabPanel />
		);

		// Check the active tab is rendered correctly.
		getByRole( 'tab', {
			selected: true,
			name: /JPY Balance/,
		} );

		const pendingAmount = getByLabelText( 'Pending funds' );
		const availableAmount = getByLabelText( 'Available funds' );

		// Check the available and pending amounts are rendered correctly.
		expect( pendingAmount ).toHaveTextContent( '¥20' );
		expect( availableAmount ).toHaveTextContent( '¥90' );
	} );

	test( 'renders default tab with invalid selected currency', () => {
		mockGetCurrencyTabTitle.mockImplementation(
			( currencyCode: string ) => {
				return `${ currencyCode.toUpperCase() } Balance`;
			}
		);
		mockOverviews( [
			createMockOverview( 'eur', 7660, 2739, 0 ),
			createMockOverview( 'usd', 84875, 47941, 0 ),
			createMockOverview( 'jpy', 2000, 9000, 0 ),
		] );
		mockUseSelectedCurrency.mockReturnValue( {
			// Invalid currency code.
			selectedCurrency: '1234',
			setSelectedCurrency: mockSetSelectedCurrency,
		} );

		const { getByLabelText, getByRole } = render(
			<AccountBalancesTabPanel />
		);

		// Check the default active tab is rendered correctly.
		getByRole( 'tab', {
			selected: true,
			name: /EUR Balance/,
		} );

		const pendingAmount = getByLabelText( 'Pending funds' );
		const availableAmount = getByLabelText( 'Available funds' );

		// Check the available and pending amounts are rendered correctly.
		expect( pendingAmount ).toHaveTextContent( '€76.60' );
		expect( availableAmount ).toHaveTextContent( '€27.39' );
	} );

	test( 'renders multiple currency tabs', () => {
		mockGetCurrencyTabTitle.mockImplementation(
			( currencyCode: string ) => {
				return `${ currencyCode.toUpperCase() } Balance`;
			}
		);
		mockOverviews( [
			createMockOverview( 'eur', 7660, 2739, 0 ),
			createMockOverview( 'usd', 84875, 47941, 0 ),
			createMockOverview( 'jpy', 2000, 9000, 0 ),
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
		expect( mockSetSelectedCurrency ).toHaveBeenCalledTimes( 1 );
		expect( mockSetSelectedCurrency ).toHaveBeenCalledWith( 'usd' );
		const usdAvailableAmount = getByLabelText( 'Available funds' );
		const usdPendingAmount = getByLabelText( 'Pending funds' );

		// Check the available and pending amounts are rendered correctly for the first tab.
		expect( usdAvailableAmount ).toHaveTextContent( '$479.41' );
		expect( usdPendingAmount ).toHaveTextContent( '$848.75' );

		/**
		 * Change the tab to the third tab (JPY).
		 */
		fireEvent.click( tabTitles[ 2 ] );
		expect( mockSetSelectedCurrency ).toHaveBeenCalledTimes( 2 );
		expect( mockSetSelectedCurrency ).toHaveBeenLastCalledWith( 'jpy' );
		const jpyAvailableAmount = getByLabelText( 'Available funds' );
		const jpyPendingAmount = getByLabelText( 'Pending funds' );

		// Check the available and pending amounts are rendered correctly for the first tab.
		expect( jpyAvailableAmount ).toHaveTextContent( '¥90' );
		expect( jpyPendingAmount ).toHaveTextContent( '¥20' );
	} );

	test( 'renders the correct tooltip text for the available balance', () => {
		mockOverviews( [ createMockOverview( 'usd', 10000, 20000, 0 ) ] );
		render( <AccountBalancesTabPanel /> );

		// Check the tooltips are rendered correctly.
		const tooltipButton = screen.getByRole( 'button', {
			name: 'Available funds tooltip',
		} );
		fireEvent.click( tooltipButton );
		const tooltip = screen.getByRole( 'tooltip', {
			name: /The amount of funds available to be deposited./,
		} );
		expect( within( tooltip ).getByRole( 'link' ) ).toHaveAttribute(
			'href',
			'https://woocommerce.com/document/woocommerce-payments/deposits/deposit-schedule'
		);
	} );

	test( 'renders the correct tooltip text for a negative available balance', () => {
		mockOverviews( [ createMockOverview( 'usd', 10000, -20000, 0 ) ] );
		render( <AccountBalancesTabPanel /> );

		// Check the tooltips are rendered correctly.
		const tooltipButton = screen.getByRole( 'button', {
			name: 'Available funds tooltip',
		} );
		fireEvent.click( tooltipButton );
		const tooltip = screen.getByRole( 'tooltip', {
			// Regex optional group for `(opens in a new tab)`.
			name: /Learn more( \(.*?\))? about why your account balance may be negative./,
		} );
		expect( within( tooltip ).getByRole( 'link' ) ).toHaveAttribute(
			'href',
			'https://woocommerce.com/document/woocommerce-payments/fees-and-debits/account-showing-negative-balance'
		);
	} );

	test( 'renders the correct tooltip text for a negative pending balance', () => {
		mockOverviews( [ createMockOverview( 'usd', -10000, 20000, 0 ) ] );
		render( <AccountBalancesTabPanel /> );

		// Check the tooltips are rendered correctly.
		const tooltipButton = screen.getByRole( 'button', {
			name: 'Pending funds tooltip',
		} );
		fireEvent.click( tooltipButton );
		const tooltip = screen.getByRole( 'tooltip', {
			// Regex optional group for `(opens in a new tab)`.
			name: /Learn more( \(.*?\))? about why your account balance may be negative./,
		} );
		expect( within( tooltip ).getByRole( 'link' ) ).toHaveAttribute(
			'href',
			'https://woocommerce.com/document/woocommerce-payments/fees-and-debits/account-showing-negative-balance'
		);
	} );

	test( 'renders the correct tooltip text for the pending balance', () => {
		const delayDays = mockAccount.deposits_schedule.delay_days;
		mockOverviews( [ createMockOverview( 'usd', 10000, 20000, 0 ) ] );
		render( <AccountBalancesTabPanel /> );

		// Check the tooltips are rendered correctly.
		const tooltipButton = screen.getByRole( 'button', {
			name: 'Pending funds tooltip',
		} );
		fireEvent.click( tooltipButton );
		const tooltip = screen.getByRole( 'tooltip', {
			// Using a regex here to allow partial matching of the tooltip text.
			name: new RegExp(
				`The amount of funds still in the ${ delayDays } day pending period.`
			),
		} );
		expect( within( tooltip ).getByRole( 'link' ) ).toHaveAttribute(
			'href',
			'https://woocommerce.com/document/woocommerce-payments/deposits/deposit-schedule'
		);
	} );

	test( 'renders instant deposit button correctly', () => {
		mockOverviews( [ createMockOverview( 'usd', 10000, 20000, 30000 ) ] );
		render( <AccountBalancesTabPanel /> );

		screen.getByRole( 'button', {
			name: 'Deposit available funds',
		} );
	} );

	test( 'does not render instant deposit button when instant amount is 0', () => {
		mockOverviews( [ createMockOverview( 'usd', 10000, 20000, 0 ) ] );
		render( <AccountBalancesTabPanel /> );

		expect(
			screen.queryByRole( 'button', { name: 'Deposit available funds' } )
		).not.toBeInTheDocument();
	} );

	test( 'does not render instant deposit button when instant is undefined', () => {
		const mockOverview = createMockOverview( 'usd', 10000, 20000, 0 );
		mockOverview.instant = undefined;
		mockOverviews( [ mockOverview ] );
		render( <AccountBalancesTabPanel /> );

		expect(
			screen.queryByRole( 'button', { name: 'Deposit available funds' } )
		).not.toBeInTheDocument();
	} );
} );
