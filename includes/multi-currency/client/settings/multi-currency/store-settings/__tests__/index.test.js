/**
 * External dependencies
 */
import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react';

/**
 * Internal dependencies
 */
import { useStoreSettings } from 'multi-currency/data';
import StoreSettings from '..';

jest.mock( 'multi-currency/data', () => ( {
	useStoreSettings: jest.fn(),
} ) );

const changeableSettings = [
	'enable_storefront_switcher',
	'enable_auto_currency',
];

const mockUpdateStoreSettingValues = jest.fn();
const mockSaveStoreSettings = jest.fn();

useStoreSettings.mockReturnValue( {
	storeSettings: {
		enable_storefront_switcher: false,
		enable_auto_currency: false,
		site_theme: 'Storefront',
	},
	isDirty: false,
	isSaving: false,
	updateStoreSettingValues: mockUpdateStoreSettingValues,
	saveStoreSettings: mockSaveStoreSettings,
} );

const createContainer = () => {
	const { container } = render( <StoreSettings /> );
	return container;
};

describe( 'Multi-Currency store settings', () => {
	afterEach( () => {
		jest.clearAllMocks();
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

	test( 'store settings button is disabled when not dirty', () => {
		createContainer();
		expect(
			screen.getByRole( 'button', { name: /Save changes/ } )
		).toBeDisabled();
	} );

	test( 'store settings button is enabled when dirty', () => {
		useStoreSettings.mockReturnValue( {
			storeSettings: {
				enable_storefront_switcher: false,
				enable_auto_currency: true,
				site_theme: 'Storefront',
			},
			isDirty: true,
			isSaving: false,
			updateStoreSettingValues: mockUpdateStoreSettingValues,
			saveStoreSettings: mockSaveStoreSettings,
		} );
		createContainer();
		expect(
			screen.getByRole( 'button', { name: /Save changes/ } )
		).toBeEnabled();
	} );

	test( 'save button calls saveStoreSettings', () => {
		useStoreSettings.mockReturnValue( {
			storeSettings: {
				enable_storefront_switcher: true,
				enable_auto_currency: true,
				site_theme: 'Storefront',
			},
			isDirty: true,
			isSaving: false,
			updateStoreSettingValues: mockUpdateStoreSettingValues,
			saveStoreSettings: mockSaveStoreSettings,
		} );
		createContainer();
		fireEvent.click(
			screen.getByRole( 'button', {
				name: /Save changes/,
			} )
		);
		expect( mockSaveStoreSettings ).toHaveBeenCalled();
	} );

	test( 'clears window.onbeforeunload when not dirty', () => {
		window.onbeforeunload = jest.fn();
		useStoreSettings.mockReturnValue( {
			storeSettings: {
				enable_storefront_switcher: false,
				enable_auto_currency: false,
				site_theme: 'Storefront',
			},
			isDirty: false,
			isSaving: false,
			updateStoreSettingValues: mockUpdateStoreSettingValues,
			saveStoreSettings: mockSaveStoreSettings,
		} );
		createContainer();
		expect( window.onbeforeunload ).toBeNull();
	} );
} );
