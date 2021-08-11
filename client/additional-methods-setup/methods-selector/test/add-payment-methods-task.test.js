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
			'sofort',
			'sepa_debit',
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
			'Sofort',
			'Direct debit payment',
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
			'Sofort',
			'Direct debit payment',
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
			'Credit card / debit card', // Marks the CC payment method as un-checked.
			'Bancontact', // Marks the Bancontact payment method as checked.
			'giropay', // Marks the Giropay payment method as checked.
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
		] );
		expect( updatePaymentRequestEnabledMock ).toHaveBeenCalledWith( true );
	} );
} );
