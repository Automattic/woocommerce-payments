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
} from 'data';

jest.mock( 'data', () => ( {
	useEnabledPaymentMethodIds: jest.fn(),
	useGetAvailablePaymentMethodIds: jest.fn(),
} ) );

describe( 'PaymentMethodsSelector', () => {
	beforeEach( () => {
		useEnabledPaymentMethodIds.mockReturnValue( {
			enabledPaymentMethodIds: [],
			updateEnabledPaymentMethodIds: jest.fn(),
		} );
		useGetAvailablePaymentMethodIds.mockReturnValue( [
			'woocommerce_payments',
			'woocommerce_payments_giropay',
			'woocommerce_payments_sofort',
			'woocommerce_payments_sepa',
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
		useEnabledPaymentMethodIds.mockReturnValue( {
			enabledPaymentMethodIds: [ 'woocommerce_payments' ],
			updateEnabledPaymentMethodIds: jest.fn( () => {} ),
		} );

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
			name: 'GiroPay',
		} );
		expect( giroPayCheckbox ).not.toBeChecked();

		const sofortCheckbox = screen.getByRole( 'checkbox', {
			name: 'Sofort',
		} );
		expect( sofortCheckbox ).not.toBeChecked();

		const sepaCheckbox = screen.getByRole( 'checkbox', {
			name: 'Direct Debit Payments',
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

		expect(
			useEnabledPaymentMethodIds().updateEnabledPaymentMethodIds
		).not.toHaveBeenCalled();

		expect(
			screen.queryByRole( 'button', {
				name: 'Cancel',
			} )
		).toBeNull();
	} );

	test( 'Only payment methods that are not enabled are listed', () => {
		useEnabledPaymentMethodIds.mockReturnValue( {
			enabledPaymentMethodIds: [
				'woocommerce_payments',
				'woocommerce_payments_sofort',
			],
			updateEnabledPaymentMethodIds: jest.fn( () => {} ),
		} );

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
		useEnabledPaymentMethodIds.mockReturnValue( {
			enabledPaymentMethodIds: [
				'woocommerce_payments',
				'woocommerce_payments_sepa',
			],
			updateEnabledPaymentMethodIds: jest.fn( () => {} ),
		} );

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
		expect(
			useEnabledPaymentMethodIds().updateEnabledPaymentMethodIds
		).not.toHaveBeenCalled();
	} );

	test( 'Selecting a payment method and clicking "Add selected" adds the method and closes the modal', () => {
		useEnabledPaymentMethodIds.mockReturnValue( {
			enabledPaymentMethodIds: [
				'woocommerce_payments',
				'woocommerce_payments_sepa',
			],
			updateEnabledPaymentMethodIds: jest.fn( () => {} ),
		} );

		render( <PaymentMethodsSelector /> );

		const addPaymentMethodButton = screen.getByRole( 'button', {
			name: 'Add payment method',
		} );
		user.click( addPaymentMethodButton );

		const giroPayCheckbox = screen.getByRole( 'checkbox', {
			name: 'GiroPay',
		} );
		user.click( giroPayCheckbox );

		const addSelectedButton = screen.getByRole( 'button', {
			name: 'Add selected',
		} );
		user.click( addSelectedButton );
		expect(
			useEnabledPaymentMethodIds().updateEnabledPaymentMethodIds
		).toHaveBeenCalledWith( [
			'woocommerce_payments',
			'woocommerce_payments_sepa',
			'woocommerce_payments_giropay',
		] );
		expect(
			screen.queryByRole( 'button', {
				name: 'Add selected',
			} )
		).toBeNull();
	} );
} );
