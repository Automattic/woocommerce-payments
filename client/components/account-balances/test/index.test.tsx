/**
 * External dependencies
 */
import React from 'react';
import { render, screen, fireEvent, within } from '@testing-library/react';

/**
 * Internal dependencies
 */
import AccountBalances from '..';
import { getCurrencyTabTitle } from '../utils';
import { useAllDepositsOverviews, useInstantDeposit } from 'wcpay/data';
import { useSelectedCurrency } from 'wcpay/overview/hooks';
import type * as AccountOverview from 'wcpay/types/account-overview';

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
	default_external_accounts: [],
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
	getCurrencyTabTitle: jest.fn(),
} ) );

jest.mock( 'wcpay/data', () => ( {
	useAllDepositsOverviews: jest.fn(),
	useInstantDeposit: jest.fn(),
} ) );

jest.mock( 'wcpay/overview/hooks', () => ( {
	useSelectedCurrency: jest.fn(),
} ) );

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
		instant: {
			currency: currencyCode,
			amount: instantAmount,
			fee: 0,
			net: 0,
			fee_percentage: 0,
		},
	};
};

describe( 'AccountBalances', () => {
	beforeEach( () => {
		global.wcpaySettings = mockWcPaySettings;
	} );

	test( 'renders the correct tab title and currency data', () => {
		mockGetCurrencyTabTitle.mockReturnValue( 'USD Balance' );
		mockOverviews( [ createMockOverview( 'usd', 10000, 20000, 0 ) ] );

		// Use a query method returned by the render function: (you could also use `container` which will represent `document`)
		const { getByText, getByLabelText } = render( <AccountBalances /> );

		// Check the tab title is rendered correctly.
		getByText( 'Total balance' );
		getByText( 'Available funds' );

		const totalAmount = getByLabelText( 'Total balance' );
		const availableAmount = getByLabelText( 'Available funds' );

		// Check the total and available amounts are rendered correctly.
		expect( totalAmount ).toHaveTextContent( '$300.00' );
		expect( availableAmount ).toHaveTextContent( '$200.00' );
	} );

	test( 'renders JPY currency correctly', () => {
		mockGetCurrencyTabTitle.mockReturnValue( 'JPY Balance' );
		mockOverviews( [ createMockOverview( 'jpy', 12300, 4560, 0 ) ] );

		const { getByText, getByLabelText } = render( <AccountBalances /> );

		// Check the tab title is rendered correctly.
		getByText( 'Total balance' );
		getByText( 'Available funds' );

		const totalAmount = getByLabelText( 'Total balance' );
		const availableAmount = getByLabelText( 'Available funds' );

		// Check the total and available amounts are rendered correctly.
		expect( totalAmount ).toHaveTextContent( '¥169' );
		expect( availableAmount ).toHaveTextContent( '¥46' );
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

		const { getByLabelText, getByRole } = render( <AccountBalances /> );

		// Check the active tab is rendered correctly.
		getByRole( 'tab', {
			selected: true,
			name: /JPY Balance/,
		} );

		const totalAmount = getByLabelText( 'Total balance' );
		const availableAmount = getByLabelText( 'Available funds' );

		// Check the total and available amounts are rendered correctly.
		expect( totalAmount ).toHaveTextContent( '¥110' );
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

		const { getByLabelText, getByRole } = render( <AccountBalances /> );

		// Check the default active tab is rendered correctly.
		getByRole( 'tab', {
			selected: true,
			name: /EUR Balance/,
		} );

		const totalAmount = getByLabelText( 'Total balance' );
		const availableAmount = getByLabelText( 'Available funds' );

		// Check the total and available amounts are rendered correctly.
		expect( totalAmount ).toHaveTextContent( '€103.99' );
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

		const { getByLabelText } = render( <AccountBalances /> );

		// Get all the tab elements to check the tab titles are rendered correctly and for testing tab switching.
		const tabTitles = screen.getAllByRole( 'tab' );

		expect( tabTitles[ 0 ] ).toHaveTextContent( 'EUR Balance' );
		expect( tabTitles[ 1 ] ).toHaveTextContent( 'USD Balance' );
		expect( tabTitles[ 2 ] ).toHaveTextContent( 'JPY Balance' );

		// Check the first tab (EUR).
		const eurTotalAmount = getByLabelText( 'Total balance' );
		const eurAvailableAmount = getByLabelText( 'Available funds' );

		// Check the total and available amounts are rendered correctly for the first tab.
		expect( eurTotalAmount ).toHaveTextContent( '€103.99' );
		expect( eurAvailableAmount ).toHaveTextContent( '€27.39' );

		/**
		 * Change the tab to the second tab (USD).
		 */
		fireEvent.click( tabTitles[ 1 ] );
		expect( mockSetSelectedCurrency ).toHaveBeenCalledTimes( 1 );
		expect( mockSetSelectedCurrency ).toHaveBeenCalledWith( 'usd' );
		const usdTotalAmount = getByLabelText( 'Total balance' );
		const usdAvailableAmount = getByLabelText( 'Available funds' );

		// Check the total and available amounts are rendered correctly for the first tab.
		expect( usdTotalAmount ).toHaveTextContent( '$1,328.16' );
		expect( usdAvailableAmount ).toHaveTextContent( '$479.41' );

		/**
		 * Change the tab to the third tab (JPY).
		 */
		fireEvent.click( tabTitles[ 2 ] );
		expect( mockSetSelectedCurrency ).toHaveBeenCalledTimes( 2 );
		expect( mockSetSelectedCurrency ).toHaveBeenLastCalledWith( 'jpy' );
		const jpyTotalAmount = getByLabelText( 'Total balance' );
		const jpyAvailableAmount = getByLabelText( 'Available funds' );

		// Check the total and available amounts are rendered correctly for the first tab.
		expect( jpyTotalAmount ).toHaveTextContent( '¥110' );
		expect( jpyAvailableAmount ).toHaveTextContent( '¥90' );
	} );

	test( 'renders the correct tooltip text for the available balance', () => {
		mockOverviews( [ createMockOverview( 'usd', 10000, 20000, 0 ) ] );
		render( <AccountBalances /> );

		// Check the tooltips are rendered correctly.
		const tooltipButton = screen.getByRole( 'button', {
			name: 'Available funds tooltip',
		} );
		fireEvent.click( tooltipButton );
		const tooltip = screen.getByRole( 'tooltip', {
			name: /Available funds have completed processing and are ready to be deposited into your bank account./,
		} );
		expect( within( tooltip ).getByRole( 'link' ) ).toHaveAttribute(
			'href',
			'https://woo.com/document/woopayments/deposits/deposit-schedule/'
		);
	} );

	test( 'renders the correct tooltip text for a negative available balance', () => {
		mockOverviews( [ createMockOverview( 'usd', 10000, -20000, 0 ) ] );
		render( <AccountBalances /> );

		// Check the tooltips are rendered correctly.
		const tooltipButton = screen.getByRole( 'button', {
			name: 'Available funds tooltip',
		} );
		fireEvent.click( tooltipButton );
		const tooltip = screen.getByRole( 'tooltip', {
			// Regex optional group for `(opens in a new tab)`.
			name: /Negative account balance\? .*Discover why\./,
		} );
		expect( within( tooltip ).getAllByRole( 'link' )[ 1 ] ).toHaveAttribute(
			'href',
			'https://woo.com/document/woopayments/fees-and-debits/account-showing-negative-balance/'
		);
	} );

	test( 'renders the correct tooltip text for a negative total balance', () => {
		mockOverviews( [ createMockOverview( 'usd', -30000, 20000, 0 ) ] );
		render( <AccountBalances /> );

		// Check the tooltips are rendered correctly.
		const tooltipButton = screen.getByRole( 'button', {
			name: 'Total balance tooltip',
		} );
		fireEvent.click( tooltipButton );
		const tooltip = screen.getByRole( 'tooltip', {
			// Regex optional group for `(opens in a new tab)`.
			name: /Negative account balance\? .*Discover why\./,
		} );
		expect( within( tooltip ).getAllByRole( 'link' )[ 1 ] ).toHaveAttribute(
			'href',
			'https://woo.com/document/woopayments/fees-and-debits/account-showing-negative-balance/'
		);
	} );

	test( 'renders the correct tooltip text for the total balance', () => {
		mockOverviews( [ createMockOverview( 'usd', 10000, 20000, 0 ) ] );

		const { container } = render( <AccountBalances /> );
		expect( container ).toMatchSnapshot();

		// Check the tooltips are rendered correctly.
		const tooltipButton = screen.getByRole( 'button', {
			name: 'Total balance tooltip',
		} );
		fireEvent.click( tooltipButton );
		const tooltip = screen.getByRole( 'tooltip', {
			name: /Total balance combines both pending funds \(transactions under processing\) and available funds \(ready for deposit\)\./,
		} );
		expect( within( tooltip ).getByRole( 'link' ) ).toHaveAttribute(
			'href',
			'https://woo.com/document/woopayments/deposits/deposit-schedule/'
		);
	} );

	test( 'renders instant deposit button correctly', () => {
		mockOverviews( [ createMockOverview( 'usd', 10000, 20000, 30000 ) ] );
		render( <AccountBalances /> );

		screen.getByRole( 'button', {
			name: 'Deposit available funds',
		} );
	} );

	test( 'does not render instant deposit button when instant amount is 0', () => {
		mockOverviews( [ createMockOverview( 'usd', 10000, 20000, 0 ) ] );
		render( <AccountBalances /> );

		expect(
			screen.queryByRole( 'button', { name: 'Deposit available funds' } )
		).not.toBeInTheDocument();
	} );

	test( 'does not render instant deposit button when instant is undefined', () => {
		const mockOverview = createMockOverview( 'usd', 10000, 20000, 0 );
		mockOverview.instant = undefined;
		mockOverviews( [ mockOverview ] );
		render( <AccountBalances /> );

		expect(
			screen.queryByRole( 'button', { name: 'Deposit available funds' } )
		).not.toBeInTheDocument();
	} );
} );
