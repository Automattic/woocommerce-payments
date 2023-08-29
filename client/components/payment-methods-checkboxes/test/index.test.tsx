/** @format */
/**
 * External dependencies
 */
import React, { ReactNode } from 'react';
import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

/**
 * Internal dependencies
 */
import PaymentMethodsCheckboxes from '..';
import PaymentMethodsCheckbox from '../payment-method-checkbox';
import { upeCapabilityStatuses } from '../../../additional-methods-setup/constants';
import { act } from 'react-dom/test-utils';

jest.mock( '@woocommerce/components', () => {
	return {
		Pill: ( {
			className,
			children,
		}: {
			className: string;
			children: ReactNode;
		} ): React.ReactElement => (
			<span className={ className }>{ children }</span>
		),
	};
} );

describe( 'PaymentMethodsCheckboxes', () => {
	it( 'triggers the onChange when clicking the checkbox', () => {
		const handleChange = jest.fn();

		const upeMethods = [
			[ 'bancontact', true ],
			[ 'eps', false ],
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
						key={ key[ 0 ] as React.Key }
						onChange={ handleChange }
						checked={ key[ 1 ] as boolean }
						name={ key[ 0 ] as string }
						status={ upeCapabilityStatuses.ACTIVE }
						fees={ '' }
						required={ false }
						locked={ false }
						isPoComplete={ false }
						isPoEnabled={ false }
					/>
				) ) }
			</PaymentMethodsCheckboxes>
		);

		const paymentMethods = screen.getAllByRole( 'listitem' );
		const bancontact = within( paymentMethods[ 0 ] );
		const eps = within( paymentMethods[ 1 ] );
		const giropay = within( paymentMethods[ 2 ] );
		const ideal = within( paymentMethods[ 3 ] );
		const p24 = within( paymentMethods[ 4 ] );
		const sepa = within( paymentMethods[ 5 ] );
		const sofort = within( paymentMethods[ 6 ] );

		expect( bancontact.getByRole( 'checkbox' ) ).toBeChecked();
		expect( eps.getByRole( 'checkbox' ) ).not.toBeChecked();
		expect( giropay.getByRole( 'checkbox' ) ).not.toBeChecked();
		expect( ideal.getByRole( 'checkbox' ) ).not.toBeChecked();
		expect( p24.getByRole( 'checkbox' ) ).not.toBeChecked();
		expect( sepa.getByRole( 'checkbox' ) ).not.toBeChecked();
		expect( sofort.getByRole( 'checkbox' ) ).not.toBeChecked();

		jest.useFakeTimers();
		act( () => {
			userEvent.click( bancontact.getByRole( 'checkbox' ) );
			userEvent.click( eps.getByRole( 'checkbox' ) );
			userEvent.click( giropay.getByRole( 'checkbox' ) );
			userEvent.click( ideal.getByRole( 'checkbox' ) );
			userEvent.click( p24.getByRole( 'checkbox' ) );
			userEvent.click( sepa.getByRole( 'checkbox' ) );
			userEvent.click( sofort.getByRole( 'checkbox' ) );
			jest.runOnlyPendingTimers();
		} );

		expect( handleChange ).toHaveBeenCalledTimes( upeMethods.length );

		expect( handleChange ).toHaveBeenNthCalledWith(
			1,
			'bancontact',
			false
		);
		expect( handleChange ).toHaveBeenNthCalledWith( 3, 'giropay', true );
		expect( handleChange ).toHaveBeenNthCalledWith( 4, 'ideal', true );
		expect( handleChange ).toHaveBeenNthCalledWith( 5, 'p24', true );
		expect( handleChange ).toHaveBeenNthCalledWith( 6, 'sepa_debit', true );
		expect( handleChange ).toHaveBeenNthCalledWith( 7, 'sofort', true );
		jest.useRealTimers();
	} );

	it( 'can click the checkbox on payment methods with pending statuses', () => {
		const handleChange = jest.fn();
		render(
			<PaymentMethodsCheckboxes>
				<PaymentMethodsCheckbox
					key={ 'sofort' }
					onChange={ handleChange }
					checked={ false }
					name={ 'sofort' }
					status={ upeCapabilityStatuses.PENDING_APPROVAL }
					fees={ '' }
					required={ false }
					locked={ false }
					isPoComplete={ false }
					isPoEnabled={ false }
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
			jest.runOnlyPendingTimers();
		} );
		expect( handleChange ).toHaveBeenCalledTimes( 1 );
		expect( handleChange ).toHaveBeenNthCalledWith( 1, 'sofort', true );
		jest.useRealTimers();
	} );

	it( 'shows the required label on payment methods which are required', () => {
		const handleChange = jest.fn();
		const page = render(
			<PaymentMethodsCheckboxes>
				<PaymentMethodsCheckbox
					key={ 'card' }
					onChange={ handleChange }
					checked={ true }
					name={ 'card' }
					required={ true }
					status={ upeCapabilityStatuses.ACTIVE }
					fees={ '' }
					locked={ false }
					isPoComplete={ false }
					isPoEnabled={ false }
				/>
			</PaymentMethodsCheckboxes>
		);

		expect( page.container ).toContainHTML(
			'<span class="payment-method-checkbox__required-label">Required</span>'
		);
	} );

	it( 'shows the disabled notice pill on payment methods with disabled statuses', () => {
		const handleChange = jest.fn();
		const page = render(
			<PaymentMethodsCheckboxes>
				<PaymentMethodsCheckbox
					key={ 'sofort' }
					onChange={ handleChange }
					checked={ true }
					name={ 'sofort' }
					status={ upeCapabilityStatuses.INACTIVE }
					fees={ '' }
					required={ false }
					locked={ false }
					isPoComplete={ false }
					isPoEnabled={ false }
				/>
			</PaymentMethodsCheckboxes>
		);

		expect( page.container ).toContainHTML(
			'<span class="wcpay-pill payment-status-inactive">More information needed</span>'
		);
	} );

	it( 'can not click the payment methods checkbox that are locked', () => {
		const handleChange = jest.fn();
		render(
			<PaymentMethodsCheckboxes>
				<PaymentMethodsCheckbox
					key={ 'card' }
					onChange={ handleChange }
					checked={ false }
					name={ 'card' }
					locked={ true }
					status={ upeCapabilityStatuses.ACTIVE }
					fees={ '' }
					required={ false }
					isPoComplete={ false }
					isPoEnabled={ false }
				/>
			</PaymentMethodsCheckboxes>
		);
		const cardCheckbox = screen.getByRole( 'checkbox', {
			name: 'Credit / Debit card',
		} );
		expect( cardCheckbox ).not.toBeChecked();
		userEvent.click( cardCheckbox );
		expect( handleChange ).toHaveBeenCalledTimes( 0 ); // Because the input is disabled.
		expect( cardCheckbox ).not.toBeChecked();
	} );

	it( 'can not click the payment methods checkbox with disabled statuses', () => {
		const handleChange = jest.fn();
		render(
			<PaymentMethodsCheckboxes>
				<PaymentMethodsCheckbox
					key={ 'sofort' }
					onChange={ handleChange }
					checked={ false }
					name={ 'sofort' }
					status={ upeCapabilityStatuses.INACTIVE }
					fees={ '' }
					required={ false }
					locked={ false }
					isPoComplete={ false }
					isPoEnabled={ false }
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

	it( "doesn't show the disabled notice pill on payment methods with active and unrequested statuses", () => {
		const handleChange = jest.fn();
		render(
			<PaymentMethodsCheckboxes>
				<PaymentMethodsCheckbox
					key={ 'sofort' }
					onChange={ handleChange }
					checked={ true }
					name={ 'sofort' }
					status={ upeCapabilityStatuses.ACTIVE }
					fees={ '' }
					required={ false }
					locked={ false }
					isPoComplete={ false }
					isPoEnabled={ false }
				/>
				<PaymentMethodsCheckbox
					key={ 'giropay' }
					onChange={ handleChange }
					checked={ true }
					name={ 'giropay' }
					status={ upeCapabilityStatuses.UNREQUESTED }
					fees={ '' }
					required={ false }
					locked={ false }
					isPoComplete={ false }
					isPoEnabled={ false }
				/>
			</PaymentMethodsCheckboxes>
		);

		// Test that the Contact support pill content isn't shown
		expect(
			screen.queryByText( 'Contact WooCommerce Support' )
		).not.toBeInTheDocument();
	} );
} );
