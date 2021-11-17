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

import AddPaymentMethodsTask from '../add-payment-methods-task';
import {
	useGetAvailablePaymentMethodIds,
	useEnabledPaymentMethodIds,
	useGetPaymentMethodStatuses,
	useSettings,
	useCurrencies,
	useEnabledCurrencies,
} from '../../../data';
import WCPaySettingsContext from '../../../settings/wcpay-settings-context';
import { upeCapabilityStatuses } from 'wcpay/additional-methods-setup/constants';

jest.mock( '../../../data', () => ( {
	useGetAvailablePaymentMethodIds: jest.fn(),
	useEnabledPaymentMethodIds: jest.fn(),
	useSettings: jest.fn(),
	useCurrencies: jest.fn(),
	useEnabledCurrencies: jest.fn(),
	useGetPaymentMethodStatuses: jest.fn(),
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
			'ideal',
			'p24',
			'sepa_debit',
			'sofort',
		] );
		useGetPaymentMethodStatuses.mockReturnValue( {
			card_payments: {
				status: upeCapabilityStatuses.ACTIVE,
				requirements: [],
			},
			bancontact_payments: {
				status: upeCapabilityStatuses.ACTIVE,
				requirements: [],
			},
			giropay_payments: {
				status: upeCapabilityStatuses.ACTIVE,
				requirements: [],
			},
			ideal_payments: {
				status: upeCapabilityStatuses.ACTIVE,
				requirements: [],
			},
			p24_payments: {
				status: upeCapabilityStatuses.ACTIVE,
				requirements: [],
			},
			sepa_debit_payments: {
				status: upeCapabilityStatuses.ACTIVE,
				requirements: [],
			},
			sofort_payments: {
				status: upeCapabilityStatuses.ACTIVE,
				requirements: [],
			},
		} );
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
		wcSettings = {
			currentUserData: {
				email: 'admin@example.com',
			},
		};
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
			'SEPA Direct Debit',
			'Sofort',
		];

		expectedToBeChecked.forEach( function ( checkboxName ) {
			expect( screen.getByLabelText( checkboxName ) ).toBeChecked();
		} );

		expect(
			screen.queryByRole( 'checkbox', { name: /Credit/ } )
		).not.toBeInTheDocument();

		expectedToBeChecked.forEach( function ( checkboxName ) {
			userEvent.click( screen.getByLabelText( checkboxName ) );
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
			'SEPA Direct Debit',
			'Sofort',
		];

		expectedToBeChecked.forEach( function ( checkboxName ) {
			expect( screen.getByLabelText( checkboxName ) ).toBeChecked();
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
			'SEPA Direct Debit',
			'Sofort',
		];

		expectedToBeChecked.forEach( function ( checkboxName ) {
			expect( screen.getByLabelText( checkboxName ) ).toBeChecked();
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
			userEvent.click( screen.getByLabelText( checkboxName ) );
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

	it( 'should not allow the inactive ones to be selected', async () => {
		useGetPaymentMethodStatuses.mockReturnValue( {
			card_payments: {
				status: upeCapabilityStatuses.ACTIVE,
				requirements: [],
			},
			bancontact_payments: {
				status: upeCapabilityStatuses.INACTIVE,
				requirements: [],
			},
			giropay_payments: {
				status: upeCapabilityStatuses.PENDING_APPROVAL,
				requirements: [],
			},
			ideal_payments: {
				status: upeCapabilityStatuses.ACTIVE,
				requirements: [],
			},
			p24_payments: {
				status: upeCapabilityStatuses.INACTIVE,
				requirements: [],
			},
			sepa_debit_payments: {
				status: upeCapabilityStatuses.PENDING_VERIFICATION,
				requirements: [],
			},
			sofort_payments: {
				status: upeCapabilityStatuses.ACTIVE,
				requirements: [],
			},
		} );

		useEnabledPaymentMethodIds.mockReturnValue( [
			[
				'card',
				'bancontact',
				'giropay',
				'p24',
				'ideal',
				'sepa_debit',
				'sofort',
			],
			() => null,
		] );

		render(
			<SettingsContextProvider>
				<WizardTaskContext.Provider
					value={ { setCompleted: () => null, isActive: true } }
				>
					<AddPaymentMethodsTask />
				</WizardTaskContext.Provider>
			</SettingsContextProvider>
		);

		// The payment methods should all be checked.
		const expectedToBeChecked = [
			'giropay',
			'iDEAL',
			'SEPA Direct Debit',
			'Sofort',
		];

		expectedToBeChecked.forEach( function ( checkboxName ) {
			expect( screen.getByLabelText( checkboxName ) ).toBeChecked();
		} );

		const expectedToBeUnchecked = [ 'Bancontact', 'Przelewy24 (P24)' ];

		expectedToBeUnchecked.forEach( function ( checkboxName ) {
			expect( screen.getByLabelText( checkboxName ) ).not.toBeChecked();
			// Click the inactive checkbox, to see if it gets enabled.
			userEvent.click( screen.getByLabelText( checkboxName ) );
			expect( screen.getByLabelText( checkboxName ) ).not.toBeChecked();
		} );
	} );
} );
