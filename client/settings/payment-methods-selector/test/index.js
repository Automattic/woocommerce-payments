/** @format */
/**
 * External dependencies
 */
import { fireEvent, render, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

/**
 * Internal dependencies
 */
import PaymentMethodsSelector from '..';
import { addSelectedPaymentMethods } from 'data';

jest.mock( 'data', () => ( { addSelectedPaymentMethods: jest.fn() } ) );

describe( 'PaymentMethodsSelector', () => {
	test( 'renders a modal window', () => {
		const { getAllByRole, getByText } = render(
			<PaymentMethodsSelector />
		);

		expect( getByText( 'Add payment methods' ) ).toBeTruthy();
		expect(
			getByText(
				"Increase your store's conversion by offering your customers preferred and convenient payment methods."
			)
		).toBeTruthy();

		const paymentMethods = getAllByRole( 'listitem' );
		expect( paymentMethods ).toHaveLength( 3 );

		const giroPay = within( paymentMethods[ 0 ] );
		expect( giroPay.getByRole( 'checkbox' ) ).toBeTruthy();
		expect( giroPay.getByText( 'GiroPay' ) ).toBeTruthy();
		expect( giroPay.getByText( 'missing fees' ) ).toBeTruthy();

		const sofort = within( paymentMethods[ 1 ] );
		expect( sofort.getByRole( 'checkbox' ) ).toBeTruthy();
		expect( sofort.getByText( 'Sofort' ) ).toBeTruthy();
		expect( sofort.getByText( 'missing fees' ) ).toBeTruthy();

		const sepa = within( paymentMethods[ 2 ] );
		expect( sepa.getByRole( 'checkbox' ) ).toBeTruthy();
		expect( sepa.getByText( 'Direct Debit Payments' ) ).toBeTruthy();
		expect( sepa.getByText( 'missing fees' ) ).toBeTruthy();

		expect( getByText( 'Add selected' ) ).toBeTruthy();
		expect( getByText( 'Cancel' ) ).toBeTruthy();
	} );

	test( 'can be dismissed', () => {
		const handleClose = jest.fn();

		const { getByText } = render(
			<PaymentMethodsSelector onClose={ handleClose } />
		);

		fireEvent.click( getByText( 'Cancel' ) );

		expect( handleClose ).toHaveBeenCalledTimes( 1 );
	} );

	test( 'allows to add selected payment methods', () => {
		const handleClose = jest.fn();

		const { getAllByRole, getByText } = render(
			<PaymentMethodsSelector onClose={ handleClose } />
		);

		const paymentMethods = getAllByRole( 'listitem' );
		const giroPay = within( paymentMethods[ 0 ] );
		const sofort = within( paymentMethods[ 1 ] );

		userEvent.click( giroPay.getByRole( 'checkbox' ) );
		userEvent.click( sofort.getByRole( 'checkbox' ) );

		fireEvent.click( getByText( 'Add selected' ) );

		expect( addSelectedPaymentMethods ).toHaveBeenCalledTimes( 1 );
		expect( addSelectedPaymentMethods ).toHaveBeenCalledWith( [
			'woocommerce_payments_giropay',
			'woocommerce_payments_sofort',
		] );

		expect( handleClose ).toHaveBeenCalledTimes( 1 );
	} );

	test( 'allows to preselect payment methods', () => {
		const { getAllByRole } = render(
			<PaymentMethodsSelector
				enabledPaymentMethods={ [ 'woocommerce_payments_giropay' ] }
			/>
		);

		const paymentMethods = getAllByRole( 'listitem' );
		expect( paymentMethods ).toHaveLength( 3 );

		const giroPay = within( paymentMethods[ 0 ] );
		expect( giroPay.getByRole( 'checkbox' ).checked ).toBeTruthy();

		const sofort = within( paymentMethods[ 1 ] );
		expect( sofort.getByRole( 'checkbox' ).checked ).toBeFalsy();

		const sepa = within( paymentMethods[ 2 ] );
		expect( sepa.getByRole( 'checkbox' ).checked ).toBeFalsy();
	} );
} );
