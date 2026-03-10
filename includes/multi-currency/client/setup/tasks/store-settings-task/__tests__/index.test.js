/**
 * External dependencies
 */
import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react';

/**
 * Internal dependencies
 */
import { useCurrencies, useStoreSettings } from 'multi-currency/data';
import { useSettings, useMultiCurrency } from 'multi-currency/interface/data';
import { WizardTaskContext } from 'multi-currency/interface/functions';
import StoreSettingsTask from '..';

jest.mock( 'multi-currency/data', () => ( {
	useStoreSettings: jest.fn(),
	useCurrencies: jest.fn(),
	useSettings: jest.fn(),
	useMultiCurrency: jest.fn(),
} ) );
jest.mock( 'multi-currency/interface/data', () => ( {
	useSettings: jest.fn(),
	useMultiCurrency: jest.fn(),
} ) );

const changeableSettings = [
	'enable_storefront_switcher',
	'enable_auto_currency',
];

const mockUpdateStoreSettingValues = jest.fn();
const mockSaveStoreSettings = jest.fn();

useCurrencies.mockReturnValue( {
	currencies: {
		enabled: {
			USD: {},
			GBP: {},
		},
		default: {
			code: 'USD',
		},
	},
} );

useStoreSettings.mockReturnValue( {
	storeSettings: {
		enable_storefront_switcher: false,
		enable_auto_currency: false,
		site_theme: 'Storefront',
		store_url: 'store_path',
	},
	isDirty: false,
	isSaving: false,
	updateStoreSettingValues: mockUpdateStoreSettingValues,
	saveStoreSettings: mockSaveStoreSettings,
} );

useSettings.mockReturnValue( {
	saveSettings: jest.fn().mockResolvedValue( {} ),
	isSaving: false,
} );

const setCompletedMock = jest.fn();

const createContainer = () => {
	const { container } = render(
		<WizardTaskContext.Provider
			value={ { isActive: true, setCompleted: setCompletedMock } }
		>
			<StoreSettingsTask />
		</WizardTaskContext.Provider>
	);
	return container;
};

describe( 'Multi-Currency store settings', () => {
	afterEach( () => {
		jest.clearAllMocks();
	} );

	beforeEach( () => {
		useMultiCurrency.mockReturnValue( [ true, jest.fn() ] );
	} );

	test( 'store settings task renders correctly', () => {
		const container = createContainer();
		expect( container ).toMatchSnapshot(
			'snapshot-multi-currency-store_settings'
		);
	} );

	test( 'store settings are default unchecked', () => {
		createContainer();
		changeableSettings.forEach( ( setting ) => {
			expect( screen.getByTestId( setting ) ).not.toBeChecked();
		} );
	} );

	test( 'store settings checkbox calls updateStoreSettingValues on click', () => {
		createContainer();
		fireEvent.click( screen.getByTestId( 'enable_auto_currency' ) );
		expect( mockUpdateStoreSettingValues ).toHaveBeenCalledWith( {
			enable_auto_currency: true,
		} );

		fireEvent.click( screen.getByTestId( 'enable_storefront_switcher' ) );
		expect( mockUpdateStoreSettingValues ).toHaveBeenCalledWith( {
			enable_storefront_switcher: true,
		} );
	} );

	test( 'multi-currency is enabled if it was previously disabled', () => {
		useMultiCurrency.mockReturnValue( [ false, jest.fn() ] );

		createContainer();
		const { saveSettings } = useSettings();
		const [ , updateIsMultiCurrencyEnabled ] = useMultiCurrency();

		fireEvent.click(
			screen.getByRole( 'button', {
				name: /Continue/,
			} )
		);

		expect( saveSettings ).toHaveBeenCalled();
		expect( updateIsMultiCurrencyEnabled ).toHaveBeenCalledWith( true );
		expect( mockSaveStoreSettings ).toHaveBeenCalledWith( true );
	} );

	test( 'store settings are saved with continue button click', () => {
		createContainer();
		fireEvent.click(
			screen.getByRole( 'button', {
				name: /Continue/,
			} )
		);
		expect( mockSaveStoreSettings ).toHaveBeenCalledWith( false );
	} );

	test( 'store settings preview should open a modal with an iframe', () => {
		createContainer();
		fireEvent.click(
			screen.getByRole( 'button', {
				name: /Preview/,
			} )
		);
		expect(
			screen.getByRole( 'dialog', { name: /Preview/ } )
		).toBeInTheDocument();

		expect(
			screen
				.getByRole( 'dialog', { name: /Preview/ } )
				.querySelector( 'iframe' )
		).toHaveAttribute(
			'src',
			'/store_path?is_mc_onboarding_simulation=1&enable_storefront_switcher=false&enable_auto_currency=false'
		);
	} );

	test( 'store settings preview should open a modal with an iframe with the correct settings', () => {
		useStoreSettings.mockReturnValue( {
			storeSettings: {
				enable_storefront_switcher: true,
				enable_auto_currency: false,
				site_theme: 'Storefront',
				store_url: 'store_path',
			},
			isDirty: true,
			isSaving: false,
			updateStoreSettingValues: mockUpdateStoreSettingValues,
			saveStoreSettings: mockSaveStoreSettings,
		} );

		createContainer();

		fireEvent.click(
			screen.getByRole( 'button', {
				name: /Preview/,
			} )
		);

		expect(
			screen.getByRole( 'dialog', { name: /Preview/ } )
		).toBeInTheDocument();

		expect(
			screen
				.getByRole( 'dialog', { name: /Preview/ } )
				.querySelector( 'iframe' )
		).toHaveAttribute(
			'src',
			'/store_path?is_mc_onboarding_simulation=1&enable_storefront_switcher=true&enable_auto_currency=false'
		);
	} );
} );
