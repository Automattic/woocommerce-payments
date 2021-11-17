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
import { upeCapabilityStatuses } from '../../../additional-methods-setup/constants';
import { act } from 'react-dom/test-utils';

describe( 'PaymentMethodsCheckboxes', () => {
	it( 'triggers the onChange when clicking the checkbox', () => {
		const handleChange = jest.fn();

		const upeMethods = [
			[ 'Bancontact', true ],
			[ 'giropay', false ],
			[ 'iDEAL', false ],
			[ 'Przelewy24 (P24)', false ],
			[ 'SEPA Direct Debit', false ],
			[ 'Sofort', false ],
		];

		render(
			<PaymentMethodsCheckboxes>
				{ upeMethods.map( ( key ) => (
					<PaymentMethodsCheckbox
						key={ key[ 0 ] }
						onChange={ handleChange }
						checked={ key[ 1 ] }
						name={ key[ 0 ] }
						status={ upeCapabilityStatuses.ACTIVE }
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

		jest.useFakeTimers();
		act( () => {
			userEvent.click( bancontact.getByRole( 'checkbox' ) );
			userEvent.click( giropay.getByRole( 'checkbox' ) );
			userEvent.click( ideal.getByRole( 'checkbox' ) );
			userEvent.click( p24.getByRole( 'checkbox' ) );
			userEvent.click( sepa.getByRole( 'checkbox' ) );
			userEvent.click( sofort.getByRole( 'checkbox' ) );
			jest.runAllTimers();
		} );

		expect( handleChange ).toHaveBeenCalledTimes( upeMethods.length );

		expect( handleChange ).toHaveBeenNthCalledWith(
			1,
			'Bancontact',
			false
		);
		expect( handleChange ).toHaveBeenNthCalledWith( 2, 'giropay', true );
		expect( handleChange ).toHaveBeenNthCalledWith( 3, 'iDEAL', true );
		expect( handleChange ).toHaveBeenNthCalledWith(
			4,
			'Przelewy24 (P24)',
			true
		);
		expect( handleChange ).toHaveBeenNthCalledWith(
			5,
			'SEPA Direct Debit',
			true
		);
		expect( handleChange ).toHaveBeenNthCalledWith( 6, 'Sofort', true );
		jest.useRealTimers();
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
					status={ upeCapabilityStatuses.PENDING_APPROVAL }
				/>
			</PaymentMethodsCheckboxes>
		);

		const sofortCheckbox = screen.getByRole( 'checkbox', {
			name: 'Sofort',
		} );
		expect( sofortCheckbox ).not.toBeChecked();
		jest.useFakeTimers();
		act( () => {
			userEvent.click( sofortCheckbox );
			jest.runAllTimers();
		} );
		expect( handleChange ).toHaveBeenCalledTimes( 1 );
		expect( handleChange ).toHaveBeenNthCalledWith( 1, 'sofort', true );
		jest.useRealTimers();
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
					status={ upeCapabilityStatuses.INACTIVE }
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
					status={ upeCapabilityStatuses.INACTIVE }
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

	it( 'doesnt show the disabled notice pill on payment methods with active and unrequested statuses', () => {
		const handleChange = () => {};
		const page = render(
			<PaymentMethodsCheckboxes>
				<PaymentMethodsCheckbox
					key={ 'sofort' }
					onChange={ handleChange }
					checked={ 1 }
					name={ 'sofort' }
					status={ upeCapabilityStatuses.ACTIVE }
				/>
				<PaymentMethodsCheckbox
					key={ 'giropay' }
					onChange={ handleChange }
					checked={ 1 }
					name={ 'giropay' }
					status={ upeCapabilityStatuses.UNREQUESTED }
				/>
			</PaymentMethodsCheckboxes>
		);

		expect( page.container ).not.toContainHTML(
			'<span class="wcpay-pill payment-status-inactive">Contact WooCommerce Support</span>'
		);
	} );
} );
