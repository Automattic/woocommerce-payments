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
	useSettings,
	useCurrencies,
	useEnabledCurrencies,
} from '../../../data';

jest.mock( '../../../data', () => ( {
	useGetAvailablePaymentMethodIds: jest.fn(),
	useEnabledPaymentMethodIds: jest.fn(),
	useSettings: jest.fn(),
	useCurrencies: jest.fn(),
	useEnabledCurrencies: jest.fn(),
} ) );

describe( 'AddPaymentMethodsTask', () => {
	beforeEach( () => {
		useGetAvailablePaymentMethodIds.mockReturnValue( [
			'card',
			'giropay',
			'sepa_debit',
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
			<WizardTaskContext.Provider
				value={ { setCompleted: () => null, isActive: false } }
			>
				<AddPaymentMethodsTask />
			</WizardTaskContext.Provider>
		);

		expect(
			screen.queryByText( /we\'ll add Euro \(€\) to your store/ )
		).not.toBeInTheDocument();
		expect(
			screen.queryByText( 'Add payment methods' )
		).not.toBeInTheDocument();
		expect( useSettings ).not.toHaveBeenCalled();
	} );

	it( 'should not allow to move forward if no payment methods are selected', () => {
		const setCompletedMock = jest.fn();
		render(
			<WizardTaskContext.Provider
				value={ { setCompleted: setCompletedMock, isActive: true } }
			>
				<AddPaymentMethodsTask />
			</WizardTaskContext.Provider>
		);

		expect(
			screen.queryByText( /we\'ll add Euro \(€\) to your store/ )
		).toBeInTheDocument();
		expect( screen.getByText( 'Add payment methods' ) ).toBeEnabled();
		expect( useSettings ).toHaveBeenCalled();
		// the payment methods should all be checked
		expect(
			screen.getByRole( 'checkbox', { name: 'GiroPay' } )
		).toBeChecked();
		expect(
			screen.getByRole( 'checkbox', { name: 'Direct Debit Payments' } )
		).toBeChecked();
		expect(
			screen.queryByRole( 'checkbox', { name: /Credit/ } )
		).not.toBeInTheDocument();

		// un-checking the checkboxes and clicking "add payment methods" should display a notice
		userEvent.click( screen.getByRole( 'checkbox', { name: 'GiroPay' } ) );
		userEvent.click(
			screen.getByRole( 'checkbox', { name: 'Direct Debit Payments' } )
		);

		// no "euro" text when no elements are checked
		expect(
			screen.queryByText( /we\'ll add Euro \(€\) to your store/ )
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
			<WizardTaskContext.Provider
				value={ { setCompleted: setCompletedMock, isActive: true } }
			>
				<AddPaymentMethodsTask />
			</WizardTaskContext.Provider>
		);

		expect(
			screen.queryByText( /we\'ll add Euro \(€\) to your store/ )
		).toBeInTheDocument();
		expect( screen.getByText( 'Add payment methods' ) ).toBeEnabled();
		expect( useSettings ).toHaveBeenCalled();
		// the payment methods should all be checked
		expect(
			screen.getByRole( 'checkbox', { name: 'GiroPay' } )
		).toBeChecked();
		expect(
			screen.getByRole( 'checkbox', { name: 'Direct Debit Payments' } )
		).toBeChecked();
		expect(
			screen.queryByRole( 'checkbox', { name: /Credit/ } )
		).not.toBeInTheDocument();

		userEvent.click( screen.getByText( 'Add payment methods' ) );

		expect( updateEnabledPaymentMethodsMock ).toHaveBeenCalledWith( [
			'card',
			'giropay',
			'sepa_debit',
		] );
		await waitFor( () =>
			expect( setCompletedMock ).toHaveBeenCalledWith(
				true,
				'setup-complete'
			)
		);
	} );

	it( 'should remove the un-checked payment methods, if they were present before', async () => {
		const setCompletedMock = jest.fn();
		const updateEnabledPaymentMethodsMock = jest.fn();
		useEnabledPaymentMethodIds.mockReturnValue( [
			[ 'card', 'giropay' ],
			updateEnabledPaymentMethodsMock,
		] );
		render(
			<WizardTaskContext.Provider
				value={ { setCompleted: setCompletedMock, isActive: true } }
			>
				<AddPaymentMethodsTask />
			</WizardTaskContext.Provider>
		);

		// the payment methods should all be checked
		expect(
			screen.getByRole( 'checkbox', { name: 'GiroPay' } )
		).toBeChecked();
		expect(
			screen.getByRole( 'checkbox', { name: 'Direct Debit Payments' } )
		).toBeChecked();

		// un-check giropay
		userEvent.click( screen.getByRole( 'checkbox', { name: 'GiroPay' } ) );
		userEvent.click( screen.getByText( 'Add payment methods' ) );

		// giropay is removed
		expect( updateEnabledPaymentMethodsMock ).toHaveBeenCalledWith( [
			'card',
			'sepa_debit',
		] );
	} );
} );
