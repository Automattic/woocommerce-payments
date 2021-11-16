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

		const expectedToBeChecked = [ 'card', 'giropay', 'sepa_debit' ];

		expectedToBeChecked.forEach( function ( checkboxName ) {
			expect(
				screen.getByRole( 'checkbox', { name: checkboxName } )
			).toBeChecked();
		} );

		const expectedNotToBeChecked = [
			'bancontact',
			'ideal',
			'p24',
			'sofort',
			'Enable Apple Pay & Google Pay',
		];

		expectedNotToBeChecked.forEach( function ( checkboxName ) {
			expect(
				screen.getByRole( 'checkbox', { name: checkboxName } )
			).not.toBeChecked();
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

		const expectedToBeChecked = [ 'card', 'giropay', 'sepa_debit' ];

		expectedToBeChecked.forEach( function ( checkboxName ) {
			expect(
				screen.getByRole( 'checkbox', { name: checkboxName } )
			).toBeChecked();
		} );

		const expectedNotToBeChecked = [
			'sofort',
			'bancontact',
			'ideal',
			'p24',
			'Enable Apple Pay & Google Pay',
		];

		expectedNotToBeChecked.forEach( function ( checkboxName ) {
			expect(
				screen.getByRole( 'checkbox', { name: checkboxName } )
			).not.toBeChecked();
		} );

		const expectedToBeDisabled = [ 'p24', 'bancontact' ];

		expectedToBeDisabled.forEach( function ( checkboxName ) {
			expect(
				screen.getByRole( 'checkbox', { name: checkboxName } )
			).toBeDisabled();
		} );
	} );

	it( 'should not render the checkboxes that are not available', () => {
		useGetAvailablePaymentMethodIds.mockReturnValue( [
			'card',
			'giropay',
		] );

		const { container } = render(
			<WizardTaskContext.Provider value={ {} }>
				<AddPaymentMethodsTask />
			</WizardTaskContext.Provider>
		);
		expect( container ).toMatchSnapshot();

		const expectedInDocument = [ 'card', 'giropay' ];

		expectedInDocument.forEach( function ( checkboxName ) {
			expect(
				screen.queryByRole( 'checkbox', { name: checkboxName } )
			).toBeInTheDocument();
		} );

		const expectedNotInDocument = [
			'bancontact',
			'ideal',
			'p24',
			'sepa_debit',
			'sofort',
		];

		expectedNotInDocument.forEach( function ( checkboxName ) {
			expect(
				screen.queryByRole( 'checkbox', { name: checkboxName } )
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
			'card', // Mark the CC payment method as un-checked.
			'bancontact', // Mark as checked.
			'giropay', // Mark as checked.
			'ideal', // Mark as checked.
			'sepa_debit',
			'p24', // Mark as checked.
			'Enable Apple Pay & Google Pay', // Enable 1-click checkouts.
		];

		jest.useFakeTimers();

		checkboxesToClick.forEach( function ( checkboxName ) {
			act( () => {
				userEvent.click(
					screen.getByRole( 'checkbox', { name: checkboxName } )
				);
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
