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
	useStoreSettings,
} from 'wcpay/data';

import MultiCurrencySettingsContext from '../../context';

jest.mock( 'wcpay/data', () => ( {
	useCurrencies: jest.fn(),
	useAvailableCurrencies: jest.fn(),
	useDefaultCurrency: jest.fn(),
	useEnabledCurrencies: jest.fn(),
	useCurrencySettings: jest.fn(),
	useStoreSettings: jest.fn(),
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
		last_updated: 1632460484,
	},
	EUR: {
		code: 'EUR',
		rate: '0.826381',
		name: 'Euro',
		id: 'eur',
		is_default: false,
		flag: '🇪🇺',
		symbol: '€',
		last_updated: 1632460484,
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
		last_updated: 1632460484,
	},
	EUR: {
		code: 'EUR',
		rate: '0.826381',
		name: 'Euro',
		id: 'eur',
		is_default: false,
		flag: '🇪🇺',
		symbol: '€',
		last_updated: 1632460484,
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
	last_updated: 1632460484,
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

useStoreSettings.mockReturnValue( {
	storeSettings: {
		enable_storefront_switcher: false,
		enable_auto_currency: false,
		site_theme: 'Storefront',
		store_url: 'store_path',
	},
	submitStoreSettingsUpdate: jest.fn(),
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
		};
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
			'€9.00'
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
			'€20.00'
		);

		fireEvent.change( screen.getByTestId( 'manual_rate_input' ), {
			target: {
				value: '1.73',
			},
		} );

		expect( screen.getByTestId( 'calculated_value' ) ).toHaveTextContent(
			'€18.00'
		);

		// Price rounding calculation.
		fireEvent.change( screen.getByTestId( 'price_rounding' ), {
			target: { value: '0.5' },
		} );
		expect( screen.getByTestId( 'calculated_value' ) ).toHaveTextContent(
			'€17.50'
		);

		// Price charm calculation.
		fireEvent.change( screen.getByTestId( 'price_charm' ), {
			target: { value: '-0.05' },
		} );
		expect( screen.getByTestId( 'calculated_value' ) ).toHaveTextContent(
			'€17.45'
		);

		// Submit settings test.
		const { submitCurrencySettings } = useCurrencySettings();

		fireEvent.click(
			screen.getByRole( 'button', { name: /Save Changes/i } )
		);

		expect( submitCurrencySettings ).toHaveBeenCalledWith( 'EUR', {
			exchange_rate_type: 'manual',
			manual_rate: '1.73',
			price_rounding: '0.5',
			price_charm: '-0.05',
		} );
	} );
} );
