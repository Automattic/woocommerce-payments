/**
 * External dependencies
 */
import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react';

/**
 * Internal dependencies
 */
import { fields, togglePaymentMethod } from '../fields';

it( 'preserves other enabled payment methods when toggling a method', () => {
	expect(
		togglePaymentMethod( [ 'card', 'ideal', 'klarna' ], 'ideal', false )
	).toEqual( [ 'card', 'klarna' ] );
	expect( togglePaymentMethod( [ 'card', 'ideal' ], 'ideal', true ) ).toEqual(
		[ 'card', 'ideal' ]
	);
} );

it( 'requires confirmation before enabling manual capture', () => {
	const field = fields.find(
		( item ) => item.id === 'is_manual_capture_enabled'
	);
	if ( ! field ) throw new Error( 'Manual capture field is missing' );
	const Edit = field.Edit as React.ComponentType< {
		data: Record< string, unknown >;
		field: typeof field;
		onChange: jest.Mock;
	} >;
	const onChange = jest.fn();
	render(
		<Edit
			data={ { is_manual_capture_enabled: false } }
			field={ field }
			onChange={ onChange }
		/>
	);
	fireEvent.click( screen.getByRole( 'checkbox' ) );
	expect( onChange ).not.toHaveBeenCalled();
	fireEvent.click( screen.getByRole( 'button', { name: 'Enable' } ) );
	expect( onChange ).toHaveBeenCalledWith( {
		is_manual_capture_enabled: true,
	} );
} );
