/**
 * External dependencies
 */
import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react';

/**
 * Internal dependencies
 */
import WizardTaskContext from '../../../../additional-methods-setup/wizard/task/context';
import { useCurrencies, useStoreSettings } from 'wcpay/data';
import StoreSettingsTask from '..';

jest.mock( 'wcpay/data', () => ( {
	useStoreSettings: jest.fn(),
	useCurrencies: jest.fn(),
} ) );

const changeableSettings = [
	'enable_storefront_switcher',
	'enable_auto_currency',
];

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
	submitStoreSettingsUpdate: jest.fn(),
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
				name: /Continue/,
			} )
		);
		expect( submitStoreSettingsUpdate ).toBeCalledWith( false, false );

		changeableSettings.forEach( ( setting ) => {
			fireEvent.click( screen.getByTestId( setting ) );
			expect( screen.getByTestId( setting ) ).toBeChecked();
		} );
		fireEvent.click(
			screen.getByRole( 'button', {
				name: /Continue/,
			} )
		);
		expect( submitStoreSettingsUpdate ).toBeCalledWith( true, true );
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
		createContainer();
		fireEvent.click( screen.getByTestId( 'enable_storefront_switcher' ) );

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
