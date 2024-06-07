/**
 * External dependencies
 */
import React from 'react';
import { render } from '@testing-library/react';
import user from '@testing-library/user-event';

/**
 * Internal dependencies
 */
import Welcome from '..';
import { useCurrentWpUser } from '../hooks';
import { useAllDepositsOverviews } from 'data';
import { useSelectedCurrency } from 'overview/hooks';
import type { Overview } from 'types/account-overview';

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

jest.mock( '../hooks', () => ( {
	useCurrentWpUser: jest.fn(),
} ) );
jest.mock( 'wcpay/data', () => ( {
	useAllDepositsOverviews: jest.fn(),
} ) );
jest.mock( 'wcpay/overview/hooks', () => ( {
	useSelectedCurrency: jest.fn(),
} ) );

const mockUseAllDepositsOverviews = useAllDepositsOverviews as jest.MockedFunction<
	typeof useAllDepositsOverviews
>;
const mockUseSelectedCurrency = useSelectedCurrency as jest.MockedFunction<
	typeof useSelectedCurrency
>;

const mockAccountOverviewCurrencies: Partial< Overview >[] = [
	{
		currency: 'usd',
	},
];
mockUseAllDepositsOverviews.mockReturnValue( {
	overviews: {
		account: null,
		currencies: mockAccountOverviewCurrencies as Overview[],
	},
	isLoading: false,
} );

// Mocks the useSelectedCurrency hook to return no previously selected currency.
const mockSetSelectedCurrency = jest.fn();
mockUseSelectedCurrency.mockReturnValue( {
	selectedCurrency: 'usd',
	setSelectedCurrency: mockSetSelectedCurrency,
} );

const mockUseCurrentWpUser = useCurrentWpUser as jest.MockedFunction<
	typeof useCurrentWpUser
>;
mockUseCurrentWpUser.mockReturnValue( {
	user: {
		id: 123,
		first_name: 'Tester',
		username: 'admin',
		name: 'admin',
		nickname: 'Tester-nickname',
		last_name: 'Tester-lastname',
		email: 'tester@test.com',
		locale: 'en',
	},
	isLoading: false,
} );

describe( 'Welcome and Currency Select', () => {
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
				DE: {
					code: 'EUR',
					symbol: '€',
					symbolPosition: 'right_space',
					thousandSeparator: ' ',
					decimalSeparator: ',',
					precision: 2,
				},
				NO: {
					code: 'NOK',
					symbol: 'kr',
					symbolPosition: 'right',
					thousandSeparator: ' ',
					decimalSeparator: ',',
					precision: 2,
				},
			},
		};
	} );

	test( 'renders the correct greeting when the user first name exists', () => {
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
		const expectedGreeting = /Good (morning|afternoon|evening), Tester 👋/;
		mockUseCurrentWpUser.mockReturnValue( {
			user: mockUser,
			isLoading: false,
		} );
		const { getByText } = render( <Welcome /> );
		getByText( expectedGreeting );
	} );

	test( 'renders the correct greeting when the user first name is empty', () => {
		const mockUser = {
			id: 123,
			first_name: '',
			username: 'admin',
			name: 'admin',
			nickname: 'Tester-nickname',
			last_name: 'Tester-lastname',
			email: 'tester@test.com',
			locale: 'en',
		};
		const expectedGreeting = /Good (morning|afternoon|evening) 👋/;
		mockUseCurrentWpUser.mockReturnValue( {
			user: mockUser,
			isLoading: false,
		} );
		const { getByText } = render( <Welcome /> );
		getByText( expectedGreeting );
	} );

	test( 'renders the currency select control if multiple deposit currencies', () => {
		mockUseAllDepositsOverviews.mockReturnValue( {
			overviews: {
				account: null,
				currencies: [
					{
						currency: 'usd',
					},
					{
						currency: 'eur',
					},
					{
						currency: 'nok',
					},
				] as Overview[],
			},
			isLoading: false,
		} );
		const { getByRole } = render( <Welcome /> );
		getByRole( 'button', {
			name: /currency/i,
		} );

		// Check default selected currency.
		const selectControl = getByRole( 'button', { name: /currency/i } );
		expect( selectControl ).toHaveTextContent( /usd/i );

		user.click( getByRole( 'button' ) );

		// Currency options should be visible.
		getByRole( 'option', { name: 'USD $' } );
		getByRole( 'option', { name: 'EUR €' } );
		getByRole( 'option', { name: 'NOK kr' } );

		// Select a currency.
		user.click( getByRole( 'option', { name: 'NOK kr' } ) );
		expect( mockSetSelectedCurrency ).toHaveBeenCalledWith( 'nok' );
	} );

	test( 'does not render the currency select control if single deposit currency', () => {
		mockUseAllDepositsOverviews.mockReturnValue( {
			overviews: {
				account: null,
				currencies: [
					{
						currency: 'nok',
					},
				] as Overview[],
			},
			isLoading: false,
		} );
		const { queryByRole } = render( <Welcome /> );
		expect(
			queryByRole( 'button', {
				name: /currency/i,
			} )
		).toBeNull();
	} );
} );
