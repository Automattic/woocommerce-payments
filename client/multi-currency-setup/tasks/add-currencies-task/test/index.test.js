/**
 * External dependencies
 */
import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react';
import { useSelect } from '@wordpress/data';

/**
 * Internal dependencies
 */
import AddCurrenciesTask from '..';
import {
	useCurrencies,
	useAvailableCurrencies,
	useDefaultCurrency,
	useEnabledCurrencies,
	useSettings,
} from 'wcpay/data';

import WizardTaskContext from '../../../../additional-methods-setup/wizard/task/context';
import { recommendedCurrencyCodes } from '../constants';
import { __ } from '@wordpress/i18n';

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

const setCompletedMock = jest.fn();

const createContainer = () => {
	const { container } = render(
		<WizardTaskContext.Provider
			value={ { isActive: true, setCompleted: setCompletedMock } }
		>
			<AddCurrenciesTask />
		</WizardTaskContext.Provider>
	);
	return container;
};

describe( 'Multi-Currency enabled currencies list', () => {
	beforeEach( () => {
		global.wcpaySettings = { zeroDecimalCurrencies: [] };
	} );

	afterEach( () => {
		jest.clearAllMocks();
	} );

	test( 'add currencies task renders correctly', () => {
		const container = createContainer();
		expect( container ).toMatchSnapshot(
			'snapshot-multi-currency-onboarding'
		);
	} );

	test( 'recommended currencies are checked by default', () => {
		createContainer();
		recommendedCurrencyCodes.forEach( ( currencyCode ) => {
			if ( currencyCode !== defaultCurrency.code ) {
				expect(
					screen.getByRole( 'checkbox', {
						name: new RegExp(
							availableCurrencies[ currencyCode ].name,
							'g'
						),
					} )
				).toBeChecked();
			} else {
				expect(
					screen.queryByRole( 'checkbox', {
						name: new RegExp(
							availableCurrencies[ currencyCode ].name,
							'g'
						),
					} )
				).not.toBeInTheDocument();
			}
		} );
	} );

	test( "store currency shouldn't be visible", () => {
		createContainer();
		expect(
			screen.queryByRole( 'checkbox', { name: /US Dollar/ } )
		).not.toBeInTheDocument();
	} );

	test( 'currency search works with currency name', () => {
		const container = createContainer();

		fireEvent.change(
			screen.getByPlaceholderText(
				__( 'Search currencies', 'woocommerce-payments' )
			),
			{ target: { value: 'Danish krone' } }
		);

		expect( container ).toMatchSnapshot(
			'snapshot-currency-search-by-name'
		);

		expect(
			screen.getByRole( 'checkbox', { name: /Danish krone/ } )
		).toBeVisible();
		expect(
			screen.queryByRole( 'checkbox', { name: /Euro/ } )
		).not.toBeInTheDocument();
	} );

	test( 'currency search works with currency code', () => {
		const container = createContainer();

		fireEvent.change(
			screen.getByPlaceholderText(
				__( 'Search currencies', 'woocommerce-payments' )
			),
			{ target: { value: 'DKK' } }
		);

		expect( container ).toMatchSnapshot(
			'snapshot-currency-search-by-code'
		);

		expect(
			screen.getByRole( 'checkbox', { name: /Danish krone/ } )
		).toBeVisible();
		expect(
			screen.queryByRole( 'checkbox', { name: /Euro/ } )
		).not.toBeInTheDocument();
	} );

	test( 'currency search works with currency symbol', () => {
		const container = createContainer();

		fireEvent.change(
			screen.getByPlaceholderText(
				__( 'Search currencies', 'woocommerce-payments' )
			),
			{ target: { value: '€' } }
		);

		expect( container ).toMatchSnapshot(
			'snapshot-currency-search-by-symbol'
		);

		expect(
			screen.getByRole( 'checkbox', { name: /Euro/ } )
		).toBeVisible();
		expect(
			screen.queryByRole( 'checkbox', { name: /Danish krone/ } )
		).not.toBeInTheDocument();
	} );

	test( "shouldn't proceed with no selected currency", () => {
		createContainer();
		// uncheck all recommended currencies
		recommendedCurrencyCodes.forEach( ( code ) => {
			if ( code !== defaultCurrency.code ) {
				fireEvent.click(
					screen.queryByRole( 'checkbox', {
						name: new RegExp(
							availableCurrencies[ code ].name,
							'g'
						),
					} )
				);
			}
		} );

		expect(
			screen.getByRole( 'button', {
				name: /Add ([a-z0-9]+ )?currencies/,
			} )
		).toBeDisabled();
	} );

	test( 'should proceed with at least one selected currency', () => {
		createContainer();
		// uncheck all recommended currencies
		recommendedCurrencyCodes.forEach( ( code ) => {
			if ( code !== defaultCurrency.code ) {
				fireEvent.click(
					screen.queryByRole( 'checkbox', {
						name: new RegExp(
							availableCurrencies[ code ].name,
							'g'
						),
					} )
				);
			}
		} );

		expect(
			screen.getByRole( 'button', {
				name: /Add ([a-z0-9]+ )?currenc(y|ies)/i,
			} )
		).toBeDisabled();

		fireEvent.click(
			screen.queryByRole( 'checkbox', {
				name: /Euro/,
			} )
		);

		expect(
			screen.getByRole( 'button', {
				name: /Add ([a-z0-9]+ )?currenc(y|ies)/i,
			} )
		).toBeEnabled();

		expect(
			screen.getByRole( 'button', {
				name: /Add ([a-z0-9]+ )?currenc(y|ies)/i,
			} )
		).toHaveTextContent( 'Add 1 currency' );

		fireEvent.click(
			screen.queryByRole( 'checkbox', {
				name: /Pound sterling/,
			} )
		);

		expect(
			screen.getByRole( 'button', {
				name: /Add ([a-z0-9]+ )?currenc(y|ies)/i,
			} )
		).toBeEnabled();

		expect(
			screen.getByRole( 'button', {
				name: /Add ([a-z0-9]+ )?currenc(y|ies)/i,
			} )
		).toHaveTextContent( 'Add 2 currencies' );
	} );

	test( 'should hide recommended currencies section, if all recommended currencies are enabled', () => {
		useEnabledCurrencies.mockReturnValue( {
			enabledCurrencies: availableCurrencies,
			submitEnabledCurrenciesUpdate: jest.fn(),
		} );

		const container = createContainer();

		expect( container ).toMatchSnapshot(
			'snapshot-all-currencies-selected'
		);

		expect( screen.queryAllByRole( 'checkbox' ).length ).toBe( 0 );
		expect(
			screen.queryByText( /Recommended currencies/ )
		).not.toBeInTheDocument();

		expect(
			screen.getByRole( 'button', {
				name: /Add ([a-z0-9]+ )?currenc(y|ies)/i,
			} )
		).toBeDisabled();

		// Reset mock currencies to original state.
		useEnabledCurrencies.mockReturnValue( {
			enabledCurrencies: enabledCurrencies,
			submitEnabledCurrenciesUpdate: jest.fn(),
		} );
	} );

	test( 'should save the checkboxes state on "continue" click', () => {
		createContainer();
		// uncheck all recommended currencies
		recommendedCurrencyCodes.forEach( ( code ) => {
			if ( code !== defaultCurrency.code ) {
				fireEvent.click(
					screen.queryByRole( 'checkbox', {
						name: new RegExp(
							availableCurrencies[ code ].name,
							'g'
						),
					} )
				);
			}
		} );

		expect(
			screen.getByRole( 'button', {
				name: /Add ([a-z0-9]+ )?currenc(y|ies)/i,
			} )
		).toBeDisabled();

		fireEvent.click(
			screen.queryByRole( 'checkbox', {
				name: /Euro/gi,
			} )
		);

		fireEvent.click(
			screen.queryByRole( 'checkbox', {
				name: /Pound sterling/gi,
			} )
		);

		expect(
			screen.getByRole( 'button', {
				name: /Add ([a-z0-9]+ )?currenc(y|ies)/i,
			} )
		).toBeEnabled();

		expect(
			screen.getByRole( 'button', {
				name: /Add ([a-z0-9]+ )?currenc(y|ies)/i,
			} )
		).toHaveTextContent( 'Add 2 currencies' );

		fireEvent.click(
			screen.getByRole( 'button', {
				name: /Add ([a-z0-9]+ )?currenc(y|ies)/i,
			} )
		);

		expect( setCompletedMock ).toHaveBeenCalledWith(
			{
				initialCurrencies: Object.keys( enabledCurrencies ),
			},
			'multi-currency-settings'
		);
	} );
} );
