/** @format */
/**
 * External dependencies
 */
import { render, within, screen } from '@testing-library/react';
import user from '@testing-library/user-event';

/**
 * Internal dependencies
 */
import PaymentMethodsSelector from '..';

import {
	useEnabledPaymentMethodIds,
	useGetAvailablePaymentMethodIds,
} from 'wcpay/data';

jest.mock( 'wcpay/data', () => ( {
	useEnabledPaymentMethodIds: jest.fn(),
	useGetAvailablePaymentMethodIds: jest.fn(),
	useCurrencies: jest.fn().mockReturnValue( { isLoading: true } ),
	useEnabledCurrencies: jest.fn().mockReturnValue( {} ),
} ) );

describe( 'PaymentMethodsSelector', () => {
	beforeEach( () => {
		useEnabledPaymentMethodIds.mockReturnValue( [ [], jest.fn() ] );
		useGetAvailablePaymentMethodIds.mockReturnValue( [
			'card',
			'giropay',
			'sofort',
			'sepa_debit',
		] );
	} );

	test( 'Displays "Add payment Method" button, modal is not visible', () => {
		render( <PaymentMethodsSelector /> );

		const addPaymentMethodButton = screen.queryByRole( 'button', {
			name: 'Add payment method',
		} );
		expect( addPaymentMethodButton ).toBeInTheDocument();

		expect(
			screen.queryByText(
				"Increase your store's conversion by offering your customers preferred and convenient payment methods."
			)
		).toBeNull();

		expect(
			screen.queryByRole( 'button', {
				name: 'Add selected',
			} )
		).toBeNull();
	} );

	test( 'Clicking "Add Payment Method" opens the Payment method selection', () => {
		useEnabledPaymentMethodIds.mockReturnValue( [
			[ 'card' ],
			jest.fn( () => {} ),
		] );

		render( <PaymentMethodsSelector /> );

		const addPaymentMethodButton = screen.getByRole( 'button', {
			name: 'Add payment method',
		} );
		user.click( addPaymentMethodButton );

		expect(
			screen.getByText(
				"Increase your store's conversion by offering your customers preferred and convenient payment methods."
			)
		).toBeInTheDocument();

		const paymentMethods = screen.getAllByRole( 'listitem' );
		expect( paymentMethods ).toHaveLength( 3 );

		const giroPayCheckbox = screen.getByRole( 'checkbox', {
			name: 'giropay',
		} );
		expect( giroPayCheckbox ).not.toBeChecked();

		const sofortCheckbox = screen.getByRole( 'checkbox', {
			name: 'Sofort',
		} );
		expect( sofortCheckbox ).not.toBeChecked();

		const sepaCheckbox = screen.getByRole( 'checkbox', {
			name: 'Direct debit payment',
		} );
		expect( sepaCheckbox ).not.toBeChecked();

		expect(
			screen.getByRole( 'button', {
				name: 'Add selected',
			} )
		).toBeInTheDocument();
	} );

	test( 'Payment method selection can be dismissed', () => {
		render( <PaymentMethodsSelector /> );

		user.click(
			screen.getByRole( 'button', {
				name: 'Add payment method',
			} )
		);

		user.click(
			screen.getByRole( 'button', {
				name: 'Cancel',
			} )
		);

		expect( useEnabledPaymentMethodIds()[ 1 ] ).not.toHaveBeenCalled();

		expect(
			screen.queryByRole( 'button', {
				name: 'Cancel',
			} )
		).toBeNull();
	} );

	test( 'Only payment methods that are not enabled are listed', () => {
		useEnabledPaymentMethodIds.mockReturnValue( [
			[ 'card', 'sofort' ],
			jest.fn( () => {} ),
		] );

		render( <PaymentMethodsSelector /> );

		const addPaymentMethodButton = screen.getByRole( 'button', {
			name: 'Add payment method',
		} );
		user.click( addPaymentMethodButton );

		const paymentMethods = screen.getAllByRole( 'listitem' );
		expect( paymentMethods ).toHaveLength( 2 );
		expect(
			screen.queryByRole( 'checkbox', { name: 'Sofort' } )
		).toBeNull();
	} );

	test( 'Selecting payment methods does not update enabled payment methods', () => {
		const updateEnabledPaymentMethodIdsMock = jest.fn( () => {} );
		useEnabledPaymentMethodIds.mockReturnValue( [
			[ 'card', 'sepa_debit' ],
			updateEnabledPaymentMethodIdsMock,
		] );

		render( <PaymentMethodsSelector /> );

		const addPaymentMethodButton = screen.getByRole( 'button', {
			name: 'Add payment method',
		} );
		user.click( addPaymentMethodButton );

		const paymentMethods = screen.getAllByRole( 'listitem' );
		const paymentMethodCheckbox = within( paymentMethods[ 0 ] ).getByRole(
			'checkbox'
		);
		user.click( paymentMethodCheckbox );

		expect( paymentMethodCheckbox ).toBeChecked();

		// closing the modal, to ensure that no methods have been added
		user.click(
			screen.getByRole( 'button', {
				name: 'Cancel',
			} )
		);

		expect( updateEnabledPaymentMethodIdsMock ).not.toHaveBeenCalled();

		// re-opening the modal should present all the checkboxes in the un-checked state
		user.click( addPaymentMethodButton );
		screen.getAllByRole( 'checkbox' ).forEach( ( checkbox ) => {
			expect( checkbox ).not.toBeChecked();
		} );
	} );

	test( 'Disables the "Add selected" button until at least one payment method is checked', () => {
		render( <PaymentMethodsSelector /> );

		const addPaymentMethodButton = screen.getByRole( 'button', {
			name: 'Add payment method',
		} );
		user.click( addPaymentMethodButton );

		expect( screen.getByText( 'Add selected' ) ).toBeDisabled();

		// checking one payment method
		const paymentMethods = screen.getAllByRole( 'listitem' );
		const paymentMethodCheckbox = within( paymentMethods[ 0 ] ).getByRole(
			'checkbox'
		);
		user.click( paymentMethodCheckbox );

		expect( screen.getByText( 'Add selected' ) ).not.toBeDisabled();

		// un-checking the same payment method
		user.click( paymentMethodCheckbox );

		expect( screen.getByText( 'Add selected' ) ).toBeDisabled();
	} );

	test( 'Selecting a payment method and clicking "Add selected" adds the method and closes the modal', () => {
		const updateEnabledPaymentMethodIdsMock = jest.fn( () => {} );
		useEnabledPaymentMethodIds.mockReturnValue( [
			[ 'card', 'sepa_debit' ],
			updateEnabledPaymentMethodIdsMock,
		] );

		render( <PaymentMethodsSelector /> );

		const addPaymentMethodButton = screen.getByRole( 'button', {
			name: 'Add payment method',
		} );
		user.click( addPaymentMethodButton );

		const giroPayCheckbox = screen.getByRole( 'checkbox', {
			name: 'giropay',
		} );
		user.click( giroPayCheckbox );

		const addSelectedButton = screen.getByRole( 'button', {
			name: 'Add selected',
		} );
		user.click( addSelectedButton );
		expect( updateEnabledPaymentMethodIdsMock ).toHaveBeenCalledWith( [
			'card',
			'sepa_debit',
			'giropay',
		] );
		expect(
			screen.queryByRole( 'button', {
				name: 'Add selected',
			} )
		).toBeNull();
	} );
} );
