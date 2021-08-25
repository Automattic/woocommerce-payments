/**
 * External dependencies
 */
import React from 'react';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

/**
 * Internal dependencies
 */
import WizardTaskContext from '../../wizard/task/context';

import AddPaymentMethodsTask from '../add-currencies-task';
import {
	useGetAvailablePaymentMethodIds,
	useEnabledPaymentMethodIds,
	useSettings,
	useCurrencies,
	useEnabledCurrencies,
} from '../../../data';
import WCPaySettingsContext from '../../../settings/wcpay-settings-context';

jest.mock( '../../../data', () => ( {
	useGetAvailablePaymentMethodIds: jest.fn(),
	useEnabledPaymentMethodIds: jest.fn(),
	useSettings: jest.fn(),
	useCurrencies: jest.fn(),
	useEnabledCurrencies: jest.fn(),
} ) );

jest.mock( '@wordpress/a11y', () => ( {
	...jest.requireActual( '@wordpress/a11y' ),
	speak: jest.fn(),
} ) );

const SettingsContextProvider = ( { children } ) => (
	<WCPaySettingsContext.Provider
		value={ { featureFlags: { multiCurrency: true }, accountFees: {} } }
	>
		{ children }
	</WCPaySettingsContext.Provider>
);

describe( 'AddPaymentMethodsTask', () => {
	beforeEach( () => {
		useGetAvailablePaymentMethodIds.mockReturnValue( [
			'card',
			'bancontact',
			'giropay',
			'p24',
			'ideal',
			'sepa_debit',
			'sofort',
		] );
		useSettings.mockReturnValue( {
			saveSettings: () => Promise.resolve( true ),
			isSaving: false,
		} );
		useEnabledPaymentMethodIds.mockReturnValue( [
			[ 'card' ],
			() => null,
		] );
		useCurrencies.mockReturnValue( {
			isLoading: false,
			currencies: {
				available: {
					EUR: { name: 'Euro', symbol: '€' },
					USD: { name: 'US Dollar', symbol: '$' },
					PLN: { name: 'Polish złoty', symbol: 'zł' },
				},
			},
		} );
		useEnabledCurrencies.mockReturnValue( {
			enabledCurrencies: {
				USD: { id: 'usd', code: 'USD' },
			},
		} );
	} );

	it( 'should not call the useSettings hook if the task is not active', () => {
		useGetAvailablePaymentMethodIds.mockReturnValue( [] );
		render(
			<SettingsContextProvider>
				<WizardTaskContext.Provider
					value={ { setCompleted: () => null, isActive: false } }
				>
					<AddPaymentMethodsTask />
				</WizardTaskContext.Provider>
			</SettingsContextProvider>
		);

		expect(
			screen.queryByText(
				/(we\'ll add|and) Euro \(€\) (and|to your store)/
			)
		).not.toBeInTheDocument();
		expect(
			screen.queryByText(
				/(we\'ll add|and) Polish złoty \(zł\) (and|to your store)/
			)
		).not.toBeInTheDocument();
		expect(
			screen.queryByText( 'Add payment methods' )
		).not.toBeInTheDocument();
		expect( useSettings ).not.toHaveBeenCalled();
	} );

	it( 'should not allow to move forward if no payment methods are selected', () => {
		const setCompletedMock = jest.fn();
		render(
			<SettingsContextProvider>
				<WizardTaskContext.Provider
					value={ { setCompleted: setCompletedMock, isActive: true } }
				>
					<AddPaymentMethodsTask />
				</WizardTaskContext.Provider>
			</SettingsContextProvider>
		);

		expect(
			screen.queryByText(
				/(we\'ll add|and) Euro \(€\) (and|to your store)/
			)
		).toBeInTheDocument();
		expect(
			screen.queryByText(
				/(we\'ll add|and) Polish złoty \(zł\) (and|to your store)/
			)
		).toBeInTheDocument();
		expect( screen.getByText( 'Add payment methods' ) ).toBeEnabled();
		expect( useSettings ).toHaveBeenCalled();

		// The payment methods should all be checked.
		const expectedToBeChecked = [
			'Bancontact',
			'giropay',
			'iDEAL',
			'Przelewy24 (P24)',
			'Direct debit payment',
			'Sofort',
		];

		expectedToBeChecked.forEach( function ( checkboxName ) {
			expect(
				screen.getByRole( 'checkbox', { name: checkboxName } )
			).toBeChecked();
		} );

		expect(
			screen.queryByRole( 'checkbox', { name: /Credit/ } )
		).not.toBeInTheDocument();

		// Unchecking the checkboxes and clicking "add payment methods" should display a notice.
		expectedToBeChecked.forEach( function ( checkboxName ) {
			userEvent.click(
				screen.getByRole( 'checkbox', { name: checkboxName } )
			);
		} );

		// No add currency text when no elements are checked.
		expect(
			screen.queryByText(
				/(we\'ll add|and) Euro \(€\) (and|to your store)/
			)
		).not.toBeInTheDocument();
		expect(
			screen.queryByText(
				/(we\'ll add|and) Polish złoty \(zł\) (and|to your store)/
			)
		).not.toBeInTheDocument();
		expect( screen.getByText( 'Add payment methods' ) ).not.toBeEnabled();
	} );

	it( 'should move forward when the payment methods are selected', async () => {
		const setCompletedMock = jest.fn();
		const updateEnabledPaymentMethodsMock = jest.fn();
		useEnabledPaymentMethodIds.mockReturnValue( [
			[ 'card' ],
			updateEnabledPaymentMethodsMock,
		] );
		render(
			<SettingsContextProvider>
				<WizardTaskContext.Provider
					value={ { setCompleted: setCompletedMock, isActive: true } }
				>
					<AddPaymentMethodsTask />
				</WizardTaskContext.Provider>
			</SettingsContextProvider>
		);

		expect(
			screen.queryByText(
				/(we\'ll add|and) Polish złoty \(zł\) (and|to your store)/
			)
		).toBeInTheDocument();
		expect( screen.getByText( 'Add payment methods' ) ).toBeEnabled();
		expect( useSettings ).toHaveBeenCalled();

		// The payment methods should all be checked.
		const expectedToBeChecked = [
			'Bancontact',
			'giropay',
			'iDEAL',
			'Przelewy24 (P24)',
			'Direct debit payment',
			'Sofort',
		];

		expectedToBeChecked.forEach( function ( checkboxName ) {
			expect(
				screen.getByRole( 'checkbox', { name: checkboxName } )
			).toBeChecked();
		} );
		expect(
			screen.queryByRole( 'checkbox', { name: /Credit/ } )
		).not.toBeInTheDocument();

		userEvent.click( screen.getByText( 'Add payment methods' ) );

		expect( updateEnabledPaymentMethodsMock ).toHaveBeenCalledWith( [
			'card',
			'bancontact',
			'giropay',
			'p24',
			'ideal',
			'sepa_debit',
			'sofort',
		] );
		await waitFor( () =>
			expect( setCompletedMock ).toHaveBeenCalledWith(
				{ initialMethods: [ 'card' ] },
				'setup-complete'
			)
		);
	} );

	it( 'should remove the un-checked payment methods, if they were present before', async () => {
		const setCompletedMock = jest.fn();
		const updateEnabledPaymentMethodsMock = jest.fn();
		const initialMethods = [
			'card',
			'bancontact',
			'giropay',
			'ideal',
			'p24',
			'sofort',
		];
		useEnabledPaymentMethodIds.mockReturnValue( [
			initialMethods,
			updateEnabledPaymentMethodsMock,
		] );
		render(
			<SettingsContextProvider>
				<WizardTaskContext.Provider
					value={ { setCompleted: setCompletedMock, isActive: true } }
				>
					<AddPaymentMethodsTask />
				</WizardTaskContext.Provider>
			</SettingsContextProvider>
		);

		// The payment methods should all be checked.
		const expectedToBeChecked = [
			'Bancontact',
			'giropay',
			'iDEAL',
			'Przelewy24 (P24)',
			'Direct debit payment',
			'Sofort',
		];

		expectedToBeChecked.forEach( function ( checkboxName ) {
			expect(
				screen.getByRole( 'checkbox', { name: checkboxName } )
			).toBeChecked();
		} );

		// Uncheck methods.
		const methodsToUncheck = [
			'Bancontact',
			'giropay',
			'iDEAL',
			'Przelewy24 (P24)',
			'Sofort',
		];
		methodsToUncheck.forEach( function ( checkboxName ) {
			userEvent.click(
				screen.getByRole( 'checkbox', { name: checkboxName } )
			);
		} );

		userEvent.click( screen.getByText( 'Add payment methods' ) );

		// Methods are removed.
		expect( updateEnabledPaymentMethodsMock ).toHaveBeenCalledWith( [
			'card',
			'sepa_debit',
		] );
		await waitFor( () =>
			expect( setCompletedMock ).toHaveBeenCalledWith(
				{
					initialMethods: initialMethods,
				},
				'setup-complete'
			)
		);
	} );
} );
