/**
 * External dependencies
 */
import React from 'react';
import { fireEvent, render, screen } from '@testing-library/react';

/**
 * Internal dependencies
 */
import { BorderRadiusControl } from '../express-checkout-controls';

describe( 'BorderRadiusControl', () => {
	const renderControl = ( onChange = jest.fn() ) => {
		render(
			<BorderRadiusControl
				data={ { payment_request_button_border_radius: 4 } }
				onChange={ onChange }
			/>
		);
		return onChange;
	};

	it( 'saves the radius typed in the number input', () => {
		const onChange = renderControl();

		fireEvent.change(
			screen.getByLabelText( 'Border radius, number input' ),
			{ target: { value: '12' } }
		);

		expect( onChange ).toHaveBeenLastCalledWith( {
			payment_request_button_border_radius: 12,
		} );
	} );

	it( 'keeps the radius between 0 and 30', () => {
		const onChange = renderControl();
		const input = screen.getByLabelText( 'Border radius, number input' );

		fireEvent.change( input, { target: { value: '45' } } );
		expect( onChange ).toHaveBeenLastCalledWith( {
			payment_request_button_border_radius: 30,
		} );

		fireEvent.change( input, { target: { value: '' } } );
		expect( onChange ).toHaveBeenLastCalledWith( {
			payment_request_button_border_radius: 0,
		} );
	} );
} );
