/** @format */
/**
 * External dependencies
 */
import React from 'react';
import { render, within, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

/**
 * Internal dependencies
 */
import PaymentMethodsCheckboxes from '..';
import PaymentMethodsCheckbox from '../payment-method-checkbox';

describe( 'PaymentMethodsCheckboxes', () => {
	it( 'triggers the onChange when clicking the checkbox', () => {
		const handleChange = jest.fn();

		const upeMethods = [
			[ 'bancontact', true ],
			[ 'giropay', false ],
			[ 'ideal', false ],
			[ 'p24', false ],
			[ 'sepa_debit', false ],
			[ 'sofort', false ],
		];

		render(
			<PaymentMethodsCheckboxes>
				{ upeMethods.map( ( key ) => (
					<PaymentMethodsCheckbox
						key={ key[ 0 ] }
						onChange={ handleChange }
						checked={ key[ 1 ] }
						name={ key[ 0 ] }
						status={ 'active' }
					/>
				) ) }
			</PaymentMethodsCheckboxes>
		);

		const paymentMethods = screen.getAllByRole( 'listitem' );
		const bancontact = within( paymentMethods[ 0 ] );
		const giropay = within( paymentMethods[ 1 ] );
		const ideal = within( paymentMethods[ 2 ] );
		const p24 = within( paymentMethods[ 3 ] );
		const sepa = within( paymentMethods[ 4 ] );
		const sofort = within( paymentMethods[ 5 ] );

		expect( bancontact.getByRole( 'checkbox' ) ).toBeChecked();
		expect( giropay.getByRole( 'checkbox' ) ).not.toBeChecked();
		expect( ideal.getByRole( 'checkbox' ) ).not.toBeChecked();
		expect( p24.getByRole( 'checkbox' ) ).not.toBeChecked();
		expect( sepa.getByRole( 'checkbox' ) ).not.toBeChecked();
		expect( sofort.getByRole( 'checkbox' ) ).not.toBeChecked();

		userEvent.click( bancontact.getByRole( 'checkbox' ) );
		userEvent.click( giropay.getByRole( 'checkbox' ) );
		userEvent.click( ideal.getByRole( 'checkbox' ) );
		userEvent.click( p24.getByRole( 'checkbox' ) );
		userEvent.click( sepa.getByRole( 'checkbox' ) );
		userEvent.click( sofort.getByRole( 'checkbox' ) );

		expect( handleChange ).toHaveBeenCalledTimes( upeMethods.length );

		expect( handleChange ).toHaveBeenNthCalledWith(
			1,
			'bancontact',
			false
		);
		expect( handleChange ).toHaveBeenNthCalledWith( 2, 'giropay', true );
		expect( handleChange ).toHaveBeenNthCalledWith( 3, 'ideal', true );
		expect( handleChange ).toHaveBeenNthCalledWith( 4, 'p24', true );
		expect( handleChange ).toHaveBeenNthCalledWith( 5, 'sepa_debit', true );
		expect( handleChange ).toHaveBeenNthCalledWith( 6, 'sofort', true );
	} );

	it( 'shows the pending notice pill on payment methods with pending statuses', () => {
		const handleChange = () => {};
		const page = render(
			<PaymentMethodsCheckboxes>
				<PaymentMethodsCheckbox
					key={ 'sofort' }
					onChange={ handleChange }
					checked={ 1 }
					name={ 'sofort' }
					status={ 'pending' }
				/>
			</PaymentMethodsCheckboxes>
		);

		expect( page.container ).toContainHTML(
			'<span class="wcpay-pill payment-status-pending">Pending</span>'
		);
	} );

	it( 'can click the checkbox on payment methods with pending statuses', () => {
		const handleChange = jest.fn();
		render(
			<PaymentMethodsCheckboxes>
				<PaymentMethodsCheckbox
					key={ 'sofort' }
					onChange={ handleChange }
					checked={ 0 }
					name={ 'sofort' }
					status={ 'pending' }
				/>
			</PaymentMethodsCheckboxes>
		);

		const sofortCheckbox = screen.getByRole( 'checkbox', {
			name: 'Sofort',
		} );
		expect( sofortCheckbox ).not.toBeChecked();
		userEvent.click( sofortCheckbox );
		expect( handleChange ).toHaveBeenCalledTimes( 1 );
		expect( handleChange ).toHaveBeenNthCalledWith( 1, 'sofort', true );
	} );

	it( 'shows the disabled notice pill on payment methods with disabled statuses', () => {
		const handleChange = () => {};
		const page = render(
			<PaymentMethodsCheckboxes>
				<PaymentMethodsCheckbox
					key={ 'sofort' }
					onChange={ handleChange }
					checked={ 1 }
					name={ 'sofort' }
					status={ 'inactive' }
				/>
			</PaymentMethodsCheckboxes>
		);

		expect( page.container ).toContainHTML(
			'<span class="wcpay-pill payment-status-inactive">Contact WooCommerce Support</span>'
		);
	} );

	it( 'can not click the payment methods checkbox with disabled statuses', () => {
		const handleChange = jest.fn();
		render(
			<PaymentMethodsCheckboxes>
				<PaymentMethodsCheckbox
					key={ 'sofort' }
					onChange={ handleChange }
					checked={ 0 }
					name={ 'sofort' }
					status={ 'inactive' }
				/>
			</PaymentMethodsCheckboxes>
		);
		const sofortCheckbox = screen.getByRole( 'checkbox', {
			name: 'Sofort',
		} );
		expect( sofortCheckbox ).not.toBeChecked();
		userEvent.click( sofortCheckbox );
		expect( handleChange ).toHaveBeenCalledTimes( 0 ); // Because the input is disabled.
		expect( sofortCheckbox ).not.toBeChecked();
	} );

	it( 'doesnt show the pending and disabled notice pill on payment methods with active and unrequested statuses', () => {
		const handleChange = () => {};
		const page = render(
			<PaymentMethodsCheckboxes>
				<PaymentMethodsCheckbox
					key={ 'sofort' }
					onChange={ handleChange }
					checked={ 1 }
					name={ 'sofort' }
					status={ 'active' }
				/>
				<PaymentMethodsCheckbox
					key={ 'giropay' }
					onChange={ handleChange }
					checked={ 1 }
					name={ 'giropay' }
					status={ 'unrequested' }
				/>
			</PaymentMethodsCheckboxes>
		);

		expect( page.container ).not.toContainHTML(
			'<span class="wcpay-pill payment-status-pending">Pending</span>'
		);
		expect( page.container ).not.toContainHTML(
			'<span class="wcpay-pill payment-status-inactive">Contact WooCommerce Support</span>'
		);
	} );
} );
