/** @format */
/**
 * External dependencies
 */
import { render, within } from '@testing-library/react';
import user from '@testing-library/user-event';

/**
 * Internal dependencies
 */
import PaymentMethodsSelector from '..';
import { useEnabledPaymentMethodIds } from 'data';

jest.mock( 'data', () => ( { useEnabledPaymentMethodIds: jest.fn() } ) );

describe( 'PaymentMethodsSelector', () => {
	beforeEach( () => {
		useEnabledPaymentMethodIds.mockReturnValue( {
			enabledPaymentMethodIds: [],
			updateEnabledPaymentMethodIds: jest.fn(),
		} );
	} );

	test( 'Displays "Add payment Method" button, modal is not visible', () => {
		const { getByRole, queryByText, queryByRole } = render(
			<PaymentMethodsSelector />
		);

		const addPaymentMethodButton = getByRole( 'button', {
			name: 'Add payment method',
		} );
		expect( addPaymentMethodButton ).toBeInTheDocument();

		expect(
			queryByText(
				"Increase your store's conversion by offering your customers preferred and convenient payment methods."
			)
		).toBeNull();

		expect(
			queryByRole( 'button', {
				name: 'Add selected',
			} )
		).toBeNull();
	} );

	test( 'Clicking "Add Payment Method" opens the Payment method selection', () => {
		useEnabledPaymentMethodIds.mockReturnValue( {
			enabledPaymentMethodIds: [
				'woocommerce_payments',
				'woocommerce_payments_sepa',
			],
			updateEnabledPaymentMethodIds: jest.fn( () => {} ),
		} );

		const { getByText, getByRole, getAllByRole } = render(
			<PaymentMethodsSelector />
		);

		const addPaymentMethodButton = getByRole( 'button', {
			name: 'Add payment method',
		} );
		user.click( addPaymentMethodButton );

		expect(
			getByText(
				"Increase your store's conversion by offering your customers preferred and convenient payment methods."
			)
		).toBeInTheDocument();

		const paymentMethods = getAllByRole( 'listitem' );
		expect( paymentMethods ).toHaveLength( 3 );

		const giroPay = within( paymentMethods[ 0 ] );
		expect( giroPay.getByRole( 'checkbox' ) ).not.toBeChecked();

		const sofort = within( paymentMethods[ 1 ] );
		expect( sofort.getByRole( 'checkbox' ) ).not.toBeChecked();

		const sepa = within( paymentMethods[ 2 ] );
		expect( sepa.getByRole( 'checkbox' ) ).toBeChecked();

		expect(
			getByRole( 'button', {
				name: 'Add selected',
			} )
		).toBeInTheDocument();
	} );

	test( 'Payment method selection can be dismissed', () => {
		const { getByRole, queryByRole } = render( <PaymentMethodsSelector /> );

		user.click(
			getByRole( 'button', {
				name: 'Add payment method',
			} )
		);

		user.click(
			getByRole( 'button', {
				name: 'Cancel',
			} )
		);

		expect(
			useEnabledPaymentMethodIds().updateEnabledPaymentMethodIds
		).not.toHaveBeenCalled();

		expect(
			queryByRole( 'button', {
				name: 'Cancel',
			} )
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

		const { getByRole, getAllByRole } = render(
			<PaymentMethodsSelector />
		);

		const addPaymentMethodButton = getByRole( 'button', {
			name: 'Add payment method',
		} );
		user.click( addPaymentMethodButton );

		const paymentMethods = getAllByRole( 'listitem' );
		const giroPayCheckbox = within( paymentMethods[ 0 ] ).getByRole(
			'checkbox'
		);
		user.click( giroPayCheckbox );

		expect( giroPayCheckbox ).toBeChecked();
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

		const { getByRole, getAllByRole, queryByRole } = render(
			<PaymentMethodsSelector />
		);

		const addPaymentMethodButton = getByRole( 'button', {
			name: 'Add payment method',
		} );
		user.click( addPaymentMethodButton );

		const paymentMethods = getAllByRole( 'listitem' );
		const giroPayCheckbox = within( paymentMethods[ 0 ] ).getByRole(
			'checkbox'
		);
		user.click( giroPayCheckbox );

		const addSelectedButton = getByRole( 'button', {
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
			queryByRole( 'button', {
				name: 'Add selected',
			} )
		).toBeNull();
	} );
} );
