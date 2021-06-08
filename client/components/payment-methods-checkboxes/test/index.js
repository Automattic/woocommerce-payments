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

		render(
			<PaymentMethodsCheckboxes>
				<PaymentMethodsCheckbox
					onChange={ handleChange }
					checked={ true }
					fees=""
					name="sepa_debit"
				/>
				<PaymentMethodsCheckbox
					onChange={ handleChange }
					checked={ false }
					fees=""
					name="sofort"
				/>
				<PaymentMethodsCheckbox
					onChange={ handleChange }
					checked={ false }
					fees=""
					name="giropay"
				/>
			</PaymentMethodsCheckboxes>
		);

		const paymentMethods = screen.getAllByRole( 'listitem' );
		const sepa = within( paymentMethods[ 0 ] );
		const sofort = within( paymentMethods[ 1 ] );
		const giropay = within( paymentMethods[ 2 ] );

		expect( sepa.getByRole( 'checkbox' ) ).toBeChecked();
		expect( sofort.getByRole( 'checkbox' ) ).not.toBeChecked();
		expect( giropay.getByRole( 'checkbox' ) ).not.toBeChecked();

		userEvent.click( sepa.getByRole( 'checkbox' ) );
		userEvent.click( giropay.getByRole( 'checkbox' ) );

		expect( handleChange ).toHaveBeenCalledTimes( 2 );
		expect( handleChange ).toHaveBeenNthCalledWith(
			1,
			'sepa_debit',
			false
		);
		expect( handleChange ).toHaveBeenNthCalledWith( 2, 'giropay', true );
	} );
} );
