/**
 * External dependencies
 */
import React from 'react';
import { fireEvent, render, screen } from '@testing-library/react';

/**
 * Internal dependencies
 */
import SingleCurrencySettings from '../';
import {
	useCurrencies,
	useAvailableCurrencies,
	useDefaultCurrency,
	useEnabledCurrencies,
	useCurrencySettings,
} from 'wcpay/data';

import MultiCurrencySettingsContext from '../../context';

jest.mock( 'wcpay/data', () => ( {
	useCurrencies: jest.fn(),
	useAvailableCurrencies: jest.fn(),
	useDefaultCurrency: jest.fn(),
	useEnabledCurrencies: jest.fn(),
	useCurrencySettings: jest.fn(),
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

useCurrencySettings.mockReturnValue( {
	currencySettings: {
		EUR: {
			exchange_rate_type: 'automatic',
			manual_rate: null,
			price_charm: null,
			price_rounding: null,
		},
	},
	isLoading: false,
	submitCurrencySettings: jest.fn(),
} );

const containerContext = {
	isSingleCurrencyScreenOpen: true,
	currencyCodeToShowSettingsFor: 'EUR',
	openSingleCurrencySettings: jest.fn(),
	closeSingleCurrencySettings: jest.fn(),
};

const getContainer = () => {
	return render(
		<MultiCurrencySettingsContext.Provider value={ containerContext }>
			<SingleCurrencySettings />
		</MultiCurrencySettingsContext.Provider>
	);
};

describe( 'Single currency settings screen', () => {
	beforeEach( () => {
		jest.clearAllMocks();
		global.wcpaySettings = { zeroDecimalCurrencies: [] };
	} );

	test( 'Page renders correctly', () => {
		const { container } = getContainer();
		expect( container ).toMatchSnapshot();
	} );

	test( 'Settings work correctly', () => {
		getContainer();
		expect(
			screen.queryByText( /Currency Settings/i )
		).toBeInTheDocument();
		expect(
			screen.queryByText( /Current rate: 1 USD = 0.826381 EUR/ )
		).toBeInTheDocument();

		// Check the selects if they have default values.
		expect( screen.getByTestId( 'price_rounding' ) ).toHaveValue( '1' );
		expect( screen.getByTestId( 'price_charm' ) ).toHaveValue( '0' );

		// Test when Automatic rate selected.
		expect(
			screen.getByRole( 'radio', { name: /Fetch rates automatically/ } )
		).toBeChecked();
		expect( screen.queryByTestId( 'manual_rate_input' ) ).toBeNull();
		expect( screen.getByTestId( 'store_currency_value' ) ).toBeVisible();
		fireEvent.change( screen.getByTestId( 'store_currency_value' ), {
			target: {
				value: '10',
			},
		} );
		expect( screen.getByTestId( 'calculated_value' ) ).toHaveTextContent(
			'8.00€'
		);
		// Manual Rate calculation.
		fireEvent.click( screen.getByRole( 'radio', { name: /Manual/ } ) );
		expect( screen.getByTestId( 'manual_rate_input' ) ).toBeVisible();
		fireEvent.change( screen.getByTestId( 'manual_rate_input' ), {
			target: {
				value: '2',
			},
		} );

		expect( screen.getByTestId( 'calculated_value' ) ).toHaveTextContent(
			'20.00€'
		);

		fireEvent.change( screen.getByTestId( 'manual_rate_input' ), {
			target: {
				value: '1.77',
			},
		} );

		expect( screen.getByTestId( 'calculated_value' ) ).toHaveTextContent(
			'17.00€'
		);

		// Price rounding calculation.
		fireEvent.change( screen.getByTestId( 'price_rounding' ), {
			target: { value: '0.5' },
		} );
		expect( screen.getByTestId( 'calculated_value' ) ).toHaveTextContent(
			'17.50€'
		);

		// Price charm calculation.
		fireEvent.change( screen.getByTestId( 'price_charm' ), {
			target: { value: '-0.05' },
		} );
		expect( screen.getByTestId( 'calculated_value' ) ).toHaveTextContent(
			'17.45€'
		);

		// Submit settings test.
		const { submitCurrencySettings } = useCurrencySettings();

		fireEvent.click(
			screen.getByRole( 'button', { name: /Save Changes/i } )
		);

		expect( submitCurrencySettings ).toHaveBeenCalledWith( 'EUR', {
			exchange_rate_type: 'manual',
			manual_rate: '1.77',
			price_rounding: '0.5',
			price_charm: '-0.05',
		} );
	} );
} );
