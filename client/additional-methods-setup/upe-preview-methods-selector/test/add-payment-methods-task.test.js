/**
 * External dependencies
 */
import React from 'react';
import { act, render, screen, waitFor } from '@testing-library/react';
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
	useManualCapture,
	useAccountDomesticCurrency,
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
	useManualCapture: jest.fn(),
	useAccountDomesticCurrency: jest.fn(),
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
			'eps',
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
			eps_payments: {
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
		useManualCapture.mockReturnValue( [ false, jest.fn() ] );
		useAccountDomesticCurrency.mockReturnValue( 'usd' );
		global.wcpaySettings = {
			accountEmail: 'admin@example.com',
		};
	} );

	afterEach( () => {
		jest.useRealTimers();
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
		expect( screen.queryByText( 'Continue' ) ).not.toBeInTheDocument();
		expect( useSettings ).not.toHaveBeenCalled();
	} );

	it( 'should allow to move forward when no payment methods are selected', () => {
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

		expect( useSettings ).toHaveBeenCalled();

		// The payment methods should all be checked.
		const expectedToBeUnchecked = [
			'Bancontact',
			'EPS',
			'giropay',
			'iDEAL',
			'Przelewy24 (P24)',
			'SEPA Direct Debit',
			'Sofort',
		];

		expectedToBeUnchecked.forEach( function ( checkboxName ) {
			expect( screen.getByLabelText( checkboxName ) ).not.toBeChecked();
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
		expect( screen.getByText( 'Continue' ) ).toBeEnabled();
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
				/(we\'ll add|and) Polish złoty \(zł\) (and|to your store)/,
				{
					ignore: '.a11y-speak-region',
				}
			)
		).not.toBeInTheDocument();
		expect( screen.getByText( 'Continue' ) ).toBeEnabled();
		expect( useSettings ).toHaveBeenCalled();

		// The payment methods should all be checked.
		const expectedToBeUnchecked = [
			'Bancontact',
			'EPS',
			'giropay',
			'iDEAL',
			'Przelewy24 (P24)',
			'SEPA Direct Debit',
			'Sofort',
		];

		expectedToBeUnchecked.forEach( function ( checkboxName ) {
			expect( screen.getByLabelText( checkboxName ) ).not.toBeChecked();
		} );
		expect(
			screen.queryByRole( 'checkbox', { name: /Credit/ } )
		).toBeInTheDocument();

		jest.useFakeTimers();
		act( () => {
			userEvent.click( screen.getByLabelText( 'Przelewy24 (P24)' ) );
			jest.runOnlyPendingTimers();
		} );

		expect( screen.getByText( 'Continue' ) ).toBeEnabled();

		expect(
			screen.queryByText(
				/(we\'ll add|and) Polish złoty \(zł\) (and|to your store)/,
				{
					ignore: '.a11y-speak-region',
				}
			)
		).toBeInTheDocument();

		userEvent.click( screen.getByText( 'Continue' ) );

		expect( updateEnabledPaymentMethodsMock ).toHaveBeenCalledWith( [
			'card',
			'p24',
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
			'eps',
			'giropay',
			'p24',
			'ideal',
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
		const expectedToBeUnchecked = [
			'Bancontact',
			'EPS',
			'giropay',
			'iDEAL',
			'Przelewy24 (P24)',
			'SEPA Direct Debit',
			'Sofort',
		];

		expectedToBeUnchecked.forEach( function ( checkboxName ) {
			expect( screen.getByLabelText( checkboxName ) ).not.toBeChecked();
		} );

		jest.useFakeTimers();
		// Uncheck methods.
		act( () => {
			const methodsToCheck = [ 'Bancontact', 'giropay' ];
			methodsToCheck.forEach( function ( checkboxName ) {
				userEvent.click( screen.getByLabelText( checkboxName ) );
				jest.runOnlyPendingTimers();
			} );
		} );

		userEvent.click( screen.getByText( 'Continue' ) );

		// Methods are removed.
		expect( updateEnabledPaymentMethodsMock ).toHaveBeenCalledWith( [
			'card',
			'bancontact',
			'giropay',
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

	it( 'should not allow the disabled ones to be selected', async () => {
		useGetPaymentMethodStatuses.mockReturnValue( {
			card_payments: {
				status: upeCapabilityStatuses.ACTIVE,
				requirements: [],
			},
			bancontact_payments: {
				status: upeCapabilityStatuses.INACTIVE,
				requirements: [],
			},
			eps_payments: {
				status: upeCapabilityStatuses.ACTIVE,
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

		const { queryByRole, getByText, queryAllByTestId } = render(
			<SettingsContextProvider>
				<WizardTaskContext.Provider
					value={ { setCompleted: () => null, isActive: true } }
				>
					<AddPaymentMethodsTask />
				</WizardTaskContext.Provider>
			</SettingsContextProvider>
		);

		const expectedToBeDisabled = [
			'Bancontact',
			'Przelewy24 (P24)',
			'giropay',
			'SEPA Direct Debit',
		];

		expectedToBeDisabled.forEach( function ( checkboxName ) {
			expect(
				queryByRole( 'checkbox', { name: checkboxName } )
			).not.toBeInTheDocument();
			expect(
				getByText(
					`${ checkboxName } cannot be enabled at checkout. Click to expand.`
				)
			).toBeInTheDocument();
			expect(
				queryAllByTestId( 'loadable-checkbox-icon-warning' )
			).toHaveLength( expectedToBeDisabled.length );
		} );
	} );

	it( 'should render the activation modal when requirements exist for the payment method', () => {
		const setCompletedMock = jest.fn();
		useEnabledPaymentMethodIds.mockReturnValue( [ [ 'card' ], jest.fn() ] );
		useGetAvailablePaymentMethodIds.mockReturnValue( [ 'sofort' ] );
		useGetPaymentMethodStatuses.mockReturnValue( {
			sofort_payments: {
				status: upeCapabilityStatuses.UNREQUESTED,
				requirements: [ 'company.tax_id' ],
			},
		} );

		render(
			<SettingsContextProvider>
				<WizardTaskContext.Provider
					value={ { setCompleted: setCompletedMock, isActive: true } }
				>
					<AddPaymentMethodsTask />
				</WizardTaskContext.Provider>
			</SettingsContextProvider>
		);

		expect( screen.queryByLabelText( 'Sofort' ) ).toBeInTheDocument();

		const cardCheckbox = screen.getByLabelText( 'Sofort' );

		expect( cardCheckbox ).not.toBeChecked();

		jest.useFakeTimers();

		act( () => {
			// Enabling a PM with requirements should show the activation modal
			userEvent.click( cardCheckbox );
			jest.runOnlyPendingTimers();
		} );

		expect(
			screen.queryByText(
				/You need to provide more information to enable Sofort on your checkout/
			)
		).toBeInTheDocument();

		jest.useRealTimers();
	} );
} );
