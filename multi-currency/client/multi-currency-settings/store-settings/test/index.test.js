/**
 * External dependencies
 */
import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react';

/**
 * Internal dependencies
 */
import { useStoreSettings } from 'wcpay/data';
import StoreSettings from '..';

jest.mock( 'wcpay/data', () => ( {
	useStoreSettings: jest.fn(),
} ) );

const changeableSettings = [
	'enable_storefront_switcher',
	'enable_auto_currency',
];

useStoreSettings.mockReturnValue( {
	storeSettings: {
		enable_storefront_switcher: false,
		enable_auto_currency: false,
		site_theme: 'Storefront',
	},
	submitStoreSettingsUpdate: jest.fn(),
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

	test( 'store settings are changed with clicking', () => {
		createContainer();
		changeableSettings.forEach( ( setting ) => {
			fireEvent.click( screen.getByTestId( setting ) );
			expect( screen.getByTestId( setting ) ).toBeChecked();
		} );
	} );

	test( 'store settings are saved with continue button click', () => {
		createContainer();
		const { submitStoreSettingsUpdate } = useStoreSettings();
		fireEvent.click(
			screen.getByRole( 'button', {
				name: /Save changes/,
			} )
		);
		expect( submitStoreSettingsUpdate ).toBeCalledWith( false, false );

		changeableSettings.forEach( ( setting ) => {
			fireEvent.click( screen.getByTestId( setting ) );
			expect( screen.getByTestId( setting ) ).toBeChecked();
		} );
		fireEvent.click(
			screen.getByRole( 'button', {
				name: /Save changes/,
			} )
		);
		expect( submitStoreSettingsUpdate ).toBeCalledWith( true, true );
	} );
} );
