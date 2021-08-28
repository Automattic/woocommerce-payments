/**
 * External dependencies
 */
import React from 'react';
import { render } from '@testing-library/react';
import { useSelect } from '@wordpress/data';

/**
 * Internal dependencies
 */
import AddCurrenciesTask from '../add-currencies-task';
import {
	useCurrencies,
	useAvailableCurrencies,
	useDefaultCurrency,
	useEnabledCurrencies,
	useSettings,
} from 'wcpay/data';

import WizardTaskContext from '../../wizard/task/context';

jest.mock( 'wcpay/data', () => ( {
	useCurrencies: jest.fn(),
	useAvailableCurrencies: jest.fn(),
	useDefaultCurrency: jest.fn(),
	useEnabledCurrencies: jest.fn(),
	useSettings: jest.fn(),
} ) );

jest.mock( '@wordpress/data', () => ( {
	useSelect: jest.fn(),
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
	AUD: {
		code: 'AUD',
		rate: 1.79,
		name: 'Australian dollar',
		id: 'aud',
		is_default: false,
		flag: '🇦🇺',
		symbol: '$',
	},
	JPY: {
		code: 'JPY',
		rate: 1,
		name: 'Japanese yen',
		id: 'jpy',
		is_default: false,
		flag: '🇯🇵',
		symbol: '¥',
	},
	INR: {
		code: 'INR',
		rate: 1,
		name: 'Indian rupee',
		id: 'inr',
		is_default: false,
		flag: '🇮🇳',
		symbol: '₹',
		is_zero_decimal: false,
		last_updated: 1630070442,
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
	submitEnabledCurrenciesUpdate: jest.fn(),
} );

useSelect.mockReturnValue( {} );

useSettings.mockReturnValue( {
	saveSettings: jest.fn().mockResolvedValue( true ),
	isSaving: false,
} );

const createContainer = () => {
	const { container } = render(
		<WizardTaskContext.Provider value={ { isActive: true } }>
			<AddCurrenciesTask />
		</WizardTaskContext.Provider>
	);
	return container;
};

describe( 'Multi Currency enabled currencies list', () => {
	beforeEach( () => {
		global.wcpaySettings = { zeroDecimalCurrencies: [] };
	} );

	afterEach( () => {
		jest.clearAllMocks();
	} );

	test( 'Add currencies task renders correctly', () => {
		const container = createContainer();
		expect( container ).toMatchSnapshot();
	} );

	test( 'Recommended currencies are checked by default', () => {} );

	test( 'Currency search works with currency name', () => {} );

	test( 'Currency search works with currency code', () => {} );

	test( 'Currency search works with currency symbol', () => {} );

	test( "Shouldn't proceed with no selected currency", () => {} );

	test( 'Should proceed with at least one selected currency', () => {} );
} );
