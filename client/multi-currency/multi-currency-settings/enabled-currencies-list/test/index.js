/**
 * External dependencies
 */
import React from 'react';
import { cleanup, fireEvent, render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

/**
 * Internal dependencies
 */
import EnabledCurrencies from '../';
import {
	useCurrencies,
	useAvailableCurrencies,
	useDefaultCurrency,
	useEnabledCurrencies,
} from 'wcpay/data';

import MultiCurrencySettingsContext from '../../../context';

jest.mock( 'wcpay/data', () => ( {
	useCurrencies: jest.fn(),
	useAvailableCurrencies: jest.fn(),
	useDefaultCurrency: jest.fn(),
	useEnabledCurrencies: jest.fn(),
} ) );

const availableCurrencies = {
	USD: {
		code: 'USD',
		rate: 1,
		name: 'United States (US) dollar',
		id: 'usd',
		is_default: true,
		flag: '🇺🇸',
		symbol: '$',
	},
	CAD: {
		code: 'CAD',
		rate: '1.206823',
		name: 'Canadian dollar',
		id: 'cad',
		is_default: false,
		flag: '🇨🇦',
		symbol: '$',
	},
	GBP: {
		code: 'GBP',
		rate: '0.708099',
		name: 'Pound sterling',
		id: 'gbp',
		is_default: false,
		flag: '🇬🇧',
		symbol: '£',
	},
	EUR: {
		code: 'EUR',
		rate: '0.826381',
		name: 'Euro',
		id: 'eur',
		is_default: false,
		flag: '🇪🇺',
		symbol: '€',
	},
	AED: {
		code: 'AED',
		rate: '3.6732',
		name: 'United Arab Emirates dirham',
		id: 'aed',
		is_default: false,
		flag: '🇦🇪',
		symbol: 'د.إ',
	},
	CDF: {
		code: 'CDF',
		rate: '2000',
		name: 'Congolese franc',
		id: 'cdf',
		is_default: false,
		flag: '🇨🇩',
		symbol: 'Fr',
	},
	NZD: {
		code: 'NZD',
		rate: '1.387163',
		name: 'New Zealand dollar',
		id: 'nzd',
		is_default: false,
		flag: '🇳🇿',
		symbol: '$',
	},
	DKK: {
		code: 'DKK',
		rate: '6.144615',
		name: 'Danish krone',
		id: 'dkk',
		is_default: false,
		flag: '🇩🇰',
		symbol: 'DKK',
	},
	BIF: {
		code: 'BIF',
		rate: '1974',
		name: 'Burundian franc',
		id: 'bif',
		is_default: false,
		flag: '🇧🇮',
		symbol: 'Fr',
	},
	CLP: {
		code: 'CLP',
		rate: '706.8',
		name: 'Chilean peso',
		id: 'clp',
		is_default: false,
		flag: '🇨🇱',
		symbol: '$',
	},
};

const enabledCurrencies = {
	USD: {
		code: 'USD',
		rate: 1,
		name: 'United States (US) dollar',
		id: 'usd',
		is_default: true,
		flag: '🇺🇸',
		symbol: '$',
	},
	CAD: {
		code: 'CAD',
		rate: '1.42',
		name: 'Canadian dollar',
		id: 'cad',
		is_default: false,
		flag: '🇨🇦',
		symbol: '$',
	},
	GBP: {
		code: 'GBP',
		rate: '0.708099',
		name: 'Pound sterling',
		id: 'gbp',
		is_default: false,
		flag: '🇬🇧',
		symbol: '£',
	},
	EUR: {
		code: 'EUR',
		rate: '0.826381',
		name: 'Euro',
		id: 'eur',
		is_default: false,
		flag: '🇪🇺',
		symbol: '€',
	},
};

const defaultCurrency = {
	code: 'USD',
	rate: 1,
	name: 'United States (US) dollar',
	id: 'usd',
	is_default: true,
	flag: '🇺🇸',
	symbol: '$',
};

useCurrencies.mockReturnValue( {
	currencies: {
		available: availableCurrencies,
		enabled: enabledCurrencies,
		default: defaultCurrency,
	},
	isLoading: false,
} );

useAvailableCurrencies.mockReturnValue( availableCurrencies );

useDefaultCurrency.mockReturnValue( defaultCurrency );

useEnabledCurrencies.mockReturnValue( {
	enabledCurrencies: enabledCurrencies,
	submitEnabledCurrenciesUpdate: () => {},
} );

const containerContext = {
	isSingleCurrencyScreenOpen: false,
	currencyCodeToShowSettingsFor: null,
	openSingleCurrencySettings: jest.fn(),
	closeSingleCurrencySettings: jest.fn(),
};

const getContainer = () => {
	return render(
		<MultiCurrencySettingsContext.Provider value={ containerContext }>
			<EnabledCurrencies />
		</MultiCurrencySettingsContext.Provider>
	);
};

describe( 'Multi-Currency enabled currencies list', () => {
	beforeEach( () => {
		jest.clearAllMocks();
		global.wcpaySettings = { zeroDecimalCurrencies: [] };
	} );

	test( 'Enabled currencies list renders correctly', () => {
		const { container } = getContainer();
		expect( container ).toMatchSnapshot();
	} );

	test( 'Available currencies modal renders correctly', () => {
		getContainer();
		expect(
			screen.queryByRole( 'dialog', { name: /add enabled currencies/i } )
		).not.toBeInTheDocument();
		fireEvent.click(
			screen.getByRole( 'button', { name: /add currencies/i } )
		);
		const modal = screen.queryByRole( 'dialog', {
			name: /add enabled currencies/i,
		} );
		expect( modal ).toBeInTheDocument();
		expect( modal ).toMatchSnapshot();
	} );

	test( 'Remove currency modal doesnt render when theres no dependency', () => {
		getContainer();
		expect(
			screen.queryByRole( 'dialog', { name: /remove euro/i } )
		).not.toBeInTheDocument();
		fireEvent.click(
			screen.getByRole( 'button', {
				name: /remove euro as an enabled currency/i,
			} )
		);
		const modal = screen.queryByRole( 'dialog', { name: /remove euro/i } );
		expect( modal ).not.toBeInTheDocument();
	} );

	test( 'Remove currency modal renders correctly', () => {
		window.multiCurrencyPaymentMethodsMap = {
			EUR: {
				giropay: 'giropay',
				sofort: 'Sofort',
				sepa_debit: 'SEPA Direct Debit',
			},
		};
		getContainer();
		expect(
			screen.queryByRole( 'dialog', { name: /remove euro/i } )
		).not.toBeInTheDocument();
		fireEvent.click(
			screen.getByRole( 'button', {
				name: /remove euro as an enabled currency/i,
			} )
		);
		const modal = screen.queryByRole( 'dialog', { name: /remove euro/i } );
		expect( modal ).toBeInTheDocument();
		expect( modal ).toMatchSnapshot();
		window.multiCurrencyPaymentMethodsMap = undefined;
	} );

	test( 'Modal should clear search term on cancel and update selected', () => {
		for ( const name of [ /cancel/i, /update selected/i ] ) {
			getContainer();
			userEvent.click(
				screen.getByRole( 'button', {
					name: /add currencies/i,
				} )
			);
			userEvent.type( screen.getByRole( 'textbox' ), 'dollar' );
			userEvent.click(
				screen.getByRole( 'button', {
					name,
				} )
			);
			userEvent.click(
				screen.getByRole( 'button', {
					name: /add currencies/i,
				} )
			);
			expect(
				screen.queryByDisplayValue( 'dollar' )
			).not.toBeInTheDocument();
			cleanup();
		}
	} );
} );
