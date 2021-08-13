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
	useSettings,
} from '../../../data';

jest.mock( '../../../data', () => ( {
	useEnabledPaymentMethodIds: jest.fn(),
	usePaymentRequestEnabledSettings: jest.fn(),
	useGetAvailablePaymentMethodIds: jest.fn(),
	useSettings: jest.fn(),
	useCurrencies: jest.fn().mockReturnValue( { isLoading: true } ),
	useEnabledCurrencies: jest.fn().mockReturnValue( {} ),
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
			[ 'card', 'giropay' ],
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

		const expectedToBeChecked = [ 'Credit card / debit card', 'giropay' ];

		expectedToBeChecked.forEach( function ( checkboxName ) {
			expect(
				screen.getByRole( 'checkbox', { name: checkboxName } )
			).toBeChecked();
		} );

		const expectedNotToBeChecked = [
			'Bancontact',
			'iDEAL',
			'Przelewy24 (P24)',
			'Direct debit payment',
			'Sofort',
			'Enable Apple Pay & Google Pay',
		];

		expectedNotToBeChecked.forEach( function ( checkboxName ) {
			expect(
				screen.getByRole( 'checkbox', { name: checkboxName } )
			).not.toBeChecked();
		} );
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
				screen.queryByRole( 'checkbox', { name: checkboxName } )
			).toBeInTheDocument();
		} );

		const expectedNotInDocument = [
			'Bancontact',
			'iDEAL',
			'Przelewy24 (P24)',
			'Direct debit payment',
			'Sofort',
		];

		expectedNotInDocument.forEach( function ( checkboxName ) {
			expect(
				screen.queryByRole( 'checkbox', { name: checkboxName } )
			).not.toBeInTheDocument();
		} );
	} );

	it( 'should save the checkboxes state on "continue" click', async () => {
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
			'Przelewy24 (P24)', // Mark as checked.
			'Enable Apple Pay & Google Pay', // Enable 1-click checkouts.
		];

		checkboxesToClick.forEach( function ( checkboxName ) {
			userEvent.click(
				screen.getByRole( 'checkbox', {
					name: checkboxName,
				} )
			);
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
			'p24',
		] );
		expect( updatePaymentRequestEnabledMock ).toHaveBeenCalledWith( true );
	} );
} );
