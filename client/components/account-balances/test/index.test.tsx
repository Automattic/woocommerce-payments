/**
 * External dependencies
 */
import React from 'react';
import { render } from '@testing-library/react';

/**
 * Internal dependencies
 */
import AccountBalances from '../';
import AccountBalancesHeader from '../header';
import AccountBalancesTabPanel from '../balances-tab-panel';
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

// Mock the wcpaySettings localized variables needed by these tests.
declare const global: {
	wcpaySettings: {
		accountDefaultCurrency: 'USD';
		zeroDecimalCurrencies: string[];
		currencyData: Record< string, any >;
		connect: {
			country: string;
		};
	};
};

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
			},
		};
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
	test( 'renders the correct tab title and currency data', () => {
		mockGetCurrencyTabTitle.mockReturnValue( 'USD Balance' );
		mockOverviews( [ createMockOverview( 'usd', 10000, 20000 ) ] );

		render( <AccountBalancesTabPanel /> );

		const availableAmount = document.querySelector(
			'.wcpay-account-available__balance_item .wcpay-account-balances__balances__item__amount'
		) as HTMLElement;

		const pendingAmount = document.querySelector(
			'.wcpay-account-pending__balance_item .wcpay-account-balances__balances__item__amount'
		) as HTMLElement;

		// Check the available and pending amounts are rendered correctly.
		expect( availableAmount ).toHaveTextContent( '$200.00' );
		expect( pendingAmount ).toHaveTextContent( '$100.00' );
	} );

	test( 'renders JPY currency correctly', () => {
		mockGetCurrencyTabTitle.mockReturnValue( 'JPY Balance' );
		mockOverviews( [ createMockOverview( 'jpy', 12300, 4560 ) ] );

		render( <AccountBalancesTabPanel /> );

		const availableAmount = document.querySelector(
			'.wcpay-account-available__balance_item .wcpay-account-balances__balances__item__amount'
		) as HTMLElement;

		const pendingAmount = document.querySelector(
			'.wcpay-account-pending__balance_item .wcpay-account-balances__balances__item__amount'
		) as HTMLElement;

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

		render( <AccountBalancesTabPanel /> );

		// Check the tab titles are rendered correctly.
		const tabTitles = document.querySelectorAll(
			'.components-tab-panel__tabs-item'
		) as NodeListOf< HTMLElement >;

		expect( tabTitles[ 0 ] ).toHaveTextContent( 'EUR Balance' );
		expect( tabTitles[ 1 ] ).toHaveTextContent( 'USD Balance' );
		expect( tabTitles[ 2 ] ).toHaveTextContent( 'JPY Balance' );

		const eurAvailableAmount = document.querySelector(
			'.wcpay-account-available__balance_item .wcpay-account-balances__balances__item__amount'
		) as HTMLElement;

		const eurPendingAmount = document.querySelector(
			'.wcpay-account-pending__balance_item .wcpay-account-balances__balances__item__amount'
		) as HTMLElement;

		// Check the available and pending amounts are rendered correctly for the first tab.
		expect( eurAvailableAmount ).toHaveTextContent( '€27.39' );
		expect( eurPendingAmount ).toHaveTextContent( '€76.60' );

		// Create a click event to trigger clicking on the other tabs.
		const clickEvent = new MouseEvent( 'click', {
			view: window,
			bubbles: true,
			cancelable: true,
		} );

		// Trigger clicking on the second tab.
		tabTitles[ 1 ].dispatchEvent( clickEvent );

		const usdAvailableAmount = document.querySelector(
			'.wcpay-account-available__balance_item .wcpay-account-balances__balances__item__amount'
		) as HTMLElement;

		const usdPendingAmount = document.querySelector(
			'.wcpay-account-pending__balance_item .wcpay-account-balances__balances__item__amount'
		) as HTMLElement;

		// Check the available and pending amounts are rendered correctly for the first tab.
		expect( usdAvailableAmount ).toHaveTextContent( '$479.41' );
		expect( usdPendingAmount ).toHaveTextContent( '$848.75' );

		// Trigger clicking on the second tab.
		tabTitles[ 2 ].dispatchEvent( clickEvent );

		const jpyAvailableAmount = document.querySelector(
			'.wcpay-account-available__balance_item .wcpay-account-balances__balances__item__amount'
		) as HTMLElement;

		const jpyPendingAmount = document.querySelector(
			'.wcpay-account-pending__balance_item .wcpay-account-balances__balances__item__amount'
		) as HTMLElement;

		// Check the available and pending amounts are rendered correctly for the first tab.
		expect( jpyAvailableAmount ).toHaveTextContent( '¥90' );
		expect( jpyPendingAmount ).toHaveTextContent( '¥20' );
	} );
} );
