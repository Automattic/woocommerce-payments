/**
 * External dependencies
 */
import React from 'react';
import { useSelect } from '@wordpress/data';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

/**
 * Internal dependencies
 */
import WizardTaskContext from '../../wizard/task/context';
import AddPaymentMethodsTask from '../add-payment-methods-task';
import {
	useEnabledPaymentMethodIds,
	usePaymentRequestEnabledSettings,
	useGetAvailablePaymentMethodIds,
	useGetPaymentMethodStatuses,
	useSettings,
} from '../../../data';
import { upeCapabilityStatuses } from 'wcpay/additional-methods-setup/constants';
import { act } from 'react-dom/test-utils';

jest.mock( '../../../data', () => ( {
	useEnabledPaymentMethodIds: jest.fn(),
	usePaymentRequestEnabledSettings: jest.fn(),
	useGetAvailablePaymentMethodIds: jest.fn(),
	useSettings: jest.fn(),
	useCurrencies: jest.fn().mockReturnValue( { isLoading: true } ),
	useEnabledCurrencies: jest.fn().mockReturnValue( {} ),
	useGetPaymentMethodStatuses: jest.fn(),
} ) );
jest.mock( '@wordpress/data', () => ( {
	useSelect: jest.fn(),
} ) );

describe( 'AddPaymentMethodsTask', () => {
	beforeEach( () => {
		useSelect.mockReturnValue( {} );
		usePaymentRequestEnabledSettings.mockReturnValue( [
			false,
			jest.fn(),
		] );
		useEnabledPaymentMethodIds.mockReturnValue( [
			[ 'card', 'giropay', 'sepa_debit' ],
			jest.fn(),
		] );
		useGetAvailablePaymentMethodIds.mockReturnValue( [
			'card',
			'bancontact',
			'giropay',
			'ideal',
			'p24',
			'sepa_debit',
			'sofort',
		] );
		useSettings.mockReturnValue( {
			saveSettings: jest.fn().mockResolvedValue( true ),
			isSaving: false,
		} );
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
		global.wcpaySettings = {
			accountEmail: 'admin@example.com',
		};
	} );

	afterEach( () => {
		jest.restoreAllMocks();
	} );

	it( 'should render the payment methods checkboxes with default values', () => {
		render(
			<WizardTaskContext.Provider value={ {} }>
				<AddPaymentMethodsTask />
			</WizardTaskContext.Provider>
		);

		const expectedToBeChecked = [
			'Credit card / debit card',
			'giropay',
			'SEPA Direct Debit',
		];

		expectedToBeChecked.forEach( function ( checkboxName ) {
			expect( screen.getByLabelText( checkboxName ) ).toBeChecked();
		} );

		const expectedNotToBeChecked = [
			'Bancontact',
			'iDEAL',
			'Przelewy24 (P24)',
			'Sofort',
			'Enable Apple Pay & Google Pay',
		];

		expectedNotToBeChecked.forEach( function ( checkboxName ) {
			expect( screen.getByLabelText( checkboxName ) ).not.toBeChecked();
		} );
	} );

	it( 'should render the active and pending payment methods checkboxes with default values', () => {
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

		render(
			<WizardTaskContext.Provider value={ {} }>
				<AddPaymentMethodsTask />
			</WizardTaskContext.Provider>
		);

		const expectedToBeChecked = [
			'Credit card / debit card',
			'giropay',
			'SEPA Direct Debit',
		];

		expectedToBeChecked.forEach( function ( checkboxName ) {
			expect( screen.getByLabelText( checkboxName ) ).toBeChecked();
		} );

		const expectedNotToBeChecked = [
			'Sofort',
			'Bancontact',
			'iDEAL',
			'Przelewy24 (P24)',
			'Enable Apple Pay & Google Pay',
		];

		expectedNotToBeChecked.forEach( function ( checkboxName ) {
			expect( screen.getByLabelText( checkboxName ) ).not.toBeChecked();
		} );

		const expectedToBeDisabled = [ 'Przelewy24 (P24)', 'Bancontact' ];

		expectedToBeDisabled.forEach( function ( checkboxName ) {
			expect( screen.getByLabelText( checkboxName ) ).toBeDisabled();
		} );
	} );

	it( 'should render the activation modal when requirements exist for the payment method', () => {
		useEnabledPaymentMethodIds.mockReturnValue( [ [], jest.fn() ] );
		useGetAvailablePaymentMethodIds.mockReturnValue( [ 'card' ] );
		useGetPaymentMethodStatuses.mockReturnValue( {
			card_payments: {
				status: upeCapabilityStatuses.UNREQUESTED,
				requirements: [ 'company.tax_id' ],
			},
		} );

		render(
			<WizardTaskContext.Provider value={ {} }>
				<AddPaymentMethodsTask />
			</WizardTaskContext.Provider>
		);

		expect(
			screen.queryByLabelText( 'Credit card / debit card' )
		).toBeInTheDocument();

		const cardCheckbox = screen.getByLabelText(
			'Credit card / debit card'
		);

		expect( cardCheckbox ).not.toBeChecked();

		jest.useFakeTimers();

		act( () => {
			// Enabling a PM with requirements should show the activation modal
			userEvent.click( cardCheckbox );
			jest.runAllTimers();
		} );

		expect(
			screen.queryByText(
				/You need to provide more information to enable Credit card \/ debit card on your checkout/
			)
		).toBeInTheDocument();

		jest.useRealTimers();
	} );

	it( 'should not render the checkboxes that are not available', () => {
		useGetAvailablePaymentMethodIds.mockReturnValue( [
			'card',
			'giropay',
		] );

		render(
			<WizardTaskContext.Provider value={ {} }>
				<AddPaymentMethodsTask />
			</WizardTaskContext.Provider>
		);

		const expectedInDocument = [ 'Credit card / debit card', 'giropay' ];

		expectedInDocument.forEach( function ( checkboxName ) {
			expect(
				screen.queryByLabelText( checkboxName )
			).toBeInTheDocument();
		} );

		const expectedNotInDocument = [
			'Bancontact',
			'iDEAL',
			'Przelewy24 (P24)',
			'SEPA Direct Debit',
			'Sofort',
		];

		expectedNotInDocument.forEach( function ( checkboxName ) {
			expect(
				screen.queryByLabelText( checkboxName )
			).not.toBeInTheDocument();
		} );
	} );

	it( 'should save the checkboxes state on checkbox click', async () => {
		const updatePaymentRequestEnabledMock = jest.fn();
		usePaymentRequestEnabledSettings.mockReturnValue( [
			false,
			updatePaymentRequestEnabledMock,
		] );
		const updateEnabledPaymentMethodIdsMock = jest.fn();
		useEnabledPaymentMethodIds.mockReturnValue( [
			[ 'card' ],
			updateEnabledPaymentMethodIdsMock,
		] );
		const setCompletedMock = jest.fn();
		render(
			<WizardTaskContext.Provider
				value={ { setCompleted: setCompletedMock } }
			>
				<AddPaymentMethodsTask />
			</WizardTaskContext.Provider>
		);

		const checkboxesToClick = [
			'Credit card / debit card', // Mark the CC payment method as un-checked.
			'Bancontact', // Mark as checked.
			'giropay', // Mark as checked.
			'iDEAL', // Mark as checked.
			'SEPA Direct Debit',
			'Przelewy24 (P24)', // Mark as checked.
			'Enable Apple Pay & Google Pay', // Enable 1-click checkouts.
		];

		jest.useFakeTimers();

		checkboxesToClick.forEach( function ( checkboxName ) {
			act( () => {
				userEvent.click( screen.getByLabelText( checkboxName ) );
				jest.runAllTimers();
			} );
		} );

		expect( setCompletedMock ).not.toHaveBeenCalled();
		expect( updateEnabledPaymentMethodIdsMock ).not.toHaveBeenCalled();
		expect( updatePaymentRequestEnabledMock ).not.toHaveBeenCalled();

		userEvent.click( screen.getByText( 'Continue' ) );

		await waitFor( () => expect( setCompletedMock ).toHaveBeenCalled() );

		expect( setCompletedMock ).toHaveBeenCalledWith(
			true,
			'setup-complete'
		);
		expect( updateEnabledPaymentMethodIdsMock ).toHaveBeenCalledWith( [
			'bancontact',
			'giropay',
			'ideal',
			'sepa_debit',
			'p24',
		] );
		expect( updatePaymentRequestEnabledMock ).toHaveBeenCalledWith( true );
		jest.useRealTimers();
	} );
} );
